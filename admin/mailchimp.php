<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Mailchimp {
	protected $settings;
	protected $api_key;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key  = $this->settings->get_params( 'mailchimp', 'api_key' );
		add_action('wlwl_settings_tab',[$this,'wlwl_settings_tab'],10,1);
	}
	public function wlwl_settings_tab($tab){
		if ($tab !== 'email_api'){
			return;
		}
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance(true);
		$fields = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'mailchimp_enable' => [
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
						<input type="checkbox" name="mailchimp_enable"
						       id="mailchimp_enable" %s>
						<label for="mailchimp_enable"></label>
					</div>', $this->settings->get_params( 'mailchimp', 'enable' )== 'on' ? ' checked' : ''),
					'desc'  =>  esc_html__( 'Turn on to use MailChimp system', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'Enable Mailchimp', 'woocommerce-lucky-wheel' ),
				],
				'mailchimp_double_optin' => [
					'wrap_class'     => 'wlwl-mailchimp_enable-class',
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
						<input type="checkbox" name="mailchimp_double_optin"
						       id="wlwl_mailchimp_double_optin" %s>
						<label for="wlwl_mailchimp_double_optin"></label>
					</div>',$this->settings->get_params( 'mailchimp', 'double_optin' )== 'on' ? ' checked' : ''),
					'desc'  =>  esc_html__( 'If enabled, a confirm subscription email will be sent to each subscriber for them to confirm that they subscribe to your list.', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'Mailchimp double optin', 'woocommerce-lucky-wheel' ),
				],
				'mailchimp_api'       => [
					'wrap_class'     => 'wlwl-mailchimp_enable-class',
					'type'     => 'input',
					'html'    => sprintf('<input type="text" id="mailchimp_api" name="mailchimp_api"
					       value="%s">
					<p class="description">%s
						<a href="https://admin.mailchimp.com/account/api" target="_blank">%s</a>.
					</p>',esc_attr( $this->settings->get_params( 'mailchimp', 'api_key' ) ),
						esc_html__( ' The API key for connecting with your MailChimp account. Get your API key ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here', 'woocommerce-lucky-wheel' )),
					'title'    => esc_html__( 'API key', 'woocommerce-lucky-wheel' ),
				],
				'mailchimp_lists'       => [
					'wrap_class'     => 'wlwl-mailchimp_enable-class',
					'type'     => 'select',
					'title'    => esc_html__( 'Mailchimp lists', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$mail_lists     = $this->get_lists();
		$mailchimp_list = $mail_lists->lists ?? array();
		$mailchimp_lists_value = $this->settings->get_params( 'mailchimp', 'lists' );
		ob_start();
		?>
		<select class="select-who vi-ui fluid dropdown" name="mailchimp_lists" id="mailchimp_lists">
			<?php
			if ( is_array( $mailchimp_list ) && ! empty( $mailchimp_list ) ) {
				foreach ( $mailchimp_list as $mail_list ) {
                    if (!isset($mail_list->id )){
                        continue;
                    }
                    $selected = $mail_list->id == $mailchimp_lists_value ? 'selected' : '';
					?>
					<option value='<?php echo esc_attr( $mail_list->id ); ?>' <?php echo esc_attr($selected); ?> ><?php echo esc_html( $mail_list->name ?? $mail_list->id  ); ?></option>
					<?php
				}
			}
			?>
		</select>
		<?php
		$fields['fields']['mailchimp_lists']['html'] = ob_get_clean();
		$fields['fields']['mailchimp_lists']['value'] = $mailchimp_lists_value;
		$fields['fields']['mailchimp_lists']['mailchimp_list'] = $mailchimp_list;
		$this->settings::villatheme_render_table_field( $fields );
	}

	public function get_lists() {
		if ( $this->api_key ) {
			$dash_position = strpos( $this->api_key, '-' );
			if ( $dash_position !== false ) {
				$api_url = 'https://' . substr( $this->api_key, $dash_position + 1 ) . '.api.mailchimp.com/3.0/lists?fields=lists.name,lists.id&count=1000';
				$auth       = base64_encode( 'user:' . esc_attr( $this->api_key ) );

				try {
					$r = wp_remote_get( $api_url, [
						'headers' => [
							'Authorization' => "Basic $auth",
							'Accept'        => 'application/json',
							'Content-Type'  => 'application/json',
						],
					] );

					$body = wp_remote_retrieve_body( $r );

					return json_decode( $body );

				} catch ( \Exception $e ) {

					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}


	public function add_email( $email, $list_id, $fname = '', $lname = '', $phone = '', $birthday = '' ) {
		if ( $this->api_key && $list_id ) {
			$status = 'subscribed';
			if ( $this->settings->get_params( 'mailchimp', 'double_optin' ) == 'on' ) {
				$status = 'pending';
			}
			$data = array(
				'email_address' => $email,
				'status'        => $status,
				'merge_fields'  => array(
					'FNAME'    => $fname,
					'LNAME'    => $lname,
					'PHONE'    => $phone,
					'BIRTHDAY' => $birthday,
				),
			);

			$dataCenter = substr( $this->api_key, strpos( $this->api_key, '-' ) + 1 );
			$url        = 'https://' . esc_attr( $dataCenter ) . '.api.mailchimp.com/3.0/lists/' . esc_attr( $list_id ) . '/members/' . md5( strtolower( $email ) );
			$auth       = base64_encode( 'user:' . esc_attr( $this->api_key ) );

			try {
				$r = wp_remote_post( $url, [
					'method'  => 'PUT',
					'headers' => [
						'Authorization' => "Basic $auth",
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( $data ),
				] );

				$body = wp_remote_retrieve_body( $r );

				return true;

			} catch ( \Exception $e ) {

				return false;
			}
		} else {
			return false;
		}
	}


}
