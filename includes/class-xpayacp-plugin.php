<?php
/**
 * Plugin bootstrap — loads each subsystem and wires activation hooks.
 */

defined( 'ABSPATH' ) || exit;

require_once XPAYACP_PATH . 'includes/class-xpayacp-client.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-consent.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-emitter-probe.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-rest.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-settings.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-shortcode.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-block.php';
require_once XPAYACP_PATH . 'includes/class-xpayacp-loader.php';

class XPAYACP_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		XPAYACP_REST::instance();
		XPAYACP_Settings::instance();
		XPAYACP_Shortcode::instance();
		XPAYACP_Block::instance();
		XPAYACP_Loader::instance();

		if ( is_admin() ) {
			XPAYACP_Consent::instance();
		}

		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		$this->maybe_handle_version_bump();
	}

	private function maybe_handle_version_bump() {
		$stored = get_option( 'xpayacp_installed_version' );
		if ( $stored === XPAYACP_VERSION ) {
			return;
		}
		update_option( 'xpayacp_flush_rewrites', 1 );
		update_option( 'xpayacp_installed_version', XPAYACP_VERSION );
	}

	public function maybe_redirect_after_activation() {
		if ( ! get_transient( 'xpayacp_post_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'xpayacp_post_activation_redirect' );
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( wp_safe_redirect( admin_url( 'options-general.php?page=xpay-agentic-commerce-for-publishers' ) ) ) {
			exit;
		}
	}

	public static function on_activate() {
		if ( ! get_option( 'xpayacp_site_token' ) ) {
			update_option( 'xpayacp_site_token', wp_generate_password( 32, false ) );
		}
		update_option( 'xpayacp_flush_rewrites', 1 );

		if ( ! get_option( 'xpayacp_first_activated_at' ) ) {
			update_option( 'xpayacp_first_activated_at', time() );
		}

		if ( ! self::is_connected() ) {
			set_transient( 'xpayacp_post_activation_redirect', 1, 60 );
		}
	}

	public static function on_deactivate() {
		flush_rewrite_rules();
	}

	public static function is_connected() {
		return (bool) get_option( 'xpayacp_site_id' );
	}

	public static function site_id() {
		return (string) get_option( 'xpayacp_site_id', '' );
	}

	public static function site_token() {
		return (string) get_option( 'xpayacp_site_token', '' );
	}
}
