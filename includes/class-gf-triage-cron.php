<?php
/**
 * Cron and Processing Engine for MainWP Gravity Forms Triage.
 *
 * @package MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MainWP_GF_Triage_Cron
 */
class MainWP_GF_Triage_Cron {

	const VERSION = '1.0.18';

	/**
	 * Instance variable.
	 *
	 * @var MainWP_GF_Triage_Cron|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MainWP_GF_Triage_Cron
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
		add_action( 'mainwp_gf_triage_monthly_cron_hook', array( $this, 'run_monthly_fleet_sync' ) );
	}

	/**
	 * Monthly automated background sync.
	 */
	public function run_monthly_fleet_sync() {
		global $wpdb;

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		$table_name = $wpdb->prefix . 'mainwp_wp';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$all_sites = $wpdb->get_results( "SELECT id, name, url FROM {$table_name} ORDER BY name ASC", ARRAY_A );

		if ( empty( $all_sites ) || ! is_array( $all_sites ) ) {
			return;
		}

		foreach ( $all_sites as $site ) {
			$site_id = isset( $site['id'] ) ? absint( $site['id'] ) : 0;
			if ( ! $site_id ) {
				continue;
			}

			$this->sync_single_site( $site_id );
			usleep( 250000 );
		}

		update_option( 'mwp_gf_triage_last_auto_sync', current_time( 'mysql' ) );
	}

	/**
	 * Syncs Gravity Forms data for a single site using OpenSSL snippet execution.
	 *
	 * @param int $site_id Child site ID.
	 * @return array|WP_Error
	 */
	public function sync_single_site( $site_id ) {
		global $wpdb;

		$table_wp      = $wpdb->prefix . 'mainwp_wp';
		$table_clients = $wpdb->prefix . 'mainwp_wp_clients';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$site_data = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_wp} WHERE id = %d", $site_id ),
			ARRAY_A
		);

		if ( empty( $site_data ) ) {
			return new WP_Error(
				'site_not_found',
				sprintf( __( 'Child Site ID %d was not found in MainWP database.', 'mainwp-gravity-forms-triage' ), $site_id )
			);
		}

		$site_obj    = (object) $site_data;
		$site_name   = ! empty( $site_data['name'] ) ? $site_data['name'] : 'Site ' . $site_id;
		$site_url    = ! empty( $site_data['url'] ) ? $site_data['url'] : '';
		$client_id   = ! empty( $site_data['client_id'] ) ? absint( $site_data['client_id'] ) : 0;
		$client_name = 'Unassigned Client';

		if ( $client_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$found_name = $wpdb->get_var(
				$wpdb->prepare( "SELECT name FROM {$table_clients} WHERE client_id = %d LIMIT 1", $client_id )
			);
			if ( ! empty( $found_name ) ) {
				$client_name = $found_name;
			}
		}

		// Snippet code sent natively over OpenSSL
		$code = '
			if ( ! class_exists( "GFAPI" ) ) {
				echo json_encode( array(
					"gf_active" => false,
					"forms"     => array(),
					"message"   => "Gravity Forms is not active on this site."
				) );
				return;
			}

			global $wpdb;

			$forms = GFAPI::get_forms();
			$inventory = array();

			if ( ! empty( $forms ) && is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					$form_id    = isset( $form["id"] ) ? intval( $form["id"] ) : 0;
					$form_title = isset( $form["title"] ) ? $form["title"] : "";

					if ( ! $form_id ) {
						continue;
					}

					$active_count = GFAPI::count_entries( $form_id, array( "status" => "active" ) );
					$spam_count   = GFAPI::count_entries( $form_id, array( "status" => "spam" ) );

					$entry_table       = GFFormsModel::get_entry_table_name();
					$monthly_breakdown = array();

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$monthly_counts = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT DATE_FORMAT(date_created, \'%Y-%m\') as month_year, status, COUNT(*) as count 
							 FROM {$entry_table} 
							 WHERE form_id = %d AND status IN (\'active\', \'spam\')
							 GROUP BY month_year, status 
							 ORDER BY month_year DESC",
							$form_id
						),
						ARRAY_A
					);

					if ( ! empty( $monthly_counts ) && is_array( $monthly_counts ) ) {
						foreach ( $monthly_counts as $row ) {
							$ym  = $row["month_year"];
							$st  = $row["status"];
							$cnt = intval( $row["count"] );

							if ( ! isset( $monthly_breakdown[ $ym ] ) ) {
								$monthly_breakdown[ $ym ] = array(
									"active" => 0,
									"spam"   => 0,
								);
							}
							$monthly_breakdown[ $ym ][ $st ] = $cnt;
						}
					}

					$inventory[] = array(
						"form_id"           => $form_id,
						"form_title"        => $form_title,
						"total_active"      => $active_count,
						"total_spam"        => $spam_count,
						"monthly_breakdown" => $monthly_breakdown,
					);
				}
			}

			echo json_encode( array(
				"gf_active"   => true,
				"total_forms" => count( $inventory ),
				"forms"       => $inventory,
			) );
		';

		$post_data = array(
			'action' => 'run_snippet',
			'code'   => $code,
		);

		$response_data = null;

		try {
			if ( class_exists( '\MainWP\Dashboard\MainWP_Connect' ) ) {
				$response_data = \MainWP\Dashboard\MainWP_Connect::fetch_url_authed(
					$site_obj,
					'code_snippet',
					$post_data
				);
			} elseif ( class_exists( '\MainWP_Connect' ) ) {
				$response_data = \MainWP_Connect::fetch_url_authed(
					$site_obj,
					'code_snippet',
					$post_data
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_exception', $e->getMessage() );
		} catch ( Throwable $t ) {
			return new WP_Error( 'connection_fatal', $t->getMessage() );
		}

		if ( empty( $response_data ) ) {
			return new WP_Error(
				'fetch_failed',
				__( 'Failed to retrieve response from child site. Verify MainWP connection status.', 'mainwp-gravity-forms-triage' )
			);
		}

		$raw_result = '';
		if ( is_array( $response_data ) && isset( $response_data['result'] ) ) {
			$raw_result = trim( $response_data['result'] );
		} elseif ( is_string( $response_data ) ) {
			$raw_result = trim( $response_data );
		}

		if ( preg_match( '/<mainwp>(.*)<\/mainwp>/s', $raw_result, $matches ) ) {
			$decoded_wrapper = base64_decode( $matches[1] );
			$wrapper_json    = json_decode( $decoded_wrapper, true );
			if ( is_array( $wrapper_json ) && isset( $wrapper_json['result'] ) ) {
				$raw_result = trim( $wrapper_json['result'] );
			}
		}

		$parsed_json = json_decode( $raw_result, true );

		if ( ! is_array( $parsed_json ) ) {
			return new WP_Error( 'json_parse_error', __( 'Failed to parse JSON response from child site.', 'mainwp-gravity-forms-triage' ) );
		}

		$db           = MainWP_GF_Triage_DB::instance();
		$logged_forms = $db->log_site_gf_data( $site_id, $site_name, $site_url, $client_id, $client_name, $parsed_json );

		return array(
			'site_id'      => $site_id,
			'site_name'    => $site_name,
			'site_url'     => $site_url,
			'client_name'  => $client_name,
			'logged_forms' => $logged_forms,
			'gf_data'      => $parsed_json,
		);
	}
}

MainWP_GF_Triage_Cron::instance();
