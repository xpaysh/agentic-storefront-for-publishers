<?php
/**
 * REST + discovery surface for the publisher plugin.
 *
 * Two roles:
 *   1. Internal REST endpoints under namespace `xpayacp/v1` for the admin UI
 *      (connect, disconnect, decide-preview, save-settings).
 *   2. Top-level discovery emitters that respond on bare paths:
 *        - /.well-known/agent-storefront.json
 *        - /llms.txt (append-only fenced block; default OFF)
 *
 * Discovery emitters follow the WC plugin's pattern: register rewrite rules,
 * register a query var, and short-circuit early in template_redirect. The
 * detect-not-clobber probe guarantees we never overwrite an existing file.
 */

defined( 'ABSPATH' ) || exit;

class XPAYACP_REST {

	private static $instance = null;
	const NAMESPACE_V1       = 'xpayacp/v1';
	const QUERY_VAR          = 'xpayacp_route';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_discovery' ), 0 );

		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );
	}

	public function maybe_flush_rewrites() {
		if ( get_option( 'xpayacp_flush_rewrites' ) ) {
			flush_rewrite_rules();
			delete_option( 'xpayacp_flush_rewrites' );
		}
	}

	// --- WP REST API (xpayacp/v1) --------------------------------------------

	public function register_rest_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_connect' ),
				'permission_callback' => array( $this, 'admin_only' ),
				'args'                => array(
					'site_id'   => array( 'type' => 'string', 'required' => true ),
					'site_name' => array( 'type' => 'string', 'required' => false ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_disconnect' ),
				'permission_callback' => array( $this, 'admin_only' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/decide-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_decide_preview' ),
				'permission_callback' => array( $this, 'admin_only' ),
				'args'                => array(
					'url' => array( 'type' => 'string', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_health' ),
				'permission_callback' => array( $this, 'admin_only' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/page-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_page_context' ),
				/*
				 * The widget iframe is on a separate origin and has no
				 * WordPress nonce, so we cannot use current_user_can().
				 * Instead we require an HMAC the shortcode produced at
				 * render time, signed with the site's secret. The
				 * permission_callback verifies the signature; the body
				 * still exposes only published public-post fields.
				 */
				'permission_callback' => array( $this, 'verify_page_context_signature' ),
				'args'                => array(
					'post_id' => array( 'type' => 'integer', 'required' => true ),
					'ts'      => array( 'type' => 'integer', 'required' => true ),
					'sig'     => array( 'type' => 'string',  'required' => true ),
				),
			)
		);
	}

	public function admin_only( WP_REST_Request $req ) {
		return current_user_can( 'manage_options' );
	}

	const PAGE_CONTEXT_TTL = 6 * HOUR_IN_SECONDS;

	public static function sign_page_context( $post_id, $ts = null ) {
		$token = XPAYACP_Plugin::site_token();
		if ( ! $token ) {
			return null;
		}
		$ts = null === $ts ? time() : (int) $ts;
		$sig = hash_hmac( 'sha256', $post_id . '|' . $ts, $token );
		return array( 'ts' => $ts, 'sig' => $sig );
	}

	public function verify_page_context_signature( WP_REST_Request $req ) {
		$token = XPAYACP_Plugin::site_token();
		if ( ! $token ) {
			return false;
		}
		$post_id = (int) $req->get_param( 'post_id' );
		$ts      = (int) $req->get_param( 'ts' );
		$sig     = (string) $req->get_param( 'sig' );
		if ( $post_id <= 0 || $ts <= 0 || '' === $sig ) {
			return false;
		}
		if ( abs( time() - $ts ) > self::PAGE_CONTEXT_TTL ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $post_id . '|' . $ts, $token );
		return hash_equals( $expected, $sig );
	}

	public function rest_connect( WP_REST_Request $req ) {
		$site_id = sanitize_text_field( (string) $req->get_param( 'site_id' ) );
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{6,64}$/', $site_id ) ) {
			return new WP_Error( 'xpayacp_invalid_site_id', __( 'Invalid site id.', 'xpay-agentic-commerce-for-publishers' ), array( 'status' => 400 ) );
		}
		update_option( 'xpayacp_site_id', $site_id );
		XPAYACP_Emitter_Probe::clear_cache();
		return rest_ensure_response( array( 'ok' => true, 'site_id' => $site_id ) );
	}

	public function rest_disconnect( WP_REST_Request $req ) {
		delete_option( 'xpayacp_site_id' );
		XPAYACP_Emitter_Probe::clear_cache();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function rest_decide_preview( WP_REST_Request $req ) {
		$url     = esc_url_raw( (string) $req->get_param( 'url' ) );
		$context = array(
			'site_id' => XPAYACP_Plugin::site_id(),
			'url'     => $url,
			'preview' => true,
		);
		$resp = XPAYACP_Client::decide( $context );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return rest_ensure_response( $resp );
	}

	public function rest_health( WP_REST_Request $req ) {
		$emitters = XPAYACP_Emitter_Probe::existing_emitters();
		return rest_ensure_response(
			array(
				'connected'         => XPAYACP_Plugin::is_connected(),
				'site_id'           => XPAYACP_Plugin::site_id(),
				'version'           => XPAYACP_VERSION,
				'existing_emitters' => $emitters,
			)
		);
	}

	public function rest_page_context( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'xpayacp_not_found', __( 'Post not found.', 'xpay-agentic-commerce-for-publishers' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'xpayacp_not_found', __( 'Post not found.', 'xpay-agentic-commerce-for-publishers' ), array( 'status' => 404 ) );
		}
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object || empty( $post_type_object->public ) ) {
			return new WP_Error( 'xpayacp_not_found', __( 'Post not found.', 'xpay-agentic-commerce-for-publishers' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::build_page_context( $post ) );
	}

	public static function build_page_context( $post ) {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		return array(
			'post_id'    => $post->ID,
			'url'        => get_permalink( $post ),
			'title'      => $post->post_title,
			'excerpt'    => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'categories' => array_values( $categories ),
			'tags'       => array_values( $tags ),
			'lang'       => get_locale(),
		);
	}

	// --- Discovery emitters (top-level paths) ---------------------------

	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function register_rewrite() {
		add_rewrite_rule( '^\\.well-known/agent-storefront\\.json$', 'index.php?' . self::QUERY_VAR . '=agent_storefront', 'top' );
		add_rewrite_rule( '^llms\\.txt$', 'index.php?' . self::QUERY_VAR . '=llms', 'top' );
	}

	public function maybe_serve_discovery() {
		$route = get_query_var( self::QUERY_VAR );
		if ( ! $route ) {
			// Fallback for hosts where rewrite rules don't fire (Plain permalinks).
			$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$path    = wp_parse_url( $req_uri, PHP_URL_PATH );
			if ( '/.well-known/agent-storefront.json' === $path ) {
				$route = 'agent_storefront';
			} elseif ( '/llms.txt' === $path ) {
				$route = 'llms';
			}
		}

		if ( 'agent_storefront' === $route ) {
			$this->serve_agent_storefront();
			exit;
		}
		if ( 'llms' === $route ) {
			$this->serve_llms_txt();
			exit;
		}
	}

	private function serve_agent_storefront() {
		if ( ! XPAYACP_Plugin::is_connected() ) {
			status_header( 404 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'error' => 'site not connected' ) );
			return;
		}
		if ( ! (bool) get_option( 'xpayacp_emit_agent_storefront', 1 ) ) {
			status_header( 404 );
			return;
		}

		$cache_key = 'xpayacp_agent_storefront_' . XPAYACP_Plugin::site_id();
		$payload   = get_transient( $cache_key );
		if ( ! is_array( $payload ) ) {
			$resp = XPAYACP_Client::agent_card( XPAYACP_Plugin::site_id() );
			if ( is_wp_error( $resp ) ) {
				status_header( 502 );
				header( 'Content-Type: application/json; charset=utf-8' );
				echo wp_json_encode( array( 'error' => 'upstream unavailable' ) );
				return;
			}
			$payload = $resp;
			set_transient( $cache_key, $payload, 15 * MINUTE_IN_SECONDS );
		}

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-ASP-Emitter: asp' );
		header( 'Cache-Control: public, max-age=900' );
		echo wp_json_encode( $payload );
	}

	private function serve_llms_txt() {
		$augment = (bool) get_option( 'xpayacp_emit_llms_augment', 0 );
		if ( ! $augment || ! XPAYACP_Plugin::is_connected() ) {
			status_header( 404 );
			return;
		}

		$existing = XPAYACP_Emitter_Probe::existing_emitters();
		if ( isset( $existing['/llms.txt'] ) && $existing['/llms.txt'] ) {
			// Something else owns /llms.txt — refuse to overwrite.
			status_header( 404 );
			return;
		}

		$cache_key = 'xpayacp_llms_txt_' . XPAYACP_Plugin::site_id();
		$body      = get_transient( $cache_key );
		if ( ! is_string( $body ) ) {
			// All dynamic values are escaped/sanitised at the point of
			// concatenation so the final echo only contains safe content.
			// wp_strip_all_tags() neutralises any HTML the publisher
			// might have placed in their blog name; esc_url_raw() makes
			// the URLs safe even though we're serving text/plain.
			$site_name = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
			$site_url  = esc_url_raw( home_url( '/' ) );
			$agent_url = esc_url_raw( home_url( '/.well-known/agent-storefront.json' ) );
			$body      = "# " . $site_name . "\n";
			$body     .= "Site: " . $site_url . "\n\n";
			$body     .= "<!-- xpay:agent-storefront:begin -->\n";
			$body     .= "Agent storefront discovery for this site is available at:\n";
			$body     .= $agent_url . "\n";
			$body     .= "<!-- xpay:agent-storefront:end -->\n";
			set_transient( $cache_key, $body, 30 * MINUTE_IN_SECONDS );
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-ASP-Emitter: asp' );
		header( 'Cache-Control: public, max-age=1800' );
		// Body composed entirely from pre-sanitised values above; the
		// document is served as text/plain so HTML semantics do not apply.
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
