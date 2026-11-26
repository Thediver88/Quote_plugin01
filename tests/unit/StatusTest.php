<?php
/**
 * Tests for the "Quote requested" status helpers.
 *
 * @package QuoteDesk
 */

use PHPUnit\Framework\TestCase;

/**
 * The status is registered, editable and kept out of analytics.
 */
class StatusTest extends TestCase {

	/**
	 * The slug fits WooCommerce's 20-character limit and the key matches it.
	 */
	public function test_slug_length_and_key() {
		$this->assertLessThanOrEqual( 20, strlen( Qtdesk_Status::KEY ) );
		$this->assertSame( 'wc-' . Qtdesk_Status::SLUG, Qtdesk_Status::KEY );
	}

	/**
	 * The status appears in WooCommerce's list.
	 */
	public function test_added_to_status_list() {
		$list = Qtdesk_Status::add_to_list( array( 'wc-pending' => 'Pending payment' ) );
		$this->assertArrayHasKey( 'wc-qtdesk-requested', $list );
		$this->assertArrayHasKey( 'wc-pending', $list );
	}

	/**
	 * The legacy storage registration carries a label.
	 */
	public function test_registered_for_legacy_storage() {
		$statuses = Qtdesk_Status::register_post_status( array() );
		$this->assertSame( 'Quote requested', $statuses['wc-qtdesk-requested']['label'] );
	}

	/**
	 * Requests are excluded from analytics once, and other statuses are kept.
	 */
	public function test_excluded_from_analytics_once() {
		$one = Qtdesk_Status::exclude_from_analytics( array( 'pending' ) );
		$two = Qtdesk_Status::exclude_from_analytics( $one );
		$this->assertSame( array( 'pending', 'qtdesk-requested' ), $two );
		$this->assertSame( array( 'qtdesk-requested' ), Qtdesk_Status::exclude_from_analytics( 'junk' ) );
	}

	/**
	 * A requested quote is editable; other orders keep WooCommerce's answer.
	 */
	public function test_editable_only_for_requested_quotes() {
		$quote = new class() {
			/**
			 * Pretend status check.
			 *
			 * @param string $status Status.
			 * @return bool
			 */
			public function has_status( $status ) {
				return 'qtdesk-requested' === $status;
			}
		};
		$other = new class() {
			/**
			 * Pretend status check.
			 *
			 * @param string $status Status.
			 * @return bool
			 */
			public function has_status( $status ) {
				return 'completed' === $status;
			}
		};
		$this->assertTrue( Qtdesk_Status::is_editable( false, $quote ) );
		$this->assertFalse( Qtdesk_Status::is_editable( false, $other ) );
		$this->assertTrue( Qtdesk_Status::is_editable( true, $other ) );
		$this->assertFalse( Qtdesk_Status::is_editable( false, 'not an order' ) );
	}
}
