<?php
/**
 * The request page: the form, its checks, and turning the cart into a "Quote requested" order.
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode, form handling and order creation.
 */
class Qtdesk_Request {

	const SHORTCODE = 'qtdesk_request';
	const NONCE     = 'qtdesk_request';
	const ACTION    = 'qtdesk_submit';

	/**
	 * Errors of the current submission, keyed by field ("form" for general ones).
	 *
	 * @var array
	 */
	private static $errors = array();

	/**
	 * Values the visitor typed, to fill the form again after an error.
	 *
	 * @var array
	 */
	private static $values = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
	}

	/**
	 * Create the request page on activation (once; an existing, published page is kept).
	 */
	public static function create_page() {
		$existing = Qtdesk_Settings::page_id();
		if ( $existing && 'publish' === get_post_status( $existing ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Request a quote', 'quote-desk-for-woocommerce' ),
				'post_name'    => 'request-a-quote',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[' . self::SHORTCODE . ']',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( Qtdesk_Settings::OPT_PAGE_ID, (int) $page_id );
		}
	}

	/**
	 * Address of the request page, or "" when there is none.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = Qtdesk_Settings::page_id();
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		return (string) get_permalink( $page_id );
	}

	/**
	 * Whether the visitor is on the request page.
	 *
	 * @return bool
	 */
	private static function is_request_page() {
		$page_id = Qtdesk_Settings::page_id();
		return $page_id && is_page( $page_id );
	}

	/**
	 * Clean and check what the visitor typed. Pure PHP apart from the WordPress sanitising helpers.
	 *
	 * @param array $raw       Posted values (already unslashed).
	 * @param array $countries Allowed country codes => names.
	 * @return array { values: cleaned values, errors: field => message }
	 */
	public static function validate( array $raw, array $countries ) {
		$get = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
		};
		$cut = static function ( $text, $max ) {
			return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		};

		$values = array(
			'name'     => $cut( sanitize_text_field( $get( 'name' ) ), 100 ),
			'email'    => sanitize_email( $get( 'email' ) ),
			'phone'    => $cut( trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^0-9+()\s.\-]/', '', $get( 'phone' ) ) ) ), 40 ),
			'country'  => strtoupper( sanitize_text_field( $get( 'country' ) ) ),
			'postcode' => $cut( sanitize_text_field( $get( 'postcode' ) ), 20 ),
			'address'  => $cut( sanitize_text_field( $get( 'address' ) ), 200 ),
			'message'  => $cut( sanitize_textarea_field( $get( 'message' ) ), 2000 ),
		);

		$errors = array();
		if ( '' === $values['name'] ) {
			$errors['name'] = __( 'Please enter your name.', 'quote-desk-for-woocommerce' );
		}
		if ( '' === $values['email'] || ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'quote-desk-for-woocommerce' );
		}
		if ( '' === $values['country'] || ! isset( $countries[ $values['country'] ] ) ) {
			$errors['country'] = __( 'Please choose your country.', 'quote-desk-for-woocommerce' );
		}
		if ( '' === $values['address'] ) {
			$errors['address'] = __( 'Please enter the delivery address.', 'quote-desk-for-woocommerce' );
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * No-cache headers: the page shows one shopper's cart.
	 */
	private static function no_cache() {
		nocache_headers();
		wc_maybe_define_constant( 'DONOTCACHEPAGE', true );
	}

	/**
	 * Handle a submission before anything is printed (the cart is loaded at this point).
	 */
	public static function handle() {
		if ( ! self::is_request_page() ) {
			return;
		}
		self::no_cache();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked below, in the order the design requires.
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! isset( $_POST[ self::ACTION ] ) ) {
			return;
		}

		// 1. Nonce.
		if ( ! isset( $_POST['_qtdesk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_qtdesk_nonce'] ) ), self::NONCE ) ) {
			self::$errors['form'] = __( 'This page has expired. Please try again.', 'quote-desk-for-woocommerce' );
			return;
		}

		// 2. Honeypot: bots fill it. Pretend all is well and do nothing.
		if ( ! empty( $_POST['qtdesk_website'] ) ) {
			self::done();
		}

		// 3. Field checks.
		$posted = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; every value is cleaned in validate().
		$raw    = array();
		foreach ( array( 'name', 'email', 'phone', 'country', 'postcode', 'address', 'message' ) as $field_key ) {
			if ( isset( $posted[ 'qtdesk_' . $field_key ] ) ) {
				$raw[ $field_key ] = $posted[ 'qtdesk_' . $field_key ];
			}
		}
		$countries    = WC()->countries->get_shipping_countries();
		$checked      = self::validate( $raw, $countries );
		self::$values = $checked['values'];
		self::$errors = $checked['errors'];
		if ( '' !== self::$values['postcode'] && '' !== self::$values['country'] && ! WC_Validation::is_postcode( self::$values['postcode'], self::$values['country'] ) ) {
			self::$errors['postcode'] = __( 'This postcode does not look right for the country.', 'quote-desk-for-woocommerce' );
		}
		if ( ! self::$errors ) {
			self::$values['postcode'] = wc_format_postcode( self::$values['postcode'], self::$values['country'] );
		}
		if ( self::$errors ) {
			return;
		}

		// 4. The cart's own checks (stock, limits of other plugins, purchasable products).
		if ( WC()->cart->is_empty() ) {
			self::$errors['form'] = __( 'Your cart is empty.', 'quote-desk-for-woocommerce' );
			return;
		}
		// Run the same checks as checkout (core's and other plugins' hang off this action), then give the shopper's own notices back.
		$saved_notices = wc_get_notices();
		wc_clear_notices();
		do_action( 'woocommerce_check_cart_items' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's own hook.
		$cart_errors = wc_get_notices( 'error' );
		WC()->session->set( 'wc_notices', $saved_notices ? $saved_notices : null );
		if ( $cart_errors ) {
			self::$errors['form'] = __( 'Something in your cart needs attention. Please review the cart and try again.', 'quote-desk-for-woocommerce' );
			return;
		}
		foreach ( WC()->cart->get_cart() as $values ) {
			if ( empty( $values['data'] ) || ! $values['data']->is_purchasable() ) {
				self::$errors['form'] = __( 'An item in your cart can no longer be ordered.', 'quote-desk-for-woocommerce' );
				return;
			}
		}

		// A double click or a reload of the POST must not make a second order: the same form and cart is handled once.
		// Guests all share one nonce value, so the key uses the shopper's cart session, the e-mail and the cart contents instead.
		$once = 'qtdesk_once_' . md5( WC()->session->get_customer_id() . '|' . strtolower( self::$values['email'] ) . '|' . WC()->cart->get_cart_hash() );
		if ( get_transient( $once ) ) {
			self::done();
		}
		set_transient( $once, 1, 5 * MINUTE_IN_SECONDS );

		// 5. Limits. Counters go up only now, after every other check has passed.
		$user_id  = get_current_user_id();
		$ip_limit = Qtdesk_Settings::ip_limit();
		$ip       = Qtdesk_Limits::client_ip();
		if ( ! Qtdesk_Limits::within( 'email', self::$values['email'], 3 )
			|| ( $user_id && ! Qtdesk_Limits::within( 'user', (string) $user_id, 5 ) )
			|| ! Qtdesk_Limits::within( 'ip', $ip, $ip_limit ) ) {
			delete_transient( $once );
			self::$errors['form'] = __( 'You have sent several requests already. Please try again in an hour.', 'quote-desk-for-woocommerce' );
			return;
		}
		// A refused attempt above spends nothing; the counters go up together.
		Qtdesk_Limits::record( 'email', self::$values['email'], 3 );
		if ( $user_id ) {
			Qtdesk_Limits::record( 'user', (string) $user_id, 5 );
		}
		Qtdesk_Limits::record( 'ip', $ip, $ip_limit );

		$overflow = false;
		$cap      = Qtdesk_Settings::global_cap();
		if ( $cap > 0 ) {
			$exempt = $user_id && ( new WC_Customer( $user_id ) )->get_is_paying_customer();
			switch ( Qtdesk_Limits::decide( Qtdesk_Limits::bump_global(), $cap, Qtdesk_Settings::ceiling(), (bool) $exempt ) ) {
				case 'refuse':
					$contact = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );

					self::$errors['form'] = sprintf(
						/* translators: %s: the shop's email address. */
						__( 'We cannot take more requests right now. Please write to us at %s.', 'quote-desk-for-woocommerce' ),
						$contact
					);
					return;
				case 'overflow':
					$overflow = true;
					break;
			}
		}

		try {
			self::create_order( self::$values, $overflow );
		} catch ( Exception $e ) {
			delete_transient( $once );
			self::$errors['form'] = __( 'Sorry, your request could not be saved. Please try again.', 'quote-desk-for-woocommerce' );
			return;
		}
		self::done();
	}

	/**
	 * Redirect to the page with the "sent" flag (post/redirect/get) and stop.
	 */
	private static function done() {
		wp_safe_redirect( add_query_arg( 'qtdesk_sent', '1', self::page_url() ) );
		exit;
	}

	/**
	 * Turn the cart into a "Quote requested" order, the way checkout builds its lines.
	 *
	 * @param array $values   Cleaned form values.
	 * @param bool  $overflow Whether the shop-wide cap was exceeded (saved quietly).
	 * @return WC_Order
	 * @throws Exception When the order cannot be saved.
	 */
	public static function create_order( array $values, $overflow ) {
		$parts = explode( ' ', $values['name'], 2 );
		$first = $parts[0];
		$last  = isset( $parts[1] ) ? $parts[1] : '';

		// Put the delivery details on the customer first, so tax and prices follow the country typed on the form.
		$customer = WC()->customer;
		$before   = array(
			'billing_country'    => $customer->get_billing_country(),
			'billing_postcode'   => $customer->get_billing_postcode(),
			'billing_address_1'  => $customer->get_billing_address_1(),
			'shipping_country'   => $customer->get_shipping_country(),
			'shipping_postcode'  => $customer->get_shipping_postcode(),
			'shipping_address_1' => $customer->get_shipping_address_1(),
		);
		$customer->set_billing_country( $values['country'] );
		$customer->set_billing_postcode( $values['postcode'] );
		$customer->set_shipping_country( $values['country'] );
		$customer->set_shipping_postcode( $values['postcode'] );
		$customer->set_billing_address_1( $values['address'] );
		$customer->set_shipping_address_1( $values['address'] );
		try {
			WC()->cart->calculate_totals();
		} finally {
			// Give the shopper's saved address back: a quote request must not overwrite it, even if totals fail.
			foreach ( $before as $prop => $old ) {
				$customer->{'set_' . $prop}( $old );
			}
		}

		// Built in memory and saved once, so other plugins never see a half-made order.
		$order = new WC_Order();
		$order->set_created_via( 'qtdesk' );
		if ( get_current_user_id() ) {
			$order->set_customer_id( get_current_user_id() );
		}

		foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
			$item = new WC_Order_Item_Product();

			$item->legacy_values        = $cart_item;
			$item->legacy_cart_item_key = $cart_key;

			$product = $cart_item['data'];
			// Price before coupons and without tax: tax is worked out again for the order's address, and the shop prices the rest.
			// Built the way checkout builds it (no set_product(), which would overwrite the chosen variation).
			$item->set_props(
				array(
					'quantity'     => $cart_item['quantity'],
					'variation'    => $cart_item['variation'],
					'subtotal'     => $cart_item['line_subtotal'],
					'total'        => $cart_item['line_subtotal'],
					'name'         => $product->get_name(),
					'tax_class'    => $product->get_tax_class(),
					'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
					'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				)
			);
			$item->set_backorder_meta();
			$item = apply_filters( 'woocommerce_checkout_create_order_line_item_object', $item, $cart_key, $cart_item, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's own hook.
			// Same hook as checkout, with the unsaved item: other plugins copy their cart data onto it.
			do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_key, $cart_item, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's own hook.
			$order->add_item( $item );
		}

		$order->set_prices_include_tax( wc_prices_include_tax() );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_billing_first_name( $first );
		$order->set_billing_last_name( $last );
		$order->set_billing_email( $values['email'] );
		$order->set_billing_phone( $values['phone'] );
		$order->set_billing_country( $values['country'] );
		$order->set_billing_postcode( $values['postcode'] );
		$order->set_billing_address_1( $values['address'] );
		$order->set_shipping_first_name( $first );
		$order->set_shipping_last_name( $last );
		$order->set_shipping_country( $values['country'] );
		$order->set_shipping_postcode( $values['postcode'] );
		$order->set_shipping_address_1( $values['address'] );
		$order->set_customer_note( $values['message'] );
		$order->update_meta_data( '_qtdesk_quote', 'yes' );
		if ( $overflow ) {
			$order->update_meta_data( '_qtdesk_overflow', 'yes' );
		}
		$order->set_status( Qtdesk_Status::SLUG );
		$order->calculate_totals( true );

		$coupons = WC()->cart->get_applied_coupons();
		if ( $coupons ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: coupon codes. */
					__( 'The customer had these coupons in the cart: %s. They were not applied to this quote.', 'quote-desk-for-woocommerce' ),
					implode( ', ', array_map( 'wc_clean', $coupons ) )
				)
			);
		}

		$order->save();
		WC()->cart->empty_cart();

		/**
		 * Fires after a quote request has been saved.
		 *
		 * @param WC_Order $order    The new order.
		 * @param bool     $overflow Whether the shop-wide hourly cap was exceeded (no customer email should go out).
		 */
		do_action( 'qtdesk_request_created', $order, $overflow );
		return $order;
	}

	/**
	 * Print one form field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $type  Input type or "textarea".
	 * @param bool   $req   Whether it is required.
	 * @param string $auto  Autocomplete token.
	 */
	private static function field( $name, $label, $type, $req, $auto ) {
		$value = isset( self::$values[ $name ] ) ? self::$values[ $name ] : '';
		$error = isset( self::$errors[ $name ] ) ? self::$errors[ $name ] : '';
		$id    = 'qtdesk-' . $name;
		echo '<p class="form-row qtdesk-field">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $req ? ' <span class="required" aria-hidden="true">*</span>' : '' ) . '</label>';
		$described = $error ? ' aria-describedby="' . esc_attr( $id ) . '-error" aria-invalid="true"' : '';
		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( 'qtdesk_' . $name ) . '" rows="4" maxlength="2000"' . $described . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $described is built from escaped parts.
		} else {
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( 'qtdesk_' . $name ) . '" value="' . esc_attr( $value ) . '" autocomplete="' . esc_attr( $auto ) . '"' . ( $req ? ' required' : '' ) . $described . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $described is built from escaped parts.
		}
		if ( $error ) {
			echo '<span class="qtdesk-error" id="' . esc_attr( $id ) . '-error">' . esc_html( $error ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * Render the page content (shortcode).
	 *
	 * @return string
	 */
	public static function render() {
		wc_maybe_define_constant( 'DONOTCACHEPAGE', true );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		ob_start();
		echo '<div class="qtdesk-request woocommerce">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A display flag only.
		if ( isset( $_GET['qtdesk_sent'] ) ) {
			echo '<div class="woocommerce-message" role="status">' . esc_html__( 'Thank you. We received your request and will reply with a quote.', 'quote-desk-for-woocommerce' ) . '</div></div>';
			return ob_get_clean();
		}

		if ( WC()->cart->is_empty() ) {
			echo '<p>' . esc_html__( 'Your cart is empty. Add the products you would like a quote for, then come back here.', 'quote-desk-for-woocommerce' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Back to the shop', 'quote-desk-for-woocommerce' ) . '</a></p></div>';
			return ob_get_clean();
		}

		self::summary();

		if ( isset( self::$errors['form'] ) ) {
			echo '<div class="woocommerce-error" role="alert" tabindex="-1" id="qtdesk-errors">' . esc_html( self::$errors['form'] ) . '</div>';
		} elseif ( self::$errors ) {
			echo '<div class="woocommerce-error" role="alert" tabindex="-1" id="qtdesk-errors">' . esc_html__( 'Please check the fields marked below.', 'quote-desk-for-woocommerce' ) . '</div>';
		}

		if ( ! self::$values && is_user_logged_in() ) {
			$user         = wp_get_current_user();
			self::$values = array(
				'name'  => trim( $user->first_name . ' ' . $user->last_name ),
				'email' => $user->user_email,
			);
		}
		if ( empty( self::$values['country'] ) ) {
			self::$values['country'] = WC()->countries->get_base_country();
		}

		// Field names carry a prefix: a bare "name" in a POST makes WordPress look for a post of that name and answer 404.
		$action = self::page_url();
		echo '<form method="post" class="qtdesk-form" novalidate' . ( $action ? ' action="' . esc_url( $action ) . '"' : '' ) . '>';
		wp_nonce_field( self::NONCE, '_qtdesk_nonce' );
		self::field( 'name', __( 'Your name', 'quote-desk-for-woocommerce' ), 'text', true, 'name' );
		self::field( 'email', __( 'Email address', 'quote-desk-for-woocommerce' ), 'email', true, 'email' );
		self::field( 'phone', __( 'Phone (optional)', 'quote-desk-for-woocommerce' ), 'tel', false, 'tel' );
		self::field( 'address', __( 'Delivery address', 'quote-desk-for-woocommerce' ), 'text', true, 'street-address' );
		self::field( 'postcode', __( 'Postcode', 'quote-desk-for-woocommerce' ), 'text', false, 'postal-code' );

		$error = isset( self::$errors['country'] ) ? self::$errors['country'] : '';
		echo '<p class="form-row qtdesk-field"><label for="qtdesk-country">' . esc_html__( 'Country', 'quote-desk-for-woocommerce' ) . ' <span class="required" aria-hidden="true">*</span></label>';
		echo '<select id="qtdesk-country" name="qtdesk_country" autocomplete="country"' . ( $error ? ' aria-describedby="qtdesk-country-error" aria-invalid="true"' : '' ) . '>';
		foreach ( WC()->countries->get_shipping_countries() as $code => $country_name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( self::$values['country'], $code, false ) . '>' . esc_html( $country_name ) . '</option>';
		}
		echo '</select>';
		if ( $error ) {
			echo '<span class="qtdesk-error" id="qtdesk-country-error">' . esc_html( $error ) . '</span>';
		}
		echo '</p>';

		self::field( 'message', __( 'Message (optional)', 'quote-desk-for-woocommerce' ), 'textarea', false, 'off' );

		// Honeypot: hidden from people and from assistive technology.
		echo '<p class="qtdesk-hp" aria-hidden="true" style="position:absolute;left:-9999px;"><label>' . esc_html__( 'Leave this empty', 'quote-desk-for-woocommerce' ) . '<input type="text" name="qtdesk_website" value="" tabindex="-1" autocomplete="off"></label></p>';

		echo '<p class="qtdesk-privacy">' . wp_kses_post(
			wc_replace_policy_page_link_placeholders(
				__( 'We use your details only to answer this request. Read our [privacy_policy].', 'quote-desk-for-woocommerce' )
			)
		) . '</p>';
		echo '<p><button type="submit" class="button alt" name="' . esc_attr( self::ACTION ) . '" value="1">' . esc_html( Qtdesk_Settings::button_text() ) . '</button></p>';
		echo '</form>';
		if ( self::$errors ) {
			wp_print_inline_script_tag( 'var qe=document.getElementById("qtdesk-errors");if(qe){qe.focus();}' );
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Read-only summary of the cart.
	 */
	private static function summary() {
		echo '<table class="shop_table qtdesk-summary"><thead><tr><th>' . esc_html__( 'Product', 'quote-desk-for-woocommerce' ) . '</th><th>' . esc_html__( 'Quantity', 'quote-desk-for-woocommerce' ) . '</th><th>' . esc_html__( 'Price now', 'quote-desk-for-woocommerce' ) . '</th></tr></thead><tbody>';
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			echo '<tr><td>' . esc_html( $product->get_name() );
			echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes this.
			echo '</td><td>' . esc_html( (string) $cart_item['quantity'] ) . '</td><td>' . wp_kses_post( WC()->cart->get_product_subtotal( $cart_item['data'], $cart_item['quantity'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="qtdesk-note">' . esc_html__( 'These are the prices in your cart now. We will send you the final quote, with delivery and tax.', 'quote-desk-for-woocommerce' ) . '</p>';
	}
}
