<?php
/**
 * Gutenberg "Recommendations" block. Server-rendered so the same shortcode
 * payload path is used on the front-end. No client-side JS for the block
 * editor — uses a static `block.json` registration with `render_callback`.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Block {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			ASP_PATH . 'assets/blocks/recommendations',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	public function render( $attributes, $content, $block ) {
		$limit  = isset( $attributes['limit'] ) ? max( 1, min( 12, (int) $attributes['limit'] ) ) : 3;
		$layout = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'cards';
		$title  = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';

		// Delegate to the shortcode for the actual render to keep one path.
		$shortcode = sprintf(
			'[xpay_recs layout="%1$s" limit="%2$d" title="%3$s"]',
			esc_attr( $layout ),
			$limit,
			esc_attr( $title )
		);
		return do_shortcode( $shortcode );
	}
}
