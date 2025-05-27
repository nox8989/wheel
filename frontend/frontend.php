<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_Frontend
 *
 */
class VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_Frontend {
	protected $settings;
	protected $footer_text;
	protected $is_mobile;
	protected $detect;
	protected $pointer_position;
	protected $background_effect;
	protected $characters_array;
	protected $language;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->language = '';
		add_action( 'wlwl_schedule_add_recipient_to_list', array( $this, 'add_recipient_to_list' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );
		if ( $this->settings->get_params( 'ajax_endpoint' ) === 'ajax' ) {
			add_action( 'wp_ajax_wlwl_get_email', array( $this, 'get_email' ) );
			add_action( 'wp_ajax_nopriv_wlwl_get_email', array( $this, 'get_email' ) );
		} else {
			add_action( 'rest_api_init', array( $this, 'register_api' ) );
		}
	}

	/**
	 * @param $user_email
	 * @param $customer_name
	 * @param $coupon_code
	 * @param string $date_expires
	 * @param string $coupon_label
	 * @param string $language
	 * @param string $mobile
	 * @param string $email_template
	 */
	public function send_email( $user_email, $customer_name, $coupon_code, $date_expires = '', $coupon_label = '', $language = '', $mobile = '', $email_template = '' ) {
		$coupon_label = str_replace( array( '/n', '\n' ), ' ', $coupon_label );
		$coupon_label = str_replace( array(
			'{coupon_amount}',
			'{wheel_prize_title}',
			'{quantity_label}'
		), '', $coupon_label );
		$coupon_label = preg_replace( '/ +/', ' ', $coupon_label );
		$use_template =  false;
		if ( $email_template && VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_WooCommerce_Email_Template_Customizer::$is_active ) {
			$email_template_obj = get_post( $email_template );
			if ( $email_template_obj && $email_template_obj->post_type === 'viwec_template' ) {
                add_filter('viwec_disable_woocommerce_email_inline_style','__return_false');
				$use_template = true;
                $viwec_email = new VIWEC_Render_Email_Template(array(
					'template_id' => $email_template
				));
				ob_start();
				$viwec_email->get_content();
				$content = ob_get_clean();
				$subject = $viwec_email->get_subject();
				$content = str_replace( array(
					'{wlwl_customer_email}',
					'{wlwl_site_title}',
					'{wlwl_customer_mobile}',
					'{wlwl_coupon_label}',
					'{wlwl_customer_name}',
					'{wlwl_coupon_code}',
					'{wlwl_date_expires}',
				), array(
					$user_email,
					get_bloginfo( 'name' ),
					$mobile,
					$coupon_label,
					$customer_name,
					strtoupper( $coupon_code ),
					empty( $date_expires ) ? esc_html__( 'never expires', 'woocommerce-lucky-wheel' ) : date_i18n( 'F d, Y', strtotime( $date_expires ) ),
				), $content );
				$subject = str_replace( array(
					'{wlwl_customer_email}',
					'{wlwl_site_title}',
					'{wlwl_customer_mobile}',
					'{wlwl_coupon_label}',
					'{wlwl_customer_name}',
					'{wlwl_coupon_code}',
					'{wlwl_date_expires}',
				), array(
					$user_email,
					get_bloginfo( 'name' ),
					$mobile,
					$coupon_label,
					$customer_name,
					strtoupper( $coupon_code ),
					empty( $date_expires ) ? esc_html__( 'never expires', 'woocommerce-lucky-wheel' ) : date_i18n( 'F d, Y', strtotime( $date_expires ) ),
				), $subject );
			}
		}
		$mailer  = WC()->mailer();
		$email   = new WC_Email();
		if ( ! $use_template ) {
			remove_all_filters( 'wp_get_attachment_thumb_url' );
			remove_all_filters( 'wp_get_attachment_url' );
			$button_shop_now        = '<a href="' . ( $this->settings->get_params( 'button_shop_url', '', $language ) ) . '" target="_blank" style="text-decoration:none;display:inline-block;padding:10px 30px;margin:10px 0;font-size:' . esc_attr( $this->settings->get_params( 'button_shop_size' ) ) . 'px;color:' . esc_attr( $this->settings->get_params( 'button_shop_color' ) ) . ';background:' . esc_attr( $this->settings->get_params( 'button_shop_bg_color' ) ) . ';">' . $this->settings->get_params( 'button_shop_title', '', $language ) . '</a>';
			$email_temp             = $this->settings->get_params( 'result', 'email', $language );
			$content                = stripslashes( $email_temp['content'] );
			$content                = str_replace( '{customer_email}', $user_email, $content );
			$content                = str_replace( '{customer_mobile}', $mobile, $content );
			$content                = str_replace( '{coupon_label}', $coupon_label, $content );
			$content                = str_replace( '{customer_name}', $customer_name, $content );
			$content                = str_replace( '{coupon_code}', '<span style="font-size: x-large;">' . strtoupper( $coupon_code ) . '</span>', $content );
			$content                = str_replace( '{date_expires}', empty( $date_expires ) ? esc_html__( 'never expires', 'woocommerce-lucky-wheel' ) : date_i18n( 'F d, Y', strtotime( $date_expires ) ), $content );
			$content                = str_replace( '{shop_now}', $button_shop_now, $content );
			$featured_products      = wc_get_featured_product_ids();
			$featured_products_html = '';
			if ( is_array( $featured_products ) && count( $featured_products ) ) {
				$featured_products_html = '<table style="width: 100%;">';
				foreach ( $featured_products as $p ) {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $p ) : new WC_Product( $p );
					if ( $product ) {
						$featured_products_html .= '<tr><td style="text-align: center;"><a href="' . esc_url( $product->get_permalink() ) . '" target="_blank"><img style="width: 150px;" src="' . wp_get_attachment_thumb_url( $product->get_image_id() ) . '"></a></td><td><p>' . esc_html( $product->get_title() ) . '</p><p>' . wp_kses_post( $product->get_price_html() ) . '</p><a target="_blank" style="text-align: center;font-size:' . esc_attr( $this->settings->get_params( 'button_shop_size' ) ) . 'px; background-color: ' . esc_attr( $this->settings->get_params( 'button_shop_bg_color' ) ) . ';color: ' . esc_attr( $this->settings->get_params( 'button_shop_color' ) ) . ';padding: 10px;text-decoration: none;" href="' . esc_url( $product->get_permalink() ) . '" >' . esc_html( $this->settings->get_params( 'button_shop_title', '', $language ) ) . '</a></td></tr>';
					}
				}
				$featured_products_html .= '</table>';
			}
			$content            = str_replace( '{featured_products}', $featured_products_html, $content );
			$suggested_products = $this->settings->get_params( 'suggested_products' );
			if ( is_array( $suggested_products ) && count( $suggested_products ) ) {
				$content .= '<table style="width: 100%;">';
				foreach ( $suggested_products as $suggested_product ) {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $suggested_product ) : new WC_Product( $suggested_product );
					if ( ! $product || $product->get_parent_id() ) {
						continue;
					}
					$content .= '<tr><td style="text-align: center;"><a href="' . $product->get_permalink() . '" target="_blank"><img style="width: 150px;" src="' . wp_get_attachment_thumb_url( $product->get_image_id() ) . '"></a></td><td><p>' . $product->get_title() . '</p><p>' . $product->get_price_html() . '</p><a target="_blank" style="text-align: center;font-size:' . $this->settings->get_params( 'button_shop_size' ) . 'px; background-color: ' . ( $this->settings->get_params( 'button_shop_bg_color' ) ) . ';color: ' . ( $this->settings->get_params( 'button_shop_color' ) ) . ';padding: 10px;text-decoration: none;" href="' . $product->get_permalink() . '" >' . $this->settings->get_params( 'button_shop_title' ) . '</a></td></tr>';
				}
				$content .= '</table>';
			}
			$subject = stripslashes( $email_temp['subject'] ??'');
			$subject = str_replace( '{coupon_label}', $coupon_label, $subject );
			/*03-06-2024 Add check language He_IL*/
			if ( ( get_locale() !== 'he_IL' ) && function_exists( 'mb_encode_mimeheader' ) ) {
				$subject = mb_encode_mimeheader( html_entity_decode( $subject, ENT_COMPAT, 'UTF-8' ) );
			}

			$email_heading     = $email_temp['heading']??'';
			$email_heading     = str_replace( '{coupon_label}', $coupon_label, $email_heading );
			$this->footer_text = isset( $email_temp['footer_text'] ) ? $email_temp['footer_text'] : get_option( 'woocommerce_email_footer_text' );
			$this->footer_text = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::replace_placeholders( $this->footer_text );
			$content           = $email->style_inline( $mailer->wrap_message( $email_heading, $content ) );
		}

		add_filter( 'woocommerce_email_footer_text', array( $this, 'woocommerce_email_footer_text' ) );
		$headers         = "Content-Type: text/html\r\n";
        if (apply_filters('_wlwl_email_render_header_site_info',true)) {
	        $admin_email = get_bloginfo( 'admin_email' );
	        $headers     = "Content-Type: text/html\r\nReply-to: {$email->get_from_name()} <{$email->get_from_address()}>\r\n";
	        $headers     .= "Reply-to: <{$admin_email}>";
        }

		$content = ( do_shortcode( $content ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$email->send( $user_email, $subject, $content, $headers, array() );

		remove_filter( 'woocommerce_email_footer_text', array( $this, 'woocommerce_email_footer_text' ) );

		$admin_email = $this->settings->get_params( 'result', 'admin_email' );
		if ( !empty($admin_email['enable']) ) {
			$admin_email_address = !empty($admin_email['address']) ? $admin_email['address'] : $email->get_from_address();
			$admin_email_subject = str_replace( '{coupon_label}', $coupon_label, $admin_email['subject']??'' );
			$admin_email_heading = str_replace( '{coupon_label}', $coupon_label, $admin_email['heading']??'' );
			$admin_email_content = str_replace( array(
				'{customer_email}',
				'{customer_mobile}',
				'{customer_name}',
				'{coupon_label}',
				'{coupon_code}'
			), array( $user_email, $mobile, $customer_name, $coupon_label, $coupon_code ), $admin_email['content'] ??'');
			$admin_email_content = ( do_shortcode( $admin_email_content ) );
			$admin_email_content = $email->style_inline( $mailer->wrap_message( $admin_email_heading, $admin_email_content ) );
			$email->send( $admin_email_address, $admin_email_subject, $admin_email_content, $headers, array() );
		}
	}

	/**Use custom footer text for email
	 *
	 * @param $footer_text
	 *
	 * @return mixed
	 */
	public function woocommerce_email_footer_text( $footer_text ) {
		$footer_text = $this->footer_text;

		return $footer_text;
	}

	/**
	 *
	 */
	public function frontend_enqueue() {
		if ( isset( $_COOKIE['wlwl_cookie'] ) ) {
			return;
		}
		if ( $this->settings->get_params( 'general', 'enable' ) != 'on' ) {
			return;
		}
		$show = true;
		if ( $this->settings->get_params( 'notify', 'show_only_front' ) === 'on' || $this->settings->get_params( 'notify', 'show_only_blog' ) === 'on' || $this->settings->get_params( 'notify', 'show_only_shop' ) === 'on' ) {
			$show = false;
			if ( is_front_page() && $this->settings->get_params( 'notify', 'show_only_front' ) === 'on' ) {
				$show = true;
			}
			if ( is_home() && $this->settings->get_params( 'notify', 'show_only_blog' ) === 'on' ) {
				$show = true;
			}
			if ( is_shop() && $this->settings->get_params( 'notify', 'show_only_shop' ) === 'on' ) {
				$show = true;
			}
		}
		if ( ! $show ) {
			return;
		}
		$logic_value = $this->settings->get_params( 'notify', 'conditional_tags' );
		if ( $logic_value ) {
			if ( stristr( $logic_value, "return" ) === false ) {
				$logic_value = "return (" . $logic_value . ");";
			}
//			if ( ! eval( $logic_value ) ) {// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
//				return;
//			}

			try {
				$logic_show = eval( $logic_value);// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			}
			catch ( \Error $e ) {
				$logic_show = false;
			}catch ( \Exception $e ) {
				$logic_show = false;
			}
			if ( !$logic_show ) {
				return;
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
			return;
		}
		$mobile_enable =  $this->settings->get_params( 'general', 'mobile' ) === 'on';
		$this->settings::enqueue_script(
			array( 'woocommerce-lucky-wheel-frontend-javascript' ),
			array( 'woocommerce-lucky-wheel' ),
			array( 0 ),
		);
		$font = '';
		if ( $this->settings->get_params( 'wheel_wrap', 'font' ) ) {
			$font = $this->settings->get_params( 'wheel_wrap', 'font' );
			wp_enqueue_style( 'woocommerce-lucky-wheel-google-font-' . strtolower( str_replace( '+', '-', $font ) ), '//fonts.googleapis.com/css?family=' . $font . ':300,400,700', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
			$font = str_replace( '+', ' ', $font );
		}
		$font_wheel = apply_filters( 'wlwl_font_text_wheel', '' );
		if ( ! empty( $font_wheel ) ) {
			wp_enqueue_style( 'wlwl-wheel-google-font-' . strtolower( str_replace( '+', '-', $font_wheel ) ), '//fonts.googleapis.com/css?family=' . $font_wheel . ':300,400,700', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
		}
		if ( $this->settings->get_params( 'wheel_wrap', 'congratulations_effect' ) === 'firework' ) {
			$this->settings::enqueue_style(
				array( 'woocommerce-lucky-wheel-frontend-style-firework'),
				array('firework' ),
				array()
			);
		}
		$this->background_effect = $this->settings->get_params( 'wheel_wrap', 'background_effect' );
		if ( $this->background_effect === 'random' ) {
			$randoms                 = array_keys( VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_all_bg_effects() );
			$rand_index              = wp_rand( 0, count( $randoms ) - 2 );
			$this->background_effect = $randoms[ $rand_index ];
		}
		$background_effect_css='';
		switch ( $this->background_effect ) {
			case 'snowflakes':
				$background_effect_css='snowflakes';
//				wp_enqueue_style( 'woocommerce-lucky-wheel-frontend-style-snowflakes', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'snowflakes.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
				break;
			case 'snowflakes-1':
				$background_effect_css='snowflakes-1';
//				wp_enqueue_style( 'woocommerce-lucky-wheel-frontend-style-snowflakes-1', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'snowflakes-1.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
				break;
			case 'snowflakes-2-1':
			case 'snowflakes-2-2':
			case 'snowflakes-2-3':
			$background_effect_css='snowflakes-2';
//				wp_enqueue_style( 'woocommerce-lucky-wheel-frontend-style-snowflakes-2', VI_WOOCOMMERCE_LUCKY_WHEEL_CSS . 'snowflakes-2.css', array(), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION );
				break;
			default:
		}
		if ($background_effect_css){
			$this->settings::enqueue_style(
				array( 'woocommerce-lucky-wheel-frontend-style-snowflakes', ),
				array($background_effect_css ),
				array()
			);
		}
		$this->settings::enqueue_style(
			array( 'woocommerce-lucky-wheel-gift-icons','woocommerce-lucky-wheel-frontend-style' ),
			array('giftbox','woocommerce-lucky-wheel' ),
			array()
		);

		$inline_css = '.wlwl_lucky_wheel_content {';
		if ( $this->settings->get_params( 'wheel_wrap', 'bg_image' ) ) {
			$bg_image_url = $this->settings->get_params( 'wheel_wrap', 'bg_image' ) ;
            if (!wc_is_valid_url( $bg_image_url )) {
	            $bg_image_url = wp_get_attachment_url( $bg_image_url );
            }
			$inline_css   .= 'background-image:url("' . $bg_image_url . '");background-repeat: no-repeat;background-size:cover;background-position:center;';
		}
		if ( $this->settings->get_params( 'wheel_wrap', 'bg_color' ) ) {
			$inline_css .= 'background-color:' . $this->settings->get_params( 'wheel_wrap', 'bg_color' ) . ';';
		}
		if ( $this->settings->get_params( 'wheel_wrap', 'text_color' ) ) {
			$inline_css .= 'color:' . $this->settings->get_params( 'wheel_wrap', 'text_color' ) . ';';
		}
		$inline_css .= '}';
		if ( 'on' === $this->settings->get_params( 'wheel', 'show_full_wheel' ) ) {
			$inline_css .= '.wlwl_lucky_wheel_content .wheel_content_left{margin-left:0 !important;}';
			$inline_css .= '.wlwl_lucky_wheel_content .wheel_content_right{width:48% !important;}';
//			$inline_css .= '.wlwl_lucky_wheel_content .wheel_content_right .wlwl_user_lucky{max-width:300px !important;}';
		}

		if ( $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) ) {
			$inline_css .= '.wlwl_pointer:before{color:' . $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) . ';}';
		}
		//wheel wrap design
		$inline_css .= '.wheel_content_right .wlwl_user_lucky .wlwl_spin_button{';
		if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) ) {
			$inline_css .= 'color:' . $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) . ';';
		}

		if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) ) {
			$inline_css .= 'background-color:' . $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) . ';';
		}
		$inline_css .= '}';
		if ( $font ) {
			$inline_css .= '.wlwl_lucky_wheel_content .wheel-content-wrapper .wheel_content_right,.wlwl_lucky_wheel_content .wheel-content-wrapper .wheel_content_right input,.wlwl_lucky_wheel_content .wheel-content-wrapper .wheel_content_right span,.wlwl_lucky_wheel_content .wheel-content-wrapper .wheel_content_right a,.wlwl_lucky_wheel_content .wheel-content-wrapper .wheel_content_right .wlwl-frontend-result{font-family:' . $font . ' !important;}';
		}
		$popup_icon = $this->settings->get_params( 'notify', 'popup_icon' );
		if ( $popup_icon ) {
			$popup_icon_class    = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_gift_icon_class( $popup_icon );
			$popup_icon_color    = $this->settings->get_params( 'notify', 'popup_icon_color' );
			$popup_icon_bg_color = $this->settings->get_params( 'notify', 'popup_icon_bg_color' );
			$inline_css          .= ".wlwl_wheel_icon.{$popup_icon_class}{padding:6px;border-radius:5px;}";
			if ( $popup_icon_color ) {
				$inline_css .= ".wlwl_wheel_icon.{$popup_icon_class}{color:{$popup_icon_color};}";
			}
			if ( $popup_icon_bg_color ) {
				$inline_css .= ".wlwl_wheel_icon.{$popup_icon_class}{background-color:{$popup_icon_bg_color};}";
			}
		}
		/*Button apply coupon*/
		$inline_css .= ".wlwl-button-apply-coupon-form .wlwl-button-apply-coupon{color:{$this->settings->get_params( 'button_apply_coupon_color' )};background-color:{$this->settings->get_params( 'button_apply_coupon_bg_color' )};font-size:{$this->settings->get_params( 'button_apply_coupon_font_size' )}px;border-radius:{$this->settings->get_params( 'button_apply_coupon_border_radius' )}px;}";

		$inline_css .= $this->settings->get_params( 'wheel_wrap', 'custom_css' );
		wp_add_inline_style( 'woocommerce-lucky-wheel-frontend-style', ( $inline_css ) );

		$time_if_close = intval( $this->settings->get_params( 'notify', 'time_on_close' ) );
		switch ( $this->settings->get_params( 'notify', 'time_on_close_unit' ) ) {
			case 'm':
				$time_if_close *= MINUTE_IN_SECONDS;
				break;
			case 'h':
				$time_if_close *= HOUR_IN_SECONDS;
				break;
			case 'd':
				$time_if_close *= DAY_IN_SECONDS;
				break;
			default:
		}

		$intent = $this->settings->get_params( 'notify', 'intent' );
		if ( $intent === 'random' ) {
			$ran = wp_rand( 1, 4 );
			switch ( $ran ) {
				case 1:
					$intent = 'popup_icon';
					break;
				case 2:
					$intent = 'show_wheel';
					break;
				case 3:
					$intent = 'on_scroll';
					break;
				case 4:
					$intent = 'on_exit';
					break;
			}
		}
		$limit_time_warning = esc_html__( 'You have to wait until your next spin.', 'woocommerce-lucky-wheel' );
		switch ( $this->settings->get_params( 'notify', 'show_again_unit' ) ) {
			case 's':
				$limit_time_warning = sprintf( esc_html( 'You can only spin 1 time every %s seconds' ), $this->settings->get_params( 'notify', 'show_again' ) );
				break;
			case 'm':
				$limit_time_warning = sprintf( esc_html( 'You can only spin 1 time every %s minutes' ), $this->settings->get_params( 'notify', 'show_again' ) );
				break;
			case 'h':
				$limit_time_warning = sprintf( esc_html( 'You can only spin 1 time every %s hours' ), $this->settings->get_params( 'notify', 'show_again' ) );
				break;
			case 'd':
				$limit_time_warning = sprintf( esc_html( 'You can only spin 1 time every %s days' ), $this->settings->get_params( 'notify', 'show_again' ) );
				break;

		}
		$this->pointer_position = $this->settings->get_params( 'wheel_wrap', 'pointer_position' );
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
		wp_localize_script( 'woocommerce-lucky-wheel-frontend-javascript', '_wlwl_get_email_params', array(
			'ajaxurl'            => $this->settings->get_params( 'ajax_endpoint' ) === 'ajax' ? ( admin_url( 'admin-ajax.php' ) . '?action=wlwl_get_email' ) : site_url() . '/wp-json/woocommerce_lucky_wheel/spin',
			'pointer_position'   => $this->pointer_position,
			'wheel_dot_color'    => $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ),
			'wheel_border_color' => $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ),
			'wheel_center_color' => $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ),
			'gdpr'               => $this->settings->get_params( 'wheel_wrap', 'gdpr' )=== 'on' ? 1 : '',
			'gdpr_warning'       => esc_html__( 'Please agree with our term and condition.', 'woocommerce-lucky-wheel' ),

			'position'        => $this->settings->get_params( 'notify', 'position' ),
			'show_again'      => $this->settings->get_params( 'notify', 'show_again' ),
			'scroll_amount'   => $this->settings->get_params( 'notify', 'scroll_amount' ),
			'show_again_unit' => $this->settings->get_params( 'notify', 'show_again_unit' ),
			'intent'          => $intent,
			'hide_popup'      => $this->settings->get_params( 'notify', 'hide_popup' )==='on',

			'slice_text_color'                  => ( isset( $wheel['slice_text_color'] ) && $wheel['slice_text_color'] ) ? $wheel['slice_text_color'] : '#fff',
			'bg_color_random'                          => ($wheel['random_color']??'') === 'on' ? 1: '',
			'bg_color'                          => $this->settings->get_params( 'wheel', 'random_color' ) === 'on' ? $this->settings::get_random_color() : $wheel['bg_color'],
			'slices_text_color'                 => $this->settings->get_params( 'wheel', 'slices_text_color' ),
			'label'                             => $label,
			'coupon_type'                       => $wheel['coupon_type'],
			'spinning_time'                     => $this->settings->get_params( 'wheel', 'spinning_time' ) ?: 1,
			'wheel_speed'                       => $this->settings->get_params( 'wheel', 'wheel_speed' )?: 3,
			'auto_close'                        => $this->settings->get_params( 'result', 'auto_close' ),
			'show_wheel'                        => wlwl_get_explode( $this->settings->get_params( 'notify', 'show_wheel' ), ',' ),
			'time_if_close'                     => $time_if_close,
			'empty_email_warning'               => esc_html__( '*Please enter your email', 'woocommerce-lucky-wheel' ),
			'invalid_email_warning'             => esc_html__( '*Please enter a valid email address', 'woocommerce-lucky-wheel' ),
			'wlwl_warring_recaptcha'            => esc_html__( '*Require reCAPTCHA verification', 'woocommerce-lucky-wheel' ),
			'limit_time_warning'                => $limit_time_warning,
			'custom_field_name_enable'          => $this->settings->get_params( 'custom_field_name_enable' )=== 'on' ? 1 : '',
			'custom_field_name_enable_mobile'   => $this->settings->get_params( 'custom_field_name_enable_mobile' )=== 'on' ? 1 : '',
			'custom_field_name_required'        => $this->settings->get_params( 'custom_field_name_required' )=== 'on' ? 1 : '',
			'custom_field_name_message'         => esc_html__( 'Name is required!', 'woocommerce-lucky-wheel' ),
			'custom_field_mobile_enable'        => $this->settings->get_params( 'custom_field_mobile_enable' )=== 'on' ? 1 : '',
			'custom_field_mobile_enable_mobile' => $this->settings->get_params( 'custom_field_mobile_enable_mobile' )=== 'on' ? 1 : '',
			'custom_field_mobile_required'      => $this->settings->get_params( 'custom_field_mobile_required' ),
			'custom_field_mobile_message'       => esc_html__( 'Phone number is required!', 'woocommerce-lucky-wheel' ),
			'custom_field_mobile_warning'       => esc_html__( 'Please enter a valid mobile', 'woocommerce-lucky-wheel' ),
			'show_full_wheel'                   => $this->settings->get_params( 'wheel', 'show_full_wheel' ),
			'font_size'                         => $this->settings->get_params( 'wheel', 'font_size','',100 ),
			'wheel_size'                        => $this->settings->get_params( 'wheel', 'wheel_size','', 100 ),
			'congratulations_effect'            => $this->settings->get_params( 'wheel_wrap', 'congratulations_effect' ),
			'images_dir'                        => VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES,
			'language'                          => $this->language,
			'rotate'                            => in_array( $this->background_effect, array(
				'leaf-1',
				'leaf-2',
			) ),
			'font_text_wheel'                   => apply_filters( 'wlwl_font_text_wheel', '' ),
			'wlwl_recaptcha_site_key'           => $this->settings->get_params( 'wlwl_recaptcha_site_key' ),
			'wlwl_recaptcha_version'            => $this->settings->get_params( 'wlwl_recaptcha_version' ),
			'wlwl_recaptcha_secret_theme'       => $this->settings->get_params( 'wlwl_recaptcha_secret_theme' ),
			'wlwl_recaptcha'                    => $this->settings->get_params( 'wlwl_recaptcha' ),
			'wlwl_mobile_enable'            => $mobile_enable
		) );
		add_action( 'wp_footer', array( $this, 'draw_wheel' ) );

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

	/**
	 * Register API json
	 */
	public function register_api() {
		register_rest_route(
			'woocommerce_lucky_wheel', '/spin', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_email' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function add_recipient_to_list( $email, $list_id ) {
		$sendgrid = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendgrid();
		$sendgrid->add_recipient_to_list( $email, $list_id );
	}

	public function draw_wheel() {
		if ( isset( $_COOKIE['wlwl_cookie'] ) ) {
			return;
		}
		$spin_button = $this->settings->get_params( 'wheel_wrap', 'spin_button', $this->language );
		if ( empty( $spin_button ) ) {
			$spin_button = esc_html__( 'Try Your Lucky', 'woocommerce-lucky-wheel' );
		}
		echo '<div class="wlwl_lucky_wheel_wrap">';
		wp_nonce_field( 'woocommerce_lucky_wheel_nonce_action', '_woocommerce_lucky_wheel_nonce' );
		$center_image   = wp_get_attachment_url( $this->settings->get_params( 'wheel_wrap', 'wheel_center_image' ) );
		$wlwl_recaptcha = $this->settings->get_params( 'wlwl_recaptcha' ) && $this->settings->get_params( 'wlwl_recaptcha_site_key' ) && $this->settings->get_params( 'wlwl_recaptcha_secret_key' );

		$custom_field_mobile_enable = $this->settings->get_params( 'custom_field_mobile_enable' ) === 'on';
		if ( $custom_field_mobile_enable ) {
			$country_codes = $this->settings->get_params( 'custom_field_mobile_phone_countries' );
			if ( ! is_array( $country_codes ) ) {
				$country_codes = [];
			}
			if ( in_array( 0, $country_codes ) ) {
				$country_codes = false;
			}
			if ( is_array( $country_codes ) ) {
				$phone_countries = villatheme_json_decode( $this->settings::def_phone_country() );
				ksort( $phone_countries );
				if ( empty( $country_codes ) ) {
					foreach ( $phone_countries as $v ) {
						if ( is_array( $v ) ) {
							$country_codes += $v;
						} else {
							$country_codes[] = $v;
						}
					}
				}
				$locate               = WC_Geolocation::geolocate_ip();
				$detect_phone_country = '';
				if ( ! empty( $locate['country'] ) && isset( $phone_countries[ $locate['country'] ] ) ) {
					$tmp = $phone_countries[ $locate['country'] ];
					if ( is_array( $tmp ) ) {
						$detect_phone_country = $tmp[0];
					} else {
						$detect_phone_country = $tmp;
					}
				}
				$tmp = [];
				foreach ( $country_codes as $v ) {
					if ( ! $v ) {
						continue;
					}
					foreach ( $phone_countries as $j => $k ) {
						$check_phone_country = '';
						if ( $k === $v ) {
							$check_phone_country = true;
						} elseif ( is_array( $k ) ) {
							foreach ( $k as $k1 ) {
								if ( $k1 === $v ) {
									$check_phone_country = true;
									break;
								}
							}
						}
						if ( $check_phone_country ) {
							$tmp[ $v ] = "{$j}( {$v} )";
							break;
						}
					}
				}
				$country_codes = $tmp;
			}
		}

		if ( in_array( $this->background_effect, array(
			'snowflakes-2-1',
			'snowflakes-2-2',
			'snowflakes-2-3'
		) ) ) {
			?>
            <div class="wlwl-overlay wlwl-background-effect-snowflakes-2 <?php echo esc_attr( 'wlwl-background-effect-' . $this->background_effect ); ?>">
                <i></i>
            </div>
			<?php
		} else {
			?>
            <div class="wlwl-overlay">
            </div>
			<?php
		}
		?>
        <input id="wlwl_center_image" type="hidden" value="<?php echo esc_attr( $center_image ) ?>">
		<?php
		$class = array( 'wlwl_lucky_wheel_content' );//lucky_wheel_content_tablet,wlwl_lucky_wheel_content_mobile
		if ( $this->pointer_position === 'top' ) {
			$class[] = 'wlwl_margin_position';
			$class[] = 'wlwl_spin_top';
		} elseif ( $this->pointer_position === 'right' ) {
			$class[] = 'wlwl_margin_position';
		} elseif ( $this->pointer_position === 'bottom' ) {
			$class[] = 'wlwl_margin_position';
			$class[] = 'wlwl_spin_bottom';
		}
		if ( in_array( $this->background_effect, array(
			'snowflakes-2-1',
			'snowflakes-2-2',
			'snowflakes-2-3'
		) ) ) {
			$class[] = 'wlwl-background-effect-snowflakes-2';
			$class[] = 'wlwl-background-effect-' . $this->background_effect;
		} elseif ( in_array( $this->background_effect, array(
			'hearts',
			'heart',
			'smile',
			'star',
			'leaf-1',
			'leaf-2',
			'halloween-1',
			'halloween-2',
			'halloween-3'
		) ) ) {
			$class[] = "wlwl-background-effect-falling-leaves";
			$class[] = "wlwl-background-effect-{$this->background_effect}";
		}
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $class ) ) ?>">
	        <?php
	        switch ( $this->background_effect ) {
		        case 'snowflakes':
			        self::snowflake_html();
			        break;
		        case 'snowflakes-1':
			        self::snowflake_1_html();
			        break;
		        case 'snowflakes-2-1':
		        case 'snowflakes-2-2':
		        case 'snowflakes-2-3':
			        ?>
                    <i></i>
			        <?php
			        break;
		        default:
	        }
	        ?>
            <div class="wheel-content-wrapper">
                <div class="wheel_content_left">
                    <div class="wlwl-frontend-result"></div>
                    <div class="wlwl_wheel_spin">
                        <canvas id="wlwl_canvas"></canvas>
                        <canvas id="wlwl_canvas1" class="<?php
				        if ( $this->pointer_position === 'top' ) {
					        echo 'canvas_spin_top';
				        } elseif ( $this->pointer_position === 'bottom' ) {
					        echo 'canvas_spin_bottom';
				        } ?>">
                        </canvas>
                        <canvas id="wlwl_canvas2"></canvas>
                        <div class="wlwl_wheel_spin_container">
                            <div class="wlwl_pointer_before"></div>
                            <div class="wlwl_pointer_content">
                                <span class="wlwl-location wlwl_pointer <?php
                                if ( $this->pointer_position === 'top' ) {
	                                echo 'pointer_spin_top';
                                } elseif ( $this->pointer_position === 'bottom' ) {
	                                echo 'pointer_spin_bottom';
                                } ?>"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wheel_content_right">
                    <div class="wheel_description">
				        <?php
				        echo do_shortcode( $this->settings->get_params( 'wheel_wrap', 'description', $this->language ) );
				        ?>
                    </div>
                    <div class="wlwl-congratulations-effect">
                        <div class="wlwl-congratulations-effect-before"></div>
                        <div class="wlwl-congratulations-effect-after"></div>
                    </div>
                    <div class="wlwl_user_lucky">
				        <?php
				        if ( 'on' === $this->settings->get_params( 'custom_field_name_enable' ) ) {
					        ?>
                            <div class="wlwl_field_name_wrap">
                                <input type="text" class="wlwl_field_input wlwl_field_name" name="wlwl_player_name"
                                       placeholder="<?php esc_html_e( 'Please enter your name', 'woocommerce-lucky-wheel' ) ?>"
                                       id="wlwl_player_name">
                                <span id="wlwl_error_name" class="wlwl_error_field"></span>
                            </div>
					        <?php
				        }
				        if ( $custom_field_mobile_enable ) {
					        $attribute_arr = apply_filters( 'wlwl_filter_attribute_phone', [
						        'type' => 'tel'
					        ] );
					        ?>
                            <div class="wlwl_field_mobile_wrap <?php echo ! empty( $country_codes ) ? 'wlwl_field_mobile_wrap_country_code' : ''; ?>">
						        <?php
						        if ( ! empty( $country_codes ) ) {
							        echo wp_kses( '<select name="wlwl_country_code" id="wlwl_country_code" class="wlwl_field_select">', $this->settings::filter_allowed_html() );
							        foreach ( $country_codes as $k => $v ) {
								        echo wp_kses( sprintf( '<option value="%s" %s > %s </option>', $k, selected( $k, $detect_phone_country ), $v ), $this->settings::filter_allowed_html() );
							        }
							        echo wp_kses( '</select>', $this->settings::filter_allowed_html() );
						        }
						        ?>
                                <input <?php echo wp_kses( wc_implode_html_attributes( $attribute_arr ), $this->settings::filter_allowed_html() ); ?>
                                        class="wlwl_field_input wlwl_field_mobile"
                                        name="wlwl_player_mobile"
                                        placeholder="<?php esc_html_e( 'Please enter your phone number', 'woocommerce-lucky-wheel' ) ?>"
                                        id="wlwl_player_mobile">
                                <span id="wlwl_error_mobile" class="wlwl_error_field"></span>
                            </div>
					        <?php
				        }
				        ?>
                        <div class="wlwl_field_email_wrap">
                            <input type="email" class="wlwl_field_input wlwl_field_email" name="wlwl_player_mail"
                                   placeholder="<?php esc_html_e( 'Please enter your email', 'woocommerce-lucky-wheel' ) ?>"
                                   value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->user_email : '' ) ?>"
                                   id="wlwl_player_mail">
                            <span id="wlwl_error_mail" class="wlwl_error_field"></span>
                        </div>
                        <!--captcha-->
				        <?php
				        if ( $wlwl_recaptcha ) {
					        ?>
                            <div class="wlwl_recaptcha_wrap">
                                <div class="wlwl-recaptcha-field">
                                    <div id="wlwl-recaptcha" class="wlwl-recaptcha"></div>
                                    <input type="hidden" value="" id="wlwl-g-validate-response">
                                </div>
                                <div id="wlwl_warring_recaptcha" class="wlwl_error_field"></div>
                            </div>
					        <?php
				        }
				        ?>
                        <span class="wlwl_chek_mail wlwl_spin_button button-primary"
                              id="wlwl_chek_mail"><?php echo esc_html( $spin_button ); ?></span>
				        <?php
				        if ( 'on' === $this->settings->get_params( 'wheel_wrap', 'gdpr' ) ) {
					        $gdpr_message = $this->settings->get_params( 'wheel_wrap', 'gdpr_message', $this->language );
					        if ( empty( $gdpr_message ) ) {
						        $gdpr_message = esc_html__( 'I agree with the term and condition', 'woocommerce-lucky-wheel' );
					        }
					        ?>
                            <div class="wlwl-gdpr-checkbox-wrap">
                                <input type="checkbox">
                                <span><?php echo wp_kses_post( $gdpr_message ) ?></span>
                            </div>
					        <?php
				        }
				        if ( 'on' === $this->settings->get_params( 'wheel_wrap', 'close_option' ) ) {
					        ?>
                            <div class="wlwl-show-again-option">
                                <div class="wlwl-never-again">
                                    <span><?php esc_html_e( 'Never', 'woocommerce-lucky-wheel' ); ?></span>
                                </div>
                                <div class="wlwl-reminder-later">
                                    <span class="wlwl-reminder-later-a"><?php esc_html_e( "Remind later", 'woocommerce-lucky-wheel' ); ?></span>
                                </div>
                                <div class="wlwl-close">
                                    <span><?php esc_html_e( 'No thanks', 'woocommerce-lucky-wheel' ); ?></span>
                                </div>
                            </div>
					        <?php
				        }
				        ?>
                    </div>
                </div>
		        <?php
		        if ( $this->background_effect === 'floating-bubbles' ) {
			        self::bubbles_html( array(
				        'balloon-1',
				        'balloon-2',
				        'balloon-3',
				        'balloon-4',
				        'balloon-5',
				        'balloon-2',
				        'balloon-5',
				        'balloon-1',
				        'balloon-4',
				        'balloon-3',
				        'balloon-2',
				        'balloon-5',
				        'balloon-1',
				        'balloon-4',
				        'balloon-3',
				        'balloon-1'
			        ) );
		        } elseif ( $this->background_effect === 'floating-halloween' ) {
			        self::bubbles_html( array(
				        'pumpkin',
				        'halloween-ghost',
				        'pumpkin',
				        'ghost',
				        'pumpkin',
				        'creepy-ghost',
				        'pumpkin',
				        'halloween-ghost',
				        'pumpkin',
				        'ghost',
				        'pumpkin',
				        'creepy-ghost',
				        'pumpkin',
				        'halloween-ghost',
				        'pumpkin',
				        'ghost',
				        'pumpkin',
				        'creepy-ghost',
			        ) );
		        }
		        ?>
            </div>
            <div class="wlwl-close-wheel"><span class="wlwl-cancel"></span></div>
            <div class="wlwl-hide-after-spin">
                <span class="wlwl-cancel"></span>
            </div>
        </div>
        <?php
		$wheel_icon_class = 'wlwl_wheel_icon woocommerce-lucky-wheel-popup-icon wlwl-wheel-position-' . $this->settings->get_params( 'notify', 'position' );
		$popup_icon       = $this->settings->get_params( 'notify', 'popup_icon' );
		if ( $popup_icon ) {
			$wheel_icon_class .= ' ' . VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_gift_icon_class( $popup_icon );
			?>
            <span class="<?php echo esc_attr( $wheel_icon_class ) ?>"></span>
			<?php
		} else {
			?>
            <canvas id="wlwl_popup_canvas" class="<?php echo esc_attr( $wheel_icon_class ) ?>" width="64"
                    height="64"></canvas>
			<?php
		}
		echo '</div>';
	}

	public function wc_price( $price, $args = array() ) {
		extract(
			apply_filters(
				'wc_price_args', wp_parse_args(
					$args, array(
						'ex_tax_label'       => false,
						'currency'           => get_option( 'woocommerce_currency' ),
						'decimal_separator'  => get_option( 'woocommerce_price_decimal_sep' ),
						'thousand_separator' => get_option( 'woocommerce_price_thousand_sep' ),
						'decimals'           => apply_filters( 'wlwl_woocommerce_price_num_decimals', get_option( 'woocommerce_price_num_decimals', 2 ) ),
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
		$price             = apply_filters( 'raw_woocommerce_price', floatval( $negative ? ( (int) $price * - 1 ) : $price ) );
		$price             = apply_filters( 'formatted_woocommerce_price', number_format( $price, $decimals, $decimal_separator, $thousand_separator ), $price, $decimals, $decimal_separator, $thousand_separator );

		if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $decimals > 0 ) {
			$price = wc_trim_zeros( $price );
		}
		if ( $this->settings->get_params( 'wheel', 'currency' ) === 'code' ) {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, ( $currency ), $price );
		} else {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, wlwl_get_currency_symbol( $currency ), $price );
		}

		return $formatted_price;
	}

	public function get_email() {
		if ( $this->settings->get_params( 'ajax_endpoint' ) === 'rest_api' ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: POST' );
		}
		$msg = array(
			'status'              => '',
			'message'             => '',
			'allow_spin'          => '',
			'warning'             => '',
			'g_validate_response' => '',
		);
		if ( $this->settings->get_params( 'wlwl_recaptcha' ) ) {
			$msg['g_validate_response'] = 1;
			$wlwl_recaptcha_secret_key  = $this->settings->get_params( 'wlwl_recaptcha_secret_key' );
			if ( ! $wlwl_recaptcha_secret_key ) {
				$msg['status']  = 'invalid';
				$msg['warning'] = esc_html__( '*Invalid google reCAPTCHA secret key!', 'woocommerce-lucky-wheel' );
				wp_send_json( $msg );
				die;
			}
			$g_validate_response = isset( $_POST['g_validate_response'] ) ? sanitize_text_field( $_POST['g_validate_response'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $g_validate_response ) {
				$msg['status']  = 'invalid';
				$msg['warning'] = esc_html__( '*No g_validate_response', 'woocommerce-lucky-wheel' );
				wp_send_json( $msg );
			}
			$url  = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $wlwl_recaptcha_secret_key . '&response=' . $g_validate_response;
			$curl = curl_init();// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
			curl_setopt_array( $curl, array(// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => "",
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => "POST",
				CURLOPT_POSTFIELDS     => '{}',
				CURLOPT_HTTPHEADER     => array(
					"content-type: application/json"
				),
			) );

			$response = curl_exec( $curl );// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
			$err      = curl_error( $curl );// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
			curl_close( $curl );// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
			if ( $err ) {
				$msg['status']  = 'invalid';
				$msg['warning'] = "*reCAPTCHA cURL Error #:" . $err;
				wp_send_json( $msg );
			} else {
				$data = json_decode( $response, true );
				if ( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 2 ) {
					if ( ! $data['success'] ) {
						$msg['status']  = 'invalid';
						$msg['warning'] = esc_html__( '*reCAPTCHA verification failed', 'woocommerce-lucky-wheel' );
						$msg['message'] = $data;
						wp_send_json( $msg );
					}
				} else {
					$g_score = isset( $data['score'] ) ? $data['score'] : 0;
					if ( $g_score < 0.5 ) {
						$msg['status']  = 'invalid';
						$msg['warning'] = esc_html( '*reCAPTCHA score ' . $g_score . ' lower than threshold 0.5 ' );
						$msg['message'] = $data;
						wp_send_json( $msg );
					}
				}
			}
		}
		$origin_prize = isset( $_POST['origin_prize'] ) ? array_map( 'sanitize_text_field', $_POST['origin_prize'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$language     = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email        = isset( $_POST['user_email'] ) ? sanitize_email( strtolower( $_POST['user_email'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name         = ( isset( $_POST['user_name'] ) && $_POST['user_name'] ) ? sanitize_text_field( $_POST['user_name'] ) : 'Sir/Madam';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mobile       = ( isset( $_POST['user_mobile'] ) && $_POST['user_mobile'] ) ? sanitize_text_field( $_POST['user_mobile'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $email || ! is_email( $email ) ) {
			$msg['allow_spin'] = esc_html__( 'Email is invalid', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		/*Option white and black list*/
		$choose_list = $this->settings->get_params( 'choose_using_white_black_list' );
		$list_text   = $this->settings->get_params( $choose_list );

		if ( ! empty( $list_text ) ) {
			$lines_domain_email     = explode( "\n", $list_text );
			$lines_domain_email     = array_map( 'trim', $lines_domain_email );
			$lines_domain_email     = array_map( 'strtolower', $lines_domain_email );
			$explode_email_to_check = explode( '@', $email );
			$email_to_check         = $explode_email_to_check[1] ?? '';

			switch ( $choose_list ) {
				case 'white_list':
					if ( ! in_array( $email_to_check, $lines_domain_email ) ) {
						$msg['warning'] = esc_html__( 'Your email is not allowed', 'woocommerce-lucky-wheel' );
						wp_send_json( $msg );
					}
					break;
				case 'black_list':
					if ( in_array( $email_to_check, $lines_domain_email ) ) {
						$msg['warning'] = esc_html__( 'Your email is not allowed', 'woocommerce-lucky-wheel' );
						wp_send_json( $msg );
					}
					break;
			}
		}

		/*End option white and black list */
		if ( ! $name && 'on' === $this->settings->get_params( 'custom_field_name_required' )&&
		     (!empty($_POST['is_desktop']) || ('on' == $this->settings->get_params( 'custom_field_name_enable_mobile' )) )  ) {
			$msg['warning'] = esc_html__( 'Name is required', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		if ( ! $mobile && 'on' === $this->settings->get_params( 'custom_field_mobile_required' ) &&
		     (!empty($_POST['is_desktop']) || ('on' == $this->settings->get_params( 'custom_field_mobile_enable_mobile' )) )) {
			$msg['warning'] = esc_html__( 'Phone number is required', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		if ( $mobile && ! WC_Validation::is_phone( $mobile ) ) {
			$msg['warning'] = esc_html__( 'Mobile field is not a valid phone number.', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		if ( $this->settings->get_params( 'general', 'enable' ) !== 'on' ) {
			$msg['warning'] = esc_html__( 'Wrong email.', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		$allow       = 'no';
		$email_delay = intval( $this->settings->get_params( 'general', 'delay' ) );
		switch ( $this->settings->get_params( 'general', 'delay_unit' ) ) {
			case 'm':
				$email_delay *= MINUTE_IN_SECONDS;
				break;
			case 'h':
				$email_delay *= HOUR_IN_SECONDS;
				break;
			case 'd':
				$email_delay *= DAY_IN_SECONDS;
				break;
			default:
		}
		$stop                = - 1;
		$result              = 'lost';
		$frontend_message    = $this->settings->get_params( 'result', 'notification', $language );
		$result_notification = $frontend_message['lost']??'';
		$now                 = time();
		$wheel               = $this->settings->get_params( 'wheel' );
		$custom_label        = $this->settings->get_params( 'wheel', 'custom_label', $language );
		if (!isset( $wheel['coupon_type']) || !is_array( $wheel['coupon_type'])){
			$wheel['coupon_type'] = [];
		}
		if (!isset( $wheel['probability']) || !is_array( $wheel['probability'])){
			$wheel['probability'] = [];
		}
		if ( isset( $wheel['prize_quantity'] ) ) {
			$prize_quantity = $wheel['prize_quantity'];
			foreach ( $wheel['coupon_type'] as $count => $v ) {
				if ( $v !== 'non' ) {
					if ( $prize_quantity[ $count ] == 0 ) {
						$wheel['probability'][ $count ] = 0;
					} elseif ( ! in_array( $v, array(
						'custom',
						'existing_coupon',
						'percent',
						'fixed_cart',
						'fixed_product'
					) ) ) {
						$dynamic_coupon = get_post( $v );
						if ( ! $dynamic_coupon || $dynamic_coupon->post_status !== 'publish' ) {
							$wheel['coupon_type'][ $count ] = 'non';
							$custom_label[ $count ]         = esc_html__( 'Not Lucky', 'woocommerce-lucky-wheel' );
						}
					}
				}
			}
		}
		$email_templates = $this->settings->get_params( 'wheel', 'email_templates', $language );
		if ( is_array( $origin_prize ) && count( $origin_prize ) !== count( array_intersect_assoc( $origin_prize, $wheel['coupon_type'] ) ) ) {
			$msg['warning'] = esc_html__( 'Prizes have been changed, please reload the page and spin again. Thank you!', 'woocommerce-lucky-wheel' );
			wp_send_json( $msg );
		}
		/*Mail api region*/
		$arr_name   = explode( ' ', $name );
		$first_name = $name;
		$last_name  = '';
		if ( count( $arr_name ) > 1 ) {
			$first_name = $arr_name[0];
			unset( $arr_name[0] );/*Delete first name*/
			$last_name = implode( ' ', $arr_name );
		}
		/*Mailchimp*/
		if ( $this->settings->get_params( 'mailchimp', 'enable' ) === 'on' ) {
			$mailchimp         = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Mailchimp();
			$mailchimp_list_id = $this->settings->get_params( 'mailchimp', 'lists', $language );
			$mailchimp->add_email( $email, $mailchimp_list_id, $first_name, $last_name, $mobile );
		}
		if ( 'on' === $this->settings->get_params( 'active_campaign', 'enable' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Active_Campaign' ) ) {
			$active_campaign = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Active_Campaign();
			$ac_list_id      = $this->settings->get_params( 'active_campaign', 'list' );
			if ( $ac_list_id ) {
				$active_campaign->contact_add( $email, $ac_list_id, $first_name, $last_name, $mobile );
			} else {
				$active_campaign->contact_add( $email, '', $first_name, $last_name, $mobile );
			}
		}
		/*Sendgrid*/
		if ( 'on' === $this->settings->get_params( 'sendgrid', 'enable' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendgrid' ) ) {
			$sendgrid = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendgrid();
			$sendgrid->add_recipient( $email, $name );
			$sendgrid_list = $this->settings->get_params( 'sendgrid', 'list' );
			if ( $sendgrid_list && $sendgrid_list != 'none' ) {
				$time = time() + MINUTE_IN_SECONDS;
				wp_schedule_single_event(
					$time, 'wlwl_schedule_add_recipient_to_list', array(
						$email,
						$sendgrid_list,
					)
				);
			}
		}
		/*Metrilo*/
		$data = array();
		if ( $this->settings->get_params( 'metrilo_enable' ) ) {
			$metrilo                  = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Metrilo();
			$data['metrilo_response'] = $metrilo->contact_add( $email, $name, '', $language );
		}
		/*Hubspot*/
		if ( $this->settings->get_params( 'wlwl_enable_hubspot' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Hubspot' ) ) {
			$hubspot = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Hubspot();
			$hubspot->add_recipient( $email, $first_name, $last_name, $mobile );
		}

		/*Klaviyo*/
		if ( $this->settings->get_params( 'wlwl_enable_klaviyo' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Klaviyo' ) ) {
			$klaviyo      = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Klaviyo();
			$klaviyo_list = $this->settings->get_params( 'wlwl_klaviyo_list' );
			$klaviyo->add_recipient( $email, $klaviyo_list, $first_name, $last_name, $mobile );
		}
		/*Sendinblue*/
		if ( $this->settings->get_params( 'wlwl_enable_sendinblue' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendinblue' ) ) {
			$sendinblue      = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendinblue();
			$sendinblue_list = $this->settings->get_params( 'wlwl_sendinblue_list' );
			$sendinblue_list = array_map( 'absint', $sendinblue_list );

			$sendinblue->add_recipient( $email, $sendinblue_list, $first_name, $last_name, $mobile );
		}
		/*MailPoet*/
		if ( $this->settings->get_params( 'wlwl_enable_mailpoet' ) && class_exists( \MailPoet\API\API::class ) ) {
			$mailpoet_api           = \MailPoet\API\API::MP( 'v1' );
			$mailpoet_selected_list = $this->settings->get_params( 'wlwl_mailpoet_list' );
			$mailpoet_selected_list = array_map( 'absint', $mailpoet_selected_list );

			try {
				$mailpoet_api->addSubscriber(
					[
						'email'  => $email,
						'status' => 'subscribed'
					],
					$mailpoet_selected_list
				);
			} catch ( \MailPoet\API\MP\v1\APIException $e ) {
			}
		}

		/*Mailster*/
		if ( $this->settings->get_params( 'wlwl_enable_mailster' ) && function_exists( 'mailster' ) ) {
			// define to overwrite existing users
			$overwrite = true;

			// add with double opt in
			$double_opt_in = true;

			// prepare the userdata from a $_POST request. only the email is required
			$user_mailster_data = array(
				'email'     => $email,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'status'    => 1,
			);

			// add a new subscriber and $overwrite it if exists
			$subscriber_mailster_id = mailster( 'subscribers' )->add( $user_mailster_data, $overwrite );

			// if result isn't a WP_error assign the lists
			if ( ! is_wp_error( $subscriber_mailster_id ) ) {

				// your list ids
				$list_mailster_ids = $this->settings->get_params( 'wlwl_mailster_list' ) ?? [];
				if ( ! empty( $list_mailster_ids ) ) {
					mailster( 'subscribers' )->assign_lists( $subscriber_mailster_id, $list_mailster_ids );
				}

			} else {
				// actions if adding fails. $subscriber_id is a WP_Error object
			}
		}

		/*Sendy*/
		if ( $this->settings->get_params( 'wlwl_enable_sendy' ) && class_exists( 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendy' ) ) {
			$sendy = new VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendy();

			$sendy->add_subscribe( $email, $first_name, $last_name, $language );

		}

		/*FunnelKit*/
		if ( $this->settings->get_params( 'wlwl_enable_funnelkit' ) && class_exists( 'BWFCRM_Contact' ) ) {
			$contact_obj             = BWF_Contacts::get_instance();
			$funnelkit_selected_list = $this->settings->get_params( 'wlwl_funnelkit_list' );
			$funnelkit_status        = $this->settings->get_params( 'wlwl_funnelkit_status' );

			$contact = $contact_obj->get_contact_by( 'email', $email );
			if ( 0 === $contact->get_id() ) {
				/** New contact */
				! empty( $email ) && $contact->set_email( $email );
				$contact->set_creation_date( date( 'Y-m-d H:i:s' ) );// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} else {
				! empty( $funnelkit_selected_list ) && $contact->set_lists( $funnelkit_selected_list );
			}
			! empty( $name ) && $contact->set_l_name( $name );
			! empty( $mobile ) && $contact->set_contact_no( $mobile );
			! empty( $funnelkit_selected_list ) && $contact->set_lists( $funnelkit_selected_list );

			/**
			 * Contact status
			 * 0 - Unverified
			 * 1 - Subscribed
			 * 2 - Bounced
			 */
			isset( $funnelkit_status ) && $contact->set_status( absint( $funnelkit_status ) );
			do_action( 'wlwl_funnelkit_api_email', $contact, $email, $name, $mobile );
			/** Save contact */
			$contact->save();
		}
		do_action( 'wlwl_end_api_email', $email, $name, $mobile );
		do_action( 'woo_lucky_wheel_get_email_before_validating_email', $email, $name, $mobile );
		$wlwl_emails_args = array(
			'post_type'      => 'wlwl_email',
			'posts_per_page' => 1,
			'title'          => $email,
			'post_status'    => array( // (string | array) - use post status. Retrieves posts by Post Status, default value i'publish'.
				'publish', // - a published post or page.
				'pending', // - post is pending review.
				'draft',  // - a post in draft status.
				'auto-draft', // - a newly created post, with no content.
				'future', // - a post to publish in the future.
				'private', // - not visible to users who are not logged in.
				'inherit', // - a revision. see get_children.
				'trash', // - post is in trashbin (available with Version 2.9).
			)
		);
		$the_query        = get_posts( $wlwl_emails_args );
		if ( $the_query && ! empty( $the_query[0] ) ) {
			$tmp      = $the_query[0];
			$email_id = $tmp->ID;
			if ( $tmp->post_status === 'trash' ) {
				$allow               = esc_html__( 'Sorry, this email is marked as spam now. Please enter another email to continue.', 'woocommerce-lucky-wheel' );
				$data ['allow_spin'] = $allow;
				wp_send_json( $data );
			}
			$old_mobile = get_post_meta( $email_id, 'wlwl_email_mobile', true );
			if ( ! $old_mobile && $mobile ) {
				update_post_meta( $email_id, 'wlwl_email_mobile', $mobile );
			}
			if ( $name != $tmp->post_content ) {
				$tmp->post_content = $name;
				wp_update_post( $tmp );
			}
			$spin_meta = get_post_meta( $email_id, 'wlwl_spin_times', true );
			$spin_num  = $this->settings->get_params( 'general', 'spin_num' );
			if (is_numeric($spin_num) &&  $spin_meta['spin_num'] >= floatval($spin_num) ) {
				$allow = esc_html__( 'You have reached the maximum spins.', 'woocommerce-lucky-wheel' );
			} elseif ( ( $now - $spin_meta['last_spin'] ) < $email_delay ) {
				$wait      = $email_delay + $spin_meta['last_spin'] - $now;
				$wait_day  = floor( $wait / DAY_IN_SECONDS );
				$wait_hour = floor( ( $wait - $wait_day * DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
				$wait_min  = floor( ( $wait - $wait_day * DAY_IN_SECONDS - $wait_hour * HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
				$wait_sec  = $wait - $wait_day * DAY_IN_SECONDS - $wait_hour * HOUR_IN_SECONDS - $wait_min * MINUTE_IN_SECONDS;

				$wait_return = $wait_sec . esc_html__( ' seconds', 'woocommerce-lucky-wheel' );
				if ( $wait_day ) {
					$wait_return = sprintf( esc_html( '%1$s days %2$s hours %3$s minutes %4$s seconds' ), esc_attr( $wait_day ), esc_attr( $wait_hour ), esc_attr( $wait_min ), esc_attr( $wait_sec ) );
				} elseif ( $wait_hour ) {
					$wait_return = sprintf( esc_html( '%1$s hours %2$s minutes %3$s seconds' ), esc_attr( $wait_hour ), esc_attr( $wait_min ), esc_attr( $wait_sec ) );
				} elseif ( $wait_min ) {
					$wait_return = sprintf( esc_html( '%1$s minutes %2$s seconds' ), esc_attr( $wait_min ), esc_attr( $wait_sec ) );
				}
				$allow = esc_html__( 'You have to wait ', 'woocommerce-lucky-wheel' ) . esc_html( $wait_return ) . esc_html__( ' to be able to spin again.', 'woocommerce-lucky-wheel' );
			} else {
				$allow = 'yes';
				$spin_meta['spin_num'] ++;
				update_post_meta(
					$email_id, 'wlwl_spin_times', array(
						'spin_num'  => $spin_meta['spin_num'],
						'last_spin' => $now,
						'gdpr'      => 1
					)
				);
			}
		} else {
			$allow = 'yes';
			//save email
			$email_id = wp_insert_post(
				array(
					'post_title'   => $email,
					'post_name'    => $email,
					'post_content' => $name,
					'post_author'  => 1,
					'post_status'  => 'publish',
					'post_type'    => 'wlwl_email',
				)
			);
			update_post_meta( $email_id, 'wlwl_email_mobile', $mobile );
			update_post_meta( $email_id, 'wlwl_spin_times', array(
				'spin_num'  => 1,
				'last_spin' => $now,
				'gdpr'      => 1
			) );
		}
		$wheel_label = '';/*For action hook woo_lucky_wheel_get_email*/
		if ( $allow === 'yes' ) {
			$stop = self::get_result( $wheel );
			if ( $wheel['coupon_type'][ $stop ] !== 'non' ) {
				$result              = 'win';
				$result_notification = $frontend_message['win'];
				$wheel_label         = $custom_label[ $stop ];
				$email_template      = isset( $email_templates[ $stop ] ) ? $email_templates[ $stop ] : '';
				$wlwl_email_coupons  = get_post_meta( $email_id, 'wlwl_email_coupons', true );
				$email_coupons       = is_array( $wlwl_email_coupons ) ? $wlwl_email_coupons : array();
				$wlwl_email_labels   = get_post_meta( $email_id, 'wlwl_email_labels', true );
				$email_labels        = is_array( $wlwl_email_labels ) ? $wlwl_email_labels : array();
				switch ( $wheel['coupon_type'][ $stop ] ) {
					case 'custom':
						if ( ! empty( $frontend_message['win_custom'] ) ) {
							$result_notification = $frontend_message['win_custom'];
						}
						$email_coupons_code = !empty($wheel['custom_value'][ $stop ]) ? $wheel['custom_value'][ $stop ] :'';
						$this->send_email( $email, $name, $email_coupons_code, '', $wheel_label, $language, $mobile, $email_template );
						$result_notification = str_replace( '{coupon_code}', '<strong>' . $email_coupons_code . '</strong>', $result_notification );
						break;
					case 'existing_coupon':
						$code   = get_post( $wheel['existing_coupon'][ $stop ] )->post_title;
						$coupon = new WC_Coupon( $code );
						if ( $coupon->get_discount_type() === 'percent' ) {
							$wheel_label = str_replace( '{coupon_amount}', $coupon->get_amount() . '%', $wheel_label );
						} else {
							$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $coupon->get_amount() ), $wheel_label );
							$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
						}
						$email_restrict = is_array( $coupon->get_email_restrictions() ) ? $coupon->get_email_restrictions() : array();
						if ( 'yes' === $this->settings->get_params( 'coupon', 'email_restriction' ) && ! in_array( $email, $email_restrict ) ) {
							$email_restrict[] = $email;
							$coupon->set_email_restrictions( $email_restrict );
							$coupon->save();
						}
						$email_coupons_code = $coupon->get_code();
						$this->send_email( $email, $name, $email_coupons_code, $coupon->get_date_expires(), $wheel_label, $language, $mobile, $email_template );
						$result_notification = str_replace( '{coupon_code}', '<strong>' . $coupon->get_code() . '</strong>', $result_notification );
						$result_notification .= $this->button_apply_coupon_html( $this->settings->get_params( 'button_apply_coupon_redirect', '', $language ), $email_coupons_code);
						break;
					case 'percent':
					case 'fixed_cart':
					case 'fixed_product':
						if ( $wheel['coupon_type'][ $stop ] === 'percent' ) {
							$wheel_label = str_replace( '{coupon_amount}', $wheel['coupon_amount'][ $stop ] . '%', $wheel_label );
						} else {
							$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $wheel['coupon_amount'][ $stop ] ), $wheel_label );
							$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
						}
						$coupon = $this->create_coupon( $wheel['coupon_type'][ $stop ], $wheel['coupon_amount'][ $stop ] );
						if ( 'yes' === $this->settings->get_params( 'coupon', 'email_restriction' ) ) {
							$email_restrict = array( $email );
							$coupon->set_email_restrictions( $email_restrict );
							$coupon->save();
						}
						$email_coupons_code = $coupon->get_code();
						$this->send_email( $email, $name, $email_coupons_code, $coupon->get_date_expires(), $wheel_label, $language, $mobile, $email_template );
						$result_notification .= $this->button_apply_coupon_html( $this->settings->get_params( 'button_apply_coupon_redirect', '', $language ), $email_coupons_code);
						$result_notification = str_replace( '{coupon_code}', '<strong>' . $email_coupons_code . '</strong>', $result_notification );
						break;
					default:
						$coupon_type_tmp   = get_post_meta( $wheel['coupon_type'][ $stop ], 'coupon_type', true );
						$coupon_amount_tmp = get_post_meta( $wheel['coupon_type'][ $stop ], 'coupon_amount', true );
						if ( $coupon_type_tmp === 'percent' ) {
							$wheel_label = str_replace( '{coupon_amount}', $coupon_amount_tmp . '%', $wheel_label );
						} else {
							$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $coupon_amount_tmp ), $wheel_label );
							$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
						}
						$coupon = $this->create_dynamic_coupon( $wheel['coupon_type'][ $stop ], $wheel_label );
						if ( ! $coupon ) {
							$data['allow_spin'] = esc_html__( 'Prizes have been changed, please reload the page and spin again. Thank you!', 'woocommerce-lucky-wheel' );
							wp_send_json( $data );
						}
						if ( get_post_meta( $wheel['coupon_type'][ $stop ], 'email_restriction', true ) ) {
							$email_restrict = array( $email );
							$coupon->set_email_restrictions( $email_restrict );
							$coupon->save();
						}
						if ( get_post_meta( $wheel['coupon_type'][ $stop ], 'custom_winning_message', true ) ) {
							$result_notification = get_post_meta( $wheel['coupon_type'][ $stop ], $language ? "result_win_{$language}" : 'result_win', true );
						}
						$email_coupons_code = $coupon->get_code();
						$this->send_email( $email, $name, $email_coupons_code, $coupon->get_date_expires(), $wheel_label, $language, $mobile, $email_template );
						$result_notification .= $this->button_apply_coupon_html( get_post_meta( $wheel['coupon_type'][ $stop ], 'custom_button_apply_coupon_redirect', true ) ? get_post_meta( $wheel['coupon_type'][ $stop ], $language ? "button_apply_coupon_redirect_{$language}" : 'button_apply_coupon_redirect', true ) : $this->settings->get_params( 'button_apply_coupon_redirect', '', $language ), $email_coupons_code );
						$result_notification = str_replace( '{coupon_code}', '<strong>' . $email_coupons_code . '</strong>', $result_notification );
				}
				$email_coupons[] = $email_coupons_code ?? $code ?? '';
				$email_labels[]  = $wheel_label;
				update_post_meta( $email_id, 'wlwl_email_coupons', $email_coupons );
				update_post_meta( $email_id, 'wlwl_email_labels', $email_labels );
				$result_notification = str_replace( '{coupon_label}', '<strong>' . $wheel_label . '</strong>', $result_notification );
				$result_notification = str_replace( '{customer_name}', '<strong>' . $name . '</strong>', $result_notification );
				$result_notification = str_replace( '{customer_email}', '<strong>' . $email . '</strong>', $result_notification );
				$result_notification = str_replace( '{checkout}', '<a href="' . wc_get_checkout_url() . '">' . esc_html__( 'Checkout', 'woocommerce-lucky-wheel' ) . '</a>', $result_notification );
				$result_notification = str_replace( '{quantity_label}', '', $result_notification );
				$result_notification = str_replace( array( '\n', '/n' ), ' ', $result_notification );
				if ( isset( $wheel['prize_quantity'] ) ) {
					$prize_quantity_left = intval( $wheel['prize_quantity'][ $stop ] ??0);
					if ( $prize_quantity_left > 0 ) {
						$params                                     = $this->settings->get_params();
						$params['wheel']['prize_quantity'][ $stop ] = $prize_quantity_left - 1;
						update_option( '_wlwl_settings', $params );
						$this->send_email_no_prize_left( $params );
					}
				}
			}
		}
		do_action( 'woo_lucky_wheel_get_email', $email, $name, $mobile, $wheel_label, $result_notification );
		$data['allow_spin']          = $allow;
		$data['stop_position']       = $stop;
		$data['result_notification'] = do_shortcode( $result_notification );
		$data['result']              = $result;
		$data                        = apply_filters( 'woo_lucky_wheel_get_email_response', $data, $email, $name, $mobile );
		wp_send_json( $data );
	}

	public function button_apply_coupon_html( $redirect, $code ) {
		if ( $this->settings->get_params( 'button_apply_coupon' ) ) {
			if ( strpos( $redirect, '{checkout_page}' ) !== false ) {
				$redirect = wc_get_checkout_url();
			} elseif ( strpos( $redirect, '{cart_page}' ) !== false ) {
				$redirect = wc_get_cart_url();
			}
			ob_start();
			?>
            <form class="wlwl-button-apply-coupon-form" method="post"
                  action="<?php echo wc_is_valid_url( $redirect ) ? esc_url( $redirect ) : '';// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>">
                <input type="hidden" name="coupon_code" class="wlwl-button-apply-coupon-value"
                       value="<?php echo esc_attr( $code ) ?>"/>
                <button type="submit" class="wlwl-button-apply-coupon" name="apply_coupon"
                        value="<?php esc_attr_e( 'Apply coupon', 'woocommerce-lucky-wheel' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce-lucky-wheel' ); ?></button>
            </form>
			<?php
			return ob_get_clean();
		} else {
			return '';
		}
	}

	/**
	 * @param $params
	 */
	protected function send_email_no_prize_left( $params ) {
		$non = 0;
        if (!isset($params['wheel']['coupon_type']) || !is_array($params['wheel']['coupon_type'])){
	        $params['wheel']['coupon_type'] = [];
        }
		foreach ( $params['wheel']['coupon_type'] as $key => $value ) {
			if ( $value === 'non' || $params['wheel']['prize_quantity'][ $key ] == 0 ) {
				$non ++;
			}
		}
		if ( $non === count( $params['wheel']['coupon_type'] ) ) {
			$admin_email         = $this->settings->get_params( 'result', 'admin_email' );
			$mailer              = WC()->mailer();
			$email               = new WC_Email();
			$headers             = "Content-Type: text/html\r\nReply-to: {$email->get_from_name()} <{$email->get_from_address()}>\r\n";
			$admin_email_address = $admin_email['address'] ? $admin_email['address'] : $email->get_from_address();
			$admin_email_content = $email->style_inline( $mailer->wrap_message( 'No prize left to spin', sprintf( wp_kses_post( 'All prizes of WooCommerce Lucky Wheel have been won. Please go to <a target="_blank" href="%s">WooCommerce Lucky Wheel settings</a> to config the wheel.' ), esc_url_raw( admin_url( 'admin.php?page=woocommerce-lucky-wheel#/wheel' ) ) ) ) );
			$admin_email_content = wp_kses_post( do_shortcode( $admin_email_content ) );
			$email->send( $admin_email_address, 'WooCommerce Lucky Wheel alert', $admin_email_content, $headers, array() );
		}
	}

	protected function rand() {
		if ( $this->characters_array === null ) {
			$this->characters_array = array_merge( range( 0, 9 ), range( 'a', 'z' ) );
		}
		if ( apply_filters( 'wlwl_only_number_generate_coupon', false ) ) {
			$this->characters_array = apply_filters( 'wlwl_number_generate_coupon', range( 0, 9 ) );
		}

		$rand = wp_rand( 0, count( $this->characters_array ) - 1 );

		return $this->characters_array[ $rand ];
	}

	protected function create_code( $prefix ) {
		$code         = $prefix;
		$max_num_code = absint( apply_filters( 'wlwl_max_num_coupon_code', 7 ) );
		for ( $i = 0; $i < $max_num_code; $i ++ ) {
			$code .= $this->rand();
		}
		$args      = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'title'          => $code,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			wp_reset_postdata();
			$code = $this->create_code( $prefix );
		}
		wp_reset_postdata();

		return $code;
	}

	/**
	 * @param $coupon_type
	 * @param $coupon_amount
	 *
	 * @return WC_Coupon
	 */
	public function create_coupon( $coupon_type, $coupon_amount ) {
		//Create coupon
		$code         = $this->create_code( $this->settings->get_params( 'coupon', 'coupon_code_prefix' ) );
		$coupon       = new WC_Coupon( $code );
		$today        = strtotime( date( 'Ymd' ) );// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$expire_date  = $this->settings->get_params( 'coupon', 'expiry_date' );
		$date_expires = $expire_date ? ( ( $expire_date + 1 ) * DAY_IN_SECONDS + $today ) : '';
		$coupon->set_amount( $coupon_amount );
		$coupon->set_date_expires( $date_expires );
		$coupon->set_discount_type( $coupon_type );
		$coupon->set_individual_use( $this->settings->get_params( 'coupon', 'individual_use' ) === 'yes' ? 1 : 0 );
		$product_ids = $this->settings->get_params( 'coupon', 'product_ids' );
		if ( $product_ids ) {
			$coupon->set_product_ids( $product_ids );
		}
		$exclude_product_ids = $this->settings->get_params( 'coupon', 'exclude_product_ids' );
		if ( $exclude_product_ids ) {
			$coupon->set_excluded_product_ids( $exclude_product_ids );
		}
		$coupon->set_usage_limit( $this->settings->get_params( 'coupon', 'limit_per_coupon' ) );
		$coupon->set_usage_limit_per_user( $this->settings->get_params( 'coupon', 'limit_per_user' ) );
		$coupon->set_limit_usage_to_x_items( $this->settings->get_params( 'coupon', 'limit_to_x_items' ) );
		$coupon->set_free_shipping( $this->settings->get_params( 'coupon', 'allow_free_shipping' ) === 'yes' ? 1 : 0 );
		$coupon->set_product_categories( $this->settings->get_params( 'coupon', 'product_categories' ) );
		$coupon->set_excluded_product_categories( $this->settings->get_params( 'coupon', 'exclude_product_categories' ) );
		$coupon->set_exclude_sale_items( $this->settings->get_params( 'coupon', 'exclude_sale_items' ) === 'yes' ? 1 : 0 );
		$min_spend = $this->settings->get_params( 'coupon', 'min_spend' );
		if ( $min_spend == 0 ) {
			$min_spend = '';
		}
		$coupon->set_minimum_amount( $min_spend );
		$max_spend = $this->settings->get_params( 'coupon', 'max_spend' );
		if ( $max_spend == 0 ) {
			$max_spend = '';
		}
		$coupon->set_maximum_amount( $max_spend );
		$coupon->save();
		update_post_meta( $coupon->get_id(), 'wlwl_unique_coupon', 'yes' );

		return $coupon;
	}

	/**
	 * @param $post_id
	 * @param $wheel_label
	 *
	 * @return bool|WC_Coupon
	 */
	public function create_dynamic_coupon( $post_id, &$wheel_label ) {
		$post = VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Wheel_Prize::get( $post_id );
		if ( $post ) {
			$wheel_label  = str_replace( '{wheel_prize_title}', $post->post_title, $wheel_label );
			$code         = $this->create_code( get_post_meta( $post_id, 'coupon_code_prefix', true ) );
			$coupon       = new WC_Coupon( $code );
			$today        = strtotime( date( 'Ymd' ) );// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$expire_date  = absint( get_post_meta( $post_id, 'expiry_date', true ) );
			$date_expires = $expire_date ? ( ( $expire_date + 1 ) * DAY_IN_SECONDS + $today ) : '';
			$coupon->set_amount( get_post_meta( $post_id, 'coupon_amount', true ) );
			$coupon->set_date_expires( $date_expires );
			$coupon->set_discount_type( get_post_meta( $post_id, 'coupon_type', true ) );
			$coupon->set_individual_use( get_post_meta( $post_id, 'individual_use', true ) );
			$product_ids = get_post_meta( $post_id, 'product_ids', true );
			if ( $product_ids ) {
				$coupon->set_product_ids( $product_ids );
			}
			$exclude_product_ids = get_post_meta( $post_id, 'exclude_product_ids', true );
			if ( $exclude_product_ids ) {
				$coupon->set_excluded_product_ids( $exclude_product_ids );
			}
			$coupon->set_usage_limit( get_post_meta( $post_id, 'limit_per_coupon', true ) );
			$coupon->set_usage_limit_per_user( get_post_meta( $post_id, 'limit_per_user', true ) );
			$coupon->set_limit_usage_to_x_items( get_post_meta( $post_id, 'limit_to_x_items', true ) );
			$coupon->set_free_shipping( get_post_meta( $post_id, 'allow_free_shipping', true ) );
			$coupon->set_product_categories( get_post_meta( $post_id, 'product_categories', true ) );
			$coupon->set_excluded_product_categories( get_post_meta( $post_id, 'exclude_product_categories', true ) );
			$coupon->set_exclude_sale_items( get_post_meta( $post_id, 'exclude_sale_items', true ) );
			$min_spend = get_post_meta( $post_id, 'min_spend', true );
			if ( $min_spend == 0 ) {
				$min_spend = '';
			}
			$coupon->set_minimum_amount( $min_spend );
			$max_spend = get_post_meta( $post_id, 'max_spend', true );
			if ( $max_spend == 0 ) {
				$max_spend = '';
			}
			$coupon->set_maximum_amount( $max_spend );
			$coupon->save();
			update_post_meta( $coupon->get_id(), 'wlwl_unique_coupon', 'yes' );

			return $coupon;
		} else {
			return false;
		}
	}


	public static function bubbles_html( $images ) {
		?>
        <div class="wlwl-background-effect-floating-bubbles" aria-hidden="true">
			<?php
			for ( $i = 1; $i <= 16; $i ++ ) {
				?>
                <div class="wlwl-bubble <?php echo esc_attr( "wlwl-bubble-x{$i}" ) ?>"><img
                            src="<?php echo esc_url( VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES . 'falling-snow/' . ( isset( $images[ $i ] ) ? $images[ $i ] : $images[ wp_rand( 0, count( $images ) - 1 ) ] ) . '.png' ) ?>">
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}

	public static function snowflake_html() {
		?>
        <div class="wlwl-background-effect-snowflakes" aria-hidden="true">
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❅
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❆
            </div>
            <div class="wlwl-background-effect-snowflake">
                ❄
            </div>
        </div>
		<?php
	}

	public static function snowflake_1_html() {
		?>
        <div class="wlwl-background-effect-snowflakes-1" aria-hidden="true">
			<?php
			for ( $i = 0; $i < 42; $i ++ ) {
				?>
                <span></span>
				<?php
			}
			?>
        </div>
		<?php
	}

	public static function get_result( $wheel ) {
		$weigh       = $wheel['probability']?? [];
		$probability = array_sum( $weigh );
		for ( $i = 1; $i < count( $weigh ); $i ++ ) {
			$weigh[ $i ] += $weigh[ $i - 1 ];
		}
		for ( $i = 0; $i < count( $weigh ); $i ++ ) {
			if ( $wheel['probability'][ $i ] == 0 ) {
				$weigh[ $i ] = 0;
			}
		}
		$random = wp_rand( 1, $probability );
		$stop   = 0;
		foreach ( $weigh as $v ) {
			if ( $random <= $v ) {
				break;
			}
			$stop ++;
		}

		return $stop;
	}
}