<?php
/**
 * Settings: what the shop owner configured. Everything is read and cleaned on the server.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Option names, defaults and the pure helpers that clean typed values.
 */
class Qtdesk_Settings {

	const OPT_VALID_DAYS  = 'qtdesk_valid_days';
	const OPT_CANCEL_DAYS = 'qtdesk_autocancel_days';
	const OPT_GLOBAL_CAP  = 'qtdesk_global_cap';
	const OPT_IP_LIMIT    = 'qtdesk_ip_limit';
	const OPT_BUTTON_TEXT = 'qtdesk_button_text';
	const OPT_PAGE_ID     = 'qtdesk_page_id';

	const DEFAULT_VALID_DAYS  = 14;
	const DEFAULT_CANCEL_DAYS = 30;
	const DEFAULT_GLOBAL_CAP  = 30;
	const DEFAULT_IP_LIMIT    = 3;
	const MAX_TEXT_LENGTH     = 60;

	/**
	 * Clean a typed whole number.
	 *
	 * @param mixed $raw           Typed value.
	 * @param int   $min           Smallest allowed value.
	 * @param int   $max           Largest allowed value.
	 * @param int   $default_value Value used when the input is empty or not a number.
	 * @return int
	 */
	public static function normalize_int( $raw, $min, $max, $default_value ) {
		$raw = is_string( $raw ) ? trim( $raw ) : $raw;
		if ( ! is_scalar( $raw ) || ! is_numeric( $raw ) || ! is_finite( (float) $raw ) ) {
			return (int) $default_value;
		}
		return (int) min( $max, max( $min, (int) $raw ) );
	}

	/**
	 * Clean the button text typed by the shop owner.
	 *
	 * @param mixed $raw Typed value.
	 * @return string Plain text, at most MAX_TEXT_LENGTH characters; "" means "use the translated default".
	 */
	public static function normalize_text( $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $raw ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT_LENGTH ) : substr( $text, 0, self::MAX_TEXT_LENGTH );
	}

	/**
	 * How many days a sent quote stays valid.
	 *
	 * @return int Between 1 and 90.
	 */
	public static function valid_days() {
		return self::normalize_int( get_option( self::OPT_VALID_DAYS, self::DEFAULT_VALID_DAYS ), 1, 90, self::DEFAULT_VALID_DAYS );
	}

	/**
	 * After how many days an unanswered request is cancelled.
	 *
	 * @return int Between 1 and 365.
	 */
	public static function autocancel_days() {
		return self::normalize_int( get_option( self::OPT_CANCEL_DAYS, self::DEFAULT_CANCEL_DAYS ), 1, 365, self::DEFAULT_CANCEL_DAYS );
	}

	/**
	 * Requests per hour (whole shop) before extra requests are saved quietly. 0 switches the limit off.
	 *
	 * @return int Between 0 and 1000.
	 */
	public static function global_cap() {
		$cap = self::normalize_int( get_option( self::OPT_GLOBAL_CAP, self::DEFAULT_GLOBAL_CAP ), 0, 1000, self::DEFAULT_GLOBAL_CAP );
		/**
		 * Filters the hourly cap on quote requests for the whole shop.
		 *
		 * @param int $cap Requests per hour; 0 means no cap.
		 */
		return max( 0, (int) apply_filters( 'qtdesk_global_cap', $cap ) );
	}

	/**
	 * The hard ceiling per hour: twice the cap. Above it, requests are refused. 0 when the cap is off.
	 *
	 * @return int
	 */
	public static function ceiling() {
		return 2 * self::global_cap();
	}

	/**
	 * Requests per hour from one network address. 0 switches the limit off.
	 *
	 * @return int Between 0 and 100.
	 */
	public static function ip_limit() {
		return self::normalize_int( get_option( self::OPT_IP_LIMIT, self::DEFAULT_IP_LIMIT ), 0, 100, self::DEFAULT_IP_LIMIT );
	}

	/**
	 * Text of the "Request a quote" button.
	 *
	 * @return string
	 */
	public static function button_text() {
		$text = self::normalize_text( get_option( self::OPT_BUTTON_TEXT, '' ) );
		return '' === $text ? __( 'Request a quote', 'quote-desk-for-woocommerce' ) : $text;
	}

	/**
	 * ID of the page that holds the request form (0 when none).
	 *
	 * @return int
	 */
	public static function page_id() {
		return absint( get_option( self::OPT_PAGE_ID, 0 ) );
	}
}
