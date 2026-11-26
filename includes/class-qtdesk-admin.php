<?php
/**
 * Admin: the WooCommerce > Settings > Quote Desk tab.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screens. WooCommerce handles the nonces and capability checks for its settings pages;
 * we only clean what we read.
 */
class Qtdesk_Admin {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_settings_page' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Qtdesk_Settings::OPT_VALID_DAYS, array( __CLASS__, 'sanitize_valid_days' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Qtdesk_Settings::OPT_CANCEL_DAYS, array( __CLASS__, 'sanitize_cancel_days' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Qtdesk_Settings::OPT_GLOBAL_CAP, array( __CLASS__, 'sanitize_global_cap' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Qtdesk_Settings::OPT_IP_LIMIT, array( __CLASS__, 'sanitize_ip_limit' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Qtdesk_Settings::OPT_BUTTON_TEXT, array( 'Qtdesk_Settings', 'normalize_text' ) );
	}

	/**
	 * Add our tab.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public static function add_settings_page( $pages ) {
		if ( class_exists( 'WC_Settings_Page' ) ) {
			require_once QTDESK_PATH . 'includes/class-qtdesk-settings-page.php';
			$pages[] = new Qtdesk_Settings_Page();
		}
		return $pages;
	}

	/**
	 * Clean "valid for (days)".
	 *
	 * @param mixed $value Typed value.
	 * @return int
	 */
	public static function sanitize_valid_days( $value ) {
		return Qtdesk_Settings::normalize_int( $value, 1, 90, Qtdesk_Settings::DEFAULT_VALID_DAYS );
	}

	/**
	 * Clean "auto-cancel after (days)".
	 *
	 * @param mixed $value Typed value.
	 * @return int
	 */
	public static function sanitize_cancel_days( $value ) {
		return Qtdesk_Settings::normalize_int( $value, 1, 365, Qtdesk_Settings::DEFAULT_CANCEL_DAYS );
	}

	/**
	 * Clean the hourly cap.
	 *
	 * @param mixed $value Typed value.
	 * @return int
	 */
	public static function sanitize_global_cap( $value ) {
		return Qtdesk_Settings::normalize_int( $value, 0, 1000, Qtdesk_Settings::DEFAULT_GLOBAL_CAP );
	}

	/**
	 * Clean the per-address limit.
	 *
	 * @param mixed $value Typed value.
	 * @return int
	 */
	public static function sanitize_ip_limit( $value ) {
		return Qtdesk_Settings::normalize_int( $value, 0, 100, Qtdesk_Settings::DEFAULT_IP_LIMIT );
	}
}
