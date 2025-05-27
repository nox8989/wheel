<?php
/*
Class Name: VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Hubspot
Author: Andy Ha (support@villatheme.com)
Author URI: https://villatheme.com
Copyright 2015 villatheme.com. All rights reserved.
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Hubspot {
	protected $settings;
	protected $api_key;

	function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key  = $this->settings->get_params( 'wlwl_hubspot_api' );
		add_action( 'wlwl_settings_tab', [ $this, 'wlwl_settings_tab' ], 14, 1 );
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
				'wlwl_enable_hubspot' => [
					'type'  => 'checkbox',
					'html'  => sprintf( '<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="wlwl_enable_hubspot"
                                           id="wlwl_enable_hubspot" %s value="1">
                                    <label for="wlwl_enable_hubspot"></label>
                                </div>', $this->settings->get_params( 'wlwl_enable_hubspot' )? 'checked':'' ),
					'desc'  => esc_html__( 'Turn on to use Hubspot system', 'woocommerce-lucky-wheel' ),
					'title' => esc_html__( 'Hubspot', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_hubspot_api'    => [
					'wrap_class' => ' wlwl-wlwl_enable_hubspot-class',
					'type'       => 'input',
					'html'       => sprintf( '<input type="text" id="wlwl_hubspot_api" name="wlwl_hubspot_api"
                                       value="%s">
                                <p>%s
                                    <a href="https://knowledge.hubspot.com/integrations/how-do-i-get-my-hubspot-api-key"
                                       target="_blank">%s</a>
                                </p>', esc_attr( $this->settings->get_params( 'wlwl_hubspot_api' ) ),
						esc_html__( '**The API key for connecting with your Hubspot account. Get your API key ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here.', 'woocommerce-lucky-wheel' ) ),
					'title'      => esc_html__( 'Hubspot API Key', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$this->settings::villatheme_render_table_field( $fields );
	}


	public function add_recipient( $email = '', $firstname = '', $lastname = '', $phone = '' ) {
		if ( ! $this->api_key ) {
			return;
		}

		if ( ! $email ) {
			return;
		}
		$arr = array(
			'properties' => array(
				array(
					'property' => 'email',
					'value'    => $email
				),
				array(
					'property' => 'firstname',
					'value'    => $firstname
				),
				array(
					'property' => 'lastname',
					'value'    => $lastname
				),
				array(
					'property' => 'phone',
					'value'    => $phone
				)
			)
		);


		$res = wp_remote_post( 'https://api.hubapi.com/contacts/v1/contact?hapikey=' . $this->api_key, [
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $arr )
		] );
		if ( wp_remote_retrieve_response_code( $res ) == 409 ) {
			$body = json_decode( wp_remote_retrieve_body( $res ) );
			$vid  = $body->identityProfile->vid;
			$res  = wp_remote_post( 'https://api.hubapi.com/contacts/v1/contact/vid/' . $vid . '/profile?hapikey=' . $this->api_key, [
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $arr )
			] );
		}
	}
}
