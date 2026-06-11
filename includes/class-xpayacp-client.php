<?php
/**
 * HTTP client to the xpay backend. Wraps wp_remote_* with site auth and
 * sensible timeouts. All requests are signed with the site's HMAC token so
 * the backend can validate origin without storing per-site secrets server-side
 * beyond the registry.
 */

defined( 'ABSPATH' ) || exit;

class XPAYACP_Client {

	const TIMEOUT_DECIDE = 5;
	const TIMEOUT_OTHER  = 8;

	public static function decide( $context ) {
		return self::request( 'POST', '/storefront/decide', $context, self::TIMEOUT_DECIDE );
	}

	public static function register( $payload ) {
		return self::request( 'POST', '/storefront/register', $payload, self::TIMEOUT_OTHER );
	}

	public static function agent_card( $site_id ) {
		return self::request( 'GET', '/storefront/agent-card/' . rawurlencode( $site_id ), null, self::TIMEOUT_OTHER );
	}

	public static function site_context( $payload ) {
		return self::request( 'POST', '/storefront/site-context', $payload, self::TIMEOUT_OTHER );
	}

	public static function beacon( $payload ) {
		return self::request( 'POST', '/storefront/beacon', $payload, self::TIMEOUT_OTHER );
	}

	public static function health() {
		return self::request( 'GET', '/storefront/health', null, self::TIMEOUT_OTHER );
	}

	private static function request( $method, $path, $body, $timeout ) {
		$url     = trailingslashit( XPAYACP_API_BASE ) . ltrim( $path, '/' );
		$site_id = XPAYACP_Plugin::site_id();
		$token   = XPAYACP_Plugin::site_token();

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'Accept'         => 'application/json',
				'X-ASP-Version'  => XPAYACP_VERSION,
				'X-ASP-Site-Id'  => $site_id,
			),
		);

		if ( null !== $body ) {
			$json         = wp_json_encode( $body );
			$args['body'] = $json;
			if ( $token ) {
				$args['headers']['X-ASP-Signature'] = hash_hmac( 'sha256', $json, $token );
			}
		}

		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$json = json_decode( $raw, true );

		if ( $code >= 400 ) {
			return new WP_Error( 'xpayacp_http_' . $code, is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'Upstream error.' );
		}

		return is_array( $json ) ? $json : array();
	}
}
