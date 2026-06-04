<?php
/**
 * Plugin bootstrap — loads each subsystem and wires activation hooks.
 */

defined( 'ABSPATH' ) || exit;

require_once ASP_PATH . 'includes/class-asp-client.php';
require_once ASP_PATH . 'includes/class-asp-consent.php';
require_once ASP_PATH . 'includes/class-asp-emitter-probe.php';
require_once ASP_PATH . 'includes/class-asp-rest.php';
require_once ASP_PATH . 'includes/class-asp-settings.php';
require_once ASP_PATH . 'includes/class-asp-shortcode.php';
require_once ASP_PATH . 'includes/class-asp-block.php';
require_once ASP_PATH . 'includes/class-asp-loader.php';

class ASP_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		ASP_REST::instance();
		ASP_Settings::instance();
		ASP_Shortcode::instance();
		ASP_Block::instance();
		ASP_Loader::instance();

		if ( is_admin() ) {
			ASP_Consent::instance();
		}

		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		$this->maybe_handle_version_bump();
	}

	private function maybe_handle_version_bump() {
		$stored = get_option( 'asp_installed_version' );
		if ( $stored === ASP_VERSION ) {
			return;
		}
		update_option( 'asp_flush_rewrites', 1 );
		update_option( 'asp_installed_version', ASP_VERSION );
	}

	public function maybe_redirect_after_activation() {
		if ( ! get_transient( 'asp_post_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'asp_post_activation_redirect' );
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( wp_safe_redirect( admin_url( 'options-general.php?page=agentic-storefront-for-publishers' ) ) ) {
			exit;
		}
	}

	public static function on_activate() {
		if ( ! get_option( 'asp_site_token' ) ) {
			update_option( 'asp_site_token', wp_generate_password( 32, false ) );
		}
		update_option( 'asp_flush_rewrites', 1 );

		if ( ! get_option( 'asp_first_activated_at' ) ) {
			update_option( 'asp_first_activated_at', time() );
		}

		if ( ! self::is_connected() ) {
			set_transient( 'asp_post_activation_redirect', 1, 60 );
		}
	}

	public static function on_deactivate() {
		flush_rewrite_rules();
	}

	public static function is_connected() {
		return (bool) get_option( 'asp_site_id' );
	}

	public static function site_id() {
		return (string) get_option( 'asp_site_id', '' );
	}

	public static function site_token() {
		return (string) get_option( 'asp_site_token', '' );
	}
}
