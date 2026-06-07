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
		// No front-end script enqueue in v0.3.0 — the iframe at
		// widget.xpay.sh/embed/recs/inline owns its own runtime.
	}
}
