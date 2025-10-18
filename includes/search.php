<?php
defined('ABSPATH') || die();

/**
 * Search Tracking Class
 * Handles server-side search tracking functionality
 */
class Kepixel_Search_Tracking
{
    /**
     * Initialize Search tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_search_tracking_script'));
    }

    /**
     * Add Search tracking script to head
     */
    public static function add_search_tracking_script()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on search pages
        if (!is_search()) {
            return;
        }

        // Get the search query
        $search_query = get_search_query();

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const eventName = "Products Searched";
                const eventPayload = {
                    query: "<?php echo esc_js($search_query); ?>",
                    content_type: 'search',
                    content_id: "<?php echo esc_js($search_query); ?>"
                };

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
     * Get Search tracking statistics (server-side functionality)
     */
    public static function get_search_stats()
    {
        // This could be extended to track server-side search related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_searches' => 0,
            'search_queries' => array(),
        );
    }

    /**
     * Admin hook to display Search tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Search tracking status
    }
}

// Initialize the Search tracking
add_action('init', array('Kepixel_Search_Tracking', 'init'));
