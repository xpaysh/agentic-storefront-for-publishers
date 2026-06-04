<?php
/**
 * Uninstall handler — removes all plugin options + transients on plugin delete.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'asp_site_id',
	'asp_site_token',
	'asp_oauth_state',
	'asp_settings',
	'asp_consent_personalization',
	'asp_first_activated_at',
	'asp_installed_version',
	'asp_flush_rewrites',
	'asp_emit_agent_storefront',
	'asp_emit_llms_augment',
	'asp_exclude_categories',
	'asp_exclude_domains',
);

foreach ( $options as $opt ) {
	delete_option( $opt );
	delete_site_option( $opt );
}

delete_transient( 'asp_post_activation_redirect' );
delete_transient( 'asp_emitter_probe' );
delete_transient( 'asp_health' );
