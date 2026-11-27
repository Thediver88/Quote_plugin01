/**
 * Adds the "Request a quote" link to the block cart, in WooCommerce's order-summary slot.
 * The slot only exists while the cart has items, so the link disappears with an empty cart.
 * No build step: plain JavaScript using WordPress globals.
 */
( function () {
	'use strict';
	var data = window.qtdeskCart;
	if ( ! data || ! window.wp || ! window.wc || ! window.wc.blocksCheckout ) {
		return;
	}
	var slot = window.wc.blocksCheckout.ExperimentalOrderMeta;
	if ( ! slot || ! window.wp.plugins ) {
		return;
	}
	var el = window.wp.element.createElement;
	window.wp.plugins.registerPlugin( 'qtdesk-quote-link', {
		scope: 'woocommerce-checkout',
		render: function () {
			return el(
				slot,
				null,
				el(
					'div',
					{ className: 'qtdesk-quote-link-wrap' },
					el( 'a', { className: 'wc-block-components-button outlined qtdesk-quote-link', href: data.url }, data.text )
				)
			);
		}
	} );
}() );
