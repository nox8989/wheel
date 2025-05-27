<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_System_Status {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ),14 );
	}
	public function add_menu(){
		add_submenu_page(
			'woocommerce-lucky-wheel',
			esc_html__( 'System Status', 'woocommerce-lucky-wheel' ),
			esc_html__( 'System Status', 'woocommerce-lucky-wheel' ),
			'manage_options',
			'wlwl-system-status',
			array($this, 'system_status' )
		);
	}

	public function system_status() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'System Status', 'woocommerce-lucky-wheel' ) ?></h2>
			<table cellspacing="0" id="status" class="widefat">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Option name', 'woocommerce-lucky-wheel' ) ?></th>
					<th><?php esc_html_e( 'Your option value', 'woocommerce-lucky-wheel' ) ?></th>
					<th><?php esc_html_e( 'Minimum recommended value', 'woocommerce-lucky-wheel' ) ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td data-export-label="file_get_contents">file_get_contents</td>
					<td>
						<?php
						if ( function_exists( 'file_get_contents' ) ) {
							?>
							<mark class="yes">&#10004; <code class="private"></code></mark>
							<?php
						} else {
							?>
							<mark class="error">&#10005;</mark>'
							<?php
						}
						?>
					</td>
					<td><?php esc_html_e( 'Required', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				<tr>
					<td data-export-label="file_put_contents">file_put_contents</td>
					<td>
						<?php
						if ( function_exists( 'file_put_contents' ) ) {
							?>
							<mark class="yes">&#10004; <code class="private"></code></mark>
							<?php
						} else {
							?>
							<mark class="error">&#10005;</mark>
							<?php
						}
						?>

					</td>
					<td><?php esc_html_e( 'Required', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				<tr>
					<td data-export-label="mkdir">mkdir</td>
					<td>
						<?php
						if ( function_exists( 'mkdir' ) ) {
							?>
							<mark class="yes">&#10004; <code class="private"></code></mark>
							<?php
						} else {
							?>
							<mark class="error">&#10005;</mark>
							<?php
						}
						?>

					</td>
					<td><?php esc_html_e( 'Required', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				<?php
				$max_execution_time = ini_get( 'max_execution_time' );
				$max_input_vars     = ini_get( 'max_input_vars' );
				$memory_limit       = ini_get( 'memory_limit' );
				?>
				<tr>
					<td data-export-label="<?php esc_attr_e( 'PHP Time Limit', 'woocommerce-lucky-wheel' ) ?>"><?php esc_html_e( 'PHP Time Limit', 'woocommerce-lucky-wheel' ) ?></td>
					<td style="<?php if ( $max_execution_time > 0 && $max_execution_time < 300 ) {
						echo esc_attr( 'color:red' );
					} ?>"><?php echo esc_html( $max_execution_time ); ?></td>
					<td><?php esc_html_e( '300', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				<tr>
					<td data-export-label="<?php esc_attr_e( 'PHP Max Input Vars', 'woocommerce-lucky-wheel' ) ?>"><?php esc_html_e( 'PHP Max Input Vars', 'woocommerce-lucky-wheel' ) ?></td>
					<td style="<?php if ( $max_input_vars < 1000 ) {
						echo esc_attr( 'color:red' );
					} ?>"><?php echo esc_html( $max_input_vars ); ?></td>
					<td><?php esc_html_e( '5000', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				<tr>
					<td data-export-label="<?php esc_attr_e( 'Memory Limit', 'woocommerce-lucky-wheel' ) ?>"><?php esc_html_e( 'Memory Limit', 'woocommerce-lucky-wheel' ) ?></td>
					<td style="<?php if ( intval( $memory_limit ) < 64 ) {
						echo esc_attr( 'color:red' );
					} ?>"><?php echo esc_html( $memory_limit ); ?></td>
					<td><?php esc_html_e( '64M', 'woocommerce-lucky-wheel' ) ?></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}