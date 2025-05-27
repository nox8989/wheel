<?php
/*
Class Name: VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendy
Author: Andy Ha (support@villatheme.com)
Author URI: https://villatheme.com
Copyright 2015 villatheme.com. All rights reserved.
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Sendy {
	protected $settings;
	protected $api_key;
	protected $login_url;
	protected $brand_id;

	function __construct() {
		$this->settings  = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->api_key   = $this->settings->get_params( 'wlwl_sendy_api' );
		$this->login_url = $this->settings->get_params( 'wlwl_sendy_login_url' );
		$this->brand_id  = $this->settings->get_params( 'wlwl_sendy_brand' );
		add_action('wlwl_settings_tab',[$this,'wlwl_settings_tab'],19,1);
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
				'wlwl_enable_sendy' => [
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
                                    <input type="checkbox" name="wlwl_enable_sendy"
                                           id="wlwl_enable_sendy" %s value="1">
                                    <label for="wlwl_enable_sendy"></label>
                                </div>', $this->settings->get_params( 'wlwl_enable_sendy' )?'checked':''),
					'desc'  =>  esc_html__( 'Turn on to use Sendy system', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'Sendy', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendy_api'       => [
					'wrap_class'     => 'wlwl-wlwl_enable_sendy-class',
					'type'     => 'input',
					'html'    => sprintf('<input type="text" id="wlwl_sendy_api" name="wlwl_sendy_api"
                                       value="%s">
                                <p>%s
                                    <a href="https://sendy.co/api" target="_blank">%s</a>
                                </p>',esc_attr( $this->settings->get_params( 'wlwl_sendy_api' ) ),
						esc_html__( '**The API key for connecting with your Sendy account. Get your API key ', 'woocommerce-lucky-wheel' ),
						esc_html__( 'here', 'woocommerce-lucky-wheel' )),
					'title'    => esc_html__( 'Sendy API Key', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendy_login_url'       => [
					'wrap_class'     => 'wlwl-wlwl_enable_sendy-class',
					'type'     => 'input',
					'html'    => sprintf('<input type="text" id="wlwl_sendy_login_url" name="wlwl_sendy_login_url" value="%s">
                                <p>%s</p>',esc_attr( $this->settings->get_params( 'wlwl_sendy_login_url' ) ),
						esc_html__( '**Your domain Sendy url, including http:// or https:// in the url. Example: https://sendy.co/demo', 'woocommerce-lucky-wheel' )),
					'title'    => esc_html__( 'Sendy login url', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_sendy_brand'       => [
					'wrap_class'     => 'wlwl-wlwl_enable_sendy-class',
					'type'     => 'select',
					'title'    => esc_html__( 'Sendy Brand', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$mail_brands = $this->get_brands();
		$mail_lists  = $this->get_lists();
		$sendy_brand = $this->settings->get_params( 'wlwl_sendy_brand' );
		ob_start();
		?>
		<select class="vi-ui fluid dropdown" name="wlwl_sendy_brand">
			<?php
			if ( is_array( $mail_brands ) && ! empty( $mail_brands ) ) {
				foreach ( $mail_brands as $key_m => $mail_brand ) {
					if (empty($mail_brand->id)){
						continue;
					}
					$selected = ( $mail_brand->id === $sendy_brand ) ? 'selected' : '';
					?>
                    <option value="<?php echo esc_attr( $mail_brand->id) ?>" <?php echo esc_attr($selected) ?>>
						<?php echo esc_html( $mail_brand->name ?? $mail_brand->id) ?>
                    </option>
					<?php
				}
			}
			?>
		</select>
		<?php
		$fields['fields']['wlwl_sendy_brand']['html'] = ob_get_clean();
		if (!empty($sendy_brand)){
			$fields['fields']['wlwl_sendy_list'] = [
				'wrap_class'     => 'wlwl-enable_sendy-class',
				'type'     => 'select',
				'title'    => esc_html__( 'Sendy list', 'woocommerce-lucky-wheel' ),
			];
			$sendy_list  = $this->settings->get_params( 'wlwl_sendy_list' );
			ob_start();
			?>
			<select class="vi-ui fluid dropdown" name="sendy_list">
				<?php
				if ( is_array( $mail_lists ) && ! empty( $mail_lists ) ) {
					foreach ( $mail_lists as $key_m => $mail_list ) {
						if (empty($mail_list->id)){
							continue;
						}
						$selected = ( $mail_list->id === $sendy_list ) ? 'selected' : '';
						?>
                        <option value="<?php echo esc_attr( $mail_list->id) ?>" <?php echo esc_attr($selected) ?>>
							<?php echo esc_html( $mail_list->name ?? $mail_list->id) ?>
                        </option>
						<?php
					}
				}
				?>
			</select>
			<?php
			$fields['fields']['wlwl_sendy_list']['html'] = ob_get_clean();
		}
		$this->settings::villatheme_render_table_field( $fields );
	}

	public function get_brands() {
		if ( ! $this->api_key || ! $this->login_url ) {
			return array();
		}

		try {
			$r = wp_remote_post( $this->login_url . '/api/brands/get-brands.php', [
				'body' => [
					'api_key' => $this->api_key,
				]
			] );

			$body = wp_remote_retrieve_body( $r );

			return json_decode( $body ) ?? [];

		} catch ( \Exception $e ) {

		}

		return [];
	}

	public function get_lists() {
		if ( ! $this->api_key || ! $this->login_url || ! $this->brand_id ) {
			return array();
		}

		try {
			$r = wp_remote_post( $this->login_url . '/api/lists/get-lists.php', [
				'body' => [
					'api_key'  => $this->api_key,
					'brand_id' => $this->brand_id,
				]
			] );

			$body = wp_remote_retrieve_body( $r );

			return json_decode( $body ) ?? [];

		} catch ( \Exception $e ) {

		}

		return [];
	}

	public function add_subscribe( $email = '', $firstname = '', $lastname = '', $language = '' ) {
		if ( ! $this->api_key || ! $this->login_url || ! $email ) {
			return;
		}
		$sendy_list_id = $this->settings->get_params( 'wlwl_sendy_list', '', $language );
		$body          = [
			'api_key' => $this->api_key,
			"name"    => $firstname . ' ' . $lastname,
			'email'   => $email,
			"list"    => $sendy_list_id,
			'hp'      => ''
		];
		try {
			$r = wp_remote_post( $this->login_url . '/subscribe', [
				'body' => $body,
			] );

		} catch ( \Exception $e ) {

		}
	}

}
