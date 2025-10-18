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
 * Retrieve remote metadata for plugin updates.
 *
 * @return object|false
 */
function kepixel_get_remote_metadata()
{
    $cache_key = 'kepixel_update_metadata';
    $metadata = get_site_transient($cache_key);

    if (false === $metadata) {
        $response = wp_remote_get('https://edge.kepixel.com/wordpress-plugin/metadata.json', array(
                'timeout' => 15,
                'headers' => array(
                        'Accept' => 'application/json',
                ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body);

        if (!is_object($decoded)) {
            return false;
        }

        $metadata = $decoded;
        set_site_transient($cache_key, $metadata, 12 * HOUR_IN_SECONDS);
    }

    return $metadata;
}

/**
 * Inject update information into WordPress update checks.
 *
 * @param object $transient
 *
 * @return object
 */
function kepixel_check_for_updates($transient)
{
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_file = plugin_basename(__FILE__);
    $current_version = isset($transient->checked[$plugin_file]) ? $transient->checked[$plugin_file] : false;

    $metadata = kepixel_get_remote_metadata();

    if (!$metadata || !$current_version || empty($metadata->version)) {
        return $transient;
    }

    if (version_compare($metadata->version, $current_version, '>')) {
        $update = new stdClass();
        $update->slug = 'kepixel';
        $update->plugin = $plugin_file;
        $update->new_version = $metadata->version;

        if (!empty($metadata->download_url)) {
            $update->package = $metadata->download_url;
        }

        if (!empty($metadata->requires)) {
            $update->requires = $metadata->requires;
        }

        if (!empty($metadata->tested)) {
            $update->tested = $metadata->tested;
        }

        if (!empty($metadata->requires_php)) {
            $update->requires_php = $metadata->requires_php;
        }

        $transient->response[$plugin_file] = $update;
    }

    return $transient;
}
add_filter('site_transient_update_plugins', 'kepixel_check_for_updates');

/**
 * Provide plugin information in the WordPress updates UI.
 *
 * @param false|object|array $result
 * @param string             $action
 * @param object             $args
 *
 * @return object|false
 */
function kepixel_plugins_api($result, $action, $args)
{
    if ('plugin_information' !== $action || empty($args->slug) || 'kepixel' !== $args->slug) {
        return $result;
    }

    $metadata = kepixel_get_remote_metadata();

    if (!$metadata) {
        return $result;
    }

    $info = new stdClass();
    $info->name = isset($metadata->name) ? $metadata->name : 'Kepixel';
    $info->slug = 'kepixel';
    $info->version = isset($metadata->version) ? $metadata->version : '1.0.0';
    $info->author = isset($metadata->author) ? $metadata->author : 'kepixel-bot';
    $info->homepage = isset($metadata->homepage) ? $metadata->homepage : 'https://www.kepixel.com/';
    $info->requires = isset($metadata->requires) ? $metadata->requires : '6.0';
    $info->tested = isset($metadata->tested) ? $metadata->tested : get_bloginfo('version');
    $info->requires_php = isset($metadata->requires_php) ? $metadata->requires_php : '7.4';
    $info->download_link = isset($metadata->download_url) ? $metadata->download_url : '';

    $sections = array();
    if (!empty($metadata->sections) && is_object($metadata->sections)) {
        foreach ($metadata->sections as $section => $content) {
            $sections[$section] = $content;
        }
    } else {
        $sections['description'] = __('Kepixel plugin update information is currently unavailable.', 'kepixel');
    }

    $info->sections = $sections;

    if (!empty($metadata->banners) && is_object($metadata->banners)) {
        $info->banners = (array) $metadata->banners;
    }

    return $info;
}
add_filter('plugins_api', 'kepixel_plugins_api', 10, 3);

/**
 * Force automatic updates for this plugin.
 *
 * @param bool   $update
 * @param object $item
 *
 * @return bool
 */
function kepixel_force_auto_update($update, $item)
{
    if (!empty($item->plugin) && plugin_basename(__FILE__) === $item->plugin) {
        return true;
    }

    return $update;
}
add_filter('auto_update_plugin', 'kepixel_force_auto_update', 10, 2);

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

