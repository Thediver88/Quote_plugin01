<?php
/**
 * The "Request a quote" link in the cart (classic and block).
 *
 * @package QuoteDesk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the link; the page it leads to checks everything again on the server.
 */
class Qtdesk_Cart_Link {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'classic_link' ), 25 );
		add_filter( 'render_block', array( __CLASS__, 'enqueue_for_block_cart' ), 10, 2 );
	}

	/**
	 * Link under the checkout button of the classic cart.
	 */
	public static function classic_link() {
		$url = Qtdesk_Request::page_url();
		if ( '' === $url || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		echo '<a class="button qtdesk-quote-link" href="' . esc_url( $url ) . '">' . esc_html( Qtdesk_Settings::button_text() ) . '</a>';
	}

	/**
	 * Load the block-cart script when the cart block is on the page.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function enqueue_for_block_cart( $content, $block ) {
		if ( isset( $block['blockName'] ) && 'woocommerce/cart' === $block['blockName'] ) {
			$url = Qtdesk_Request::page_url();
			if ( '' !== $url ) {
				wp_enqueue_script(
					'qtdesk-cart-block',
					QTDESK_URL . 'assets/js/cart-block.js',
					array( 'wp-element', 'wp-plugins', 'wc-blocks-checkout' ),
					QTDESK_VERSION,
					true
				);
				wp_add_inline_script(
					'qtdesk-cart-block',
					'window.qtdeskCart = ' . wp_json_encode(
						array(
							'url'  => esc_url_raw( $url ),
							'text' => Qtdesk_Settings::button_text(),
						)
					) . ';',
					'before'
				);
			}
		}
		return $content;
	}
}
