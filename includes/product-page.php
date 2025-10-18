<?php
defined('ABSPATH') || die();

/**
 * Product Tracking Class
 * Handles server-side product page tracking functionality
 */
class Kepixel_Product_Tracking
{
    /**
     * Initialize Product tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_product_tracking_script'));
    }

    /**
     * Add Product tracking script to head
     */
    public static function add_product_tracking_script()
    {
        if (!function_exists('kepixel_is_woocommerce_installed') || !kepixel_is_woocommerce_installed()) {
            return;
        }

        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on product pages
        if (!is_product()) {
            return;
        }

        global $product;

        if (!$product) {
            return;
        }

        $sku = $product->get_sku();
        $name = $product->get_name();
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_names = array_map(function ($term) {
            return $term->name;
        }, $categories);
        $category_list = implode(', ', $category_names);
        $price = $product->get_price();

        // Get product image
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

        // Get product URL
        $product_url = get_permalink($product->get_id());

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Define product data inline since we're generating server-side
                window.wpKepixelProductData = {
                    product_id: "<?php echo esc_js($product->get_id()); ?>",
                    sku: "<?php echo esc_js($sku); ?>",
                    name: "<?php echo esc_js($name); ?>",
                    categoryList: "<?php echo esc_js($category_list); ?>",
                    price: "<?php echo esc_js($price); ?>",
                    quantity: 1,
                    currency: "<?php echo get_woocommerce_currency(); ?>",
                    position: 1
                };

                // Get current page URL and product image
                let currentUrl = window.location.href;
                let productImage = "<?php echo esc_js($image_url); ?>";

                // Try to get the product image from the page if not available from server
                if (!productImage) {
                    const imgElement = document.querySelector('img.wp-post-image, .woocommerce-product-gallery__image img, .product-image img');
                    if (imgElement) {
                        productImage = imgElement.getAttribute('src') || imgElement.getAttribute('data-src') || '';
                    }
                }

                const productItem = {
                    product_id: wpKepixelProductData.product_id || '',
                    sku: wpKepixelProductData.sku || '',
                    category: wpKepixelProductData.categoryList || '',
                    name: wpKepixelProductData.name || '',
                    price: parseFloat(wpKepixelProductData.price) || 0,
                    quantity: parseInt(wpKepixelProductData.quantity) || 1,
                    currency: wpKepixelProductData.currency || "USD",
                    position: parseInt(wpKepixelProductData.position) || 1,
                    url: currentUrl,
                    image_url: productImage
                };

                let eventName = "Product Viewed";
                let eventPayload = {
                    product_id: wpKepixelProductData.product_id || '',
                    content_type: 'product',
                    content_id: wpKepixelProductData.product_id || '',
                    sku: wpKepixelProductData.sku || '',
                    category: wpKepixelProductData.categoryList || '',
                    name: wpKepixelProductData.name || '',
                    price: parseFloat(wpKepixelProductData.price) || 0,
                    quantity: parseInt(wpKepixelProductData.quantity) || 1,
                    currency: wpKepixelProductData.currency || "USD",
                    position: parseInt(wpKepixelProductData.position) || 1,
                    url: currentUrl,
                    image_url: productImage,
                    products: [productItem]
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
     * Get Product tracking statistics (server-side functionality)
     */
    public static function get_product_stats()
    {
        // This could be extended to track server-side product related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_products' => 0,
            'product_viewed_events' => 0,
            'product_clicked_events' => 0,
        );
    }

    /**
     * Admin hook to display Product tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Product tracking status
    }
}

// Initialize the Product tracking
add_action('init', array('Kepixel_Product_Tracking', 'init'));
