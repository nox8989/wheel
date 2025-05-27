<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_Report {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ),13 );
		add_action( 'admin_init', array( $this, 'export_emails' ) );
	}
	public function export_emails() {
		$nonce = isset( $_POST['wlwl_export_emails_nonce'] ) ? sanitize_text_field( $_POST['wlwl_export_emails_nonce'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['wlwl_export_emails'] ) && wp_verify_nonce( $nonce, 'wlwl_export_emails_nonce_action' ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$start    = isset( $_POST['wlwl_export_start'] ) ? sanitize_text_field( $_POST['wlwl_export_start'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$end      = isset( $_POST['wlwl_export_end'] ) ? sanitize_text_field( $_POST['wlwl_export_end'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$filename = "lucky_wheel_email";
			if ( ! $start && ! $end ) {
				$args1    = array(
					'post_type'      => 'wlwl_email',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
				);
				$filename .= date( 'Y-m-d_h-i-s', time() ) . ".csv";// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} elseif ( ! $start ) {
				$args1    = array(
					'post_type'      => 'wlwl_email',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'date_query'     => array(
						array(
							'before'    => $end,
							'inclusive' => true

						)
					),
				);
				$filename .= 'before_' . $end . ".csv";
			} elseif ( ! $end ) {
				$args1    = array(
					'post_type'      => 'wlwl_email',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'date_query'     => array(
						array(
							'after'     => $start,
							'inclusive' => true
						)
					),

				);
				$filename .= 'from' . $start . 'to' . date( 'Y-m-d' ) . ".csv";// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} else {
				if ( strtotime( $start ) > strtotime( $end ) ) {
					wp_die( 'Incorrect input date' );
				}
				$args1    = array(
					'post_type'      => 'wlwl_email',
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'date_query'     => array(
						array(
							'before'    => $end,
							'after'     => $start,
							'inclusive' => true

						)
					),
				);
				$filename .= 'from' . $start . 'to' . $end . ".csv";
			}
			$the_query        = new WP_Query( $args1 );
			$csv_source_array = array();
			$names            = array();
			$mobiles          = array();
			$coupons          = array();
			$coupons_labels   = array();
			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$id                 = get_the_ID();
					$csv_source_array[] = get_the_title();
					$names[]            = get_the_content();
					$mobiles[]          = ! empty( get_post_meta( $id, 'wlwl_email_mobile', true ) ) ? get_post_meta( $id, 'wlwl_email_mobile', true ) : '';
					$coupon             = ! empty( get_post_meta( $id, 'wlwl_email_coupons', true ) ) ? get_post_meta( $id, 'wlwl_email_coupons', true ) : array( '' );
					$label              = ! empty( get_post_meta( $id, 'wlwl_email_labels', true ) ) ? get_post_meta( $id, 'wlwl_email_labels', true ) : array( '' );
					if ( is_array( $coupon ) && !empty( $coupon ) ) {
						$coupons[] = implode( ", ", $coupon );
					}
					if ( is_array( $label ) && !empty( $label ) ) {
						$coupons_labels[] = implode( ", ", array_map( 'html_entity_decode', $label ) );
					}
				}
				wp_reset_postdata();
				$data_rows  = array();
				$header_row = array(
					'Order',
					'Email',
					'Name',
					'Mobile',
					'Coupon codes',
					'Coupon labels',
				);
				$i          = 1;
				foreach ( $csv_source_array as $key => $result ) {
					$row         = array(
						$i,
						$result,
						$names[ $key ],
						$mobiles[ $key ],
						$coupons[ $key ],
						$coupons_labels[ $key ]
					);
					$data_rows[] = $row;
					$i ++;
				}

				$fh = @fopen( 'php://output', 'w' );
				fprintf( $fh, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
				if ( function_exists( 'gc_enable' ) ) {
					gc_enable(); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.gc_enableFound
				}
				if ( function_exists( 'apache_setenv' ) ) {
					@apache_setenv( 'no-gzip', 1 ); // @codingStandardsIgnoreLine
				}
				@ini_set( 'zlib.output_compression', 'Off' ); // @codingStandardsIgnoreLine
				@ini_set( 'output_buffering', 'Off' ); // @codingStandardsIgnoreLine
				@ini_set( 'output_handler', '' ); // @codingStandardsIgnoreLine
				ignore_user_abort( true );
				wc_set_time_limit( 0 );
				wc_nocache_headers();
				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=' . $filename );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				fputcsv( $fh, $header_row );
				foreach ( $data_rows as $data_row ) {
					fputcsv( $fh, $data_row );
				}
				$csvFile = stream_get_contents( $fh );
				fclose( $fh );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				die();
			}
		}
	}
	public function add_menu(){
		add_submenu_page(
			'woocommerce-lucky-wheel',
			esc_html__( 'Report', 'woocommerce-lucky-wheel' ),
			esc_html__( 'Report', 'woocommerce-lucky-wheel' ),
			'manage_options',
			'wlwl-report',
			array( $this, 'report_callback' )
		);
	}
	public function report_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$total_spin = $email_subscribe = $coupon_given = 0;

		$args      = array(
			'post_type'      => 'wlwl_email',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			$email_subscribe = $the_query->post_count;
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$id = get_the_ID();
				if ( get_post_meta( $id, 'wlwl_spin_times', true ) ) {
					$total_spin += get_post_meta( $id, 'wlwl_spin_times', true )['spin_num'] ?? 0;
				}
				if ( get_post_meta( $id, 'wlwl_email_coupons', true ) ) {
					$coupon       = get_post_meta( $id, 'wlwl_email_coupons', true );
					$coupon_given += sizeof( $coupon );
				}
			}
			wp_reset_postdata();
		}

		?>
		<div class="wrap">
			<form action="" method="post">
				<h2><?php esc_html_e( 'Lucky Wheel Report', 'woocommerce-lucky-wheel' ) ?></h2>

				<table cellspacing="0" id="status" class="widefat">
					<tbody>
					<tr>
						<th><?php esc_html_e( 'Total Spins', 'woocommerce-lucky-wheel' ) ?></th>
						<th><?php esc_html_e( 'Emails Subcribed', 'woocommerce-lucky-wheel' ) ?></th>
						<th><?php esc_html_e( 'Coupon Given', 'woocommerce-lucky-wheel' ) ?></th>
					</tr>
					<tr>
						<td><?php echo esc_html( $total_spin ); ?></td>
						<td><?php echo esc_html( $email_subscribe ); ?></td>
						<td><?php echo esc_html( $coupon_given ); ?></td>
					</tr>
					</tbody>

				</table>
                <p></p>
				<label for="wlwl_export_start"><?php esc_html_e( 'From', 'woocommerce-lucky-wheel' ); ?></label><input
					type="date" name="wlwl_export_start" id="wlwl_export_start" class="wlwl_export_date">
				<label for="wlwl_export_end"><?php esc_html_e( 'To', 'woocommerce-lucky-wheel' ); ?></label><input
					type="date" name="wlwl_export_end" id="wlwl_export_end" class="wlwl_export_date">

				<input id="submit"
				       type="submit"
				       class="button-primary"
				       name="wlwl_export_emails"
				       value="<?php esc_html_e( 'Export Emails', 'woocommerce-lucky-wheel' ); ?>"/>
				<?php
				wp_nonce_field( 'wlwl_export_emails_nonce_action', 'wlwl_export_emails_nonce' );
				?>
			</form>
		</div>
		<?php
	}
}