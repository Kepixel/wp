<?php
defined('ABSPATH') || die();

/**
 * Cart Tracking Class
 * Handles server-side cart page tracking functionality
 */
class Kepixel_Cart_Tracking
{
    /**
     * Initialize Cart tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_cart_tracking_script'));
    }

    /**
     * Add Cart tracking script to head
     */
    public static function add_cart_tracking_script()
    {
        if (!function_exists('kepixel_is_woocommerce_installed') || !kepixel_is_woocommerce_installed()) {
            return;
        }

        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on cart pages
        if (!is_cart()) {
            return;
        }

        // Generate dynamic cart_id using session or user ID
        $cart_id = '';
        if (is_user_logged_in()) {
            $cart_id = 'cart_user_' . get_current_user_id() . '_' . time();
        } else {
            $cart_id = 'cart_guest_' . session_id() . '_' . time();
        }

        // Get currency
        $currency = get_woocommerce_currency();

        $items = array();
        $position = 1;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            if (!is_a($_product, 'WC_Product')) continue;

            // Get product categories
            $_categories = wp_get_post_terms($_product->get_id(), 'product_cat');
            $category_names = array_map(function ($term) {
                return $term->name;
            }, $_categories);
            $_category_name = implode(', ', $category_names);

            // Get product image
            $image_id = $_product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

            $items[] = array(
                'product_id' => strval($_product->get_id()),
                'sku' => $_product->get_sku() ?: '',
                'name' => $_product->get_name(),
                'price' => floatval($_product->get_price()),
                'position' => $position,
                'category' => $_category_name,
                'url' => get_permalink($_product->get_id()),
                'image_url' => $image_url ?: ''
            );
            $position++;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Check if cart data is available
                window.wpKepixelCartData = {
                    cartId: "<?php echo esc_js($cart_id); ?>",
                    currency: "<?php echo esc_js($currency); ?>",
                    products: <?php echo json_encode($items); ?>
                };

                const cartProducts = Array.isArray(wpKepixelCartData.products) ? wpKepixelCartData.products : [];
                const cartContentIds = cartProducts.map(product => product.product_id).filter(id => !!id);
                const cartContentType = cartContentIds.length > 1 ? 'product_group' : 'product';
                const cartContentId = cartContentIds.length > 1 ? cartContentIds : (cartContentIds[0] || '');

                const eventName = "Cart Viewed";
                const eventPayload = {
                    cart_id: wpKepixelCartData.cartId || '',
                    currency: wpKepixelCartData.currency || 'USD',
                    products: cartProducts,
                    content_type: cartContentIds.length === 0 ? 'product' : cartContentType,
                    content_id: cartContentIds.length === 0 ? '' : cartContentId
                };

                if (cartProducts.length === 0) {
                    return;
                }

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
     * Get Cart tracking statistics (server-side functionality)
     */
    public static function get_cart_stats()
    {
        // This could be extended to track server-side cart related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_carts' => 0,
            'cart_viewed_events' => 0,
        );
    }

    /**
     * Admin hook to display Cart tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Cart tracking status
    }
}

// Initialize the Cart tracking
add_action('init', array('Kepixel_Cart_Tracking', 'init'));
