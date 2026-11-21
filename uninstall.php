<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up all plugin options, user meta, and OAuth state transients.
 *
 * @package YDGL
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// 1. Delete all plugin options.
$ydgl_options = array(
	'ydgl_enabled',
	'ydgl_client_id',
	'ydgl_client_secret',
	'ydgl_enable_registration',
	'ydgl_verify_account_link',
	'ydgl_block_admin_login',
	'ydgl_domain_restriction',
	'ydgl_role_mapping',
	'ydgl_avatar_sync',
	'ydgl_button_theme',
	'ydgl_button_text',
	'ydgl_checkout_button_text',
	'ydgl_checkout_promo_enabled',
	'ydgl_checkout_promo_digital_only',
	'ydgl_checkout_promo_title',
	'ydgl_checkout_promo_desc',
	'ydgl_login_redirect_type',
	'ydgl_login_redirect_url',
	'ydgl_enable_one_tap',
	'ydgl_one_tap_auto_select',
);

foreach ( $ydgl_options as $ydgl_option ) {
	delete_option( $ydgl_option );
}

// 2. Delete all user meta created by this plugin.
$ydgl_user_meta_keys = array(
	'_ydgl_google_id',
	'_ydgl_google_avatar',
	'_ydgl_pending_link_token',
	'_ydgl_pending_google_id',
	'_ydgl_pending_link_expiry',
	'_ydgl_pending_link_redirect',
);

foreach ( $ydgl_user_meta_keys as $ydgl_meta_key ) {
	delete_metadata( 'user', 0, $ydgl_meta_key, '', true );
}

// 3. Delete any leftover OAuth state transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during plugin uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ydgl_state_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ydgl_state_' ) . '%'
	)
);
