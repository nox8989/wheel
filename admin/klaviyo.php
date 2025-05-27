<?php
/*
Class Name: VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Klaviyo
Author: Andy Ha (support@villatheme.com)
Author URI: https://villatheme.com
Copyright 2015 villatheme.com. All rights reserved.
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Klaviyo {
	protected $settings;
	protected $api_key;
	protected $version_name;

	public function __construct() {
		$this->settings     = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key      = $this->settings->get_params( 'wlwl_klaviyo_api' );
		$this->version_name = '2024-02-15';//$this->settings->get_params( 'wlwl_klaviyo_version_api' );
		add_action( 'wlwl_settings_tab', [ $this, 'wlwl_settings_tab' ], 15, 1 );
	}

	public function wlwl_settings_tab( $tab ) {
		if ( $tab !== 'email_api' ) {
			return;
		}
		$this->settings      = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance( true );
		$fields              = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'wlwl_enable_klaviyo' => [
					'type'  => 'checkbox',
					'html'  => sprintf( '<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="wlwl_enable_klaviyo"
                                           id="wlwl_enable_klaviyo" %s value="1">
                                    <label for="wlwl_enable_klaviyo"></label>
                                </div>', $this->settings->get_params( 'wlwl_enable_klaviyo' ) ? 'checked':''),
					'desc'  => esc_html__( 'Turn on to use Klaviyo system', 'woocommerce-lucky-wheel' ),
					'title' => esc_html__( 'Klaviyo', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_klaviyo_api'    => [
					'wrap_class' => ' wlwl-wlwl_enable_klaviyo-class',
					'type'       => 'input',
					'html'       => sprintf( '<input type="text" id="wlwl_klaviyo_api" name="wlwl_klaviyo_api" value="%s">
                                <p>%s
                                    <a href="https://developers.klaviyo.com/en/docs/retrieve-api-credentials"  target="_blank">%s</a>
                                </p>', esc_attr( $this->settings->get_params( 'wlwl_klaviyo_api' ) ),
						esc_html__( '**The API key for connecting with your Klaviyo account. Get your API key ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here', 'woocommerce-lucky-wheel' ) ),
					'title'      => esc_html__( 'Klaviyo API Key', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_klaviyo_list'   => [
					'wrap_class' => ' wlwl-wlwl_enable_klaviyo-class',
					'type'       => 'select',
					'title'      => esc_html__( 'Klaviyo lists', 'woocommerce-lucky-wheel' ),
				],
			],
		];

		$mail_lists   = $this->get_lists();
		$klaviyo_list = $this->settings->get_params( 'wlwl_klaviyo_list' );
		ob_start();
		?>
        <select class="vi-ui fluid dropdown" name="wlwl_klaviyo_list">
			<?php

			if ( is_array( $mail_lists ) && ! empty( $mail_lists ) ) {
				foreach ( $mail_lists as $key_m => $mail_list ) {
					if ( empty( $mail_list['list_id'] ) ) {
						continue;
					}
                    $selected = $klaviyo_list == $mail_list['list_id'] ? 'selected':'';
					?>
                    <option value="<?php echo esc_attr( $mail_list['list_id']) ?>" <?php echo esc_attr($selected) ?>>
                        <?php echo esc_html( $mail_list['list_name'] ?? $mail_list['list_id'] ) ?>
                    </option>
					<?php
				}
			}
			?>
        </select>
		<?php
		$fields['fields']['wlwl_klaviyo_list']['html'] = ob_get_clean();
		$this->settings::villatheme_render_table_field( $fields );
	}

	public function get_lists() {
		if ( ! $this->api_key ) {
			return array();
		}
		$result = [];
		try {
			if ( ! empty( $this->version_name ) && ( $this->version_name !== '1-2' ) ) {
				$headers = [
					'Authorization' => 'Klaviyo-API-Key ' . $this->api_key,
					'Accept'        => 'application/json',
					'revision'      => $this->version_name
				];

				$r    = wp_remote_get( 'https://a.klaviyo.com/api/lists/', [
					'headers' => $headers
				] );
				$body = villatheme_json_decode( wp_remote_retrieve_body( $r ) );
				if ( isset( $body['data'] ) ) {
					$body_data = $body['data'];

					foreach ( $body_data as $list_data ) {
						$list_attribute = $list_data['attributes'] ?? [];
						$result[]       = [
							'list_id'   => $list_data['id'] ?? '',
							'list_name' => $list_attribute['name'] ?? '',
						];
					}
				}


			} else {
				$r      = wp_remote_get( 'https://a.klaviyo.com/api/v2/lists?api_key=' . $this->api_key );
				$result = villatheme_json_decode( wp_remote_retrieve_body( $r ) );
			}

			return $result;

		} catch ( \Exception $e ) {

		}

		return [];
	}


	public function add_recipient( $email = '', $list_id = '', $firstname = '', $lastname = '', $phone = '' ) {
		if ( ! $this->api_key || ! $email || ! $list_id ) {
			return;
		}

		if ( ! empty( $this->version_name ) && ( $this->version_name !== '1-2' ) ) {

			$add_profile = $this->add_profile( $email, $firstname, $lastname, $phone );

			if ( ! empty( $add_profile ) ) {
				$data_profile = $add_profile['data'] ?? [];
				$id_profile   = $data_profile['id'] ?? '';
				if ( ! empty( $id_profile ) ) {
					$headers = [
						'Authorization' => 'Klaviyo-API-Key ' . $this->api_key,
						'Accept'        => 'application/json',
						'content-type'  => 'application/json',
						'revision'      => $this->version_name
					];
					$body    = wp_json_encode( [
						'data' => [
							[
								'type' => 'profile',
								'id'   => $id_profile,/*ID profile*/
							]
						]

					] );
					try {
						$r = wp_remote_post( "https://a.klaviyo.com/api/lists/{$list_id}/relationships/profiles/", [
							'headers' => $headers,
							'body'    => $body,
						] );
					} catch ( Exception $e ) {

					}

				}
			}

		} else {
			$body = wp_json_encode( [
				'profiles' => [
					[
						'email'        => $email,
						'first_name'   => $firstname,
						'last_name'    => $lastname,
						'phone_number' => strval( $phone ),
					]
				]
			] );
			$r    = wp_remote_post( "https://a.klaviyo.com/api/v2/list/{$list_id}/members?api_key=" . $this->api_key, [
				'body'    => $body,
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
			] );
			if ( wp_remote_retrieve_response_code( $r ) == 400 ) {
				$body = wp_json_encode( [
					'profiles' => [
						[
							'email'      => $email,
							'first_name' => $firstname,
							'last_name'  => $lastname,
						]
					]
				] );
				$r    = wp_remote_post( "https://a.klaviyo.com/api/v2/list/{$list_id}/members?api_key=" . $this->api_key, [
					'body'    => $body,
					'headers' => [
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					],
				] );
			}
		}


	}

	public function add_profile( $email = '', $firstname = '', $lastname = '', $phone = '' ) {
		if ( ! empty( $firstname ) && empty( $lastname ) ) {
			$arr_name = explode( ' ', $firstname );/*separates the full name if there are spaces, Ex: Join Smith*/
			$lastname = $arr_name[1] ?? ''; /*get the last name: Ex: Smith*/
		}
		$attributes_profile = [
			'email'      => $email,
			'first_name' => $firstname,
			'last_name'  => $lastname
		];
		if ( ! empty( $phone ) ) {
			$attributes_profile['phone_number'] = strval( $phone );
		}
		$body = wp_json_encode( [
			'data' => [
				'type'       => 'profile',
				'attributes' => $attributes_profile
			]
		] );

		$headers = [
			'Authorization' => 'Klaviyo-API-Key ' . $this->api_key,
			'Accept'        => 'application/json',
			'content-type'  => 'application/json',
			'revision'      => $this->version_name
		];

		try {
			$r    = wp_remote_post( "https://a.klaviyo.com/api/profiles/", [
				'headers'     => $headers,
				'body'        => $body,
				'method'      => 'POST',
				'data_format' => 'body'
			] );
			$body = villatheme_json_decode( wp_remote_retrieve_body( $r ) );
			if ( ! empty( $body['errors'] ) ) {
				$profile_meta     = isset( $body['errors'][0]['meta'] ) ? $body['errors'][0]['meta'] : [];
				$profile_id_exist = $profile_meta['duplicate_profile_id'] ?? '';
				$r_2              = wp_remote_get( "https://a.klaviyo.com/api/profiles/" . $profile_id_exist, [
					'headers' => $headers,
				] );
				$body             = villatheme_json_decode( wp_remote_retrieve_body( $r_2 ) );
			}

			return ( $body );

		} catch ( \Exception $e ) {
		}

		return [];

	}

}
