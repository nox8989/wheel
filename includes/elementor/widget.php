<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WLWL_Elementor_Wheel_Widget extends Elementor\Widget_Base {

	public static $slug = 'wlwl-elementor-wheel-widget';

	public function get_name() {
		return 'woocommerce-lucky-wheel';
	}

	public function get_title() {
		return esc_html__( 'Lucky Wheel', 'woocommerce-lucky-wheel' );
	}

	public function get_icon() {
		return 'fas fa-dharmachakra';
	}

	public function get_categories() {
		return [ 'woocommerce-elements' ];
	}

	protected function _register_controls() {
		$wheel_settings = VI_WOOCOMMERCE_LUCKY_WHEEL_DATA::get_instance();
		$this->start_controls_section(
			'wlwl_general',
			[
				'label' => esc_html__( 'General', 'woocommerce-lucky-wheel' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_control(
			'bg_color',
			[
				'label'     => esc_html__( 'Background Color', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'bg_color' ),
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-container' => 'background-color: {{VALUE}};',
				],
				'dynamic'   => [
					'active' => false,
				],
			]
		);
		$this->add_control(
			'bg_image',
			[
				'label'     => esc_html__( 'Background Image', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'default'   => [
					'id'  => $wheel_settings->get_params( 'wheel_wrap', 'bg_image' ),
					'url' => wp_get_attachment_url( $wheel_settings->get_params( 'wheel_wrap', 'bg_image' ) ),
				],
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-container' => 'background-image:url({{URL}});',
				],
				'separator' => 'after'
			]
		);
		$this->add_control(
			'wheel_center_color',
			[
				'label'   => esc_html__( 'Wheel Center Color', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => $wheel_settings->get_params( 'wheel_wrap', 'wheel_center_color' ),
				'dynamic' => [
					'active' => false,
				],
			]
		);
		$this->add_control(
			'center_image',
			[
				'label'     => esc_html__( 'Center Image', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'default'   => [
					'id'  => $wheel_settings->get_params( 'wheel_wrap', 'wheel_center_image' ),
					'url' => wp_get_attachment_url( $wheel_settings->get_params( 'wheel_wrap', 'wheel_center_image' ) ),
				],
				'separator' => 'after'
			]
		);
		$this->add_control(
			'text_color',
			[
				'label'     => esc_html__( 'Text Color', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'text_color' ),
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-container' => 'color: {{VALUE}};',
				],
				'dynamic'   => [
					'active' => false,
				],
			]
		);
		$this->add_control(
			'wheel_dot_color',
			[
				'label'   => esc_html__( 'Wheel Dot Color', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => $wheel_settings->get_params( 'wheel_wrap', 'wheel_dot_color' ),
				'dynamic' => [
					'active' => false,
				],
			]
		);
		$this->add_control(
			'wheel_border_color',
			[
				'label'   => esc_html__( 'Wheel Border Color', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => $wheel_settings->get_params( 'wheel_wrap', 'wheel_border_color' ),
				'dynamic' => [
					'active' => false,
				],
			]
		);
		$this->add_control(
			'spin_button_color',
			[
				'label'     => esc_html__( 'Button Spin Color', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'spin_button_color' ),
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-wheel-button-wrap' => 'color: {{VALUE}};',
				],
				'dynamic'   => [
					'active' => false,
				],
				'separator' => 'before',
			]
		);
		$this->add_control(
			'spin_button_bg_color',
			[
				'label'     => esc_html__( 'Button Spin Background Color', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'spin_button_bg_color' ),
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-wheel-button-wrap' => 'background-color: {{VALUE}};',
				],
				'dynamic'   => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'pointer_color',
			[
				'label'     => esc_html__( 'Pointer Color', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'pointer_color' ),
				'selectors' => [
					'{{WRAPPER}} .wc-lucky-wheel-shortcode-wheel-pointer:before' => 'color: {{VALUE}};',
				],
				'dynamic'   => [
					'active' => false,
				],
				'separator' => 'before',
			]
		);
		$this->add_control(
			'pointer_position',
			[
				'label'   => esc_html__( 'Pointer Position', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => $wheel_settings->get_params( 'wheel_wrap', 'pointer_position' ),
				'options' => [
					'center' => esc_html__( 'Center', 'woocommerce-lucky-wheel' ),
					'top'    => esc_html__( 'Top', 'woocommerce-lucky-wheel' ),
					'right'  => esc_html__( 'Right', 'woocommerce-lucky-wheel' ),
					'bottom' => esc_html__( 'Bottom', 'woocommerce-lucky-wheel' ),
				],
			]
		);
		$this->add_control(
			'congratulations_effect',
			[
				'label'     => esc_html__( 'Congratulation Effect', 'woocommerce-lucky-wheel' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => $wheel_settings->get_params( 'wheel_wrap', 'congratulations_effect' ),
				'options'   => [
					'none'     => esc_html__( 'Disable', 'woocommerce-lucky-wheel' ),
					'firework' => esc_html__( 'Firework', 'woocommerce-lucky-wheel' ),
				],
				'separator' => 'before',
			]
		);
		$this->add_control(
			'spinning_time',
			[
				'label'   => esc_html__( 'Spinning Time', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => '3',
				'max'     => '15',
				'step'    => '1',
				'default' => $wheel_settings->get_params( 'wheel', 'spinning_time' ),
			]
		);

		$this->add_control(
			'wheel_speed',
			[
				'label'   => esc_html__( 'Wheel Speed', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => '1',
				'max'     => '10',
				'step'    => '1',
				'default' => $wheel_settings->get_params( 'wheel', 'wheel_speed' ),
			]
		);
		$this->add_responsive_control(
			'custom_field_name_enable',
			[
				'label'           => esc_html__( 'Enable Field Name', 'woocommerce-lucky-wheel' ),
				'type'            => \Elementor\Controls_Manager::SWITCHER,
				'default'         => $wheel_settings->get_params( 'custom_field_name_enable' ),
				'description'     => '',
				'label_on'        => esc_html__( 'Yes', 'woocommerce-lucky-wheel' ),
				'label_off'       => esc_html__( 'No', 'woocommerce-lucky-wheel' ),
				'devices'         => [ 'desktop', 'mobile' ],
				'desktop_default' => 'on',
				'mobile_default'  => 'on',
				'return_value'    => 'on',
				'separator'       => 'before',
			]
		);
		$this->add_control(
			'custom_field_name_required',
			[
				'label'        => esc_html__( '"Name" field is required', 'woocommerce-lucky-wheel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $wheel_settings->get_params( 'custom_field_name_required' ),
				'description'  => '',
				'label_on'     => esc_html__( 'Yes', 'woocommerce-lucky-wheel' ),
				'label_off'    => esc_html__( 'No', 'woocommerce-lucky-wheel' ),
				'return_value' => 'on',
				'separator'    => 'after',
			]
		);
		$this->add_responsive_control(
			'custom_field_mobile_enable',
			[
				'label'           => esc_html__( 'Enable Field Phone Number', 'woocommerce-lucky-wheel' ),
				'type'            => \Elementor\Controls_Manager::SWITCHER,
				'default'         => $wheel_settings->get_params( 'custom_field_mobile_enable' ),
				'description'     => '',
				'label_on'        => esc_html__( 'Yes', 'woocommerce-lucky-wheel' ),
				'label_off'       => esc_html__( 'No', 'woocommerce-lucky-wheel' ),
				'devices'         => [ 'desktop', 'mobile' ],
				'desktop_default' => 'on',
				'mobile_default'  => 'on',
				'return_value'    => 'on',
			]
		);
		$this->add_control(
			'custom_field_mobile_required',
			[
				'label'        => esc_html__( '"Phone number" field is required', 'woocommerce-lucky-wheel' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $wheel_settings->get_params( 'custom_field_mobile_required' ),
				'description'  => '',
				'label_on'     => esc_html__( 'Yes', 'woocommerce-lucky-wheel' ),
				'label_off'    => esc_html__( 'No', 'woocommerce-lucky-wheel' ),
				'return_value' => 'on',
				'separator'    => 'after',
			]
		);
		$this->add_control(
			'font_size',
			[
				'label'   => esc_html__( 'Adjust Font Size(%)', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => '1',
				'max'     => '100',
				'step'    => '1',
				'default' => $wheel_settings->get_params( 'wheel', 'font_size' ),
			]
		);
		$this->add_control(
			'wheel_size',
			[
				'label'   => esc_html__( 'Adjust Wheel Size(%)', 'woocommerce-lucky-wheel' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => '1',
				'max'     => '100',
				'step'    => '1',
				'default' => $wheel_settings->get_params( 'wheel', 'wheel_size' ),
			]
		);
		$this->end_controls_section();
	}

	public function get_shortcode_text() {
		$settings     = $this->get_settings_for_display();
		$bg_image     = isset( $settings['bg_image']['url'] ) ? $settings['bg_image']['url'] : '';
		$center_image = isset( $settings['center_image']['url'] ) ? $settings['center_image']['url'] : '';

		return "[woocommerce_lucky_wheel is_elementor='yes' bg_image='{$bg_image}' bg_color='{$settings['bg_color']}' text_color='{$settings['text_color']}' pointer_color='{$settings['pointer_color']}' spin_button_color='{$settings['spin_button_color']}' pointer_position='{$settings['pointer_position']}' spin_button_bg_color='{$settings['spin_button_bg_color']}' wheel_dot_color='{$settings['wheel_dot_color']}' wheel_border_color='{$settings['wheel_border_color']}' wheel_center_color='{$settings['wheel_center_color']}' spinning_time='{$settings['spinning_time']}' wheel_speed='{$settings['wheel_speed']}' custom_field_name_enable='{$settings['custom_field_name_enable']}' custom_field_name_enable_mobile='{$settings['custom_field_name_enable_mobile']}' custom_field_name_required='{$settings['custom_field_name_required']}' custom_field_mobile_enable='{$settings['custom_field_mobile_enable']}' custom_field_mobile_enable_mobile='{$settings['custom_field_mobile_enable_mobile']}' custom_field_mobile_required='{$settings['custom_field_mobile_required']}' font_size='{$settings['font_size']}' wheel_size='{$settings['wheel_size']}' congratulations_effect='{$settings['congratulations_effect']}' center_image='{$center_image}']";
	}

	protected function render() {
		echo do_shortcode( $this->get_shortcode_text() );
	}

	public function render_plain_content() {
		echo $this->get_shortcode_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}