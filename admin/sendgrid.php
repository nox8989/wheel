<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendgrid {
	protected $settings;
	protected $api_key;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key  =  $this->settings->get_params('sendgrid','key');
		add_action( 'wlwl_settings_tab', [ $this, 'wlwl_settings_tab' ], 12, 1 );
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
				'wlwl_sendgrid_enable'      => [
					'type'  => 'checkbox',
					'html'  => sprintf( '<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="wlwl_sendgrid_enable"
                                           id="wlwl_sendgrid_enable" %s >
                                    <label for="wlwl_sendgrid_enable"></label>
                                </div>', $this->settings->get_params( 'sendgrid', 'enable' )== 'on' ? ' checked' : '' ),
					'desc'  => esc_html__( 'Turn on to use SendGrid system', 'woocommerce-lucky-wheel' ),
					'title' => esc_html__( 'SendGrid', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendgrid_key'         => [
					'wrap_class' => 'wlwl-wlwl_sendgrid_enable-class',
					'type'       => 'input',
					'html'       => sprintf( '<input type="text" id="wlwl_sendgrid_key" name="wlwl_sendgrid_key" value="%s">
                                <p>%s</p><p>%s <a href="https://app.sendgrid.com/settings/api_keys" target="_blank">%s</a></p>',
						esc_attr( $this->settings->get_params( 'sendgrid', 'key' ) ),
						esc_html__( '*This is the API key that\'s shown only once when your created it, not the API key ID.', 'woocommerce-lucky-wheel' ),
						esc_html__( '**This API Key must have full-access permission of API Keys. You can set it ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here.', 'woocommerce-lucky-wheel' ) ),
					'title'      => esc_html__( 'SendGrid API Key', 'woocommerce-lucky-wheel' ),
				],
				'sendgrid_lists' => [
					'wrap_class' => 'wlwl-wlwl_sendgrid_enable-class',
					'type'       => 'select',
					'title'      => esc_html__( 'Sendgrid lists', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$mail_lists    = $this->get_lists();
		$sendgrid_list = $this->settings->get_params( 'sendgrid', 'list' );
		ob_start();
		?>
		<select class="select-who vi-ui fluid dropdown" name="sendgrid_lists"
		        id="sendgrid_lists">
			<option value="none"><?php esc_html_e( 'Do not add to any list', 'woocommerce-lucky-wheel' ) ?></option>
			<?php
			if ( is_array( $mail_lists ) && count( $mail_lists ) ) {
				foreach ( $mail_lists as $key_m => $mail_list ) {
                    if (!isset( $mail_list->id )){
                        continue;
                    }
                    $selected = $sendgrid_list == $mail_list->id ? 'selected' : '';
					?>
					<option value="<?php echo esc_attr( $mail_list->id ) ?>" <?php echo esc_attr($selected) ?>><?php echo esc_html( $mail_list->name ?? $mail_list->id) ?></option>
					<?php
				}
			}
			?>
		</select>
		<?php
		$fields['fields']['sendgrid_lists']['html'] = ob_get_clean();
		$this->settings::villatheme_render_table_field( $fields );
	}


	public function get_lists() {
		if ( ! $this->api_key ) {
			return false;
		}
		$args = array(
			'headers' => array(
				'Authorization' =>  "Bearer " . $this->api_key
			)
		);
		$url        = "https://api.sendgrid.com/v3/marketing/lists";
		try {

			$r = wp_remote_get( $url,$args);

			$body = wp_remote_retrieve_body( $r );

			return  json_decode( $body ) ;

		} catch ( \Exception $e ) {
			return false;
		}

	}

	public function add_recipient( $email = '', $firstname = '', $lastname = '' ) {
		if ( ! $this->api_key ) {
			return;
		}
		if ( ! $email ) {
			return;
		}

		$args = [
			'method'  => 'PUT',
			'headers' => [
				"Authorization" => "Bearer " . $this->api_key,
				"Content-type"  => "application/json"
			],
			'body'    => wp_json_encode( [
				'contacts' => [
					[
						'email'      => $email,
						'first_name' => $firstname,
						'last_name'  => $lastname,
					]
				]
			] )
		];
		$res  = wp_remote_request( 'https://api.sendgrid.com/v3/marketing/contacts', $args );
	}

	public function add_recipient_to_list( $email = '', $list_id = '' ) {
		if ( ! $this->api_key ) {
			return;
		}
		if ( ! $email || ! $list_id ) {
			return;
		}
		$args = [
			'method'  => 'PUT',
			'headers' => [
				"Authorization" => "Bearer " . $this->api_key,
				"Content-type"  => "application/json"
			],
			'body'    => wp_json_encode( [
				'list_ids' => [ $list_id ],
				'contacts' => [
					[ 'email' => $email ]
				]
			] )
		];
		$res  = wp_remote_request( 'https://api.sendgrid.com/v3/marketing/contacts', $args );
	}
}
