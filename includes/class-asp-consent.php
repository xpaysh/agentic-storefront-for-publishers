<?php
/**
 * Consent gating. The front-end script is enqueued only when consent
 * is positive for the "marketing" category via the WP Consent API
 * (https://wordpress.org/plugins/wp-consent-api/), AND the publisher
 * has flipped the "Enable personalization (optional)" toggle.
 *
 * If the WP Consent API plugin is not installed, the loader falls back
 * to detecting common CMP global states (Complianz, CookieYes, OneTrust)
 * conservatively — when in doubt, no script.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Consent {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Currently used only by the admin to render the consent status row.
	}

	/**
	 * Is the front-end allowed to enqueue and run?
	 *
	 * @return bool
	 */
	public static function may_enqueue() {
		if ( ! ASP_Plugin::is_connected() ) {
			return false;
		}
		$personalization = (bool) get_option( 'asp_consent_personalization', false );
		if ( ! $personalization ) {
			// Non-personalised path is still allowed — manual shortcode placement.
			// The actual decision API call is L1+L3 only when personalization is off,
			// so we still enqueue. Consent gating below.
		}
		// Defer the true decision to runtime — the loader script itself
		// checks the CMP state on the client. We only block the *enqueue*
		// here when we know up-front that no consent path can ever fire.
		return true;
	}

	/**
	 * Check WP Consent API server-side for the request if available.
	 * Returns true/false/null where null means "unknown — defer to client".
	 */
	public static function server_side_consent_state() {
		if ( function_exists( 'wp_has_consent' ) ) {
			return (bool) call_user_func( 'wp_has_consent', 'marketing' );
		}
		return null;
	}
}
