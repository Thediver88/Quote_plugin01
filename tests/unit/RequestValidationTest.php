<?php
/**
 * Tests for cleaning and checking the request form.
 *
 * @package QuoteDesk
 */

use PHPUnit\Framework\TestCase;

/**
 * Qtdesk_Request::validate().
 */
class RequestValidationTest extends TestCase {

	/**
	 * A complete, valid form.
	 *
	 * @param array $over Values to replace.
	 * @return array
	 */
	private function form( array $over = array() ) {
		return array_merge(
			array(
				'name'     => 'Ada Lovelace',
				'email'    => 'ada@example.com',
				'phone'    => '+41 44 123 45 67',
				'country'  => 'CH',
				'postcode' => '8045',
				'address'  => 'Flat 10, Example Street 1',
				'message'  => 'Need 40 m2.',
			),
			$over
		);
	}

	/**
	 * A good form passes without errors.
	 */
	public function test_valid_form() {
		$result = Qtdesk_Request::validate( $this->form(), array( 'CH' => 'Switzerland' ) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'Ada Lovelace', $result['values']['name'] );
	}

	/**
	 * Required fields are reported one by one.
	 */
	public function test_required_fields() {
		$result = Qtdesk_Request::validate( array(), array( 'CH' => 'Switzerland' ) );
		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
		$this->assertArrayHasKey( 'country', $result['errors'] );
		$this->assertArrayHasKey( 'address', $result['errors'] );
	}

	/**
	 * A country the shop does not ship to is refused, case does not matter.
	 */
	public function test_country_must_be_allowed() {
		$bad = Qtdesk_Request::validate( $this->form( array( 'country' => 'XX' ) ), array( 'CH' => 'Switzerland' ) );
		$this->assertArrayHasKey( 'country', $bad['errors'] );
		$low = Qtdesk_Request::validate( $this->form( array( 'country' => 'ch' ) ), array( 'CH' => 'Switzerland' ) );
		$this->assertSame( array(), $low['errors'] );
	}

	/**
	 * Bad email addresses are refused.
	 */
	public function test_bad_email() {
		$result = Qtdesk_Request::validate( $this->form( array( 'email' => 'not-an-email' ) ), array( 'CH' => 'Switzerland' ) );
		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	/**
	 * Tags are removed and lengths are capped.
	 */
	public function test_cleaning_and_caps() {
		$result = Qtdesk_Request::validate(
			$this->form(
				array(
					'name'    => '<script>x</script>Ada',
					'phone'   => 'call me: +41 44 <b>1</b>',
					'message' => str_repeat( 'a', 5000 ),
					'address' => str_repeat( 'b', 500 ),
				)
			),
			array( 'CH' => 'Switzerland' )
		);
		$this->assertSame( false, strpos( $result['values']['name'], '<' ) );
		$this->assertSame( 'Ada', substr( $result['values']['name'], -3 ) );
		$this->assertSame( '+41 44 1', $result['values']['phone'] );
		$this->assertSame( 2000, strlen( $result['values']['message'] ) );
		$this->assertSame( 200, strlen( $result['values']['address'] ) );
	}

	/**
	 * Non-text input (arrays) is treated as empty, never as an error in PHP.
	 */
	public function test_non_scalar_input_is_empty() {
		$result = Qtdesk_Request::validate( $this->form( array( 'name' => array( 'x' ), 'message' => array( 'y' ) ) ), array( 'CH' => 'Switzerland' ) );
		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertSame( '', $result['values']['message'] );
	}
}
