<?php
/**
 * Plugin Name: Affiliate Disclosure Pro
 * Description: Automatic affiliate disclosure management with per-article overrides, multiple styles, and FTC compliance.
 * Version:     1.0.0
 * Author:      Abderrahim KHALID
 * Text Domain: affiliate-disclosure-pro
 * Network:     true
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADP_VERSION', '1.0.0' );
define( 'ADP_FILE', __FILE__ );
define( 'ADP_BASENAME', plugin_basename( __FILE__ ) );
define( 'ADP_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADP_URL',  plugin_dir_url( __FILE__ ) );
define( 'ADP_CAPABILITY', 'manage_adp' );
define( 'ADP_API_URL', 'https://dp-starter.khalid.digital' );

// License system FIRST
require_once ADP_PATH . 'inc/license.php';

// Settings page (always loaded — includes license tab)
require_once ADP_PATH . 'admin/class-adp-settings.php';
new ADP_Settings();

// Only load the rest if licensed
if ( adp_is_licensed() ) {
    // Metabox is local (admin-only, saves meta fields)
    if ( is_admin() ) {
        require_once ADP_PATH . 'admin/class-adp-metabox.php';
        new ADP_Metabox();
    }

    // Display + CSS are premium (served from Worker)
    adp_load_premium_code();
}

function adp_add_caps_for_blog() {
    $role = get_role( 'administrator' );
    if ( ! $role ) return;
    $role->add_cap( ADP_CAPABILITY );
}

function adp_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            adp_add_caps_for_blog();
            restore_current_blog();
        }
    } else {
        adp_add_caps_for_blog();
    }

    // Initialize settings defaults if not set
    if ( function_exists( 'adp_settings_defaults' ) ) {
        $defaults = adp_settings_defaults();
        $current = get_option( 'adp_settings', [] );
        if ( empty( $current ) ) {
            update_option( 'adp_settings', $defaults );
        }
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'adp_activate' );

function adp_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'adp_deactivate' );

function adp_add_caps_on_new_blog( $blog_id ) {
    if ( ! is_multisite() ) return;
    switch_to_blog( $blog_id );
    adp_add_caps_for_blog();
    restore_current_blog();
}
add_action( 'wpmu_new_blog', 'adp_add_caps_on_new_blog' );

function adp_maybe_add_caps() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( ADP_CAPABILITY ) ) {
        $role->add_cap( ADP_CAPABILITY );
    }
}
add_action( 'admin_init', 'adp_maybe_add_caps' );
