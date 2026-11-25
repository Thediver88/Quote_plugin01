<?php
/**
 * The WooCommerce > Settings > Quote Desk tab. Loaded only inside WooCommerce's settings screens.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab.
 */
class Qtdesk_Settings_Page extends WC_Settings_Page {

	/**
	 * Set up the tab.
	 */
	public function __construct() {
		$this->id    = 'qtdesk';
		$this->label = __( 'Quote Desk', 'quote-desk-for-woocommerce' );
		parent::__construct();
	}

	/**
	 * A number field with the usual look.
	 *
	 * @param string $id      Option name.
	 * @param string $title   Label.
	 * @param string $desc    Help text.
	 * @param int    $default Default value.
	 * @param int    $min     Smallest value.
	 * @param int    $max     Largest value.
	 * @return array
	 */
	private function number_field( $id, $title, $desc, $default, $min, $max ) {
		return array(
			'title'             => $title,
			'desc'              => $desc,
			'id'                => $id,
			'type'              => 'number',
			'default'           => $default,
			'desc_tip'          => false,
			'custom_attributes' => array(
				'min'  => $min,
				'max'  => $max,
				'step' => 1,
			),
			'css'               => 'width: 6em;',
		);
	}

	/**
	 * Fields of the (only) section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		return array(
			array(
				'title' => __( 'Quote Desk', 'quote-desk-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Shoppers can ask for a quote from their cart. You price the request in the order screen and send it; the customer pays it like any other order.', 'quote-desk-for-woocommerce' ),
				'id'    => 'qtdesk_options',
			),
			$this->number_field(
				Qtdesk_Settings::OPT_VALID_DAYS,
				__( 'Quote valid for (days)', 'quote-desk-for-woocommerce' ),
				__( 'How long a sent quote can be paid. After that it is cancelled.', 'quote-desk-for-woocommerce' ),
				Qtdesk_Settings::DEFAULT_VALID_DAYS,
				1,
				90
			),
			$this->number_field(
				Qtdesk_Settings::OPT_CANCEL_DAYS,
				__( 'Cancel unanswered requests after (days)', 'quote-desk-for-woocommerce' ),
				__( 'Requests you never answered are cancelled after this many days, so personal data is not kept forever.', 'quote-desk-for-woocommerce' ),
				Qtdesk_Settings::DEFAULT_CANCEL_DAYS,
				1,
				365
			),
			$this->number_field(
				Qtdesk_Settings::OPT_GLOBAL_CAP,
				__( 'Requests per hour (whole shop)', 'quote-desk-for-woocommerce' ),
				__( 'Protects you from spam. Above this number, new requests are saved without a confirmation email; at twice this number they are refused. 0 switches the protection off.', 'quote-desk-for-woocommerce' ),
				Qtdesk_Settings::DEFAULT_GLOBAL_CAP,
				0,
				1000
			),
			$this->number_field(
				Qtdesk_Settings::OPT_IP_LIMIT,
				__( 'Requests per hour from one network address', 'quote-desk-for-woocommerce' ),
				__( 'Visitors behind a shared address (a company network, some CDNs) count together. 0 switches this limit off.', 'quote-desk-for-woocommerce' ),
				Qtdesk_Settings::DEFAULT_IP_LIMIT,
				0,
				100
			),
			array(
				'title'    => __( 'Button text', 'quote-desk-for-woocommerce' ),
				'desc'     => __( 'Leave empty to use "Request a quote".', 'quote-desk-for-woocommerce' ),
				'id'       => Qtdesk_Settings::OPT_BUTTON_TEXT,
				'type'     => 'text',
				'default'  => '',
				'desc_tip' => false,
			),
			array(
				'title'    => __( 'Request page', 'quote-desk-for-woocommerce' ),
				'desc'     => __( 'The page that holds the request form. It is created for you when the plugin is activated.', 'quote-desk-for-woocommerce' ),
				'id'       => Qtdesk_Settings::OPT_PAGE_ID,
				'type'     => 'single_select_page',
				'default'  => '',
				'class'    => 'wc-enhanced-select-nostd',
				'css'      => 'min-width:300px;',
				'desc_tip' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'qtdesk_options',
			),
		);
	}
}
