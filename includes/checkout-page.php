<?php
defined('ABSPATH') || die();

/**
 * Checkout Tracking Class
 * Handles server-side checkout page tracking functionality
 */
class Kepixel_Checkout_Tracking
{
    /**
     * Initialize Checkout tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_checkout_tracking_script'));
    }

    /**
     * Add Checkout tracking script to head
     */
    public static function add_checkout_tracking_script()
    {
        if (!function_exists('kepixel_is_woocommerce_installed') || !kepixel_is_woocommerce_installed()) {
            return;
        }

        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on checkout pages (not order received page)
        if (!is_checkout() || is_order_received_page()) {
            return;
        }

        // Generate dynamic checkout_id
        $checkout_id = '';
        if (is_user_logged_in()) {
            $checkout_id = 'checkout_user_' . get_current_user_id() . '_' . time();
        } else {
            $checkout_id = 'checkout_guest_' . session_id() . '_' . time();
        }

        // Generate dynamic order_id for checkout started event
        $order_id = 'temp_order_' . time() . '_' . wp_rand(1000, 9999);

        // Get site/store name as affiliation
        $affiliation = get_bloginfo('name') ?: 'Online Store';

        // Get cart totals
        $cart_total = floatval(WC()->cart->total);
        $cart_subtotal = floatval(WC()->cart->subtotal);
        $cart_shipping = floatval(WC()->cart->shipping_total);
        $cart_tax = floatval(WC()->cart->get_taxes_total());
        $cart_discount = floatval(WC()->cart->get_discount_total());

        // Get applied coupons
        $applied_coupons = WC()->cart->get_applied_coupons();
        $coupon = !empty($applied_coupons) ? $applied_coupons[0] : '';

        // Get currency
        $currency = get_woocommerce_currency();

        $items_data = array();
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

            $items_data[] = array(
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
                // Define checkout data inline since we're generating server-side
                window.wpKepixelCheckoutData = {
                    checkout_id: "<?php echo esc_js($checkout_id); ?>",
                    order_id: "<?php echo esc_js($order_id); ?>",
                    affiliation: "<?php echo esc_js($affiliation); ?>",
                    value: <?php echo $cart_total; ?>,
                    revenue: <?php echo $cart_total; ?>,
                    shipping: <?php echo $cart_shipping; ?>,
                    tax: <?php echo $cart_tax; ?>,
                    discount: <?php echo $cart_discount; ?>,
                    coupon: "<?php echo esc_js($coupon); ?>",
                    currency: "<?php echo esc_js($currency); ?>",
                    step: 1,
                    shipping_method: "Standard",
                    payment_method: "Unknown",
                    products: <?php echo json_encode($items_data); ?>
                };

                const checkoutProducts = Array.isArray(wpKepixelCheckoutData.products) ? wpKepixelCheckoutData.products : [];
                const checkoutContentIds = checkoutProducts.map(product => product.product_id).filter(id => !!id);
                const checkoutContentType = checkoutContentIds.length > 1 ? 'product_group' : 'product';
                const checkoutContentId = checkoutContentIds.length > 1 ? checkoutContentIds : (checkoutContentIds[0] || '');

                let eventName = "Checkout Step Viewed";
                let eventPayload = {
                    checkout_id: wpKepixelCheckoutData.checkout_id || '',
                    step: wpKepixelCheckoutData.step || 1,
                    shipping_method: wpKepixelCheckoutData.shipping_method || '',
                    payment_method: wpKepixelCheckoutData.payment_method || '',
                    content_type: 'checkout',
                    content_id: wpKepixelCheckoutData.checkout_id || '',
                    products: checkoutProducts
                };

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track(eventName, eventPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", eventName, eventPayload]);
                }

                eventName = "Checkout Started";
                eventPayload = {
                    order_id: wpKepixelCheckoutData.order_id || '',
                    affiliation: wpKepixelCheckoutData.affiliation || '',
                    value: wpKepixelCheckoutData.value || 0,
                    revenue: wpKepixelCheckoutData.revenue || 0,
                    shipping: wpKepixelCheckoutData.shipping || 0,
                    tax: wpKepixelCheckoutData.tax || 0,
                    discount: wpKepixelCheckoutData.discount || 0,
                    coupon: wpKepixelCheckoutData.coupon || '',
                    currency: wpKepixelCheckoutData.currency || 'USD',
                    products: checkoutProducts,
                    content_type: checkoutContentIds.length === 0 ? 'product' : checkoutContentType,
                    content_id: checkoutContentIds.length === 0 ? '' : checkoutContentId
                };

                if (checkoutProducts.length === 0) {
                    return;
                }

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track(eventName, eventPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", eventName, eventPayload]);
                }

                eventName = "Begin Checkout";
                const beginCheckoutPayload = {
                    order_id: wpKepixelCheckoutData.order_id || '',
                    affiliation: wpKepixelCheckoutData.affiliation || '',
                    value: wpKepixelCheckoutData.value || 0,
                    revenue: wpKepixelCheckoutData.revenue || 0,
                    shipping: wpKepixelCheckoutData.shipping || 0,
                    tax: wpKepixelCheckoutData.tax || 0,
                    discount: wpKepixelCheckoutData.discount || 0,
                    coupon: wpKepixelCheckoutData.coupon || '',
                    currency: wpKepixelCheckoutData.currency || 'USD',
                    products: checkoutProducts,
                    content_type: checkoutContentIds.length === 0 ? 'product' : checkoutContentType,
                    content_id: checkoutContentIds.length === 0 ? '' : checkoutContentId
                };

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track(eventName, beginCheckoutPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", eventName, beginCheckoutPayload]);
                }
            });
        </script>
        <?php
    }

    /**
     * Get Checkout tracking statistics (server-side functionality)
     */
    public static function get_checkout_stats()
    {
        // This could be extended to track server-side checkout related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_checkouts' => 0,
            'checkout_started_events' => 0,
            'checkout_step_viewed_events' => 0,
        );
    }

    /**
     * Admin hook to display Checkout tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Checkout tracking status
    }
}

// Initialize the Checkout tracking
add_action('init', array('Kepixel_Checkout_Tracking', 'init'));
