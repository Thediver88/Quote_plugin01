// @ts-check
/**
 * The request flow: cart link, form, and the order it creates (see tests/e2e/seed.php).
 * Each test gets a fresh browser context, so a fresh, empty cart.
 */
const { test, expect } = require( '@playwright/test' );

const TILE = '/product/sample-tile/';

/** Put the tile in the cart, as the add-to-cart form would. */
async function addTile( page, qty = '2', extra = {} ) {
	await page.goto( TILE );
	const id = String( await page.locator( 'form.cart [name="add-to-cart"]' ).first().getAttribute( 'value' ) );
	await page.request.post( TILE, { form: { 'add-to-cart': id, quantity: qty, ...extra } } );
}

async function adminLogin( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
}

/** Fill the form and send it. */
async function submitRequest( page, over = {} ) {
	const v = {
		name: 'Ada Lovelace',
		email: 'ada@example.com',
		address: 'Flat 10, Example Street 1',
		postcode: 'SW1A 1AA',
		country: 'GB',
		message: 'Need it by March.',
		...over,
	};
	await page.goto( '/request-a-quote/' );
	await page.locator( '#qtdesk-name' ).fill( v.name );
	await page.locator( '#qtdesk-email' ).fill( v.email );
	await page.locator( '#qtdesk-address' ).fill( v.address );
	await page.locator( '#qtdesk-postcode' ).fill( v.postcode );
	await page.locator( '#qtdesk-country' ).selectOption( v.country );
	await page.locator( '#qtdesk-message' ).fill( v.message );
	await page.locator( 'button[name="qtdesk_submit"]' ).click();
	await page.waitForLoadState( 'load' );
}

async function resetCounter( page ) {
	await page.goto( '/?qtdesk_reset_counter=1' );
}

test.describe( 'Cart link', () => {
	test( 'classic cart shows the link only when the cart has items', async ( { page } ) => {
		await page.goto( '/classic-cart/' );
		await expect( page.locator( 'a.qtdesk-quote-link' ) ).toHaveCount( 0 );
		await addTile( page );
		await page.goto( '/classic-cart/' );
		const link = page.locator( 'a.qtdesk-quote-link' );
		await expect( link ).toHaveText( 'Request a quote' );
		await expect( link ).toHaveAttribute( 'href', /request-a-quote/ );
	} );

	test( 'block cart shows the link and removes it when the cart is emptied without a reload', async ( { page } ) => {
		await addTile( page );
		await page.goto( '/cart/' );
		const link = page.locator( 'a.qtdesk-quote-link' );
		await expect( link ).toBeVisible();
		await page.locator( 'button.wc-block-cart-item__remove-link' ).first().click();
		await expect( link ).toHaveCount( 0 );
	} );
} );

test.describe( 'Request form', () => {
	test( 'an empty cart gets a friendly message and no form', async ( { page } ) => {
		await page.goto( '/request-a-quote/' );
		await expect( page.locator( 'body' ) ).toContainText( 'Your cart is empty' );
		await expect( page.locator( 'form.qtdesk-form' ) ).toHaveCount( 0 );
	} );

	test( 'missing fields show errors, linked to the fields, and keep what was typed', async ( { page } ) => {
		await addTile( page );
		await page.goto( '/request-a-quote/' );
		await page.locator( '#qtdesk-name' ).fill( 'Only A Name' );
		await page.locator( 'button[name="qtdesk_submit"]' ).click();
		await expect( page.locator( '#qtdesk-errors' ) ).toBeVisible();
		await expect( page.locator( '#qtdesk-email' ) ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page.locator( '#qtdesk-email-error' ) ).toBeVisible();
		await expect( page.locator( '#qtdesk-name' ) ).toHaveValue( 'Only A Name' );
	} );

	test( 'a form without its security token is refused and nothing is created', async ( { page } ) => {
		await addTile( page );
		await page.goto( '/request-a-quote/' );
		await page.locator( '[name="_qtdesk_nonce"]' ).evaluate( ( n ) => n.remove() );
		await page.locator( '#qtdesk-name' ).fill( 'Ada Lovelace' );
		await page.locator( '#qtdesk-email' ).fill( 'ada@example.com' );
		await page.locator( '#qtdesk-address' ).fill( 'Somewhere 1' );
		await page.locator( 'button[name="qtdesk_submit"]' ).click();
		await expect( page.locator( '#qtdesk-errors' ) ).toContainText( 'expired' );
		await page.goto( '/classic-cart/' );
		await expect( page.locator( '.cart_item' ) ).toHaveCount( 1 ); // The cart is untouched.
	} );

	test( 'a filled honeypot looks like success but creates nothing', async ( { page } ) => {
		await addTile( page );
		await page.goto( '/request-a-quote/' );
		await page.locator( '[name="qtdesk_website"]' ).evaluate( ( i ) => {
			i.value = 'http://spam.example';
		} );
		await page.locator( '#qtdesk-name' ).fill( 'Bot' );
		await page.locator( 'button[name="qtdesk_submit"]' ).click();
		await expect( page.locator( 'body' ) ).toContainText( 'Thank you' );
		await page.goto( '/classic-cart/' );
		await expect( page.locator( '.cart_item' ) ).toHaveCount( 1 ); // A real request would have emptied it.
	} );

	test( 'a request creates a Quote requested order with the cart lines and data from other plugins, and leaves stock alone', async ( { page } ) => {
		await resetCounter( page );
		await addTile( page, '2', { qtdesk_cartdata: '1' } );
		await submitRequest( page, { email: 'lines@example.com', name: 'Lena Lines' } );
		await expect( page.locator( 'body' ) ).toContainText( 'Thank you' );

		await page.goto( '/classic-cart/' );
		await expect( page.locator( 'body' ) ).toContainText( 'Your cart is currently empty' );

		await page.goto( TILE );
		await expect( page.locator( '.stock' ).first() ).toContainText( '50 in stock' );

		await adminLogin( page );
		await page.goto( '/wp-admin/admin.php?page=wc-orders&status=wc-qtdesk-requested&s=lines%40example.com' );
		const row = page.locator( 'tbody#the-list tr' ).filter( { hasText: 'Lena Lines' } ).first();
		await expect( row ).toBeVisible();
		await row.locator( 'a.order-view' ).first().click();
		await page.waitForLoadState( 'load' );
		await expect( page.locator( '#order_data' ) ).toContainText( 'Quote requested' );
		await expect( page.locator( '#order_line_items' ) ).toContainText( 'Sample Tile' );
		await expect( page.locator( '#order_line_items' ) ).toContainText( 'Test note' ); // Data stored by another plugin arrived.
		await expect( page.locator( '#order_line_items' ) ).toContainText( 'carried' );
		await expect( page.locator( 'body' ) ).toContainText( 'Need it by March.' );
	} );
} );

test.describe( 'Cart rules of other plugins', () => {
	test( 'a rule that blocks checkout also blocks the request, and nothing is created', async ( { page, context, baseURL } ) => {
		await resetCounter( page );
		await addTile( page );
		await context.addCookies( [ { name: 'qtdesk_block_cart', value: '1', url: baseURL } ] );
		await submitRequest( page, { email: 'block@example.com' } );
		await expect( page.locator( '#qtdesk-errors' ) ).toContainText( 'needs attention' );
		await context.clearCookies( { name: 'qtdesk_block_cart' } );
		await page.goto( '/?qtdesk_dump=1' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'block@example.com' );
	} );
} );

test.describe( 'Shop-wide counter', () => {
	test( 'counts 1, 2, 3 on an empty hour without an object cache', async ( { page } ) => {
		await resetCounter( page );
		await page.goto( '/?qtdesk_bump=1' );
		await expect( page.locator( 'body' ) ).toContainText( 'bump:1,2,3' );
		await resetCounter( page );
	} );
} );

test.describe( 'Hourly limits', () => {
	test( 'over the cap requests are still saved; over the ceiling they are refused', async ( { page } ) => {
		await adminLogin( page );
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=qtdesk' );
		await page.locator( '#mainform' ).evaluate( ( f ) => {
			f.noValidate = true;
		} );
		await page.locator( '#qtdesk_global_cap' ).fill( '1' );
		const save = page.locator( 'button[name="save"]' );
		await save.evaluate( ( b ) => {
			b.removeAttribute( 'disabled' );
			b.removeAttribute( 'aria-disabled' );
		} );
		await Promise.all( [ page.waitForNavigation(), save.click( { force: true } ) ] );

		const shopper = await page.context().browser().newContext( { baseURL: page.url().split( '/wp-admin' )[ 0 ] } );
		const guest = await shopper.newPage();
		await resetCounter( guest );

		for ( const n of [ 1, 2 ] ) {
			await addTile( guest );
			await submitRequest( guest, { email: `cap${ n }@example.com` } );
			await expect( guest.locator( 'body' ) ).toContainText( 'Thank you' ); // 1 = normal, 2 = over the cap, saved quietly.
		}
		await addTile( guest );
		await submitRequest( guest, { email: 'cap3@example.com' } );
		await expect( guest.locator( '#qtdesk-errors' ) ).toContainText( 'We cannot take more requests' ); // Over the ceiling.
		await shopper.close();
		await page.goto( '/?qtdesk_dump=1' );
		const dump = await page.locator( 'body' ).innerText();
		expect( dump ).toContain( 'cap1@example.com|qtdesk||1|GB' ); // Within the cap: normal.
		expect( dump ).toContain( 'cap2@example.com|qtdesk|yes|1|GB' ); // Over the cap: saved quietly, tagged.
		expect( dump ).not.toContain( 'cap3@example.com' ); // Over the ceiling: nothing created.

		// Put the default back for any later test.
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=qtdesk' );
		await page.locator( '#qtdesk_global_cap' ).fill( '30' );
		await save.evaluate( ( b ) => {
			b.removeAttribute( 'disabled' );
			b.removeAttribute( 'aria-disabled' );
		} );
		await Promise.all( [ page.waitForNavigation(), save.click( { force: true } ) ] );
		await resetCounter( page );
	} );
} );
