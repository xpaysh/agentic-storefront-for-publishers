<?php
/**
 * Admin settings screen. Uses the Settings API for storage and field rendering
 * so all input is sanitised by registered callbacks and form-submission CSRF
 * is handled by WordPress.
 */

defined( 'ABSPATH' ) || exit;

class ASP_Settings {

	const PAGE_SLUG  = 'agentic-storefront-for-publishers';
	const OPT_GROUP  = 'asp_settings_group';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_capture_connect_return' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Captures `?asp_site_id=…&asp_connected=1` on the settings page after the
	 * publisher returns from app.xpay.sh/onboard/publisher. Persists the site_id
	 * and strips the query params with a redirect so the URL stays clean and
	 * the connect handshake is idempotent on refresh.
	 */
	public function maybe_capture_connect_return() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['asp_site_id'] ) || empty( $_GET['asp_connected'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$incoming = sanitize_text_field( wp_unslash( $_GET['asp_site_id'] ) );
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{6,64}$/', $incoming ) ) {
			return;
		}
		update_option( 'asp_site_id', $incoming );
		if ( class_exists( 'ASP_Emitter_Probe' ) ) {
			ASP_Emitter_Probe::clear_cache();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&asp_just_connected=1' ) );
		exit;
	}

	public function register_menu() {
		add_options_page(
			__( 'Agentic Storefront', 'agentic-storefront-for-publishers' ),
			__( 'Agentic Storefront', 'agentic-storefront-for-publishers' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::OPT_GROUP,
			'asp_consent_personalization',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
		register_setting(
			self::OPT_GROUP,
			'asp_emit_agent_storefront',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			self::OPT_GROUP,
			'asp_emit_llms_augment',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
		register_setting(
			self::OPT_GROUP,
			'asp_amazon_tag',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_amazon_tag' ),
				'default'           => '',
			)
		);
		register_setting(
			self::OPT_GROUP,
			'asp_exclude_categories',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_csv' ),
				'default'           => '',
			)
		);
		register_setting(
			self::OPT_GROUP,
			'asp_exclude_domains',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_csv' ),
				'default'           => '',
			)
		);
	}

	public function sanitize_csv( $val ) {
		$parts = array_map( 'trim', explode( ',', (string) $val ) );
		$parts = array_filter( $parts, 'strlen' );
		$parts = array_map( 'sanitize_text_field', $parts );
		return implode( ', ', $parts );
	}

	/**
	 * Amazon Associates tag — accepts the standard "myblog-20" shape.
	 * Returns empty string for anything malformed so we never inject garbage.
	 */
	public function sanitize_amazon_tag( $val ) {
		$tag = strtolower( trim( (string) $val ) );
		if ( '' === $tag ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{1,28}-\d{2,4}$/', $tag ) ) {
			return '';
		}
		return $tag;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'asp-admin',
			ASP_URL . 'assets/css/asp-admin.css',
			array(),
			ASP_VERSION
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected   = ASP_Plugin::is_connected();
		$site_id     = ASP_Plugin::site_id();
		$existing    = ASP_Emitter_Probe::existing_emitters();
		$connect_url = esc_url(
			add_query_arg(
				array(
					'site_url' => rawurlencode( home_url( '/' ) ),
					'return'   => rawurlencode( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				),
				ASP_CONNECT_URL
			)
		);

		?>
		<div class="wrap asp-admin">
			<h1><?php echo esc_html__( 'Agentic Storefront for Publishers', 'agentic-storefront-for-publishers' ); ?></h1>

			<p class="asp-intro" style="margin: 0 0 14px; color: #6b7280; font-size: 13px;">
				<?php
				printf(
					/* translators: %s: link to the landing page */
					esc_html__( 'Plain-English overview of what this plugin does, how revenue works, and Mediavine/Ezoic compatibility: %s', 'agentic-storefront-for-publishers' ),
					'<a href="https://www.xpay.sh/publishers/wordpress-plugin" target="_blank" rel="noopener">xpay.sh/publishers/wordpress-plugin →</a>'
				);
				?>
			</p>

			<div class="asp-card">
				<h2><?php echo esc_html__( 'Connection', 'agentic-storefront-for-publishers' ); ?></h2>
				<?php if ( $connected ) : ?>
					<p>
						<?php
						/* translators: %s: site id */
						printf( esc_html__( 'Connected. Site id: %s', 'agentic-storefront-for-publishers' ), '<code>' . esc_html( $site_id ) . '</code>' );
						?>
					</p>
					<p>
						<a href="<?php echo esc_url( ASP_DASHBOARD_URL . '/sites/' . rawurlencode( $site_id ) ); ?>" class="button button-primary" target="_blank" rel="noopener">
							<?php echo esc_html__( 'Open xpay dashboard ↗', 'agentic-storefront-for-publishers' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'asp-action', 'disconnect', wp_nonce_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ), 'asp-disconnect' ) ) ); ?>" class="button">
							<?php echo esc_html__( 'Disconnect', 'agentic-storefront-for-publishers' ); ?>
						</a>
					</p>
					<p class="description" style="margin-top: 4px;">
						<?php echo esc_html__( 'Manage Amazon tag, see scanned affiliate links, preview what we\'d render, and track click-through revenue.', 'agentic-storefront-for-publishers' ); ?>
					</p>
				<?php else : ?>
					<p><?php echo esc_html__( 'Connect this site to xpay to start receiving contextual product recommendations.', 'agentic-storefront-for-publishers' ); ?></p>
					<p><a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary"><?php echo esc_html__( 'Connect site', 'agentic-storefront-for-publishers' ); ?></a></p>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php" class="asp-card">
				<?php settings_fields( self::OPT_GROUP ); ?>
				<h2><?php echo esc_html__( 'Discovery emitters', 'agentic-storefront-for-publishers' ); ?></h2>

				<p>
					<label>
						<input type="checkbox" name="asp_emit_agent_storefront" value="1" <?php checked( get_option( 'asp_emit_agent_storefront', 1 ) ); ?> />
						<?php echo esc_html__( 'Serve /.well-known/agent-storefront.json (recommended)', 'agentic-storefront-for-publishers' ); ?>
					</label>
					<?php if ( ! empty( $existing['/.well-known/agent-storefront.json'] ) ) : ?>
						<br /><em class="asp-warning"><?php echo esc_html__( 'Detected: another emitter is already serving this path. Our emitter will stay silent.', 'agentic-storefront-for-publishers' ); ?></em>
					<?php endif; ?>
				</p>

				<p>
					<label>
						<input type="checkbox" name="asp_emit_llms_augment" value="1" <?php checked( get_option( 'asp_emit_llms_augment', 0 ) ); ?> />
						<?php echo esc_html__( 'Augment /llms.txt with an append-only block (off by default)', 'agentic-storefront-for-publishers' ); ?>
					</label>
					<?php if ( ! empty( $existing['/llms.txt'] ) ) : ?>
						<br /><em class="asp-warning"><?php echo esc_html__( 'Detected: another emitter or static file is serving /llms.txt. Our emitter will stay silent until you remove the conflict.', 'agentic-storefront-for-publishers' ); ?></em>
					<?php endif; ?>
				</p>

				<h2><?php echo esc_html__( 'Personalization (optional)', 'agentic-storefront-for-publishers' ); ?></h2>

				<p>
					<label>
						<input type="checkbox" name="asp_consent_personalization" value="1" <?php checked( get_option( 'asp_consent_personalization', false ) ); ?> />
						<?php echo esc_html__( 'Enable personalization (requires visitor consent via your consent manager)', 'agentic-storefront-for-publishers' ); ?>
					</label>
				</p>
				<p class="description">
					<?php echo esc_html__( 'With this off, recommendations are based only on the public page (categories, tags, post title). With this on, visitor session context may be used — but only when your consent banner explicitly authorises it.', 'agentic-storefront-for-publishers' ); ?>
				</p>

				<h2><?php echo esc_html__( 'Amazon Associates', 'agentic-storefront-for-publishers' ); ?></h2>

				<p>
					<label for="asp_amazon_tag"><?php echo esc_html__( 'Your Amazon Associates tag (optional)', 'agentic-storefront-for-publishers' ); ?></label><br />
					<input type="text" id="asp_amazon_tag" name="asp_amazon_tag" value="<?php echo esc_attr( get_option( 'asp_amazon_tag', '' ) ); ?>" class="regular-text" placeholder="myblog-20" autocomplete="off" spellcheck="false" />
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to Amazon Associates tracking IDs page */
						esc_html__( 'When set, any Amazon link this plugin surfaces gets your %s appended. Amazon pays you directly — xpay does not take a share. Find or create a tag in Amazon Associates → Account → Manage Your Tracking IDs.', 'agentic-storefront-for-publishers' ),
						'<code>?tag=&lt;your-tag&gt;</code>'
					);
					?>
				</p>

				<h2><?php echo esc_html__( 'Brand safety', 'agentic-storefront-for-publishers' ); ?></h2>

				<p>
					<label for="asp_exclude_categories"><?php echo esc_html__( 'Excluded product categories (comma-separated)', 'agentic-storefront-for-publishers' ); ?></label><br />
					<input type="text" id="asp_exclude_categories" name="asp_exclude_categories" value="<?php echo esc_attr( get_option( 'asp_exclude_categories', '' ) ); ?>" class="regular-text" />
				</p>

				<p>
					<label for="asp_exclude_domains"><?php echo esc_html__( 'Excluded merchant domains (comma-separated)', 'agentic-storefront-for-publishers' ); ?></label><br />
					<input type="text" id="asp_exclude_domains" name="asp_exclude_domains" value="<?php echo esc_attr( get_option( 'asp_exclude_domains', '' ) ); ?>" class="regular-text" />
				</p>

				<?php submit_button(); ?>
			</form>

			<div class="asp-card">
				<h2><?php echo esc_html__( 'How to place recommendations', 'agentic-storefront-for-publishers' ); ?></h2>
				<p>
					<?php echo esc_html__( 'Add the shortcode [xpay_recs] anywhere in a post, or use the "Recommendations" block in the block editor. The widget is intentionally not auto-injected into your content.', 'agentic-storefront-for-publishers' ); ?>
				</p>
			</div>
		</div>
		<?php

		// Handle disconnect action.
		if ( isset( $_GET['asp-action'] ) && 'disconnect' === $_GET['asp-action'] && check_admin_referer( 'asp-disconnect' ) ) {
			delete_option( 'asp_site_id' );
			ASP_Emitter_Probe::clear_cache();
			wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
			exit;
		}
	}
}
