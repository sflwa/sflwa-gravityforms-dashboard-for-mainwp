<?php
/**
 * Plugin Name:       MainWP Gravity Forms Triage
 * Plugin URI:        https://southfloridawebadvisors.com/
 * Description:       High-efficiency Gravity Forms status board for MainWP. Tracks form activity, spam counts, churn alerts, and 3-month rolling averages across child sites.
 * Version:           1.0.20
 * Author:            South Florida Web Advisors
 * Author URI:        https://southfloridawebadvisors.com/
 * Contributors:      sflwa
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mainwp-gravity-forms-triage
 * Requires at least: 5.8
 * Tested up to:      7.1
 * Requires PHP:      7.4
 *
 * @package           MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MWP_GF_TRIAGE_VERSION', '1.0.20' );
define( 'MWP_GF_TRIAGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MWP_GF_TRIAGE_URL', plugin_dir_url( __FILE__ ) );

require_once MWP_GF_TRIAGE_PATH . 'includes/class-gf-triage-db.php';
require_once MWP_GF_TRIAGE_PATH . 'includes/class-gf-triage-cron.php';
require_once MWP_GF_TRIAGE_PATH . 'includes/class-gf-triage-ajax.php';
require_once MWP_GF_TRIAGE_PATH . 'includes/class-gf-triage-admin.php';

// Include shared SFLWA support handler module if present in plugin root
if ( file_exists( plugin_dir_path( __FILE__ ) . 'sflwa-support-handler.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'sflwa-support-handler.php';

	if ( function_exists( 'sflwa_register_support_page' ) ) {
		sflwa_register_support_page(
			array(
				'plugin_name'   => 'MainWP Gravity Forms Triage',
				'plugin_slug'   => 'mainwp-gravity-forms-triage',
				'text_domain'   => 'mainwp-gravity-forms-triage',
				'plugin_file'   => __FILE__,
				'submenu_title' => 'GF Triage',
				'description'   => 'High-efficiency Gravity Forms status board for MainWP. Tracks form activity, spam counts, churn alerts, and 3-month rolling averages across child sites.',
				'version'       => '1.0.20',
			)
		);
	}
}

/**
 * Main Plugin Class
 */
class MainWP_GF_Triage {

	/**
	 * Instance variable.
	 *
	 * @var MainWP_GF_Triage|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MainWP_GF_Triage
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
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'admin_notices', array( $this, 'check_dependencies' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule_cron' ) );

		MainWP_GF_Triage_Cron::instance();
		MainWP_GF_Triage_Ajax::instance();
		MainWP_GF_Triage_Admin::instance();
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		MainWP_GF_Triage_DB::instance()->create_tables();

		if ( ! wp_next_scheduled( 'mainwp_gf_triage_monthly_cron_hook' ) ) {
			$first_run = strtotime( 'first day of next month 02:00:00' );
			wp_schedule_event( $first_run, 'monthly', 'mainwp_gf_triage_monthly_cron_hook' );
		}
	}

	/**
	 * Deactivation hook.
	 */
	public function deactivate() {
		$timestamp = wp_next_scheduled( 'mainwp_gf_triage_monthly_cron_hook' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mainwp_gf_triage_monthly_cron_hook' );
		}
	}

	/**
	 * Self-healing check to ensure WP-Cron is always scheduled.
	 */
	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'mainwp_gf_triage_monthly_cron_hook' ) ) {
			$first_run = strtotime( 'first day of next month 02:00:00' );
			wp_schedule_event( $first_run, 'monthly', 'mainwp_gf_triage_monthly_cron_hook' );
		}
	}

	/**
	 * Register Configurable Volume Tiers and Drop Thresholds.
	 */
	public function register_settings() {
		register_setting( 'mwp_gf_triage_settings', 'mwp_gf_low_vol_max', array( 'default' => 25, 'sanitize_callback' => 'absint' ) );
		register_setting( 'mwp_gf_triage_settings', 'mwp_gf_low_vol_drop', array( 'default' => 80, 'sanitize_callback' => 'absint' ) );
		
		register_setting( 'mwp_gf_triage_settings', 'mwp_gf_med_vol_max', array( 'default' => 100, 'sanitize_callback' => 'absint' ) );
		register_setting( 'mwp_gf_triage_settings', 'mwp_gf_med_vol_drop', array( 'default' => 50, 'sanitize_callback' => 'absint' ) );
		
		register_setting( 'mwp_gf_triage_settings', 'mwp_gf_high_vol_drop', array( 'default' => 25, 'sanitize_callback' => 'absint' ) );
	}

	/**
	 * Dependency Notice for MainWP.
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'MainWP\Dashboard\MainWP' ) && ! class_exists( 'MainWP_DB' ) ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>MainWP Gravity Forms Triage:</strong> Requires the <a href="https://mainwp.com/" target="_blank">MainWP Dashboard</a> plugin to be installed and active.';
			echo '</p></div>';
		}
	}
}

add_action( 'plugins_loaded', function() {
	MainWP_GF_Triage::instance();
} );
