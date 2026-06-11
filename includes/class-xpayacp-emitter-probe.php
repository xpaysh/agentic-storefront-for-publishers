<?php
/**
 * Detect-not-clobber probe for /llms.txt and /.well-known/agent-storefront.json.
 *
 * Carries the same hard rule as the WC plugin family
 * ([[feedback_plugin_must_not_clobber_existing_standards_files]]): if some
 * other plugin or static file is already serving the path, we refuse to
 * register our own emitter for it. The probe uses an HTTP self-probe with
 * a unique header so we recognise our own response when we are the one
 * answering.
 */

defined( 'ABSPATH' ) || exit;

class XPAYACP_Emitter_Probe {

	const PROBE_HEADER     = 'X-ASP-Probe';
	const TRANSIENT_KEY    = 'xpayacp_emitter_probe';
	const TRANSIENT_TTL    = HOUR_IN_SECONDS * 6;
	const PROBE_TIMEOUT    = 4;

	/**
	 * Returns associative array of path => bool (true = path is already served
	 * by something else and our emitter should stay silent).
	 */
	public static function existing_emitters() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$paths = array(
			'/llms.txt',
			'/.well-known/agent-storefront.json',
		);

		$result = array();
		foreach ( $paths as $path ) {
			$result[ $path ] = self::probe_path( $path );
		}

		set_transient( self::TRANSIENT_KEY, $result, self::TRANSIENT_TTL );
		return $result;
	}

	private static function probe_path( $path ) {
		$url  = home_url( $path );
		$resp = wp_remote_get(
			$url,
			array(
				'timeout'     => self::PROBE_TIMEOUT,
				'redirection' => 1,
				'headers'     => array( self::PROBE_HEADER => '1' ),
				'sslverify'   => false,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( 200 !== (int) $code ) {
			return false;
		}
		$emitted_by = wp_remote_retrieve_header( $resp, 'x-asp-emitter' );
		if ( 'asp' === $emitted_by ) {
			// It's our own response — path is ours, no conflict.
			return false;
		}
		return true;
	}

	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
