<?php
/**
 * Tests for the request limits' pure parts.
 *
 * @package QuoteDesk
 */

use PHPUnit\Framework\TestCase;

/**
 * Address handling and the cap/ceiling decision.
 */
class LimitsTest extends TestCase {

	/**
	 * Public IPv4 addresses are kept; junk, private and reserved addresses give no key.
	 */
	public function test_normalize_ip_ipv4() {
		$this->assertSame( '8.8.8.8', Qtdesk_Limits::normalize_ip( ' 8.8.8.8 ' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( '10.0.0.5' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( '192.168.1.20' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( '127.0.0.1' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( '' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( 'not an ip' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( null ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( array( '8.8.8.8' ) ) );
	}

	/**
	 * IPv6 addresses are grouped by their first 64 bits.
	 */
	public function test_normalize_ip_ipv6_groups_by_64() {
		$one = Qtdesk_Limits::normalize_ip( '2001:4860:4860::8888' );
		$two = Qtdesk_Limits::normalize_ip( '2001:4860:4860:0000:1234:5678:9abc:def0' );
		$far = Qtdesk_Limits::normalize_ip( '2001:4860:4861::8888' );
		$this->assertSame( 'v6:200148604860' . '0000', $one );
		$this->assertSame( $one, $two );
		$this->assertTrue( $one !== $far );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( 'fe80::1' ) );
		$this->assertSame( '', Qtdesk_Limits::normalize_ip( '::1' ) );
	}

	/**
	 * Below the cap everything is fine.
	 */
	public function test_decide_ok_below_cap() {
		$this->assertSame( 'ok', Qtdesk_Limits::decide( 1, 2, 4 ) );
		$this->assertSame( 'ok', Qtdesk_Limits::decide( 2, 2, 4 ) );
	}

	/**
	 * Between the cap and the ceiling requests are saved quietly; above the ceiling they are refused.
	 */
	public function test_decide_overflow_then_refuse() {
		$this->assertSame( 'overflow', Qtdesk_Limits::decide( 3, 2, 4 ) );
		$this->assertSame( 'overflow', Qtdesk_Limits::decide( 4, 2, 4 ) );
		$this->assertSame( 'refuse', Qtdesk_Limits::decide( 5, 2, 4 ) );
	}

	/**
	 * Paying customers skip the cap but never the ceiling.
	 */
	public function test_decide_exempt_skips_cap_not_ceiling() {
		$this->assertSame( 'ok', Qtdesk_Limits::decide( 3, 2, 4, true ) );
		$this->assertSame( 'ok', Qtdesk_Limits::decide( 4, 2, 4, true ) );
		$this->assertSame( 'refuse', Qtdesk_Limits::decide( 5, 2, 4, true ) );
	}

	/**
	 * A cap of 0 switches the protection off.
	 */
	public function test_decide_cap_zero_is_off() {
		$this->assertSame( 'ok', Qtdesk_Limits::decide( 9999, 0, 0 ) );
	}
}
