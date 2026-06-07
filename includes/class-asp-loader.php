<?php
/**
 * Front-end loader. In v0.3.0 there is NO bundled JS to enqueue — each
 * shortcode emits its own <iframe> pointing at widget.xpay.sh/embed/recs.
 * This class is kept as a no-op placeholder for backwards-compatibility
 * with any external code that called `ASP_Loader::flag_present()`.
 *
 * If a future surface needs a per-page bridge script (e.g. for inline
 * resize postMessage handling) it can be enqueued from here. None today.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Loader {

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
		// widget.js owns the FAB + footer drawer surfaces. It's a global
		// script that mounts once per page, observes the URL/content, and
		// renders the bottom-right FAB + sticky footer drawer iframes.
		// The shortcode-emitted inline iframe is a SEPARATE surface and
		// stays even when widget.js is disabled.
		add_action( 'wp_footer', array( $this, 'enqueue_widget' ), 50 );
	}

	/**
	 * Emit the widget.js script tag on frontend pages so the FAB and
	 * footer drawer surfaces render.
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
		if ( ! ASP_Plugin::is_connected() ) {
			return;
		}
		if ( ! ASP_Consent::granted() ) {
			return;
		}

		$site_id        = (string) get_option( 'asp_site_id', '' );
		$api_base       = defined( 'ASP_API_BASE' ) ? ASP_API_BASE : 'https://publisher-api.xpay.sh';
		$widget_src     = defined( 'ASP_EMBED_BASE' ) ? rtrim( ASP_EMBED_BASE, '/' ) . '/widget/v1/widget.js' : 'https://widget.xpay.sh/widget/v1/widget.js';
		$embed_base     = defined( 'ASP_EMBED_BASE' ) ? ASP_EMBED_BASE : 'https://widget.xpay.sh';
		$amazon_tag     = (string) get_option( 'asp_amazon_tag', '' );

		// data-surfaces controls which of widget.js's surfaces mount.
		// "fab" alone = bottom-right FAB only. "fab,drawer" = FAB + a
		// sticky footer drawer pinned to the viewport bottom.
		printf(
			'<script async src="%s" data-site-id="%s" data-storefront-api="%s" data-embed-base="%s" data-amazon-tag="%s" data-surfaces="fab,drawer"></script>' . "\n",
			esc_url( $widget_src ),
			esc_attr( $site_id ),
			esc_url( $api_base ),
			esc_url( $embed_base ),
			esc_attr( $amazon_tag )
		);
	}
}
