<?php
/**
 * Builds a known test store (run with `wp eval-file`). Safe to run repeatedly.
 *
 * @package QuoteDesk
 */

// phpcs:disable -- test fixture, not shipped.

update_option( 'woocommerce_currency', 'GBP' );
update_option( 'woocommerce_currency_pos', 'left' );
update_option( 'woocommerce_price_num_decimals', '2' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_default_country', 'GB' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery' ) );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'permalink_structure', '/%postname%/' );


function qtdesk_seed_clean( $sku ) {
	$id = wc_get_product_id_by_sku( $sku );
	if ( $id ) {
		wp_delete_post( $id, true );
	}
}

// A plain product the smoke test opens.
qtdesk_seed_clean( 'QD-TILE' );
$tile = new WC_Product_Simple();
$tile->set_name( 'Sample Tile' );
$tile->set_slug( 'sample-tile' );
$tile->set_sku( 'QD-TILE' );
$tile->set_regular_price( '30' );
$tile->set_manage_stock( true );
$tile->set_stock_quantity( 50 );
$tile->set_status( 'publish' );
$tile->save();

// Classic (shortcode) cart and checkout pages, alongside WooCommerce's block ones.
function qtdesk_seed_page( $slug, $title, $content ) {
	$page = get_page_by_path( $slug );
	$args = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	);
	if ( $page ) {
		$args['ID'] = $page->ID;
	}
	return wp_insert_post( $args );
}
qtdesk_seed_page( 'classic-cart', 'Classic Cart', '[woocommerce_cart]' );
qtdesk_seed_page( 'classic-checkout', 'Classic Checkout', '[woocommerce_checkout]' );

// One UK zone with a flat rate, so classic checkout offers payment methods.
foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
	$old_zone = new WC_Shipping_Zone( $zone_data['id'] );
	$old_zone->delete();
}
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'United Kingdom' );
$zone->add_location( 'GB', 'country' );
$zone->save();
$instance_id = $zone->add_shipping_method( 'flat_rate' );
$method      = WC_Shipping_Zones::get_shipping_method( $instance_id );
update_option( $method->get_instance_option_key(), array( 'title' => 'Flat rate', 'tax_status' => 'none', 'cost' => '5', ) );


// A quote request, as the request form will create it later.
foreach ( wc_get_orders( array( 'billing_email' => 'buyer@example.com', 'limit' => -1 ) ) as $old_order ) {
	$old_order->delete( true );
}
$quote = wc_create_order( array( 'status' => 'qtdesk-requested' ) );
$quote->add_product( wc_get_product( $tile->get_id() ), 2 );
$quote->set_billing_email( 'buyer@example.com' );
$quote->set_billing_first_name( 'Quentin' );
$quote->calculate_totals();
$quote->save();


// Test helper (mu-plugin): resets the hourly counter and mimics another plugin that stores data on cart lines.
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents(
	WPMU_PLUGIN_DIR . '/qtdesk-e2e-helper.php',
	<<<'PHP'
<?php
// Written by tests/e2e/seed.php. Test stores only.
add_action(
	'init',
	function () {
		if ( isset( $_GET['qtdesk_reset_counter'] ) ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'qtdesk\\_hits\\_%'" );
			foreach ( array( 'lines@example.com', 'cap1@example.com', 'cap2@example.com', 'cap3@example.com', 'ada@example.com', 'block@example.com', 'paying@example.com', 'double@example.com' ) as $mail ) {
				delete_transient( 'qtdesk_email_' . substr( hash_hmac( 'sha256', strtolower( $mail ), wp_salt( 'auth' ) ), 0, 32 ) );
			}
			echo 'counter reset';
			exit;
		}
		if ( isset( $_GET['qtdesk_bump'] ) ) {
			$a = Qtdesk_Limits::bump_global();
			$b = Qtdesk_Limits::bump_global();
			$c = Qtdesk_Limits::bump_global();
			echo "bump:$a,$b,$c";
			exit;
		}
		if ( isset( $_GET['qtdesk_dump'] ) ) {
			$out = array();
			foreach ( wc_get_orders( array( 'status' => 'qtdesk-requested', 'limit' => 20, 'orderby' => 'id', 'order' => 'ASC' ) ) as $o ) {
				$out[] = $o->get_billing_email() . '|' . $o->get_created_via() . '|' . $o->get_meta( '_qtdesk_overflow' ) . '|' . count( $o->get_items() ) . '|' . $o->get_billing_country();
			}
			echo 'dump:' . implode( ';', $out );
			exit;
		}
	}
);
add_action(
	'woocommerce_check_cart_items',
	function () {
		if ( ! empty( $_COOKIE['qtdesk_block_cart'] ) ) {
			wc_add_notice( 'Blocked by a test rule.', 'error' );
		}
	}
);
add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data ) {
		if ( isset( $_REQUEST['qtdesk_cartdata'] ) ) {
			$data['qtdesk_test'] = 'carried';
		}
		return $data;
	}
);
add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $key, $values ) {
		if ( ! empty( $values['qtdesk_test'] ) ) {
			$item->add_meta_data( 'Test note', $values['qtdesk_test'] );
		}
	},
	10,
	3
);
PHP
);

// The request page (activation may have run before WooCommerce was active).
Qtdesk_Request::create_page();

flush_rewrite_rules();
// WP-CLI can't detect mod_rewrite, so write Apache's rules ourselves (wp-env serves via Apache).
file_put_contents(
	ABSPATH . '.htaccess',
	"# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n"
);
WP_CLI::success( 'Quote Desk test store ready.' );
