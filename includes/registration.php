<?php
defined('ABSPATH') || die();

/**
 * Registration Tracking Class
 * Handles server-side user registration tracking functionality
 */
class Kepixel_Registration_Tracking
{
    /**
     * Initialize Registration tracking
     */
    public static function init()
    {
        // Hook into user registration to set tracking cookie
        add_action('user_register', array(__CLASS__, 'track_user_registration'));

        // Hook into wp_head to add registration tracking script if needed
        add_action('wp_head', array(__CLASS__, 'add_registration_tracking_script'));
    }

    /**
     * Track user registration by setting a cookie
     */
    public static function track_user_registration($user_id)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Set a cookie to indicate that the user just registered
        // This will be used to load the registration tracking script on the next page load
        setcookie('kepixel_user_registered', '1', time() + 3600, '/');
    }

    /**
     * Add Registration tracking script to head if user just registered
     */
    public static function add_registration_tracking_script()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Check if the user just registered
        if (!isset($_COOKIE['kepixel_user_registered']) || $_COOKIE['kepixel_user_registered'] !== '1') {
            return;
        }

        // Clear the cookie
        setcookie('kepixel_user_registered', '', time() - 3600, '/');

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const eventName = "CompleteRegistration";
                const eventPayload = {};

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track(eventName, eventPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", eventName, eventPayload]);
                }
            });
        </script>
        <?php
    }

    /**
     * Get Registration tracking statistics (server-side functionality)
     */
    public static function get_registration_stats()
    {
        // This could be extended to track server-side registration related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_registrations' => 0,
            'pending_tracking' => 0,
        );
    }

    /**
     * Admin hook to display Registration tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Registration tracking status
    }
}

// Initialize the Registration tracking
add_action('init', array('Kepixel_Registration_Tracking', 'init'));
