<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendinblue {
	protected $settings;
	protected $api_key;

	function __construct() {
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key  = $this->settings->get_params( 'wlwl_sendinblue_api' );
		add_action('wlwl_settings_tab',[$this,'wlwl_settings_tab'],16,1);
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
				'wlwl_enable_sendinblue' => [
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="wlwl_enable_sendinblue"
                                           id="wlwl_enable_sendinblue" %s value="1">
                                    <label for="wlwl_enable_sendinblue"></label>
                                </div>',$this->settings->get_params( 'wlwl_enable_sendinblue' ) == 1 ? 'checked':''),
					'desc'  =>  esc_html__( 'Turn on to use Brevo system', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'Brevo (Sendinblue)', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendinblue_api'       => [
					'wrap_class'     => ' wlwl-wlwl_enable_sendinblue-class',
					'type'     => 'input',
					'html'    => sprintf('<input type="text" id="wlwl_sendinblue_api" name="wlwl_sendinblue_api"
                                       value="%s">
                                <p>%s
                                    <a href="https://app.brevo.com/settings/keys/api"
                                       target="_blank">%s</a>
                                </p>',esc_attr( $this->settings->get_params( 'wlwl_sendinblue_api' ) ),
						esc_html__( '**The API key for connecting with your Brevo account. Get your API key ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here', 'woocommerce-lucky-wheel' )),
					'title'    => esc_html__( 'Brevo API Key', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendinblue_list'       => [
					'wrap_class'     => ' wlwl-wlwl_enable_sendinblue-class',
					'type'     => 'select',
					'title'    => esc_html__( 'Brevo lists', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$mail_lists     = $this->get_lists();
		$sendinblue_list = $this->settings->get_params( 'wlwl_sendinblue_list' );
        if (!is_array($sendinblue_list)){
	        $sendinblue_list = [];
        }
		ob_start();
		?>
		<select class="vi-ui fluid dropdown" name="wlwl_sendinblue_list[]" multiple>
			<?php
			if ( is_array( $mail_lists ) && ! empty( $mail_lists ) ) {
				foreach ( $mail_lists as $mail_list ) {
					$selected = in_array( $mail_list->id, $sendinblue_list ) ? 'selected' : '';
					?>
                    <option value="<?php echo esc_attr( $mail_list->id) ?>" <?php echo esc_attr($selected) ?>>
						<?php echo esc_html( $mail_list->name ?? $mail_list->id) ?>
                    </option>
					<?php
				}
			}
			?>
		</select>
		<p><?php esc_html_e( 'When you are mapping to the SMS field, the Mobile Number should be passed with the proper country code.', 'woocommerce-lucky-wheel' ) ?></p>
		<p><?php esc_html_e( 'For example, it can only accept the value to be either +91xxxxxxxxxx or 0091xxxxxxxxxx form. Any other value entered would result in an error, hence the form submission will not be successful.', 'woocommerce-lucky-wheel' ) ?></p>
		<p><?php esc_html_e( 'This means that the field type has to be a TEXT type if you want it to accept both formats (i.e +91xxxxxxxxxx or 0091xxxxxxxxxx). E.g 0061467029760 or +61467029760', 'woocommerce-lucky-wheel' ) ?></p>
		<?php
		$fields['fields']['wlwl_sendinblue_list']['html'] = ob_get_clean();
		$this->settings::villatheme_render_table_field( $fields );
	}

	public function get_lists() {
		if ( ! $this->api_key ) {
			return array();
		}

		try {
			$r = wp_remote_get( 'https://api.brevo.com/v3/contacts/lists?limit=50&offset=0&sort=desc', [
				'headers' => [
					'api-key'      => $this->api_key,
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				]
			] );

			$body = wp_remote_retrieve_body( $r );

			return json_decode( $body )->lists ?? [];

		} catch ( \Exception $e ) {

		}

		return [];
	}

	public function add_recipient( $email = '', $list_id = [], $firstname = '', $lastname = '', $mobile = '' ) {
		if ( ! $this->api_key || ! $email ) {
			return;
		}
		$list_id    = array_map( 'absint', (array) $list_id );
		$attributes = [
			"FIRSTNAME" => $firstname,
			"LASTNAME"  => $lastname
		];
		if ( ! empty( $mobile ) ) {
			$attributes['SMS'] = (string) $mobile;
		}
		$body = wp_json_encode( [
			'email'      => $email,
			"attributes" => $attributes,
			"listIds"    => $list_id,
		] );
		try {
			$r    = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
				'body'    => $body,
				'headers' => [
					'api-key'      => $this->api_key,
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
			] );
			$body = wp_remote_retrieve_body( $r );

		} catch ( \Exception $e ) {

		}

	}

}
