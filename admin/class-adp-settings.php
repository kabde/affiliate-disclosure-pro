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
            80
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

        // Général
        $clean['enabled']  = empty( $input['enabled'] ) ? '0' : '1';
        $clean['text']     = wp_kses_post( $input['text'] ?? '' );
        $clean['position'] = in_array( $input['position'] ?? '', [ 'before_content', 'after_content', 'before_footer', 'after_header', 'both' ], true ) ? $input['position'] : 'after_content';

        // Ciblage
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

        // Apparence
        $clean['style']         = in_array( $input['style'] ?? '', [ 'box', 'banner', 'inline', 'minimal' ], true ) ? $input['style'] : 'box';
        $clean['color_bg']      = sanitize_hex_color( $input['color_bg'] ?? '#f8f9fa' ) ?: '#f8f9fa';
        $clean['color_text']    = sanitize_hex_color( $input['color_text'] ?? '#6b7280' ) ?: '#6b7280';
        $clean['color_border']  = sanitize_hex_color( $input['color_border'] ?? '#e5e7eb' ) ?: '#e5e7eb';
        $clean['show_icon']     = empty( $input['show_icon'] ) ? '0' : '1';
        $clean['border_radius'] = absint( $input['border_radius'] ?? 6 );
        $clean['custom_css']    = wp_strip_all_tags( $input['custom_css'] ?? '' );

        return $clean;
    }

    /* --- Assets --- */

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook ) {
            return;
        }

        wp_enqueue_style( 'adp-frontend', ADP_URL . 'admin/css/adp-frontend.css', [], ADP_VERSION );
        wp_enqueue_script( 'adp-admin', ADP_URL . 'admin/js/adp-admin.js', [ 'jquery' ], ADP_VERSION, true );
    }

    /* --- Render --- */

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions.' );
        }

        $licensed      = adp_is_licensed();
        $license_key   = get_option( 'adp_license_key', '' );
        $settings      = get_option( self::OPTION_KEY, [] );
        $defaults      = adp_settings_defaults();
        $s             = wp_parse_args( $settings, $defaults );
        $tabs = [
            'license'    => [ 'label' => 'Licence',       'icon' => 'dashicons-lock' ],
            'general'    => [ 'label' => 'Général',       'icon' => 'dashicons-admin-settings' ],
            'targeting'  => [ 'label' => 'Ciblage',        'icon' => 'dashicons-filter' ],
            'appearance' => [ 'label' => 'Apparence',      'icon' => 'dashicons-art' ],
            'docs'       => [ 'label' => 'Documentation',  'icon' => 'dashicons-book' ],
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
                            <h2>Licence</h2>
                            <div class="adp-license-card">
                                <?php if ( $licensed ) : ?>
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span class="adp-license-active">&#10003; Licence Active</span>
                                    </div>
                                    <table class="form-table" style="margin:0;">
                                        <tr>
                                            <th>Cl&eacute; de licence</th>
                                            <td><?php
$masked = substr($license_key, 0, 4) . '-****-****-' . substr($license_key, -4);
?><code style="font-size:14px;"><?php echo esc_html($masked); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Domaine</th>
                                            <td><?php echo esc_html( home_url() ); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Expiration</th>
                                            <td>
                                                <?php
                                                $expires = get_option( 'adp_license_expires_at', '' );
                                                if ( $expires ) {
                                                    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
                                                    $date_formatted = wp_date( 'd F Y', strtotime( $expires ) );
                                                    if ( $days <= 0 ) {
                                                        echo '<span style="color:#dc2626;font-weight:600;">Expirée le ' . esc_html( $date_formatted ) . '</span>';
                                                    } elseif ( $days <= 30 ) {
                                                        echo '<span style="color:#d97706;font-weight:600;">' . esc_html( $date_formatted ) . ' (' . $days . ' jour' . ($days > 1 ? 's' : '') . ' restants)</span>';
                                                    } else {
                                                        echo '<span style="color:#16a34a;">' . esc_html( $date_formatted ) . ' (' . $days . ' jours restants)</span>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#16a34a;">Lifetime (pas d\'expiration)</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin-top:20px;">
                                        <button type="button" id="adp-deactivate-btn" class="button button-secondary" style="color:#d63638;">D&eacute;sactiver la licence</button>
                                    </p>
                                <?php else : ?>
                                    <h2 style="margin-top:0;">Activez votre licence</h2>
                                    <p>Entrez votre cl&eacute; de licence pour activer Affiliate Disclosure Pro.</p>
                                    <p>
                                        <input type="text" id="adp-license-key" placeholder="ADP-XXXX-XXXX-XXXX" style="width:100%;font-size:16px;padding:8px 12px;font-family:monospace;text-transform:uppercase;" maxlength="19">
                                    </p>
                                    <p>
                                        <button type="button" id="adp-activate-btn" class="button button-primary button-hero" style="width:100%;">Activer la licence</button>
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
                                <h2>Param&egrave;tres g&eacute;n&eacute;raux</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Activer les divulgations</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="adp_settings[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?>>
                                                Afficher automatiquement les avertissements d'affiliation
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Texte par d&eacute;faut</th>
                                        <td>
                                            <textarea name="adp_settings[text]" rows="5" style="width:100%;font-size:14px;"><?php echo esc_textarea( $s['text'] ); ?></textarea>
                                            <p style="margin-top:8px;">
                                                <button type="button" id="adp-preset-fr" class="button adp-preset-btn">Preset FR</button>
                                                <button type="button" id="adp-preset-en" class="button adp-preset-btn">Preset EN</button>
                                            </p>
                                            <p class="description">Le texte qui sera affich&eacute; comme avertissement d'affiliation.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Position</th>
                                        <td>
                                            <fieldset>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="before_content" <?php checked( $s['position'], 'before_content' ); ?>>
                                                    Avant le contenu
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="after_content" <?php checked( $s['position'], 'after_content' ); ?>>
                                                    Apr&egrave;s le contenu
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="after_header" <?php checked( $s['position'], 'after_header' ); ?>>
                                                    Apr&egrave;s le header (wp_body_open)
                                                </label>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[position]" value="before_footer" <?php checked( $s['position'], 'before_footer' ); ?>>
                                                    Avant le footer (get_footer)
                                                </label>
                                                <label style="display:block;">
                                                    <input type="radio" name="adp_settings[position]" value="both" <?php checked( $s['position'], 'both' ); ?>>
                                                    Les deux (avant + apr&egrave;s le contenu)
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- Targeting Tab -->
                        <div id="adp-tab-targeting" class="adp-tab-content">
                            <div class="adp-admin-section">
                                <h2>Types de contenu</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Post types</th>
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
                                <h2>Cat&eacute;gories</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Cat&eacute;gories cibl&eacute;es</th>
                                        <td>
                                            <label style="display:block;margin-bottom:12px;font-weight:600;">
                                                <input type="checkbox" id="adp-all-categories">
                                                Toutes les cat&eacute;gories
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
                                            <p class="description">Laissez vide pour cibler toutes les cat&eacute;gories.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="adp-admin-section">
                                <h2>Comportement par d&eacute;faut</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Mode</th>
                                        <td>
                                            <fieldset>
                                                <label style="display:block;margin-bottom:6px;">
                                                    <input type="radio" name="adp_settings[behavior]" value="opt-out" <?php checked( $s['behavior'], 'opt-out' ); ?>>
                                                    <strong>Opt-out</strong> &mdash; Affich&eacute; partout, d&eacute;sactivable par article
                                                </label>
                                                <label style="display:block;">
                                                    <input type="radio" name="adp_settings[behavior]" value="opt-in" <?php checked( $s['behavior'], 'opt-in' ); ?>>
                                                    <strong>Opt-in</strong> &mdash; Masqu&eacute; par d&eacute;faut, activable par article
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- Appearance Tab -->
                        <div id="adp-tab-appearance" class="adp-tab-content">
                            <div class="adp-admin-section">
                                <h2>Style</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Style d'affichage</th>
                                        <td>
                                            <select name="adp_settings[style]">
                                                <option value="box" <?php selected( $s['style'], 'box' ); ?>>Box (encadr&eacute;)</option>
                                                <option value="banner" <?php selected( $s['style'], 'banner' ); ?>>Banner (barre lat&eacute;rale)</option>
                                                <option value="inline" <?php selected( $s['style'], 'inline' ); ?>>Inline (italique)</option>
                                                <option value="minimal" <?php selected( $s['style'], 'minimal' ); ?>>Minimal (petit texte)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Couleur de fond</th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_bg]" value="<?php echo esc_attr( $s['color_bg'] ); ?>">
                                                <code><?php echo esc_html( $s['color_bg'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Couleur du texte</th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_text]" value="<?php echo esc_attr( $s['color_text'] ); ?>">
                                                <code><?php echo esc_html( $s['color_text'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Couleur de bordure</th>
                                        <td>
                                            <div class="adp-color-field">
                                                <input type="color" name="adp_settings[color_border]" value="<?php echo esc_attr( $s['color_border'] ); ?>">
                                                <code><?php echo esc_html( $s['color_border'] ); ?></code>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Afficher l'ic&ocirc;ne</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="adp_settings[show_icon]" value="1" <?php checked( $s['show_icon'], '1' ); ?>>
                                                Afficher une ic&ocirc;ne info avant le texte
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Border radius</th>
                                        <td>
                                            <input type="number" name="adp_settings[border_radius]" value="<?php echo absint( $s['border_radius'] ); ?>" min="0" max="50" style="width:80px;"> px
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">CSS personnalis&eacute;</th>
                                        <td>
                                            <textarea name="adp_settings[custom_css]" rows="6" style="width:100%;font-family:monospace;font-size:13px;"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
                                            <p class="description">CSS additionnel appliqu&eacute; au front-end.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="adp-admin-section">
                                <h2>Aper&ccedil;u en direct</h2>
                                <div id="adp-preview" class="adp-disclosure adp-style-<?php echo esc_attr( $s['style'] ); ?>" style="--adp-bg:<?php echo esc_attr( $s['color_bg'] ); ?>;--adp-color:<?php echo esc_attr( $s['color_text'] ); ?>;--adp-border:<?php echo esc_attr( $s['color_border'] ); ?>;--adp-radius:<?php echo absint( $s['border_radius'] ); ?>px;">
                                    <?php if ( $s['show_icon'] === '1' ) : ?><span class="adp-icon">&#8505;&#65039;</span> <?php endif; ?>
                                    <p class="adp-text" style="display:inline"><?php echo esc_html( mb_substr( $s['text'], 0, 100 ) ); ?>...</p>
                                </div>
                            </div>

                            <div class="submit">
                                <?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                    </form>

                    <!-- Documentation tab (outside the form, no save needed) -->
                    <div id="adp-tab-docs" class="adp-tab-content">

                        <div class="adp-admin-section">
                            <h2>Premiers pas</h2>
                            <ol style="line-height:2;font-size:14px;color:#374151;">
                                <li>Activez votre licence dans l'onglet <strong>Licence</strong></li>
                                <li>Configurez le <strong>texte par d&eacute;faut</strong> dans l'onglet G&eacute;n&eacute;ral (utilisez les presets FR ou EN)</li>
                                <li>Choisissez la <strong>position</strong> d'affichage (avant/apr&egrave;s le contenu, header, footer)</li>
                                <li>S&eacute;lectionnez les <strong>post types</strong> et <strong>cat&eacute;gories</strong> cibl&eacute;s dans l'onglet Ciblage</li>
                                <li>Personnalisez l'<strong>apparence</strong> (style, couleurs, ic&ocirc;ne) dans l'onglet Apparence</li>
                                <li>Pour chaque article, vous pouvez <strong>surcharger</strong> le comportement via la metabox lat&eacute;rale</li>
                            </ol>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Options de position</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr><th>Position</th><th>Description</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Avant le contenu</strong></td>
                                        <td>La divulgation appara&icirc;t au d&eacute;but de l'article, avant le premier paragraphe. Recommand&eacute; pour la conformit&eacute; FTC.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Apr&egrave;s le contenu</strong></td>
                                        <td>La divulgation appara&icirc;t &agrave; la fin de l'article, apr&egrave;s le dernier paragraphe.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Apr&egrave;s le header</strong></td>
                                        <td>Utilise le hook <code>wp_body_open</code>. Appara&icirc;t en haut de la page, avant tout contenu.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Avant le footer</strong></td>
                                        <td>Utilise le hook <code>get_footer</code>. Appara&icirc;t en bas de page, juste avant le pied de page.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Les deux</strong></td>
                                        <td>Affiche la divulgation avant ET apr&egrave;s le contenu. Id&eacute;al pour les articles longs.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Styles d'affichage</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr><th>Style</th><th>Apparence</th><th>Id&eacute;al pour</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Box</strong></td>
                                        <td>Encadr&eacute; avec fond, bordure et border-radius</td>
                                        <td>Usage g&eacute;n&eacute;ral, blogs, sites &eacute;ditoriaux</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Banner</strong></td>
                                        <td>Barre avec bordure lat&eacute;rale color&eacute;e (style callout)</td>
                                        <td>Sites modernes, mise en &eacute;vidence</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Inline</strong></td>
                                        <td>Texte en italique, discret, int&eacute;gr&eacute; au contenu</td>
                                        <td>Blogs minimalistes, int&eacute;gration discr&egrave;te</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Minimal</strong></td>
                                        <td>Petit texte gris, tr&egrave;s discret</td>
                                        <td>Sites commerciaux, mentions l&eacute;gales discr&egrave;tes</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Surcharge par article</h2>
                            <p style="color:#374151;">Chaque article dispose d'une metabox <strong>Affiliate Disclosure</strong> dans la sidebar de l'&eacute;diteur. Trois options :</p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>Global</strong> &mdash; Suit le comportement d&eacute;fini dans les r&eacute;glages (opt-in/opt-out)</li>
                                <li><strong>Activer</strong> &mdash; Force l'affichage de la divulgation sur cet article + permet un texte personnalis&eacute;</li>
                                <li><strong>D&eacute;sactiver</strong> &mdash; Masque la divulgation sur cet article</li>
                            </ul>
                            <p style="color:#374151;">Quand &laquo; Activer &raquo; est s&eacute;lectionn&eacute;, vous pouvez &eacute;crire un texte sp&eacute;cifique et choisir une position diff&eacute;rente.</p>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Shortcode</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead>
                                    <tr><th>Shortcode</th><th>R&eacute;sultat</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>[adp_disclosure]</code></td>
                                        <td>Affiche la divulgation &agrave; l'endroit exact o&ugrave; le shortcode est plac&eacute;. Utilise le texte global ou le texte personnalis&eacute; de l'article.</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="description" style="margin-top:10px;">Le shortcode ignore la position configur&eacute;e &mdash; il s'affiche exactement l&agrave; o&ugrave; vous le placez dans le contenu.</p>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Conformit&eacute; FTC</h2>
                            <p style="color:#374151;">La <strong>Federal Trade Commission (FTC)</strong> exige que les cr&eacute;ateurs de contenu divulguent clairement leurs relations d'affiliation. Affiliate Disclosure Pro vous aide &agrave; :</p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li>Afficher automatiquement un avertissement sur tous les articles contenant des liens d'affiliation</li>
                                <li>Placer la divulgation de mani&egrave;re visible (recommandation FTC : <strong>avant le premier lien d'affiliation</strong>)</li>
                                <li>Utiliser un langage clair et compr&eacute;hensible</li>
                                <li>Rendre la divulgation visuellement distincte du contenu</li>
                            </ul>
                            <p style="color:#6b7280;font-size:13px;margin-top:12px;"><em>Note : Ce plugin facilite la conformit&eacute; mais ne constitue pas un conseil juridique. Consultez un avocat pour vos obligations sp&eacute;cifiques.</em></p>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Ciblage par cat&eacute;gories</h2>
                            <p style="color:#374151;">Dans l'onglet <strong>Ciblage</strong>, vous pouvez s&eacute;lectionner les cat&eacute;gories sur lesquelles la divulgation sera affich&eacute;e :</p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>Aucune cat&eacute;gorie coch&eacute;e</strong> = toutes les cat&eacute;gories sont cibl&eacute;es</li>
                                <li><strong>Cat&eacute;gories s&eacute;lectionn&eacute;es</strong> = seuls les articles dans ces cat&eacute;gories affichent la divulgation</li>
                                <li>Un article dans <strong>plusieurs cat&eacute;gories</strong> affichera la divulgation si au moins une est cibl&eacute;e</li>
                            </ul>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Ciblage par post types</h2>
                            <p style="color:#374151;">Par d&eacute;faut, seuls les <strong>Articles (post)</strong> sont cibl&eacute;s. Vous pouvez ajouter des post types personnalis&eacute;s :</p>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>post</strong> &mdash; Articles de blog</li>
                                <li><strong>page</strong> &mdash; Pages statiques</li>
                                <li>Tout <strong>custom post type</strong> public enregistr&eacute; dans WordPress</li>
                            </ul>
                            <p style="color:#6b7280;font-size:13px;">La metabox de surcharge appara&icirc;t uniquement sur les post types s&eacute;lectionn&eacute;s.</p>
                        </div>

                        <div class="adp-admin-section">
                            <h2>Licence</h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li>Le plugin n&eacute;cessite une <strong>cl&eacute; de licence</strong> au format <code>ADP-XXXX-XXXX-XXXX</code></li>
                                <li>La licence est valid&eacute;e toutes les 72 heures automatiquement</li>
                                <li>Selon votre licence, elle peut &ecirc;tre <strong>mono-domaine</strong> (un seul site) ou <strong>multi-domaines</strong> (illimit&eacute;)</li>
                                <li>En cas de changement de domaine, d&eacute;sactivez d'abord la licence sur l'ancien domaine</li>
                                <li>Les mises &agrave; jour du plugin sont automatiques via l'admin WordPress</li>
                            </ul>
                        </div>

                        <div class="adp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;">Support</h2>
                            <p style="color:#374151;">Pour toute question ou probl&egrave;me :</p>
                            <ul style="list-style:none;padding:0;line-height:2.2;">
                                <li>Email : <a href="mailto:contact@khalid.digital">contact@khalid.digital</a></li>
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

            $('#adp-activate-btn').on('click', function() {
                var btn = $(this);
                var key = $('#adp-license-key').val().trim();
                if (!key) return;

                btn.prop('disabled', true).text('Activation...');

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
                        btn.prop('disabled', false).text('Activer la licence');
                    }
                }).fail(function() {
                    $('#adp-license-message').html('<div class="notice notice-error inline"><p>Erreur de connexion.</p></div>').show();
                    btn.prop('disabled', false).text('Activer la licence');
                });
            });

            $('#adp-deactivate-btn').on('click', function() {
                if (!confirm('Désactiver la licence sur ce domaine ?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('Désactivation...');

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
        'text'             => 'Cet article contient des liens d\'affiliation. Si vous effectuez un achat via ces liens, nous pouvons percevoir une commission, sans frais supplémentaires pour vous.',
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
