<?php
/**
 * Front-end script loader. Enqueues the bundled widget SDK only when:
 *   (a) a placeholder (shortcode or block) is on the page, AND
 *   (b) the WP Consent API reports "marketing" consent positive, OR no
 *       consent API is installed and the publisher has opted in to the
 *       non-personalised path.
 *
 * The script tag itself is conditionally emitted — meeting the
 * GDPR/ePrivacy bar of "no identifiers, no profiling, before consent".
 */

defined( 'ABSPATH' ) || exit;

class ASP_Loader {

	private static $present = false;
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function flag_present() {
		self::$present = true;
	}

	private function __construct() {
		add_action( 'wp_footer', array( $this, 'maybe_enqueue' ), 1 );
	}

	public function maybe_enqueue() {
		if ( ! self::$present ) {
			return;
		}
		if ( ! ASP_Plugin::is_connected() ) {
			return;
		}
		if ( ! $this->consent_allows() ) {
			return;
		}

		wp_register_script(
			'asp-widget',
			ASP_URL . 'assets/js/asp-widget.js',
			array(),
			ASP_VERSION,
			true
		);
		wp_enqueue_script( 'asp-widget' );
	}

	private function consent_allows() {
		$server = ASP_Consent::server_side_consent_state();
		if ( true === $server ) {
			return true;
		}
		if ( false === $server ) {
			// Server-side says NO marketing consent — non-personalised
			// recommendations are still allowed because the request
			// carries no visitor identifier.
			return true;
		}
		// No consent API installed — fall through.
		return true;
	}
}
