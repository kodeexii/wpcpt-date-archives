<?php
/**
 * Plugin Name:       WPCPT Date Archives
 * Plugin URI:        https://hadeeroslan.my/wpcpt-date-archives
 * Description:       Enables structured date archives (e.g., /cpt-slug/year/month/) for selected Custom Post Types.
 * Version:           0.0.2
 * Author:            Al-Hadee Mohd Roslan & Mat Gem
 * Author URI:        https://hadeeroslan.my
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wpcptda
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =================================================================================
// Bahagian Auto-Updater
// =================================================================================
if ( file_exists(__DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php') ) {
    require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 'https://github.com/kodeexii/wpcpt-date-archives/', __FILE__, 'wpcpt-date-archives' );
}


/**
 * Hook untuk menambah rewrite rules semasa plugin diaktifkan.
 */
register_activation_hook( __FILE__, 'wpcptda_flush_rewrite_rules_on_activate' );
function wpcptda_flush_rewrite_rules_on_activate() {
    // Panggil fungsi utama untuk pastikan rules ada sebelum flush.
    wpcptda_add_date_archive_rules();
    // Flush the rewrite rules.
    flush_rewrite_rules();
}

/**
 * Hook untuk membuang rewrite rules semasa plugin dinyahaktifkan.
 */
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );


/**
 * Fungsi utama yang menambah rewrite rules berdasarkan CPT yang dipilih dalam settings.
 */
add_action( 'init', 'wpcptda_add_date_archive_rules' );
function wpcptda_add_date_archive_rules() {
    // Dapatkan CPT yang telah dipilih dari options table.
    $enabled_cpts = get_option( 'wpcptda_settings' );

    // Jika tiada CPT yang dipilih, jangan buat apa-apa.
    if ( empty( $enabled_cpts ) || ! is_array( $enabled_cpts ) ) {
        return;
    }

    // Loop melalui setiap CPT yang telah diaktifkan.
    foreach ( $enabled_cpts as $cpt_slug ) {
        // Peraturan untuk arkib TAHUN, BULAN, dan HARI
        add_rewrite_rule(
            "^{$cpt_slug}/(\d{4})/(\d{2})/(\d{2})/?$",
            'index.php?post_type=' . $cpt_slug . '&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
            'top'
        );
        // Peraturan untuk arkib TAHUN dan BULAN
        add_rewrite_rule(
            "^{$cpt_slug}/(\d{4})/(\d{2})/?$",
            'index.php?post_type=' . $cpt_slug . '&year=$matches[1]&monthnum=$matches[2]',
            'top'
        );
        // Peraturan untuk arkib TAHUN sahaja
        add_rewrite_rule(
            "^{$cpt_slug}/(\d{4})/?$",
            'index.php?post_type=' . $cpt_slug . '&year=$matches[1]',
            'top'
        );
    }
}


/**
 * Menambah halaman settings di bawah menu "Settings" dalam admin dashboard.
 */
add_action( 'admin_menu', 'wpcptda_add_admin_menu' );
function wpcptda_add_admin_menu() {
    add_options_page(
        'CPT Date Archives',                     // Page Title
        'CPT Date Archives',                     // Menu Title
        'manage_options',                        // Capability
        'wpcpt_date_archives',                   // Menu Slug
        'wpcptda_render_settings_page'           // Callback function to render the page
    );
}


/**
 * Mendaftar setting kita dengan WordPress Settings API.
 */
add_action( 'admin_init', 'wpcptda_register_settings' );
function wpcptda_register_settings() {
    register_setting(
        'wpcptda_options_group',        // Option group
        'wpcptda_settings',             // Option name
        'wpcptda_sanitize_settings'     // Sanitization callback
    );
}

/**
 * Callback function untuk sanitize input sebelum disimpan ke database.
 * Ini adalah langkah keselamatan yang penting.
 */
function wpcptda_sanitize_settings( $input ) {
    $sanitized_input = [];
    if ( ! empty( $input ) && is_array( $input ) ) {
        // Dapatkan semua CPT yang wujud untuk perbandingan.
        $registered_cpts = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $input as $cpt_slug ) {
            // Hanya simpan jika ia adalah CPT slug yang sah dan wujud.
            if ( array_key_exists( $cpt_slug, $registered_cpts ) ) {
                $sanitized_input[] = sanitize_key( $cpt_slug );
            }
        }
    }
    
    // Selepas menyimpan, kita perlu flush rewrite rules supaya perubahan berkuatkuasa serta-merta.
    flush_rewrite_rules();

    return $sanitized_input;
}


/**
 * Callback function untuk memaparkan kandungan halaman settings.
 */
function wpcptda_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p>Pilih Custom Post Types (CPTs) yang mana Tuan ingin aktifkan arkib berdasarkan tarikh.</p>
        <form action="options.php" method="post">
            <?php
            // Security fields
            settings_fields( 'wpcptda_options_group' );
            
            // Dapatkan senarai CPT awam yang bukan 'built-in'.
            $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
            
            // Dapatkan settings yang telah disimpan.
            $enabled_cpts = (array) get_option( 'wpcptda_settings', [] );

            if ( empty( $post_types ) ) {
                echo '<p>Tiada Custom Post Type awam yang ditemui.</p>';
            } else {
                ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Aktifkan untuk CPTs</th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>Pilih CPTs</span></legend>
                                    <?php foreach ( $post_types as $post_type ) : ?>
                                        <label for="<?php echo esc_attr( $post_type->name ); ?>">
                                            <input
                                                type="checkbox"
                                                name="wpcptda_settings[]"
                                                id="<?php echo esc_attr( $post__type->name ); ?>"
                                                value="<?php echo esc_attr( $post_type->name ); ?>"
                                                <?php checked( in_array( $post_type->name, $enabled_cpts ) ); ?>
                                            />
                                            <?php echo esc_html( $post_type->label ); ?> (<code><?php echo esc_html( $post_type->name ); ?></code>)
                                        </label><br>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php
            }
            
            // Submit button
            submit_button( 'Simpan Tetapan' );
            ?>
        </form>
    </div>
    <?php
}
