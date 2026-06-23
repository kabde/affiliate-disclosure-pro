<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ADP_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register metabox on targeted post types.
     */
    public function add_meta_boxes() {
        $post_types = adp_get_setting( 'post_types' );
        if ( ! is_array( $post_types ) || empty( $post_types ) ) {
            $post_types = [ 'post' ];
        }

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'adp-disclosure-metabox',
                __( 'Affiliate Disclosure', 'affiliate-disclosure-pro' ),
                [ $this, 'render' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    /**
     * Render metabox content.
     */
    public function render( $post ) {
        wp_nonce_field( 'adp_metabox_nonce', '_adp_nonce' );

        $override    = get_post_meta( $post->ID, '_adp_override', true );
        $custom_text = get_post_meta( $post->ID, '_adp_custom_text', true );
        $position    = get_post_meta( $post->ID, '_adp_position', true );

        if ( ! $override ) {
            $override = 'global';
        }
        if ( ! $position ) {
            $position = 'global';
        }
        ?>
        <div style="line-height:1.8;">
            <p style="margin:0 0 10px;font-weight:600;color:#374151;"><?php esc_html_e( 'Behavior:', 'affiliate-disclosure-pro' ); ?></p>

            <label style="display:block;margin-bottom:4px;">
                <input type="radio" name="adp_override" value="global" <?php checked( $override, 'global' ); ?>>
                <?php esc_html_e( 'Global (follow settings)', 'affiliate-disclosure-pro' ); ?>
            </label>
            <label style="display:block;margin-bottom:4px;">
                <input type="radio" name="adp_override" value="enable" <?php checked( $override, 'enable' ); ?>>
                <?php esc_html_e( 'Enable disclosure', 'affiliate-disclosure-pro' ); ?>
            </label>
            <label style="display:block;margin-bottom:4px;">
                <input type="radio" name="adp_override" value="disable" <?php checked( $override, 'disable' ); ?>>
                <?php esc_html_e( 'Disable disclosure', 'affiliate-disclosure-pro' ); ?>
            </label>

            <div id="adp-custom-text-wrap" style="margin-top:12px;<?php echo $override !== 'enable' ? 'display:none;' : ''; ?>">
                <p style="margin:0 0 4px;font-weight:600;color:#374151;"><?php esc_html_e( 'Custom text:', 'affiliate-disclosure-pro' ); ?></p>
                <textarea name="adp_custom_text" rows="4" style="width:100%;"><?php echo esc_textarea( $custom_text ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Leave empty to use the global text.', 'affiliate-disclosure-pro' ); ?></p>
            </div>

            <div id="adp-position-wrap" style="margin-top:12px;<?php echo $override === 'disable' ? 'display:none;' : ''; ?>">
                <p style="margin:0 0 4px;font-weight:600;color:#374151;"><?php esc_html_e( 'Position:', 'affiliate-disclosure-pro' ); ?></p>
                <select name="adp_position" style="width:100%;">
                    <option value="global" <?php selected( $position, 'global' ); ?>><?php esc_html_e( 'Global (follow settings)', 'affiliate-disclosure-pro' ); ?></option>
                    <option value="before_content" <?php selected( $position, 'before_content' ); ?>><?php esc_html_e( 'Before content', 'affiliate-disclosure-pro' ); ?></option>
                    <option value="after_content" <?php selected( $position, 'after_content' ); ?>><?php esc_html_e( 'After content', 'affiliate-disclosure-pro' ); ?></option>
                    <option value="after_header" <?php selected( $position, 'after_header' ); ?>><?php esc_html_e( 'After header', 'affiliate-disclosure-pro' ); ?></option>
                    <option value="before_footer" <?php selected( $position, 'before_footer' ); ?>><?php esc_html_e( 'Before footer', 'affiliate-disclosure-pro' ); ?></option>
                    <option value="both" <?php selected( $position, 'both' ); ?>><?php esc_html_e( 'Both (before + after)', 'affiliate-disclosure-pro' ); ?></option>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Save metabox data.
     */
    public function save_post( $post_id, $post ) {
        // Nonce check
        if ( ! isset( $_POST['_adp_nonce'] ) || ! wp_verify_nonce( $_POST['_adp_nonce'], 'adp_metabox_nonce' ) ) {
            return;
        }

        // Capability check
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Autosave check
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Override
        $override = isset( $_POST['adp_override'] ) ? sanitize_text_field( $_POST['adp_override'] ) : 'global';
        if ( ! in_array( $override, [ 'global', 'enable', 'disable' ], true ) ) {
            $override = 'global';
        }
        update_post_meta( $post_id, '_adp_override', $override );

        // Custom text
        $custom_text = isset( $_POST['adp_custom_text'] ) ? wp_kses_post( $_POST['adp_custom_text'] ) : '';
        update_post_meta( $post_id, '_adp_custom_text', $custom_text );

        // Position
        $position = isset( $_POST['adp_position'] ) ? sanitize_text_field( $_POST['adp_position'] ) : 'global';
        if ( ! in_array( $position, [ 'global', 'before_content', 'after_content', 'after_header', 'before_footer', 'both' ], true ) ) {
            $position = 'global';
        }
        update_post_meta( $post_id, '_adp_position', $position );
    }

    /**
     * Enqueue admin JS on relevant post types.
     */
    public function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $post_types = adp_get_setting( 'post_types' );
        if ( ! is_array( $post_types ) ) {
            $post_types = [ 'post' ];
        }

        if ( in_array( $screen->post_type, $post_types, true ) ) {
            wp_enqueue_script( 'adp-admin', ADP_URL . 'admin/js/adp-admin.js', [ 'jquery' ], ADP_VERSION, true );
        }
    }
}
