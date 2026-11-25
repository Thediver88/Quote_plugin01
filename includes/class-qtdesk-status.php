<?php
/**
 * The "Quote requested" order status.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the status for both order storage modes and keeps it out of reports.
 */
class Qtdesk_Status {

	/** Status slug without the "wc-" prefix (19 characters; WooCommerce allows 20). */
	const SLUG = 'qtdesk-requested';

	/** Status key as WooCommerce stores it. */
	const KEY = 'wc-qtdesk-requested';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( __CLASS__, 'register_post_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_list' ) );
		add_filter( 'wc_order_is_editable', array( __CLASS__, 'is_editable' ), 10, 2 );
		add_filter( 'woocommerce_analytics_excluded_statuses', array( __CLASS__, 'exclude_from_analytics' ) );
	}

	/**
	 * Label shown to the shop owner.
	 *
	 * @return string
	 */
	public static function label() {
		return _x( 'Quote requested', 'Order status', 'quote-desk-for-woocommerce' );
	}

	/**
	 * Register the status for the legacy (posts) order storage.
	 *
	 * @param array $statuses Registered post statuses.
	 * @return array
	 */
	public static function register_post_status( $statuses ) {
		$statuses[ self::KEY ] = array(
			'label'                     => self::label(),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop( 'Quote requested <span class="count">(%s)</span>', 'Quote requested <span class="count">(%s)</span>', 'quote-desk-for-woocommerce' ),
		);
		return $statuses;
	}

	/**
	 * Add the status to WooCommerce's status list (used by HPOS and the order screens).
	 *
	 * @param array $statuses Status key => label.
	 * @return array
	 */
	public static function add_to_list( $statuses ) {
		$statuses[ self::KEY ] = self::label();
		return $statuses;
	}

	/**
	 * Let the shop edit the lines of a requested quote (prices, shipping, tax) before sending it.
	 *
	 * @param bool  $editable Whether the order is editable.
	 * @param mixed $order    The order.
	 * @return bool
	 */
	public static function is_editable( $editable, $order ) {
		if ( is_object( $order ) && method_exists( $order, 'has_status' ) && $order->has_status( self::SLUG ) ) {
			return true;
		}
		return $editable;
	}

	/**
	 * Keep quote requests out of WooCommerce Analytics (they are not sales).
	 *
	 * @param array $statuses Excluded statuses, without the "wc-" prefix.
	 * @return array
	 */
	public static function exclude_from_analytics( $statuses ) {
		$statuses = is_array( $statuses ) ? $statuses : array();
		if ( ! in_array( self::SLUG, $statuses, true ) ) {
			$statuses[] = self::SLUG;
		}
		return $statuses;
	}
}
