<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Settings {
	protected $settings;
	protected $next_schedule;
	protected $updated_sucessfully, $error;
	public function __construct() {
		$this->settings         = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->next_schedule = 0;
		add_action( 'admin_init', array( $this, 'check_update' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ),11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'wlwl_reset_total_spins', array( $this, 'reset_total_spins' ) );
		add_action( 'wp_ajax_wlwl_search_coupon', array( $this, 'search_coupon' ) );
		add_action( 'wp_ajax_wlwl_search_cate', array( $this, 'search_cate' ) );
		add_action( 'wp_ajax_wlwl_search_product', array( $this, 'search_product' ) );
		add_action( 'wp_ajax_wlwl_search_suggested_product', array( $this, 'search_suggested_product' ) );
		add_action( 'wp_ajax_wlwl_preview_emails', array( $this, 'preview_emails_ajax' ) );
		add_action( 'wp_ajax_wlwl_preview_wheel', array( $this, 'preview_wheel_ajax' ) );
		add_action( 'media_buttons', array( $this, 'preview_emails_button' ) );
		add_action( 'admin_footer', array( $this, 'preview_emails_html' ) );
	}
	public function reset_total_spins() {
		$args        = array(
			'numberposts' => - 1,
			'post_type'   => 'wlwl_email',
			'fields'      => 'ids'
		);
		$posts_array = get_posts( $args );
		foreach ( $posts_array as $post_id ) {
			$current_spin_meta = get_post_meta( $post_id, 'wlwl_spin_times', true );
			if ( ! isset( $current_spin_meta['total_spins'] ) ) {
				$current_spin_meta['total_spins'] = absint( $current_spin_meta['spin_num'] );
			}
			$current_spin_meta['spin_num'] = 0;
			update_post_meta( $post_id, 'wlwl_spin_times', $current_spin_meta );
		}
	}
    
	public function cron_schedules( $schedules ) {
		$schedules['wlwl_reset_total_spins_interval'] = array(
			'interval' => 86400 * absint( $this->settings->get_params( 'reset_spins_interval' ) ),
			'display'  => esc_html__( 'Reset total spins', 'woocommerce-lucky-wheel' ),
		);
		return $schedules;
	}
	public function unschedule_event() {
		if ( $this->next_schedule ) {
			wp_unschedule_hook( 'wlwl_reset_total_spins' );
			$this->next_schedule = '';
		}
	}
	public function preview_emails_html() {
		if (isset( $_REQUEST['page'] ) && wc_clean(wp_unslash($_REQUEST['page'])) === 'woocommerce-lucky-wheel' ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
            <div class="preview-emails-html-container preview-html-hidden">
                <div class="preview-emails-html-overlay"></div>
                <div class="preview-emails-html"></div>
            </div>
			<?php
		}
	}
	public function preview_emails_button( $editor_id ) {
		if ( isset( $_REQUEST['page'] ) && wc_clean(wp_unslash($_REQUEST['page'])) === 'woocommerce-lucky-wheel' ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$editor_ids = apply_filters('wlwl_preview_emails_button_ids',array( 'content' ));
			if ( in_array( $editor_id, $editor_ids ) ) {
				ob_start();
				?>
                <span class="button wlwl-preview-emails-button"
                      data-wlwl_language="<?php echo esc_attr( str_replace( 'content', '', $editor_id ) ) ?>"><?php esc_html_e( 'Preview emails', 'woocommerce-lucky-wheel' ) ?></span>
				<?php
				echo ob_get_clean();// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}
	public function preview_wheel_ajax() {
		$label          = isset( $_GET['label'] ) ? wc_clean( $_GET['label'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$coupon_type    = isset( $_GET['coupon_type'] ) ? wc_clean( $_GET['coupon_type'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$prize_quantity = isset( $_GET['prize_quantity'] ) ? wc_clean( $_GET['prize_quantity'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quantity_label = isset( $_GET['quantity_label'] ) ? wc_clean( $_GET['quantity_label'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$coupon_amount  = isset( $_GET['coupon_amount'] ) ? wc_clean( $_GET['coupon_amount'] ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$labels         = array();
		if ( is_array( $label ) && !empty( $label ) ) {
			for ( $i = 0; $i < count( $label ); $i ++ ) {
				$wheel_label      = stripslashes( $label[ $i ] );
				$quantity_label_1 = '';
				if ( $coupon_type[ $i ] !== 'non' ) {
					if ( $prize_quantity[ $i ] > 0 ) {
						$quantity_label_1 = str_replace( '{prize_quantity}', $prize_quantity[ $i ], $quantity_label );
					}
					switch ( $coupon_type[ $i ] ) {
						case 'custom':
						case 'existing_coupon':
							break;
						case 'percent':
							$wheel_label = str_replace( '{coupon_amount}', $coupon_amount[ $i ] . '%', $wheel_label );
							break;
						case 'fixed_cart':
						case 'fixed_product':
							$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( $coupon_amount[ $i ] ), $wheel_label );
							$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
							break;
						default:
							$post = get_post( $coupon_type[ $i ] );
							if ( $post && $post->post_status === 'publish' ) {
								$wheel_label = str_replace( '{wheel_prize_title}', $post->post_title, $wheel_label );
								if ( get_post_meta( $coupon_type[ $i ], 'coupon_type', true ) === 'percent' ) {
									$wheel_label = str_replace( '{coupon_amount}', get_post_meta( $coupon_type[ $i ], 'coupon_amount', true ) . '%', $wheel_label );
								} else {
									$wheel_label = str_replace( '{coupon_amount}', $this->wc_price( get_post_meta( $coupon_type[ $i ], 'coupon_amount', true ) ), $wheel_label );
									$wheel_label = str_replace( '&nbsp;', ' ', $wheel_label );
								}
							} else {
								$wheel_label = esc_html__( 'Coupon not exists, please select an other one.', 'woocommerce-lucky-wheel' );
							}
					}
				}
				$wheel_label = str_replace( '{quantity_label}', $quantity_label_1, $wheel_label );
				$wheel_label = str_replace( array( '{coupon_amount}', '{wheel_prize_title}' ), '', $wheel_label );
				$labels[]    = $wheel_label;
			}
		}
		wp_send_json( array( 'labels' => $labels ) );
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
		if ( isset( $this->settings->get_params( 'wheel' )['currency'] ) && $this->settings->get_params( 'wheel' )['currency'] == 'code' ) {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, ( $currency ), $price );
		} else {
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( $price_format, wlwl_get_currency_symbol( $currency ), $price );
		}

		return $formatted_price;
	}
	public function preview_emails_ajax() {
		$content              = isset( $_GET['content'] ) ? wp_kses_post( stripslashes( $_GET['content'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email_heading        = isset( $_GET['heading'] ) ? wc_clean( stripslashes( $_GET['heading'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->footer_text    = isset( $_GET['footer_text'] ) ? wc_clean( stripslashes( $_GET['footer_text'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$button_shop_url      = isset( $_GET['button_shop_url'] ) ? wc_clean( stripslashes( $_GET['button_shop_url'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$button_shop_size     = isset( $_GET['button_shop_size'] ) ? wc_clean( stripslashes( $_GET['button_shop_size'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$button_shop_color    = isset( $_GET['button_shop_color'] ) ? wc_clean( stripslashes( $_GET['button_shop_color'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$button_shop_bg_color = isset( $_GET['button_shop_bg_color'] ) ? wc_clean( stripslashes( $_GET['button_shop_bg_color'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$button_shop_title    = isset( $_GET['button_shop_title'] ) ? wc_clean( stripslashes( $_GET['button_shop_title'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$suggested_products   = isset( $_GET['suggested_products'] ) ? wc_clean( array_map( 'stripslashes', ( $_GET['suggested_products'] ) ) ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$button_shop_now        = '<a href="' . esc_url( $button_shop_url ) . '" target="_blank" style="text-decoration:none;display:inline-block;padding:10px 30px;margin:10px 0;font-size:' . esc_attr( $button_shop_size ) . 'px;color:' . esc_attr( $button_shop_color ) . ';background:' . esc_attr( $button_shop_bg_color ) . ';">' . esc_html( $button_shop_title ) . '</a>';
		$coupon_label           = '10% OFF';
		$coupon_code            = 'LUCKY_WHEEL';
		$date_expires           = strtotime( '+30 days' );
		$customer_name          = 'John';
		$content                = str_replace( '{coupon_label}', $coupon_label, $content );
		$content                = str_replace( '{customer_name}', $customer_name, $content );
		$content                = str_replace( '{coupon_code}', '<span style="font-size: x-large;">' . strtoupper( $coupon_code ) . '</span>', $content );
		$content                = str_replace( '{date_expires}', empty( $date_expires ) ? esc_html__( 'never expires', 'woocommerce-lucky-wheel' ) : date_i18n( 'F d, Y', ( $date_expires ) ), $content );
		$content                = str_replace( '{shop_now}', $button_shop_now, $content );
		$featured_products      = wc_get_featured_product_ids();
		$featured_products_html = '';
		if ( is_array( $featured_products ) && !empty( $featured_products ) ) {
			$featured_products_html = '<table style="width: 100%;">';
			foreach ( $featured_products as $p ) {
				$product                = wc_get_product( $p );
				$featured_products_html .= '<tr><td style="text-align: center;"><a href="' . esc_url( $product->get_permalink() ) . '" target="_blank"><img style="width: 150px;" src="' . wp_get_attachment_thumb_url( $product->get_image_id() ) . '"></a></td><td><p>' . $product->get_title() . '</p><p>' . $product->get_price_html() . '</p><a target="_blank" style="text-align: center;font-size:' . $button_shop_size . 'px; background-color: ' . ( $button_shop_bg_color ) . ';color: ' . ( $button_shop_color ) . ';padding: 10px;text-decoration: none;" href="' . $product->get_permalink() . '" >' . $button_shop_title . '</a></td></tr>';
			}
			$featured_products_html .= '</table>';
		}
		$content = str_replace( '{featured_products}', $featured_products_html, $content );
		if ( is_array( $suggested_products ) && !empty( $suggested_products ) ) {
			$content .= '<table style="width: 100%;">';
			foreach ( $suggested_products as $suggested_product ) {
				$product = wc_get_product( $suggested_product );
				if ( $product->get_parent_id() ) {
					continue;
				}
				$content .= '<tr><td style="text-align: center;"><a href="' . esc_url( $product->get_permalink() ) . '" target="_blank"><img style="width: 150px;" src="' . wp_get_attachment_thumb_url( $product->get_image_id() ) . '"></a></td><td><p>' . esc_html( $product->get_title() ) . '</p><p>' . wp_kses_post( $product->get_price_html() ) . '</p><a target="_blank" style="text-align: center;font-size:' . esc_html( $button_shop_size ) . 'px; background-color: ' . esc_html( $button_shop_bg_color ) . ';color: ' . esc_html( $button_shop_color ) . ';padding: 10px;text-decoration: none;" href="' . esc_html( $product->get_permalink() ) . '" >' . esc_html( $button_shop_title ) . '</a></td></tr>';
			}
			$content .= '</table>';
		}
		$email_heading = str_replace( '{coupon_label}', $coupon_label, $email_heading );

		// load the mailer class
		$mailer = WC()->mailer();

		// create a new email
		$email             = new WC_Email();
		$this->footer_text = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::replace_placeholders( $this->footer_text );
		add_filter( 'woocommerce_email_footer_text', array( $this, 'woocommerce_email_footer_text' ) );
		// wrap the content with the email template and then add styles
		$message = apply_filters( 'woocommerce_mail_content', $email->style_inline( $mailer->wrap_message( $email_heading, $content ) ) );
		remove_filter( 'woocommerce_email_footer_text', array( $this, 'woocommerce_email_footer_text' ) );
		// print the preview email
		wp_send_json(
			array(
				'html' => $message,
			)
		);
	}

	public function woocommerce_email_footer_text( $footer_text ) {
		$footer_text = $this->footer_text;

		return $footer_text;
	}
	public function search_suggested_product() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		ob_start();

		$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';

		if ( empty( $keyword ) ) {
			die();
		}
		$arg            = array(
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'posts_per_page' => 50,
			's'              => $keyword

		);
		$the_query      = new WP_Query( $arg );
		$found_products = array();
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();

				$product_id    = get_the_ID();
				$product_title = get_the_title();
				$the_product   = new WC_Product( $product_id );
				if ( ! $the_product->is_in_stock() ) {
					$product_title .= ' (out-of-stock)';
				}
				$product          = array( 'id' => $product_id, 'text' => $product_title );
				$found_products[] = $product;
			}
		}
		wp_send_json( $found_products );
	}
	public function search_product() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
		if ( empty( $keyword ) ) {
			die();
		}
		$arg            = array(
			'post_status'    => 'publish',
			'post_type'      => 'product',
			'posts_per_page' => 50,
			's'              => $keyword
		);
		$the_query      = new WP_Query( $arg );
		$found_products = array();
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$prd = wc_get_product( get_the_ID() );
				if ( $prd->has_child() && $prd->is_type( 'variable' ) ) {
					$product_children = $prd->get_children();
					if ( !empty( $product_children ) ) {
						foreach ( $product_children as $product_child ) {
							if ( woocommerce_version_check() ) {
								$product = array(
									'id'   => $product_child,
									'text' => get_the_title( $product_child )
								);
							} else {
								$child_wc  = wc_get_product( $product_child );
								$get_atts  = $child_wc->get_variation_attributes();
								$attr_name = array_values( $get_atts )[0];
								$product   = array(
									'id'   => $product_child,
									'text' => get_the_title() . ' - ' . $attr_name
								);
							}
							$found_products[] = $product;
						}
					}
				} else {
					$product          = array( 'id' => get_the_ID(), 'text' => get_the_title() );
					$found_products[] = $product;
				}
			}
		}
		wp_send_json( $found_products );
	}
	public function search_cate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		ob_start();

		$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
		if ( ! $keyword ) {
			$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
		}
		if ( empty( $keyword ) ) {
			die();
		}
		$categories = get_terms(
			array(
				'taxonomy' => 'product_cat',
				'orderby'  => 'name',
				'order'    => 'ASC',
				'search'   => $keyword,
				'number'   => 100
			)
		);
		$items      = array();
		if ( !empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->term_id,
					'text' => $category->name
				);
				$items[] = $item;
			}
		}
		wp_send_json( $items );
	}
	public function search_coupon() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		ob_start();
		$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
		if ( empty( $keyword ) ) {
			die();
		}
		$arg            = array(
			'post_status'    => 'publish',
			'post_type'      => 'shop_coupon',
			'posts_per_page' => 50,
			'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => 'wlwl_unique_coupon',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key'     => 'kt_unique_coupon',
					'compare' => 'NOT EXISTS'
				)
			),
			's'              => $keyword
		);
		$the_query      = new WP_Query( $arg );
		$found_products = array();
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$coupon = new WC_Coupon( get_the_ID() );
				if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
					continue;
				}
				if ( $coupon->get_date_expires() && time() > $coupon->get_date_expires()->getTimestamp() ) {
					continue;
				}
				$product          = array( 'id' => get_the_ID(), 'text' => get_the_title() );
				$found_products[] = $product;
			}
		}
		wp_send_json( $found_products );
	}
	protected function general_options() {
		$args       = [
			'wlwl_enable'        => [
				'type'  => 'checkbox',
				'html'  => sprintf( '<div class="vi-ui toggle checkbox">
                                    <input type="checkbox" name="wlwl_enable" id="wlwl_enable" value="on" %s >
                                    <label></label>
                                </div>', $this->settings->get_params( 'general', 'enable' ) == 'on' ? ' checked' : '' ),
				'title' => esc_html__( 'Enable', 'woocommerce-lucky-wheel' ),
			],
			'wlwl_enable_mobile' => [
				'type'  => 'checkbox',
				'html'  => sprintf( '<div class="vi-ui toggle checkbox">
                                    <input type="checkbox" name="wlwl_enable_mobile" id="wlwl_enable_mobile" %s>
                                    <label></label>
                                </div>', $this->settings->get_params( 'general', 'mobile' ) == 'on' ? ' checked' : '' ),
				'desc'  => esc_html__( 'Allow to display wheel for screen less than 760px', 'woocommerce-lucky-wheel' ),
				'title' => esc_html__( 'Small screen', 'woocommerce-lucky-wheel' ),
			],
			'ajax_endpoint'       => [
				'type'    => 'select',
				'value'   => $this->settings->get_params( 'ajax_endpoint' ),
				'options' => [
					'ajax'     => esc_html__( 'Ajax', 'woocommerce-lucky-wheel' ),
					'rest_api' => esc_html__( 'Ajax endpoint', 'woocommerce-lucky-wheel' ),
				],
				'title'   => esc_html__( 'Ajax endpoint', 'woocommerce-lucky-wheel' ),
			],
			'wlwl_spin_num'      => [
				'type'  => 'input',
				'html'  => sprintf( '<input type="number" id="wlwl_spin_num" name="wlwl_spin_num" min="1"
                                       value="%s">', esc_attr( $this->settings->get_params( 'general', 'spin_num' ) ) ),
				'desc'  => __( 'Leave empty to not set the limit.', 'woocommerce-lucky-wheel' ),
				'title' => __( 'The number of spins per email', 'woocommerce-lucky-wheel' ),
			],
			'wlwl_delay'         => [
				'type'  => 'input',
				'title' => esc_html__( 'Gap between 2 spins', 'woocommerce-lucky-wheel' ),
			],
		];
		$delay_unit = $this->settings->get_params( 'general', 'delay_unit' );
		ob_start();
		?>
        <div class="vi-ui right labeled fluid input">
            <input type="number" id="wlwl_delay" name="wlwl_delay"
                   min="0"
                   value="<?php echo esc_attr( $this->settings->get_params( 'general', 'delay' ) ); ?>">
            <select name="wlwl_delay_unit" class="vi-ui dropdown label">
                <option value="s" <?php selected( $delay_unit, 's' ) ?>>
					<?php esc_html_e( 'Seconds', 'woocommerce-lucky-wheel' ); ?>
                </option>
                <option value="m" <?php selected( $delay_unit, 'm' ) ?>><?php esc_html_e( 'Minutes', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="h" <?php selected( $delay_unit, 'h' ) ?>><?php esc_html_e( 'Hours', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="d" <?php selected( $delay_unit, 'd' ) ?>><?php esc_html_e( 'Days', 'woocommerce-lucky-wheel' ); ?></option>
            </select>
        </div>
        <p class="description"><?php esc_html_e( 'Gap time between 2 consecutive spins of an email', 'woocommerce-lucky-wheel' ) ?></p>
		<?php
		$args['wlwl_delay']['html']   = ob_get_clean();
		$choose_using_white_black_list = $this->settings->get_params( 'choose_using_white_black_list' );
		ob_start();
		?>
        <div class="equal width fields">
            <div class="field">
                <div class="vi-ui toggle checkbox">
                    <input type="radio" name="choose_using_white_black_list"
                           id="choose_using_white_list"
                           value="white_list" <?php checked( $choose_using_white_black_list, 'white_list' ) ?>>
                    <label for="choose_using_white_list"><?php esc_html_e( 'White List', 'woocommerce-lucky-wheel' ); ?></label>
                </div>
                <p class="description"><?php esc_html_e( 'Only emails on the list below will be eligible for spinning.', 'woocommerce-lucky-wheel' ); ?></p></div>
            <div class="field">
                <div class="vi-ui toggle checkbox">
                    <input type="radio" name="choose_using_white_black_list"
                           id="choose_using_black_list"
                           value="black_list" <?php checked( $choose_using_white_black_list, 'black_list' ) ?>>
                    <label for="choose_using_black_list"><?php esc_html_e( 'Black List', 'woocommerce-lucky-wheel' ); ?></label>
                </div>
                <p class="description"><?php esc_html_e( 'Emails on the list below will be excluded from spinning, while all other emails will be eligible for spinning', 'woocommerce-lucky-wheel' ); ?></p>
            </div>
        </div>
		<?php
		$args['choose_using_white_list'] = [
			'title' => esc_html__( 'Choose using white/black list', 'woocommerce-lucky-wheel' ),
			'html'  => ob_get_clean(),
		];
		$args['white_list']              = [
			'title' => esc_html__( 'White list', 'woocommerce-lucky-wheel' ),
			'desc'  => sprintf( '<p class="description">%s</p>
                                <p class="description">%s</p>',
				esc_html__( 'Enter domains to this list, each domain per line, leave empty if not use this feature', 'woocommerce-lucky-wheel' ),
				esc_html__( 'Ex: abc@email.com is "email.com"', 'woocommerce-lucky-wheel' ) ),
			'html'  => sprintf( '<textarea name="white_list">%s</textarea>', $this->settings->get_params( 'white_list' ) ),
		];
		$args['black_list']              = [
			'title' => esc_html__( 'Black list', 'woocommerce-lucky-wheel' ),
			'desc'  => sprintf( '<p class="description">%s</p>
                                <p class="description">%s</p>',
				esc_html__( 'Enter domains to this list, each domain per line, leave empty if not use this feature', 'woocommerce-lucky-wheel' ),
				esc_html__( 'Ex: abc@email.com is "email.com"', 'woocommerce-lucky-wheel' ) ),
			'html'  => sprintf( '<textarea name="black_list">%s</textarea>', $this->settings->get_params( 'black_list' ) ),
		];
		$fields                          = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => $args,
		];
		$this->settings::villatheme_render_table_field( $fields );
		?>
        <div class="vi-ui <?php echo esc_attr( $this->next_schedule ? 'positive' : 'negative' ); ?> message">
            <div class="header">
				<?php esc_html_e( 'Auto reset spins: Reset the total spins of every email to zero at a specific time', 'woocommerce-lucky-wheel' );; ?>
            </div>
			<?php
			if ( $this->next_schedule ) {
				$gmt_offset = intval( get_option( 'gmt_offset' ) );
				echo wp_kses( sprintf( 'Next schedule: <strong>%s</strong>', date_i18n( 'F j, Y g:i:s A', ( $this->next_schedule + HOUR_IN_SECONDS * $gmt_offset ) ) ), $this->settings::filter_allowed_html() );
			} else {
				esc_html_e( 'This function is currently DISABLED', 'woocommerce-lucky-wheel' );
			}
			?>
        </div>
		<?php
		$args   = [
			'reset_spins_interval' => [
				'title' => esc_html__( 'Reset total spins every', 'woocommerce-lucky-wheel' ),
				'desc'  => esc_html__( 'Left zero to disable this function', 'woocommerce-lucky-wheel' ),
				'html'  => sprintf( '<div class="vi-ui right labeled input">
                                    <input type="number" min="0" name="reset_spins_interval"
                                           value="%s">
                                    <label class="vi-ui label">%s</label>
                                </div>', esc_attr( $this->settings->get_params( 'reset_spins_interval' ) ), esc_html__( 'Day(s)', 'woocommerce-lucky-wheel' ) ),
			],
			'reset_spins_hour'     => [
				'title' => esc_html__( 'Run reset function at', 'woocommerce-lucky-wheel' ),
				'html'  => sprintf( '<div class="equal width fields">
                                    <div class="field">
                                        <div class="vi-ui right labeled input">
                                            <input type="number" min="0" max="23" name="reset_spins_hour"
                                                   value="%s">
                                            <label class="vi-ui label">%s</label>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <div class="vi-ui right labeled input">
                                            <input type="number" min="0" max="59" name="reset_spins_minute"
                                                   value="%s">
                                            <label  class="vi-ui label">%s</label>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <div class="vi-ui right labeled input">
                                            <input type="number" min="0" max="59" name="reset_spins_second"
                                                   value="%s">
                                            <label  class="vi-ui label">%s</label>
                                        </div>
                                    </div>
                                </div>', esc_attr( $this->settings->get_params( 'reset_spins_hour' ) ),
					esc_html__( 'Hour', 'woocommerce-lucky-wheel' ),
					esc_attr( $this->settings->get_params( 'reset_spins_minute' ) ),
					esc_html__( 'Minute', 'woocommerce-lucky-wheel' ),
					esc_attr( $this->settings->get_params( 'reset_spins_second' ) ),
					esc_html__( 'Second', 'woocommerce-lucky-wheel' ) ),
			]
		];
		$fields = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => $args,
		];
		$this->settings::villatheme_render_table_field( $fields );
		return '';
	}
    protected function popup_options() {
	    ?>
        <div class="vi-ui secondary pointing tabular attached top menu">
            <div class="active item" data-tab="popup_general">
			    <?php esc_html_e( 'General', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item" data-tab="popup_icon">
			    <?php esc_html_e( 'Icon Design', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item" data-tab="popup_assign">
			    <?php esc_html_e( 'Assign Page', 'woocommerce-lucky-wheel' ) ?>
            </div>
        </div>
	    <?php
	    $notify_intent = $this->settings->get_params( 'notify', 'intent' );
	    ob_start();
	    ?>
        <select name="notify_intent" class="vi-ui fluid dropdown">
            <option value="popup_icon" <?php selected( $notify_intent, 'popup_icon' ) ?>><?php esc_html_e( 'Popup icon', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="show_wheel" <?php selected( $notify_intent, 'show_wheel' ) ?>><?php esc_html_e( 'Automatically show wheel after initial time', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="on_scroll" <?php selected( $notify_intent, 'on_scroll' ) ?>><?php esc_html_e( 'Show wheel after users scroll down a specific value', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="on_exit" <?php selected( $notify_intent, 'on_exit' ) ?>><?php esc_html_e( 'Show wheel when users move mouse over the top to close browser', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="random" <?php selected( $notify_intent, 'random' ) ?>><?php esc_html_e( 'Random one of these above', 'woocommerce-lucky-wheel' ); ?></option>
        </select>
	    <?php
	    $notify_intent_html = ob_get_clean();
	    $time_on_close_unit = $this->settings->get_params( 'notify', 'time_on_close_unit' );
	    ob_start();
	    ?>
        <div class="vi-ui right labeled fluid input">
            <input type="number" id="notify_time_on_close" name="notify_time_on_close"
                   min="0"
                   value="<?php echo esc_attr( $this->settings->get_params( 'notify', 'time_on_close' ) ); ?>">
            <select name="notify_time_on_close_unit" class="vi-ui label dropdown">
                <option value="m" <?php selected( $time_on_close_unit, 'm' ) ?>><?php esc_html_e( 'Minutes', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="h" <?php selected( $time_on_close_unit, 'h' ) ?>><?php esc_html_e( 'Hours', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="d" <?php selected( $time_on_close_unit, 'd' ) ?>><?php esc_html_e( 'Days', 'woocommerce-lucky-wheel' ); ?></option>
            </select>
        </div>
	    <?php
	    $notify_time_on_close_html = ob_get_clean();
	    $show_again_unit           = $this->settings->get_params( 'notify', 'show_again_unit' );
	    ob_start();
	    ?>
        <div class="vi-ui right labeled fluid input">
            <input type="number" id="notify_show_again" name="notify_show_again"
                   min="0"
                   value="<?php echo esc_attr( $this->settings->get_params( 'notify', 'show_again' ) ); ?>">
            <select name="notify_show_again_unit" class="vi-ui label dropdown">
                <option value="s" <?php selected( $show_again_unit, 's' ) ?>><?php esc_html_e( 'Seconds', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="m" <?php selected( $show_again_unit, 'm' ) ?>><?php esc_html_e( 'Minutes', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="h" <?php selected( $show_again_unit, 'h' ) ?>><?php esc_html_e( 'Hours', 'woocommerce-lucky-wheel' ); ?></option>
                <option value="d" <?php selected( $show_again_unit, 'd' ) ?>><?php esc_html_e( 'Days', 'woocommerce-lucky-wheel' ); ?></option>
            </select>
        </div>
	    <?php
	    $notify_show_again_html = ob_get_clean();
	    $args                   = [
		    'notify_intent'        => [
			    'title' => esc_html__( 'Action required to open the popup', 'woocommerce-lucky-wheel' ),
			    'html'  => $notify_intent_html,
		    ],
		    'show_wheel'           => [
			    'title' => esc_html__( 'Initial time', 'woocommerce-lucky-wheel' ),
			    'desc'  => esc_html__( 'Gap time before the popup icon appears after the action to trigger is done. This gap time is selected randomly within the range you add. Enter min,max time (seconds). For example: 1,2', 'woocommerce-lucky-wheel' ),
			    'html'  => sprintf( '<div class="vi-ui right labeled input">
                                    <input type="text" id="show_wheel" name="show_wheel"
                                           value="%s">
                                    <label class="vi-ui label">%s</label>
                                </div>', esc_attr( $this->settings->get_params( 'notify', 'show_wheel' ) ),
				    esc_html__( 'Seconds', 'woocommerce-lucky-wheel' ) ),
		    ],
		    'scroll_amount'        => [
			    'title' => esc_html__( 'Scroll amount(%)', 'woocommerce-lucky-wheel' ),
			    'html'  => sprintf( '<input type="number" id="scroll_amount" name="scroll_amount"
                                       value="%s">', esc_attr( $this->settings->get_params( 'notify', 'scroll_amount' ) ) ),
		    ],
		    'notify_time_on_close' => [
			    'title' => esc_html__( 'If the wheel is closed without a spin, show the popup again after', 'woocommerce-lucky-wheel' ),
			    'html'  => $notify_time_on_close_html,
		    ],
		    'notify_show_again'    => [
			    'title' => esc_html__( 'After one spin, show the popup again after', 'woocommerce-lucky-wheel' ),
			    'html'  => $notify_show_again_html,
		    ],
	    ];
	    $fields                 = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'active'    => 1,
//			    'class'     => 'wlwl-popup-general-accordion',
//			    'title'     => esc_html__( 'Popup General', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields'        => $args,
	    ];
	    ?>
        <div class="vi-ui bottom attached active tab" data-tab="popup_general">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    $notify_position = $this->settings->get_params( 'notify', 'position' );
	    ob_start();
	    ?>
        <select name="notify_position" id="notify_position" class="vi-ui fluid dropdown">
            <option value="top-left" <?php selected( $notify_position, 'top-left' ) ?>><?php esc_html_e( 'Top Left', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="top-right" <?php selected( $notify_position, 'top-right' ) ?>><?php esc_html_e( 'Top Right', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="middle-left" <?php selected( $notify_position, 'middle-left' ) ?>><?php esc_html_e( 'Middle Left', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="middle-right" <?php selected( $notify_position, 'middle-right' ) ?>><?php esc_html_e( 'Middle Right', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="bottom-left" <?php selected( $notify_position, 'bottom-left' ) ?>><?php esc_html_e( 'Bottom Left', 'woocommerce-lucky-wheel' ); ?></option>
            <option value="bottom-right" <?php selected( $notify_position, 'bottom-right' ) ?>><?php esc_html_e( 'Bottom Right', 'woocommerce-lucky-wheel' ); ?></option>
        </select>
	    <?php
	    $notify_position_html = ob_get_clean();
	    $icons                = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_gift_icons();
	    $popup_icon           = $this->settings->get_params( 'notify', 'popup_icon' );
	    ob_start();
	    ?>
        <div class="vi-ui segment wheel-popup-icons-container">
		    <?php
		    foreach ( $icons as $icon ) {
			    ?>
                <span class="vi-ui button wheel-popup-icon <?php echo esc_attr( $popup_icon === $icon ? 'wheel-popup-icon-selected' : '' ); ?>"
                      data-wheel_popup_icon="<?php echo esc_attr( $icon ) ?>"><span
                            class="<?php echo esc_attr( VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_gift_icon_class( $icon ) ) ?>"></span></span>
			    <?php
		    }
		    ?>
            <input type="hidden" name="wheel_popup_icon"
                   value="<?php echo esc_attr( $popup_icon ); ?>">
        </div>
	    <?php
	    $popup_icon_html     = ob_get_clean();
	    $popup_icon_color    = $this->settings->get_params( 'notify', 'popup_icon_color' );
	    $popup_icon_bg_color = $this->settings->get_params( 'notify', 'popup_icon_bg_color' );
	    ob_start();
	    ?>
        <div class="equal width fields">
            <div class="field">
                <input type="text" class="color-picker" name="wheel_popup_icon_color"
                       id="wheel_popup_icon_color"
                       value="<?php if ( $popup_icon_color ) {
				           echo esc_attr( $popup_icon_color );
			           } ?>"
                       style="background-color:<?php if ( $popup_icon_color ) {
				           echo esc_attr( $popup_icon_color );
			           } ?>;">
                <p class="description"><?php esc_html_e( 'Color', 'woocommerce-lucky-wheel' ); ?></p>
            </div>
            <div class="field">
                <input type="text" class="color-picker" name="wheel_popup_icon_bg_color"
                       id="wheel_popup_icon_bg_color"
                       value="<?php if ( $popup_icon_bg_color ) {
				           echo esc_attr( $popup_icon_bg_color );
			           } ?>"
                       style="background-color:<?php if ( $popup_icon_bg_color ) {
				           echo esc_attr( $popup_icon_bg_color );
			           } ?>;">
                <p class="description"><?php esc_html_e( 'Background', 'woocommerce-lucky-wheel' ); ?></p>
            </div>
        </div>
	    <?php
	    $popup_icon_design_html = ob_get_clean();
	    ob_start();
	    ?>
        <div class="vi-ui toggle checkbox">
            <input type="checkbox" name="notify_hide_popup"
                   id="notify_hide_popup" <?php checked( $this->settings->get_params( 'notify', 'hide_popup' ), 'on' ) ?>>
            <label for="notify_hide_popup"></label>
        </div>
	    <?php
	    $popup_icon_hide_html = ob_get_clean();
	    $args                 = [
		    'notify_position'   => [
			    'title' => esc_html__( 'Popup icon position', 'woocommerce-lucky-wheel' ),
			    'desc'  => esc_html__( 'Position of the popup on screen', 'woocommerce-lucky-wheel' ),
			    'html'  => $notify_position_html,
		    ],
		    'popup_icon'        => [
			    'title' => esc_html__( 'Custom popup icon', 'woocommerce-lucky-wheel' ),
			    'desc'  => esc_html__( 'If no icon is selected, a small version of your real wheel will be used', 'woocommerce-lucky-wheel' ),
			    'html'  => $popup_icon_html,
		    ],
		    'popup_design'      => [
			    'title' => esc_html__( 'Custom popup icon design', 'woocommerce-lucky-wheel' ),
			    'html'  => $popup_icon_design_html,
		    ],
		    'notify_hide_popup' => [
			    'title' => esc_html__( 'Hide popup icon', 'woocommerce-lucky-wheel' ),
			    'desc'  => esc_html__( 'Enable to hide the popup icon after the user closes the wheel.', 'woocommerce-lucky-wheel' ),
			    'html'  => $popup_icon_hide_html,
		    ],
	    ];
	    $fields               = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-popup-icon-accordion',
//			    'title'     => esc_html__( 'Popup Icon', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields'        => $args,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="popup_icon">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    ob_start();
	    ?>
        <input type="text" name="notify_conditional_tags"
               placeholder="<?php esc_html_e( 'Ex: !is_page(array(123,41,20))', 'woocommerce-lucky-wheel' ) ?>"
               id="notify_conditional_tags"
               value="<?php if ( $this->settings->get_params( 'notify', 'conditional_tags' ) ) {
		           echo esc_attr( htmlentities( $this->settings->get_params( 'notify', 'conditional_tags' ) ) );
	           } ?>">
        <p class="description"><?php esc_html_e( 'Let you control on which pages Woocommerce Lucky wheel icon appears using ', 'woocommerce-lucky-wheel' ) ?>
            <a href="https://codex.wordpress.org/Conditional_Tags"><?php esc_html_e( 'WP\'s conditional tags', 'woocommerce-lucky-wheel' ) ?></a>
        </p>
        <p class="description">
            <strong>*</strong><?php esc_html_e( '"Home page", "Blog page" and "Shop page" options above must be disabled to run these conditional tags.', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description"><?php esc_html_e( 'Use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_cart()</strong><?php esc_html_e( ' to show only on cart page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description"><?php esc_html_e( 'Use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_checkout()</strong><?php esc_html_e( ' to show only on checkout page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description"><?php esc_html_e( 'Use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_product_category()</strong><?php esc_html_e( 'to show only on WooCommerce category page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description"><?php esc_html_e( 'Use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_shop()</strong><?php esc_html_e( ' to show only on WooCommerce shop page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description"><?php esc_html_e( 'Use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_product()</strong><?php esc_html_e( ' to show only on WooCommerce single product page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description">
            <strong>**</strong><?php esc_html_e( 'Combining 2 or more conditionals using || to show wheel if 1 of the conditionals matched. e.g use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>is_cart() ||
                is_checkout()</strong><?php esc_html_e( ' to show only on cart page and checkout page', 'woocommerce-lucky-wheel' ) ?>
        </p>
        <p class="description">
            <strong>***</strong><?php esc_html_e( 'Use exclamation mark(!) before a conditional to hide wheel if the conditional matched. e.g use ', 'woocommerce-lucky-wheel' ); ?>
            <strong>!is_home()</strong><?php esc_html_e( ' to hide wheel on homepage', 'woocommerce-lucky-wheel' ) ?>
        </p>
	    <?php
	    $notify_conditional_tags_html = ob_get_clean();
	    $args                         = [
		    'notify_frontpage_only'   => [
			    'title' => esc_html__( 'Show only on Homepage', 'woocommerce-lucky-wheel' ),
			    'html'  => sprintf( '<div class="vi-ui toggle checkbox">
                                    <input type="checkbox" name="notify_frontpage_only"
                                           id="notify_frontpage_only" %s>
                                    <label></label>
                                </div>', $this->settings->get_params( 'notify', 'show_only_front' ) == 'on' ? ' checked' : '' ),
		    ],
		    'notify_blogpage_only'    => [
			    'title' => esc_html__( 'Show only on Blog page', 'woocommerce-lucky-wheel' ),
			    'html'  => sprintf( '<div class="vi-ui toggle checkbox">
                                    <input type="checkbox" name="notify_blogpage_only"
                                           id="notify_blogpage_only" %s>
                                    <label></label>
                                </div>', $this->settings->get_params( 'notify', 'show_only_blog' ) == 'on' ? ' checked' : '' ),
		    ],
		    'notify_shop_only'    => [
			    'title' => esc_html__( 'Show only on Shop page', 'woocommerce-lucky-wheel' ),
			    'html'  => sprintf( '<div class="vi-ui toggle checkbox">
                                    <input type="checkbox" name="notify_shop_only"
                                           id="notify_shop_only" %s>
                                    <label></label>
                                </div>', $this->settings->get_params( 'notify', 'show_only_shop' ) == 'on' ? ' checked' : '' ),
                'desc' => __('Enable to make the popup icon only work on the Shop page', 'woocommerce-lucky-wheel' )
		    ],
		    'notify_conditional_tags' => [
			    'title' => esc_html__( 'Conditional tags', 'woocommerce-lucky-wheel' ),
			    'html'  => $notify_conditional_tags_html,
		    ],
	    ];
	    $fields                       = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-popup-assign-accordion',
//			    'title'     => esc_html__( 'Popup Assign', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields'        => $args,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="popup_assign">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    return '';
    }
    protected function wheel_options() {
	    ?>
        <div class="vi-ui secondary pointing tabular attached top menu">
            <div class="item" data-tab="wheel_fields">
			    <?php esc_html_e( 'Input fields', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item active" data-tab="wheel_sildes">
			    <?php esc_html_e( 'Wheel Slides', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item" data-tab="wheel_after_spining">
			    <?php esc_html_e( 'After Finishing Spinning', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item" data-tab="wheel_design">
			    <?php esc_html_e( 'Design', 'woocommerce-lucky-wheel' ) ?>
            </div>
            <div class="item" data-tab="wheel_recaptcha">
			    <?php esc_html_e( 'Google reCAPTCHA', 'woocommerce-lucky-wheel' ) ?>
            </div>
        </div>
	    <?php
	    $custom_fields = [
		    'name'=>  [
			    'label' => esc_html__('Name', 'woocommerce-lucky-wheel' ),
			    'enable' => $this->settings->get_params( 'custom_field_name_enable' ),
			    'mobile' => $this->settings->get_params( 'custom_field_name_enable_mobile' ),
			    'required' => $this->settings->get_params( 'custom_field_name_required' ),
		    ],
		    'mobile'=>  [
			    'label' => esc_html__('Phone number', 'woocommerce-lucky-wheel' ),
			    'enable' => $this->settings->get_params( 'custom_field_mobile_enable' ),
			    'mobile' => $this->settings->get_params( 'custom_field_mobile_enable_mobile' ),
			    'required' => $this->settings->get_params( 'custom_field_mobile_required' ),
		    ],
	    ];
	    ob_start();
	    ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Field', 'woocommerce-lucky-wheel' ); ?></th>
                <th><?php esc_html_e( 'Enable', 'woocommerce-lucky-wheel' ); ?></th>
                <th><?php esc_html_e( 'On mobile', 'woocommerce-lucky-wheel' );?></th>
                <th><?php esc_html_e( 'Required', 'woocommerce-lucky-wheel' );?></th>
                <th><?php esc_html_e( 'Country code', 'woocommerce-lucky-wheel' );?></th>
            </tr>
		    <?php
		    foreach ($custom_fields as $field => $field_data){
			    $field_name = "custom_field_{$field}_";
			    ?>
                <tr>
                    <th>
                        <label for="<?php echo esc_attr($field_name.'enable')?>"><?php echo esc_html($field_data['label'] ?? $field)?></label>
                    </th>
                    <td>
                        <div class="vi-ui toggle checkbox">
                            <input class="<?php echo esc_attr($field_name.'enable')?>" type="checkbox"
                                   id="<?php echo esc_attr($field_name.'enable')?>" name="<?php echo esc_attr($field_name.'enable')?>"
                                   value="on" <?php checked( $field_data['enable']??'', 'on' ); ?>>
                            <label></label>
                        </div>
                    </td>
                    <td>
                        <div class="vi-ui toggle checkbox">
                            <input class="<?php echo esc_attr($field_name.'enable_mobile')?>" type="checkbox"
                                   id="<?php echo esc_attr($field_name.'enable_mobile')?>" name="<?php echo esc_attr($field_name.'enable_mobile')?>"
                                   value="on" <?php if(isset($field_data['mobile'])){ checked( $field_data['mobile'], 'on' );}else{ echo esc_attr('disabled checked');} ?>>
                            <label></label>
                        </div>
                    </td>
                    <td>
                        <div class="vi-ui toggle checkbox">
                            <input class="<?php echo esc_attr($field_name.'required')?>" type="checkbox"
                                   id="<?php echo esc_attr($field_name.'required')?>" name="<?php echo esc_attr($field_name.'required')?>"
                                   value="on" <?php if(isset($field_data['required'])){ checked( $field_data['required'], 'on' );}else{ echo esc_attr('disabled checked');} ?>>
                            <label></label>
                        </div>
                    </td>
                    <td>
					    <?php
					    if ($field === 'mobile'){
						    $country_codes = villatheme_json_decode($this->settings::def_phone_country());
						    ksort( $country_codes );
						    $phone_country = $this->settings->get_params( 'custom_field_mobile_phone_countries' );
						    if (!is_array($phone_country)){
							    $phone_country = [];
						    }
						    ?>
                            <select name="custom_field_mobile_phone_countries[]" class="vi-ui fluid dropdown search" multiple
                                    id="custom_field_mobile_phone_countries">
                                <option value="0" <?php selected( in_array(0, $phone_country), true ) ?>>
								    <?php esc_html_e( 'None', 'woocommerce-lucky-wheel' ); ?>
                                </option>
							    <?php
							    foreach ($country_codes as $k => $v){
								    if (is_array($v)){
									    foreach ($v as $c){
										    if ($c){
											    echo wp_kses(sprintf('<option value="%s" %s > %s </option>', $c, selected(in_array($c, $phone_country), true), $k . ' ('. $c .')'), $this->settings::filter_allowed_html());
										    }
									    }
								    }elseif (! empty( $v ) ){
									    echo wp_kses(sprintf('<option value="%s" %s > %s </option>', $v, selected(in_array($v, $phone_country), true), $k . ' ('. $v .')'), $this->settings::filter_allowed_html());
								    }
							    }
							    ?>
                            </select>
                            <p class="description">
							    <?php esc_html_e( 'leave blank to apply all countries', 'woocommerce-lucky-wheel' ); ?>
                            </p>
						    <?php
					    }
					    ?>
                    </td>
                </tr>
			    <?php
		    }
		    ?>
        </table>
	    <?php
	    $wheel_fields_html = ob_get_clean();
	    $fields            = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-wheel-fields-accordion',
//			    'title'     => esc_html__( 'Wheel fields', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields_html'   => $wheel_fields_html,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="wheel_fields">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    ob_start();
	    ?>
        <span class="vi-ui positive button preview-lucky-wheel labeled icon tiny">
            <i class="icon eye"></i><?php esc_html_e( 'Preview Wheel', 'woocommerce-lucky-wheel' ); ?>
        </span>
        <div class="vi-ui message positive tiny">
            <ul class="list">
                <li><?php echo wp_kses_post( __('Use <strong>{coupon_amount}</strong> for WooCommerce coupon type to refer to the amount of that coupon. e.g: Coupon type is percentage discount, coupon value is 10 then <strong>{coupon_amount}</strong> will become 10% when printing out on the wheel.','woocommerce-lucky-wheel') ); ?></li>
                <li><?php echo wp_kses_post( __('<strong>{quantity_label}</strong> is used in prize label if quantity is greater than 0', 'woocommerce-lucky-wheel' )); ?></li>
                <li><?php echo wp_kses_post( __('<strong>{wheel_prize_title}</strong> can be used to refer to the title if coupon type is a <a href="edit.php?post_type=wlwl_wheel_prize" target="_blank">Wheel Prize</a>','woocommerce-lucky-wheel') ); ?></li>
                <li><?php echo wp_kses_post( __('If quantity of a prize is down to zero, its probability will be 0 instead its original probability and <strong>{quantity_label}</strong> will be replace with empty string' ,'woocommerce-lucky-wheel')); ?></li>
                <li>
				    <?php
				    echo wp_kses_post(__('You can use <a href="https://1.envato.market/BZZv1" target="_blank">WooCommerce Email Template Customizer</a> or <a href="http://bit.ly/woo-email-template-customizer" target="_blank">Email Template Customizer for WooCommerce</a> to create and customize your own email template for each prize. If no email template is selected, the default setting at <a href="#email">the \'Email\' tab</a> will be used.','woocommerce-lucky-wheel'));
				    ?>
                </li>
			    <?php
			    if ( VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_WooCommerce_Email_Template_Customizer::$is_active ) {
				    ?>
                    <li>
                        <a href="edit.php?post_type=viwec_template"
                           target="_blank"><?php esc_html_e( 'View all Email templates', 'woocommerce-lucky-wheel' ) ?></a>
	                    <?php esc_html_e( 'or', 'woocommerce-lucky-wheel' ) ?>
                        <a href="post-new.php?post_type=viwec_template&sample=wlwl_coupon_email&style=basic"
                           target="_blank"><?php esc_html_e( 'Create a new email template', 'woocommerce-lucky-wheel' ) ?></a>
                    </li>
                    <li>
					    <?php printf( esc_html( 'Important note: The custom email template must be assigned to each index (wheel segment). Otherwise, notification for that segment will use the default generic email instead. For more info, please see this %s.' ), '<a href="https://docs.villatheme.com/woocommerce-email-template-customizer/#configuration_child_menu_4818">documentation</a>' ); ?>
                    </li>
				    <?php
			    }
			    ?>
            </ul>
        </div>
	    <?php
	    $fields     = [
		    'section_start' => [],
		    'section_end'   => [],
		    'fields'   => [
			    'quantity_label' =>[
				    'title' => esc_html__( '{quantity_label}', 'woocommerce-lucky-wheel' ),
				    'desc' => esc_html__( '{prize_quantity} - The quantity of respective prize', 'woocommerce-lucky-wheel' ),
				    'html' => sprintf('<input type="text" class="quantity_label" id="quantity_label" name="quantity_label"
                           value="%s">',esc_attr( $this->settings->get_params( 'wheel', 'quantity_label' ) )),
			    ]
		    ],
	    ];
	    $this->settings::villatheme_render_table_field( $fields );
	    ?>
        <div class="wheel-settings-container">
            <table class="vi-ui celled table wheel-settings">
                <thead>
                <tr class="wheel-slices">
                    <th width="1%" rowspan="2" class="wheel-index-th"><?php esc_html_e( 'Index', 'woocommerce-lucky-wheel' ) ?></th>
                    <th rowspan="2"><?php esc_html_e( 'Coupon Type', 'woocommerce-lucky-wheel' ) ?></th>
                    <th rowspan="2"><?php esc_html_e( 'Label', 'woocommerce-lucky-wheel' ) ?></th>
                    <th rowspan="2"><?php esc_html_e( 'Value', 'woocommerce-lucky-wheel' ) ?></th>
                    <th width="5%" colspan="2"><?php esc_html_e( 'Probability', 'woocommerce-lucky-wheel' ) ?></th>
                    <th width="2%" rowspan="2"><?php esc_html_e( 'Quantity', 'woocommerce-lucky-wheel' ) ?></th>
                    <th width="6%" rowspan="2"><?php esc_html_e( 'Color', 'woocommerce-lucky-wheel' ) ?></th>
                    <th width="6%" rowspan="2"><?php esc_html_e( 'Text Color', 'woocommerce-lucky-wheel' ) ?></th>
				    <?php do_action('wlwl_wheel_settings_slices_column'); ?>
                </tr>
                <tr class="wheel-slices">
                    <th class="wheel-slices-weight" style="border-left: 1px solid rgba(34,36,38,.1);"><?php esc_html_e( 'Weight', 'woocommerce-lucky-wheel' ) ?></th>
                    <th><?php esc_html_e( 'Percentage(%)', 'woocommerce-lucky-wheel' ) ?></th>
                </tr>
                </thead>
                <tbody class="ui-sortable">
			    <?php
			    $coupon_type         = $this->settings->get_params( 'wheel', 'coupon_type' );
			    $coupon_amount       = $this->settings->get_params( 'wheel', 'coupon_amount' );
			    $probability         = $this->settings->get_params( 'wheel', 'probability' );
			    $existing_coupon     = $this->settings->get_params( 'wheel', 'existing_coupon' );
			    $custom_value        = $this->settings->get_params( 'wheel', 'custom_value' );
			    $custom_label        = $this->settings->get_params( 'wheel', 'custom_label' );
			    $slices_text_color   = $this->settings->get_params( 'wheel', 'slices_text_color' );
			    $bg_color            = $this->settings->get_params( 'wheel', 'bg_color' );
			    if (!is_array($coupon_type)){
				    $coupon_type = [];
			    }
			    $coupon_count        = count( $coupon_type );
			    $prize_quantity      = $this->settings->get_params( 'wheel', 'prize_quantity' );
			    if (!is_array($prize_quantity)){
				    $prize_quantity = [];
			    }
			    if ( count( $prize_quantity ) !== $coupon_count ) {
				    $prize_quantity = array_fill( 0, $coupon_count, - 1 );
			    }
			    $dynamic_coupons = VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Wheel_Prize::get_coupons();
			    for ( $count = 0; $count < $coupon_count; $count ++ ) {
				    $is_dynamic_coupon = ! in_array( $coupon_type[ $count ], array(
					    'non',
					    'existing_coupon',
					    'percent',
					    'fixed_product',
					    'fixed_cart',
					    'custom'
				    ) );
				    ?>
                    <tr class="wheel_col <?php echo esc_attr( $is_dynamic_coupon ? 'wheel_col-dynamic_coupon' : "wheel_col-{$coupon_type[ $count ]}" ); ?>">
                        <td class="wheel_col_index remove_field_wrap" width="1%">
                            <span class="wheel-col-index"><?php echo esc_attr( $count + 1 ); ?></span>
                            <span class="remove_field negative vi-ui button"
                                  title="<?php esc_attr_e( 'Remove this item', 'woocommerce-lucky-wheel' ); ?>"><i
                                        class="icon trash"></i></span>
                            <span class="clone_piece positive vi-ui button"
                                  title="<?php esc_attr_e( 'Clone this item', 'woocommerce-lucky-wheel' ); ?>"><i
                                        class="icon copy"></i></span>
                        </td>
                        <td class="wheel_col_coupons">
                            <select name="coupon_type[]" class="coupons_select vi-ui fluid dropdown">
                                <option value="non" <?php selected( $coupon_type[ $count ], 'non' ); ?>><?php esc_html_e( 'Non', 'woocommerce-lucky-wheel' ) ?></option>
                                <option value="existing_coupon" <?php selected( $coupon_type[ $count ], 'existing_coupon' ); ?>><?php esc_html_e( 'Existing coupon', 'woocommerce-lucky-wheel' ) ?></option>
                                <option value="percent" <?php selected( $coupon_type[ $count ], 'percent' ); ?>><?php esc_html_e( 'Percentage discount', 'woocommerce-lucky-wheel' ) ?></option>
                                <option value="fixed_product" <?php selected( $coupon_type[ $count ], 'fixed_product' ); ?>><?php esc_html_e( 'Fixed product discount', 'woocommerce-lucky-wheel' ) ?></option>
                                <option value="fixed_cart" <?php selected( $coupon_type[ $count ], 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart discount', 'woocommerce-lucky-wheel' ) ?></option>
                                <option value="custom" <?php selected( $coupon_type[ $count ], 'custom' ); ?>><?php esc_html_e( 'Custom', 'woocommerce-lucky-wheel' ) ?></option>
							    <?php
							    if ( !empty( $dynamic_coupons ) ) {
								    foreach ( $dynamic_coupons as $dynamic_coupon ) {
									    ?>
                                        <option value="<?php echo esc_attr( $dynamic_coupon->ID ) ?>" <?php selected( $coupon_type[ $count ], $dynamic_coupon->ID ); ?>
                                                data-coupon_amount="<?php echo esc_attr( get_post_meta( $dynamic_coupon->ID, 'coupon_amount', true ) ) ?>"><?php echo esc_html( "(#{$dynamic_coupon->ID}){$dynamic_coupon->post_title}" ) ?></option>
									    <?php
								    }
							    }
							    ?>
                            </select>
                        </td>
                        <td class="wheel_col_coupons_label">
	                        <?php
	                        $fields     = [
		                        'fields'   => [
			                        'custom_type_label' =>[
				                        'not_wrap_html' => 1,
				                        'wheel_slide_index' => $count,
				                        'html' => sprintf('<input type="text" name="custom_type_label[]" class="custom_type_label" value="%s" placeholder="Label">',
					                        esc_attr( $custom_label[ $count ] )),
			                        ]
		                        ],
	                        ];
	                        $this->settings::villatheme_render_table_field( $fields );
	                        ?>
                        </td>
                        <td class="wheel_col_coupons_value">
                            <input type="number" name="coupon_amount[]" min="0" step=".01"
                                   class="coupon_amount <?php echo ( $is_dynamic_coupon || $coupon_type[ $count ] === 'non' ) ? 'coupon-amount-readonly' : ''; ?>"
                                   value="<?php echo esc_attr( $coupon_amount[ $count ] ); ?>"
                                   placeholder="Coupon Amount" <?php if ( $is_dynamic_coupon || $coupon_type[ $count ] === 'non' ) {
							    echo 'readonly';
						    } ?>/>
                            <input type="text" name="custom_type_value[]" class="custom_type_value"
                                   value="<?php echo isset( $custom_value[ $count ] ) ? esc_attr( $custom_value[ $count ] ) : ''; ?>"
                                   placeholder="Value/Code"/>
                            <div class="wlwl_existing_coupon">
                                <select name="wlwl_existing_coupon[]"
                                        class="coupon-search wlwl_existing_coupon select2-selection--single"
                                        data-placeholder="<?php esc_attr_e( 'Enter Code', 'woocommerce-lucky-wheel' ) ?>">
								    <?php
								    $coupon_id    = '';
								    $coupon_title = '';
								    if ( isset( $existing_coupon[ $count ] ) ) {
									    $coupon_obj = get_post( $existing_coupon[ $count ] );
									    if ( $coupon_obj ) {
										    $coupon_id    = $existing_coupon[ $count ];
										    $coupon_title = $coupon_obj->post_title;
									    }
								    }
								    ?>
                                    <option value="<?php echo esc_attr( $coupon_id ) ?>"
                                            selected><?php echo esc_html( $coupon_title ) ?></option>
                                </select>
                            </div>
                        </td>
                        <td class="wheel_col_probability">
                            <input type="number" name="probability[]"
                                   class="probability probability_<?php echo esc_attr( $count ); ?>" min="0"
                                   placeholder="Probability"
                                   value="<?php echo esc_attr( absint( $probability[ $count ] ) ) ?>"/>
                        </td>
                        <td class="wheel_col_probability_percent">
                            <div class="vi-ui right labeled left input fluid">
                                <input type="text"
                                       class="probability-percent probability-percent-<?php echo esc_attr( $count ); ?>"
                                       min="0" readonly disabled>
                                <label for="" class="vi-ui label">%</label>
                            </div>

                        </td>
                        <td class="wheel_col_prize_quantity">
                            <input type="number" name="prize_quantity[]"
                                   class="prize_quantity prize_quantity_<?php echo esc_attr( $count ); ?>"
                                   min="-1"
                                   value="<?php echo esc_attr( intval( $prize_quantity[ $count ] ) ) ?>"/>
                        </td>
                        <td>
                            <input type="text" name="bg_color[]" class="color-picker"
                                   value=" <?php echo esc_attr( trim( $bg_color[ $count ] ) ); ?>"
                                   style="background: <?php echo esc_attr( trim( $bg_color[ $count ] ) ); ?>"/>
                        </td>
                        <td>
                            <input type="text" name="slices_text_color[]"
                                   class="color-picker"
                                   value="<?php echo esc_attr( $tmp_slices_text_color =  $slices_text_color[ $count ] ?? '#fff' ); ?>"
                                   style="background:<?php echo $tmp_slices_text_color; ?>"/>
                        </td>
	                    <?php do_action('wlwl_wheel_settings_slices_column_content',$count); ?>
                    </tr>
				    <?php
			    }
			    ?>
                <tfoot>
                <tr>
                    <th class="col_add_new" colspan="4">
                        <div class="vi-ui message tiny">
                            <ul class="list">
                                <li><?php esc_html_e( 'You can drag and drop slices to rearrange them.', 'woocommerce-lucky-wheel' ); ?></li>
                                <li><?php echo wp_kses_post( 'To create more flexible prizes, please go to <a href="edit.php?post_type=wlwl_wheel_prize" target="_blank">Wheel Prizes</a>' ); ?></li>
                            </ul>
                        </div>
                    </th>
                    <th class="col_add_new col_total_probability" colspan="3">
                        <div class="vi-ui message tiny">
                            <ul class="list">
                                <li><?php esc_html_e( 'To change probability, please adjust weight', 'woocommerce-lucky-wheel' ) ?></li>
                                <li><?php esc_html_e( 'Each time a customer wins a prize, its quantity will be automatically reduced by 1', 'woocommerce-lucky-wheel' ); ?></li>
                                <li><?php echo wp_kses_post( 'Set quantity to <strong>-1</strong> to not limit the number of prizes' ); ?></li>
                            </ul>
                        </div>
                    </th>
                    <th class="col_add_new" colspan="3">
			            <?php
			            self::auto_color();
			            ?>
                        <p>
                            <span class="auto_color positive vi-ui button tiny"><?php esc_html_e( 'Auto Color', 'woocommerce-lucky-wheel' ) ?></span>
                        </p>
                        <div class="vi-ui toggle checkbox">
                            <p>
                                <input class="random_color" type="checkbox" id="random_color"
                                       name="random_color"
                                       value="on" <?php checked( $this->settings->get_params( 'wheel', 'random_color' ), 'on' ) ?>>
                                <label><?php esc_html_e( 'Color is set randomly from predefined sets for each visitor', 'woocommerce-lucky-wheel' ) ?></label>
                            </p>
                        </div>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
	    <?php
	    $wheel_html = ob_get_clean();
	    $fields     = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'active'    => 1,
//			    'class'     => 'wlwl-wheel-slide-accordion',
//			    'title'     => esc_html__( 'Wheel Slides', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields_html'   => $wheel_html,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab active" data-tab="wheel_sildes">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    $congratulations_effect = $this->settings->get_params( 'wheel_wrap', 'congratulations_effect' );
	    ob_start();
	    ?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                    <label for="result_win"><?php esc_html_e( 'Automatically hide wheel after', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui right labeled input">
                        <input type="number" name="result-auto_close" min="0"
                               id="result-auto_close"
                               value="<?php echo intval( $this->settings->get_params( 'result', 'auto_close' ) ) ?>">
                        <label class="vi-ui label"><?php esc_html_e( 'Seconds', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Hide the wheel in how many seconds after one spin. Leave 0 to disable this feature', 'woocommerce-lucky-wheel'); ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="congratulations_effect"><?php esc_html_e( 'Winning effect', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <select name="congratulations_effect" id="congratulations_effect"
                            class="vi-ui fluid dropdown">
                        <option value="none" <?php selected( $congratulations_effect, 'none' ) ?>>
						    <?php esc_html_e( 'None', 'woocommerce-lucky-wheel' ); ?>
                        </option>
                        <option value="firework" <?php selected( $congratulations_effect, 'firework' ) ?>>
						    <?php esc_html_e( 'Firework', 'woocommerce-lucky-wheel' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="result_win"><?php esc_html_e( 'Winning message if prize is WooCommerce coupon', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
	                <?php
	                $frontend_message = $this->settings->get_params( 'result', 'notification' );
	                $win_option = array( 'editor_height' => 200, 'media_buttons' => true );
	                ob_start();
	                wp_editor( stripslashes( $frontend_message['win'] ??'' ), 'result_win', $win_option );
	                $result_win_html = ob_get_clean();
	                $fields     = [
		                'fields'   => [
			                'result_win' =>[
				                'not_wrap_html' => 1,
				                'result_win_option' => $win_option,
				                'html' => $result_win_html,
			                ]
		                ],
	                ];
	                $this->settings::villatheme_render_table_field( $fields );
	                ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="result_win_custom"><?php esc_html_e( 'Winning message if prize is custom type', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
	                <?php
	                $win_custom = isset( $frontend_message['win_custom'] ) ? $frontend_message['win_custom'] : $frontend_message['win'];
	                ob_start();
	                wp_editor( stripslashes( $win_custom ), 'result_win_custom', $win_option );
	                $result_win_html = ob_get_clean();
	                $fields     = [
		                'fields'   => [
			                'result_win_custom' =>[
				                'not_wrap_html' => 1,
				                'value' => $win_custom,
				                'result_win_option' => $win_option,
				                'html' => $result_win_html,
			                ]
		                ],
	                ];
	                $this->settings::villatheme_render_table_field( $fields );
	                ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
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
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon"><?php esc_html_e( '"Apply Coupon" button', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input id="wlwl_button_apply_coupon" type="checkbox"
                               name="wlwl_button_apply_coupon"
                               value="1" <?php checked( $this->settings->get_params( 'button_apply_coupon' ), 1 ) ?>>
                        <label></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enable to show the "Apply Coupon" button if the prize is WooCommerce Coupon.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon_redirect"><?php esc_html_e( '"Apply Coupon" button redirect', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
	                <?php
	                ob_start();
                    ?>
                    <input type="text" name="wlwl_button_apply_coupon_redirect"
                           id="wlwl_button_apply_coupon_redirect"
                           value="<?php echo esc_html( $this->settings->get_params( 'button_apply_coupon_redirect' ) ); ?>">
                    <?php
                    $tmp_html = ob_get_clean();
	                $fields     = [
		                'fields'   => [
			                'button_apply_coupon_redirect' =>[
				                'not_wrap_html' => 1,
				                'html' => $tmp_html,
			                ]
		                ],
	                ];
	                $this->settings::villatheme_render_table_field( $fields );
	                ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon_color"><?php esc_html_e( '"Apply Coupon" button color', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_button_apply_coupon_color"
                           id="wlwl_button_apply_coupon_color"
                           class="color-picker"
                           value="<?php echo esc_attr($button_apply_coupon_color = $this->settings->get_params( 'button_apply_coupon_color' ) ); ?>"
                           style="background-color: <?php echo wp_kses_post( $button_apply_coupon_color ) ?>;">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon_bg_color"><?php esc_html_e( '"Apply Coupon" button background color', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_button_apply_coupon_bg_color"
                           id="wlwl_button_apply_coupon_bg_color" class="color-picker"
                           value="<?php echo esc_attr($button_apply_coupon_bg_color = $this->settings->get_params( 'button_apply_coupon_bg_color' ) ); ?>"
                           style="background-color: <?php echo wp_kses_post($button_apply_coupon_bg_color) ?>;">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon_font_size"><?php esc_html_e( '"Apply Coupon" button font size', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui right labeled input">
                        <input type="number" name="wlwl_button_apply_coupon_font_size"
                               id="wlwl_button_apply_coupon_font_size"
                               min="1"
                               value="<?php echo esc_attr( $this->settings->get_params( 'button_apply_coupon_font_size' ) ); ?>">
                        <label class="vi-ui label"><?php esc_html_e( 'PX', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_apply_coupon_border_radius"><?php esc_html_e( '"Apply Coupon" button rounded corner', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui right labeled input">
                        <input type="number" name="wlwl_button_apply_coupon_border_radius"
                               id="wlwl_button_apply_coupon_border_radius"
                               min="1"
                               value="<?php echo esc_attr( $this->settings->get_params( 'button_apply_coupon_border_radius' ) ); ?>">
                        <label class="vi-ui label"><?php esc_html_e( 'PX', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="result_lost"><?php esc_html_e( 'Frontend message if lost', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
				    <?php
				    $lost_option = array( 'editor_height' => 100, 'media_buttons' => true );
				    ob_start();
				    wp_editor( stripslashes( $this->settings->get_params( 'result', 'notification' )['lost'] ??''), 'result_lost', $lost_option );
				    $result_win_html = ob_get_clean();
				    $fields     = [
					    'fields'   => [
						    'result_lost' =>[
							    'not_wrap_html' => 1,
							    'result_lost_option' => $lost_option,
							    'html' => $result_win_html,
						    ]
					    ],
				    ];
				    $this->settings::villatheme_render_table_field( $fields );
				    ?>
                </td>
            </tr>
            </tbody>
        </table>
	    <?php
	    $wheel_html = ob_get_clean();
	    $fields     = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-wheel-after-finishing-spinning-accordion',
//			    'title'     => esc_html__( 'After Finishing Spinning', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields_html'   => $wheel_html,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="wheel_after_spining">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    ob_start();
	    ?>
        <table class="form-table wheel-settings">
            <tbody class="content">
            <tr>
                <th>
                    <label for="show_full_wheel"><?php esc_html_e( 'Show full wheel', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input class="show_full_wheel" type="checkbox" id="show_full_wheel"
                               name="show_full_wheel"
                               value="on" <?php checked( $this->settings->get_params( 'wheel', 'show_full_wheel' ), 'on' ) ?>>
                        <label></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Make all wheel segments visible on desktop. By default, the wheel on desktop shows partially. Enable this option to to make it show fully.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_speed"><?php esc_html_e( 'Wheel spin', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <div class="equal width fields">
                        <div class="field">
                            <div class="vi-ui right labeled input">
                                <select name="wheel_speed" id="wheel_speed" class="vi-ui fluid dropdown">
								    <?php
								    for ( $i = 1; $i <= 10; $i ++ ) {
									    ?>
                                        <option value="<?php echo esc_attr( $i ) ?>" <?php selected( $this->settings->get_params( 'wheel', 'wheel_speed' ), $i ) ?>>
										    <?php echo esc_html( $i ); ?>
                                        </option>
									    <?php
								    }
								    ?>
                                </select>
                            </div>
                            <p class="description"><?php esc_html_e( 'The number of spins per one second. For example, if you select 10, it means the wheel spins 10 rolls in one second', 'woocommerce-lucky-wheel' ) ?></p>
                        </div>
                        <div class="field">
                            <div class="vi-ui right labeled input">
                                <input type="number" min="3" max="15" name="wheel_spinning_time"
                                       id="wheel_spinning_time"
                                       value="<?php echo esc_attr( $this->settings->get_params( 'wheel', 'spinning_time' ) ); ?>">
                                <label class="vi-ui label"><?php esc_html_e( 'Seconds', 'woocommerce-lucky-wheel' ) ?></label>
                            </div>
                            <p class="description"><?php esc_html_e( 'How long the wheel will spin. Valid duration from 3 to 15 seconds', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                    </div></td>
            </tr>
            <tr>
                <th>
                    <label for="font_size"><?php esc_html_e( 'Adjust size', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="equal width fields">
                        <div class="field">
                            <div class="vi-ui right labeled input">
                                <input type="number" class="font_size" id="font_size"
                                       name="font_size"
                                       value="<?php echo esc_attr( $this->settings->get_params( 'wheel', 'font_size' ,'',100) ) ?>">
                                <label class="vi-ui label"><?php esc_html_e( '%', 'woocommerce-lucky-wheel' ) ?></label>
                            </div>
                            <p class="description"><?php esc_html_e( 'Adjust font size of text on the wheel by', 'woocommerce-lucky-wheel' ) ?></p>
                        </div>
                        <div class="field">
                            <div class="vi-ui right labeled input">
                                <input type="number" class="wheel_size" id="wheel_size"
                                       name="wheel_size"
                                       value="<?php echo esc_attr( $this->settings->get_params( 'wheel', 'wheel_size','',100 ) ) ?>">
                                <label class="vi-ui label"><?php esc_html_e( '%', 'woocommerce-lucky-wheel' ) ?></label>
                            </div>
                            <p class="description"><?php esc_html_e( 'Adjust wheel size', 'woocommerce-lucky-wheel' ) ?></p>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl-currency"><?php esc_html_e( 'Displayed Currency', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>

                    <select name="wlwl_currency" id="wlwl-currency" class="vi-ui fluid dropdown">
                        <option value="symbol" <?php selected( $this->settings->get_params( 'wheel', 'currency' ), 'symbol' ) ?>><?php esc_html_e( 'Symbol', 'woocommerce-lucky-wheel' ); ?>
                            (₫, $, &euro;, &pound;...)
                        </option>
                        <option value="code" <?php selected( $this->settings->get_params( 'wheel', 'currency' ), 'code' ) ?>><?php esc_html_e( 'Currency code in English(VND, USD, EUR, GBP ...)', 'woocommerce-lucky-wheel' ); ?></option>
                    </select>

                </td>
            </tr>
            <tr>
                <th>
                    <label for="pointer_position"><?php esc_html_e( 'Wheel pointer', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <div class="equal width fields">
                        <div class="field">
                            <select name="pointer_position" id="pointer_position" class="vi-ui fluid dropdown">
                                <option value="center" <?php selected( $this->settings->get_params( 'wheel_wrap', 'pointer_position' ), 'center' ) ?>><?php esc_html_e( 'Center', 'woocommerce-lucky-wheel' ); ?></option>
                                <option value="top" <?php selected( $this->settings->get_params( 'wheel_wrap', 'pointer_position' ), 'top' ) ?>><?php esc_html_e( 'Top', 'woocommerce-lucky-wheel' ); ?></option>
                                <option value="right" <?php selected( $this->settings->get_params( 'wheel_wrap', 'pointer_position' ), 'right' ) ?>><?php esc_html_e( 'Right', 'woocommerce-lucky-wheel' ); ?></option>
                                <option value="bottom" <?php selected( $this->settings->get_params( 'wheel_wrap', 'pointer_position' ), 'bottom' ) ?>><?php esc_html_e( 'Bottom', 'woocommerce-lucky-wheel' ); ?></option>
                                <option value="random" <?php selected( $this->settings->get_params( 'wheel_wrap', 'pointer_position' ), 'random' ) ?>><?php esc_html_e( 'Random', 'woocommerce-lucky-wheel' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Wheel pointer position', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                        <div class="field">
                            <input name="pointer_color" id="pointer_color" type="text"
                                   class="color-picker"
                                   value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) );
						           } ?>"
                                   style="background-color: <?php if ( $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'pointer_color' ) );
						           } ?>;">
                            <p class="description"><?php esc_html_e( 'Wheel pointer color', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl-center-image1"><?php esc_html_e( 'Wheel center background image', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td id="wlwl-bg-image1">
				    <?php
				    if ( $this->settings->get_params( 'wheel_wrap', 'wheel_center_image' ) ) {
					    ?>
                        <div class="wlwl-image-container1">
                            <img style="border: 1px solid;" class="review-images1" alt=""
                                 src="<?php echo esc_url_raw( wp_get_attachment_thumb_url( $this->settings->get_params( 'wheel_wrap', 'wheel_center_image' ) ) ); ?>">
                            <input class="wheel_center_image" name="wheel_center_image"
                                   type="hidden"
                                   value="<?php echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_center_image' ) ); ?>">
                            <span class="wlwl-remove-image1 negative vi-ui button"><?php esc_html_e( 'Remove', 'woocommerce-lucky-wheel' ); ?></span>
                        </div>
                        <div id="wlwl-new-image1" style="float: left;">
                        </div>
                        <span style="display: none;"
                              class="positive vi-ui button wlwl-upload-custom-img1"><?php esc_html_e( 'Add Image', 'woocommerce-lucky-wheel' ); ?></span>
					    <?php

				    } else {
					    ?>
                        <div id="wlwl-new-image1" style="float: left;">
                        </div>
                        <span class="positive vi-ui button wlwl-upload-custom-img1"><?php esc_html_e( 'Add Image', 'woocommerce-lucky-wheel' ); ?></span>
					    <?php
				    }
				    ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_border_color"><?php esc_html_e( 'Color', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <div class="equal width fields">
                        <div class="field">
                            <input name="wheel_center_color" id="wheel_center_color" type="text"
                                   class="color-picker"
                                   value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ) );
						           } ?>"
                                   style="background-color: <?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_center_color' ) );
						           } ?>;">
                            <p class="description"><?php esc_html_e( 'Wheel center color', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                        <div class="field">
                            <input name="wheel_border_color" id="wheel_border_color" type="text"
                                   class="color-picker"
                                   value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ) );
						           } ?>"
                                   style="background-color: <?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_border_color' ) );
						           } ?>;">
                            <p class="description"><?php esc_html_e( 'Wheel border color', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                        <div class="field">
                            <input name="wheel_dot_color" id="wheel_dot_color" type="text"
                                   class="color-picker"
                                   value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ) );
						           } ?>"
                                   style="background-color: <?php if ( $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ) ) {
							           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'wheel_dot_color' ) );
						           } ?>;">
                            <p class="description"><?php esc_html_e( 'Wheel border dot color', 'woocommerce-lucky-wheel' ); ?></p>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_bg_image"><?php esc_html_e( 'Background image', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td id="wlwl-bg-image">
				    <?php
                    $wheel_wrap_bg_color = $this->settings->get_params( 'wheel_wrap', 'bg_color' ) ;
				    $bg_image = $this->settings->get_params( 'wheel_wrap', 'bg_image' );
				    $bg_image_url = $bg_image && intval( $bg_image ) ? wp_get_attachment_url( $bg_image ) : $bg_image;
				    $use_bg_image_default = $bg_image_url === VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES . '2020.png' || str_ends_with($bg_image_url,'/woo-lucky-wheel/images/2020.png');
				    ?>
                    <select name="wheel_wrap_bg_image_type" class="vi-ui fluid dropdown wheel_wrap_bg_image_type">
                        <option value="0" <?php selected($use_bg_image_default) ?>><?php esc_html_e('Default','woocommerce-lucky-wheel') ?></option>
                        <option value="1" <?php selected($use_bg_image_default,false) ?>><?php esc_html_e('Custom image','woocommerce-lucky-wheel') ?></option>
                    </select>
                    <div class="wheel_wrap_bg_image_custom">
                        <div class="wlwl-image-container">
                            <input class="wheel_wrap_bg_image" name="wheel_wrap_bg_image"
                                   type="hidden"
                                   value="<?php echo esc_attr( $bg_image ); ?>">
                            <img style="width: 300px;background: <?php echo esc_attr( $wheel_wrap_bg_color ); ?>" class="review-images"
                                 src="<?php echo esc_url( $bg_image_url ); ?>">
                            <span class="wlwl-remove-image negative vi-ui button"><?php esc_html_e( 'Remove', 'woocommerce-lucky-wheel' ); ?></span>
                        </div>
                        <span class="positive vi-ui button wlwl-upload-custom-img"><?php esc_html_e( 'Add Image', 'woocommerce-lucky-wheel' ); ?></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_bg_color"><?php esc_html_e( 'Background color', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <input name="wheel_wrap_bg_color" id="wheel_wrap_bg_color" type="text"
                           class="color-picker"
                           value="<?php if ( $wheel_wrap_bg_color ) {
					           echo esc_attr($wheel_wrap_bg_color );
				           } ?>"
                           style="background: <?php if ($wheel_wrap_bg_color) {
					           echo esc_attr( $wheel_wrap_bg_color );
				           } ?>;">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_text_color"><?php esc_html_e( 'Text color', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <input name="wheel_wrap_text_color" id="wheel_wrap_text_color" type="text"
                           class="color-picker"
                           value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'text_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'text_color' ) );
				           } ?>"
                           style="background: <?php if ( $this->settings->get_params( 'wheel_wrap', 'text_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'text_color' ) );
				           } ?>;">
                    <p class="description"><?php esc_html_e( 'Text color in the wheel background content, including wheel description, text to not show the wheel again... Note: This option may be affected by your theme.', 'woocommerce-lucky-wheel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_description"><?php esc_html_e( 'Wheel description', 'woocommerce-lucky-wheel' ); ?>
                    </label>
                </th>
                <td>
				    <?php
				    $desc_option = array( 'editor_height' => 200, 'media_buttons' => true );
				    ob_start();
				    wp_editor( stripslashes( $this->settings->get_params( 'wheel_wrap', 'description' ) ), 'wheel_wrap_description', $desc_option );
				    $wheel_wrap_description_html = ob_get_clean();
				    $fields     = [
					    'fields'   => [
						    'wheel_wrap_description' =>[
							    'not_wrap_html' => 1,
							    'wheel_desc_option' => $desc_option,
							    'html' => $wheel_wrap_description_html,
						    ]
					    ],
				    ];
				    $this->settings::villatheme_render_table_field( $fields );
				    ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_spin_button"><?php esc_html_e( 'Spin Wheel button', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
				    <?php
				    ob_start();
				    ?>
                    <input type="text" name="wheel_wrap_spin_button" id="wheel_wrap_spin_button"
                           value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'spin_button' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'spin_button' ) );
				           } ?>">
				    <?php
				    $wheel_wrap_spin_button_html = ob_get_clean();
				    $fields     = [
					    'fields'   => [
						    'wheel_wrap_spin_button' =>[
							    'not_wrap_html' => 1,
							    'html' => $wheel_wrap_spin_button_html,
						    ]
					    ],
				    ];
				    $this->settings::villatheme_render_table_field( $fields );
				    ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_spin_button_color"><?php esc_html_e( 'Spin Wheel button color', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <input type="text" class="color-picker" name="wheel_wrap_spin_button_color"
                           id="wheel_wrap_spin_button_color"
                           value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) );
				           } ?>"
                           style="background-color:<?php if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'spin_button_color' ) );
				           } ?>;">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_spin_button_bg_color"><?php esc_html_e( 'Spin Wheel button background color', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <input type="text" class="color-picker" name="wheel_wrap_spin_button_bg_color"
                           id="wheel_wrap_spin_button_bg_color"
                           value="<?php if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) );
				           } ?>"
                           style="background-color:<?php if ( $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) ) {
					           echo esc_attr( $this->settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ) );
				           } ?>;">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="gdpr_policy"><?php esc_html_e( 'GDPR checkbox', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input class="gdpr_policy" type="checkbox" id="gdpr_policy"
                               name="gdpr_policy"
                               value="on" <?php checked( $this->settings->get_params( 'wheel_wrap', 'gdpr' ), 'on' ) ?>>
                        <label></label>
                    </div>
                </td>
            </tr>
            <tr class="wlwl-gdpr_policy-class">
                <th>
                    <label for="gdpr_message"><?php esc_html_e( 'GDPR message', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
				    <?php
				    $desc_option = array( 'editor_height' => 200, 'media_buttons' => false );
				    ob_start();
				    wp_editor( stripslashes( $this->settings->get_params( 'wheel_wrap', 'gdpr_message' ) ), 'gdpr_message', $desc_option );
				    $wheel_wrap_description_html = ob_get_clean();
				    $fields     = [
					    'fields'   => [
						    'gdpr_message' =>[
							    'not_wrap_html' => 1,
							    'gdpr_message_option' => $desc_option,
							    'html' => $wheel_wrap_description_html,
						    ]
					    ],
				    ];
				    $this->settings::villatheme_render_table_field( $fields );
				    ?>
                </td>
            </tr>
            </tbody>
        </table>
        <div class="vi-ui message positive tiny">
            <p><?php esc_html_e('The options below will be specifically reserved for the popup.','woocommerce-lucky-wheel' ); ?></p>
        </div>
        <table class="form-table">
            <tbody>
		    <?php
		    $background_effect  = $this->settings->get_params( 'wheel_wrap', 'background_effect' );
		    $background_effects = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_all_bg_effects();
		    ?>
            <tr>
                <th>
                    <label for="background_effect"><?php esc_html_e( 'Background effect', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <select name="background_effect" id="background_effect"
                            class="vi-ui fluid dropdown">
					    <?php
					    foreach ( $background_effects as $bg_e_k => $bg_e ) {
						    ?>
                            <option value="<?php echo esc_attr( $bg_e_k ) ?>" <?php selected( $background_effect, $bg_e_k ) ?>>
							    <?php echo esc_html( $bg_e ); ?>
                            </option>
						    <?php
					    }
					    ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wheel_wrap_close_option"><?php esc_html_e( 'Not display wheel again', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" name="wheel_wrap_close_option"
                               id="wheel_wrap_close_option" <?php checked( $this->settings->get_params( 'wheel_wrap', 'close_option' ), 'on' ) ?>>
                        <label></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enable this option to show "Never", "Remind later" and "No thanks" below the Spin Wheel button. The wheel will be hidden afterward if the user clicks one of these text.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl-google-font-select"><?php esc_html_e( 'Select font', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>

                    <input type="text" name="wlwl_google_font_select"
                           id="wlwl-google-font-select"
                           value="<?php echo esc_attr( $wheel_wrap_font = $this->settings->get_params( 'wheel_wrap', 'font' ) ) ?>"><span
                            class="wlwl-google-font-select-remove wlwl-cancel"
                            style="<?php if ( ! $wheel_wrap_font ) {
							    echo 'display:none';
						    } ?>"></span>

                </td>
            </tr>
            <tr>
                <th>
                    <label for="custom_css"><?php esc_html_e( 'Custom css', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <textarea name="custom_css"><?php echo wp_kses_post( $this->settings->get_params( 'wheel_wrap', 'custom_css' ) ) ?></textarea>
                </td>
            </tr>
            </tbody>
        </table>
	    <?php
	    $wheel_html = ob_get_clean();
	    $fields = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-wheel-design-accordion',
//			    'title'     => esc_html__( 'Wheel Design', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields_html'   => $wheel_html,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="wheel_design">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php
	    ob_start();
	    ?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                    <label for=""><?php esc_html_e( 'Enable', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox"
                               name="wlwl_recaptcha"
                               class="checkbox"
                               id="wlwl_recaptcha"
                               tabindex="0" <?php checked( $this->settings->get_params( 'wlwl_recaptcha' ), 'on' ) ?>>
                        <label for="wlwl_recaptcha"></label>
                    </div>

                    <p class="description"><?php esc_html_e( 'Turn on to use Google ReCaptcha', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_recaptcha_version"><?php esc_html_e( 'Version', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select name="wlwl_recaptcha_version" id="wlwl_recaptcha_version"
                            class="vi-ui fluid dropdown wlwl_recaptcha_version">
                        <option value="2" <?php selected( $this->settings->get_params( 'wlwl_recaptcha_version' ), '2' ) ?>><?php esc_html_e( 'reCAPTCHA v2', 'woocommerce-lucky-wheel' ) ?></option>
                        <option value="3" <?php selected( $this->settings->get_params( 'wlwl_recaptcha_version' ), '3' ) ?>><?php esc_html_e( 'reCAPTCHA v3', 'woocommerce-lucky-wheel' ) ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_recaptcha_site_key"><?php esc_html_e( 'Site key', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_recaptcha_site_key"
                           value="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_site_key' ) ) ?>">
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_recaptcha_secret_key"><?php esc_html_e( 'Secret key', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_recaptcha_secret_key"
                           value="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_secret_key' ) ) ?>">
                </td>
            </tr>
            <tr class="wlwl-recaptcha-v2-wrap"
                style="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 2 ? '' : 'display:none;' ); ?>">
                <th>
                    <label for="wlwl_recaptcha_secret_theme"><?php esc_html_e( 'Theme', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select name="wlwl_recaptcha_secret_theme" id="wlwl_recaptcha_secret_theme"
                            class="vi-ui fluid dropdown wlwl_recaptcha_secret_theme">
                        <option value="dark" <?php selected( $this->settings->get_params( 'wlwl_recaptcha_secret_theme' ), 'dark' ) ?>><?php esc_html_e( 'Dark', 'woocommerce-lucky-wheel' ) ?></option>
                        <option value="light" <?php selected( $this->settings->get_params( 'wlwl_recaptcha_secret_theme' ), 'light' ) ?>><?php esc_html_e( 'Light', 'woocommerce-lucky-wheel' ) ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    <label for=""><?php esc_html_e( 'Guide', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div>
                        <strong class="wlwl-recaptcha-v2-wrap"
                                style="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 2 ? '' : 'display:none;' ); ?>">
						    <?php esc_html_e( 'Get Google reCAPTCHA V2 Site and Secret key', 'woocommerce-lucky-wheel' ) ?>
                        </strong>
                        <strong class="wlwl-recaptcha-v3-wrap"
                                style="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 3 ? '' : 'display:none;' ); ?>">
						    <?php esc_html_e( 'Get Google reCAPTCHA V3 Site and Secret key', 'woocommerce-lucky-wheel' ) ?>
                        </strong>
                        <ul>
                            <li><?php echo wp_kses_post( __('1, Visit <a target="_blank" href="https://www.google.com/recaptcha/admin">page</a> to sign up for an API key pair with your Gmail account', 'woocommerce-lucky-wheel' )) ?></li>

                            <li class="wlwl-recaptcha-v2-wrap"
                                style="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 2 ? '' : 'display:none;' ); ?>">
							    <?php esc_html_e( '2, Choose reCAPTCHA v2 checkbox ', 'woocommerce-lucky-wheel' ) ?>
                            </li>
                            <li class="wlwl-recaptcha-v3-wrap"
                                style="<?php echo esc_attr( $this->settings->get_params( 'wlwl_recaptcha_version' ) == 3 ? '' : 'display:none;' ); ?>">
							    <?php esc_html_e( '2, Choose reCAPTCHA v3', 'woocommerce-lucky-wheel' ) ?>
                            </li>
                            <li><?php esc_html_e( '3, Fill in authorized domains', 'woocommerce-lucky-wheel' ) ?></li>
                            <li><?php esc_html_e( '4, Accept terms of service and click Register button', 'woocommerce-lucky-wheel' ) ?></li>
                            <li><?php esc_html_e( '5, Copy and paste the site and secret key into the above field', 'woocommerce-lucky-wheel' ) ?></li>
                        </ul>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
	    <?php
	    $wheel_html = ob_get_clean();
	    $fields = [
		    'section_start' => [],
		    'section_end'   => [],
//		    'section_start' => [
//			    'accordion' => 1,
//			    'class'     => 'wlwl-wheel-grecaptcha-accordion',
//			    'title'     => esc_html__( 'Google reCAPTCHA', 'woocommerce-lucky-wheel' ),
//		    ],
//		    'section_end'   => [ 'accordion' => 1 ],
		    'fields_html'   => $wheel_html,
	    ];
	    ?>
        <div class="vi-ui bottom attached tab" data-tab="wheel_recaptcha">
		    <?php
		    $this->settings::villatheme_render_table_field( $fields );
		    ?>
        </div>
	    <?php

	    return '';
    }
    protected function coupon_options(){
        ?>
        <table class="form-table">
            <tbody>
            <tr class="wlwl-custom-coupon">
                <th><?php esc_html_e( 'Email restriction', 'woocommerce-lucky-wheel' ) ?></th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox"
                               class="checkbox" <?php checked( $this->settings->get_params( 'coupon', 'email_restriction' ), 'yes' ) ?>
                               name="wlwl_email_restriction" id="wlwl_email_restriction" value="yes">
                        <label for="wlwl_email_restriction"></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Add received email to coupon\'s allowed emails list', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th><?php esc_html_e( 'Allow free shipping', 'woocommerce-lucky-wheel' ) ?></th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox"
                               class="checkbox" <?php checked( $this->settings->get_params( 'coupon', 'allow_free_shipping' ), 'yes' ) ?>
                               name="wlwl_free_shipping" id="wlwl_free_shipping" value="yes">
                        <label for="wlwl_free_shipping"></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Check this box if the coupon grants free shipping. A ', 'woocommerce-lucky-wheel' ) ?>
                        <a href="https://docs.woocommerce.com/document/free-shipping/"
                           target="_blank"><?php esc_html_e( 'free shipping method', 'woocommerce-lucky-wheel' ); ?></a><?php esc_html_e( ' must be enabled in your shipping zone and be set to require "a valid free shipping coupon" (see the "Free Shipping Requires" setting).', 'woocommerce-lucky-wheel' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_expiry_date"><?php esc_html_e( 'Time to live', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui right labeled input">
                        <input type="number" min="0" name="wlwl_expiry_date" id="wlwl_expiry_date"
                               value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'expiry_date' ) ); ?>">
                        <label class="vi-ui label"><?php esc_html_e( 'Day(s)', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Coupon will expire after x day(s) since it\'s generated and sent', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_min_spend"><?php esc_html_e( 'Minimum spend', 'woocommerce-lucky-wheel' ) ?></label>

                </th>
                <td>
                    <input type="text" class="short wc_input_price" name="wlwl_min_spend"
                           id="wlwl_min_spend"
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'min_spend' ) ); ?>"
                           placeholder="<?php esc_html_e( 'No minimum', 'woocommerce-lucky-wheel' ) ?>">
                    <p class="description"><?php esc_html_e( 'The minimum spend to use the coupon.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>

            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_max_spend"><?php esc_html_e( 'Maximum spend', 'woocommerce-lucky-wheel' ) ?></label>

                </th>
                <td>
                    <input type="text" class="short wc_input_price" name="wlwl_max_spend"
                           id="wlwl_max_spend"
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'max_spend' ) ); ?>"
                           placeholder="<?php esc_html_e( 'No maximum', 'woocommerce-lucky-wheel' ) ?>">
                    <p class="description"><?php esc_html_e( 'The maximum spend to use the coupon.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th><?php esc_html_e( 'Individual use only', 'woocommerce-lucky-wheel' ) ?></th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" <?php checked( $this->settings->get_params( 'coupon', 'individual_use' ), 'yes' ) ?>
                               class="checkbox" name="wlwl_individual_use" id="wlwl_individual_use"
                               value="yes">
                        <label for="wlwl_individual_use"></label>
                    </div>
                    <p><?php esc_html_e( 'Check this box if the coupon cannot be used in conjunction with other coupons.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th><?php esc_html_e( 'Exclude sale items', 'woocommerce-lucky-wheel' ) ?></th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" <?php checked( $this->settings->get_params( 'coupon', 'exclude_sale_items' ), 'yes' ) ?>
                               class="checkbox" name="wlwl_exclude_sale_items"
                               id="wlwl_exclude_sale_items"
                               value="yes">
                        <label for="wlwl_exclude_sale_items"></label>
                    </div>
                    <p><?php esc_html_e( 'Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', 'woocommerce-lucky-wheel' ) ?></p>
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
					    $product_ids = $this->settings->get_params( 'coupon', 'product_ids' );
					    if ( !empty( $product_ids ) ) {
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
					    $exclude_product_ids = $this->settings->get_params( 'coupon', 'exclude_product_ids' );
					    if ( !empty( $exclude_product_ids ) ) {
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
					    $product_categories = $this->settings->get_params( 'coupon', 'product_categories' );
					    if ( !empty( $product_categories ) ) {
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
					    $exclude_product_categories = $this->settings->get_params( 'coupon', 'exclude_product_categories' );
					    if ( !empty( $exclude_product_categories ) ) {
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
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'limit_per_coupon' ) ); ?>"
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
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'limit_to_x_items' ) ); ?>"
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
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'limit_per_user' ) ); ?>"
                           placeholder="<?php esc_html_e( 'Unlimited Usage', 'woocommerce-lucky-wheel' ) ?>"
                           step="1" min="0">
                    <p><?php esc_html_e( 'How many times this coupon can be used by an individual user.', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr class="wlwl-custom-coupon">
                <th>
                    <label for="wlwl_coupon_code_prefix"><?php esc_html_e( 'Coupon code prefix', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input id="wlwl_coupon_code_prefix" type="text" name="wlwl_coupon_code_prefix"
                           value="<?php echo esc_attr( $this->settings->get_params( 'coupon', 'coupon_code_prefix' ) ); ?>">
                </td>
            </tr>
            </tbody>
        </table>
        <?php
	    return '';
    }
	public function email_options() {
		ob_start();
		?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                    <label for="subject"><?php esc_html_e( 'Email subject', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
					<?php
					ob_start();
					?>
                    <input id="subject" type="text" name="subject"
                           value="<?php echo esc_attr( htmlentities( $this->settings->get_params( 'result', 'email' )['subject'] ??'') ); ?>">
                    <p class="description"><?php esc_html_e( 'The subject of emails sending to customers which include discount coupon code.', 'woocommerce-lucky-wheel' ) ?></p>
                    <p>{coupon_label}
                        - <?php esc_html_e( 'Coupon label/custom label that customers win', 'woocommerce-lucky-wheel' ) ?></p>
					<?php
					$subject_html = ob_get_clean();
					$fields     = [
						'fields'   => [
							'subject' =>[
								'not_wrap_html' => 1,
								'html' => $subject_html,
							]
						],
					];
					$this->settings::villatheme_render_table_field( $fields );
					?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="heading"><?php esc_html_e( 'Email heading', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
					<?php
					ob_start();
					?>
                    <input id="heading" type="text" name="heading"
                           value="<?php echo esc_attr( htmlentities( $this->settings->get_params( 'result', 'email' )['heading'] ??'') ); ?>">
                    <p><?php esc_html_e( 'The heading of emails sending to customers which include discount coupon code.', 'woocommerce-lucky-wheel' ) ?></p>
                    <p>{coupon_label}
                        - <?php esc_html_e( 'Coupon label/custom label that customers win', 'woocommerce-lucky-wheel' ) ?></p>
					<?php
					$tmp_html = ob_get_clean();
					$fields     = [
						'fields'   => [
							'heading' =>[
								'not_wrap_html' => 1,
								'html' => $tmp_html,
							]
						],
					];
					$this->settings::villatheme_render_table_field( $fields );
					?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="content"><?php esc_html_e( 'Email content', 'woocommerce-lucky-wheel' ) ?></label>
                    <p><?php esc_html_e( 'The content of email sending to customers to inform them the coupon code they receive', 'woocommerce-lucky-wheel' ) ?></p>
                </th>
                <td>
					<?php
					$option = array( 'editor_height' => 300, 'media_buttons' => true );
					ob_start();
					wp_editor( stripslashes( $this->settings->get_params( 'result', 'email' )['content']??'' ), 'content', $option );
					$tmp_html = ob_get_clean();
					$fields     = [
						'fields'   => [
							'content' =>[
								'not_wrap_html' => 1,
								'editor_option' => $option,
								'html' => $tmp_html,
							]
						],
					];
					$this->settings::villatheme_render_table_field( $fields );
					?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <ul>
                        <li>{customer_name}
                            - <?php esc_html_e( 'Customer\'s name.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{coupon_code}
                            - <?php esc_html_e( 'Coupon code/custom value will be sent to customer.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{coupon_label}
                            - <?php esc_html_e( 'Coupon label/custom label that customers win', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{date_expires}
                            - <?php esc_html_e( 'Expiry date of the coupon.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{featured_products}
                            - <?php esc_html_e( 'List of featured products with product image thumbnail, product title, product price and a button linked to product page which is design the same as button {shop_now}(Beware of using this shortcode if your store has too many featured products)', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{shop_now}
                            - <?php esc_html_e( 'Button ' );
			                echo '<a class="wlwl-button-shop-now" href="' . esc_url( $this->settings->get_params( 'button_shop_url' ) ) . '" target="_blank" style="text-decoration:none;display:inline-block;padding:10px 30px;margin:10px 0;font-size:' . esc_attr( $this->settings->get_params( 'button_shop_size' ) ) . 'px;color:' . esc_attr( $this->settings->get_params( 'button_shop_color' ) ) . ';background:' . esc_attr( $this->settings->get_params( 'button_shop_bg_color' ) ) . ';">' . esc_html( $this->settings->get_params( 'button_shop_title' ) ) . '</a>' ?></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="footer_text"><?php esc_html_e( 'Footer text', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
					<?php
					ob_start();
					?>
                    <input name="footer_text" id="footer_text" type="text"
                           value="<?php if ( isset( $this->settings->get_params( 'result', 'email' )['footer_text'] ) ) {
						       echo esc_attr( $this->settings->get_params( 'result', 'email' )['footer_text'] );
					       } ?>">
                    <p><?php esc_html_e( 'Available placeholders: ', 'woocommerce-lucky-wheel' ) ?>
                        {site_title}, {site_address}</p>
					<?php
					$tmp_html = ob_get_clean();
					$fields     = [
						'fields'   => [
							'footer_text' =>[
								'not_wrap_html' => 1,
								'html' => $tmp_html,
							]
						],
					];
					$this->settings::villatheme_render_table_field( $fields );
					?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_suggested_products"><?php esc_html_e( 'Suggested products', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <select id="wlwl_suggested_products" name="wlwl_suggested_products[]"
                            multiple="multiple"
                            class="suggested-product-search"
                            data-placeholder="<?php esc_html_e( 'Please Fill In Your Product Title', 'woocommerce-lucky-wheel' ) ?>">
			            <?php
			            $product_ids = $this->settings->get_params( 'suggested_products' );
			            if ( is_array( $product_ids ) && !empty( $product_ids ) ) {
				            foreach ( $product_ids as $ps ) {
					            $suggest_product = get_post( $ps );
					            if ( $suggest_product ) {
						            echo '<option selected value="' . esc_attr( $ps ) . '">' . esc_html( $suggest_product->post_title ) . '</option>';
					            }
				            }
			            }
			            ?>
                    </select>
                    <p><?php esc_html_e( 'These products will be added at the end of email content with product image thumbnail, product title, product price and a button linked to product page which is design the same as button {shop_now}', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="footer_text"><?php esc_html_e( '"Shop now" button title', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
		            <?php
		            ob_start();
		            ?>
                    <input name="wlwl_button_shop_title" id="wlwl_button_shop_title" type="text"
                           value="<?php echo esc_attr( $this->settings->get_params( 'button_shop_title' ) ); ?>">
		            <?php
		            $tmp_html = ob_get_clean();
		            $fields     = [
			            'fields'   => [
				            'button_shop_title' =>[
					            'not_wrap_html' => 1,
					            'html' => $tmp_html,
				            ]
			            ],
		            ];
		            $this->settings::villatheme_render_table_field( $fields );
		            ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="footer_text"><?php esc_html_e( '"Shop now" button URL', 'woocommerce-lucky-wheel' ); ?></label>
                </th>
                <td>
		            <?php
		            ob_start();
		            ?>
                    <input name="wlwl_button_shop_url" id="wlwl_button_shop_url" type="text"
                           value="<?php echo esc_attr( $this->settings->get_params( 'button_shop_url' ) ); ?>">
		            <?php
		            $tmp_html = ob_get_clean();
		            $fields     = [
			            'fields'   => [
				            'button_shop_url' =>[
					            'not_wrap_html' => 1,
					            'html' => $tmp_html,
				            ]
			            ],
		            ];
		            $this->settings::villatheme_render_table_field( $fields );
		            ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wlwl_button_shop_color"><?php esc_html_e( '"Shop now" button text color', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_button_shop_color" id="wlwl_button_shop_color"
                           class="color-picker"
                           value="<?php echo esc_attr( $this->settings->get_params( 'button_shop_color' ) ); ?>"
                           style="background-color: <?php echo wp_kses_post( $this->settings->get_params( 'button_shop_color' ) ) ?>;">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wlwl_button_shop_bg_color"><?php esc_html_e( '"Shop now" button background color', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input type="text" name="wlwl_button_shop_bg_color"
                           id="wlwl_button_shop_bg_color" class="color-picker"
                           value="<?php echo esc_attr( $this->settings->get_params( 'button_shop_bg_color' ) ); ?>"
                           style="background-color: <?php echo wp_kses_post( $this->settings->get_params( 'button_shop_bg_color' ) ) ?>;">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wlwl_button_shop_size"><?php esc_html_e( '"Shop now" button font size', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui right labeled input">
                        <input type="number" name="wlwl_button_shop_size" id="wlwl_button_shop_size"
                               min="1"
                               value="<?php echo esc_attr( $this->settings->get_params( 'button_shop_size' ) ); ?>">
                        <label class="vi-ui label"><?php esc_html_e( 'PX', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
		$wheel_html = ob_get_clean();
		$fields     = [
			'section_start' => [
				'accordion' => 1,
				'active' => 1,
				'class'     => 'wlwl-wheel-after-finishing-spinning-accordion',
				'title'     => esc_html__( 'Customer Notification', 'woocommerce-lucky-wheel' ),
			],
			'section_end'   => [ 'accordion' => 1 ],
			'fields_html'   => $wheel_html,
		];
		$this->settings::villatheme_render_table_field( $fields );
		ob_start();
		?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                    <label for="admin_email_enable"><?php esc_html_e( 'Enable admin notification', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox">
                        <input type="checkbox" name="admin_email_enable" value="1"
                               id="admin_email_enable" <?php checked( $this->settings->get_params( 'result', 'admin_email' )['enable']??'', 1) ?>>
                        <label for="admin_email_enable"><?php esc_html_e( 'Enable', 'woocommerce-lucky-wheel' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Send admin notification when someone wins', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="admin_email_to"><?php esc_html_e( 'Send notification to:', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input id="admin_email_to" type="text" name="admin_email_to"
                           value="<?php echo esc_attr( isset( $this->settings->get_params( 'result', 'admin_email' )['address'] ) ? $this->settings->get_params( 'result', 'admin_email' )['address'] : '' ); ?>">
                    <p><?php esc_html_e( 'Send notification to this email when someone wins. The from email will be used if this field is blank', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="admin_email_subject"><?php esc_html_e( 'Notification Email subject', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input id="admin_email_subject" type="text" name="admin_email_subject"
                           value="<?php echo esc_attr( htmlentities( $this->settings->get_params( 'result', 'admin_email' )['subject']??'' ) ); ?>">
					<?php esc_html_e( 'The subject of emails sending to admin.', 'woocommerce-lucky-wheel' ) ?>

                </td>
            </tr>
            <tr>
                <th>
                    <label for="admin_email_heading"><?php esc_html_e( 'Notification Email heading', 'woocommerce-lucky-wheel' ) ?></label>
                </th>
                <td>
                    <input id="admin_email_heading" type="text" name="admin_email_heading"
                           value="<?php echo esc_attr( htmlentities( $this->settings->get_params( 'result', 'admin_email' )['heading']??'' ) ); ?>">
                    <p><?php esc_html_e( 'The heading of emails sending to admin.', 'woocommerce-lucky-wheel' ) ?></p>
                    <p>{coupon_label}
                        - <?php esc_html_e( 'Coupon label/custom label that customers win', 'woocommerce-lucky-wheel' ) ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="admin_email_content"><?php esc_html_e( 'Notification Email content', 'woocommerce-lucky-wheel' ) ?></label>
                    <p><?php esc_html_e( 'The content of email sending to admin.', 'woocommerce-lucky-wheel' ) ?></p>
                </th>
                <td><?php $option = array( 'editor_height' => 300, 'media_buttons' => true );
					wp_editor( stripslashes( $this->settings->get_params( 'result', 'admin_email' )['content'] ??''), 'admin_email_content', $option ); ?></td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <ul>
                        <li>{customer_name}
                            - <?php esc_html_e( 'Customer\'s name.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{customer_mobile}
                            - <?php esc_html_e( 'Customer\'s mobile.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{coupon_code}
                            - <?php esc_html_e( 'Coupon code/custom value will be sent to customer.', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{coupon_label}
                            - <?php esc_html_e( 'Coupon label/custom label that customers win', 'woocommerce-lucky-wheel' ) ?></li>
                        <li>{customer_email}
                            - <?php esc_html_e( 'Email of customer who wins', 'woocommerce-lucky-wheel' ) ?></li>
                    </ul>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
		$wheel_html = ob_get_clean();
		$fields     = [
			'section_start' => [
				'accordion' => 1,
				'class'     => 'wlwl-wheel-after-finishing-spinning-accordion',
				'title'     => esc_html__( 'Admin Notification', 'woocommerce-lucky-wheel' ),
			],
			'section_end'   => [ 'accordion' => 1 ],
			'fields_html'   => $wheel_html,
		];
		$this->settings::villatheme_render_table_field( $fields );
		return '';
	}
    protected function update_options(){
        ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="auto-update-key"></label><?php esc_html_e( 'Auto update key', 'woocommerce-lucky-wheel' ) ?>
                </th>
                <td>
                    <div class="fields">
                        <div class="ten wide field">
                            <input type="text" name="wlwl_update_key" id="auto-update-key"
                                   class="villatheme-autoupdate-key-field"
                                   value="<?php echo esc_attr( $this->settings->get_params( 'key' ) ); ?>">
                        </div>
                        <div class="six wide field">
                                        <span class="vi-ui button green villatheme-get-key-button"
                                              data-href="https://api.envato.com/authorization?response_type=code&client_id=villatheme-download-keys-6wzzaeue&redirect_uri=https://villatheme.com/update-key"
                                              data-id="21604585"><?php echo esc_html__( 'Get Key', 'woocommerce-lucky-wheel' ) ?></span>
                        </div>
                    </div>
				    <?php do_action( 'woocommerce-lucky-wheel_key' ) ?>
                    <p class="description"><?php echo wp_kses_post( 'Please fill your key what you get from <a target="_blank" href="https://villatheme.com/my-download">https://villatheme.com/my-download</a>. See <a target="_blank" href="https://villatheme.com/knowledge-base/how-to-use-auto-update-feature/">guide</a>.' ) ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
        return '';
    }
	public function settings_page() {
		$tabs       = array(
			'general'   => esc_html__( 'General', 'woocommerce-lucky-wheel' ),
			'popup'     => esc_html__( 'Pop-up', 'woocommerce-lucky-wheel' ),
			'wheel'     => esc_html__( 'Wheel Settings', 'woocommerce-lucky-wheel' ),
			'coupon'     => esc_html__( 'Unique Coupon', 'woocommerce-lucky-wheel' ),
			'email'     => esc_html__( 'Email', 'woocommerce-lucky-wheel' ),
			'email_api' => esc_html__( 'Email API', 'woocommerce-lucky-wheel' ),
			'update'    => esc_html__( 'Update', 'woocommerce-lucky-wheel' ),
		);
		$tab_active = array_key_first( $tabs );
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'WooCommerce Lucky Wheel Settings', 'woocommerce-lucky-wheel' ); ?></h2>
			<?php
			if ( $this->error  ) {
				printf( '<div id="message" class="error"><p><strong>%s</strong></p></div>', esc_html(  $this->error ) );
			}
			if ( $this->updated_sucessfully  ) {
				printf( '<div id="message" class="updated"><p><strong>%s</strong></p></div>', esc_html__( 'Your settings have been saved!', 'woocommerce-lucky-wheel' ) );
			}
			?>
			<form method="post" class="vi-ui small form">
				<?php wp_nonce_field( 'wlwl_settings_page_save', 'wlwl_nonce_field' ); ?>
				<div class="vi-ui top attached tabular menu">
					<?php
					foreach ( $tabs as $slug => $text ) {
						$active = $tab_active === $slug ? 'active' : '';
						printf( ' <div class="item %s" data-tab="%s">%s</div>', esc_attr( $active ), esc_attr( $slug ), esc_html( $text ) );
					}
					?>
				</div>
				<?php
				foreach ( $tabs as $slug => $text ) {
					$active = $tab_active === $slug ? ' active' : '';
					$method = str_replace( '-', '_', $slug ) . '_options';
					$fields = [];
					printf( '<div class="vi-ui bottom attached%s tab segment" data-tab="%s">', esc_attr( $active ), esc_attr( $slug ) );
					if ( method_exists( $this, $method ) ) {
						$fields = $this->$method();
					}
					$this->settings::villatheme_render_table_field( apply_filters( "wlwl_settings_fields", $fields, $slug ) );
					do_action( 'wlwl_settings_tab', $slug );
					printf( '</div>' );
				}
				?>
				<p class="wlwl-button-save-settings-container">
					<button class="vi-ui primary button labeled icon wlw-submit" name="wlwl_save_settings"><i
							class="icon save"></i><?php esc_html_e( 'Save', 'woocommerce-lucky-wheel' ); ?></button>
					<button type="submit" class="vi-ui button labeled icon"
					        name="wlwl_check_key"><i
							class="icon save"></i><?php esc_html_e( 'Save & Check Key', 'woocommerce-lucky-wheel' ); ?>
					</button>
				</p>
			</form>
		</div>
		<div class="woocommerce-lucky-wheel-preview preview-html-hidden">
			<div class="woocommerce-lucky-wheel-preview-overlay"></div>
			<div class="woocommerce-lucky-wheel-preview-html">
				<canvas id="wlwl_canvas"></canvas>
				<canvas id="wlwl_canvas1"></canvas>
				<canvas id="wlwl_canvas2"></canvas>
			</div>
		</div>
		<?php
		do_action( 'villatheme_support_woocommerce-lucky-wheel' );
	}
	public function add_menu(){
		add_menu_page(
			esc_html__( 'WooCommerce Lucky Wheel', 'woocommerce-lucky-wheel' ),
			esc_html__( 'WC Lucky Wheel', 'woocommerce-lucky-wheel' ),
			'manage_options',
			'woocommerce-lucky-wheel',
			array( $this, 'settings_page' ),
			'dashicons-wheel',
			2
		);
		add_submenu_page(
			'woocommerce-lucky-wheel',
			esc_html__( 'Wheel Prizes', 'woocommerce-lucky-wheel' ),
			esc_html__( 'Wheel Prizes', 'woocommerce-lucky-wheel' ),
			'manage_options',
			'edit.php?post_type=wlwl_wheel_prize'
		);
		add_submenu_page(
			'woocommerce-lucky-wheel',
			esc_html__( 'Emails', 'woocommerce-lucky-wheel' ),
			esc_html__( 'Emails', 'woocommerce-lucky-wheel' ),
			'manage_options',
			'edit.php?post_type=wlwl_email'
		);
	}
	public function admin_enqueue_scripts() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->settings::enqueue_style(
			array( 'woocommerce-lucky-wheel-admin-icon-style' ),
			array( 'admin-icon-style' ),
			array( 0 )
		);
		if ( !in_array($page ,['woocommerce-lucky-wheel']) ) {
			return;
		}
//		$this->settings::remove_other_script();
		wp_enqueue_editor();
		$this->settings::enqueue_style(
			array(
				'semantic-ui-accordion',
				'semantic-ui-button',
				'semantic-ui-checkbox',
				'semantic-ui-dropdown',
				'semantic-ui-segment',
				'semantic-ui-form',
				'semantic-ui-label',
				'semantic-ui-input',
				'semantic-ui-icon',
				'semantic-ui-table',
				'semantic-ui-message',
				'semantic-ui-menu',
				'semantic-ui-tab',
				'transition',
				'select2',
			),
			array(
				'accordion',
				'button',
				'checkbox',
				'dropdown',
				'segment',
				'form',
				'label',
				'input',
				'icon',
				'table',
				'message',
				'menu',
				'tab',
				'transition',
				'select2',
			),
			array( 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 )
		);
		$this->settings::enqueue_style(
			array(
				'woocommerce-lucky-wheel-admin-settings',
				'woocommerce-lucky-wheel-gift-icons-style',
				'woocommerce-lucky-wheel-fontselect',
			),
			array( 'admin-style', 'giftbox', 'fontselect-default' ),
			array()
		);
		$inline_css          = '';
		$popup_icon_color    = $this->settings->get_params( 'notify', 'popup_icon_color' );
		$popup_icon_bg_color = $this->settings->get_params( 'notify', 'popup_icon_bg_color' );
		if ( $popup_icon_color ) {
			$inline_css .= ".vi-ui.button.wheel-popup-icon.wheel-popup-icon-selected{color:{$popup_icon_color};}";
		}
		if ( $popup_icon_bg_color ) {
			$inline_css .= ".vi-ui.button.wheel-popup-icon.wheel-popup-icon-selected{background-color:{$popup_icon_bg_color};}";
		}
		wp_add_inline_style( 'woocommerce-lucky-wheel-admin-style', $inline_css );
		wp_enqueue_script( 'jquery-ui-sortable' );
		/*Color picker*/
		wp_enqueue_script(
			'iris', admin_url( 'js/iris.min.js' ), array(
			'jquery-ui-draggable',
			'jquery-ui-slider',
			'jquery-touch-punch'
		), VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION, true );
		wp_enqueue_script( 'media-upload' );
		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		$this->settings::enqueue_script(
			array(
				'wordpress-lucky-wheel-fontselect',
				'wordpress-lucky-wheel-address',
				'semantic-ui-checkbox',
				'semantic-ui-dropdown',
				'semantic-ui-accordion',
				'semantic-ui-tab',
				'transition',
				'select2'
			),
			array(
				'jquery.fontselect',
				'address',
				'checkbox',
				'dropdown',
				'accordion',
				'tab',
				'transition',
				'select2'
			),
			array( 1, 1, 1, 1, 1, 1, 1, 1 )
		);
		$this->settings::enqueue_script(
			array( 'woocommerce-lucky-wheel-admin' ),
			array( 'admin-javascript' ),
			array( 0 ),
		);
		wp_localize_script( 'woocommerce-lucky-wheel-admin', 'woo_lucky_wheel_params_admin', array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'bg_img_default'   => VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES . '2020.png',
			'nonce' => wp_create_nonce( 'wlwl_nonce' ),
			'time_on_close' => $this->settings->get_params( 'notify', 'time_on_close' ) ,
			'show_again' =>  $this->settings->get_params( 'notify', 'show_again' )  ,
			'time_on_close_unit' => $this->settings->get_params( 'notify', 'time_on_close_unit' ) ,
			'show_again_unit' =>  $this->settings->get_params( 'notify', 'show_again_unit' )  ,
		) );
	}
	public function save_settings() {
		if ( !isset( $_POST['wlwl_nonce_field'] ) || ! wp_verify_nonce( wc_clean($_POST['wlwl_nonce_field']), 'wlwl_settings_page_save' ) ) {
			return;
		}
		if ( ! isset( $_REQUEST['page'] ) || $_REQUEST['page'] != 'woocommerce-lucky-wheel' ) {
			return;
		}
		if ( !isset( $_POST['wlwl_save_settings'] ) && !isset( $_POST['wlwl_check_key'] ) ) {
			return;
		}
		if ( ! empty( $_POST['probability'] ) && is_array($_POST['probability'])) {
			if ( count( $_POST['probability'] ) < 3 ) {
				$this->error = esc_html__('There must be at least 3 rows!', 'woocommerce-lucky-wheel' );
				return;
			}
			if ( array_sum( $_POST['probability'] ) < 1 ) {
				$this->error = esc_html__('The total probability must greater than 0!', 'woocommerce-lucky-wheel' );
				return;
			}
			for ( $i = 0; $i < sizeof( wc_clean( $_POST['coupon_type'] ) ); $i ++ ) {
				if ( in_array( $_POST['coupon_type'][ $i ], array( 'fixed_cart', 'fixed_product', 'percent' ) ) ) {
					if ( $_POST['coupon_amount'][ $i ] < 0 ) {
						$this->error = esc_html__('The amount of Valued-coupon must be greater than or equal to zero!', 'woocommerce-lucky-wheel' );
                        break;
					}
				}
			}
            if ($this->error){
                return;
            }
		} else {
			$this->error = esc_html__('There must be at least 3 rows!', 'woocommerce-lucky-wheel' );
			return;
		}
		if ( isset( $_POST['custom_type_label'] ) && is_array( $_POST['custom_type_label'] ) ) {
			foreach ( $_POST['custom_type_label'] as $key => $val ) {
				if ( $val === '' ) {
					$this->error = esc_html__('Label cannot be empty.', 'woocommerce-lucky-wheel' );
					return;
				}
				if ( isset( $_POST['wlwl_existing_coupon'],$_POST['coupon_type'][ $key ] ) && is_array( $_POST['wlwl_existing_coupon'] ) ) {
					if ( $_POST['coupon_type'][ $key ] == 'existing_coupon' && ($_POST['wlwl_existing_coupon'][ $key ] == '' || $_POST['wlwl_existing_coupon'][ $key ] == 0 )) {
						$this->error = esc_html__('Please enter value for existing coupon.', 'woocommerce-lucky-wheel' );
						return;
					}
				}
				if ( isset( $_POST['custom_type_value'], $_POST['coupon_type'][ $key ] ) && is_array( $_POST['custom_type_value'] ) ) {
					if ( $_POST['coupon_type'][ $key ] == 'custom' && $_POST['custom_type_value'][ $key ] == '' ) {
						$this->error = esc_html__('Please enter value for custom type.', 'woocommerce-lucky-wheel' );
					}
				}
			}
		}
		global $woo_lucky_wheel_settings;
		$this->next_schedule = wp_next_scheduled( 'wlwl_reset_total_spins' );
		$args = array(
			'general'    => array(
				'enable'     => isset( $_POST['wlwl_enable'] ) ? sanitize_text_field( $_POST['wlwl_enable'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'mobile'     => isset( $_POST['wlwl_enable_mobile'] ) ? sanitize_text_field( $_POST['wlwl_enable_mobile'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'spin_num'   => isset( $_POST['wlwl_spin_num'] ) ? sanitize_text_field( $_POST['wlwl_spin_num'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'delay'      => isset( $_POST['wlwl_delay'] ) ? sanitize_text_field( $_POST['wlwl_delay'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'delay_unit' => isset( $_POST['wlwl_delay_unit'] ) ? sanitize_text_field( $_POST['wlwl_delay_unit'] ) : 's',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'notify'     => array(
				'position'                 => isset( $_POST['notify_position'] ) ? sanitize_text_field( $_POST['notify_position'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'size'                     => isset( $_POST['notify_size'] ) ? sanitize_text_field( $_POST['notify_size'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'color'                    => isset( $_POST['notify_color'] ) ? sanitize_text_field( $_POST['notify_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'popup_icon'               => isset( $_POST['wheel_popup_icon'] ) ? sanitize_text_field( $_POST['wheel_popup_icon'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'popup_icon_color'         => isset( $_POST['wheel_popup_icon_color'] ) ? sanitize_text_field( $_POST['wheel_popup_icon_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'popup_icon_bg_color'      => isset( $_POST['wheel_popup_icon_bg_color'] ) ? sanitize_text_field( $_POST['wheel_popup_icon_bg_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'popup_icon_border_radius' => isset( $_POST['wheel_popup_icon_border_radius'] ) ? sanitize_text_field( $_POST['wheel_popup_icon_border_radius'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'intent'                   => isset( $_POST['notify_intent'] ) ? sanitize_text_field( $_POST['notify_intent'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_again'               => isset( $_POST['notify_show_again'] ) ? sanitize_text_field( $_POST['notify_show_again'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'hide_popup'               => isset( $_POST['notify_hide_popup'] ) ? sanitize_text_field( $_POST['notify_hide_popup'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_wheel'               => isset( $_POST['show_wheel'] ) ? sanitize_text_field( $_POST['show_wheel'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'scroll_amount'            => isset( $_POST['scroll_amount'] ) ? sanitize_text_field( $_POST['scroll_amount'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_again_unit'          => isset( $_POST['notify_show_again_unit'] ) ? sanitize_text_field( $_POST['notify_show_again_unit'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_only_front'          => isset( $_POST['notify_frontpage_only'] ) ? sanitize_text_field( $_POST['notify_frontpage_only'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_only_blog'           => isset( $_POST['notify_blogpage_only'] ) ? sanitize_text_field( $_POST['notify_blogpage_only'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_only_shop'           => isset( $_POST['notify_shop_only'] ) ? sanitize_text_field( $_POST['notify_shop_only'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'conditional_tags'         => isset( $_POST['notify_conditional_tags'] ) ? stripslashes( sanitize_text_field( $_POST['notify_conditional_tags'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'time_on_close'            => isset( $_POST['notify_time_on_close'] ) ? stripslashes( sanitize_text_field( $_POST['notify_time_on_close'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'time_on_close_unit'       => isset( $_POST['notify_time_on_close_unit'] ) ? stripslashes( sanitize_text_field( $_POST['notify_time_on_close_unit'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'wheel_wrap' => array(
				'description'            => isset( $_POST['wheel_wrap_description'] ) ? wp_kses_post( stripslashes( $_POST['wheel_wrap_description'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'bg_image'               => isset( $_POST['wheel_wrap_bg_image'] ) ? sanitize_text_field( $_POST['wheel_wrap_bg_image'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'bg_color'               => isset( $_POST['wheel_wrap_bg_color'] ) ? sanitize_text_field( $_POST['wheel_wrap_bg_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'text_color'             => isset( $_POST['wheel_wrap_text_color'] ) ? sanitize_text_field( $_POST['wheel_wrap_text_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'spin_button'            => isset( $_POST['wheel_wrap_spin_button'] ) ? sanitize_text_field( stripslashes( $_POST['wheel_wrap_spin_button'] ) ) : 'Try Your Lucky',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'spin_button_color'      => isset( $_POST['wheel_wrap_spin_button_color'] ) ? sanitize_text_field( $_POST['wheel_wrap_spin_button_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'spin_button_bg_color'   => isset( $_POST['wheel_wrap_spin_button_bg_color'] ) ? sanitize_text_field( $_POST['wheel_wrap_spin_button_bg_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'pointer_position'       => isset( $_POST['pointer_position'] ) ? sanitize_text_field( $_POST['pointer_position'] ) : 'center',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'pointer_color'          => isset( $_POST['pointer_color'] ) ? sanitize_text_field( $_POST['pointer_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_center_image'     => isset( $_POST['wheel_center_image'] ) ? sanitize_text_field( $_POST['wheel_center_image'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_center_color'     => isset( $_POST['wheel_center_color'] ) ? sanitize_text_field( $_POST['wheel_center_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_border_color'     => isset( $_POST['wheel_border_color'] ) ? sanitize_text_field( $_POST['wheel_border_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_dot_color'        => isset( $_POST['wheel_dot_color'] ) ? sanitize_text_field( $_POST['wheel_dot_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'close_option'           => isset( $_POST['wheel_wrap_close_option'] ) ? sanitize_text_field( $_POST['wheel_wrap_close_option'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'font'                   => isset( $_POST['wlwl_google_font_select'] ) ? sanitize_text_field( $_POST['wlwl_google_font_select'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'gdpr'                   => isset( $_POST['gdpr_policy'] ) ? sanitize_textarea_field( $_POST['gdpr_policy'] ) : "off",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'gdpr_message'           => isset( $_POST['gdpr_message'] ) ? wp_kses_post( stripslashes( $_POST['gdpr_message'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'congratulations_effect' => isset( $_POST['congratulations_effect'] ) ? sanitize_text_field( $_POST['congratulations_effect'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'background_effect'      => isset( $_POST['background_effect'] ) ? sanitize_text_field( $_POST['background_effect'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'custom_css'             => isset( $_POST['custom_css'] ) ? wp_kses_post( stripslashes( $_POST['custom_css'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'wheel'      => array(
				'spinning_time'     => isset( $_POST['wheel_spinning_time'] ) ? sanitize_text_field( $_POST['wheel_spinning_time'] ) : 3,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_speed'       => isset( $_POST['wheel_speed'] ) ? sanitize_text_field( $_POST['wheel_speed'] ) : 1,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'coupon_type'       => isset( $_POST['coupon_type'] ) ? stripslashes_deep( array_map( 'sanitize_text_field', $_POST['coupon_type'] ) ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'coupon_amount'     => isset( $_POST['coupon_amount'] ) ? array_map( 'sanitize_text_field', $_POST['coupon_amount'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'email_templates'   => isset( $_POST['email_templates'] ) ? array_map( 'sanitize_text_field', $_POST['email_templates'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'custom_value'      => isset( $_POST['custom_type_value'] ) ? array_map( 'wlwl_sanitize_text_field', $_POST['custom_type_value'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'custom_label'      => isset( $_POST['custom_type_label'] ) ? array_map( 'wlwl_sanitize_text_field', $_POST['custom_type_label'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'existing_coupon'   => isset( $_POST['wlwl_existing_coupon'] ) ? array_map( 'sanitize_text_field', $_POST['wlwl_existing_coupon'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'probability'       => isset( $_POST['probability'] ) ? array_map( 'sanitize_text_field', $_POST['probability'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'prize_quantity'    => isset( $_POST['prize_quantity'] ) ? array_map( 'sanitize_text_field', $_POST['prize_quantity'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'bg_color'          => isset( $_POST['bg_color'] ) ? array_map( 'sanitize_text_field', $_POST['bg_color'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'slices_text_color' => isset( $_POST['slices_text_color'] ) ? array_map( 'sanitize_text_field', $_POST['slices_text_color'] ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'currency'          => isset( $_POST['wlwl_currency'] ) ? wp_kses_post( stripslashes( $_POST['wlwl_currency'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'show_full_wheel'   => isset( $_POST['show_full_wheel'] ) ? sanitize_text_field( $_POST['show_full_wheel'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'font_size'         => isset( $_POST['font_size'] ) ? sanitize_text_field( $_POST['font_size'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'wheel_size'        => isset( $_POST['wheel_size'] ) ? sanitize_text_field( $_POST['wheel_size'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'random_color'      => isset( $_POST['random_color'] ) ? sanitize_text_field( $_POST['random_color'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'quantity_label'    => isset( $_POST['quantity_label'] ) ? sanitize_text_field( $_POST['quantity_label'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),

			'result'                            => array(
				'auto_close'   => isset( $_POST['result-auto_close'] ) ? sanitize_text_field( $_POST['result-auto_close'] ) : 0,// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'email'        => array(
					'subject'     => isset( $_POST['subject'] ) ? stripslashes( sanitize_text_field( $_POST['subject'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'heading'     => isset( $_POST['heading'] ) ? stripslashes( sanitize_text_field( $_POST['heading'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'content'     => isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'footer_text' => isset( $_POST['footer_text'] ) ? stripslashes( sanitize_text_field( $_POST['footer_text'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				),
				'admin_email'  => array(
					'enable'  => isset( $_POST['admin_email_enable'] ) ? stripslashes( sanitize_text_field( $_POST['admin_email_enable'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'address' => isset( $_POST['admin_email_address'] ) ? stripslashes( sanitize_text_field( $_POST['admin_email_address'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'subject' => isset( $_POST['admin_email_subject'] ) ? stripslashes( sanitize_text_field( $_POST['admin_email_subject'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'heading' => isset( $_POST['admin_email_heading'] ) ? stripslashes( sanitize_text_field( $_POST['admin_email_heading'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'content' => isset( $_POST['admin_email_content'] ) ? wp_kses_post( $_POST['admin_email_content'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				),
				'notification' => array(
					'win'        => isset( $_POST['result_win'] ) ? wp_kses_post( stripslashes( $_POST['result_win'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'win_custom' => isset( $_POST['result_win_custom'] ) ? wp_kses_post( stripslashes( $_POST['result_win_custom'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'lost'       => isset( $_POST['result_lost'] ) ? wp_kses_post( stripslashes( $_POST['result_lost'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				)
			),
			'coupon'                            => array(
				'email_restriction'          => isset( $_POST['wlwl_email_restriction'] ) ? sanitize_text_field( $_POST['wlwl_email_restriction'] ) : 'no',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'allow_free_shipping'        => isset( $_POST['wlwl_free_shipping'] ) ? sanitize_text_field( $_POST['wlwl_free_shipping'] ) : 'no',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'expiry_date'                => isset( $_POST['wlwl_expiry_date'] ) ? sanitize_text_field( $_POST['wlwl_expiry_date'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'min_spend'                  => isset( $_POST['wlwl_min_spend'] ) ? wc_format_decimal( $_POST['wlwl_min_spend'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'max_spend'                  => isset( $_POST['wlwl_max_spend'] ) ? wc_format_decimal( $_POST['wlwl_max_spend'] ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'individual_use'             => isset( $_POST['wlwl_individual_use'] ) ? sanitize_text_field( $_POST['wlwl_individual_use'] ) : "no",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'exclude_sale_items'         => isset( $_POST['wlwl_exclude_sale_items'] ) ? sanitize_text_field( $_POST['wlwl_exclude_sale_items'] ) : "no",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'limit_per_coupon'           => isset( $_POST['wlwl_limit_per_coupon'] ) ? wc_clean( absint( $_POST['wlwl_limit_per_coupon'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'limit_to_x_items'           => isset( $_POST['wlwl_limit_to_x_items'] ) ? wc_clean( absint( $_POST['wlwl_limit_to_x_items'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'limit_per_user'             => isset( $_POST['wlwl_limit_per_user'] ) ? wc_clean( absint( $_POST['wlwl_limit_per_user'] ) ) : "",// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'product_ids'                => isset( $_POST['wlwl_product_ids'] ) ? wc_clean( stripslashes_deep( $_POST['wlwl_product_ids'] ) ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'exclude_product_ids'        => isset( $_POST['wlwl_exclude_product_ids'] ) ? wc_clean( stripslashes_deep( $_POST['wlwl_exclude_product_ids'] ) ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'product_categories'         => isset( $_POST['wlwl_product_categories'] ) ? wc_clean( stripslashes_deep( $_POST['wlwl_product_categories'] ) ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'exclude_product_categories' => isset( $_POST['wlwl_exclude_product_categories'] ) ? wc_clean( stripslashes_deep( $_POST['wlwl_exclude_product_categories'] ) ) : array(),// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'coupon_code_prefix'         => isset( $_POST['wlwl_coupon_code_prefix'] ) ? sanitize_text_field( $_POST['wlwl_coupon_code_prefix'] ) : ""// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'mailchimp'                         => array(
				'enable'       => isset( $_POST['mailchimp_enable'] ) ? sanitize_text_field( $_POST['mailchimp_enable'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'double_optin' => isset( $_POST['mailchimp_double_optin'] ) ? sanitize_text_field( $_POST['mailchimp_double_optin'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'api_key'      => isset( $_POST['mailchimp_api'] ) ? sanitize_text_field( $_POST['mailchimp_api'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'lists'        => isset( $_POST['mailchimp_lists'] ) ? sanitize_text_field( $_POST['mailchimp_lists'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'active_campaign'                   => array(
				'enable' => isset( $_POST['wlwl_enable_active_campaign'] ) ? sanitize_text_field( $_POST['wlwl_enable_active_campaign'] ) : 'off',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'key'    => isset( $_POST['wlwl_active_campaign_key'] ) ? sanitize_text_field( $_POST['wlwl_active_campaign_key'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'url'    => isset( $_POST['wlwl_active_campaign_url'] ) ? sanitize_text_field( $_POST['wlwl_active_campaign_url'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'list'   => isset( $_POST['wlwl_active_campaign_list'] ) ? sanitize_text_field( $_POST['wlwl_active_campaign_list'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'key'                               => isset( $_POST['wlwl_update_key'] ) ? sanitize_text_field( $_POST['wlwl_update_key'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_shop_title'                 => isset( $_POST['wlwl_button_shop_title'] ) ? sanitize_text_field( $_POST['wlwl_button_shop_title'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_shop_url'                   => isset( $_POST['wlwl_button_shop_url'] ) ? sanitize_text_field( $_POST['wlwl_button_shop_url'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_shop_color'                 => isset( $_POST['wlwl_button_shop_color'] ) ? sanitize_text_field( $_POST['wlwl_button_shop_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_shop_bg_color'              => isset( $_POST['wlwl_button_shop_bg_color'] ) ? sanitize_text_field( $_POST['wlwl_button_shop_bg_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_shop_size'                  => isset( $_POST['wlwl_button_shop_size'] ) ? sanitize_text_field( $_POST['wlwl_button_shop_size'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'suggested_products'                => isset( $_POST['wlwl_suggested_products'] ) ? wc_clean( stripslashes_deep( $_POST['wlwl_suggested_products'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'sendgrid'                          => array(
				'enable' => isset( $_POST['wlwl_sendgrid_enable'] ) ? sanitize_text_field( $_POST['wlwl_sendgrid_enable'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'key'    => isset( $_POST['wlwl_sendgrid_key'] ) ? stripslashes( sanitize_text_field( $_POST['wlwl_sendgrid_key'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
				'list'   => isset( $_POST['sendgrid_lists'] ) ? stripslashes( sanitize_text_field( $_POST['sendgrid_lists'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			),
			'ajax_endpoint'                     => isset( $_POST['ajax_endpoint'] ) ? sanitize_text_field( $_POST['ajax_endpoint'] ) : 'ajax',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_mobile_enable'        => isset( $_POST['custom_field_mobile_enable'] ) ? sanitize_text_field( $_POST['custom_field_mobile_enable'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_mobile_enable_mobile' => isset( $_POST['custom_field_mobile_enable_mobile'] ) ? sanitize_text_field( $_POST['custom_field_mobile_enable_mobile'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_mobile_required'      => isset( $_POST['custom_field_mobile_required'] ) ? sanitize_text_field( $_POST['custom_field_mobile_required'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_mobile_phone_countries'  => isset( $_POST['custom_field_mobile_phone_countries'] ) ? wc_clean( $_POST['custom_field_mobile_phone_countries'] ) : [],
			'custom_field_name_enable'          => isset( $_POST['custom_field_name_enable'] ) ? sanitize_text_field( $_POST['custom_field_name_enable'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_name_enable_mobile'   => isset( $_POST['custom_field_name_enable_mobile'] ) ? sanitize_text_field( $_POST['custom_field_name_enable_mobile'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'custom_field_name_required'        => isset( $_POST['custom_field_name_required'] ) ? sanitize_text_field( $_POST['custom_field_name_required'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'reset_spins_interval'              => isset( $_POST['reset_spins_interval'] ) ? absint( sanitize_text_field( $_POST['reset_spins_interval'] ) ) : '',
			'reset_spins_hour'                  => isset( $_POST['reset_spins_hour'] ) ? absint( sanitize_text_field( $_POST['reset_spins_hour'] ) ) : '',
			'reset_spins_minute'                => isset( $_POST['reset_spins_minute'] ) ? absint( sanitize_text_field( $_POST['reset_spins_minute'] ) ) : '',
			'reset_spins_second'                => isset( $_POST['reset_spins_second'] ) ? absint( sanitize_text_field( $_POST['reset_spins_second'] ) ) : '',
			'button_apply_coupon'               => isset( $_POST['wlwl_button_apply_coupon'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_apply_coupon_redirect'      => isset( $_POST['wlwl_button_apply_coupon_redirect'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon_redirect'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_apply_coupon_color'         => isset( $_POST['wlwl_button_apply_coupon_color'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_apply_coupon_bg_color'      => isset( $_POST['wlwl_button_apply_coupon_bg_color'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon_bg_color'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_apply_coupon_font_size'     => isset( $_POST['wlwl_button_apply_coupon_font_size'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon_font_size'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'button_apply_coupon_border_radius' => isset( $_POST['wlwl_button_apply_coupon_border_radius'] ) ? sanitize_text_field( $_POST['wlwl_button_apply_coupon_border_radius'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'metrilo_enable'                    => isset( $_POST['wlwl_metrilo_enable'] ) ? sanitize_text_field( $_POST['wlwl_metrilo_enable'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'metrilo_token'                     => isset( $_POST['wlwl_metrilo_token'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_metrilo_token'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'metrilo_tag'                       => isset( $_POST['wlwl_metrilo_tag'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_metrilo_tag'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'metrilo_subscribed'                => isset( $_POST['wlwl_metrilo_subscribed'] ) ? sanitize_text_field( $_POST['wlwl_metrilo_subscribed'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_hubspot'               => isset( $_POST['wlwl_enable_hubspot'] ) ? sanitize_text_field( $_POST['wlwl_enable_hubspot'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_hubspot_api'                  => isset( $_POST['wlwl_hubspot_api'] ) ? stripslashes( sanitize_text_field( $_POST['wlwl_hubspot_api'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_klaviyo'               => isset( $_POST['wlwl_enable_klaviyo'] ) ? sanitize_text_field( $_POST['wlwl_enable_klaviyo'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_klaviyo_api'                  => isset( $_POST['wlwl_klaviyo_api'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_klaviyo_api'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_klaviyo_version_api'          => isset( $_POST['wlwl_klaviyo_version_api'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_klaviyo_version_api'] ) ) : '',/*Delete after 30-06-2024*/// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_klaviyo_list'                 => isset( $_POST['wlwl_klaviyo_list'] ) ? wc_clean( $_POST['wlwl_klaviyo_list'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_sendinblue'            => isset( $_POST['wlwl_enable_sendinblue'] ) ? sanitize_text_field( $_POST['wlwl_enable_sendinblue'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendinblue_api'               => isset( $_POST['wlwl_sendinblue_api'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_sendinblue_api'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendinblue_list'              => isset( $_POST['wlwl_sendinblue_list'] ) ? wc_clean( $_POST['wlwl_sendinblue_list'] ) : [],// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_mailpoet'              => isset( $_POST['wlwl_enable_mailpoet'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_enable_mailpoet'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_mailpoet_list'                => isset( $_POST['wlwl_mailpoet_list'] ) ? wc_clean( $_POST['wlwl_mailpoet_list'] ) : [],// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_mailster'              => isset( $_POST['wlwl_enable_mailster'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_enable_mailster'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_mailster_list'                => isset( $_POST['wlwl_mailster_list'] ) ? wc_clean( $_POST['wlwl_mailster_list'] ) : [],// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_sendy'                 => isset( $_POST['wlwl_enable_sendy'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_enable_sendy'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendy_api'                    => isset( $_POST['wlwl_sendy_api'] ) ? wc_clean( $_POST['wlwl_sendy_api'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendy_login_url'              => isset( $_POST['wlwl_sendy_login_url'] ) ? wc_clean( $_POST['wlwl_sendy_login_url'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendy_brand'                  => isset( $_POST['wlwl_sendy_brand'] ) ? wc_clean( $_POST['wlwl_sendy_brand'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_sendy_list'                   => isset( $_POST['wlwl_sendy_list'] ) ? wc_clean( $_POST['wlwl_sendy_list'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_enable_funnelkit'             => isset( $_POST['wlwl_enable_funnelkit'] ) ? sanitize_text_field( stripslashes( $_POST['wlwl_enable_funnelkit'] ) ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_funnelkit_list'               => isset( $_POST['wlwl_funnelkit_list'] ) ? wc_clean( $_POST['wlwl_funnelkit_list'] ) : [],// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_funnelkit_status'             => isset( $_POST['wlwl_funnelkit_status'] ) ? wc_clean( $_POST['wlwl_funnelkit_status'] ) : '1',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_recaptcha_site_key'           => isset( $_POST['wlwl_recaptcha_site_key'] ) ? wc_clean( $_POST['wlwl_recaptcha_site_key'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_recaptcha_version'            => isset( $_POST['wlwl_recaptcha_version'] ) ? wc_clean( $_POST['wlwl_recaptcha_version'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_recaptcha_secret_theme'       => isset( $_POST['wlwl_recaptcha_secret_theme'] ) ? wc_clean( $_POST['wlwl_recaptcha_secret_theme'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_recaptcha_secret_key'         => isset( $_POST['wlwl_recaptcha_secret_key'] ) ? wc_clean( $_POST['wlwl_recaptcha_secret_key'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'wlwl_recaptcha'                    => isset( $_POST['wlwl_recaptcha'] ) ? wc_clean( $_POST['wlwl_recaptcha'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'choose_using_white_black_list'     => isset( $_POST['choose_using_white_black_list'] ) ? wc_clean( $_POST['choose_using_white_black_list'] ) : 'black_list',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'white_list'                        => isset( $_POST['white_list'] ) ? sanitize_textarea_field( $_POST['white_list'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'black_list'                        => isset( $_POST['black_list'] ) ? sanitize_textarea_field( $_POST['black_list'] ) : '',// phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
		$this->updated_sucessfully = 1;
		if ( isset( $_POST['wlwl_check_key'] ) ) {
			delete_transient( 'update_plugins' );
			delete_transient( 'villatheme_item_12884' );
			delete_option( 'woocommerce-lucky-wheel_messages' );
			do_action( 'villatheme_save_and_check_key_woocommerce-lucky-wheel', $args['key'] );
		}
		$enable               = $this->settings->get_params( 'general', 'enable' );
		$reset_spins_interval = $this->settings->get_params( 'reset_spins_interval' );
		$reset_spins_hour     = $this->settings->get_params( 'reset_spins_hour' );
		$reset_spins_minute   = $this->settings->get_params( 'reset_spins_minute' );
		$reset_spins_second   = $this->settings->get_params( 'reset_spins_second' );
		$args =  apply_filters( 'wlwl_update_settings_args',wp_parse_args( $args, get_option( '_wlwl_settings', $woo_lucky_wheel_settings ) ));
		update_option( '_wlwl_settings', $args );
		$woo_lucky_wheel_settings = $args;
		$this->settings           = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance( true );
		if ( empty( $args['reset_spins_interval'] ) || $args['general']['enable'] !== 'on' ) {
			$this->unschedule_event();
		} elseif (
			( $enable !== 'on' && $args['general']['enable'] === 'on' ) ||
			( $args['reset_spins_interval'] != $reset_spins_interval ) ||
			( $args['reset_spins_hour'] != $reset_spins_hour ) ||
			( $args['reset_spins_minute'] != $reset_spins_minute ) ||
			( $args['reset_spins_second'] != $reset_spins_second )
		) {
			$gmt_offset = intval( get_option( 'gmt_offset' ) );
			$this->unschedule_event();
			$schedule_time_local = strtotime( 'today' ) + HOUR_IN_SECONDS * abs( $args['reset_spins_hour'] ) + MINUTE_IN_SECONDS * abs( $args['reset_spins_minute'] ) + $args['reset_spins_second'] + abs( $args['reset_spins_interval'] ) * 86400;
			$schedule_time       = $schedule_time_local - HOUR_IN_SECONDS * $gmt_offset;

			if ( $schedule_time < time() ) {
				$schedule_time += DAY_IN_SECONDS;
			}

			/*Call here to apply new interval to cron_schedules filter when calling method wp_schedule_event*/
			$schedule = wp_schedule_event( $schedule_time, 'wlwl_reset_total_spins_interval', 'wlwl_reset_total_spins' );


			if ( $schedule !== false ) {
				$this->next_schedule = $schedule_time;
			} else {
				$this->next_schedule = '';
			}

		}
	}

	public function check_update() {
		/**
		 * Check update
		 */
		if ( class_exists( 'VillaTheme_Plugin_Check_Update' ) ) {
			$setting_url = admin_url( 'admin.php?page=woocommerce-lucky-wheel' );
			new VillaTheme_Plugin_Check_Update (
				VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION,                    // current version
				'https://villatheme.com/wp-json/downloads/v3',  // update path
				VI_WOOCOMMERCE_LUCKY_WHEEL_BASENAME,                  // plugin file slug
				'woocommerce-lucky-wheel', '12884', $this->settings->get_params( 'key' ), $setting_url
			);
			new VillaTheme_Plugin_Updater( VI_WOOCOMMERCE_LUCKY_WHEEL_BASENAME, 'woocommerce-lucky-wheel', $setting_url );

		}
	}
	public static function auto_color() {
		$color_arr = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::auto_color_arr();
		$palette     = json_decode( $color_arr ,true);
		?>
        <div class="color_palette" data-color_arr="<?php echo esc_attr($color_arr);?>">
			<?php
			foreach ($palette as $k => $v){
				if (empty($v['color']) || !is_array($v['color'])){
					return;
				}
				?>
                <div>
                    <div class="wlwl_color_palette" data-color_code="<?php echo esc_attr($k)?>"
                         style="background:<?php echo esc_attr(!empty($v['palette'])? $v['palette'] : end($v['color']))?>;"></div>
                </div>
				<?php
			}
			?>
        </div>
        <div class="auto_color_ok_cancel"><div class="vi-ui buttons"><span class="auto_color_ok positive vi-ui button"><?php esc_html_e( 'OK', 'woocommerce-lucky-wheel' ) ?></span>
                <div class="or"></div>
                <span class="auto_color_cancel vi-ui button">
           <?php esc_html_e( 'Cancel', 'woocommerce-lucky-wheel' ) ?>
        </span></div></div>
		<?php
	}
}