<?php
defined('ABSPATH') || die();

/**
 * WhatsApp Tracking Class
 * Handles server-side WhatsApp link detection and tracking enhancement
 */
class Kepixel_WhatsApp_Tracking
{
    /**
     * Initialize WhatsApp tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_whatsapp_tracking_script'));
        add_filter('the_content', array(__CLASS__, 'enhance_whatsapp_links'));
        add_filter('widget_text', array(__CLASS__, 'enhance_whatsapp_links'));
        add_action('wp_footer', array(__CLASS__, 'add_whatsapp_click_handler'));
    }

    /**
     * Add WhatsApp tracking script to head
     */
    public static function add_whatsapp_tracking_script()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        ?>
        <script>
            window.kepixelWhatsAppTracking = {
                track: function(element, properties) {
                    properties = properties || {};
                    properties.element_type = element.tagName.toLowerCase();
                    properties.element_id = element.id || '';
                    properties.element_class = element.className || '';
                    properties.href = element.href || '';
                    properties.page_url = window.location.href;
                    properties.page_title = document.title;

                    if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                        window.kepixelAnalytics.track("WhatsApp Clicked", properties);
                    } else {
                        window.kepixelAnalytics = window.kepixelAnalytics || [];
                        window.kepixelAnalytics.push(["track", "WhatsApp Clicked", properties]);
                    }
                }
            };
        </script>
        <?php
    }

    /**
     * Enhance WhatsApp links in content
     */
    public static function enhance_whatsapp_links($content)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only enhance links if tracking is enabled
        if (!$enable_tracking) {
            return $content;
        }

        // Patterns to match WhatsApp links and elements
        $patterns = array(
            // WhatsApp web links
            '/(<a[^>]*href=[\'"]*[^\'">]*whatsapp\.com[^\'">]*[\'"]*[^>]*>)/i',
            '/(<a[^>]*href=[\'"]*[^\'">]*wa\.me[^\'">]*[\'"]*[^>]*>)/i',
            // Elements with WhatsApp-related classes or IDs
            '/(<[^>]*(?:class|id)=[\'"]*[^\'">]*whatsapp[^\'">]*[\'"]*[^>]*>)/i',
        );

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, array(__CLASS__, 'add_tracking_attributes'), $content);
        }

        return $content;
    }

    /**
     * Add tracking attributes to matched elements
     */
    public static function add_tracking_attributes($matches)
    {
        $element = $matches[1];

        // Don't add tracking attributes if they already exist
        if (strpos($element, 'data-kepixel-whatsapp') !== false) {
            return $element;
        }

        // Add tracking attribute before the closing >
        $enhanced_element = str_replace('>', ' data-kepixel-whatsapp="true">', $element);

        return $enhanced_element;
    }

    /**
     * Add click handler for WhatsApp elements
     */
    public static function add_whatsapp_click_handler()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add handler if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Track clicks on enhanced WhatsApp elements
                document.addEventListener('click', function(e) {
                    var element = e.target.closest('[data-kepixel-whatsapp="true"]');
                    if (element) {
                        window.kepixelWhatsAppTracking.track(element, {
                            source: 'enhanced_content'
                        });
                    }
                });

                // Original selectors from whatsapp.js for backward compatibility
                var selectors = [
                    '[class*="whatsapp"]',
                    '[id*="whatsapp"]',
                    '[href*="whatsapp"]',
                    '[href*="wa.me"]',
                    'a[href*="whatsapp.com"]',
                    '#ht-ctc-chat'
                ];

                selectors.forEach(function(selector) {
                    document.addEventListener('click', function(e) {
                        if (e.target.matches(selector) || e.target.closest(selector)) {
                            var targetElement = e.target.matches(selector) ? e.target : e.target.closest(selector);
                            window.kepixelWhatsAppTracking.track(targetElement, {
                                source: 'selector_match',
                                selector: selector
                            });
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Get WhatsApp tracking statistics (server-side functionality)
     */
    public static function get_whatsapp_stats()
    {
        // This could be extended to track server-side WhatsApp related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_elements' => 0,
            'enhanced_content' => 0,
        );
    }

    /**
     * Admin hook to display WhatsApp tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about WhatsApp tracking status
    }
}

// Initialize the WhatsApp tracking
add_action('init', array('Kepixel_WhatsApp_Tracking', 'init'));
