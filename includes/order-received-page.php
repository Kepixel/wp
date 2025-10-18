<?php
defined('ABSPATH') || die();

/**
 * Order Received Tracking Class
 * Handles server-side order received page tracking functionality
 */
class Kepixel_OrderReceived_Tracking
{
    /**
     * Initialize Order Received tracking
     */
    public static function init()
    {
        add_action('woocommerce_thankyou', array(__CLASS__, 'add_order_tracking_script'), 999);
    }

    /**
     * Add Order tracking script to head
     */
    public static function add_order_tracking_script($order_id)
    {
        if (!function_exists('kepixel_is_woocommerce_installed') || !kepixel_is_woocommerce_installed()) {
            return;
        }

        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Generate dynamic checkout_id and order_id
        $checkout_id = 'checkout_' . $order->get_id() . '_' . time();
        $order_id_str = strval($order->get_id());

        // Get site/store name as affiliation
        $affiliation = get_bloginfo('name') ?: 'Online Store';

        // Get applied coupons
        $coupons = $order->get_coupon_codes();
        $coupon = !empty($coupons) ? $coupons[0] : '';

        // Calculate discount amount
        $discount_total = floatval($order->get_total_discount());

        // Get payment method
        $payment_method = $order->get_payment_method_title() ?: 'Unknown';

        // Get shipping method
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method = 'Standard';
        if (!empty($shipping_methods)) {
            $first_shipping = reset($shipping_methods);
            $shipping_method = $first_shipping->get_method_title();
        }

        $items_data = array();
        $position = 1;
        foreach ($order->get_items() as $item_id => $item) {
            $_product = $item->get_product();

            if (!$_product) {
                continue;
            }

            $_categories = wp_get_post_terms($_product->get_id(), 'product_cat');
            $category_names = array_map(function ($term) {
                return $term->name;
            }, $_categories);
            $category_name = implode(', ', $category_names);

            // Get product image
            $image_id = $_product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

            $items_data[] = array(
                'product_id' => strval($_product->get_id()),
                'sku' => $_product->get_sku() ?: '',
                'name' => $item->get_name(),
                'price' => floatval($item->get_subtotal() / $item->get_quantity()), // Unit price
                'position' => $position,
                'category' => $category_name,
                'url' => get_permalink($_product->get_id()),
                'image_url' => $image_url ?: ''
            );
            $position++;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Check if order data is available
                window.wpKepixelOrderData = {
                    checkout_id: "<?php echo esc_js($checkout_id); ?>",
                    order_id: "<?php echo esc_js($order_id_str); ?>",
                    affiliation: "<?php echo esc_js($affiliation); ?>",
                    total: <?php echo floatval($order->get_total()); ?>,
                    subtotal: <?php echo floatval($order->get_subtotal()); ?>,
                    revenue: <?php echo floatval($order->get_total()); ?>,
                    shipping: <?php echo floatval($order->get_shipping_total()); ?>,
                    tax: <?php echo floatval($order->get_total_tax()); ?>,
                    discount: <?php echo $discount_total; ?>,
                    coupon: "<?php echo esc_js($coupon); ?>",
                    currency: "<?php echo esc_js($order->get_currency()); ?>",
                    payment_method: "<?php echo esc_js($payment_method); ?>",
                    shipping_method: "<?php echo esc_js($shipping_method); ?>",
                    step: 1,
                    products: <?php echo json_encode($items_data); ?>
                };

                const orderProducts = Array.isArray(wpKepixelOrderData.products) ? wpKepixelOrderData.products : [];
                const orderContentIds = orderProducts.map(product => product.product_id).filter(id => !!id);
                const orderContentType = orderContentIds.length > 1 ? 'product_group' : 'product';
                const orderContentId = orderContentIds.length > 1 ? orderContentIds : (orderContentIds[0] || '');

                if (orderProducts.length > 0) {
                    const orderCompletedPayload = {
                        checkout_id: wpKepixelOrderData.checkout_id || '',
                        order_id: wpKepixelOrderData.order_id || '',
                        affiliation: wpKepixelOrderData.affiliation || '',
                        total: wpKepixelOrderData.total || 0,
                        subtotal: wpKepixelOrderData.subtotal || 0,
                        revenue: wpKepixelOrderData.revenue || 0,
                        shipping: wpKepixelOrderData.shipping || 0,
                        tax: wpKepixelOrderData.tax || 0,
                        discount: wpKepixelOrderData.discount || 0,
                        coupon: wpKepixelOrderData.coupon || '',
                        currency: wpKepixelOrderData.currency || 'USD',
                        products: orderProducts,
                        content_type: orderContentIds.length === 0 ? 'product' : orderContentType,
                        content_id: orderContentIds.length === 0 ? '' : orderContentId
                    };

                    if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                        window.kepixelAnalytics.track("Order Completed", orderCompletedPayload);
                    } else {
                        window.kepixelAnalytics = window.kepixelAnalytics || [];
                        window.kepixelAnalytics.push(["track", "Order Completed", orderCompletedPayload]);
                    }
                }

                const checkoutStepCompletedPayload = {
                    checkout_id: wpKepixelOrderData.checkout_id || '',
                    step: wpKepixelOrderData.step || 1,
                    shipping_method: wpKepixelOrderData.shipping_method || '',
                    payment_method: wpKepixelOrderData.payment_method || '',
                    content_type: 'checkout',
                    content_id: wpKepixelOrderData.checkout_id || '',
                    products: orderProducts
                };

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track("Checkout Step Completed", checkoutStepCompletedPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", "Checkout Step Completed", checkoutStepCompletedPayload]);
                }
            });
        </script>
        <?php
    }

    /**
     * Get Order Received tracking statistics (server-side functionality)
     */
    public static function get_order_received_stats()
    {
        // This could be extended to track server-side order received related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_orders' => 0,
            'order_completed_events' => 0,
            'checkout_step_completed_events' => 0,
        );
    }

    /**
     * Admin hook to display Order Received tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Order Received tracking status
    }
}

// Initialize the Order Received tracking
add_action('init', array('Kepixel_OrderReceived_Tracking', 'init'));
