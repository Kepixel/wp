<?php
defined('ABSPATH') || die();

/**
 * Plugin Name: Kepixel
 * Description: Kepixel is an analytics, statistics plugin for WordPress and eCommerce tracking with WooCommerce. Optimize your sales with powerful analytics!
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: kepixel-bot
 * Author URI: https://www.kepixel.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kepixel
 * Domain Path: /languages
 */

/**
 * Load translations
 */
function kepixel_load_textdomain()
{
    load_plugin_textdomain('kepixel', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'kepixel_load_textdomain');

/**
 * Add settings link to plugins page
 */
function kepixel_add_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=kepixel">' . __('Settings', 'kepixel') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'kepixel_add_settings_link');

/**
 * Register settings page
 */
function kepixel_add_settings_page()
{
    add_options_page(
            __('Kepixel Settings', 'kepixel'),
            __('Kepixel', 'kepixel'),
            'manage_options',
            'kepixel',
            'kepixel_render_settings_page'
    );
}
add_action('admin_menu', 'kepixel_add_settings_page');

/**
 * Render settings page
 */
function kepixel_render_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('kepixel_options');
            do_settings_sections('kepixel');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register settings
 */
function kepixel_register_settings()
{
    register_setting('kepixel_options', 'kepixel_write_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
    ));

    register_setting('kepixel_options', 'kepixel_enable_tracking', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
    ));

    add_settings_section(
            'kepixel_settings_section',
            __('Kepixel Settings', 'kepixel'),
            'kepixel_settings_section_callback',
            'kepixel'
    );

    add_settings_field(
            'kepixel_write_key',
            __('Write Key', 'kepixel'),
            'kepixel_write_key_callback',
            'kepixel',
            'kepixel_settings_section'
    );

    add_settings_field(
            'kepixel_enable_tracking',
            __('Enable Tracking', 'kepixel'),
            'kepixel_enable_tracking_callback',
            'kepixel',
            'kepixel_settings_section'
    );
}
add_action('admin_init', 'kepixel_register_settings');

/**
 * Settings section callback
 */
function kepixel_settings_section_callback()
{
    echo '<p>' . __('Enter your Kepixel Write Key below. You can find this in your Kepixel account.', 'kepixel') . '</p>';
}

/**
 * Write Key field callback
 */
function kepixel_write_key_callback()
{
    $write_key = get_option('kepixel_write_key');
    echo '<input type="text" id="kepixel_write_key" name="kepixel_write_key" value="' . esc_attr($write_key) . '" class="regular-text">';
}

/**
 * Enable Tracking field callback
 */
function kepixel_enable_tracking_callback()
{
    $enable_tracking = get_option('kepixel_enable_tracking', true);
    echo '<input type="checkbox" id="kepixel_enable_tracking" name="kepixel_enable_tracking" value="1" ' . checked(1, $enable_tracking, false) . '>';
    echo '<label for="kepixel_enable_tracking">' . __('Enable tracking on this site', 'kepixel') . '</label>';
}

/**
 * Check if WooCommerce is installed and activated
 */
function kepixel_is_woocommerce_installed()
{
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    return is_plugin_active('woocommerce/woocommerce.php');
}

/**
 * Check if cf7 is installed and activated
 */
function kepixel_is_cf7_installed()
{
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    return is_plugin_active('contact-form-7/wp-contact-form-7.php');
}

/**
 * Display an admin notice if Kepixel Write Key is not set
 */
function kepixel_write_key_not_set()
{
    // Only show this notice in the admin area
    if (!is_admin()) {
        return;
    }

    // Get the current screen
    $screen = get_current_screen();
    // Skip on some screens to avoid too many notices
    if ($screen && in_array($screen->id, ['plugins', 'update-core'], true)) {
        return;
    }

    $write_key = get_option('kepixel_write_key');
    if (empty($write_key)) {
        $settings_url = esc_url(admin_url('options-general.php?page=kepixel'));
        $message = sprintf(
            /* translators: %s: Link to the Kepixel settings page. */
            __('Kepixel requires a Write Key to function properly. Please <a href="%s">set your Write Key</a> to enable tracking.', 'kepixel'),
            $settings_url
        );

        echo '<div class="notice notice-warning">';
        echo '<p>' . wp_kses($message, array('a' => array('href' => array()))) . '</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'kepixel_write_key_not_set');

require_once plugin_dir_path(__FILE__) . 'includes/class-kepixel.php'; // Include Kepixel class
if (!kepixel_is_woocommerce_installed()) {
    /**
     * Display an admin notice if WooCommerce is not installed or activated
     */
    function kepixel_woocommerce_not_detected() {
        $user_id = get_current_user_id();

        // Skip if user has dismissed the notice
        if (get_user_meta($user_id, 'kepixel_woo_notice_dismissed', true)) {
            return;
        }

        $woocommerce_url = esc_url('https://woocommerce.com/');
        $woocommerce_link = sprintf(
            '<a href="%1$s" target="%2$s" rel="%3$s">%4$s</a>',
            $woocommerce_url,
            esc_attr('_blank'),
            esc_attr('noopener noreferrer'),
            esc_html__('WooCommerce', 'kepixel')
        );

        $message = sprintf(
            /* translators: %s: Link to the WooCommerce website. */
            __('Kepixel can work with %s.', 'kepixel'),
            $woocommerce_link
        );

        echo '<div class="notice notice-info is-dismissible kepixel-notice">';
        echo '<p>' . wp_kses($message, array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))) . '</p>';
        echo '</div>';
    }
    add_action('admin_notices', 'kepixel_woocommerce_not_detected');

    /**
     * Output JS to capture dismissal and send AJAX
     */
    function kepixel_notice_dismiss_script() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.kepixel-notice').on('click', '.notice-dismiss', function() {
                    $.post(ajaxurl, {
                        action: 'kepixel_dismiss_notice',
                        nonce: '<?php echo esc_js(wp_create_nonce('kepixel_dismiss_nonce')); ?>'
                    });
                });
            });
        </script>
        <?php
    }
    add_action('admin_footer', 'kepixel_notice_dismiss_script');

    /**
     * AJAX handler to save dismissal state
     */
    function kepixel_dismiss_notice_callback() {
        check_ajax_referer('kepixel_dismiss_nonce', 'nonce');

        $user_id = get_current_user_id();
        update_user_meta($user_id, 'kepixel_woo_notice_dismissed', 1);

        wp_send_json_success();
    }
    add_action('wp_ajax_kepixel_dismiss_notice', 'kepixel_dismiss_notice_callback');
}

