<?php
/**
 * Admin Settings screen for v0.3.0 thin-shell architecture.
 *
 * No PHP-rendered form. We register the storage hooks (Settings API),
 * keep the one-shot connect-return handshake, and render the admin page
 * as a single transparent <iframe> pointing at
 *   widget.xpay.sh/embed/admin/settings
 *
 * A tiny inline bridge listens for postMessage from that iframe and
 * proxies SAVE intents to the WP REST endpoint
 *   /wp-json/asp/v1/settings
 * which is the SAME endpoint app.xpay.sh writes to via the publisher's
 * Privy session — both surfaces converge on the same backend row.
 *
 * Sanitisation happens in three places: at the WP REST endpoint
 * (sanitize_text_field + the validator below), at register_setting()
 * sanitize_callback (defensive — covers any other write path), and
 * inside the MUI form itself (regex on the Amazon tag). Defence in
 * depth.
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
	 * Captures `?asp_site_id=…&asp_connected=1` after returning from
	 * app.xpay.sh/onboard/publisher. Idempotent on refresh.
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
		// Defensive — these run on any write path (Options.php form, REST
		// endpoint, programmatic update_option). Settings API just wires
		// the sanitiser closer to the wp_options table.
		register_setting( self::OPT_GROUP, 'asp_auto_inject', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true,
		) );
		register_setting( self::OPT_GROUP, 'asp_consent_personalization', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false,
		) );
		register_setting( self::OPT_GROUP, 'asp_emit_agent_storefront', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true,
		) );
		register_setting( self::OPT_GROUP, 'asp_emit_llms_augment', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false,
		) );
		register_setting( self::OPT_GROUP, 'asp_amazon_tag', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_amazon_tag' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'asp_exclude_categories', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_csv' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'asp_exclude_domains', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_csv' ), 'default' => '',
		) );
	}

	public function sanitize_csv( $val ) {
		$parts = array_map( 'trim', explode( ',', (string) $val ) );
		$parts = array_filter( $parts, 'strlen' );
		$parts = array_map( 'sanitize_text_field', $parts );
		return implode( ', ', $parts );
	}

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
		// The postMessage bridge between the embedded xpay control-panel
		// iframe and the plugin's REST endpoints. Lives in its own file
		// so Plugin Check / caching plugins / security scanners can see
		// it on the public scripts list. Configuration values are
		// printed alongside via wp_add_inline_script — see render_page().
		wp_enqueue_script(
			'asp-settings-bridge',
			ASP_URL . 'assets/js/asp-settings-bridge.js',
			array(),
			ASP_VERSION,
			true
		);
	}

	/**
	 * Renders the WP Admin page. Just the page chrome + iframe + a tiny
	 * inline bridge listening for postMessage SAVE intents and proxying
	 * them to the WP REST endpoint with a fresh nonce.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected = ASP_Plugin::is_connected();
		$site_id   = ASP_Plugin::site_id();

		// Pre-fill iframe query params — values still flow through INIT
		// postMessage from the bridge, but these are handy for the
		// standalone-fallback path inside the embed page.
		$src_args = array(
			'site_id'        => $site_id,
			'connected'      => $connected ? '1' : '0',
			'site_host'      => wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
			'plugin_version' => ASP_VERSION,
		);
		$iframe_src = add_query_arg( $src_args, ASP_EMBED_BASE . '/embed/admin/settings' );

		$nonce        = wp_create_nonce( 'wp_rest' );
		$rest_url     = esc_url_raw( rest_url( 'asp/v1/settings' ) );
		$rest_disconn = esc_url_raw( rest_url( 'asp/v1/disconnect' ) );

		// Snapshot the current options so the bridge can ship them as INIT
		// payload to the iframe immediately on READY.
		$settings = array(
			'amazon_tag'              => (string) get_option( 'asp_amazon_tag', '' ),
			'exclude_categories'      => $this->csv_to_array( (string) get_option( 'asp_exclude_categories', '' ) ),
			'exclude_domains'         => $this->csv_to_array( (string) get_option( 'asp_exclude_domains', '' ) ),
			'auto_inject'             => (bool) get_option( 'asp_auto_inject', true ),
			'emit_agent_storefront'   => (bool) get_option( 'asp_emit_agent_storefront', true ),
			'emit_llms_augment'       => (bool) get_option( 'asp_emit_llms_augment', false ),
			'consent_personalization' => (bool) get_option( 'asp_consent_personalization', false ),
		);

		$site_payload = array(
			'connected'     => $connected,
			'site_id'       => $site_id,
			'site_host'     => wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
			'site_category' => '',
			'themes'        => array(),
		);

		$connect_url = esc_url(
			add_query_arg(
				array(
					'site_url' => rawurlencode( home_url( '/' ) ),
					'return'   => rawurlencode( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				),
				ASP_CONNECT_URL
			)
		);

		// Prime the bridge script with the per-request configuration it
		// needs: REST URLs, the WP nonce, the embed origin (for tight
		// postMessage targeting), and the initial state payload to ship
		// to the embedded control panel.
		$bridge_config = array(
			'iframeId'       => 'asp-settings-iframe',
			'restSave'       => $rest_url,
			'restDisconnect' => $rest_disconn,
			'nonce'          => $nonce,
			'embedOrigin'    => esc_url_raw( ASP_EMBED_BASE ),
			'initPayload'    => array(
				'site'          => $site_payload,
				'settings'      => $settings,
				'dashboardUrl'  => ASP_DASHBOARD_URL,
				'connectUrl'    => $connect_url,
				'pluginVersion' => ASP_VERSION,
			),
		);
		wp_add_inline_script(
			'asp-settings-bridge',
			'window.ASP_SETTINGS_BRIDGE_CONFIG = ' . wp_json_encode( $bridge_config ) . ';',
			'before'
		);

		?>
		<div class="wrap asp-admin">
			<h1><?php echo esc_html__( 'Agentic Storefront for Publishers', 'agentic-storefront-for-publishers' ); ?></h1>

			<?php if ( ! $connected ) : ?>
				<div class="asp-card">
					<p><?php echo esc_html__( 'Connect this site to xpay to start receiving contextual product recommendations.', 'agentic-storefront-for-publishers' ); ?></p>
					<p><a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary"><?php echo esc_html__( 'Connect site', 'agentic-storefront-for-publishers' ); ?></a></p>
				</div>
			<?php endif; ?>

			<div class="notice notice-info" style="margin:18px 0; padding:12px 14px;">
				<p style="margin:0 0 6px; font-weight:600;">
					<?php echo esc_html__( 'Your xpay account control panel', 'agentic-storefront-for-publishers' ); ?>
				</p>
				<p style="margin:0; color:#555;">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: control-panel host 2: backend host 3: dashboard host */
							__( 'The panel below is your xpay account configuration screen, embedded here from %1$s as a sandboxed iframe for convenience — the same screen you can also reach by signing in to your xpay account directly. When connected, the plugin sends each rendered page\'s public URL, title, categories and tags to %2$s so xpay can pick relevant products. No visitor identifiers are included. The full xpay publisher dashboard at %3$s is linked-to only and never embedded.', 'agentic-storefront-for-publishers' ),
							'<code>widget.xpay.sh</code>',
							'<code>publisher-api.xpay.sh</code>',
							'<code>app.xpay.sh</code>'
						),
						array( 'code' => array() )
					);
					?>
					<a href="https://docs.xpay.sh/en/publishers/wordpress-plugin/privacy" target="_blank" rel="noopener">
						<?php echo esc_html__( 'Full data-flow disclosure →', 'agentic-storefront-for-publishers' ); ?>
					</a>
				</p>
			</div>

			<iframe
				id="asp-settings-iframe"
				src="<?php echo esc_url( $iframe_src ); ?>"
				title="<?php echo esc_attr__( 'xpay account control panel (embedded from widget.xpay.sh)', 'agentic-storefront-for-publishers' ); ?>"
				sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"
				referrerpolicy="no-referrer"
				style="width:100%; min-height:760px; border:0; background:transparent;"
				allowtransparency="true"
			></iframe>

			<p class="asp-intro" style="margin:14px 0 0; color:#6b7280; font-size:12px;">
				<?php
				printf(
					/* translators: %s: link to the plain-English overview page */
					esc_html__( 'Plain-English overview of what this plugin does, how revenue works, and Mediavine/Ezoic compatibility: %s', 'agentic-storefront-for-publishers' ),
					'<a href="https://www.xpay.sh/publishers/wordpress-plugin" target="_blank" rel="noopener">xpay.sh/publishers/wordpress-plugin →</a>'
				);
				?>
			</p>
		</div>

		<?php
	}

	private function csv_to_array( $csv ) {
		$parts = array_map( 'trim', explode( ',', (string) $csv ) );
		$parts = array_filter( $parts, 'strlen' );
		return array_values( $parts );
	}
}
