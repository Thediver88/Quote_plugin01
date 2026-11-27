<?php
/**
 * Abuse limits for the public request form: per email, per user, per network address, and a
 * shop-wide hourly counter with a hard ceiling.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keys and counters. Nothing here leaves the shop: addresses are stored only as salted hashes in
 * transients that expire within an hour.
 */
class Qtdesk_Limits {

	const GROUP = 'qtdesk';

	/**
	 * Reduce an address to what the per-address limit should count.
	 *
	 * @param mixed $ip Raw address.
	 * @return string "" when the address is invalid, private or reserved (no usable address);
	 *                otherwise the IPv4 address, or the first 64 bits of an IPv6 address in hex.
	 */
	public static function normalize_ip( $ip ) {
		if ( ! is_string( $ip ) ) {
			return '';
		}
		$ip = trim( $ip );
		// An IPv4 address written the IPv6 way (::ffff:1.2.3.4) is the same visitor as plain IPv4.
		if ( preg_match( '/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m ) ) {
			$ip = $m[1];
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}
		if ( false === strpos( $ip, ':' ) ) {
			return $ip;
		}
		$packed = inet_pton( $ip );
		return false === $packed ? '' : 'v6:' . bin2hex( substr( $packed, 0, 8 ) );
	}

	/**
	 * Decide what to do with a request, given the number of requests counted this hour.
	 *
	 * @param int  $count   Requests counted this hour, including this one.
	 * @param int  $cap     The hourly cap (0 = no cap and no ceiling).
	 * @param int  $ceiling The hard ceiling (twice the cap).
	 * @param bool $exempt  Whether the cap (not the ceiling) is waived, for paying customers.
	 * @return string "ok", "overflow" (save quietly, no customer email) or "refuse".
	 */
	public static function decide( $count, $cap, $ceiling, $exempt = false ) {
		if ( $cap <= 0 ) {
			return 'ok';
		}
		if ( $count > $ceiling ) {
			return 'refuse';
		}
		if ( $count > $cap && ! $exempt ) {
			return 'overflow';
		}
		return 'ok';
	}

	/**
	 * Salted hash of a value, short enough for a transient name.
	 *
	 * @param string $value Value to hash.
	 * @return string
	 */
	private static function hash( $value ) {
		return substr( hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * The network address of the visitor. Only REMOTE_ADDR is trusted: forwarding headers can be
	 * forged. Shops behind a proxy can supply the real address with the qtdesk_client_ip filter.
	 *
	 * @return string Normalised address, or "" when there is none to count.
	 */
	public static function client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Filters the visitor's network address used for the per-address limit.
		 *
		 * @param string $remote Value of REMOTE_ADDR.
		 */
		$remote = apply_filters( 'qtdesk_client_ip', $remote );
		return self::normalize_ip( $remote );
	}

	/**
	 * Whether another request is still within a per-address limit (counts nothing).
	 *
	 * @param string $kind  "email", "user" or "ip".
	 * @param string $value The address, user id or IP.
	 * @param int    $limit Allowed requests per hour; 0 means no limit.
	 * @return bool
	 */
	public static function within( $kind, $value, $limit ) {
		if ( $limit <= 0 || '' === $value ) {
			return true;
		}
		return (int) get_transient( self::key( $kind, $value ) ) < $limit;
	}

	/**
	 * Count one request against a per-address limit.
	 *
	 * @param string $kind  "email", "user" or "ip".
	 * @param string $value The address, user id or IP.
	 * @param int    $limit Allowed requests per hour; 0 means no limit.
	 */
	public static function record( $kind, $value, $limit ) {
		if ( $limit <= 0 || '' === $value ) {
			return;
		}
		$key = self::key( $kind, $value );
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Transient name for a limit bucket.
	 *
	 * @param string $kind  Bucket kind.
	 * @param string $value Value to count.
	 * @return string
	 */
	private static function key( $kind, $value ) {
		return 'qtdesk_' . $kind . '_' . self::hash( strtolower( $value ) );
	}

	/**
	 * Count one request in the shop-wide hourly counter, atomically.
	 *
	 * @return int The count including this request.
	 */
	public static function bump_global() {
		$bucket = 'qtdesk_hits_' . gmdate( 'YmdH' );

		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $bucket, 0, self::GROUP, HOUR_IN_SECONDS );
			$count = wp_cache_incr( $bucket, 1, self::GROUP );
			if ( false !== $count ) {
				return (int) $count;
			}
		}

		global $wpdb;
		// Three statements that do not depend on how many rows MySQL reports as changed:
		// make sure the row exists, add one atomically (remembering the result for this connection), read it back.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A shop-wide counter must be atomic; the object cache cannot be relied on.
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '0', 'off')", $bucket ) );
		$done = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", $bucket ) );
		if ( false === $done ) {
			return 0; // The database failed: do not guess, let the request through.
		}
		return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		// phpcs:enable
	}

	/**
	 * Delete counter rows of earlier hours (run by the daily sweep and on uninstall).
	 */
	public static function purge_old_buckets() {
		global $wpdb;
		$keep = 'qtdesk_hits_' . gmdate( 'YmdH' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name <> %s", $wpdb->esc_like( 'qtdesk_hits_' ) . '%', $keep ) );
	}
}
