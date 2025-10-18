<?php
defined('ABSPATH') || die();

/**
 * Category Tracking Class
 * Handles server-side category page tracking functionality
 */
class Kepixel_Category_Tracking
{
    /**
     * Initialize Category tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_category_tracking_script'));
    }

    /**
     * Add Category tracking script to head
     */
    public static function add_category_tracking_script()
    {
        if (!function_exists('kepixel_is_woocommerce_installed') || !kepixel_is_woocommerce_installed()) {
            return;
        }

        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on product category pages
        if (!is_product_category()) {
            return;
        }

        $category = get_queried_object();
        $category_name = $category->name;
        $category_id = $category->term_id;

        // Get currency
        $currency = get_woocommerce_currency();

        // Generate dynamic list_id
        $list_id = 'category_' . $category_id;

        // Get products in this category
        $products_data = array();

        // Get products for the current category page
        global $wp_query;
        if ($wp_query->have_posts()) {
            $position = 1;
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                global $product;

                if (!$product || !is_a($product, 'WC_Product')) {
                    continue;
                }

                // Get product categories
                $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                $category_names = array_map(function ($term) {
                    return $term->name;
                }, $categories);
                $product_category = implode(', ', $category_names);

                // Get product image
                $image_id = $product->get_image_id();
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

                $products_data[] = array(
                    'product_id' => strval($product->get_id()),
                    'sku' => $product->get_sku() ?: '',
                    'name' => $product->get_name(),
                    'price' => floatval($product->get_price()),
                    'position' => $position,
                    'category' => $product_category,
                    'url' => get_permalink($product->get_id()),
                    'image_url' => $image_url ?: ''
                );

                $position++;
            }
            wp_reset_postdata();
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Check if category data is available
                // Define category data inline since we're generating server-side
                window.wpKepixelCategoryData = {
                    listId: "<?php echo esc_js($list_id); ?>",
                    categoryName: "<?php echo esc_js($category_name); ?>",
                    currency: "<?php echo esc_js($currency); ?>",
                    products: <?php echo json_encode($products_data); ?>
                };

                const categoryProducts = Array.isArray(wpKepixelCategoryData.products) ? wpKepixelCategoryData.products : [];
                const categoryContentIds = categoryProducts.map(product => product.product_id).filter(id => !!id);
                const categoryContentType = categoryContentIds.length > 1 ? 'product_group' : 'product';
                const categoryContentId = categoryContentIds.length > 1 ? categoryContentIds : (categoryContentIds[0] || '');

                const eventName = "Product List Viewed";
                const eventPayload = {
                    list_id: wpKepixelCategoryData.listId || 'list1',
                    category: wpKepixelCategoryData.categoryName || 'Category',
                    currency: wpKepixelCategoryData.currency || 'USD',
                    products: categoryProducts,
                    content_type: categoryContentIds.length === 0 ? 'product' : categoryContentType,
                    content_id: categoryContentIds.length === 0 ? '' : categoryContentId
                };

                if (categoryProducts.length === 0) {
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
     * Get Category tracking statistics (server-side functionality)
     */
    public static function get_category_stats()
    {
        // This could be extended to track server-side category related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_categories' => 0,
            'product_list_viewed_events' => 0,
        );
    }

    /**
     * Admin hook to display Category tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Category tracking status
    }
}

// Initialize the Category tracking
add_action('init', array('Kepixel_Category_Tracking', 'init'));
