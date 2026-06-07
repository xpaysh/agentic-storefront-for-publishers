<?php
/**
 * REST + discovery surface for the publisher plugin.
 *
 * Two roles:
 *   1. Internal REST endpoints under namespace `asp/v1` for the admin UI
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

class ASP_REST {

	private static $instance = null;
	const NAMESPACE_V1       = 'asp/v1';
	const QUERY_VAR          = 'asp_route';

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
		if ( get_option( 'asp_flush_rewrites' ) ) {
			flush_rewrite_rules();
			delete_option( 'asp_flush_rewrites' );
		}
	}

	// --- WP REST API (asp/v1) --------------------------------------------

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

		// POST /asp/v1/settings — receives validated settings JSON from the
		// settings iframe via the WP-admin postMessage bridge. Writes to
		// wp_options (sanitisers registered in ASP_Settings::register_settings
		// also fire for defence in depth). Returns the canonical settings
		// snapshot so the iframe can confirm.
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_settings_save' ),
				'permission_callback' => array( $this, 'admin_only' ),
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
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);
	}

	public function admin_only( WP_REST_Request $req ) {
		return current_user_can( 'manage_options' );
	}

	public function rest_connect( WP_REST_Request $req ) {
		$site_id = sanitize_text_field( (string) $req->get_param( 'site_id' ) );
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{6,64}$/', $site_id ) ) {
			return new WP_Error( 'asp_invalid_site_id', __( 'Invalid site id.', 'agentic-storefront-for-publishers' ), array( 'status' => 400 ) );
		}
		update_option( 'asp_site_id', $site_id );
		ASP_Emitter_Probe::clear_cache();
		return rest_ensure_response( array( 'ok' => true, 'site_id' => $site_id ) );
	}

	public function rest_disconnect( WP_REST_Request $req ) {
		delete_option( 'asp_site_id' );
		ASP_Emitter_Probe::clear_cache();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function rest_decide_preview( WP_REST_Request $req ) {
		$url     = esc_url_raw( (string) $req->get_param( 'url' ) );
		$context = array(
			'site_id' => ASP_Plugin::site_id(),
			'url'     => $url,
			'preview' => true,
		);
		$resp = ASP_Client::decide( $context );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return rest_ensure_response( $resp );
	}

	public function rest_settings_save( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'asp_bad_body', __( 'Invalid settings payload.', 'agentic-storefront-for-publishers' ), array( 'status' => 400 ) );
		}

		$settings = ASP_Settings::instance();

		// Run each field through its registered sanitiser. The sanitisers
		// already drop bad input (e.g. malformed Amazon tag → empty string),
		// so we never store anything dangerous.
		$amazon_tag         = isset( $body['amazon_tag'] ) ? $settings->sanitize_amazon_tag( $body['amazon_tag'] ) : '';
		$exclude_categories = $this->array_to_csv( isset( $body['exclude_categories'] ) ? $body['exclude_categories'] : array() );
		$exclude_domains    = $this->array_to_csv( isset( $body['exclude_domains'] ) ? $body['exclude_domains'] : array() );
		$consent_perso      = ! empty( $body['consent_personalization'] );
		$emit_agent         = ! empty( $body['emit_agent_storefront'] );
		$emit_llms          = ! empty( $body['emit_llms_augment'] );
		// auto_inject defaults to TRUE on first install so the widget renders
		// without manual shortcode placement. If the body omits the field,
		// keep whatever value is currently stored (don't accidentally flip
		// it on/off via a partial save).
		$auto_inject = array_key_exists( 'auto_inject', $body )
			? ! empty( $body['auto_inject'] )
			: (bool) get_option( 'asp_auto_inject', true );

		update_option( 'asp_amazon_tag', $amazon_tag );
		update_option( 'asp_exclude_categories', $settings->sanitize_csv( $exclude_categories ) );
		update_option( 'asp_exclude_domains', $settings->sanitize_csv( $exclude_domains ) );
		update_option( 'asp_auto_inject', $auto_inject ? 1 : 0 );
		update_option( 'asp_consent_personalization', $consent_perso ? 1 : 0 );
		update_option( 'asp_emit_agent_storefront', $emit_agent ? 1 : 0 );
		update_option( 'asp_emit_llms_augment', $emit_llms ? 1 : 0 );

		if ( class_exists( 'ASP_Emitter_Probe' ) ) {
			ASP_Emitter_Probe::clear_cache();
		}

		return rest_ensure_response( array(
			'ok'       => true,
			'settings' => array(
				'amazon_tag'              => (string) get_option( 'asp_amazon_tag', '' ),
				'exclude_categories'      => $this->csv_to_array( (string) get_option( 'asp_exclude_categories', '' ) ),
				'exclude_domains'         => $this->csv_to_array( (string) get_option( 'asp_exclude_domains', '' ) ),
				'auto_inject'             => (bool) get_option( 'asp_auto_inject', true ),
				'consent_personalization' => (bool) get_option( 'asp_consent_personalization', false ),
				'emit_agent_storefront'   => (bool) get_option( 'asp_emit_agent_storefront', true ),
				'emit_llms_augment'       => (bool) get_option( 'asp_emit_llms_augment', false ),
			),
		) );
	}

	private function array_to_csv( $arr ) {
		if ( ! is_array( $arr ) ) {
			return (string) $arr;
		}
		$arr = array_map( 'strval', $arr );
		$arr = array_map( 'trim', $arr );
		$arr = array_filter( $arr, 'strlen' );
		return implode( ', ', $arr );
	}

	private function csv_to_array( $csv ) {
		$parts = array_map( 'trim', explode( ',', (string) $csv ) );
		$parts = array_filter( $parts, 'strlen' );
		return array_values( $parts );
	}

	public function rest_health( WP_REST_Request $req ) {
		$emitters = ASP_Emitter_Probe::existing_emitters();
		return rest_ensure_response(
			array(
				'connected'         => ASP_Plugin::is_connected(),
				'site_id'           => ASP_Plugin::site_id(),
				'version'           => ASP_VERSION,
				'existing_emitters' => $emitters,
			)
		);
	}

	public function rest_page_context( WP_REST_Request $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'asp_not_found', __( 'Post not found.', 'agentic-storefront-for-publishers' ), array( 'status' => 404 ) );
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
		if ( ! ASP_Plugin::is_connected() ) {
			status_header( 404 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'error' => 'site not connected' ) );
			return;
		}
		if ( ! (bool) get_option( 'asp_emit_agent_storefront', 1 ) ) {
			status_header( 404 );
			return;
		}

		$cache_key = 'asp_agent_storefront_' . ASP_Plugin::site_id();
		$payload   = get_transient( $cache_key );
		if ( ! is_array( $payload ) ) {
			$resp = ASP_Client::agent_card( ASP_Plugin::site_id() );
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
		$augment = (bool) get_option( 'asp_emit_llms_augment', 0 );
		if ( ! $augment || ! ASP_Plugin::is_connected() ) {
			status_header( 404 );
			return;
		}

		$existing = ASP_Emitter_Probe::existing_emitters();
		if ( isset( $existing['/llms.txt'] ) && $existing['/llms.txt'] ) {
			// Something else owns /llms.txt — refuse to overwrite.
			status_header( 404 );
			return;
		}

		$cache_key = 'asp_llms_txt_' . ASP_Plugin::site_id();
		$body      = get_transient( $cache_key );
		if ( ! is_string( $body ) ) {
			$site_name = get_bloginfo( 'name' );
			$site_url  = home_url( '/' );
			$body      = "# " . $site_name . "\n";
			$body     .= "Site: " . $site_url . "\n\n";
			$body     .= "<!-- xpay:agent-storefront:begin -->\n";
			$body     .= "Agent storefront discovery for this site is available at:\n";
			$body     .= home_url( '/.well-known/agent-storefront.json' ) . "\n";
			$body     .= "<!-- xpay:agent-storefront:end -->\n";
			set_transient( $cache_key, $body, 30 * MINUTE_IN_SECONDS );
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-ASP-Emitter: asp' );
		header( 'Cache-Control: public, max-age=1800' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
