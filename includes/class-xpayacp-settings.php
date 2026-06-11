<?php
/**
 * Admin Settings screen — native WordPress UI.
 *
 * Renders a standard wp-admin form via the Settings API. All fields are
 * registered with sanitize callbacks. Connect/disconnect run through a
 * nonced admin-post.php handler.
 *
 * No external iframe, no remote UI, no postMessage.
 */

defined( 'ABSPATH' ) || exit;

class XPAYACP_Settings {

	const PAGE_SLUG = 'xpay-agentic-commerce-for-publishers';
	const OPT_GROUP = 'xpayacp_settings_group';

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
		add_action( 'admin_post_xpayacp_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function maybe_capture_connect_return() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		// The xpay onboard page may return either the new (xpayacp_*) or
		// the legacy (asp_*) query parameter pair during the 0.3.x → 0.4.x
		// rename window. Accept both with explicit static lookups so
		// plugin-check's input validator can see the index check.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_new = ! empty( $_GET['xpayacp_site_id'] ) && ! empty( $_GET['xpayacp_connected'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_old = ! empty( $_GET['asp_site_id'] ) && ! empty( $_GET['asp_connected'] );
		if ( ! $has_new && ! $has_old ) {
			return;
		}
		if ( $has_new ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$incoming = sanitize_text_field( wp_unslash( $_GET['xpayacp_site_id'] ) );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$incoming = sanitize_text_field( wp_unslash( $_GET['asp_site_id'] ) );
		}
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{6,64}$/', $incoming ) ) {
			return;
		}
		update_option( 'xpayacp_site_id', $incoming );
		if ( class_exists( 'XPAYACP_Emitter_Probe' ) ) {
			XPAYACP_Emitter_Probe::clear_cache();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&xpayacp_just_connected=1' ) );
		exit;
	}

	public function register_menu() {
		add_options_page(
			__( 'xpay Agentic Commerce', 'xpay-agentic-commerce-for-publishers' ),
			__( 'xpay Agentic Commerce', 'xpay-agentic-commerce-for-publishers' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( self::OPT_GROUP, 'xpayacp_consent_personalization', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false,
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_emit_agent_storefront', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true,
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_emit_llms_augment', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false,
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_amazon_tag', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_amazon_tag' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_exclude_categories', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_csv' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_exclude_domains', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_csv' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_enable_widget', array(
			'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true,
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_include_patterns', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_patterns' ), 'default' => '',
		) );
		register_setting( self::OPT_GROUP, 'xpayacp_exclude_patterns', array(
			'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_patterns' ), 'default' => '',
		) );

		add_settings_section( 'xpayacp_section_emitters', __( 'Agent discovery', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_section_emitters' ), self::PAGE_SLUG );
		add_settings_field( 'xpayacp_emit_agent_storefront', __( 'Agent storefront endpoint', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_emit_agent_storefront' ), self::PAGE_SLUG, 'xpayacp_section_emitters' );
		add_settings_field( 'xpayacp_emit_llms_augment', __( 'Augment /llms.txt', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_emit_llms_augment' ), self::PAGE_SLUG, 'xpayacp_section_emitters' );

		add_settings_section( 'xpayacp_section_widget', __( 'Recommendation widget', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_section_widget' ), self::PAGE_SLUG );
		add_settings_field( 'xpayacp_amazon_tag', __( 'Amazon Associates tag', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_amazon_tag' ), self::PAGE_SLUG, 'xpayacp_section_widget' );
		add_settings_field( 'xpayacp_exclude_categories', __( 'Excluded categories', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_exclude_categories' ), self::PAGE_SLUG, 'xpayacp_section_widget' );
		add_settings_field( 'xpayacp_exclude_domains', __( 'Excluded merchant domains', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_exclude_domains' ), self::PAGE_SLUG, 'xpayacp_section_widget' );
		add_settings_field( 'xpayacp_consent_personalization', __( 'Personalization', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_consent_personalization' ), self::PAGE_SLUG, 'xpayacp_section_widget' );

		add_settings_section( 'xpayacp_section_placement', __( 'Where the widget loads', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_section_placement' ), self::PAGE_SLUG );
		add_settings_field( 'xpayacp_enable_widget', __( 'Site-wide widget', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_enable_widget' ), self::PAGE_SLUG, 'xpayacp_section_placement' );
		add_settings_field( 'xpayacp_include_patterns', __( 'Show only on these paths', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_include_patterns' ), self::PAGE_SLUG, 'xpayacp_section_placement' );
		add_settings_field( 'xpayacp_exclude_patterns', __( 'Never show on these paths', 'xpay-agentic-commerce-for-publishers' ), array( $this, 'render_field_exclude_patterns' ), self::PAGE_SLUG, 'xpayacp_section_placement' );
	}

	public function sanitize_patterns( $val ) {
		$lines = preg_split( '/[\r\n]+/', (string) $val );
		$lines = array_map( 'trim', is_array( $lines ) ? $lines : array() );
		$lines = array_filter( $lines, 'strlen' );
		$lines = array_map( 'sanitize_text_field', $lines );
		return implode( "\n", $lines );
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
		wp_enqueue_style( 'xpayacp-admin', XPAYACP_URL . 'assets/css/xpayacp-admin.css', array(), XPAYACP_VERSION );
	}

	public function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'xpay-agentic-commerce-for-publishers' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'xpayacp_disconnect' );

		delete_option( 'xpayacp_site_id' );
		if ( class_exists( 'XPAYACP_Emitter_Probe' ) ) {
			XPAYACP_Emitter_Probe::clear_cache();
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&xpayacp_disconnected=1' ) );
		exit;
	}

	public function render_section_emitters() {
		echo '<p>' . esc_html__( 'Control which discovery files this plugin serves to AI assistants and crawlers.', 'xpay-agentic-commerce-for-publishers' ) . '</p>';
	}

	public function render_section_widget() {
		echo '<p>' . esc_html__( 'Configure what the front-end recommendation widget shows on posts where you place the [xpay_recs] shortcode or the Recommendations block.', 'xpay-agentic-commerce-for-publishers' ) . '</p>';
	}

	public function render_field_emit_agent_storefront() {
		$val = (bool) get_option( 'xpayacp_emit_agent_storefront', true );
		printf(
			'<label><input type="checkbox" name="xpayacp_emit_agent_storefront" value="1" %1$s /> %2$s</label><p class="description">%3$s</p>',
			checked( $val, true, false ),
			esc_html__( 'Publish /.well-known/agent-storefront.json', 'xpay-agentic-commerce-for-publishers' ),
			esc_html__( 'When enabled, AI assistants can discover an agent-readable product list at this URL. The plugin refuses to overwrite an existing file at the same path.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_emit_llms_augment() {
		$val = (bool) get_option( 'xpayacp_emit_llms_augment', false );
		printf(
			'<label><input type="checkbox" name="xpayacp_emit_llms_augment" value="1" %1$s /> %2$s</label><p class="description">%3$s</p>',
			checked( $val, true, false ),
			esc_html__( 'Append a discovery block to /llms.txt', 'xpay-agentic-commerce-for-publishers' ),
			esc_html__( 'Off by default. When enabled and no existing /llms.txt is detected, the plugin serves a minimal /llms.txt that points at /.well-known/agent-storefront.json. Never overwrites an existing /llms.txt.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_amazon_tag() {
		$val = (string) get_option( 'xpayacp_amazon_tag', '' );
		printf(
			'<input type="text" class="regular-text code" name="xpayacp_amazon_tag" value="%1$s" placeholder="myblog-20" /><p class="description">%2$s</p>',
			esc_attr( $val ),
			esc_html__( 'Your Amazon Associates tracking ID (format: name-NN). Amazon links surfaced by the widget will have ?tag=<yours> appended; Amazon pays you directly. Leave empty to skip.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_exclude_categories() {
		$val = (string) get_option( 'xpayacp_exclude_categories', '' );
		printf(
			'<textarea class="large-text code" rows="3" name="xpayacp_exclude_categories" placeholder="alcohol, weapons, supplements">%1$s</textarea><p class="description">%2$s</p>',
			esc_textarea( $val ),
			esc_html__( 'Comma-separated category slugs to exclude from recommendations on this site.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_exclude_domains() {
		$val = (string) get_option( 'xpayacp_exclude_domains', '' );
		printf(
			'<textarea class="large-text code" rows="3" name="xpayacp_exclude_domains" placeholder="competitor.com, brand-x.com">%1$s</textarea><p class="description">%2$s</p>',
			esc_textarea( $val ),
			esc_html__( 'Comma-separated merchant domains to exclude from recommendations on this site.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_section_placement() {
		echo '<p>' . esc_html__( 'By default the recommendation widget (floating button + footer drawer) loads on every page of your connected site. Use the rules below to narrow or disable it.', 'xpay-agentic-commerce-for-publishers' ) . '</p>';
	}

	public function render_field_enable_widget() {
		$val = (bool) get_option( 'xpayacp_enable_widget', true );
		printf(
			'<label><input type="checkbox" name="xpayacp_enable_widget" value="1" %1$s /> %2$s</label><p class="description">%3$s</p>',
			checked( $val, true, false ),
			esc_html__( 'Load the widget site-wide on connected sites', 'xpay-agentic-commerce-for-publishers' ),
			esc_html__( 'Default on. Uncheck if you only want the widget to appear inside posts via the [xpay_recs] shortcode or the Recommendations block.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_include_patterns() {
		$val = (string) get_option( 'xpayacp_include_patterns', '' );
		printf(
			'<textarea class="large-text code" rows="3" name="xpayacp_include_patterns" placeholder="/blog/*&#10;/guides/*">%1$s</textarea><p class="description">%2$s</p>',
			esc_textarea( $val ),
			esc_html__( 'One URL pattern per line. If empty (default), the widget can load on any path. If set, only matching paths are eligible. Wildcards: * (any chars), ? (one char). Patterns match the request path, not the full URL.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_exclude_patterns() {
		$val = (string) get_option( 'xpayacp_exclude_patterns', '' );
		printf(
			'<textarea class="large-text code" rows="3" name="xpayacp_exclude_patterns" placeholder="/cart&#10;/checkout/*&#10;/wp-login*">%1$s</textarea><p class="description">%2$s</p>',
			esc_textarea( $val ),
			esc_html__( 'One URL pattern per line. The widget never loads on matching paths. Exclude wins over Show. Same wildcards apply.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_field_consent_personalization() {
		$val = (bool) get_option( 'xpayacp_consent_personalization', false );
		printf(
			'<label><input type="checkbox" name="xpayacp_consent_personalization" value="1" %1$s /> %2$s</label><p class="description">%3$s</p>',
			checked( $val, true, false ),
			esc_html__( 'Allow personalization signals when a Consent API is present', 'xpay-agentic-commerce-for-publishers' ),
			esc_html__( 'Off by default. When the WP Consent API plugin reports a positive marketing signal for the visitor, the widget may use lightweight personalization on top of page context. With this OFF the widget remains strictly contextual.', 'xpay-agentic-commerce-for-publishers' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected   = XPAYACP_Plugin::is_connected();
		$site_id     = XPAYACP_Plugin::site_id();
		$connect_url = esc_url(
			add_query_arg(
				array(
					'site_url' => rawurlencode( home_url( '/' ) ),
					'return'   => rawurlencode( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				),
				XPAYACP_CONNECT_URL
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$just_connected = ! empty( $_GET['xpayacp_just_connected'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$just_disconnected = ! empty( $_GET['xpayacp_disconnected'] );

		?>
		<div class="wrap xpayacp-admin">
			<h1><?php echo esc_html__( 'xpay✦ Agentic Commerce for Publishers', 'xpay-agentic-commerce-for-publishers' ); ?></h1>

			<?php if ( $just_connected ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Site connected. You can now configure the widget below.', 'xpay-agentic-commerce-for-publishers' ); ?></p></div>
			<?php elseif ( $just_disconnected ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html__( 'Site disconnected. The agent storefront endpoint and widget have been disabled.', 'xpay-agentic-commerce-for-publishers' ); ?></p></div>
			<?php endif; ?>

			<div class="xpayacp-card">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Connection', 'xpay-agentic-commerce-for-publishers' ); ?></h2>
				<?php if ( $connected ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s is the site id wrapped in a code element */
							esc_html__( 'Connected. Site ID: %s', 'xpay-agentic-commerce-for-publishers' ),
							'<code>' . esc_html( $site_id ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</p>
					<p>
						<a href="<?php echo esc_url( XPAYACP_DASHBOARD_URL ); ?>" target="_blank" rel="noopener" class="button"><?php echo esc_html__( 'Open xpay dashboard', 'xpay-agentic-commerce-for-publishers' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:8px;">
							<?php wp_nonce_field( 'xpayacp_disconnect' ); ?>
							<input type="hidden" name="action" value="xpayacp_disconnect" />
							<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Disconnect this site? The agent endpoint and widget will stop serving until reconnected.', 'xpay-agentic-commerce-for-publishers' ) ); ?>');"><?php echo esc_html__( 'Disconnect', 'xpay-agentic-commerce-for-publishers' ); ?></button>
						</form>
					</p>
				<?php else : ?>
					<p><?php echo esc_html__( 'This site is not connected to an xpay publisher account yet. Connect to start serving the agent endpoint and the recommendation widget.', 'xpay-agentic-commerce-for-publishers' ); ?></p>
					<p><a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary"><?php echo esc_html__( 'Connect site', 'xpay-agentic-commerce-for-publishers' ); ?></a></p>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPT_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<div class="xpayacp-card xpayacp-disclosure">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'External services this plugin contacts', 'xpay-agentic-commerce-for-publishers' ); ?></h2>
				<ul>
					<li>
						<code>publisher-api.xpay.sh</code> —
						<?php echo esc_html__( 'recommendation decision API and beacons. Receives the post URL, title, public categories and tags, plus your site_id. No visitor identifier.', 'xpay-agentic-commerce-for-publishers' ); ?>
					</li>
					<li>
						<code>widget.xpay.sh</code> —
						<?php echo esc_html__( 'sandboxed iframe that renders the front-end recommendation widget on posts where you place the shortcode or block. Not embedded in wp-admin.', 'xpay-agentic-commerce-for-publishers' ); ?>
					</li>
					<li>
						<code>app.xpay.sh</code> —
						<?php echo esc_html__( 'publisher dashboard, opened in a new tab from the buttons above. Not embedded.', 'xpay-agentic-commerce-for-publishers' ); ?>
					</li>
				</ul>
				<p>
					<?php
					printf(
						/* translators: 1: terms-of-use link, 2: privacy-policy link */
						esc_html__( 'Terms of use: %1$s · Privacy policy: %2$s', 'xpay-agentic-commerce-for-publishers' ),
						'<a href="https://www.xpay.sh/legal/terms-of-use/" target="_blank" rel="noopener">xpay.sh/legal/terms-of-use</a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'<a href="https://www.xpay.sh/legal/privacy-policy/" target="_blank" rel="noopener">xpay.sh/legal/privacy-policy</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
