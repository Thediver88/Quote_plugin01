<?php
/**
 * Tests for the settings helpers.
 *
 * @package QuoteDesk
 */

use PHPUnit\Framework\TestCase;

/**
 * Settings clean typed values and apply defaults.
 */
class SettingsTest extends TestCase {

	/**
	 * Reset the option and filter stand-ins.
	 */
	protected function setUp(): void {
		$GLOBALS['qtdesk_options'] = array();
		$GLOBALS['qtdesk_filters'] = array();
	}

	/**
	 * Whole numbers are clamped to their range.
	 */
	public function test_normalize_int_clamps() {
		$this->assertSame( 1, Qtdesk_Settings::normalize_int( '0', 1, 90, 14 ) );
		$this->assertSame( 90, Qtdesk_Settings::normalize_int( '500', 1, 90, 14 ) );
		$this->assertSame( 30, Qtdesk_Settings::normalize_int( ' 30 ', 1, 90, 14 ) );
		$this->assertSame( 0, Qtdesk_Settings::normalize_int( '-5', 0, 100, 3 ) );
	}

	/**
	 * Junk falls back to the default.
	 */
	public function test_normalize_int_defaults_on_junk() {
		$this->assertSame( 14, Qtdesk_Settings::normalize_int( '', 1, 90, 14 ) );
		$this->assertSame( 14, Qtdesk_Settings::normalize_int( 'abc', 1, 90, 14 ) );
		$this->assertSame( 14, Qtdesk_Settings::normalize_int( array( 5 ), 1, 90, 14 ) );
		$this->assertSame( 14, Qtdesk_Settings::normalize_int( null, 1, 90, 14 ) );
	}

	/**
	 * Button text loses tags and extra spaces and is capped.
	 */
	public function test_normalize_text() {
		$this->assertSame( 'Ask us', Qtdesk_Settings::normalize_text( '  <b>Ask</b>   us ' ) );
		$this->assertSame( '', Qtdesk_Settings::normalize_text( array( 'x' ) ) );
		$this->assertSame( Qtdesk_Settings::MAX_TEXT_LENGTH, strlen( Qtdesk_Settings::normalize_text( str_repeat( 'a', 200 ) ) ) );
	}

	/**
	 * Defaults apply when nothing is saved.
	 */
	public function test_defaults() {
		$this->assertSame( 14, Qtdesk_Settings::valid_days() );
		$this->assertSame( 30, Qtdesk_Settings::autocancel_days() );
		$this->assertSame( 30, Qtdesk_Settings::global_cap() );
		$this->assertSame( 60, Qtdesk_Settings::ceiling() );
		$this->assertSame( 3, Qtdesk_Settings::ip_limit() );
		$this->assertSame( 'Request a quote', Qtdesk_Settings::button_text() );
		$this->assertSame( 0, Qtdesk_Settings::page_id() );
	}

	/**
	 * Saved values are cleaned when read, even if the database holds junk.
	 */
	public function test_stored_values_are_cleaned() {
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_VALID_DAYS ]  = '9999';
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_CANCEL_DAYS ] = '0';
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_IP_LIMIT ]    = 'x';
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_BUTTON_TEXT ] = ' <i>Get a price</i> ';
		$this->assertSame( 90, Qtdesk_Settings::valid_days() );
		$this->assertSame( 1, Qtdesk_Settings::autocancel_days() );
		$this->assertSame( 3, Qtdesk_Settings::ip_limit() );
		$this->assertSame( 'Get a price', Qtdesk_Settings::button_text() );
	}

	/**
	 * A cap of 0 switches both the cap and the ceiling off.
	 */
	public function test_cap_zero_means_no_cap_and_no_ceiling() {
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_GLOBAL_CAP ] = '0';
		$this->assertSame( 0, Qtdesk_Settings::global_cap() );
		$this->assertSame( 0, Qtdesk_Settings::ceiling() );
	}

	/**
	 * The ceiling is twice the cap, and the cap filter can change it.
	 */
	public function test_ceiling_is_double_the_cap_and_filterable() {
		$GLOBALS['qtdesk_options'][ Qtdesk_Settings::OPT_GLOBAL_CAP ] = '10';
		$this->assertSame( 20, Qtdesk_Settings::ceiling() );
		$GLOBALS['qtdesk_filters']['qtdesk_global_cap'] = static function () {
			return 5;
		};
		$this->assertSame( 5, Qtdesk_Settings::global_cap() );
		$this->assertSame( 10, Qtdesk_Settings::ceiling() );
		$GLOBALS['qtdesk_filters']['qtdesk_global_cap'] = static function () {
			return -3;
		};
		$this->assertSame( 0, Qtdesk_Settings::global_cap() );
	}
}
