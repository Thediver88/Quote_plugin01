<?php
/**
 * Unit-test bootstrap. Loads pure PHP classes with minimal WordPress stand-ins.
 *
 * @package QuoteDesk
 */

define( 'ABSPATH', __DIR__ . '/' );

// Minimal stand-ins so the classes can be tested without WordPress or WooCommerce.
$GLOBALS['qtdesk_options'] = array();
$GLOBALS['qtdesk_filters'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['qtdesk_options'] ) ? $GLOBALS['qtdesk_options'][ $name ] : $default;
}
function __( $text ) {
	return $text;
}
function _x( $text ) {
	return $text;
}
function apply_filters( $hook, $value ) {
	return isset( $GLOBALS['qtdesk_filters'][ $hook ] ) ? call_user_func( $GLOBALS['qtdesk_filters'][ $hook ], $value ) : $value;
}
function _n_noop( $single, $plural ) {
	return array( $single, $plural );
}
function absint( $n ) {
	return abs( (int) $n );
}
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( $text ) );
}

require_once dirname( __DIR__ ) . '/includes/class-qtdesk-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-qtdesk-status.php';
