<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_Mailster{
	protected $settings;
	public function __construct() {
		add_action('wlwl_settings_tab',[$this,'wlwl_settings_tab'],18,1);
	}
	public function wlwl_settings_tab($tab){
		if ($tab !== 'email_api'){
			return;
		}
		if (!function_exists( 'mailster' )){
			return;
		}
		$this->settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance(true);
		$fields = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'wlwl_enable_mailster' => [
					'type'  => 'checkbox',
					'html' => sprintf('<div class="vi-ui toggle checkbox checked">
                                        <input type="checkbox" name="wlwl_enable_mailster"
                                               id="wlwl_enable_mailster" %s value="1">
                                        <label for="wlwl_enable_mailster"></label>
                                    </div>',checked( $this->settings->get_params( 'wlwl_enable_mailster' ), 1 )),
					'desc'  =>  esc_html__( 'Turn on to use Mailster system', 'woocommerce-lucky-wheel' ) ,
					'title' => esc_html__( 'Mailster', 'woocommerce-lucky-wheel' ),
				],
				'wlwl_mailster_list'       => [
					'wrap_class'     => ' wlwl-wlwl_enable_mailster-class',
					'type'     => 'select',
					'title'    => esc_html__( 'Mailster lists', 'woocommerce-lucky-wheel' ),
				],
			],
		];
		$mailster_lists = mailster( 'lists' )->get();
		$mailster_selected_list = $this->settings->get_params( 'wlwl_mailster_list' ) ?? [];
		ob_start();
		?>
        <select class="vi-ui fluid dropdown" name="wlwl_mailster_list[]"
                id="wlwl_mailster_list" multiple>
			<?php
			foreach ( $mailster_lists as $list ) {
				$selected = in_array( $list->ID, (array) $mailster_selected_list ) ? 'selected' : '';
				printf( '<option value="%s" %s>%s</option>',
					esc_attr( $list->ID ), esc_attr( $selected ), esc_html( $list->name ) );
			}
			?>
        </select>
		<?php
		$fields['fields']['wlwl_mailster_list']['html'] = ob_get_clean();
		$this->settings::villatheme_render_table_field( $fields );
	}

}