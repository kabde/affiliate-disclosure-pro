<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ADP_Settings {

    const OPTION_KEY = 'adp_settings';

    /** @var string Settings page hook suffix */
    private $hook = '';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /* --- Menu --- */

    public function add_menu() {
        $this->hook = add_menu_page(
            'Affiliate Disclosure Pro',
            'Affiliate Disclosure',
            'manage_options',
            'adp-settings',
            [ $this, 'render' ],
            'dashicons-info-outline',
            23
        );
    }

    /* --- Register --- */

    public function register_settings() {
        register_setting( 'adp_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );

        add_filter( 'allowed_options', function ( $allowed ) {
            $allowed['adp_settings_group'] = [ 'adp_settings' ];
            return $allowed;
        } );
    }

    /* --- Sanitize --- */

    public function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        // General
        $clean['enabled']  = empty( $input['enabled'] ) ? '0' : '1';
        $clean['text']     = wp_kses_post( $input['text'] ?? '' );
        $clean['position'] = in_array( $input['position'] ?? '', [ 'before_content', 'after_content', 'before_footer', 'after_header', 'both' ], true ) ? $input['position'] : 'after_content';

        // Targeting
        $clean['post_types'] = [];
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            foreach ( $input['post_types'] as $pt ) {
                $clean['post_types'][] = sanitize_key( $pt );
            }
        }

        $clean['categories'] = [];
        if ( ! empty( $input['categories'] ) && is_array( $input['categories'] ) ) {
            foreach ( $input['categories'] as $cat_id ) {
                $clean['categories'][] = absint( $cat_id );
            }
        }

        $clean['behavior'] = in_array( $input['behavior'] ?? '', [ 'opt-out', 'opt-in' ], true ) ? $input['behavior'] : 'opt-out';

        // Appearance
        $clean['style']         = in_array( $input['style'] ?? '', [ 'box', 'banner', 'inline', 'minimal' ], true ) ? $input['style'] : 'box';
        $clean['color_bg']      = sanitize_hex_color( $input['color_bg'] ?? '#f8f9fa' ) ?: '#f8f9fa';
        $clean['color_text']    = sanitize_hex_color( $input['color_text'] ?? '#6b7280' ) ?: '#6b7280';
        $clean['color_border']  = sanitize_hex_color( $input['color_border'] ?? '#e5e7eb' ) ?: '#e5e7eb';
        $clean['show_icon']     = empty( $input['show_icon'] ) ? '0' : '1';
        $clean['border_radius'] = min( 50, max( 0, absint( $input['border_radius'] ?? 6 ) ) );
        $clean['custom_css']    = wp_strip_all_tags( $input['custom_css'] ?? '' );

        return $clean;
    }

    /* --- Assets --- */

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook ) {
            return;
        }

        wp_enqueue_style( 'adp-frontend', ADP_URL . 'admin/css/adp-frontend.css', [], ADP_VERSION );
        wp_enqueue_script( 'adp-admin', ADP_URL . 'admin/js/adp-admin.js', [ 'jquery', 'wp-i18n' ], ADP_VERSION, true );
        wp_set_script_translations( 'adp-admin', 'affiliate-disclosure-pro', ADP_PATH . 'languages' );
    }

    /* --- Render --- */

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'affiliate-disclosure-pro' ) );
        }

        $licensed      = adp_is_licensed();
        $license_key   = get_option( 'adp_license_key', '' );
        $settings      = get_option( self::OPTION_KEY, [] );
        $defaults      = adp_settings_defaults();
        $s             = wp_parse_args( $settings, $defaults );
        $tabs = [
            'license'    => [ 'label' => __( 'License', 'affiliate-disclosure-pro' ),       'icon' => 'dashicons-lock' ],
            'general'    => [ 'label' => __( 'General', 'affiliate-disclosure-pro' ),       'icon' => 'dashicons-admin-settings' ],
            'targeting'  => [ 'label' => __( 'Targeting', 'affiliate-disclosure-pro' ),      'icon' => 'dashicons-filter' ],
            'appearance' => [ 'label' => __( 'Appearance', 'affiliate-disclosure-pro' ),     'icon' => 'dashicons-art' ],
            'docs'       => [ 'label' => __( 'Documentation', 'affiliate-disclosure-pro' ),  'icon' => 'dashicons-book' ],
        ];

        // Only show non-license tabs when licensed
        if ( ! $licensed ) {
            $tabs = [ 'license' => $tabs['license'] ];
        }

        $nonce = wp_create_nonce( 'adp_license_nonce' );
        ?>
        <style>
        /* -- Layout -- */
        #adp-settings-wrap { max-width: 1140px; margin-top: 20px; }
        .adp-settings-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .adp-settings-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; color: #1d2327; }
        .adp-settings-version { background: #f0f0f1; color: #787c82; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .adp-settings-layout { display: grid; grid-template-columns: 220px 1fr; gap: 0; min-height: 600px; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; background: #f6f7f7; }

        /* -- Sidebar -- */
        .adp-settings-sidebar { background: #1d2327; padding: 12px 0; display: flex; flex-direction: column; }
        .adp-sidebar-item { display: flex; align-items: center; gap: 10px; padding: 11px 20px; color: #bbc8d4; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 120ms; border-left: 3px solid transparent; cursor: pointer; }
        .adp-sidebar-item:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .adp-sidebar-item:focus { color: #fff; box-shadow: none; outline: none; }
        .adp-sidebar-item.is-active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: #ffc45e; }
        .adp-sidebar-item .dashicons { font-size: 16px; width: 16px; height: 16px; opacity: 0.65; }
        .adp-sidebar-item.is-active .dashicons { opacity: 1; color: #ffc45e; }

        /* -- Panel -- */
        .adp-settings-panel { background: #fff; padding: 28px 32px; overflow-y: auto; }
        .adp-tab-content { display: none; }
        .adp-tab-content.is-active { display: block; animation: adpFadeIn 200ms ease; }
        @keyframes adpFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* -- Sections -- */
        .adp-admin-section { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px 28px; margin: 0 0 20px; }
        .adp-admin-section h2 { margin: 0 0 16px; padding: 0 0 12px; border-bottom: 1px solid #e5e7eb; font-size: 1.05em; font-weight: 700; color: #1d2327; }
        .adp-admin-section .form-table th { font-weight: 600; color: #374151; padding-top: 16px; }
        .adp-admin-section .form-table td { padding-top: 12px; }

        /* -- Submit button -- */
        .adp-settings-panel .submit { margin-top: 8px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        .adp-settings-panel #submit { background: #1d2327; border-color: #1d2327; color: #fff; border-radius: 6px; padding: 6px 24px; font-weight: 600; transition: background 120ms; }
        .adp-settings-panel #submit:hover { background: #2c3338; }

        /* -- License card -- */
        .adp-license-card { max-width: 600px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 30px; }
        .adp-license-active { display: inline-block; background: #00a32a; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }
        .adp-license-inactive { display: inline-block; background: #dba617; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }

        /* -- Preset buttons -- */
        .adp-preset-btn { margin-right: 8px; margin-top: 8px; }

        /* -- Category grid -- */
        .adp-cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px 16px; max-height: 300px; overflow-y: auto; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; }

        /* -- Color field -- */
        .adp-color-field { display: flex; align-items: center; gap: 10px; }
        .adp-color-field input[type="color"] { width: 50px; height: 36px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; padding: 2px; }

        /* -- Responsive -- */
        @media (max-width: 960px) {
            .adp-settings-layout { grid-template-columns: 1fr; }
            .adp-settings-sidebar { flex-direction: row; flex-wrap: wrap; padding: 8px; gap: 4px; }
            .adp-sidebar-item { padding: 8px 12px; border-left: none; border-bottom: 2px solid transparent; font-size: 12px; }
            .adp-sidebar-item.is-active { border-left: none; border-bottom-color: #ffc45e; }
            .adp-sidebar-item .dashicons { display: none; }
            .adp-settings-panel { padding: 20px 16px; }
        }
        </style>

        <div id="adp-settings-wrap" class="wrap">

            <!-- Header -->
            <div class="adp-settings-header">
                <h1>Affiliate Disclosure Pro</h1>
                <span class="adp-settings-version">v<?php echo esc_html( ADP_VERSION ); ?></span>
            </div>

            <div class="adp-settings-layout">

                <!-- Sidebar -->
                <nav class="adp-settings-sidebar">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <a href="#<?php echo esc_attr( $slug ); ?>" class="adp-sidebar-item" data-tab="<?php echo esc_attr( $slug ); ?>">
                            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                            <?php echo esc_html( $tab['label'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Panel -->
                <div class="adp-settings-panel">

                    <!-- License Tab -->
                    <div id="adp-tab-license" class="adp-tab-content">
                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'License', 'affiliate-disclosure-pro' ); ?></h2>
                            <div class="adp-license-card">
                                <?php if ( $licensed ) : ?>
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span class="adp-license-active">&#10003; <?php esc_html_e( 'License Active', 'affiliate-disclosure-pro' ); ?></span>
                                    </div>
                                    <table class="form-table" style="margin:0;">
                                        <tr>
                                            <th><?php esc_html_e( 'License key', 'affiliate-disclosure-pro' ); ?></th>
                                            <td><?php
$masked = substr($license_key, 0, 4) . '-****-****-' . substr($license_key, -4);
?><code style="font-size:14px;"><?php echo esc_html($masked); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Domain', 'affiliate-disclosure-pro' ); ?></th>
                                            <td><?php echo esc_html( home_url() ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Expiration', 'affiliate-disclosure-pro' ); ?></th>
                                            <td>
                                                <?php
                                                $expires = get_option( 'adp_license_expires_at', '' );
                                                if ( $expires ) {
                                                    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
                                                    $date_formatted = wp_date( 'd F Y', strtotime( $expires ) );
                                                    if ( $days <= 0 ) {
                                                        /* translators: %s: formatted expiration date */
                                                        echo '<span style="color:#dc2626;font-weight:600;">' . esc_html( sprintf( __( 'Expired on %s', 'affiliate-disclosure-pro' ), $date_formatted ) ) . '</span>';
                                                    } elseif ( $days <= 30 ) {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#d97706;font-weight:600;">' . esc_html( sprintf( _n( '%1$s (%2$d day remaining)', '%1$s (%2$d days remaining)', $days, 'affiliate-disclosure-pro' ), $date_formatted, $days ) ) . '</span>';
                                                    } else {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#16a34a;">' . esc_html( sprintf( __( '%1$s (%2$d days remaining)', 'affiliate-disclosure-pro' ), $date_formatted, $days ) ) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#16a34a;">' . esc_html__( 'Lifetime (no expiration)', 'affiliate-disclosure-pro' ) . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin-top:20px;">
                                        <button type="button" id="adp-deactivate-btn" class="button button-secondary" style="color:#d63638;"><?php esc_html_e( 'Deactivate license', 'affiliate-disclosure-pro' ); ?></button>
                                    </p>
                                <?php else : ?>
                                    <h2 style="margin-top:0;"><?php esc_html_e( 'Activate your license', 'affiliate-disclosure-pro' ); ?></h2>
                                    <p><?php esc_html_e( 'Enter your license key to activate Affiliate Disclosure Pro.', 'affiliate-disclosure-pro' ); ?></p>
                                    <p>
                                        <input type="text" id="adp-license-key" placeholder="ADP-XXXX-XXXX-XXXX" style="width:100%;font-size:16px;padding:8px 12px;font-family:monospace;text-transform:uppercase;" maxlength="19">
                                    </p>
                                    <p>
                                        <button type="button" id="adp-activate-btn" class="button button-primary button-hero" style="width:100%;"><?php esc_html_e( 'Activate license', 'affiliate-disclosure-pro' ); ?></button>
                                    </p>
                                    <div id="adp-license-message" style="margin-top:15px;display:none;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ( $licensed ) : ?>

                    <!-- Form wraps General + Targeting + Appearance -->
                    <form method="post" action="options.php" id="adp-settings-form">
                        <?php settings_fields( 'adp_settings_group' ); ?>
                        <input type="hidden" id="adp_active_tab" name="adp_active_tab" value="">

                        <!-- General Tab -->
                        <div id="adp-tab-general" class="adp-tab-content">
                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'General settings', 'affiliate-disclosure-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Enable disclosures', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="adp_settings[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?>>
                                                <?php esc_html_e( 'Automatically display affiliate disclosure notices', 'affiliate-disclosure-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Default text', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <textarea name="adp_settings[text]" rows="5" style="width:100%;font-size:14px;"><?php echo esc_textarea( $s['text'] ); ?></textarea>
                                            <p style="margin-top:8px;">
                                                <button type="button" id="adp-preset-fr" class="button adp-preset-btn"><?php esc_html_e( 'Preset FR', 'affiliate-disclosure-pro' ); ?></button>
                                                <button type="button" id="adp-preset-en" class="button adp-preset-btn"><?php esc_html_e( 'Preset EN', 'affiliate-disclosure-pro' ); ?></button>
                                            </p>
                                            <p class="description"><?php esc_html_e( 'The text that will be displayed as the affiliate disclosure notice.', 'affiliate-disclosure-pro' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Position', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <fieldset>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="before_content" <?php checked( $s['position'], 'before_content' ); ?>>
                                                    <?php esc_html_e( 'Before content', 'affiliate-disclosure-pro' ); ?>
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="after_content" <?php checked( $s['position'], 'after_content' ); ?>>
                                                    <?php esc_html_e( 'After content', 'affiliate-disclosure-pro' ); ?>
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="after_header" <?php checked( $s['position'], 'after_header' ); ?>>
                                                    <?php esc_html_e( 'After header (wp_body_open)', 'affiliate-disclosure-pro' ); ?>
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="before_footer" <?php checked( $s['position'], 'before_footer' ); ?>>
                                                    <?php esc_html_e( 'Before footer (get_footer)', 'affiliate-disclosure-pro' ); ?>
                                                </label>
                                                <label style="display:block;">
                                                    <input type="radio" name="adp_settings[position]" value="both" <?php checked( $s['position'], 'both' ); ?>>
                                                    <?php esc_html_e( 'Both (before + after content)', 'affiliate-disclosure-pro' ); ?>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save', 'affiliate-disclosure-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- Targeting Tab -->
                        <div id="adp-tab-targeting" class="adp-tab-content">
                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'Content types', 'affiliate-disclosure-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Post types', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <?php
                                            $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                            foreach ( $post_types as $pt ) :
                                                $checked = in_array( $pt->name, (array) $s['post_types'], true );
                                            ?>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="checkbox" name="adp_settings[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $checked ); ?>>
                                                    <?php echo esc_html( $pt->labels->name ); ?> <code style="font-size:11px;color:#9ca3af;">(<?php echo esc_html( $pt->name ); ?>)</code>
                                                </label>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'Categories', 'affiliate-disclosure-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Targeted categories', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <label style="display:block;margin-bottom:12px;font-weight:600;">
                                                <input type="checkbox" id="adp-all-categories">
                                                <?php esc_html_e( 'All categories', 'affiliate-disclosure-pro' ); ?>
                                            </label>
                                            <div class="adp-cat-grid">
                                                <?php
                                                $categories = get_categories( [ 'hide_empty' => false ] );
                                                foreach ( $categories as $cat ) :
                                                    $checked = in_array( $cat->term_id, (array) $s['categories'], true );
                                                ?>
                                                    <label>
                                                        <input type="checkbox" name="adp_settings[categories][]" value="<?php echo absint( $cat->term_id ); ?>" <?php checked( $checked ); ?>>
                                                        <?php echo esc_html( $cat->name ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="description"><?php esc_html_e( 'Leave empty to target all categories.', 'affiliate-disclosure-pro' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'Default behavior', 'affiliate-disclosure-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Mode', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <fieldset>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[behavior]" value="opt-out" <?php checked( $s['behavior'], 'opt-out' ); ?>>
                                                    <?php
                                                    /* translators: describes the opt-out mode behavior */
                                                    echo '<strong>' . esc_html__( 'Opt-out', 'affiliate-disclosure-pro' ) . '</strong> &mdash; ' . esc_html__( 'Displayed everywhere, can be disabled per post', 'affiliate-disclosure-pro' );
                                                    ?>
                                                </label>
                                                <label style="display:block;">
                                                    <input type="radio" name="adp_settings[behavior]" value="opt-in" <?php checked( $s['behavior'], 'opt-in' ); ?>>
                                                    <?php
                                                    /* translators: describes the opt-in mode behavior */
                                                    echo '<strong>' . esc_html__( 'Opt-in', 'affiliate-disclosure-pro' ) . '</strong> &mdash; ' . esc_html__( 'Hidden by default, can be enabled per post', 'affiliate-disclosure-pro' );
                                                    ?>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save', 'affiliate-disclosure-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- Appearance Tab -->
                        <div id="adp-tab-appearance" class="adp-tab-content">
                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'Style', 'affiliate-disclosure-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Display style', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <select name="adp_settings[style]">
                                                <option value="box" <?php selected( $s['style'], 'box' ); ?>><?php esc_html_e( 'Box (framed)', 'affiliate-disclosure-pro' ); ?></option>
                                                <option value="banner" <?php selected( $s['style'], 'banner' ); ?>><?php esc_html_e( 'Banner (side bar)', 'affiliate-disclosure-pro' ); ?></option>
                                                <option value="inline" <?php selected( $s['style'], 'inline' ); ?>><?php esc_html_e( 'Inline (italic)', 'affiliate-disclosure-pro' ); ?></option>
                                                <option value="minimal" <?php selected( $s['style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal (small text)', 'affiliate-disclosure-pro' ); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Background color', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_bg]" value="<?php echo esc_attr( $s['color_bg'] ); ?>">
                                                <code><?php echo esc_html( $s['color_bg'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Text color', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_text]" value="<?php echo esc_attr( $s['color_text'] ); ?>">
                                                <code><?php echo esc_html( $s['color_text'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Border color', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_border]" value="<?php echo esc_attr( $s['color_border'] ); ?>">
                                                <code><?php echo esc_html( $s['color_border'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Show icon', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="adp_settings[show_icon]" value="1" <?php checked( $s['show_icon'], '1' ); ?>>
                                                <?php esc_html_e( 'Display an info icon before the text', 'affiliate-disclosure-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Border radius', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <input type="number" name="adp_settings[border_radius]" value="<?php echo absint( $s['border_radius'] ); ?>" min="0" max="50" style="width:80px;"> px
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Custom CSS', 'affiliate-disclosure-pro' ); ?></th>
                                        <td>
                                            <textarea name="adp_settings[custom_css]" rows="6" style="width:100%;font-family:monospace;font-size:13px;"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
                                            <p class="description"><?php esc_html_e( 'Additional CSS applied to the front-end.', 'affiliate-disclosure-pro' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="adp-admin-section">
                                <h2><?php esc_html_e( 'Live preview', 'affiliate-disclosure-pro' ); ?></h2>
                                <div id="adp-preview" class="adp-disclosure adp-style-<?php echo esc_attr( $s['style'] ); ?>" style="--adp-bg:<?php echo esc_attr( $s['color_bg'] ); ?>;--adp-color:<?php echo esc_attr( $s['color_text'] ); ?>;--adp-border:<?php echo esc_attr( $s['color_border'] ); ?>;--adp-radius:<?php echo absint( $s['border_radius'] ); ?>px;">
                                    <?php if ( $s['show_icon'] === '1' ) : ?><span class="adp-icon">&#8505;&#65039;</span> <?php endif; ?>
                                    <p class="adp-text" style="display:inline"><?php echo esc_html( mb_substr( $s['text'], 0, 100 ) ); ?>...</p>
                                </div>
                            </div>

                            <div class="submit">
                                <?php submit_button( __( 'Save', 'affiliate-disclosure-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                    </form>

                    <!-- Documentation tab (outside the form, no save needed) -->
                    <div id="adp-tab-docs" class="adp-tab-content">

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Getting started', 'affiliate-disclosure-pro' ); ?></h2>
                            <ol style="line-height:2;font-size:14px;color:#374151;">
                                <li><?php
                                    /* translators: %s: "License" tab name in bold */
                                    printf( esc_html__( 'Activate your license in the %s tab', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'License', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                                <li><?php
                                    /* translators: %s: "General" tab name */
                                    printf( esc_html__( 'Configure the %s in the General tab (use the FR or EN presets)', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'default text', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                                <li><?php
                                    /* translators: %s: "position" in bold */
                                    printf( esc_html__( 'Choose the display %s (before/after content, header, footer)', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'position', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                                <li><?php
                                    printf(
                                        /* translators: 1: "post types" in bold, 2: "categories" in bold */
                                        esc_html__( 'Select the targeted %1$s and %2$s in the Targeting tab', 'affiliate-disclosure-pro' ),
                                        '<strong>' . esc_html__( 'post types', 'affiliate-disclosure-pro' ) . '</strong>',
                                        '<strong>' . esc_html__( 'categories', 'affiliate-disclosure-pro' ) . '</strong>'
                                    );
                                ?></li>
                                <li><?php
                                    /* translators: %s: "appearance" in bold */
                                    printf( esc_html__( 'Customize the %s (style, colors, icon) in the Appearance tab', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'appearance', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                                <li><?php
                                    /* translators: %s: "override" in bold */
                                    printf( esc_html__( 'For each post, you can %s the behavior via the sidebar metabox', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'override', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                            </ol>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Position options', 'affiliate-disclosure-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Position', 'affiliate-disclosure-pro' ); ?></th>
                                        <th><?php esc_html_e( 'Description', 'affiliate-disclosure-pro' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Before content', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'The disclosure appears at the beginning of the post, before the first paragraph. Recommended for FTC compliance.', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'After content', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'The disclosure appears at the end of the post, after the last paragraph.', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'After header', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php
                                            /* translators: %s: wp_body_open hook name in code tags */
                                            printf( esc_html__( 'Uses the %s hook. Appears at the top of the page, before any content.', 'affiliate-disclosure-pro' ), '<code>wp_body_open</code>' );
                                        ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Before footer', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php
                                            /* translators: %s: get_footer hook name in code tags */
                                            printf( esc_html__( 'Uses the %s hook. Appears at the bottom of the page, just before the footer.', 'affiliate-disclosure-pro' ), '<code>get_footer</code>' );
                                        ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Both', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'Displays the disclosure before AND after the content. Ideal for long posts.', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Display styles', 'affiliate-disclosure-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Style', 'affiliate-disclosure-pro' ); ?></th>
                                        <th><?php esc_html_e( 'Appearance', 'affiliate-disclosure-pro' ); ?></th>
                                        <th><?php esc_html_e( 'Ideal for', 'affiliate-disclosure-pro' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Box', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'Framed with background, border and border-radius', 'affiliate-disclosure-pro' ); ?></td>
                                        <td><?php esc_html_e( 'General use, blogs, editorial sites', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Banner', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'Bar with colored side border (callout style)', 'affiliate-disclosure-pro' ); ?></td>
                                        <td><?php esc_html_e( 'Modern sites, emphasis', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Inline', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'Italic text, discreet, integrated into content', 'affiliate-disclosure-pro' ); ?></td>
                                        <td><?php esc_html_e( 'Minimalist blogs, discreet integration', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php esc_html_e( 'Minimal', 'affiliate-disclosure-pro' ); ?></strong></td>
                                        <td><?php esc_html_e( 'Small gray text, very discreet', 'affiliate-disclosure-pro' ); ?></td>
                                        <td><?php esc_html_e( 'Commercial sites, discreet legal notices', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Per-post override', 'affiliate-disclosure-pro' ); ?></h2>
                            <p style="color:#374151;"><?php
                                /* translators: %s: "Affiliate Disclosure" metabox name in bold */
                                printf( esc_html__( 'Each post has an %s metabox in the editor sidebar. Three options:', 'affiliate-disclosure-pro' ), '<strong>Affiliate Disclosure</strong>' );
                            ?></p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo '<strong>' . esc_html__( 'Global', 'affiliate-disclosure-pro' ) . '</strong> &mdash; ' . esc_html__( 'Follows the behavior defined in settings (opt-in/opt-out)', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php echo '<strong>' . esc_html__( 'Enable', 'affiliate-disclosure-pro' ) . '</strong> &mdash; ' . esc_html__( 'Forces the disclosure on this post + allows custom text', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php echo '<strong>' . esc_html__( 'Disable', 'affiliate-disclosure-pro' ) . '</strong> &mdash; ' . esc_html__( 'Hides the disclosure on this post', 'affiliate-disclosure-pro' ); ?></li>
                            </ul>
                            <p style="color:#374151;"><?php esc_html_e( 'When "Enable" is selected, you can write a specific text and choose a different position.', 'affiliate-disclosure-pro' ); ?></p>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Shortcode', 'affiliate-disclosure-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Shortcode', 'affiliate-disclosure-pro' ); ?></th>
                                        <th><?php esc_html_e( 'Result', 'affiliate-disclosure-pro' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>[adp_disclosure]</code></td>
                                        <td><?php esc_html_e( 'Displays the disclosure exactly where the shortcode is placed. Uses the global text or the custom text of the post.', 'affiliate-disclosure-pro' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="description" style="margin-top:10px;"><?php esc_html_e( 'The shortcode ignores the configured position — it displays exactly where you place it in the content.', 'affiliate-disclosure-pro' ); ?></p>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'FTC Compliance', 'affiliate-disclosure-pro' ); ?></h2>
                            <p style="color:#374151;"><?php esc_html_e( 'The Federal Trade Commission (FTC) requires content creators to clearly disclose their affiliate relationships. Affiliate Disclosure Pro helps you:', 'affiliate-disclosure-pro' ); ?></p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php esc_html_e( 'Automatically display a notice on all posts containing affiliate links', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php
                                    /* translators: %s: "before the first affiliate link" in bold */
                                    printf( esc_html__( 'Place the disclosure visibly (FTC recommendation: %s)', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'before the first affiliate link', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                                <li><?php esc_html_e( 'Use clear and understandable language', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php esc_html_e( 'Make the disclosure visually distinct from the content', 'affiliate-disclosure-pro' ); ?></li>
                            </ul>
                            <p style="color:#6b7280;font-size:13px;margin-top:12px;"><em><?php esc_html_e( 'Note: This plugin facilitates compliance but does not constitute legal advice. Consult a lawyer for your specific obligations.', 'affiliate-disclosure-pro' ); ?></em></p>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Category targeting', 'affiliate-disclosure-pro' ); ?></h2>
                            <p style="color:#374151;"><?php
                                /* translators: %s: "Targeting" tab name in bold */
                                printf( esc_html__( 'In the %s tab, you can select the categories on which the disclosure will be displayed:', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'Targeting', 'affiliate-disclosure-pro' ) . '</strong>' );
                            ?></p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo '<strong>' . esc_html__( 'No category checked', 'affiliate-disclosure-pro' ) . '</strong> = ' . esc_html__( 'all categories are targeted', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php echo '<strong>' . esc_html__( 'Selected categories', 'affiliate-disclosure-pro' ) . '</strong> = ' . esc_html__( 'only posts in those categories display the disclosure', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php esc_html_e( 'A post in multiple categories will display the disclosure if at least one is targeted', 'affiliate-disclosure-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'Post type targeting', 'affiliate-disclosure-pro' ); ?></h2>
                            <p style="color:#374151;"><?php
                                /* translators: %s: "Posts (post)" in bold */
                                printf( esc_html__( 'By default, only %s are targeted. You can add custom post types:', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'Posts (post)', 'affiliate-disclosure-pro' ) . '</strong>' );
                            ?></p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo '<strong>post</strong> &mdash; ' . esc_html__( 'Blog posts', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php echo '<strong>page</strong> &mdash; ' . esc_html__( 'Static pages', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php
                                    /* translators: %s: "custom post type" in bold */
                                    printf( esc_html__( 'Any public %s registered in WordPress', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'custom post type', 'affiliate-disclosure-pro' ) . '</strong>' );
                                ?></li>
                            </ul>
                            <p style="color:#6b7280;font-size:13px;"><?php esc_html_e( 'The override metabox only appears on selected post types.', 'affiliate-disclosure-pro' ); ?></p>
                        </div>

                        <div class="adp-admin-section">
                            <h2><?php esc_html_e( 'License', 'affiliate-disclosure-pro' ); ?></h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php
                                    /* translators: %s: license format in code tags */
                                    printf( esc_html__( 'The plugin requires a %s in the format %s', 'affiliate-disclosure-pro' ), '<strong>' . esc_html__( 'license key', 'affiliate-disclosure-pro' ) . '</strong>', '<code>ADP-XXXX-XXXX-XXXX</code>' );
                                ?></li>
                                <li><?php esc_html_e( 'The license is validated automatically every 72 hours', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php esc_html_e( 'Depending on your license, it can be single-domain (one site) or multi-domain (unlimited)', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php esc_html_e( 'If you change domains, first deactivate the license on the old domain', 'affiliate-disclosure-pro' ); ?></li>
                                <li><?php esc_html_e( 'Plugin updates are automatic via the WordPress admin', 'affiliate-disclosure-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="adp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;"><?php esc_html_e( 'Support', 'affiliate-disclosure-pro' ); ?></h2>
                            <p style="color:#374151;"><?php esc_html_e( 'For any questions or issues:', 'affiliate-disclosure-pro' ); ?></p>
                            <ul style="list-style:none;padding:0;line-height:2.2;">
                                <li><?php /* translators: %s: email link */ printf( esc_html__( 'Email: %s', 'affiliate-disclosure-pro' ), '<a href="mailto:contact@khalid.digital">contact@khalid.digital</a>' ); ?></li>
                            </ul>
                        </div>

                    </div>

                    <?php endif; ?>

                </div><!-- .adp-settings-panel -->
            </div><!-- .adp-settings-layout -->
        </div><!-- #adp-settings-wrap -->

        <script>
        jQuery(function($) {
            /* -- Tab switching -- */
            var $items = $('.adp-sidebar-item');
            var $tabs  = $('.adp-tab-content');

            function activateTab(slug) {
                $items.removeClass('is-active');
                $tabs.removeClass('is-active');
                $items.filter('[data-tab="' + slug + '"]').addClass('is-active');
                $('#adp-tab-' + slug).addClass('is-active');
                $('#adp_active_tab').val(slug);
                if (history.replaceState) {
                    history.replaceState(null, null, '#' + slug);
                }
            }

            $items.on('click', function(e) {
                e.preventDefault();
                activateTab($(this).data('tab'));
            });

            // Determine initial tab
            var hash = window.location.hash.replace('#', '');
            var validTabs = [];
            $items.each(function() { validTabs.push($(this).data('tab')); });

            if (hash && validTabs.indexOf(hash) !== -1) {
                activateTab(hash);
            } else {
                activateTab(validTabs[0] || 'license');
            }

            /* -- License AJAX -- */
            var licenseNonce = '<?php echo esc_js( $nonce ); ?>';
            var _i18n = wp.i18n || { __: function(s) { return s; } };
            var __ = _i18n.__;

            $('#adp-activate-btn').on('click', function() {
                var btn = $(this);
                var key = $('#adp-license-key').val().trim();
                if (!key) return;

                btn.prop('disabled', true).text(__('Activating...', 'affiliate-disclosure-pro'));

                $.post(ajaxurl, {
                    action: 'adp_activate_license',
                    nonce: licenseNonce,
                    license_key: key
                }, function(response) {
                    if (response.success) {
                        $('#adp-license-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>').show();
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $('#adp-license-message').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>').show();
                        btn.prop('disabled', false).text(__('Activate license', 'affiliate-disclosure-pro'));
                    }
                }).fail(function() {
                    $('#adp-license-message').html('<div class="notice notice-error inline"><p>' + __('Connection error.', 'affiliate-disclosure-pro') + '</p></div>').show();
                    btn.prop('disabled', false).text(__('Activate license', 'affiliate-disclosure-pro'));
                });
            });

            $('#adp-deactivate-btn').on('click', function() {
                if (!confirm(__('Deactivate the license on this domain?', 'affiliate-disclosure-pro'))) return;
                var btn = $(this);
                btn.prop('disabled', true).text(__('Deactivating...', 'affiliate-disclosure-pro'));

                $.post(ajaxurl, {
                    action: 'adp_deactivate_license',
                    nonce: licenseNonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
}

/* --- Defaults --- */

function adp_settings_defaults() {
    return [
        'enabled'          => '1',
        'text'             => 'This post contains affiliate links. If you make a purchase through these links, we may earn a commission at no extra cost to you.',
        'position'         => 'after_content',
        'post_types'       => ['post'],
        'categories'       => [],
        'behavior'         => 'opt-out',
        'style'            => 'box',
        'color_bg'         => '#f8f9fa',
        'color_text'       => '#6b7280',
        'color_border'     => '#e5e7eb',
        'show_icon'        => '1',
        'border_radius'    => '6',
        'custom_css'       => '',
    ];
}

/* --- Helper --- */

function adp_get_setting( $key ) {
    static $settings = null;
    if ( $settings === null ) {
        $settings = get_option( ADP_Settings::OPTION_KEY, [] );
    }
    $defaults = adp_settings_defaults();
    return isset( $settings[ $key ] ) && $settings[ $key ] !== '' ? $settings[ $key ] : ( $defaults[ $key ] ?? '' );
}
