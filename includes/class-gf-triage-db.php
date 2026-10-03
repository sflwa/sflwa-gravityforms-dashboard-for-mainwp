<?php
/**
 * Custom Database Abstraction Layer for MainWP Gravity Forms Triage.
 *
 * @package MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MainWP_GF_Triage_DB
 */
class MainWP_GF_Triage_DB {

	const VERSION = '1.0.18';
	const DB_VERSION_OPTION = 'mwp_gf_triage_db_version';

	private static $instance = null;
	private $table_monthly;
	private $table_forms;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		global $wpdb;

		$this->table_monthly = $wpdb->prefix . 'mainwp_gf_triage_monthly';
		$this->table_forms   = $wpdb->prefix . 'mainwp_gf_triage_forms';

		add_action( 'plugins_loaded', array( $this, 'maybe_install_tables' ) );
		$this->maybe_install_tables();
	}

	public function get_table_monthly() {
		return $this->table_monthly;
	}

	public function get_table_forms() {
		return $this->table_forms;
	}

	public function maybe_install_tables() {
		$installed_ver = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_ver, self::VERSION, '<' ) ) {
			$this->create_tables();
			update_option( self::DB_VERSION_OPTION, self::VERSION );
		}
	}

	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_forms = "CREATE TABLE {$this->table_forms} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id int(11) NOT NULL,
			site_name varchar(255) NOT NULL,
			site_url varchar(255) NOT NULL,
			client_id int(11) NOT NULL DEFAULT 0,
			client_name varchar(255) DEFAULT 'Unassigned Client',
			form_id int(11) NOT NULL,
			form_title varchar(255) NOT NULL,
			total_active int(11) NOT NULL DEFAULT 0,
			total_spam int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			is_trash tinyint(1) NOT NULL DEFAULT 0,
			last_scanned datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY site_form (site_id, form_id),
			KEY site_id (site_id),
			KEY client_id (client_id)
		) $charset_collate;";

		$sql_monthly = "CREATE TABLE {$this->table_monthly} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id int(11) NOT NULL,
			form_id int(11) NOT NULL,
			month_year varchar(7) NOT NULL,
			active_count int(11) NOT NULL DEFAULT 0,
			spam_count int(11) NOT NULL DEFAULT 0,
			last_scanned datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY site_form_month (site_id, form_id, month_year),
			KEY site_id (site_id),
			KEY form_id (form_id),
			KEY month_year (month_year)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_forms );
		dbDelta( $sql_monthly );
	}

	public function log_site_gf_data( $site_id, $site_name, $site_url, $client_id, $client_name, array $gf_data ) {
		global $wpdb;

		if ( empty( $gf_data['gf_active'] ) || empty( $gf_data['forms'] ) || ! is_array( $gf_data['forms'] ) ) {
			return 0;
		}

		$logged_forms = 0;

		foreach ( $gf_data['forms'] as $form ) {
			$form_id      = isset( $form['form_id'] ) ? absint( $form['form_id'] ) : 0;
			$form_title   = isset( $form['form_title'] ) ? sanitize_text_field( $form['form_title'] ) : '';
			$total_active = isset( $form['total_active'] ) ? absint( $form['total_active'] ) : 0;
			$total_spam   = isset( $form['total_spam'] ) ? absint( $form['total_spam'] ) : 0;

			if ( ! $form_id ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$this->table_forms} 
					(site_id, site_name, site_url, client_id, client_name, form_id, form_title, total_active, total_spam, is_active, is_trash, last_scanned)
					VALUES (%d, %s, %s, %d, %s, %d, %s, %d, %d, 1, 0, NOW())
					ON DUPLICATE KEY UPDATE 
						site_name = VALUES(site_name),
						site_url = VALUES(site_url),
						client_id = VALUES(client_id),
						client_name = VALUES(client_name),
						form_title = VALUES(form_title),
						total_active = VALUES(total_active),
						total_spam = VALUES(total_spam),
						last_scanned = NOW()",
					$site_id,
					$site_name,
					$site_url,
					$client_id,
					$client_name,
					$form_id,
					$form_title,
					$total_active,
					$total_spam
				)
			);

			$logged_forms++;

			if ( ! empty( $form['monthly_breakdown'] ) && is_array( $form['monthly_breakdown'] ) ) {
				foreach ( $form['monthly_breakdown'] as $month_year => $counts ) {
					$active_cnt = isset( $counts['active'] ) ? absint( $counts['active'] ) : 0;
					$spam_cnt   = isset( $counts['spam'] ) ? absint( $counts['spam'] ) : 0;

					if ( $active_cnt > 0 || $spam_cnt > 0 ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$wpdb->query(
							$wpdb->prepare(
								"INSERT INTO {$this->table_monthly} 
								(site_id, form_id, month_year, active_count, spam_count, last_scanned)
								VALUES (%d, %d, %s, %d, %d, NOW())
								ON DUPLICATE KEY UPDATE 
									active_count = VALUES(active_count),
									spam_count = VALUES(spam_count),
									last_scanned = NOW()",
								$site_id,
								$form_id,
								$month_year,
								$active_cnt,
								$spam_cnt
							)
						);
					}
				}
			}
		}

		return $logged_forms;
	}

	public function get_logged_sites_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_forms ) ) !== $this->table_forms ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT site_id) FROM {$this->table_forms}" );
	}

	public function get_logged_forms_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_forms ) ) !== $this->table_forms ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT form_id) FROM {$this->table_forms}" );
	}

	public function get_unique_clients() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_forms ) ) !== $this->table_forms ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			"SELECT client_id, client_name 
			 FROM {$this->table_forms} 
			 WHERE client_name IS NOT NULL AND client_name != ''
			 GROUP BY client_id, client_name
			 ORDER BY client_name ASC",
			ARRAY_A
		);
	}

	public function get_fleet_accordion_data( $filter_client_id = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_forms ) ) !== $this->table_forms ) {
			return array();
		}

		$where_sql = '';
		if ( $filter_client_id > 0 ) {
			$where_sql = $wpdb->prepare( "WHERE client_id = %d", $filter_client_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$sites = $wpdb->get_results(
			"SELECT site_id, site_name, site_url, client_id, client_name, 
			        COALESCE(SUM(total_active), 0) as aggregate_active, 
			        COALESCE(SUM(total_spam), 0) as aggregate_spam,
			        COUNT(form_id) as total_forms
			 FROM {$this->table_forms}
			 {$where_sql}
			 GROUP BY site_id, site_name, site_url, client_id, client_name
			 ORDER BY site_name ASC",
			ARRAY_A
		);

		if ( empty( $sites ) || ! is_array( $sites ) ) {
			return array();
		}

		// Configurable Threshold Option Fallbacks
		$low_max   = get_option( 'mwp_gf_low_vol_max', 25 );
		$low_drop  = get_option( 'mwp_gf_low_vol_drop', 80 );
		$med_max   = get_option( 'mwp_gf_med_vol_max', 100 );
		$med_drop  = get_option( 'mwp_gf_med_vol_drop', 50 );
		$high_drop = get_option( 'mwp_gf_high_vol_drop', 25 );

		$current_mtd_m = date( 'Y-m' );
		$m1_last_full  = date( 'Y-m', strtotime( 'first day of previous month' ) );
		$m2_prior_full = date( 'Y-m', strtotime( 'first day of -2 months' ) );
		$m3_p3_full    = date( 'Y-m', strtotime( 'first day of -3 months' ) );

		foreach ( $sites as &$site ) {
			$site_id = absint( $site['site_id'] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$forms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_forms} 
					 WHERE site_id = %d 
					 ORDER BY is_active DESC, is_trash ASC, form_title ASC",
					$site_id
				),
				ARRAY_A
			);

			$site_mtd_act = 0;
			$site_m1_act  = 0;
			$site_m2_act  = 0;
			$site_m3_act  = 0;

			$form_drops = array();

			if ( ! empty( $forms ) && is_array( $forms ) ) {
				foreach ( $forms as &$f ) {
					$form_id = absint( $f['form_id'] );

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$months = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT month_year, active_count, spam_count 
							 FROM {$this->table_monthly} 
							 WHERE site_id = %d AND form_id = %d 
							 ORDER BY month_year DESC",
							$site_id,
							$form_id
						),
						ARRAY_A
					);

					$f['monthly_breakdown'] = ! empty( $months ) && is_array( $months ) ? $months : array();

					$form_m1 = 0;
					$form_m2 = 0;
					$form_m3 = 0;

					foreach ( $f['monthly_breakdown'] as $m ) {
						if ( $m['month_year'] === $current_mtd_m ) {
							$site_mtd_act += absint( $m['active_count'] );
						} elseif ( $m['month_year'] === $m1_last_full ) {
							$site_m1_act += absint( $m['active_count'] );
							$form_m1     += absint( $m['active_count'] );
						} elseif ( $m['month_year'] === $m2_prior_full ) {
							$site_m2_act += absint( $m['active_count'] );
							$form_m2     += absint( $m['active_count'] );
						} elseif ( $m['month_year'] === $m3_p3_full ) {
							$site_m3_act += absint( $m['active_count'] );
							$form_m3     += absint( $m['active_count'] );
						}
					}

					// Rounded Whole Integer 3-Month Rolling Average
					$form_rolling_avg_exact = ( $form_m1 + $form_m2 + $form_m3 ) / 3;
					$f_diff                 = $form_m1 - $form_m2;

					if ( $f_diff < 0 ) {
						$form_drops[] = array(
							'title'     => $f['form_title'],
							'last'      => $form_m1,
							'prior'     => $form_m2,
							'drop_cnt'  => abs( $f_diff ),
							'pct'       => $form_m2 > 0 ? round( ( $f_diff / $form_m2 ) * 100, 0 ) : 0,
						);
					}

					$f['m1_act']           = $form_m1;
					$f['m2_act']           = $form_m2;
					$f['m3_act']           = $form_m3;
					$f['form_rolling_avg'] = (int) round( $form_rolling_avg_exact, 0 );
				}
			}

			// Rounded Whole Integer 3-Month Rolling Average
			$site_rolling_avg_exact = ( $site_m1_act + $site_m2_act + $site_m3_act ) / 3;
			$site_rolling_avg_round = (int) round( $site_rolling_avg_exact, 0 );
			$vs_avg_pct             = 0;

			if ( $site_rolling_avg_exact > 0 ) {
				$vs_avg_pct = round( ( ( $site_m1_act - $site_rolling_avg_exact ) / $site_rolling_avg_exact ) * 100, 0 );
			}

			// Determine Volume Bucket Tier
			$traffic_tier = 'low';
			$applied_threshold = $low_drop;

			if ( $site_rolling_avg_round > $med_max ) {
				$traffic_tier      = 'high';
				$applied_threshold = $high_drop;
			} elseif ( $site_rolling_avg_round >= $low_max ) {
				$traffic_tier      = 'medium';
				$applied_threshold = $med_drop;
			}

			// Churn Trigger Assessment
			$is_concern = false;
			if ( $site_rolling_avg_round >= 3 ) {
				if ( abs( $vs_avg_pct ) >= $applied_threshold && $vs_avg_pct < 0 ) {
					$is_concern = true;
				} elseif ( 0 === $site_m1_act && $site_m2_act > 0 ) {
					$is_concern = true;
				}
			}

			// Generate Human-Readable "Why" Diagnostic Text with Integer Rounding
			$why_diagnostic = 'Activity stable within normal traffic thresholds.';
			
			if ( $is_concern ) {
				if ( 0 === $site_m1_act ) {
					$why_diagnostic = sprintf( 'Total submission halt: 0 entries recorded in %s (3-Mo Avg: %d entries).', date( 'M Y', strtotime( 'first day of previous month' ) ), $site_rolling_avg_round );
				} elseif ( ! empty( $form_drops ) ) {
					usort( $form_drops, function( $a, $b ) {
						return $b['drop_cnt'] <=> $a['drop_cnt'];
					} );
					
					$top_drop = $form_drops[0];
					if ( 0 === $top_drop['last'] ) {
						$why_diagnostic = sprintf( 'Form ended: "%s" dropped from %d to 0 entries.', $top_drop['title'], $top_drop['prior'] );
					} else {
						$why_diagnostic = sprintf( '"%s" decreased from %d to %d entries (%d%%).', $top_drop['title'], $top_drop['prior'], $top_drop['last'], (int) $top_drop['pct'] );
					}
				} else {
					$why_diagnostic = sprintf( 'Overall volume decreased by %d%% vs 3-month rolling average.', (int) abs( $vs_avg_pct ) );
				}
			}

			$site['forms']              = ! empty( $forms ) && is_array( $forms ) ? $forms : array();
			$site['mtd_active']         = $site_mtd_act;
			$site['m1_active']          = $site_m1_act;
			$site['m2_active']          = $site_m2_act;
			$site['m3_active']          = $site_m3_act;
			$site['rolling_avg_3m']     = $site_rolling_avg_round;
			$site['vs_avg_change_pct']  = (int) $vs_avg_pct;
			$site['traffic_tier']       = $traffic_tier;
			$site['applied_threshold']  = $applied_threshold;
			$site['is_concern']         = $is_concern;
			$site['why_diagnostic']     = $why_diagnostic;
		}

		return $sites;
	}
}

MainWP_GF_Triage_DB::instance();
