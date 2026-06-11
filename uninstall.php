<?php
/**
 * Uninstall handler — removes all plugin options + transients on plugin delete.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$xpayacp_options = array(
	'xpayacp_site_id',
	'xpayacp_site_token',
	'xpayacp_oauth_state',
	'xpayacp_settings',
	'xpayacp_consent_personalization',
	'xpayacp_first_activated_at',
	'xpayacp_installed_version',
	'xpayacp_flush_rewrites',
	'xpayacp_emit_agent_storefront',
	'xpayacp_emit_llms_augment',
	'xpayacp_exclude_categories',
	'xpayacp_exclude_domains',
	'xpayacp_amazon_tag',
	'xpayacp_enable_widget',
	'xpayacp_include_patterns',
	'xpayacp_exclude_patterns',
);

foreach ( $xpayacp_options as $xpayacp_opt ) {
	delete_option( $xpayacp_opt );
	delete_site_option( $xpayacp_opt );
}

delete_transient( 'xpayacp_post_activation_redirect' );
delete_transient( 'xpayacp_emitter_probe' );
delete_transient( 'xpayacp_health' );
