<?php
/**
 * Plugin Name:       xpay✦ Agentic Commerce for Publishers
 * Plugin URI:        https://www.xpay.sh/publishers/wordpress-plugin/
 * Description:       Contextual product recommendations for content publishers. Renders a recommendation widget via shortcode or Gutenberg block, and publishes an agent-readable product feed at /.well-known/agent-storefront.json so AI assistants can discover and recommend products from your site.
 * Version:           0.4.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            xpay
 * Author URI:        https://www.xpay.sh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xpay-agentic-commerce-for-publishers
 */

defined( 'ABSPATH' ) || exit;

define( 'XPAYACP_VERSION', '0.4.3' );
define( 'XPAYACP_FILE', __FILE__ );
define( 'XPAYACP_PATH', plugin_dir_path( __FILE__ ) );
define( 'XPAYACP_URL', plugin_dir_url( __FILE__ ) );
// publisher-api.xpay.sh is the dedicated subdomain for the publisher-
// storefront Lambda. api.xpay.sh stays the cross-product umbrella.
// Publishers can override via wp-config.php for self-hosted backends.
define( 'XPAYACP_API_BASE', defined( 'XPAYACP_API_BASE_OVERRIDE' ) ? XPAYACP_API_BASE_OVERRIDE : 'https://publisher-api.xpay.sh' );
define( 'XPAYACP_CONNECT_URL', defined( 'XPAYACP_CONNECT_URL_OVERRIDE' ) ? XPAYACP_CONNECT_URL_OVERRIDE : 'https://app.xpay.sh/onboard/publisher' );
define( 'XPAYACP_DASHBOARD_URL', defined( 'XPAYACP_DASHBOARD_URL_OVERRIDE' ) ? XPAYACP_DASHBOARD_URL_OVERRIDE : 'https://app.xpay.sh/dashboard/earn/affiliate/overview' );
// widget.xpay.sh hosts the iframe-embedded UI surfaces (settings, recs).
// Overridable in wp-config.php for local dev pointing at localhost:8087.
define( 'XPAYACP_EMBED_BASE', defined( 'XPAYACP_EMBED_BASE_OVERRIDE' ) ? XPAYACP_EMBED_BASE_OVERRIDE : 'https://widget.xpay.sh' );

require_once XPAYACP_PATH . 'includes/class-xpayacp-plugin.php';

register_activation_hook( __FILE__, array( 'XPAYACP_Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'XPAYACP_Plugin', 'on_deactivate' ) );

add_action( 'plugins_loaded', array( 'XPAYACP_Plugin', 'instance' ), 10 );

// WordPress >=4.6 auto-loads translations for plugins hosted on
// WordPress.org based on the Text Domain header; no manual
// load_plugin_textdomain() call needed. See WP_PluginCheck guideline
// "load_plugin_textdomainFound".
