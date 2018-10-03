<?php
/**
 * Frontend class
 *
 * @author Yithemes
 * @package YITH WooCommerce Quick View
 * @version 1.1.1
 */

if ( ! defined( 'YITH_WCQV' ) ) {
	exit;
} // Exit if accessed directly

if( ! class_exists( 'YITH_WCQV_Frontend' ) ) {
	/**
	 * Admin class.
	 * The class manage all the Frontend behaviors.
	 *
	 * @since 1.0.0
	 */
	class YITH_WCQV_Frontend {

		/**
		 * Single instance of the class
		 *
		 * @var \YITH_WCQV_Frontend
		 * @since 1.0.0
		 */
		protected static $instance;

		/**
		 * Plugin version
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public $version = YITH_WCQV_VERSION;

		/**
		 * Button quick view position
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public $position = '';

		/**
		 * Enable zoom magnifier
		 *
		 * @var boolean
		 * @since 1.0.0
		 */
		public $enable_zoom = false;

		/**
		 * Product images ids
		 *
		 * @var array
		 * @since 1.0.0
		 */
		public $product_images = array();

		/**
		 * Returns single instance of the class
		 *
		 * @return \YITH_WCQV_Frontend
		 * @since 1.0.0
		 */
		public static function get_instance(){
			if( is_null( self::$instance ) ){
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @access public
		 * @since 1.0.0
		 */
		public function __construct() {

			$this->position = get_option( 'yith-wcqv-button-position' );
			// enable zoom option
			$this->enable_zoom = ( get_option( 'yith-wcqv-enable-zoom-magnifier' ) == 'yes' && get_option('yith-wcqv-product-images-mode') != 'slider' && defined( 'YITH_YWZM_DIR' ) );

			// custom styles and javascripts
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ), 20 );

			// quick view ajax
			if( $this->use_wc_ajax() ){
				add_action('wc_ajax_yith_load_product_quick_view', array($this, 'yith_load_product_quick_view_ajax'));
			}
			// no priv
			add_action( 'wp_ajax_yith_load_product_quick_view', array($this, 'yith_load_product_quick_view_ajax') );
			add_action( 'wp_ajax_nopriv_yith_load_product_quick_view', array($this, 'yith_load_product_quick_view_ajax') );
			
			// add button
			if( $this->position == 'add-cart' ) {
				add_action( 'woocommerce_after_shop_loop_item', array( $this, 'yith_add_quick_view_button' ), 15 );
			}
			else {
				add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'yith_add_quick_view_button' ), 15 );
			}

			add_action( 'yith_wcwl_table_after_product_name', array( $this, 'add_quick_view_button_in_wishlist' ), 15 );

			// load action for product template
			add_action( 'init', array( $this , 'yith_quick_view_action_template' ) );

			// filter image size
			add_filter( 'single_product_large_thumbnail_size', array( $this, 'yith_set_image_size' ) );

			// add attachment id to variation data
			add_filter( 'woocommerce_available_variation', array( $this, 'add_attachment_id' ), 10, 3 );

			// woocommerce multilingual currency
			add_filter( 'wcml_multi_currency_is_ajax', array( $this, 'set_correct_currency' ), 10, 1 );

			// add redirect to checkout
			add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'get_checkout_url' ), 99, 1 );

			add_shortcode( 'yith_quick_view', array( $this, 'quick_view_shortcode' ) );
		}


		/**
		 * Enqueue styles and scripts
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function enqueue_styles_scripts() {

			$suffix       = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$assets_path  = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';

			wp_register_script( 'jquery-blockui', $assets_path . 'js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array( 'jquery' ), '2.60', true );

			wp_register_script( 'yith-wcqv-frontend', YITH_WCQV_ASSETS_URL . '/js/frontend' . $suffix . '.js', array('jquery'), $this->version, true );

			$paths      = apply_filters( 'yith_wcqv_stylesheet_paths', array( WC()->template_path() . 'yith-quick-view.css', 'yith-quick-view.css' ) );
			$located    = locate_template( $paths, false, false );
			$search     = array( get_stylesheet_directory(), get_template_directory() );
			$replace    = array( get_stylesheet_directory_uri(), get_template_directory_uri() );
			$stylesheet = ! empty( $located ) ? str_replace( $search, $replace, $located ) : YITH_WCQV_ASSETS_URL . '/css/yith-quick-view.css';

			wp_register_style( 'yith-quick-view', $stylesheet, array(), $this->version );

			// third part plugin
			wp_register_style( 'external-plugin', YITH_WCQV_ASSETS_URL . '/css/style-external.css', array(), $this->version );
			wp_register_script( 'external-plugin', YITH_WCQV_ASSETS_URL . '/js/scripts-external.min.js', array('jquery'), $this->version, true );
			wp_register_script( 'bxslider-plugin', YITH_WCQV_ASSETS_URL . '/js/jquery.bxslider.min.js', array('jquery'), $this->version, true );

			// enqueue style
			wp_enqueue_style( 'external-plugin' );
			wp_enqueue_style( 'yith-quick-view' );

			if( $this->enable_zoom ){
				wp_enqueue_style( 'yith-magnifier');
				// new magnifier handler
				wp_enqueue_style( 'ywzm-magnifier');
			}
			
			// add custom style
			$inline_css = yith_wcqv_get_custom_style();
			wp_add_inline_style( 'yith-quick-view', $inline_css );
		}

		/**
		 * Enqueue scripts if quick view is added
		 *
		 * @access public
		 * @since 1.0.7
		 * @author Francesco Licandro
		 */
		public function enqueue_scripts() {

			wp_enqueue_script( 'jquery-blockui' );
			wp_enqueue_script( 'yith-wcqv-frontend' );
			wp_enqueue_script( 'external-plugin' );
			wp_enqueue_script( 'bxslider-plugin' );

			if( $this->enable_zoom ){
				wp_enqueue_script('yith-magnifier');
				// new magnifier handler
				wp_enqueue_script('ywzm_frontend');
			}
		}

		/**
		 * Add quick view button in wc product loop
		 *
		 * @access public
		 * @param int $product_id
		 * @param string $label
		 * @param string $type
		 * @param boolean $return
		 * @return string
		 * @since  1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_add_quick_view_button( $product_id = 0, $label = '', $type = '', $return = false ) {

			global $product;

			if( ! $product_id ){
				$product_id = yit_get_prop( $product, 'id', true );
			}

			// enqueue scripts
			if( ! has_action( 'wp_footer', array( $this, 'enqueue_scripts' ) ) ) {
				add_action( 'wp_footer', array( $this, 'enqueue_scripts' ) );
			}
			// add modal template
			if( ! has_action( 'wp_footer', array( $this, 'yith_quick_view' ) ) ) {
				add_action( 'wp_footer', array( $this, 'yith_quick_view' ) );
			}

			$content = $button = '';
			! $type && $type = get_option( 'yith-wcqv-button-type' );

			if( $type === 'icon' ) {
				$icon = get_option( 'yith-wcqv-button-icon' );
				$content = '<img src="' . esc_url( $icon ) . '" class="yith-wcqv-icon"/>';
			}
			else {
				! $label && $label = $this->get_button_label();
				$content = '<span>' . esc_html( $label ) . '</span>';
			}

			if( $this->position == 'image' ) {
				$button = '<div class="yith-wcqv-button inside-thumb" data-product_id="' . $product_id . '">'. $content . '</div>';
			}
			else {
				$class = ( $type === 'button' ) ? 'button' : 'qvicon';
				$button = '<a href="#" class="yith-wcqv-button ' . $class .'" data-product_id="' . $product_id . '">' . $content . '</a>';
			}

			// let's third part filter button html
			$button = apply_filters( 'yith_wcqv_button_html', $button, $product_id, $content );

			if( $return ) {
				return $button;
			}
			else {
				echo $button;
			}
		}

		/**
		 * Add quick view button in wishlist table
		 *
		 * @access public
		 * @since 1.0.7
		 * @author Francesco Licandro
		 */
		public function add_quick_view_button_in_wishlist() {
			global $product;

			if( get_option( 'yith-wcqv-enable-wishlist' ) != 'yes' || get_option( 'yith-wcqv-modal-type' ) != 'yith-modal' ){
				return;
			}

			// enqueue scripts
			if( ! has_action( 'wp_footer', array( $this, 'enqueue_scripts' ) ) ) {
				add_action( 'wp_footer', array( $this, 'enqueue_scripts' ) );
			}
			// add modal template
			if( ! has_action( 'wp_footer', array( $this, 'yith_quick_view' ) ) ) {
				add_action( 'wp_footer', array( $this, 'yith_quick_view' ) );
			}

			$label      = $this->get_button_label();
			$product_id = yit_get_prop( $product, 'id', true );

			echo '<a href="#" class="yith-wcqv-button button" data-product_id="' . $product_id . '">' . $label . '</a>';
		}

		/**
		 * Enqueue scripts and pass variable to js used in quick view
		 *
		 * @access public
		 * @return bool
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_woocommerce_quick_view() {

			wp_enqueue_script( 'wc-add-to-cart-variation' );

			// enqueue wc color e label variation style
			wp_enqueue_script( 'yith_wccl_frontend' );
			wp_enqueue_style( 'yith_wccl_frontend' );

			$lightbox_en = get_option( 'yith-wcqv-enable-lightbox' ) == 'yes';
			$mobile      = wp_is_mobile();
			$type 		 = get_option( 'yith-wcqv-modal-type' );

			// if enabled load prettyPhoto css
			if( $lightbox_en ) {

				$assets_path = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';

				wp_enqueue_script( 'prettyPhoto', $assets_path . 'js/prettyPhoto/jquery.prettyPhoto.min.js', array( 'jquery' ), false, true );
				wp_enqueue_style( 'woocommerce_prettyPhoto_css', $assets_path . 'css/prettyPhoto.css' );
			}

			$version = version_compare( preg_replace( '/-beta-([0-9]+)/', '', WC()->version ), '2.3.0', '<' );

			// loader gif
			$loader = apply_filters( 'yith_quick_view_loader_gif', YITH_WCQV_ASSETS_URL . '/image/qv-loader.gif' );

			// ajax add to cart
			$ajax_cart = get_option( 'yith-wcqv-ajax-add-to-cart' ) == 'yes';

			// added compatibility with woocommerce-quantity-increment plugin
			$increment_plugin = class_exists( 'WooCommerce_Quantity_Increment' );

			// Allow user to load custom style and scripts
			do_action( 'yith_quick_view_custom_style_scripts' );

			// selectors
			$main_product = 'li.product';
			$main_product_link = 'li.product > a';

			wp_localize_script( 'yith-wcqv-frontend', 'yith_qv', array (
					'ajaxurl'           => $this->use_wc_ajax() ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : admin_url( 'admin-ajax.php', 'relative' ),
					'loader'            => $loader,
					'is2_2'             => $version,
					'increment_plugin'  => $increment_plugin, // added compatibility with woocommerce-quantity-increment plugin
					'type'              => $type,
					'ismobile'          => $mobile,
					'ajaxcart'          => $ajax_cart,
					'enable_loading'    => get_option( 'yith-wcqv-enable-loading-effect' ) == 'yes',
					'loading_text'      => get_option( 'yith-wcqv-enable-loading-text' ),
					'enable_zoom'       => get_option( 'yith-wcqv-enable-zoom-magnifier' ) == 'yes',
					'redirect_checkout' => get_option( 'yith-wcqv-ajax-redirect-to-checkout' ) == 'yes',
					'checkout_url'      => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : WC()->cart->get_checkout_url(),
					'main_product'      => apply_filters( 'yith_wcqv_main_product_selector', $main_product ),
					'main_product_link' => apply_filters( 'yith_wcqv_main_product_link_selector', $main_product_link ),
					'popup_size_width'  => get_option( 'yith-quick-view-modal-width', '1000' ),
					'popup_size_height' => get_option( 'yith-quick-view-modal-height', '500' ),
                    'main_image_class'  => apply_filters( 'yith_wcqv_main_image_class', 'img.attachment-shop_catalog, img.kw-prodimage-img' )
				)
			);

			return true;
		}

		/**
		 * Ajax action to load product in quick view
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_load_product_quick_view_ajax() {

			if ( ! isset( $_REQUEST['product_id'] ) ) {
				die();
			}

			$product_id = intval( $_REQUEST['product_id'] );

			$images_type = get_option('yith-wcqv-product-images-mode');
			$nav = get_option( 'yith-wcqv-enable-nav' ) == 'yes';
			$in_same_cat = get_option( 'yith-wcqv-enable-nav-same-category') == 'yes';
			$style = get_option( 'yith-wcqv-nav-style' );


			// remove product thumbnails gallery
			remove_all_actions( 'woocommerce_product_thumbnails' );
			// remove badge from nav
			if( function_exists( 'YITH_WCBM_Frontend' ) ) {
				remove_filter( 'post_thumbnail_html', array( YITH_WCBM_Frontend(), 'add_box_thumb' ), 999 );
			}

			// set the main wp query for the product
			wp( 'p=' . $product_id . '&post_type=product' );

			$prev_product_id = $next_product_id = false;

			if( $nav ) {
				// if in same cat get next e prev post
				if( $in_same_cat ) {
					// prev
					$prev_product       = wc_get_product( get_previous_post( $in_same_cat, '', 'product_cat' ) );
					( $prev_product && ! post_password_required( $prev_product ) ) && $prev_product_id = yit_get_prop( $prev_product, 'id', true );

					// next
					$next_product       = wc_get_product( get_next_post( $in_same_cat, '', 'product_cat' ) );
					( $next_product && ! post_password_required( $next_product ) ) && $next_product_id = yit_get_prop( $next_product, 'id', true );
				}
				else {
					isset( $_REQUEST[ 'prev_product_id'] ) && $prev_product_id = intval( $_REQUEST[ 'prev_product_id'] );
					isset( $_REQUEST[ 'next_product_id'] ) && $next_product_id = intval( $_REQUEST[ 'next_product_id'] );
				}
			}

			// Prev Product Preview
			$prev_product_preview   = $prev_product_id ? get_the_post_thumbnail( $prev_product_id, 'shop_thumbnail' ) : '';
			$prev_product_preview   .= ( $prev_product_id && $style != 'diamond' ) ? '<h4>' . get_the_title( $prev_product_id ) . '</h4>' : '';

			// Next Product Preview
			$next_product_preview   = $next_product_id ? get_the_post_thumbnail( $next_product_id, 'shop_thumbnail' ) : '';
			$next_product_preview   .= ( $next_product_id && $style != 'diamond' ) ? '<h4>' . get_the_title( $next_product_id ) . '</h4>' : '';


			// re-add badge from nav
			if( function_exists( 'YITH_WCBM_Frontend' ) ) {
				add_filter( 'post_thumbnail_html', array( YITH_WCBM_Frontend(), 'add_box_thumb' ), 999, 2 );
			}

			// add hidden to add to cart form
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_hidden_to_cart_form' ) );

			// change template for variable products
			$attributes = false;
			if ( isset( $GLOBALS['yith_wccl'] ) ) {
				$GLOBALS['yith_wccl']->obj = new YITH_WCCL_Frontend( YITH_WCCL_VERSION );
				$GLOBALS['yith_wccl']->obj->override();
			}
			elseif( defined( 'YITH_WCCL_PREMIUM' ) && YITH_WCCL_PREMIUM && class_exists( 'YITH_WCCL_Frontend' ) ) {
				$attributes = YITH_WCCL_Frontend()->create_attributes_json( $product_id, true );
			}

			// Allow custom action for user
			do_action( 'yith_load_product_quick_view_custom_action' );

			$product_link = get_permalink( $product_id );
			if( is_ssl() ){
				$product_link = str_replace('http://', 'https://', $product_link );
			}

			ob_start();

			// load content template
			wc_get_template( 'yith-quick-view-content.php', array(), '', YITH_WCQV_DIR . 'templates/' );

			$html = ob_get_clean();

			echo json_encode( array (
				"html"                  => $html,
				"prod_attr"             => $attributes,
				"prev_product"          => $prev_product_id,
				"prev_product_preview"  => $prev_product_preview,
				"next_product"          => $next_product_id,
				"next_product_preview"  => $next_product_preview,
				'images_type'           => $images_type,
				'product_link'          => $product_link
			));

			die();
		}

		/**
		 * Add hidden to add to cart form
		 *
		 * @since 1.1.0
		 * @author Francesco Licandro
		 */
		public function add_hidden_to_cart_form(){
			echo '<input type="hidden" name="yith_is_quick_view" id="yith_is_quick_view" value="1"/>';
		}

		/**
		 * Return the checkout url
		 *
		 * @since 1.1.0
		 * @param string $url
		 * @return string
		 * @author Francesco Licandro
		 */
		public function get_checkout_url( $url ){
			// add redirect to checkout
			if( get_option( 'yith-wcqv-ajax-redirect-to-checkout' ) != 'yes' || ! isset( $_REQUEST['yith_is_quick_view'] ) ){
				return $url;
			}

			return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : WC()->cart->get_checkout_url();
		}

		/**
		 * Load quick view template
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_quick_view() {
			$this->yith_woocommerce_quick_view();

			$type = get_option( 'yith-wcqv-modal-type' );

			$args = array(
				'type'      => $type,
				'effect'    => ( $type == 'yith-modal' ) ? get_option( 'yith-quick-view-modal-effect' ) : '',
				'nav'       => get_option( 'yith-wcqv-enable-nav' ) == 'yes',
				'nav_style' => get_option( 'yith-wcqv-nav-style' ),
				'is_mobile'  => wp_is_mobile() ? 'is-mobile' : ''
			);

			$args = apply_filters( 'yith_args_quick_view_template', $args );

			wc_get_template( 'yith-quick-view.php', $args, '', YITH_WCQV_DIR . 'templates/' );
		}

		/**
		 * Load share template for single product in quick view
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_quick_view_share() {
			wc_get_template( 'yith-quick-view-share.php', array(), '', YITH_WCQV_DIR . 'templates/' );
		}

		/**
		 * Load wc action for quick view product template
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_quick_view_action_template() {

			global $yith_wcmg;

			$this->set_action_dynamic_pricing();

			if( get_option( 'yith-wcqv-product-show-thumb') == 'yes' ) {

				add_action( 'yith_wcqv_product_image', 'woocommerce_show_product_sale_flash', 10 );
				add_action( 'yith_wcqv_product_image', array( $this, 'custom_thumb_html' ) );
			}
			if( get_option( 'yith-wcqv-product-show-title') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_title', 5 );
			}
			if( get_option( 'yith-wcqv-product-show-rating') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_rating', 10 );
			}
			if( get_option( 'yith-wcqv-product-show-price') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_price', 15 );
			}
			if( get_option( 'yith-wcqv-product-show-excerpt') == 'yes' ) {

				if( get_option( 'yith-wcqv-product-full-description' ) == 'no' ) {
					add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_excerpt', 20 );
				}
				else {
					add_action( 'yith_wcqv_product_summary', array( $this, 'get_full_description' ), 20 );
				}
			}
			if( get_option( 'yith-wcqv-product-show-add-to-cart') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_add_to_cart', 25 );
			}

			if( defined( 'YITH_YWRAQ_VERSION' ) && class_exists( 'YITH_YWRAQ_Frontend' ) && get_option( 'yith-wcqv-product-show-quote', 'yes' ) == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', array( YITH_YWRAQ_Frontend(), 'add_button_single_page' ), 27 );
				add_action( 'yith_wcqv_product_summary', array( $this, 'hide_add_to_cart' ), 27 );
			}
			if( defined( 'YITH_WCWL' ) && YITH_WCWL && get_option( 'yith-wcqv-product-show-wishlist', 'yes' ) == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', array( $this, 'yith_wishlist_quick_view' ), 27 );
			}
			if ( shortcode_exists( 'yith_compare_button' ) && get_option( 'yith-wcqv-product-show-compare', 'yes' ) == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', array( $this, 'yith_compare_quick_view' ), 27 );
			}

			if( get_option( 'yith-wcqv-details-button') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary',  array( $this, 'yith_add_view_details_button' ), 30 );
			}
			if( get_option( 'yith-wcqv-product-show-meta') == 'yes' ) {
				add_action( 'yith_wcqv_product_summary', 'woocommerce_template_single_meta', 30 );
			}
			if( get_option( 'yith-wcqv-enable-share' ) == 'yes'  ) {
				add_action( 'yith_wcqv_product_summary', array( $this, 'yith_quick_view_share' ), 35 );
			}
		}

        /**
         * Set action for Dynamic Pricing Plugin
         *
         * @since 1.2.3
         * @author Francesco Licandro
         */
        public function set_action_dynamic_pricing(){

            if( ! defined( 'YITH_YWDPD_PREMIUM' )
                || ! function_exists( 'YITH_WC_Dynamic_Pricing_Frontend' ) || ! function_exists( 'YITH_WC_Dynamic_Pricing' ) ){
                return;
            }

	        if( YITH_WC_Dynamic_Pricing()->get_option( 'show_quantity_table' ) == 'yes' && get_option( 'yith-wcqv-product-show-discount-table', 'yes' ) == 'yes' ) {
		        $position = YITH_WC_Dynamic_Pricing()->get_option( 'show_quantity_table_place' );
		        switch ( $position ) {
			        case 'before_add_to_cart':
			        case 'after_excerpt':
				        $priority = 24;
				        break;
			        case 'before_excerpt':
				        $priority = 18;
				        break;
			        case 'after_meta':
				        $priority = 31;
				        break;
			        default:
				        $priority = 26;
				        break;
		        }

		        // then add action
		        add_action( 'yith_wcqv_product_summary', array(
			        YITH_WC_Dynamic_Pricing_Frontend(),
			        'show_table_quantity'
		        ), $priority );
	        }

	        if( YITH_WC_Dynamic_Pricing()->get_option( 'show_note_on_products' ) == 'yes' && get_option( 'yith-wcqv-product-show-discount-note', 'yes' ) == 'yes' ) {
		        $position = YITH_WC_Dynamic_Pricing()->get_option( 'show_note_on_products_place' );
		        switch ( $position ) {
			        case 'before_add_to_cart':
			        case 'after_excerpt':
						$priority = 24;
				        break;
			        case 'before_excerpt':
				        $priority = 18;
				        break;
			        case 'after_meta':
						$priority = 31;
				        break;
			        default:
				        $priority = 26;
				        break;
		        }

		        // then add action
		        add_action( 'yith_wcqv_product_summary', array(
			        YITH_WC_Dynamic_Pricing_Frontend(),
			        'show_note_on_products'
		        ), $priority );
	        }

        }

		/**
		 * Print custom image thumb html instead of woocommerce standard
		 *
		 * @access public
		 * @since 1.0.7
		 * @author Francesco Licandro
		 */
		public function custom_thumb_html(){
			global $post, $product;

            /**
             * @type $product \WC_Product
             */
			$main_image_id      = is_callable( array( $product, 'get_image_id' ) ) ? $product->get_image_id() : get_post_thumbnail_id( $product );
			$attachments_ids    = $this->get_attachments_ids( $product );
			$images_type        = get_option( 'yith-wcqv-product-images-mode' );

			// collect images
			$this->product_images = array_merge( array( $main_image_id ), (array) $attachments_ids );
			// prevent empty values
			$this->product_images = array_filter( $this->product_images );
			// prevent double values
			$this->product_images = array_filter( $this->product_images );

			// check for magnifier zoom
			$zoom_class = $this->enable_zoom ? 'yith_magnifier_zoom' : '';

			$html = '<div class="images">';

			if( $images_type == 'slider' && count( $this->product_images ) > 1 ) {
				$html .= $this->yith_quick_view_images_slider( '', 0, true );
			}
			else {
				// main image
				$image_title 	= esc_attr( get_the_title( $main_image_id ) );
				$image_caption 	= get_post( $main_image_id )->post_excerpt;
				$image_link  	= wp_get_attachment_url( $main_image_id );
				// check to use or not get image method for prevent https error on certain configuration
				$use_get_image  = apply_filters( 'yith_wcqv_use_get_image_method', true );
				if( $use_get_image ) {
				    $image 			= $product->get_image( 'quick_view_image_size', array( 'title' => $image_title, 'alt' => $image_title ));
                }
                else {
				    $product_id = yit_get_base_product_id( $product );
				    $image = get_the_post_thumbnail( $product_id,  'quick_view_image_size', array( 'title' => $image_title, 'alt' => $image_title ) );
                }

				$html .= '<a href="' . $image_link . '" itemprop="image" class=" woocommerce-main-image '.$zoom_class.' zoom" title="'. $image_caption. '" data-rel="prettyPhoto[product-gallery]">' . $image . '</a>';

				if( $images_type == 'classic' ) {
					// get attachments
					$html .= $this->yith_quick_view_attachments_images( $this->product_images, true );
				}
			}

			if( $zoom_class ) {
				$html .= $this->get_zoom_magnifier_options();
			}

			$html .= '</div>';

			// let's third part filter html
			$html = apply_filters( 'yith_wcqv_product_image_html', $html );

			echo $html;
		}

		/**
		 * Get YITH WooCommerce Zoom Magnifier options
		 *
		 * @since 1.2.3
		 * @author Francesco Licandro
		 * @return string
		 */
		public function get_zoom_magnifier_options(){
			ob_start(); ?>

			<script type="text/javascript" charset="utf-8">
				var yith_magnifier_options = {
					enableSlider: false,
					showTitle: false,
					zoomWidth: '<?php echo get_option( 'yith_wcmg_zoom_width' ) ?>',
					zoomHeight: '<?php echo get_option( 'yith_wcmg_zoom_height' ) ?>',
					position: '<?php echo get_option( 'yith_wcmg_zoom_position' ) ?>',
					lensOpacity: '<?php echo get_option( 'yith_wcmg_lens_opacity' ) ?>',
					softFocus: <?php echo get_option( 'yith_wcmg_softfocus' ) == 'yes' ? 'true' : 'false' ?>,
					adjustY: 0,
					disableRightClick: false,
					phoneBehavior: '<?php echo get_option( 'yith_wcmg_zoom_mobile_position' ) ?>',
					loadingLabel: '<?php echo stripslashes( get_option( 'yith_wcmg_loading_label' ) ) ?>',
					zoom_wrap_additional_css: ''
				};
			</script>

			<?php
			return ob_get_clean();
		}

		/**
		 * Get full description instead of short
		 *
		 * @access public
		 * @since 1.0.7
		 * @author Francesco Licandro
		 */
		public function get_full_description(){
			global $post;

			ob_start();
			?>

			<div itemprop="description">
				<?php the_content(); ?>
			</div>

			<?php
			echo ob_get_clean();
		}

		/**
		 * Print wishlist shortcode in quick view
		 *
		 * @access  public
		 * @since 1.0.0
		 * @return void
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_wishlist_quick_view() {
			echo do_shortcode('[yith_wcwl_add_to_wishlist]');
		}

		/**
		 * Print compare shortcode in quick view
		 *
		 * @access  public
		 * @since 1.0.3
		 * @return void
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_compare_quick_view() {
			echo do_shortcode( '[yith_compare_button type="link"]' );
		}

		/**
		 * Add attachments images to a single image in quick view
		 *
		 * @access public
		 * @param array $attachments
		 * @param boolean $return
		 * @return void | string
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_quick_view_attachments_images( $attachments = array(), $return = false ) {

			global $post, $product;

			if( empty( $attachments ) ) {
				$attachments 	= array();
				$attachments[]  = get_post_thumbnail_id();
				$attachments    = array_merge( $attachments, $this->get_attachments_ids( $product ) );
			}

			$first = true;

			if( $return ) {
				ob_start();
			}

			?>
			<div class="yith-quick-view-thumbs">
					<?php foreach ( $attachments as $attachment ) : ?>

						<?php
						$src        = wp_get_attachment_image_src( $attachment, 'quick_view_image_size' );
						$image_link = wp_get_attachment_url( $attachment );
						?>

						<div class="yith-quick-view-single-thumb <?php echo $first ? 'active' : '' ?>" data-rel="prettyPhoto[product-gallery]"
						     data-img="<?php echo $src[0] ?>"
						     data-href="<?php echo $image_link ?>"
						     data-attachment_id="<?php echo $attachment ?>" >
							<?php echo wp_get_attachment_image( $attachment, 'shop_thumbnail' ); ?>
						</div>

						<?php $first = false; ?>
					<?php endforeach; ?>
			</div>
			<?php

			if( $return ) {
				return ob_get_clean();
			}
		}

		/**
		 * Add image slider instead a single image in quick view
		 *
		 * @access public
		 * @param string $html
		 * @param int $post_id
		 * @param boolean $return
		 * @return void | string
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_quick_view_images_slider( $html = '', $post_id = 0, $return = false ) {
			/**
			 * @type $product WC_Product
			 */
			if( $return ) {
				ob_start();
			}
			?>

			<ul class="yith-quick-view-images-slider bxslider">
				<?php foreach ( $this->product_images as $attachment ) : ?>
					<li class="yith-quick-view-slide">
						<?php echo wp_get_attachment_image( $attachment, 'quick_view_image_size' ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php

			if( $return ) {
				return ob_get_clean();
			}
		}

		/**
		 * Filter image size if is in quick view
		 *
		 * @access  public
		 * @param $size string
		 * @return string
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_set_image_size( $size ) {

			if( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'yith_load_product_quick_view' && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return 'quick_view_image_size';
			}

			return $size;
		}

		/**
		 * Add View Details button in quick view
		 *
		 * @access public
		 * @return void
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_add_view_details_button() {

			global $product;

			$label = esc_html( get_option( 'yith-wcqv-details-button-label' ) );
			$link  = $product->get_permalink();

			echo '<a href="' . $link . '" class="yith-wcqv-view-details button">' . $label . '</a>';
		}

		/**
		 * Check if is quick view
		 *
		 * @access public
		 * @return bool
		 * @since 1.0.0
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function yith_is_quick_view() {
			return ( defined('DOING_AJAX') && DOING_AJAX && isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'yith_load_product_quick_view' ) ? true : false;
		}

		/**
		 * Add attachment id to variation data in select variation form
		 *
		 * @since 1.0.4
		 *
		 * @param mixed $attr default array
		 * @param $variable
		 * @param object $variation \WC_Variation
		 * @return mixed
		 * @author Francesco Licandro <francesco.licandro@yithemes.com>
		 */
		public function add_attachment_id( $attr, $variable, $variation ) {
			if( ! $this->yith_is_quick_view() ) {
				return $attr;
			}

			$variation_id = version_compare( WC()->version, '2.7.0', '<' ) ? $variation->get_variation_id() : $variation->get_id();

			if ( has_post_thumbnail( $variation_id ) ) {
				$attachment_id = get_post_thumbnail_id( $variation_id );
				$attr['attachment_id'] = $attachment_id;
			}

			return $attr;
		}

		/**
		 * Set action for get correnct currency in woocommerce multilingual
		 *
		 * @access public
		 * @param array $action
		 * @return array
		 * @author Francesco Licandro
		 */
		public function set_correct_currency( $action ){
			$action[] = 'yith_load_product_quick_view';

			return $action;
		}

		/**
		 * Quick View shortcode button
		 *
		 * @access public
		 * @since 1.0.7
		 * @param array $atts
		 * @return string
		 * @author Francesco Licandro
		 */
		public function quick_view_shortcode( $atts ) {

			$atts = shortcode_atts(array(
				'product_id' => 0,
				'label'		 => '',
				'type'       => ''
			), $atts );

			extract( $atts );

			if( ! intval( $product_id ) ) {
				return '';
			}

			return $this->yith_add_quick_view_button( $product_id, $label, $type, true );
		}

		/**
		 * Hide add to cart for plugin Request a Quote on quick view
		 *
		 * @since 1.1.5
		 * @author Francesco Licandro
		 */
		public function hide_add_to_cart(){
			global $product;

			if ( get_option( 'ywraq_hide_add_to_cart' ) == 'yes' ) {
				if ( $product->product_type == 'variable' ) {
					$css = ".single_variation_wrap .variations_button button{
	                 display:none!important;
	                }";
				} else {
					$css = ".cart button.single_add_to_cart_button{
	                 display:none!important;
	                }";
				}

				echo '<style>'.$css.'</style>';
			}
		}

		/**
		 * Get product attachments ids
		 *
		 * @author Francesco Licandro
		 * @since 1.2.0
		 * @return array
		 * @param object $product \WC_Product
		 */
		public function get_attachments_ids( $product ){
			return is_callable( array( $product, 'get_gallery_image_ids' ) ) ? $product->get_gallery_image_ids() : $product->get_gallery_attachment_ids();
		}

		/**
		 * Get Quick View button label
		 *
		 * @author Francesco Licandro
		 * @since 1.2.0
		 * @return string
		 */
		public function get_button_label(){
			$label = get_option( 'yith-wcqv-button-label' );
			$label = call_user_func( '__' , $label, 'yith-woocommerce-quick-view' );

			return apply_filters( 'yith_wcqv_button_label', esc_html( $label ) );
		}

		/**
         * Check to use WC Ajax
         *
         * @since 1.2.1
         * @author Francesco Licandro
         * @return boolean
         */
		public function use_wc_ajax(){
		    return apply_filters( 'yith_wcqv_use_wc_ajax', false );
        }
	}
}
/**
 * Unique access to instance of YITH_WCQV_Frontend class
 *
 * @return \YITH_WCQV_Frontend
 * @since 1.0.0
 */
function YITH_WCQV_Frontend(){
	return YITH_WCQV_Frontend::get_instance();
}