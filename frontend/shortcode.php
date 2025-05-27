<?php

/**
 * Class VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_Shortcode
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_Shortcode {
	protected $settings;
	protected $language;
	protected $prefix;
	protected $pointer_position;
	protected $font_wheel;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->language = '';
		$this->prefix   = 'wc-lucky-wheel-shortcode-';
		if ( 'on' === $this->settings->get_params( 'general', 'enable' ) ) {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ), 99 );
			add_action( 'elementor/frontend/after_enqueue_scripts', array(
				$this,
				'wp_enqueue_scripts_elementor'
			), 99 );
		}
	}


	public function set( $name ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( $this, 'set' ), $name ) );
		} else {
			return esc_attr( $this->prefix . $name );
		}
	}

	public function init() {
		add_shortcode( 'woocommerce_lucky_wheel', array( $this, 'woocommerce_lucky_wheel' ) );
	}

	public function wp_enqueue_scripts_elementor() {
		if ( ! wp_script_is( 'woocommerce-lucky-wheel-shortcode' ) ) {
			wp_enqueue_style( 'woocommerce-lucky-wheel-shortcode', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'shortcode.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
			wp_enqueue_script( 'woocommerce-lucky-wheel-shortcode', VI_WOOCOMMERCE_LUCKY_WHEEL_JS . 'shortcode.js', array( 'jquery' ), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION, true );
		}
	}

	public function wp_enqueue_scripts() {
		if ( ! wp_script_is( 'woocommerce-lucky-wheel-shortcode', 'registered' ) ) {
			$this->font_wheel = apply_filters( 'wlwl_font_text_wheel', '' );
			if ( ! empty( $this->font_wheel ) ) {
				wp_enqueue_style( 'wlwl-wheel-google-font-' . strtolower( str_replace( '+', '-', $this->font_wheel ) ), 'https://fonts.googleapis.com/css?family=' . $this->font_wheel . ':300,400,700', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION  );
			}
			wp_register_style( 'woocommerce-lucky-wheel-shortcode', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'shortcode.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
			wp_register_script( 'woocommerce-lucky-wheel-shortcode', VI_WOOCOMMERCE_LUCKY_WHEEL_JS . 'shortcode.js', array( 'jquery' ), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION, true );
			if ( $this->settings->get_params( 'wlwl_recaptcha' ) ) {
				if ( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 2 ) {
					?>
                    <script src='https://www.google.com/recaptcha/api.js?hl=<?php echo esc_attr( $this->language ? $this->language : get_locale() ) ?>&render=explicit'
                            async
                            defer></script>
					<?php
				} elseif ( $this->settings->get_params( 'wlwl_recaptcha_site_key' ) ) {
					?>
                    <script src="https://www.google.com/recaptcha/api.js?hl=<?php echo esc_attr( $this->language ? $this->language : get_locale() ) ?>&render=<?php echo esc_html( $this->settings->get_params( 'wlwl_recaptcha_site_key' ) ); ?>"></script>
					<?php
				}
			}

		}
	}

	/**
	 * @param $price
	 * @param array $args
	 *
	 * @return string
	 */
	public function wc_price( $price, $args = array() ) {
		extract(
			apply_filters(
				'wc_price_args', wp_parse_args(
					$args, array(
						'ex_tax_label'       => false,
						'currency'           => get_option( 'woocommerce_currency' ),
						'decimal_separator'  => get_option( 'woocommerce_price_decimal_sep' ),
						'thousand_separator' => get_option( 'woocommerce_price_thousand_sep' ),
						'decimals'           => get_option( 'woocommerce_price_num_decimals', 2 ),
						'price_format'       => get_woocommerce_price_format(),
					)
				)
			)
		);
		$currency_pos = get_option( 'woocommerce_currency_pos' );
		$price_format = '%1$s%2$s';

		switch ( $currency_pos ) {
			case 'left' :
				$price_format = '%1$s%2$s';
				break;
			case 'right' :
				$price_format = '%2$s%1$s';
				break;
			case 'left_space' :
				$price_format = '%1$s&nbsp;%2$s';
				break;
			case 'right_space' :
				$price_format = '%2$s&nbsp;%1$s';
				break;
		}

		$unformatted_price = $price;
		$negative          = $price < 0;
		$price             = apply_filters( 'raw_woocommerce_price', floatval( $negative ? $price * - 1 : $price ) );
		$price             = apply_filters( 'formatted_woocommerce_price', number_format( $price, $decimals, $decimal_separator, $thousand_separator ), $price, $decimals, $decimal_separator, $thousand_separator );

		if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $decimals > 0 ) {
			$price = wc_trim_zeros( $price );
		}
		if ( isset( $this->settings->get_params( 'wheel' )['currency'] ) && $this->settings->get_params( 'wheel' )['currency'] === 'code' ) {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, ( $currency ), $price );
		} else {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, wlwl_get_currency_symbol( $currency ), $price );
		}

		return $formatted_price;
	}

	public function woocommerce_lucky_wheel( $atts ) {
		global $wlwl_shortcode_id;
		if ( $wlwl_shortcode_id === null ) {
			$wlwl_shortcode_id = 1;
		} else {
			$wlwl_shortcode_id ++;
		}
		$shortcode_id     = "woocommerce-lucky-wheel-shortcode-{$wlwl_shortcode_id}";
		$shortcode_id_css = "#{$shortcode_id}.wc-lucky-wheel-shortcode-container";
		$args             = shortcode_atts(
			array(
				'bg_image'                          => $this->settings->get_params( 'wheel_wrap', 'bg_image' ),
				'bg_color'                          => $this->settings->get_params( 'wheel_wrap', 'bg_color' ),
				'text_color'                        => $this->settings->get_params( 'wheel_wrap', 'text_color' ),
				'pointer_color'                     => $this->settings->get_params( 'wheel_wrap', 'pointer_color' ),
				'spin_button_color'                 => $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ),
				'pointer_position'                  => $this->settings->get_params( 'wheel_wrap', 'pointer_position' ),
				'spin_button_bg_color'              => $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ),
				'wheel_dot_color'                   => $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ),
				'wheel_border_color'                => $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ),
				'wheel_center_color'                => $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ),
				'spinning_time'                     => $this->settings->get_params( 'wheel', 'spinning_time' ),
				'wheel_speed'                       => $this->settings->get_params( 'wheel', 'wheel_speed' ),
				'custom_field_name_enable'          => $this->settings->get_params( 'custom_field_name_enable' ),
				'custom_field_name_enable_mobile'   => $this->settings->get_params( 'custom_field_name_enable_mobile' ),
				'custom_field_name_required'        => $this->settings->get_params( 'custom_field_name_required' ),
				'custom_field_mobile_enable'        => $this->settings->get_params( 'custom_field_mobile_enable' ),
				'custom_field_mobile_enable_mobile' => $this->settings->get_params( 'custom_field_mobile_enable_mobile' ),
				'custom_field_mobile_required'      => $this->settings->get_params( 'custom_field_mobile_required' ),
				'font_size'                         => $this->settings->get_params( 'wheel', 'font_size' ,'',100),
				'wheel_size'                        => $this->settings->get_params( 'wheel', 'wheel_size' ,'',100),
				'congratulations_effect'            => $this->settings->get_params( 'wheel_wrap', 'congratulations_effect' ),
				'center_image'                      => wp_get_attachment_url( $this->settings->get_params( 'wheel_wrap', 'wheel_center_image' ) ),
				'class'                             => '',
				'is_elementor'                      => 'no',
			), $atts );
		if ( ! wp_script_is( 'woocommerce-lucky-wheel-shortcode' ) ) {
			wp_enqueue_style( 'woocommerce-lucky-wheel-shortcode' );
			wp_enqueue_script( 'woocommerce-lucky-wheel-shortcode' );
			if ( $this->settings->get_params( 'wheel_wrap', 'congratulations_effect' ) === 'firework' ) {
				if ( ! wp_style_is( 'woocommerce-lucky-wheel-frontend-style-firework' ) ) {
					wp_enqueue_style( 'woocommerce-lucky-wheel-frontend-style-firework', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'firework.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
				}
			}
		}
		$this->language = apply_filters('wlwl_get_current_language', '');
//		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
//			$default_lang     = apply_filters( 'wpml_default_language', null );
//			$current_language = apply_filters( 'wpml_current_language', null );
//
//			if ( $current_language && $current_language !== $default_lang ) {
//				$this->language = $current_language;
//			}
//		} else if ( class_exists( 'Polylang' ) ) {
//			$default_lang     = pll_default_language( 'slug' );
//			$current_language = pll_current_language( 'slug' );
//			if ( $current_language && $current_language !== $default_lang ) {
//				$this->language = $current_language;
//			}
//		}
		/*css*/
		if ( $args['is_elementor'] !== 'yes' ) {
			$css = "{$shortcode_id_css}{";
			if ( $args['bg_image'] ) {
				$bg_image_url = wc_is_valid_url( $args['bg_image'] ) ? $args['bg_image'] : wp_get_attachment_url( $args['bg_image'] );
				if ( $bg_image_url ) {
					$css .= 'background-image:url("' . $bg_image_url . '");';
				}
			}
			if ( $args['bg_color'] ) {
				$css .= 'background-color:' . $args['bg_color'] . ';';
			}

			if ( $args['text_color'] ) {
				$css .= 'color:' . $args['text_color'] . ';';
			}
			$css .= '}';

			if ( $args['pointer_color'] ) {
				$css .= "$shortcode_id_css .wc-lucky-wheel-shortcode-wheel-pointer:before{color: {$args['pointer_color']};}";
			}
			//wheel wrap design
			$css .= "$shortcode_id_css .wc-lucky-wheel-shortcode-wheel-button-wrap{";
			if ( $args['spin_button_color'] ) {
				$css .= 'color:' . $args['spin_button_color'] . ';';
			}

			if ( $args['spin_button_bg_color'] ) {
				$css .= 'background-color:' . $args['spin_button_bg_color'] . ';';
			}
			$css .= '}';
			$css .= "$shortcode_id_css .wlwl-button-apply-coupon-form .wlwl-button-apply-coupon{color:{$this->settings->get_params( 'button_apply_coupon_color' )};background-color:{$this->settings->get_params( 'button_apply_coupon_bg_color' )};font-size:{$this->settings->get_params( 'button_apply_coupon_font_size' )}px;border-radius:{$this->settings->get_params( 'button_apply_coupon_border_radius' )}px;}";
			wp_add_inline_style( 'woocommerce-lucky-wheel-shortcode', $css );
		}
		/*params*/
		$wheel          = $this->settings->get_params( 'wheel' );
        if (!isset($wheel['coupon_type']) || !is_array($wheel['coupon_type'])){
	        $wheel['coupon_type'] = [];
        }
		$coupon_count   = count( $wheel['coupon_type'] );
		$prize_quantity = $this->settings->get_params( 'wheel', 'prize_quantity' );
        if (!is_array($prize_quantity)){
	        $prize_quantity = [];
        }
		$custom_label   = $this->settings->get_params( 'wheel', 'custom_label', $this->language );
		$quantity_label = $this->settings->get_params( 'wheel', 'quantity_label', $this->language );
		if ( count( $prize_quantity ) !== $coupon_count ) {
			$prize_quantity = array_fill( 0, $coupon_count, - 1 );
		}
		$label       = array();
		$non         = 0;
		$probability = 0;
		foreach ( $wheel['coupon_type'] as $count => $v ) {
			$wheel_label      = $custom_label[ $count ];
			$quantity_label_1 = '';
			if ( $wheel['coupon_type'][ $count ] === 'non' ) {
				$non ++;
				$probability += absint( $wheel['probability'][ $count ] );
			} else {
				if ( $prize_quantity[ $count ] != 0 ) {
					$probability += absint( $wheel['probability'][ $count ] );
				}
				if ( $prize_quantity[ $count ] > 0 ) {
					$quantity_label_1 = str_replace( '{prize_quantity}', $prize_quantity[ $count ], $quantity_label );
				}
				if ( $wheel['coupon_type'][ $count ] === 'custom' ) {

				} elseif ( $wheel['coupon_type'][ $count ] === 'existing_coupon' ) {
					$code   = get_post( $wheel['existing_coupon'][ $count ] )->post_title;
					$coupon = new WC_Coupon( $code );
					if ( $coupon->get_discount_type() === 'percent' ) {
						$wheel_label = str_replace( '{coupon_amount}', $coupon->get_amount() . '%', $wheel_label );
					} else {
						$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $coupon->get_amount() ), $wheel_label );
						$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
					}
				} elseif ( in_array( $wheel['coupon_type'][ $count ], array(
					'fixed_product',
					'fixed_cart',
					'percent'
				) ) ) {
					if ( $wheel['coupon_type'][ $count ] === 'percent' ) {
						$wheel_label = str_replace( '{coupon_amount}', $wheel['coupon_amount'][ $count ] . '%', $wheel_label );
					} else {
						$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $wheel['coupon_amount'][ $count ] ), $wheel_label );
						$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
					}
				} else {
					$dynamic_coupon = get_post( $wheel['coupon_type'][ $count ] );
					if ( $dynamic_coupon && $dynamic_coupon->post_status === 'publish' ) {
						$wheel_label = str_replace( '{wheel_prize_title}', $dynamic_coupon->post_title, $wheel_label );
						if ( get_post_meta( $wheel['coupon_type'][ $count ], 'coupon_type', true ) === 'percent' ) {
							$wheel_label = str_replace( '{coupon_amount}', get_post_meta( $wheel['coupon_type'][ $count ], 'coupon_amount', true ) . '%', $wheel_label );
						} else {
							$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( get_post_meta( $wheel['coupon_type'][ $count ], 'coupon_amount', true ) ), $wheel_label );
							$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
						}
					} else {
						$wheel['coupon_type'][ $count ] = 'non';
						$wheel_label                    = esc_html__( 'Not Lucky', 'woocommerce-lucky-wheel' );
						$non ++;
					}
				}
			}
			$wheel_label = str_replace( '{quantity_label}', $quantity_label_1, $wheel_label );
			$wheel_label = str_replace( array( '{coupon_amount}', '{wheel_prize_title}' ), '', $wheel_label );
			$label[]     = $wheel_label;
		}
		$wheel['label'] = $label;
		if ( $non === $coupon_count || $probability === 0 ) {
			return '';
		}
		$this->pointer_position = $args['pointer_position'];
		if ( $this->pointer_position === 'random' ) {
			$pointer_positions      = array(
				'center',
				'top',
				'right',
				'bottom',
			);
			$ran                    = wp_rand( 0, 3 );
			$this->pointer_position = $pointer_positions[ $ran ];
		}

		$args            = wp_parse_args( $args, array(
			'ajaxurl'                     => $this->settings->get_params( 'ajax_endpoint' ) === 'ajax' ? ( admin_url( 'admin-ajax.php?action=wlwl_get_email' ) ) : site_url() . '/wp-json/woocommerce_lucky_wheel/spin',
			'pointer_position'            => $this->pointer_position,
			'gdpr'                        => $this->settings->get_params( 'wheel_wrap', 'gdpr' ),
			'gdpr_warning'                => esc_html__( 'Please agree with our term and condition.', 'woocommerce-lucky-wheel' ),
			'color'                       => $this->settings->get_params( 'wheel', 'random_color' ) === 'on' ? $this->settings::get_random_color() : $wheel['bg_color'],
			'slices_text_color'           => $this->settings->get_params( 'wheel', 'slices_text_color' ),
			'label'                       => $label,
			'coupon_type'                 => $wheel['coupon_type'],
			'font_text_wheel'             => $this->font_wheel,
			'empty_email_warning'         => esc_html__( '*Please enter your email', 'woocommerce-lucky-wheel' ),
			'invalid_email_warning'       => esc_html__( '*Please enter a valid email address', 'woocommerce-lucky-wheel' ),
			'custom_field_name_message'   => esc_html__( '*Name is required!', 'woocommerce-lucky-wheel' ),
			'custom_field_mobile_message' => esc_html__( '*Phone number is required!', 'woocommerce-lucky-wheel' ),
			'wlwl_warring_recaptcha'      => esc_html__( '*Require reCAPTCHA verification', 'woocommerce-lucky-wheel' ),
			'language'                    => $this->language,
			'wlwl_recaptcha_site_key'     => $this->settings->get_params( 'wlwl_recaptcha_site_key' ),
			'wlwl_recaptcha_version'      => $this->settings->get_params( 'wlwl_recaptcha_version' ),
			'wlwl_recaptcha_secret_theme' => $this->settings->get_params( 'wlwl_recaptcha_secret_theme' ),
			'wlwl_recaptcha'              => $this->settings->get_params( 'wlwl_recaptcha' ),
		) );
		$container_class = array( 'container', 'pointer-position-' . $this->pointer_position );
		if ( $this->pointer_position !== 'center' ) {
			$container_class[] = 'margin-position';
		}
		$shortcode_class = $this->set( $container_class );
		if ( $args['class'] ) {
			$shortcode_class .= ' ' . $args['class'];
		}
		$wlwl_shortcode_recaptcha = $this->settings->get_params( 'wlwl_recaptcha' );
		ob_start();
		?>
        <div id="<?php echo esc_attr( $shortcode_id ) ?>" class="<?php echo esc_attr( $shortcode_class ) ?>"
             data-shortcode_args="<?php echo esc_attr( wp_json_encode( $args ) ) ?>">
            <div class="<?php echo esc_attr( $this->set( 'wheel-container' ) ) ?>">
                <div class="<?php echo esc_attr( $this->set( 'wheel-canvas' ) ) ?>">
                    <canvas class="<?php echo esc_attr( $this->set( 'wheel-canvas-1' ) ) ?>"
                            id="<?php echo esc_attr( $this->set( 'wheel-canvas-1' ) ) ?>">
                    </canvas>
                    <canvas class="<?php echo esc_attr( $this->set( 'wheel-canvas-2' ) ) ?>"
                            id="<?php echo esc_attr( $this->set( 'wheel-canvas-2' ) ) ?>">
                    </canvas>
                    <canvas class="<?php echo esc_attr( $this->set( 'wheel-canvas-3' ) ) ?>">
                    </canvas>
                    <div class="<?php echo esc_attr( $this->set( 'wheel-pointer-container' ) ) ?>">
                        <div class="<?php echo esc_attr( $this->set( 'wheel-pointer-before' ) ) ?>">
                        </div>
                        <div class="<?php echo esc_attr( $this->set( 'wheel-pointer-main' ) ) ?>">
                            <span class="wlwl-location <?php echo esc_attr( $this->set( array(
	                            'wheel-pointer',
	                            'wheel-pointer-' . $this->pointer_position
                            ) ) ) ?>"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="<?php echo esc_attr( $this->set( 'content-container' ) ) ?>">
                <div class="<?php echo esc_attr( $this->set( 'wheel-description' ) ) ?>">
					<?php
					echo do_shortcode( $this->settings->get_params( 'wheel_wrap', 'description', $this->language ) ); ?>
                </div>
                <div class="wlwl-congratulations-effect">
                    <div class="wlwl-congratulations-effect-before"></div>
                    <div class="wlwl-congratulations-effect-after"></div>
                </div>
                <div class="<?php echo esc_attr( $this->set( 'wheel-fields-container' ) ) ?>">
					<?php
					if ( 'on' === $args['custom_field_name_enable'] ) {
						?>
                        <div class="<?php echo esc_attr( $this->set( array(
							'wheel-field-name-wrap',
							'wheel-field-wrap'
						) ) ) ?>">
                            <input type="text"
                                   class="<?php echo esc_attr( $this->set( array(
								       'wheel-field-name',
								       'wheel-field'
							       ) ) ) ?>"
                                   placeholder="<?php esc_attr_e( 'Please enter your name', 'woocommerce-lucky-wheel' ) ?>">
                        </div>
						<?php
					}
					if ( 'on' === $args['custom_field_mobile_enable'] ) {
						$attribute_arr = apply_filters( 'wlwl_filter_attribute_phone', [
							'type' => 'tel'
						] );
						?>
                        <div class="<?php echo esc_attr( $this->set( array(
							'wheel-field-mobile-wrap',
							'wheel-field-wrap'
						) ) ) ?>">
                            <input <?php echo wc_implode_html_attributes( $attribute_arr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
                                    class="<?php echo esc_attr( $this->set( array(
										'wheel-field-mobile',
										'wheel-field'
									) ) ) ?>"
                                    placeholder="<?php esc_attr_e( 'Please enter your number', 'woocommerce-lucky-wheel' ) ?>">
                        </div>
						<?php
					}
					?>
                    <div class="<?php echo esc_attr( $this->set( array(
						'wheel-field-email-wrap',
						'wheel-field-wrap'
					) ) ) ?>">
                        <span class="<?php echo esc_attr( $this->set( array(
	                        'wheel-field-error-email',
	                        'wheel-field-error'
                        ) ) ) ?>"></span>
                        <input type="email"
                               class="<?php echo esc_attr( $this->set( array(
							       'wheel-field-email',
							       'wheel-field'
						       ) ) ) ?>"
                               value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->user_email : '' ) ?>"
                               placeholder="<?php esc_attr_e( "Please enter your email", 'woocommerce-lucky-wheel' ) ?>">
                    </div>
                    <!--captcha-->
                    <div class="wlwl_shortcode_recaptcha_wrap">
                        <div class="wlwl-shortcode-recaptcha-field"
                             style="<?php echo isset( $wlwl_shortcode_recaptcha ) ? '' : 'display:none;'; ?>">
                            <div id="wlwl-shortcode-recaptcha" class="wlwl-shortcode-recaptcha"></div>

                            <input type="hidden" value="" id="wlwl-shortcode-g-validate-response">
                        </div>
                        <div id="wlwl_shortcode_warring_recaptcha"></div>
                    </div>
                    <div class="<?php echo esc_attr( $this->set( array( 'wheel-button-wrap' ) ) ) ?>">
                        <span class="<?php echo esc_attr( $this->set( array( 'wheel-button' ) ) ) ?>"><?php echo esc_html( $this->settings->get_params( 'wheel_wrap', 'spin_button', $this->language ) ); ?></span>
                    </div>
					<?php
					if ( 'on' === $this->settings->get_params( 'wheel_wrap', 'gdpr' ) ) {
						$gdpr_message = $this->settings->get_params( 'wheel_wrap', 'gdpr_message', $this->language );
						if ( empty( $gdpr_message ) ) {
							$gdpr_message = esc_html__( "I agree with the term and condition", 'woocommerce-lucky-wheel' );
						}
						?>
                        <div class="<?php echo esc_attr( $this->set( array( 'wheel-gdpr-wrap' ) ) ) ?>">
                            <input type="checkbox">
                            <span><?php echo wp_kses_post( $gdpr_message ) ?></span>
                        </div>
						<?php
					}
					?>
                </div>
            </div>
            <div class="<?php echo esc_attr( $this->set( 'result-container' ) ) ?>">
            </div>
        </div>

		<?php
		return ob_get_clean();
	}

	public function get_random_color() {
		$colors_array = array(
			array(
				"#ffcdd2",
				"#b71c1c",
				"#e57373",
				"#e53935",
				"#ffcdd2",
				"#b71c1c",
				"#e57373",
				"#e53935",
				"#ffcdd2",
				"#b71c1c",
				"#e57373",
				"#e53935",
				"#ffcdd2",
				"#b71c1c",
				"#e57373",
				"#e53935",
				"#ffcdd2",
				"#b71c1c",
				"#e57373",
				"#e53935",
			),
			array(
				"#e1bee7",
				"#4a148c",
				"#ba68c8",
				"#8e24aa",
				"#e1bee7",
				"#4a148c",
				"#ba68c8",
				"#8e24aa",
				"#e1bee7",
				"#4a148c",
				"#ba68c8",
				"#8e24aa",
				"#e1bee7",
				"#4a148c",
				"#ba68c8",
				"#8e24aa",
				"#e1bee7",
				"#4a148c",
				"#ba68c8",
				"#8e24aa",
			),
			array(
				"#d1c4e9",
				"#311b92",
				"#9575cd",
				"#5e35b1",
				"#d1c4e9",
				"#311b92",
				"#9575cd",
				"#5e35b1",
				"#d1c4e9",
				"#311b92",
				"#9575cd",
				"#5e35b1",
				"#d1c4e9",
				"#311b92",
				"#9575cd",
				"#5e35b1",
				"#d1c4e9",
				"#311b92",
				"#9575cd",
				"#5e35b1",
			),
			array(
				"#c5cae9",
				"#1a237e",
				"#7986cb",
				"#3949ab",
				"#c5cae9",
				"#1a237e",
				"#7986cb",
				"#3949ab",
				"#c5cae9",
				"#1a237e",
				"#7986cb",
				"#3949ab",
				"#c5cae9",
				"#1a237e",
				"#7986cb",
				"#3949ab",
				"#c5cae9",
				"#1a237e",
				"#7986cb",
				"#3949ab",
			),
			array(
				"#bbdefb",
				"#64b5f6",
				"#1e88e5",
				"#0d47a1",
				"#bbdefb",
				"#64b5f6",
				"#1e88e5",
				"#0d47a1",
				"#bbdefb",
				"#64b5f6",
				"#1e88e5",
				"#0d47a1",
				"#bbdefb",
				"#64b5f6",
				"#1e88e5",
				"#0d47a1",
				"#bbdefb",
				"#64b5f6",
				"#1e88e5",
				"#0d47a1",
			),
			array(
				"#b2dfdb",
				"#004d40",
				"#4db6ac",
				"#00897b",
				"#b2dfdb",
				"#004d40",
				"#4db6ac",
				"#00897b",
				"#b2dfdb",
				"#004d40",
				"#4db6ac",
				"#00897b",
				"#b2dfdb",
				"#004d40",
				"#4db6ac",
				"#00897b",
				"#b2dfdb",
				"#004d40",
				"#4db6ac",
				"#00897b",
			),
			array(
				"#c8e6c9",
				"#1b5e20",
				"#81c784",
				"#43a047",
				"#c8e6c9",
				"#1b5e20",
				"#81c784",
				"#43a047",
				"#c8e6c9",
				"#1b5e20",
				"#81c784",
				"#43a047",
				"#c8e6c9",
				"#1b5e20",
				"#81c784",
				"#43a047",
				"#c8e6c9",
				"#1b5e20",
				"#81c784",
				"#43a047",
			),
			array(
				"#f0f4c3",
				"#827717",
				"#dce775",
				"#c0ca33",
				"#f0f4c3",
				"#827717",
				"#dce775",
				"#c0ca33",
				"#f0f4c3",
				"#827717",
				"#dce775",
				"#c0ca33",
				"#f0f4c3",
				"#827717",
				"#dce775",
				"#c0ca33",
				"#f0f4c3",
				"#827717",
				"#dce775",
				"#c0ca33",
			),
			array(
				"#fff9c4",
				"#f57f17",
				"#fff176",
				"#fdd835",
				"#fff9c4",
				"#f57f17",
				"#fff176",
				"#fdd835",
				"#fff9c4",
				"#f57f17",
				"#fff176",
				"#fdd835",
				"#fff9c4",
				"#f57f17",
				"#fff176",
				"#fdd835",
				"#fff9c4",
				"#f57f17",
				"#fff176",
				"#fdd835",
			),
			array(
				"#ffe0b2",
				"#e65100",
				"#ffb74d",
				"#fb8c00",
				"#ffe0b2",
				"#e65100",
				"#ffb74d",
				"#fb8c00",
				"#ffe0b2",
				"#e65100",
				"#ffb74d",
				"#fb8c00",
				"#ffe0b2",
				"#e65100",
				"#ffb74d",
				"#fb8c00",
				"#ffe0b2",
				"#e65100",
				"#ffb74d",
				"#fb8c00",
			),
			array(
				"#d7ccc8",
				"#3e2723",
				"#a1887f",
				"#6d4c41",
				"#d7ccc8",
				"#3e2723",
				"#a1887f",
				"#6d4c41",
				"#d7ccc8",
				"#3e2723",
				"#a1887f",
				"#6d4c41",
				"#d7ccc8",
				"#3e2723",
				"#a1887f",
				"#6d4c41",
				"#d7ccc8",
				"#3e2723",
				"#a1887f",
				"#6d4c41",
			),
			array(
				"#cfd8dc",
				"#263238",
				"#90a4ae",
				"#546e7a",
				"#cfd8dc",
				"#263238",
				"#90a4ae",
				"#546e7a",
				"#cfd8dc",
				"#263238",
				"#90a4ae",
				"#546e7a",
				"#cfd8dc",
				"#263238",
				"#90a4ae",
				"#546e7a",
				"#cfd8dc",
				"#263238",
				"#90a4ae",
				"#546e7a",
			),
		);
		$index        = wp_rand( 0, 11 );
		$colors       = $colors_array[ $index ];
		$slices       = $this->settings->get_params( 'wheel', 'bg_color' );
        if (!is_array($slices)){
            $slices = [];
        }

		return array_slice( $colors, 0, count( $slices ) );
	}
}
