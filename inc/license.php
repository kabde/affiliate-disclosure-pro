<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if plugin is licensed
 */
function adp_is_licensed() {
    // Check cached result first
    static $result = null;
    if ( $result !== null ) return $result;

    $status = get_option( 'adp_license_status', '' );
    if ( $status === 'valid' ) {
        // Check transient for periodic revalidation
        if ( false === get_transient( 'adp_license_valid' ) ) {
            // Schedule revalidation but don't block
            if ( ! wp_next_scheduled( 'adp_validate_license_cron' ) ) {
                wp_schedule_single_event( time() + 10, 'adp_validate_license_cron' );
            }
        }
        $result = true;
        return true;
    }
    $result = false;
    return false;
}

/**
 * Activate license
 */
function adp_activate_license( $key ) {
    $attempts = (int) get_transient( 'adp_license_attempts' );
    if ( $attempts >= 5 ) {
        return [ 'success' => false, 'message' => 'Trop de tentatives. Réessayez dans une minute.' ];
    }
    set_transient( 'adp_license_attempts', $attempts + 1, MINUTE_IN_SECONDS );

    $key = strtoupper( sanitize_text_field( trim( $key ) ) );
    if ( ! preg_match( '/^ADP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key ) ) {
        return [ 'success' => false, 'message' => 'Format de licence invalide.' ];
    }

    $response = wp_remote_post( ADP_API_URL . '/activate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'affiliate-disclosure-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    if ( is_wp_error( $response ) ) {
        error_log( '[ADP] License activation error: ' . $response->get_error_message() );
        return [ 'success' => false, 'message' => 'Erreur de connexion: ' . $response->get_error_message() ];
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['success'] ) ) {
        update_option( 'adp_license_key', $key );
        update_option( 'adp_license_status', 'valid' );
        update_option( 'adp_license_domain', home_url() );
        if ( isset( $body['expires_at'] ) ) {
            update_option( 'adp_license_expires_at', sanitize_text_field( $body['expires_at'] ) );
        }
        set_transient( 'adp_license_valid', 1, 72 * HOUR_IN_SECONDS );
        return [ 'success' => true, 'message' => $body['message'] ?? 'Licence activée.' ];
    }

    return [ 'success' => false, 'message' => $body['message'] ?? 'Activation échouée.' ];
}

/**
 * Deactivate license
 */
function adp_deactivate_license() {
    $key = get_option( 'adp_license_key', '' );
    if ( empty( $key ) ) return;

    wp_remote_post( ADP_API_URL . '/deactivate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'affiliate-disclosure-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    delete_option( 'adp_license_key' );
    delete_option( 'adp_license_status' );
    delete_option( 'adp_license_domain' );
    delete_option( 'adp_license_expires_at' );
    delete_transient( 'adp_license_valid' );
}

/**
 * Validate license (called by cron)
 */
function adp_validate_license() {
    $key = get_option( 'adp_license_key', '' );
    if ( empty( $key ) ) return;

    $response = wp_remote_post( ADP_API_URL . '/validate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'affiliate-disclosure-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    if ( is_wp_error( $response ) ) {
        error_log( '[ADP] License validation error: ' . $response->get_error_message() );
        return;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['valid'] ) ) {
        update_option( 'adp_license_status', 'valid' );
        if ( isset( $body['expires_at'] ) ) {
            update_option( 'adp_license_expires_at', sanitize_text_field( $body['expires_at'] ) );
        }
        set_transient( 'adp_license_valid', 1, 72 * HOUR_IN_SECONDS );
    } else {
        update_option( 'adp_license_status', 'invalid' );
        delete_transient( 'adp_license_valid' );
    }
}
add_action( 'adp_validate_license_cron', 'adp_validate_license' );

// Schedule cron
function adp_schedule_validation() {
    if ( ! wp_next_scheduled( 'adp_validate_license_cron' ) && adp_is_licensed() ) {
        wp_schedule_event( time(), 'twicedaily', 'adp_validate_license_cron' );
    }
}
add_action( 'init', 'adp_schedule_validation' );

// Cleanup cron on deactivation
register_deactivation_hook( ADP_FILE, function() {
    wp_clear_scheduled_hook( 'adp_validate_license_cron' );
    delete_transient( 'adp_license_valid' );
});

/**
 * Auto-update via Worker
 */
function adp_check_plugin_update( $transient ) {
    if ( empty( $transient ) || ! is_object( $transient ) ) return $transient;

    $response = wp_remote_get( ADP_API_URL . '/update-check?product=affiliate-disclosure-pro', [
        'timeout' => 10,
    ]);

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return $transient;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['version'] ) || ! version_compare( ADP_VERSION, $data['version'], '<' ) ) {
        return $transient;
    }

    $transient->response[ ADP_BASENAME ] = (object) [
        'slug'         => 'affiliate-disclosure-pro',
        'plugin'       => ADP_BASENAME,
        'new_version'  => $data['version'],
        'url'          => $data['url'] ?? '',
        'package'      => $data['download_url'] ?? '',
        'tested'       => '7.0',
        'requires'     => '5.0',
        'requires_php' => '7.4',
    ];

    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'adp_check_plugin_update' );

/**
 * Admin notice when not licensed
 */
function adp_admin_notice_no_license() {
    if ( adp_is_licensed() ) return;
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'toplevel_page_adp-settings' ) return;

    echo '<div class="notice notice-warning"><p>';
    echo '<strong>Affiliate Disclosure Pro</strong> — ';
    echo 'Veuillez <a href="' . esc_url( admin_url( 'admin.php?page=adp-settings' ) ) . '">activer votre licence</a> pour utiliser le plugin.';
    echo '</p></div>';
}
add_action( 'admin_notices', 'adp_admin_notice_no_license' );

function adp_admin_notice_expiring() {
    if ( ! adp_is_licensed() ) return;
    $expires = get_option( 'adp_license_expires_at', '' );
    if ( ! $expires ) return;
    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
    if ( $days > 14 ) return;
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'toplevel_page_adp-settings' ) return;

    if ( $days <= 0 ) {
        echo '<div class="notice notice-error"><p><strong>Affiliate Disclosure Pro</strong> — Votre licence a expiré. <a href="' . esc_url( admin_url( 'admin.php?page=adp-settings' ) ) . '">Renouveler</a></p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>Affiliate Disclosure Pro</strong> — Votre licence expire dans ' . $days . ' jour' . ($days > 1 ? 's' : '') . '. <a href="' . esc_url( admin_url( 'admin.php?page=adp-settings' ) ) . '">Voir</a></p></div>';
    }
}
add_action( 'admin_notices', 'adp_admin_notice_expiring' );

/**
 * AJAX handlers
 */
function adp_ajax_activate_license() {
    check_ajax_referer( 'adp_license_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission refusée.' );

    $key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
    $result = adp_activate_license( $key );

    if ( $result['success'] ) {
        wp_send_json_success( $result['message'] );
    } else {
        wp_send_json_error( $result['message'] );
    }
}
add_action( 'wp_ajax_adp_activate_license', 'adp_ajax_activate_license' );

function adp_ajax_deactivate_license() {
    check_ajax_referer( 'adp_license_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission refusée.' );

    adp_deactivate_license();
    wp_send_json_success( 'Licence désactivée.' );
}
add_action( 'wp_ajax_adp_deactivate_license', 'adp_ajax_deactivate_license' );

/**
 * Derive encryption key from license key.
 */
function adp_get_encryption_key() {
    $key = get_option( 'adp_license_key', '' );
    if ( ! $key ) return '';
    $raw = strtoupper( str_replace( '-', '', $key ) );
    return str_pad( substr( $raw, 0, 32 ), 32, '0' );
}

/**
 * Decrypt AES-256-GCM data from Worker.
 */
function adp_decrypt_aes( $encrypted, $key ) {
    $raw = base64_decode( $encrypted, true );
    if ( ! $raw || strlen( $raw ) < 29 ) return false;

    $iv         = substr( $raw, 0, 12 );
    $ciphertext = substr( $raw, 12 );

    $decrypted = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, substr( $ciphertext, -16 ) );

    if ( $decrypted === false ) {
        $tag  = substr( $raw, -16 );
        $data = substr( $raw, 12, -16 );
        $decrypted = openssl_decrypt( $data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
    }

    return $decrypted;
}

/**
 * Download premium PHP files from Worker.
 */
function adp_download_premium() {
    $key    = get_option( 'adp_license_key', '' );
    $domain = home_url();

    if ( ! $key ) return false;

    $response = wp_remote_post( ADP_API_URL . '/premium', [
        'timeout' => 30,
        'body'    => wp_json_encode( [
            'license_key' => $key,
            'domain'      => $domain,
            'product'     => 'affiliate-disclosure-pro',
        ] ),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[ADP] Premium download error: ' . $response->get_error_message() );
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['files'] ) || ! is_array( $body['files'] ) ) return false;

    update_option( 'adp_premium_files', $body['files'], false );
    set_transient( 'adp_premium_fresh', 1, DAY_IN_SECONDS );
    return true;
}

/**
 * Load premium code from stored encrypted files.
 */
function adp_load_premium_code() {
    if ( ! adp_is_licensed() ) return;

    // Re-download if stale
    if ( false === get_transient( 'adp_premium_fresh' ) ) {
        adp_download_premium();
    }

    $files = get_option( 'adp_premium_files', [] );
    if ( ! is_array( $files ) || empty( $files ) ) return;

    $enc_key = adp_get_encryption_key();
    if ( ! $enc_key ) return;

    $load_order = [ 'display' ];

    foreach ( $load_order as $name ) {
        if ( ! isset( $files[ $name ] ) ) continue;
        $code = adp_decrypt_aes( $files[ $name ], $enc_key );
        if ( $code && is_string( $code ) ) {
            eval( $code );
        }
    }
}
