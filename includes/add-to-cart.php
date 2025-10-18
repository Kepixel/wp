<?php
defined('ABSPATH') || die();

/**
 * Add To Cart Tracking Class
 * Handles server-side add-to-cart button detection and tracking enhancement
 */
class Kepixel_AddToCart_Tracking
{
    /**
     * Initialize Add To Cart tracking
     */
    public static function init()
    {
        // Only initialize if WooCommerce is available
        if (!function_exists('is_woocommerce')) {
            return;
        }

        add_action('wp_head', array(__CLASS__, 'add_addtocart_tracking_script'));
        add_filter('the_content', array(__CLASS__, 'enhance_addtocart_buttons'));
        add_filter('woocommerce_loop_add_to_cart_link', array(__CLASS__, 'enhance_loop_addtocart_button'), 10, 2);
        add_action('wp_footer', array(__CLASS__, 'add_addtocart_click_handler'));

        // AJAX handlers
        add_action('wp_ajax_kepixel_get_product_data', array(__CLASS__, 'get_product_data'));
        add_action('wp_ajax_nopriv_kepixel_get_product_data', array(__CLASS__, 'get_product_data'));
    }

    /**
     * Add Add To Cart tracking script to head
     */
    public static function add_addtocart_tracking_script()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on WooCommerce pages where add to cart functionality exists
        if (!function_exists('is_woocommerce') || !(is_product() || is_shop() || is_product_category() || is_product_tag())) {
            return;
        }

        // Generate dynamic cart_id
        $cart_id = '';
        if (is_user_logged_in()) {
            $cart_id = 'cart_user_' . get_current_user_id() . '_' . time();
        } else {
            $cart_id = 'cart_guest_' . session_id() . '_' . time();
        }

        // Get currency
        $currency = get_woocommerce_currency();

        ?>
        <script>
            window.wpKepixelAddToCartData = {
                cartId: '<?php echo esc_js($cart_id); ?>',
                currency: '<?php echo esc_js($currency); ?>',
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('kepixel_add_to_cart_nonce'); ?>'
            };

            window.kepixelAddToCartTracking = {
                track: function(productData) {
                    const eventName = "Product Added";
                    const productItem = {
                        product_id: productData.product_id || '',
                        sku: productData.sku || '',
                        category: productData.category || '',
                        name: productData.name || '',
                        brand: productData.brand || '',
                        variant: productData.variant || '',
                        price: parseFloat(productData.price) || 0,
                        quantity: parseInt(productData.quantity) || 1,
                        coupon: productData.coupon || '',
                        position: parseInt(productData.position) || 1,
                        url: productData.url || window.location.href,
                        image_url: productData.image_url || ''
                    };
                    const eventPayload = {
                        cart_id: productData.cart_id || '',
                        product_id: productData.product_id || '',
                        content_type: productData.content_type || 'product',
                        content_id: productData.content_id || productData.product_id || '',
                        sku: productData.sku || '',
                        category: productData.category || '',
                        name: productData.name || '',
                        brand: productData.brand || '',
                        variant: productData.variant || '',
                        price: parseFloat(productData.price) || 0,
                        quantity: parseInt(productData.quantity) || 1,
                        coupon: productData.coupon || '',
                        position: parseInt(productData.position) || 1,
                        url: productData.url || window.location.href,
                        image_url: productData.image_url || '',
                        products: [productItem]
                    };

                    if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                        window.kepixelAnalytics.track(eventName, eventPayload);
                    } else {
                        window.kepixelAnalytics = window.kepixelAnalytics || [];
                        window.kepixelAnalytics.push(["track", eventName, eventPayload]);
                    }
                }
            };
        </script>
        <?php
    }

    /**
     * Enhance add-to-cart buttons in content
     */
    public static function enhance_addtocart_buttons($content)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only enhance buttons if tracking is enabled
        if (!$enable_tracking) {
            return $content;
        }

        // Patterns to match add-to-cart buttons and elements
        $patterns = array(
            // WooCommerce add to cart buttons
            '/(<[^>]*class=[\'"]*[^\'">]*add_to_cart_button[^\'">]*[\'"]*[^>]*>)/i',
            '/(<[^>]*class=[\'"]*[^\'">]*single_add_to_cart_button[^\'">]*[\'"]*[^>]*>)/i',
            // Generic add to cart elements
            '/(<[^>]*(?:class|id)=[\'"]*[^\'">]*add.*cart[^\'">]*[\'"]*[^>]*>)/i',
        );

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, array(__CLASS__, 'add_tracking_attributes'), $content);
        }

        return $content;
    }

    /**
     * Enhance WooCommerce loop add to cart buttons specifically
     */
    public static function enhance_loop_addtocart_button($button_html, $product)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only enhance if tracking is enabled
        if (!$enable_tracking) {
            return $button_html;
        }

        // Don't add tracking attributes if they already exist
        if (strpos($button_html, 'data-kepixel-addtocart') !== false) {
            return $button_html;
        }

        // Add tracking attribute before the closing >
        $enhanced_button = str_replace('>', ' data-kepixel-addtocart="true">', $button_html);

        return $enhanced_button;
    }

    /**
     * Add tracking attributes to matched elements
     */
    public static function add_tracking_attributes($matches)
    {
        $element = $matches[1];

        // Don't add tracking attributes if they already exist
        if (strpos($element, 'data-kepixel-addtocart') !== false) {
            return $element;
        }

        // Add tracking attribute before the closing >
        $enhanced_element = str_replace('>', ' data-kepixel-addtocart="true">', $element);

        return $enhanced_element;
    }

    /**
     * Add click handler for add-to-cart elements
     */
    public static function add_addtocart_click_handler()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add handler if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Only add on WooCommerce pages where add to cart functionality exists
        if (!function_exists('is_woocommerce') || !(is_product() || is_shop() || is_product_category() || is_product_tag())) {
            return;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to get product data and track
                function getProductDataAndTrack(product_id, quantity, variation_id) {
                    if (typeof wpKepixelAddToCartData === 'undefined') {
                        console.warn('Kepixel: Add to cart data not available');
                        return;
                    }

                    // Use fetch API for AJAX requests
                    const formData = new FormData();
                    formData.append('action', 'kepixel_get_product_data');
                    formData.append('product_id', product_id);
                    formData.append('quantity', quantity);
                    formData.append('variation_id', variation_id);
                    formData.append('nonce', wpKepixelAddToCartData.nonce);

                    fetch(wpKepixelAddToCartData.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.kepixelAddToCartTracking.track(data.data);
                        }
                    })
                    .catch(error => {
                        console.error('Kepixel: Error fetching product data:', error);
                    });
                }

                // Handle add to cart button clicks (single product page)
                document.addEventListener('click', function(e) {
                    if (e.target.matches('.single_add_to_cart_button')) {
                        const form = e.target.closest('form.cart');
                        if (form) {
                            const productIdInput = form.querySelector('[name="add-to-cart"]') || form.querySelector('[name="product_id"]');
                            const quantityInput = form.querySelector('[name="quantity"]');
                            const variationIdInput = form.querySelector('[name="variation_id"]');

                            const product_id = productIdInput ? productIdInput.value : null;
                            const quantity = quantityInput ? quantityInput.value : 1;
                            const variation_id = variationIdInput ? variationIdInput.value : 0;

                            if (product_id) {
                                getProductDataAndTrack(product_id, quantity, variation_id);
                            }
                        }
                    }
                });

                // Handle AJAX add to cart (shop/category pages)
                document.addEventListener('click', function(e) {
                    if (e.target.matches('.add_to_cart_button:not(.single_add_to_cart_button)')) {
                        const product_id = e.target.getAttribute('data-product_id');
                        const quantity = e.target.getAttribute('data-quantity') || 1;

                        if (product_id) {
                            getProductDataAndTrack(product_id, quantity, 0);
                        }
                    }
                });

                // Handle enhanced add-to-cart buttons
                document.addEventListener('click', function(e) {
                    if (e.target.matches('[data-kepixel-addtocart="true"]')) {
                        const button = e.target;
                        let product_id = button.getAttribute('data-product_id') || button.getAttribute('data-product-id');
                        let quantity = button.getAttribute('data-quantity') || 1;
                        let variation_id = button.getAttribute('data-variation_id') || button.getAttribute('data-variation-id') || 0;

                        // If we can't get product_id from data attributes, try form elements
                        if (!product_id) {
                            const form = button.closest('form.cart');
                            if (form) {
                                const productIdInput = form.querySelector('[name="add-to-cart"]') || form.querySelector('[name="product_id"]');
                                const quantityInput = form.querySelector('[name="quantity"]');
                                const variationIdInput = form.querySelector('[name="variation_id"]');

                                product_id = productIdInput ? productIdInput.value : product_id;
                                quantity = quantityInput ? quantityInput.value : quantity;
                                variation_id = variationIdInput ? variationIdInput.value : variation_id;
                            }
                        }

                        if (product_id) {
                            getProductDataAndTrack(product_id, quantity, variation_id);
                        }
                    }
                });

                // Handle WooCommerce AJAX add to cart events
                document.body.addEventListener('added_to_cart', function(event) {
                    // Extract button from event detail if available
                    const button = event.detail && event.detail.button ? event.detail.button : null;
                    if (button) {
                        const product_id = button.getAttribute('data-product_id');
                        const quantity = button.getAttribute('data-quantity') || 1;

                        if (product_id) {
                            getProductDataAndTrack(product_id, quantity, 0);
                        }
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler to get product data when added to cart
     */
    public static function get_product_data()
    {
        if (!function_exists('is_woocommerce')) {
            wp_die('WooCommerce not available');
        }

        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'kepixel_add_to_cart_nonce')) {
            wp_die('Security check failed');
        }

        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $variation_id = intval($_POST['variation_id']);

        // Get the product
        $product = wc_get_product($variation_id ? $variation_id : $product_id);

        if (!$product) {
            wp_die('Product not found');
        }

        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_names = array_map(function ($term) {
            return $term->name;
        }, $categories);
        $category = implode(', ', $category_names);

        // Get product image
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

        // Get product brand (if available)
        $brand = '';
        $brand_terms = wp_get_post_terms($product->get_id(), 'product_brand');
        if (!empty($brand_terms)) {
            $brand = $brand_terms[0]->name;
        }

        // Get product variant info (for variable products)
        $variant = '';
        if ($variation_id && $product->is_type('variation')) {
            $attributes = $product->get_variation_attributes();
            $variant_parts = array();
            foreach ($attributes as $attribute_name => $attribute_value) {
                $variant_parts[] = $attribute_value;
            }
            $variant = implode(', ', $variant_parts);
        }

        // Get applied coupon (if any)
        $coupon = '';
        if (function_exists('WC') && WC()->cart) {
            $applied_coupons = WC()->cart->get_applied_coupons();
            if (!empty($applied_coupons)) {
                $coupon = $applied_coupons[0];
            }
        }

        // Calculate position (current cart count + 1)
        $position = 1;
        if (function_exists('WC') && WC()->cart) {
            $position = WC()->cart->get_cart_contents_count() + 1;
        }

        // Generate dynamic cart_id
        $cart_id = '';
        if (is_user_logged_in()) {
            $cart_id = 'cart_user_' . get_current_user_id() . '_' . time();
        } else {
            $cart_id = 'cart_guest_' . session_id() . '_' . time();
        }

        $response = array(
            'cart_id' => $cart_id,
            'product_id' => strval($product->get_id()),
            'content_type' => 'product',
            'content_id' => strval($product->get_id()),
            'sku' => $product->get_sku() ?: '',
            'category' => $category,
            'name' => $product->get_name(),
            'brand' => $brand,
            'variant' => $variant,
            'price' => floatval($product->get_price()),
            'quantity' => $quantity,
            'coupon' => $coupon,
            'position' => $position,
            'url' => get_permalink($product->get_id()),
            'image_url' => $image_url ?: '',
            'products' => array(
                array(
                    'product_id' => strval($product->get_id()),
                    'sku' => $product->get_sku() ?: '',
                    'category' => $category,
                    'name' => $product->get_name(),
                    'brand' => $brand,
                    'variant' => $variant,
                    'price' => floatval($product->get_price()),
                    'quantity' => $quantity,
                    'coupon' => $coupon,
                    'position' => $position,
                    'url' => get_permalink($product->get_id()),
                    'image_url' => $image_url ?: ''
                )
            )
        );

        wp_send_json_success($response);
    }

    /**
     * Get Add To Cart tracking statistics (server-side functionality)
     */
    public static function get_addtocart_stats()
    {
        // This could be extended to track server-side add-to-cart related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_buttons' => 0,
            'enhanced_content' => 0,
        );
    }

    /**
     * Admin hook to display Add To Cart tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Add To Cart tracking status
    }
}

// Initialize the Add To Cart tracking
add_action('init', array('Kepixel_AddToCart_Tracking', 'init'));
