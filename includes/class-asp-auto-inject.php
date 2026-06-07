<?php
/**
 * Auto-inject the recommendation widget into single-post content via the
 * `the_content` filter. Default ON so publishers see the widget render the
 * moment they activate the plugin + complete connect — no manual
 * shortcode placement required.
 *
 * Skipped when:
 *   - Setting asp_auto_inject is OFF.
 *   - The post body already contains the [xpay_recs] shortcode (avoid
 *     double-render when a publisher has placed it manually).
 *   - The current request isn't a single post (archives, search, feeds,
 *     admin previews, REST responses).
 *   - ASP_Consent::granted() is false.
 *   - The site isn't connected to an xpay account.
 *
 * Position: append to the end of `the_content`. Future v0.4 may add
 * `mid-content` placement once we have enough signal that bottom-of-post
 * conversion is acceptable.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Auto_Inject {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// priority 20 so it runs AFTER WordPress's own wpautop, oEmbed, and
		// most third-party content filters that the publisher's theme might
		// have stacked on the_content. Filters that rely on the appended
		// iframe ID can still hook at >20.
		add_filter( 'the_content', array( $this, 'maybe_append' ), 20 );
	}

	public function maybe_append( $content ) {
		// Only on canonical post pages in the main loop. Skip admin,
		// feeds, AJAX, REST, embeds, archives, search.
		if ( ! is_singular( array( 'post' ) ) ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		// Respect the publisher's toggle.
		if ( ! (bool) get_option( 'asp_auto_inject', true ) ) {
			return $content;
		}

		// Skip if the publisher placed the shortcode/block manually — they
		// already chose where the widget goes.
		if ( false !== strpos( (string) $content, '[xpay_recs' ) ||
			 false !== strpos( (string) $content, 'wp:asp/recommendations' ) ) {
			return $content;
		}

		if ( ! ASP_Plugin::is_connected() ) {
			return $content;
		}
		if ( ! ASP_Consent::granted() ) {
			return $content;
		}

		// Render via shortcode so we have ONE rendering path through the
		// plugin (shortcode → iframe). Reuses the existing iframe URL +
		// sandbox + height defaults.
		$injected = do_shortcode( '[xpay_recs]' );
		if ( ! is_string( $injected ) || '' === $injected ) {
			return $content;
		}

		return $content . $injected;
	}
}
