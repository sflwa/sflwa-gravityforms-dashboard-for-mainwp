<?php
/**
 * Admin Page Controller for MainWP Gravity Forms Triage.
 *
 * @package MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MainWP_GF_Triage_Admin
 */
class MainWP_GF_Triage_Admin {

	/**
	 * Instance variable.
	 *
	 * @var MainWP_GF_Triage_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MainWP_GF_Triage_Admin
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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 99 );
	}

	/**
	 * Register Admin Menu under regular WP-Admin.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'GF Status Board', 'mainwp-gravity-forms-triage' ),
			__( 'GF Status Board', 'mainwp-gravity-forms-triage' ),
			'manage_options',
			'mainwp-gf-triage',
			array( $this, 'render_admin_dashboard' ),
			'dashicons-feedback',
			30
		);
	}

	/**
	 * Render Dashboard Template.
	 */
	public function render_admin_dashboard() {
		$template = MWP_GF_TRIAGE_PATH . 'templates/admin-dashboard-view.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}

MainWP_GF_Triage_Admin::instance();
