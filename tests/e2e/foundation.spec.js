// @ts-check
/**
 * Settings screen and the "Quote requested" status (see tests/e2e/seed.php).
 */
const { test, expect } = require( '@playwright/test' );

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
}

/** Click WooCommerce's Save button even while it is still greyed out waiting for a change. */
async function saveSettings( page ) {
	const save = page.locator( 'button[name="save"]' );
	await save.evaluate( ( b ) => {
		b.removeAttribute( 'disabled' );
		b.removeAttribute( 'aria-disabled' );
		b.classList.remove( 'is-busy' );
	} );
	await Promise.all( [ page.waitForNavigation(), save.click( { force: true } ) ] );
}

test.describe( 'Settings screen', () => {
	test( 'WooCommerce > Settings > Quote Desk loads, cleans and saves', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=qtdesk' );
		await expect( page.locator( '#qtdesk_valid_days' ) ).toHaveValue( '14' );
		await expect( page.locator( '#qtdesk_global_cap' ) ).toHaveValue( '30' );

		// The browser would stop an out-of-range value before it is sent; the server must still clean it.
		await page.locator( '#mainform' ).evaluate( ( f ) => {
			f.noValidate = true;
		} );
		await page.locator( '#qtdesk_valid_days' ).fill( '9999' );
		await page.locator( '#qtdesk_global_cap' ).fill( '0' );
		await page.locator( '#qtdesk_button_text' ).fill( '  <b>Ask us</b> ' );
		await page.locator( '#qtdesk_button_text' ).blur();
		await saveSettings( page );
		await expect( page.locator( '#qtdesk_valid_days' ) ).toHaveValue( '90' ); // Clamped to the maximum.
		await expect( page.locator( '#qtdesk_global_cap' ) ).toHaveValue( '0' );
		await expect( page.locator( '#qtdesk_button_text' ) ).toHaveValue( 'Ask us' );

		// Put the defaults back for any later test.
		await page.locator( '#qtdesk_valid_days' ).fill( '14' );
		await page.locator( '#qtdesk_global_cap' ).fill( '30' );
		await page.locator( '#qtdesk_button_text' ).fill( '' );
		await page.locator( '#qtdesk_button_text' ).blur();
		await saveSettings( page );
		await expect( page.locator( '#qtdesk_valid_days' ) ).toHaveValue( '14' );
	} );
} );

test.describe( 'Quote requested status', () => {
	test( 'a requested quote is listed under its own status and its lines can be edited', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=wc-orders&status=wc-qtdesk-requested' );
		await expect( page.locator( 'body' ) ).toContainText( 'Quote requested' );
		const row = page.locator( 'tr.order-status, tbody#the-list tr' ).filter( { hasText: 'Quentin' } ).first();
		await expect( row ).toBeVisible();
		await row.locator( 'a.order-view' ).first().click();
		await page.waitForLoadState( 'load' );
		// The order editor only offers these buttons when the order is editable.
		await expect( page.locator( 'button.add-line-item' ) ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
	} );
} );
