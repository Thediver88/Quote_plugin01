<?php
/**
 * Runs when the plugin is deleted: removes the plugin's settings. Orders are never deleted.
 *
 * @package QuoteDesk
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$qtdesk_options = array(
	'qtdesk_valid_days',
	'qtdesk_autocancel_days',
	'qtdesk_global_cap',
	'qtdesk_ip_limit',
	'qtdesk_button_text',
	'qtdesk_page_id',
);
foreach ( $qtdesk_options as $qtdesk_option ) {
	delete_option( $qtdesk_option );
}
