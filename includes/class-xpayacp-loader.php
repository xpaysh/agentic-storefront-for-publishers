<?php
/**
 * Front-end loader. Registers `widget.xpay.sh/widget/v1/widget.js` via
 * the WordPress enqueue API and decorates the rendered <script> tag
 * with the data-* attributes the widget consumer expects (`site-id`,
 * `storefront-api`, `embed-base`, `amazon-tag`, `surfaces`).
 *
 * The script handles the bottom-right FAB and the sticky footer drawer
 * surfaces. The shortcode-emitted inline iframe is a SEPARATE surface
 * (rendered by class-asp-shortcode.php) and stays visible even when
 * this loader is short-circuited.
 *
 * Going through `wp_register_script` / `wp_enqueue_script` (rather than
 * `printf`-ing a raw <script> tag) is what WordPress.org plugin-check
 * insists on so that:
 *   - other plugins can dequeue / reorder our script if they need to
 *   - script-strategy hints (async/defer) flow through the standard
 *     mechanism
 *   - the public scripts list is accurate for caching plugins and
 *     security scanners
 */

defined( 'ABSPATH' ) || exit;

class XPAYACP_Loader {

	const HANDLE = 'xpayacp-widget';

	private static $present  = false;
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget' ), 50 );
		add_filter( 'script_loader_tag', array( $this, 'decorate_script_tag' ), 10, 2 );
	}

	/**
	 * Register + enqueue the widget script on frontend pages.
	 *
	 * Skips when:
	 *   - site isn't connected (no site_id to send to /decide)
	 *   - consent gate is hard-false
	 *   - we're in admin / feed / REST / AJAX / embed previews
	 */
	public function enqueue_widget() {
		if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! XPAYACP_Plugin::is_connected() ) {
			return;
		}
		// Default ON; publishers can opt out from Settings → xpay Agentic
		// Commerce. The FAB and footer drawer surfaces are what makes this
		// "install once, works on every page" — turning the toggle off
		// restricts the widget to shortcode/block placements only.
		if ( ! (bool) get_option( 'xpayacp_enable_widget', true ) ) {
			return;
		}
		if ( ! $this->path_matches_rules() ) {
			return;
		}
		if ( ! XPAYACP_Consent::granted() ) {
			return;
		}

		$widget_src = defined( 'XPAYACP_EMBED_BASE' )
			? rtrim( XPAYACP_EMBED_BASE, '/' ) . '/widget/v1/widget.js'
			: 'https://widget.xpay.sh/widget/v1/widget.js';

		wp_register_script(
			self::HANDLE,
			$widget_src,
			array(),
			XPAYACP_VERSION,
			true
		);
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Inject the data-* attributes + the async hint on our handle only.
	 * Other plugins' <script> tags pass through untouched.
	 *
	 * widget.js reads these attributes at boot to know which site it's
	 * mounting for, which backend to call, and which surfaces to render.
	 */
	/**
	 * PostHog-style include/exclude path matching against REQUEST_URI.
	 *
	 *   - empty include list  → all paths eligible
	 *   - non-empty include   → only matching paths eligible
	 *   - non-empty exclude   → matching paths always blocked (wins over include)
	 *
	 * Wildcards: `*` (any chars), `?` (single char) via fnmatch.
	 */
	private function path_matches_rules() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path    = (string) wp_parse_url( $req_uri, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}

		$include = self::parse_patterns( (string) get_option( 'xpayacp_include_patterns', '' ) );
		$exclude = self::parse_patterns( (string) get_option( 'xpayacp_exclude_patterns', '' ) );

		foreach ( $exclude as $p ) {
			if ( fnmatch( $p, $path ) ) {
				return false;
			}
		}
		if ( empty( $include ) ) {
			return true;
		}
		foreach ( $include as $p ) {
			if ( fnmatch( $p, $path ) ) {
				return true;
			}
		}
		return false;
	}

	public static function parse_patterns( $raw ) {
		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		$lines = array_map( 'trim', is_array( $lines ) ? $lines : array() );
		$lines = array_filter( $lines, 'strlen' );
		return array_values( $lines );
	}

	public function decorate_script_tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		$site_id    = (string) get_option( 'xpayacp_site_id', '' );
		$amazon_tag = (string) get_option( 'xpayacp_amazon_tag', '' );
		$api_base   = defined( 'XPAYACP_API_BASE' )   ? XPAYACP_API_BASE   : 'https://publisher-api.xpay.sh';
		$embed_base = defined( 'XPAYACP_EMBED_BASE' ) ? XPAYACP_EMBED_BASE : 'https://widget.xpay.sh';

		$attrs = sprintf(
			' async data-site-id="%s" data-storefront-api="%s" data-embed-base="%s" data-amazon-tag="%s" data-surfaces="fab,drawer"',
			esc_attr( $site_id ),
			esc_url( $api_base ),
			esc_url( $embed_base ),
			esc_attr( $amazon_tag )
		);

		// Inject our extra attributes right after the opening <script
		// token. Single-replacement to avoid touching any other tag.
		return preg_replace( '/<script /', '<script' . $attrs . ' ', $tag, 1 );
	}
}
