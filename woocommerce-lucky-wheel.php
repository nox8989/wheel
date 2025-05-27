<?php
/**
 * Plugin Name: Woocommerce Lucky Wheel Premium
 * Description: Collect customer's emails by spinning the lucky wheel game to get discount coupons.
 * Version: 1.2.10
 * Author URI: https://villatheme.com
 * Text Domain: woocommerce-lucky-wheel
 * Domain Path: /languages
 * Copyright 2018-2025 VillaTheme.com. All rights reserved.
 * Requires at least: 5.0
 * Tested up to: 6.8
 * WC requires at least: 7.0.0
 * WC tested up to: 9.8
 * Requires Plugins: woocommerce
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( ! defined( 'VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION' ) ) {
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION', '1.2.10' );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_NAME', 'Woocommerce Lucky Wheel Premium' );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_BASENAME', plugin_basename( __FILE__ ) );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_DIR', plugin_dir_path( __FILE__ ) );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "includes" . DIRECTORY_SEPARATOR );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_ADMIN', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "admin" . DIRECTORY_SEPARATOR );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_FRONTEND', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "frontend" . DIRECTORY_SEPARATOR );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_PLUGINS', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "plugins" . DIRECTORY_SEPARATOR );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_LANGUAGES', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "languages" . DIRECTORY_SEPARATOR );
	$plugin_url = plugins_url( '', __FILE__ );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_CSS', $plugin_url . "/css/" );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_JS', $plugin_url . "/js/" );
	define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES', $plugin_url . "/images/" );
}

if ( ! class_exists( 'Woocommerce_Lucky_Wheel' ) ) {
	class Woocommerce_Lucky_Wheel {
		public function __construct() {
			add_action( 'plugins_loaded', [$this,'check_environment'] );
			//compatible with 'High-Performance order storage (COT)
			add_action( 'before_woocommerce_init', function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
				}
			} );

		}

		public function check_environment() {
			if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
				include_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'support.php';
			}
			$environment = new \VillaTheme_Require_Environment( [
					'plugin_name'     => VI_WOOCOMMERCE_LUCKY_WHEEL_NAME,
					'php_version'     => '7.0',
					'wp_version'      => '5.0',
					'require_plugins' => [
						[
							'slug' => 'woocommerce',
							'name' => 'WooCommerce',
							'defined_version' => 'WC_VERSION',
							'version' => '7.0',
						],
					]
				]
			);
			if ( $environment->has_error() ) {
				return;
			}
			$this->includes();
			add_action( 'init', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_'.VI_WOOCOMMERCE_LUCKY_WHEEL_BASENAME, array( $this, 'settings_link' ) );
			add_filter( 'manage_wlwl_email_posts_columns', array( $this, 'add_column' ), 10, 1 );
			add_action( 'manage_wlwl_email_posts_custom_column', array( $this, 'add_column_data' ), 10, 2 );
			add_action( 'init', array( $this, 'create_custom_post_type' ) );
			add_action( 'add_meta_boxes', array( $this, 'wlwl_email_settings' ) );
			add_action( 'save_post', array( $this, 'wlwl_email_save_meta' ) );

		}
		public function init() {
			$this->load_plugin_textdomain();
			if ( class_exists( 'VillaTheme_Support_Pro' ) ) {
				new VillaTheme_Support_Pro(
					array(
						'support'   => 'https://villatheme.com/supports/forum/plugins/woocommerce-lucky-wheel/',
						'docs'      => 'https://docs.villatheme.com/?item=woocommerce-lucky-wheel',
						'review'    => 'https://codecanyon.net/downloads',
						'css'       => VI_WOOCOMMERCE_LUCKY_WHEEL_CSS,
						'image'     => VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES,
						'slug'      => 'woocommerce-lucky-wheel',
						'menu_slug' => 'woocommerce-lucky-wheel',
						'version'   => VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION,
					)
				);
			}
		}
		protected function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'woocommerce-lucky-wheel' );
			load_textdomain( 'woocommerce-lucky-wheel', VI_WOOCOMMERCE_LUCKY_WHEEL_LANGUAGES . "woocommerce-lucky-wheel-$locale.mo" );
			load_plugin_textdomain( 'woocommerce-lucky-wheel', false, VI_WOOCOMMERCE_LUCKY_WHEEL_LANGUAGES );
		}
		protected function includes() {
			$files = array(
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'data.php',
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'functions.php',
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'support.php',
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'check_update.php',
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'update.php',
				VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . 'elementor/elementor.php',
			);
			foreach ( $files as $file ) {
				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
			vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "class" . DIRECTORY_SEPARATOR, 'just_require');
			vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_ADMIN, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_' );
			vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_FRONTEND, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_' );
			vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_PLUGINS, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_' );
		}
		public function settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=woocommerce-lucky-wheel" title="' . esc_html__( 'Settings', 'woocommerce-lucky-wheel' ) . '">' . esc_html__( 'Settings', 'woocommerce-lucky-wheel' ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}
        public function wlwl_email_save_meta($post_id){
            global $wlwl_email_settings_saved;
	        if ( $wlwl_email_settings_saved || ! isset( $_POST['_wlwl_email_settings_nonce'] ) || ! wp_verify_nonce( wc_clean($_POST['_wlwl_email_settings_nonce']), 'wlwl_email_settings_action_nonce' ) ) {
		        return $post_id;
	        }
	        if (  ! isset( $_POST['_wlwl_email_settings_nonce'] ) || ! wp_verify_nonce( wc_clean($_POST['_wlwl_email_settings_nonce']), 'wlwl_email_settings_action_nonce' ) ) {
		        return $post_id;
	        }
	        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) {
		        return $post_id;
	        }
	        if ( ! current_user_can( 'manage_options' ) ) {
		        return $post_id;
	        }
	        $wlwl_email_settings_saved = 1;
	        $mobile = isset($_POST['wlwl_email_mobile']) ? wc_clean($_POST['wlwl_email_mobile']):'';
	        $name = isset($_POST['wlwl_email_name']) ? wc_clean($_POST['wlwl_email_name']):'';
	        wp_update_post(['ID'=> $post_id, 'post_content'=> $name]);
	        update_post_meta( $post_id, 'wlwl_email_mobile', $mobile );
        }
		public function wlwl_email_settings(){
			add_meta_box( 'wlwl_email_settings',
				__( 'Wheel email settings', 'woocommerce-lucky-wheel' ),
				array( $this, 'render_wlwl_email_settings' ),
				'wlwl_email', 'normal' );
		}
		public function render_wlwl_email_settings(){
			global $post;
			$mobile                  = get_post_meta( $post->ID, 'wlwl_email_mobile', true );
			$name                  = $post->post_content;
			wp_nonce_field( 'wlwl_email_settings_action_nonce', '_wlwl_email_settings_nonce' ,false);
			?>
			<table class="form-table wfspb-wafs-settings-wrap">
				<tr>
					<th><?php esc_html_e( 'Customer name', 'woocommerce-lucky-wheel' ) ?></th>
					<td>
						<input style="width:100%" type="text" name="wlwl_email_name" value="<?php echo esc_attr($name)?>">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Phone number', 'woocommerce-lucky-wheel' ) ?></th>
					<td>
						<input style="width:100%" type="text" name="wlwl_email_mobile" value="<?php echo esc_attr($mobile)?>">
					</td>
				</tr>
			</table>
			<?php
		}

		public function create_custom_post_type() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( post_type_exists( 'wlwl_email' ) ) {
				return;
			}
			$args = array(
				'labels'              => array(
					'name'               => esc_html_x( 'Lucky Wheel Email', 'woocommerce-lucky-wheel' ),
					'singular_name'      => esc_html_x( 'Email', 'woocommerce-lucky-wheel' ),
					'menu_name'          => esc_html_x( 'Emails', 'Admin menu', 'woocommerce-lucky-wheel' ),
					'name_admin_bar'     => esc_html_x( 'Emails', 'Add new on Admin bar', 'woocommerce-lucky-wheel' ),
					'view_item'          => esc_html__( 'View Email', 'woocommerce-lucky-wheel' ),
					'all_items'          => esc_html__( 'Email Subscribe', 'woocommerce-lucky-wheel' ),
					'search_items'       => esc_html__( 'Search Email', 'woocommerce-lucky-wheel' ),
					'parent_item_colon'  => esc_html__( 'Parent Email:', 'woocommerce-lucky-wheel' ),
					'not_found'          => esc_html__( 'No Email found.', 'woocommerce-lucky-wheel' ),
					'not_found_in_trash' => esc_html__( 'No Email found in Trash.', 'woocommerce-lucky-wheel' )
				),
				'description'         => esc_html__( 'Woocommerce lucky wheel emails.', 'woocommerce-lucky-wheel' ),
				'public'              => false,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_menu'        => false,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
			);
			register_post_type( 'wlwl_email', $args );
		}

		public function add_column( $columns ) {
			$columns['customer_name'] = esc_html__( 'Customer name', 'woocommerce-lucky-wheel' );
			$columns['mobile']        = esc_html__( 'Mobile', 'woocommerce-lucky-wheel' );
			$columns['spins']         = esc_html__( 'Number of spins', 'woocommerce-lucky-wheel' );
			$columns['last_spin']     = esc_html__( 'Last spin', 'woocommerce-lucky-wheel' );
			$columns['label']         = esc_html__( 'Labels', 'woocommerce-lucky-wheel' );
			$columns['coupon']        = esc_html__( 'Coupons', 'woocommerce-lucky-wheel' );

			return $columns;
		}

		public function add_column_data( $column, $post_id ) {
			switch ( $column ) {
				case 'customer_name':
					if ( get_post( $post_id )->post_content ) {
						echo wp_kses_post( get_post( $post_id )->post_content );
					}
					break;
				case 'mobile':
					if ( get_post_meta( $post_id, 'wlwl_email_mobile', true ) ) {
						echo esc_html( get_post_meta( $post_id, 'wlwl_email_mobile', true ) );
					}
					break;
				case 'spins':
					if ( get_post_meta( $post_id, 'wlwl_spin_times', true ) ) {
						echo esc_html( get_post_meta( $post_id, 'wlwl_spin_times', true )['spin_num'] );
					}
					break;
				case 'last_spin':
					if ( get_post_meta( $post_id, 'wlwl_spin_times', true ) ) {
						echo esc_html( date( 'Y-m-d h:i:s', get_post_meta( $post_id, 'wlwl_spin_times', true )['last_spin'] ) );// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					}
					break;

				case 'label':
					if ( get_post_meta( $post_id, 'wlwl_email_labels', true ) ) {
						$label = get_post_meta( $post_id, 'wlwl_email_labels', true );
						if ( sizeof( $label ) > 1 ) {
							for ( $i = sizeof( $label ) - 1; $i >= 0; $i -- ) {
								echo '<p>' . esc_html( $label[ $i ] ) . '</p>';
							}
						} else {
							echo esc_html( $label[0] );
						}
					}
					break;
				case 'coupon':
					if ( get_post_meta( $post_id, 'wlwl_email_coupons', true ) ) {
						$coupon = get_post_meta( $post_id, 'wlwl_email_coupons', true );
						if ( sizeof( $coupon ) > 1 ) {
							for ( $i = sizeof( $coupon ) - 1; $i >= 0; $i -- ) {
								echo '<p>' . esc_html( $coupon[ $i ] ) . '</p>';
							}
						} else {
							echo esc_html( $coupon[0] );
						}
					}
					break;
			}
		}


	}
}

new Woocommerce_Lucky_Wheel();
