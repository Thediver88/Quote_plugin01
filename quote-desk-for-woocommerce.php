<?php
/**
 * Plugin Name:          Quote Desk for WooCommerce
 * Description:          Let shoppers request a quote for large or made-to-measure tile, flooring and fabric orders.
 * Version:              0.1.0
 * Requires at least:    6.9
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Cartwright
 * Author URI:           https://cartwrightplugins.com/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          quote-desk-for-woocommerce
 * WC requires at least: 10.0
 * WC tested up to:      11.2
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

define( 'QTDESK_VERSION', '0.1.0' );
define( 'QTDESK_FILE', __FILE__ );
define( 'QTDESK_PATH', plugin_dir_path( __FILE__ ) );
define( 'QTDESK_URL', plugin_dir_url( __FILE__ ) );

// Declare compatibility with High-Performance Order Storage and the block cart/checkout.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', QTDESK_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', QTDESK_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once QTDESK_PATH . 'includes/class-qtdesk-settings.php';
		require_once QTDESK_PATH . 'includes/class-qtdesk-status.php';
		Qtdesk_Status::init();
		if ( is_admin() ) {
			require_once QTDESK_PATH . 'includes/class-qtdesk-admin.php';
			Qtdesk_Admin::init();
		}
		/**
		 * Fires once Quote Desk has loaded.
		 */
		do_action( 'qtdesk_loaded' );
	}
);
