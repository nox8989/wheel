<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Metrilo {
	protected $settings;
	protected $api_key;
	protected $url;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key  = $this->settings->get_params( 'metrilo_token' );
		add_action( 'wlwl_settings_tab', [ $this, 'wlwl_settings_tab' ], 13, 1 );
	}
	public function wlwl_settings_tab( $tab ) {
		if ( $tab !== 'email_api' ) {
			return;
		}
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance( true );
		$fields         = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'metrilo_enable'      => [
					'type'  => 'checkbox',
					'html'  => sprintf( '<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="metrilo_enable" value="1"
                                           id="metrilo_enable" %s>
                                    <label for="metrilo_enable"></label>
                                </div>', $this->settings->get_params( 'metrilo_enable' )?'checked':''),
					'desc'  => esc_html__( 'Turn on to use Metrilo system', 'woocommerce-lucky-wheel' ),
					'title' => esc_html__( 'Metrilo', 'woocommerce-lucky-wheel' ),
				],
				'metrilo_token'         => [
					'wrap_class' => 'wlwl-metrilo_enable-class',
					'type'       => 'input',
					'html'       => sprintf( '<input type="text" id="metrilo_token" name="metrilo_token"
                                       value="%s">', esc_attr( $this->settings->get_params( 'metrilo_token' ) ) ),
					'title'      => esc_html__( 'Metrilo Token', 'woocommerce-lucky-wheel' ),
				],
				'metrilo_tag'         => [
					'wrap_class' => 'wlwl-metrilo_enable-class',
					'type'       => 'input',
					'html'       => sprintf( ' <input type="text" id="metrilo_tag" name="metrilo_tag"
                                       value="%s">', esc_attr( $this->settings->get_params( 'metrilo_tag' ) ) ),
					'title'      => esc_html__( 'Metrilo tag', 'woocommerce-lucky-wheel' ),
					'desc'      => esc_html__( 'Please enter tags separated by comma(,)', 'woocommerce-lucky-wheel' ),
				],
				'metrilo_subscribed'      => [
					'wrap_class' => 'wlwl-metrilo_enable-class',
					'type'  => 'checkbox',
					'html'  => sprintf( '<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="metrilo_subscribed" value="1"
                                           id="metrilo_subscribed" %s>
                                    <label for="metrilo_subscribed">%s</label>
                                </div>', $this->settings->get_params( 'metrilo_subscribed' )?'checked':'',esc_html__( 'Yes', 'woocommerce-lucky-wheel' )),
					'desc'  => esc_html__( 'If the user has opted in for receiving emails.', 'woocommerce-lucky-wheel' ),
					'title' => esc_html__( 'Subscribed', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$this->settings::villatheme_render_table_field( $fields );
	}

	public function contact_add( $email, $firstname = '', $lastname = '', $language = '' ) {
		global $wp_version, $wc_version;
		$return = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);

		if ( $this->api_key ) {
			$time           = 1000 * time();
			$metrilo_tag    = $this->settings->get_params( 'metrilo_tag', '', $language );
			$request        = wp_remote_post( 'https://trk.mtrl.me/customer', array(
				'headers' => array( 'Content-Type' => 'text/plain' ),
				'timeout' => 10,
				'body'    => wp_json_encode( array(
					"time"          => $time,
					"token"         => $this->api_key,
					"platform"      => "Wordpress {$wp_version} / WooCommerce {$wc_version}",
					"pluginVersion" => VI_WOOCOMMERCE_LUCKY_WHEEL_VERSION,
					"params"        => array(
						"email"      => $email,
						"createdAt"  => $time,
						"firstName"  => $firstname,
						"lastName"   => $lastname,
						"subscribed" => boolval( $this->settings->get_params( 'metrilo_subscribed' ) ),
						"tags"       => $metrilo_tag ? array_values( array_filter( explode( ',', $metrilo_tag ) ) ) : array()
					)
				) )
			) );
			$code           = wp_remote_retrieve_response_code( $request );
			$return['code'] = $code;
			$return['data'] = wp_remote_retrieve_response_message( $request );
			if ( ! is_wp_error( $request ) ) {
				if ( $code == 200 ) {
					$return['status'] = 'success';
				}
			} else {
				$return['data'] = $request->get_error_message();
			}
		} else {
			$return['data'] = esc_html__( 'Missing token', 'woocommerce-lucky-wheel' );
		}

		return $return;
	}
}
