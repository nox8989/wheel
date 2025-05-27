<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Wheel_Prize {
	protected $settings;
	protected static $post_type;
	protected $language;
	protected $languages;
	protected $default_language;
	protected $default_data;
	protected $languages_data;

	public function __construct() {
		$this->settings         = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->languages        = array();
		$this->languages_data   = array();
		$this->default_data     = array(
			'coupon_amount'      => 5,
			'email_restriction'  => 1,
			'expiry_date'        => 30,
			'individual_use'     => 1,
			'exclude_sale_items' => 1,
			'limit_per_coupon'   => 1,
			'limit_to_x_items'   => 1,
			'limit_per_user'     => 1,
		);
		$this->default_language = '';
		self::$post_type        = 'wlwl_wheel_prize';
		add_action( 'init', array( $this, 'create_custom_post_type' ) );
		add_action( 'admin_init', array( $this, 'duplicate_post' ) );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'page_row_actions', array( $this, 'page_row_actions' ), 10, 2 );
	}

	/**
	 * @param $post_id
	 *
	 * @return array|bool|WP_Post|null
	 */
	public static function get( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && $post->post_type === self::$post_type && $post->post_status === 'publish' ) {
			return $post;
		}

		return false;
	}

	public function admin_head() {
		global $parent_file, $post_type;
		if ( $post_type === self::$post_type ) {
			$parent_file = 'woocommerce-lucky-wheel';
		}
	}

	public function duplicate_post() {
		global $pagenow;
		$post_type = isset( $_GET['post_type'] ) ? wc_clean( $_GET['post_type'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( $pagenow === 'edit.php' ) && $post_type === self::$post_type ) {
			$duplicate_from = isset( $_GET['duplicate_prize'] ) ? wc_clean( $_GET['duplicate_prize'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $duplicate_from ) {
				$nonce = isset( $_GET['duplicate_prize_nonce'] ) ? wc_clean( $_GET['duplicate_prize_nonce'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( wp_verify_nonce( $nonce, 'wlwl_duplicate_prize_nonce_action' ) ) {
					$post = get_post( $duplicate_from );
					if ( $post && $post->post_type === self::$post_type ) {
						$new_post = wp_insert_post( array(
							'post_type'   => self::$post_type,
							'post_status' => 'draft',
							'post_title'  => $post->post_title ? "Copy of {$post->post_title}" : "Copy of #{$duplicate_from}",
						) );
						if ( ! is_wp_error( $new_post ) ) {
							$get_settings = array_merge( $this->get_settings(), $this->get_multilingual_settings() );
							foreach ( $get_settings as $key ) {
								if ( metadata_exists( 'post', $duplicate_from, $key ) ) {
									update_post_meta( $new_post, $key, get_post_meta( $duplicate_from, $key, true ) );
								}
							}
							wp_safe_redirect( add_query_arg( array(
								'action' => 'edit',
								'post'   => $new_post
							), admin_url( 'post.php' ) ) );
							exit();
						}
					}
				}
			}
		}
	}

	public function page_row_actions( $actions, $post ) {
		if ( current_user_can( 'edit_posts' ) && $post && $post->post_type === self::$post_type && $post->post_status !== 'trash' ) {
			$actions['duplicate'] = '<a href="' . wp_nonce_url( "edit.php?post_type=" . self::$post_type . "&duplicate_prize={$post->ID}", 'wlwl_duplicate_prize_nonce_action', 'duplicate_prize_nonce' ) . '" title="' . esc_attr__( 'Duplicate this item', 'woocommerce-lucky-wheel' ) . '" rel="permalink">' . esc_html__( 'Duplicate', 'woocommerce-lucky-wheel' ) . '</a>';
		}

		return $actions;
	}

	public static function get_coupons() {
		return get_posts( array(
			'numberposts' => - 1,
			'post_type'   => self::$post_type,
			'orderby'     => 'id',
			'order'       => 'ASC',
		) );
	}

	public function admin_enqueue_scripts() {
		global $post_type, $pagenow;
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && $post_type === self::$post_type ) {
			if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
				global $sitepress;
				$default_lang           = $sitepress->get_default_language();
				$this->default_language = $default_lang;
				$languages              = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );
				$this->languages_data   = $languages;
				if ( !empty( $languages ) ) {
					foreach ( $languages as $key => $language ) {
						if ( $key != $default_lang ) {
							$this->languages[] = $key;
						}
					}
				}
			} elseif ( class_exists( 'Polylang' ) ) {
				/*Polylang*/
				$languages    = pll_languages_list();
				$default_lang = pll_default_language( 'slug' );
				foreach ( $languages as $language ) {
					if ( $language == $default_lang ) {
						continue;
					}
					$this->languages[] = $language;
				}
			}
			wp_enqueue_script( 'woocommerce-lucky-wheel-select2-js', VI_WOOCOMMERCE_LUCKY_WHEEL_JS . 'select2.min.js', array( 'jquery' ), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION,true );
			wp_enqueue_style( 'woocommerce-lucky-wheel-select2-css', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'select2.min.css' , array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
			wp_enqueue_script( 'woocommerce-lucky-wheel-admin-wheel-prize', VI_WOOCOMMERCE_LUCKY_WHEEL_JS . 'wheel-prize.js', array( 'jquery' ), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION,true );
			wp_enqueue_style( 'woocommerce-lucky-wheel-admin-wheel-prize', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'wheel-prize.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
		}
	}

	public function add_meta_boxes() {
		add_meta_box(
			'wlwl-wheel-coupon-settings',
			esc_html__( 'Coupon settings', 'woocommerce-lucky-wheel' ),
			array( $this, 'add_coupon_settings' ),
			self::$post_type,
			'normal',
			'high'
		);
		add_meta_box(
			'wlwl-wheel-winning-message',
			esc_html__( 'Custom winning message', 'woocommerce-lucky-wheel' ),
			array( $this, 'winning_message' ),
			self::$post_type,
			'normal',
			'low'
		);
		add_meta_box(
			'wlwl-wheel-button-apply-coupon-settings',
			esc_html__( 'Button Apply coupon redirect', 'woocommerce-lucky-wheel' ),
			array( $this, 'button_apply_coupon_settings' ),
			self::$post_type,
			'side',
			'low'
		);
	}

	public function winning_message( $post ) {
		$win_option             = array( 'editor_height' => 200, 'media_buttons' => true );
		$frontend_message       = $this->settings->get_params( 'result', 'notification' );
		$result_win             = metadata_exists( 'post', $post->ID, 'result_win' ) ? get_post_meta( $post->ID, 'result_win', true ) : ($frontend_message['win'] ??'');
		$custom_winning_message = get_post_meta( $post->ID, 'custom_winning_message', true );
		$class                  = array( 'wlwl-custom-winning-message-container' );
		if ( ! $custom_winning_message ) {
			$class[] = 'wlwl-hidden';
		}
		?>
        <p>
            <input type="checkbox"
                   class="checkbox" <?php checked( get_post_meta( $post->ID, 'custom_winning_message', true ), '1' ) ?>
                   name="wlwl_custom_winning_message" id="wlwl_custom_winning_message" value="1">
            <label for="wlwl_custom_winning_message"><?php esc_html_e( 'Use custom winning message', 'woocommerce-lucky-wheel' ) ?></label>
        </p>
        <div class="<?php echo esc_attr( implode( ' ', $class ) ) ?>">
			<?php
			$this->print_default_country_flag();
			wp_editor( stripslashes( $result_win ), 'wlwl_result_win', $win_option );
			if ( !empty( $this->languages ) ) {
				foreach ( $this->languages as $key => $value ) {
					?>
                    <p>
                        <label for="<?php echo 'result_win_' . esc_attr( $value ); ?>"><?php
							if ( isset( $this->languages_data[ $value ]['country_flag_url'] ) && $this->languages_data[ $value ]['country_flag_url'] ) {
								?>
                                <img src="<?php echo esc_url( $this->languages_data[ $value ]['country_flag_url'] ); ?>">
								<?php
							}
							echo wp_kses_post( $value );
							if ( isset( $this->languages_data[ $value ]['translated_name'] ) ) {
								echo '(' . esc_attr( $this->languages_data[ $value ]['translated_name'] ) . ')';
							}
							?>:</label>
                    </p>
					<?php
					wp_editor( stripslashes( metadata_exists( 'post', $post->ID, "result_win_{$value}" ) ? get_post_meta( $post->ID, "result_win_{$value}", true ) : ($this->settings->get_params( 'result', 'notification', $value )['win'] ??'')), "wlwl_result_win_{$value}", $win_option );
				}
			}
			?>
            <ul>
                <li>{coupon_label}
                    - <?php esc_html_e( 'Label of coupon that customers win', 'woocommerce-lucky-wheel' ) ?></li>
                <li>{checkout}
                    - <?php esc_html_e( '"Checkout" with link to checkout page', 'woocommerce-lucky-wheel' ) ?></li>
                <li>{customer_name}
                    - <?php esc_html_e( 'Customers\'name if they enter', 'woocommerce-lucky-wheel' ) ?></li>
                <li>{customer_email}
                    - <?php esc_html_e( 'Email that customers enter to spin', 'woocommerce-lucky-wheel' ) ?></li>
                <li>{coupon_code}
                    - <?php esc_html_e( 'Coupon code/custom value will be sent to customer.', 'woocommerce-lucky-wheel' ) ?></li>
            </ul>
        </div>
		<?php
	}

	public function button_apply_coupon_settings( $post ) {
		$use_custom_redirect = get_post_meta( $post->ID, 'custom_button_apply_coupon_redirect', true );
		$class               = array( 'wlwl-custom-button-apply-coupon-redirect-container' );
		if ( ! $use_custom_redirect ) {
			$class[] = 'wlwl-hidden';
		}
		?>
        <p>
            <input type="checkbox"
                   class="checkbox" <?php checked( $use_custom_redirect, '1' ) ?>
                   name="wlwl_custom_button_apply_coupon_redirect" id="wlwl_custom_button_apply_coupon_redirect"
                   value="1">
            <label for="wlwl_custom_button_apply_coupon_redirect"><?php esc_html_e( 'Use custom URL', 'woocommerce-lucky-wheel' ) ?></label>
        </p>
        <div class="<?php echo esc_attr( implode( ' ', $class ) ) ?>">
			<?php
			$this->print_default_country_flag();
			?>
            <input id="wlwl_button_apply_coupon_redirect" type="text" name="wlwl_button_apply_coupon_redirect"
                   value="<?php echo esc_attr( metadata_exists( 'post', $post->ID, 'button_apply_coupon_redirect' ) ? get_post_meta( $post->ID, 'button_apply_coupon_redirect', true ) : $this->settings->get_params( 'button_apply_coupon_redirect' ) ) ?>">
			<?php
			if ( !empty( $this->languages ) ) {
				foreach ( $this->languages as $key => $value ) {
					?>
                    <p>
                        <label for="<?php echo 'wlwl_button_apply_coupon_redirect_' . esc_attr( $value ); ?>"><?php
							if ( isset( $this->languages_data[ $value ]['country_flag_url'] ) && $this->languages_data[ $value ]['country_flag_url'] ) {
								?>
                                <img src="<?php echo esc_url( $this->languages_data[ $value ]['country_flag_url'] ); ?>">
								<?php
							}
							echo esc_html( $value );
							if ( isset( $this->languages_data[ $value ]['translated_name'] ) ) {
								echo '(' . esc_html( $this->languages_data[ $value ]['translated_name'] ) . ')';
							}
							?>:</label>
                    </p>
                    <input id="<?php echo esc_attr( "wlwl_button_apply_coupon_redirect_{$value}" ) ?>" type="text"
                           name="<?php echo esc_attr( "wlwl_button_apply_coupon_redirect_{$value}" ) ?>"
                           value="<?php echo esc_attr( metadata_exists( 'post', $post->ID, "button_apply_coupon_redirect_{$value}" ) ? esc_attr( get_post_meta( $post->ID, "button_apply_coupon_redirect_{$value}", true ) ) : esc_attr( $this->settings->get_params( 'button_apply_coupon_redirect', '', $value ) ) ) ?>">
					<?php
				}
			}
			?>
            <ul>
                <li>{checkout_page}
                    - <?php esc_html_e( 'Url of checkout page', 'woocommerce-lucky-wheel' ) ?></li>
                <li>{cart_page}
                    - <?php esc_html_e( 'Url of cart page', 'woocommerce-lucky-wheel' ) ?></li>
            </ul>
        </div>
		<?php
	}

	/**
	 * @param $post_id
	 * @param $post WP_Post
	 */
	public function save_post( $post_id, $post ) {
		$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $post && $post->post_type === self::$post_type && $action === 'editpost' ) {
			$get_settings = array_merge( $this->get_settings(), $this->get_multilingual_settings() );
			foreach ( $get_settings as $key ) {
				$name = "wlwl_{$key}";
				if ( isset( $_POST[ $name ] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( $name === 'wlwl_result_win' ) {
						update_post_meta( $post_id, $key, wp_kses_post( $_POST[ $name ] ) );// phpcs:ignore WordPress.Security.NonceVerification.Missing
					} else {
						update_post_meta( $post_id, $key, wc_clean( $_POST[ $name ] ) );// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
				} else {
					if ( is_array( $_POST[ $name ] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
						update_post_meta( $post_id, $key, wc_clean( $_POST[ $name ] ) );// phpcs:ignore WordPress.Security.NonceVerification.Missing
					} else {
						update_post_meta( $post_id, $key, wc_clean( $_POST[ $name ] ) );// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
				}

//				elseif ( $value = get_post_meta( $post_id, $key, true ) ) {
//					if ( is_array( $value ) ) {
//						update_post_meta( $post_id, $key, array() );
//
//					} else {
//						update_post_meta( $post_id, $key, 0 );
//
//
//					}
//				}

			}
		}
	}

	public function get_settings() {
		return array(
			'coupon_type',
			'coupon_amount',
			'coupon_code_prefix',
			'email_restriction',
			'allow_free_shipping',
			'expiry_date',
			'min_spend',
			'max_spend',
			'individual_use',
			'exclude_sale_items',
			'product_ids',
			'exclude_product_ids',
			'product_categories',
			'exclude_product_categories',
			'limit_per_coupon',
			'limit_to_x_items',
			'limit_per_user',
			'custom_winning_message',
			'custom_button_apply_coupon_redirect',
		);
	}

	public function get_multilingual_settings( $multi = true ) {
		$options = array(
			'button_apply_coupon_redirect',
			'result_win',
		);
		$return  = $options;
		if ( $multi ) {
			if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
				global $sitepress;
				$default_lang           = $sitepress->get_default_language();
				$this->default_language = $default_lang;
				$languages              = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );
				$this->languages_data   = $languages;
				if ( !empty( $languages ) ) {
					foreach ( $languages as $key => $language ) {
						if ( $key != $default_lang ) {
							$this->languages[] = $key;
						}
					}
				}
			} elseif ( class_exists( 'Polylang' ) ) {
				/*Polylang*/
				$languages    = pll_languages_list();
				$default_lang = pll_default_language( 'slug' );
				foreach ( $languages as $language ) {
					if ( $language == $default_lang ) {
						continue;
					}
					$this->languages[] = $language;
				}
			}
			foreach ( $this->languages as $key => $value ) {
				foreach ( $options as $option ) {
					$return[] = "{$option}_{$value}";
				}
			}
		}

		return $return;
	}

	public function add_coupon_settings( $post ) {
		$coupon_type = $this->get_post_meta( $post->ID, 'coupon_type', true );
		?>
        <table class="form-table">
            <tbody>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_coupon_code_prefix"><?php esc_html_e( 'Coupon code prefix', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input id="wlwl_coupon_code_prefix" type="text" name="wlwl_coupon_code_prefix"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'coupon_code_prefix', true ) ) ?>">
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_coupon_amount"><?php esc_html_e( 'Coupon amount', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="number" min="0" name="wlwl_coupon_amount" id="wlwl_coupon_amount"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'coupon_amount', true ) ) ?>">
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_coupon_type"><?php esc_html_e( 'Coupon type', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select name="wlwl_coupon_type" id="wlwl_coupon_type" class="vi-ui fluid dropdown">
                        <option value="percent" <?php selected( $coupon_type, 'percent' ); ?>><?php esc_html_e( 'Percentage discount', 'woocommerce-lucky-wheel' ) ?></option>
                        <option value="fixed_product" <?php selected( $coupon_type, 'fixed_product' ); ?>><?php esc_html_e( 'Fixed product discount', 'woocommerce-lucky-wheel' ) ?></option>
                        <option value="fixed_cart" <?php selected( $coupon_type, 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart discount', 'woocommerce-lucky-wheel' ) ?></option>
                    </select>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_email_restriction"><?php esc_html_e( 'Email restriction', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox"
                               class="checkbox" <?php checked( $this->get_post_meta( $post->ID, 'email_restriction', true ), '1' ) ?>
                               name="wlwl_email_restriction" id="wlwl_email_restriction" value="1">
                        <label for="wlwl_email_restriction"><?php esc_html_e( 'Add received email to coupon\'s allowed emails list', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_free_shipping"><?php esc_html_e( 'Allow free shipping', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox"
                               class="checkbox" <?php checked( $this->get_post_meta( $post->ID, 'allow_free_shipping', true ), '1' ) ?>
                               name="wlwl_allow_free_shipping" id="wlwl_allow_free_shipping" value="1">
                        <label for="wlwl_allow_free_shipping"><?php esc_html_e( 'Check this box if the coupon grants free shipping. A ', 'woocommerce-lucky-wheel' ) ?>
                            <a href="https://docs.woocommerce.com/document/free-shipping/"
                               target="_blank"><?php esc_html_e( 'free shipping method', 'woocommerce-lucky-wheel' ); ?></a><?php esc_html_e( ' must be enabled in your shipping zone and be set to require "a valid free shipping coupon" (see the "Free Shipping Requires" setting).', 'woocommerce-lucky-wheel' ); ?>
                        </label>
                    </div>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_expiry_date"><?php esc_html_e( 'Time to live', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="number" min="0" name="wlwl_expiry_date" id="wlwl_expiry_date"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'expiry_date', true ) ) ?>">
                    <p><?php esc_html_e( 'Coupon will expire after x day(s) since it\'s generated and sent', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_min_spend"><?php esc_html_e( 'Minimum spend', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" class="short wc_input_price" name="wlwl_min_spend"
                           id="wlwl_min_spend"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'min_spend', true ) ); ?>"
                           placeholder="<?php esc_html_e( 'No minimum', 'woocommerce-lucky-wheel' ) ?>">
                    <p><?php esc_html_e( 'The minimum spend to use the coupon.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_max_spend"><?php esc_html_e( 'Maximum spend', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" class="short wc_input_price" name="wlwl_max_spend"
                           id="wlwl_max_spend"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'max_spend', true ) ); ?>"
                           placeholder="<?php esc_html_e( 'No maximum', 'woocommerce-lucky-wheel' ) ?>">
                    <p><?php esc_html_e( 'The maximum spend to use the coupon.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_individual_use"><?php esc_html_e( 'Individual use only', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" <?php checked( $this->get_post_meta( $post->ID, 'individual_use', true ), '1' ) ?>
                               class="checkbox" name="wlwl_individual_use" id="wlwl_individual_use"
                               value="1"><label
                                for="wlwl_individual_use"><?php esc_html_e( 'Check this box if the coupon cannot be used in conjunction with other coupons.', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_exclude_sale_items"><?php esc_html_e( 'Exclude sale items', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" <?php checked( $this->get_post_meta( $post->ID, 'exclude_sale_items', true ), '1' ) ?>
                               class="checkbox" name="wlwl_exclude_sale_items"
                               id="wlwl_exclude_sale_items"
                               value="1"><label
                                for="wlwl_exclude_sale_items"><?php esc_html_e( 'Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_product_ids"><?php esc_html_e( 'Include Products', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select id="wlwl_product_ids" name="wlwl_product_ids[]" multiple="multiple"
                            class="product-search"
                            data-placeholder="<?php esc_html_e( 'Please Fill In Your Product Title', 'woocommerce-lucky-wheel' ) ?>">
						<?php
						$product_ids = $this->get_post_meta( $post->ID, 'product_ids', true );
						if ( is_array( $product_ids ) && !empty( $product_ids ) ) {
							foreach ( $product_ids as $ps ) {
								$product = wc_get_product( $ps );
								if ( $product ) {
									?>
                                    <option selected
                                            value="<?php echo esc_attr( $ps ) ?>"><?php echo esc_html( $product->get_title() ) ?></option>
									<?php
								}
							}
						}
						?>
                    </select>
                    <p><?php esc_html_e( 'Products that the coupon will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_exclude_product_ids"><?php esc_html_e( 'Exclude Products', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select id="wlwl_exclude_product_ids" name="wlwl_exclude_product_ids[]"
                            multiple="multiple"
                            class="product-search"
                            data-placeholder="<?php esc_html_e( 'Please Fill In Your Product Title', 'woocommerce-lucky-wheel' ) ?>">
						<?php
						$exclude_product_ids = $this->get_post_meta( $post->ID, 'exclude_product_ids', true );
						if ( is_array( $exclude_product_ids ) && !empty( $exclude_product_ids ) ) {
							foreach ( $exclude_product_ids as $ps ) {
								$product = wc_get_product( $ps );
								if ( $product ) {
									?>
                                    <option selected
                                            value="<?php echo esc_attr( $ps ) ?>"><?php echo esc_html( $product->get_title() ) ?></option>
									<?php
								}
							}
						}
						?>
                    </select>
                    <p><?php esc_html_e( 'Products that the coupon will not be applied to, or that cannot be in the cart in order for the "Fixed cart discount" to be applied.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_product_categories"><?php esc_html_e( 'Include categories', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select id="wlwl_product_categories" name="wlwl_product_categories[]"
                            multiple="multiple"
                            class="category-search"
                            data-placeholder="<?php esc_html_e( 'Please enter category name', 'woocommerce-lucky-wheel' ) ?>">
						<?php
						$product_categories = $this->get_post_meta( $post->ID, 'product_categories', true );
						if ( is_array( $product_categories ) && !empty( $product_categories ) ) {
							foreach ( $product_categories as $category_id ) {
								$category = get_term( $category_id );
								if ( $category ) {
									?>
                                    <option value="<?php echo esc_attr( $category_id ) ?>"
                                            selected><?php echo esc_html( $category->name ); ?></option>
									<?php
								}
							}
						}
						?>
                    </select>
                    <p><?php esc_html_e( 'Product categories that the coupon will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_exclude_product_categories"><?php esc_html_e( 'Exclude categories', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select id="wlwl_exclude_product_categories" name="wlwl_exclude_product_categories[]"
                            multiple="multiple"
                            class="category-search"
                            data-placeholder="<?php esc_html_e( 'Please enter category name', 'woocommerce-lucky-wheel' ) ?>">
						<?php
						$exclude_product_categories = $this->get_post_meta( $post->ID, 'exclude_product_categories', true );
						if ( is_array( $exclude_product_categories ) && !empty( $exclude_product_categories ) ) {
							foreach ( $exclude_product_categories as $category_id ) {
								$category = get_term( $category_id );
								if ( $category ) {
									?>
                                    <option value="<?php echo esc_attr( $category_id ) ?>"
                                            selected><?php echo esc_html( $category->name ); ?></option>
									<?php
								}
							}
						}
						?>
                    </select>
                    <p><?php esc_html_e( 'Product categories that the coupon will not be applied to, or that cannot be in the cart in order for the "Fixed cart discount" to be applied.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_limit_per_coupon"><?php esc_html_e( 'Usage limit per coupon', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="number" class="short" name="wlwl_limit_per_coupon"
                           id="wlwl_limit_per_coupon"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'limit_per_coupon', true ) ) ?>"
                           placeholder="Unlimited usage" step="1" min="0">
                    <p><?php esc_html_e( 'How many times this coupon can be used before it is void.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_limit_to_x_items"><?php esc_html_e( 'Limit usage to X items', 'woocommerce-lucky-wheel' ) ?></label>

                </th>
                <td>
                    <input type="number" class="short" name="wlwl_limit_to_x_items"
                           id="wlwl_limit_to_x_items"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'limit_to_x_items', true ) ) ?>"
                           placeholder="<?php esc_html_e( 'Apply To All Qualifying Items In Cart', 'woocommerce-lucky-wheel' ) ?>"
                           step="1" min="0">
                    <p><?php esc_html_e( 'The maximum number of individual items this coupon can apply to when using product discount.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_limit_per_user"><?php esc_html_e( 'Usage limit per user', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="number" class="short" name="wlwl_limit_per_user"
                           id="wlwl_limit_per_user"
                           value="<?php echo esc_attr( $this->get_post_meta( $post->ID, 'limit_per_user', true ) ) ?>"
                           placeholder="<?php esc_html_e( 'Unlimited Usage', 'woocommerce-lucky-wheel' ) ?>"
                           step="1" min="0">
                    <p><?php esc_html_e( 'How many times this coupon can be used by an individual user.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	protected function get_post_meta( $post_id, $meta_key ) {
		return metadata_exists( 'post', $post_id, $meta_key ) ? get_post_meta( $post_id, $meta_key, true ) : ( isset( $this->default_data[ $meta_key ] ) ? $this->default_data[ $meta_key ] : '' );
	}

	public function create_custom_post_type() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( post_type_exists( self::$post_type ) ) {
			return;
		}
		$args = array(
			'labels'              => array(
				'name'               => esc_html_x( 'Wheel Prizes', 'woocommerce-lucky-wheel' ),
				'singular_name'      => esc_html_x( 'Wheel Prize', 'woocommerce-lucky-wheel' ),
				'menu_name'          => esc_html_x( 'Wheel Prize', 'Admin menu', 'woocommerce-lucky-wheel' ),
				'name_admin_bar'     => esc_html_x( 'Wheel Prize', 'Add new on Admin bar', 'woocommerce-lucky-wheel' ),
				'view_item'          => esc_html__( 'View Wheel Prize', 'woocommerce-lucky-wheel' ),
				'view_items'         => esc_html__( 'View Wheel Prizes', 'woocommerce-lucky-wheel' ),
				'all_items'          => esc_html__( 'Wheel Prizes', 'woocommerce-lucky-wheel' ),
				'search_items'       => esc_html__( 'Search Wheel Prize', 'woocommerce-lucky-wheel' ),
				'parent_item_colon'  => esc_html__( 'Parent Wheel Prize:', 'woocommerce-lucky-wheel' ),
				'not_found'          => esc_html__( 'No item found.', 'woocommerce-lucky-wheel' ),
				'not_found_in_trash' => esc_html__( 'No item found in Trash.', 'woocommerce-lucky-wheel' ),
				'edit_item'          => esc_html__( 'Edit Wheel Prize', 'woocommerce-lucky-wheel' ),
				'update_item'        => esc_html__( 'Update Wheel Prize', 'woocommerce-lucky-wheel' ),
				'add_new_item'       => esc_html__( 'Add New Wheel Prize', 'woocommerce-lucky-wheel' ),
				'add_new'            => esc_html__( 'Add New', 'woocommerce-lucky-wheel' ),
				'new_item'           => esc_html__( 'New Wheel Prize', 'woocommerce-lucky-wheel' ),
			),
			'description'         => esc_html__( 'Wheel Prize', 'woocommerce-lucky-wheel' ),
			'supports'            => array( 'title' ),
			'hierarchical'        => true,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
//				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
			'query_var'           => false,
		);
		register_post_type( self::$post_type, $args );
	}

	public function print_default_country_flag() {
		if ( !empty( $this->languages ) ) {
			?>
            <p>
                <label><?php
					if ( isset( $this->languages_data[ $this->default_language ]['country_flag_url'] ) && $this->languages_data[ $this->default_language ]['country_flag_url'] ) {
						?>
                        <img src="<?php echo esc_url( $this->languages_data[ $this->default_language ]['country_flag_url'] ); ?>">
						<?php
					}
					echo esc_html( $this->default_language );
					if ( isset( $this->languages_data[ $this->default_language ]['translated_name'] ) ) {
						echo '(' . esc_html( $this->languages_data[ $this->default_language ]['translated_name'] ) . '):';
					}
					?></label>
            </p>
			<?php
		}
	}
}
