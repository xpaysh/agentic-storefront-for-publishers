<?php
/**
 * Plugin Name:       Agentic Storefront for Publishers
 * Plugin URI:        https://www.xpay.sh/publishers/
 * Description:       Contextual product recommendations for content publishers. Embeds a lightweight, privacy-first widget that surfaces relevant products on your posts, and publishes an agent-readable storefront endpoint so AI assistants can discover and recommend products from your site.
 * Version:           0.3.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            xpay
 * Author URI:        https://www.xpay.sh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentic-storefront-for-publishers
 */

defined( 'ABSPATH' ) || exit;

define( 'ASP_VERSION', '0.3.3' );
define( 'ASP_FILE', __FILE__ );
define( 'ASP_PATH', plugin_dir_path( __FILE__ ) );
define( 'ASP_URL', plugin_dir_url( __FILE__ ) );
// publisher-api.xpay.sh is the dedicated subdomain for the publisher-
// storefront Lambda. api.xpay.sh stays the cross-product umbrella.
// Publishers can override via wp-config.php for self-hosted backends.
define( 'ASP_API_BASE', defined( 'ASP_API_BASE_OVERRIDE' ) ? ASP_API_BASE_OVERRIDE : 'https://publisher-api.xpay.sh' );
define( 'ASP_CONNECT_URL', defined( 'ASP_CONNECT_URL_OVERRIDE' ) ? ASP_CONNECT_URL_OVERRIDE : 'https://app.xpay.sh/onboard/publisher' );
define( 'ASP_DASHBOARD_URL', defined( 'ASP_DASHBOARD_URL_OVERRIDE' ) ? ASP_DASHBOARD_URL_OVERRIDE : 'https://app.xpay.sh/dashboard/earn/affiliate/overview' );
// widget.xpay.sh hosts the iframe-embedded UI surfaces (settings, recs).
// Overridable in wp-config.php for local dev pointing at localhost:8087.
define( 'ASP_EMBED_BASE', defined( 'ASP_EMBED_BASE_OVERRIDE' ) ? ASP_EMBED_BASE_OVERRIDE : 'https://widget.xpay.sh' );

require_once ASP_PATH . 'includes/class-asp-plugin.php';

register_activation_hook( __FILE__, array( 'ASP_Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'ASP_Plugin', 'on_deactivate' ) );

add_action( 'plugins_loaded', array( 'ASP_Plugin', 'instance' ), 10 );

// WordPress >=4.6 auto-loads translations for plugins hosted on
// WordPress.org based on the Text Domain header; no manual
// load_plugin_textdomain() call needed. See WP_PluginCheck guideline
// "load_plugin_textdomainFound".
