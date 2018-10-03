<?php

if( ! function_exists( 'yith_wcqv_get_custom_style' ) ){
	/**
	 * Get custom style from plugin options panel
	 * 
	 * @since 1.2.0
	 * @author Francesco Licandro
	 * @return string
	 */
	function yith_wcqv_get_custom_style(){
		// set variables
		$content_w = get_option( 'yith-quick-view-modal-width' );
		$image_w   = get_option( 'yith-quick-view-product-image-width' );
		$background_modal = get_option( 'yith-wcqv-background-modal' );
		$button_text_color = get_option( 'yith-wcqv-button-quick-view-text-color' );
		$button_text_color_hover = get_option( 'yith-wcqv-button-quick-view-text-color-hover' );
		$button_color = get_option( 'yith-wcqv-button-quick-view-color');
		$button_color_hover = get_option( 'yith-wcqv-button-quick-view-color-hover' );
		$main_text_color = get_option( 'yith-wcqv-main-text-color' );
		$star_color = get_option( 'yith-wcqv-star-color' );
		$cart_color = get_option( 'yith-wcqv-button-cart-color' );
		$cart_color_hover = get_option( 'yith-wcqv-button-cart-color-hover' );
		$cart_text_color = get_option( 'yith-wcqv-button-cart-text-color' );
		$cart_text_color_hover = get_option( 'yith-wcqv-button-cart-text-color-hover' );
		$details_color = get_option( 'yith-wcqv-button-details-color' );
		$details_color_hover = get_option( 'yith-wcqv-button-details-color-hover' );
		$details_text_color = get_option( 'yith-wcqv-button-details-text-color' );
		$details_text_color_hover = get_option( 'yith-wcqv-button-details-text-color-hover' );

		$image_w = ( 100 * $image_w ) / $content_w;
		$summary_w = 100 - $image_w;

		$inline_css = " .yith-quick-view.yith-modal .yith-wcqv-main{background:{$background_modal};}
			.yith-wcqv-button.inside-thumb span, .yith-wcqv-button.button{color:{$button_text_color} !important;background:{$button_color} !important;}
			.yith-wcqv-button.inside-thumb:hover span, .yith-wcqv-button.button:hover{color:{$button_text_color_hover} !important;background:{$button_color_hover} !important;}
			.yith-quick-view-content.woocommerce div.summary h1,.yith-quick-view-content.woocommerce div.summary div[itemprop=\"description\"],.yith-quick-view-content.woocommerce div.summary .product_meta,.yith-quick-view-content.woocommerce div.summary .price,.yith-quick-view-content.woocommerce div.summary .price ins {color: {$main_text_color};}
			.yith-quick-view-content.woocommerce div.summary .woocommerce-product-rating .star-rating,.yith-quick-view-content.woocommerce div.summary .woocommerce-product-rating .star-rating:before {color: {$star_color};}
			.yith-quick-view-content.woocommerce div.summary button.button.alt{background: {$cart_color};color: {$cart_text_color};}
			.yith-quick-view-content.woocommerce div.summary button.button.alt:hover{background: {$cart_color_hover};color: {$cart_text_color_hover};}
			.yith-quick-view-content.woocommerce div.summary .yith-wcqv-view-details{background: {$details_color};color: {$details_text_color};}
			.yith-quick-view-content.woocommerce div.summary .yith-wcqv-view-details:hover{background: {$details_color_hover};color: {$details_text_color_hover};}
			.yith-quick-view.yith-modal .yith-quick-view-content div.images{width:{$image_w}%;}
			.yith-quick-view.yith-modal .yith-quick-view-content div.summary{width:{$summary_w}%;}";
		
		return $inline_css;
	}
}