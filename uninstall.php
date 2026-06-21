<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete options
delete_option( 'adp_settings' );
delete_option( 'adp_license_key' );
delete_option( 'adp_license_status' );
delete_option( 'adp_license_domain' );
delete_option( 'adp_license_expires_at' );

// Delete transients
delete_transient( 'adp_license_valid' );
delete_transient( 'adp_license_attempts' );

// Remove capability
$role = get_role( 'administrator' );
if ( $role ) {
    $role->remove_cap( 'manage_adp' );
}

// Delete all _adp_ post meta
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_adp_override', '_adp_custom_text', '_adp_position')" );

// Clean up cron
wp_clear_scheduled_hook( 'adp_validate_license_cron' );
