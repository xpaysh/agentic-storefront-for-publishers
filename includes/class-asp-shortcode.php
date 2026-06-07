<?php
/**
 * [xpay_recs] shortcode. Emits a single transparent <iframe> pointing at
 * widget.xpay.sh/embed/recs/inline — every pixel of UI lives there, this
 * shortcode is pure plumbing. The iframe is sandboxed and consent-gated.
 *
 * Per v0.3.0 architecture: NO bundled JS renderer, NO inline product
 * markup, NO client-side hydration in this plugin. The iframe at
 * widget.xpay.sh handles the entire decision + render flow.
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
				'layout' => 'inline',
				'limit'  => 3,
				'title'  => '',
				'theme'  => 'auto',
				'height' => '420',
			),
			$atts,
			'xpay_recs'
		);

		$post = get_post();
		if ( ! $post || ! ASP_Plugin::is_connected() ) {
			return '';
		}
		if ( ! ASP_Consent::granted() ) {
			return '';
		}

		ASP_Loader::flag_present();

		$site_id    = ASP_Plugin::site_id();
		$context    = ASP_REST::build_page_context( $post );
		$amazon_tag = get_option( 'asp_amazon_tag', '' );

		$qs_args = array(
			'site_id'  => $site_id,
			'layout'   => sanitize_key( $atts['layout'] ),
			'limit'    => max( 1, min( 12, (int) $atts['limit'] ) ),
			'context'  => rawurlencode( wp_json_encode( $context ) ),
			'origin'   => rawurlencode( home_url( '/' ) ),
		);
		if ( $amazon_tag ) {
			$qs_args['amazon_tag'] = sanitize_text_field( $amazon_tag );
		}

		$src    = add_query_arg( $qs_args, ASP_EMBED_BASE . '/embed/recs/inline' );
		$height = max( 200, min( 1200, (int) $atts['height'] ) );
		$title  = $atts['title'] ? $atts['title'] : __( 'Recommended products', 'agentic-storefront-for-publishers' );

		ob_start();
		?>
		<div class="asp-recs-frame" data-asp-mount="1">
			<iframe
				src="<?php echo esc_url( $src ); ?>"
				title="<?php echo esc_attr( $title ); ?>"
				loading="lazy"
				referrerpolicy="no-referrer"
				sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"
				style="width:100%; height:<?php echo esc_attr( $height ); ?>px; border:0; background:transparent;"
				allow="clipboard-write"
			></iframe>
			<noscript><a href="<?php echo esc_url( home_url( '/.well-known/agent-storefront.json' ) ); ?>"><?php echo esc_html__( 'Browse recommended products', 'agentic-storefront-for-publishers' ); ?></a></noscript>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
