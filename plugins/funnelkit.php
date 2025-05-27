<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_Funnelkit{
	protected $settings;
	public function __construct() {
		if (!class_exists( 'BWFCRM_Contact' ) ){
			return;
		}
		add_action('wlwl_settings_tab',[$this,'wlwl_settings_tab'],20,1);
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
				'wlwl_enable_funnelkit' => [
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
                                        <input type="checkbox" name="wlwl_enable_funnelkit"
                                               id="wlwl_enable_funnelkit" %s value="1">
                                        <label for="wlwl_enable_funnelkit"></label>
                                    </div>',checked( $this->settings->get_params( 'wlwl_enable_funnelkit' ), 1 )),
					'desc'  =>  esc_html__( 'Turn on to use FunnelKit system', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'FunnelKit', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_funnelkit_list'       => [
					'wrap_class'     => 'wlwl-wlwl_enable_funnelkit-class',
					'type'     => 'select',
					'title'    => esc_html__( 'FunnelKit lists', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_funnelkit_status'       => [
					'wrap_class'     => 'wlwl-wlwl_enable_funnelkit-class',
					'type'     => 'select',
					'title'    => esc_html__( 'FunnelKit Status', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$funnelkit_lists = BWFCRM_Lists::get_lists();
		$funnelkit_selected_list = $this->settings->get_params( 'wlwl_funnelkit_list' );
		ob_start();
		?>
        <select class="vi-ui fluid dropdown" name="wlwl_funnelkit_list[]"
                id="wlwl_funnelkit_list" multiple>
			<?php
			foreach ( $funnelkit_lists as $list ) {
                if (empty($list['ID'])){
                    continue;
                }
				$selected = in_array( $list['ID'], (array) $funnelkit_selected_list ) ? 'selected' : '';
				printf( '<option value="%s" %s>%s</option>',
					esc_attr( $list['ID'] ), esc_attr( $selected ), esc_html( $list['name'] ?? $list['ID']) );
			}
			?>
        </select>
		<?php
		$fields['fields']['wlwl_funnelkit_list']['html'] = ob_get_clean();
        $funnelkit_status= $this->settings->get_params( 'wlwl_funnelkit_status' );
		ob_start();
		?>
        <select class="vi-ui fluid dropdown" name="wlwl_funnelkit_status" id="wlwl_funnelkit_status">
            <option value="0" <?php selected( $funnelkit_status, '0' ) ?>><?php esc_html_e( 'Unverified', 'woocommerce-lucky-wheel' ) ?></option>
            <option value="1" <?php selected( $funnelkit_status, '1' ) ?>><?php esc_html_e( 'Subscribed', 'woocommerce-lucky-wheel' ) ?></option>
            <option value="2" <?php selected( $funnelkit_status, '2' ) ?>><?php esc_html_e( 'Bounced', 'woocommerce-lucky-wheel' ) ?></option>
        </select>
		<?php
		$fields['fields']['wlwl_funnelkit_status']['html'] = ob_get_clean();
		$this->settings::villatheme_render_table_field( $fields );
	}

}