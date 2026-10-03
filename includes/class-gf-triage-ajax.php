<?php
/**
 * AJAX Engine for MainWP Gravity Forms Triage.
 *
 * @package MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MainWP_GF_Triage_Ajax
 */
class MainWP_GF_Triage_Ajax {

	const VERSION = '1.0.14';

	/**
	 * Instance variable.
	 *
	 * @var MainWP_GF_Triage_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MainWP_GF_Triage_Ajax
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mainwp_gf_triage_batch_sync', array( $this, 'handle_batch_sync' ) );
	}

	/**
	 * Handles manual batch sync AJAX request across the full fleet.
	 */
	public function handle_batch_sync() {
		check_ajax_referer( 'mainwp_gf_triage_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'mainwp-gravity-forms-triage' ) ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 10; // Batch execution size per AJAX call

		global $wpdb;
		$table_name = $wpdb->prefix . 'mainwp_wp';

		// Query full fleet without artificial LIMIT restrictions
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$all_sites = $wpdb->get_results( "SELECT id, name, url FROM {$table_name} ORDER BY name ASC", ARRAY_A );

		if ( empty( $all_sites ) || ! is_array( $all_sites ) ) {
			wp_send_json_error( array( 'message' => __( 'No managed child sites were found in MainWP database.', 'mainwp-gravity-forms-triage' ) ) );
		}

		$total_sites = count( $all_sites );
		$batch       = array_slice( $all_sites, $offset, $batch_size );
		$site_logs   = array();
		$cron_engine = MainWP_GF_Triage_Cron::instance();

		foreach ( $batch as $site ) {
			$site_id   = isset( $site['id'] ) ? absint( $site['id'] ) : 0;
			$site_name = isset( $site['name'] ) ? $site['name'] : 'Site ' . $site_id;

			if ( ! $site_id ) {
				continue;
			}

			$result = $cron_engine->sync_single_site( $site_id );

			if ( is_wp_error( $result ) ) {
				$site_logs[] = array(
					'site_id'   => $site_id,
					'site_name' => $site_name,
					'status'    => 'error',
					'count'     => 0,
					'message'   => $result->get_error_message(),
				);
			} else {
				$site_logs[] = array(
					'site_id'   => $site_id,
					'site_name' => $site_name,
					'status'    => 'success',
					'count'     => isset( $result['logged_forms'] ) ? $result['logged_forms'] : 0,
					'message'   => sprintf( __( '%d forms logged to database.', 'mainwp-gravity-forms-triage' ), isset( $result['logged_forms'] ) ? $result['logged_forms'] : 0 ),
				);
			}
		}

		$next_offset = $offset + count( $batch );
		$has_more    = $next_offset < $total_sites;

		wp_send_json_success(
			array(
				'site_logs'    => $site_logs,
				'offset'       => $offset,
				'next_offset'  => $next_offset,
				'total'        => $total_sites,
				'has_more'     => $has_more,
				'logged_sites' => MainWP_GF_Triage_DB::instance()->get_logged_sites_count(),
				'logged_forms' => MainWP_GF_Triage_DB::instance()->get_logged_forms_count(),
			)
		);
	}
}

MainWP_GF_Triage_Ajax::instance();
