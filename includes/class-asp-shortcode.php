<?php
/**
 * [xpay_recs] shortcode. Outputs a placeholder element the bundled widget
 * SDK reads and hydrates client-side. The shortcode itself emits no scripts;
 * the loader is enqueued by ASP_Loader once per page only when a placeholder
 * is present.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Shortcode {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'xpay_recs', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout' => 'cards',
				'limit'  => 3,
				'title'  => '',
				'theme'  => 'auto',
			),
			$atts,
			'xpay_recs'
		);

		$post = get_post();
		if ( ! $post || ! ASP_Plugin::is_connected() ) {
			return '';
		}

		ASP_Loader::flag_present();

		$context = ASP_REST::build_page_context( $post );
		$config  = array(
			'siteId'  => ASP_Plugin::site_id(),
			'apiBase' => ASP_API_BASE,
			'layout'  => sanitize_key( $atts['layout'] ),
			'limit'   => max( 1, min( 12, (int) $atts['limit'] ) ),
			'title'   => sanitize_text_field( $atts['title'] ),
			'theme'   => sanitize_key( $atts['theme'] ),
			'context' => $context,
		);

		$config_json = wp_json_encode( $config );

		ob_start();
		?>
		<div class="asp-recs" data-asp-mount="1">
			<script type="application/json" class="asp-recs-config"><?php echo wp_kses_post( $config_json ); ?></script>
			<noscript><a href="<?php echo esc_url( home_url( '/.well-known/agent-storefront.json' ) ); ?>"><?php echo esc_html__( 'Browse recommended products', 'agentic-storefront-for-publishers' ); ?></a></noscript>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
