<?php
defined('ABSPATH') || die();

/**
 * Add Kepixel tracking code to the site
 */
function kepixel_add_tracking_code()
{
    $write_key = get_option('kepixel_write_key');
    $enable_tracking = get_option('kepixel_enable_tracking', true);

    // Only add tracking code if Write Key is set and tracking is enabled
    if (empty($write_key) || !$enable_tracking) {
        return;
    }
    ?>

    <script src="https://anubis.kepixel.com?writeKey=<?php echo esc_js($write_key); ?>"></script>

    <!-- Kepixel -->
    <script>
        var kepixelAnalytics = window.kepixelAnalytics = window.kepixelAnalytics || [];
        function extractPhoneNumberFlexible(traits) {
            if (traits && typeof traits === 'object') {
                const email = traits.email || '';
                const username = traits.username || '';
                const phoneRegex = /\+\d{7,15}/;
                const emailMatch = email.match(phoneRegex);
                if (emailMatch) {
                    return emailMatch[0];
                }
                const usernameMatch = username.match(phoneRegex);
                if (usernameMatch) {
                    return usernameMatch[0];
                }
                if (/^\d{10,15}$/.test(username)) {
                    return username;
                }
            }
            return null;
        }
        <?php
        if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $first_name = $current_user->first_name ?? $current_user->user_firstname ?? $current_user->user_nicename ?? $current_user->display_name ?? $current_user->nickname;
        $last_name = $current_user->last_name ?? $current_user->user_lastname;
        $email = $current_user->user_email;
        $username = $current_user->user_login;
        $website = $current_user->user_url;
        $description = $current_user->description;
        $created_at = $current_user->user_registered;

        // Get phone number from user meta (if available)
        $phone = get_user_meta($user_id, 'phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user_id, 'billing_phone', true); // WooCommerce billing phone
        }

        // Get additional user traits from meta
        $age = get_user_meta($user_id, 'age', true);
        $birthday = get_user_meta($user_id, 'birthday', true);
        $gender = get_user_meta($user_id, 'gender', true);
        $title = get_user_meta($user_id, 'title', true);

        // Get avatar URL
        $avatar = get_avatar_url($user_id, array('size' => 256));

        // Enhanced WooCommerce data retrieval if WooCommerce is installed
        $wc_customer = null;
        $is_paying_customer = false;
        $total_spent = 0;
        $order_count = 0;

        if (kepixel_is_woocommerce_installed() && class_exists('WC_Customer')) {
            try {
                $wc_customer = new WC_Customer($user_id);
                $is_paying_customer = $wc_customer->get_is_paying_customer();

                // Get customer statistics if functions exist
                if (function_exists('wc_get_customer_total_spent')) {
                    $total_spent = wc_get_customer_total_spent($user_id);
                }
                if (function_exists('wc_get_customer_order_count')) {
                    $order_count = wc_get_customer_order_count($user_id);
                }
            } catch (Exception $e) {
                // Fallback to regular user meta if WC_Customer fails
                $wc_customer = null;
            }
        }

        // Build address object from WooCommerce billing/shipping data
        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
        $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
        $billing_city = get_user_meta($user_id, 'billing_city', true);
        $billing_state = get_user_meta($user_id, 'billing_state', true);
        $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
        $billing_country = get_user_meta($user_id, 'billing_country', true);

        // Use shipping address as fallback if billing is empty and WooCommerce is available
        if (empty($billing_address_1) && kepixel_is_woocommerce_installed()) {
            $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
            $shipping_address_2 = get_user_meta($user_id, 'shipping_address_2', true);
            $shipping_city = get_user_meta($user_id, 'shipping_city', true);
            $shipping_state = get_user_meta($user_id, 'shipping_state', true);
            $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
            $shipping_country = get_user_meta($user_id, 'shipping_country', true);

            if (!empty($shipping_address_1)) {
                $billing_address_1 = $shipping_address_1;
                $billing_address_2 = $shipping_address_2;
                $billing_city = $shipping_city;
                $billing_state = $shipping_state;
                $billing_postcode = $shipping_postcode;
                $billing_country = $shipping_country;
            }
        }

        $street = trim($billing_address_1 . ' ' . $billing_address_2);

        // Build company object from user meta
        $company_name = get_user_meta($user_id, 'billing_company', true);
        if (empty($company_name)) {
            $company_name = get_user_meta($user_id, 'company_name', true);
        }
        // Add shipping company as fallback if WooCommerce is available
        if (empty($company_name) && kepixel_is_woocommerce_installed()) {
            $company_name = get_user_meta($user_id, 'shipping_company', true);
        }

        $company_id = get_user_meta($user_id, 'company_id', true);
        $company_industry = get_user_meta($user_id, 'company_industry', true);
        $company_employee_count = get_user_meta($user_id, 'company_employee_count', true);
        $company_plan = get_user_meta($user_id, 'company_plan', true);

        // Build full name
        $full_name = trim($first_name . ' ' . $last_name);
        ?>

        // User identification
        const userId = "<?php echo esc_js($user_id); ?>";
        const traits = {
            id: "<?php echo esc_js($user_id); ?>",
            <?php if (!empty($first_name)) { ?>firstName: "<?php echo esc_js($first_name); ?>",<?php } ?>
            <?php if (!empty($last_name)) { ?>lastName: "<?php echo esc_js($last_name); ?>",<?php } ?>
            <?php if (!empty($full_name)) { ?>name: "<?php echo esc_js($full_name); ?>",<?php } ?>
            <?php if (!empty($age)) { ?>age: <?php echo intval($age); ?>,<?php } ?>
            email: "<?php echo esc_js($email); ?>",
            <?php if (!empty($phone)) { ?>phone: "<?php echo esc_js($phone); ?>",<?php } ?>
            <?php if (!empty($street) || !empty($billing_city) || !empty($billing_state) || !empty($billing_postcode) || !empty($billing_country)) { ?>
            address: {
                <?php if (!empty($street)) { ?>street: "<?php echo esc_js($street); ?>",<?php } ?>
                <?php if (!empty($billing_city)) { ?>city: "<?php echo esc_js($billing_city); ?>",<?php } ?>
                <?php if (!empty($billing_state)) { ?>state: "<?php echo esc_js($billing_state); ?>",<?php } ?>
                <?php if (!empty($billing_postcode)) { ?>postalCode: "<?php echo esc_js($billing_postcode); ?>",<?php } ?>
                <?php if (!empty($billing_country)) { ?>country: "<?php echo esc_js($billing_country); ?>"<?php } ?>
            },
            <?php } ?>
            <?php if (!empty($birthday)) { ?>birthday: "<?php echo esc_js($birthday); ?>",<?php } ?>
            <?php if (!empty($company_name) || !empty($company_id) || !empty($company_industry) || !empty($company_employee_count) || !empty($company_plan)) { ?>
            company: {
                <?php if (!empty($company_name)) { ?>name: "<?php echo esc_js($company_name); ?>",<?php } ?>
                <?php if (!empty($company_id)) { ?>id: "<?php echo esc_js($company_id); ?>",<?php } ?>
                <?php if (!empty($company_industry)) { ?>industry: "<?php echo esc_js($company_industry); ?>",<?php } ?>
                <?php if (!empty($company_employee_count)) { ?>employee_count: <?php echo intval($company_employee_count); ?>,<?php } ?>
                <?php if (!empty($company_plan)) { ?>plan: "<?php echo esc_js($company_plan); ?>"<?php } ?>
            },
            <?php } ?>
            <?php if (!empty($created_at)) { ?>createdAt: "<?php echo esc_js($created_at); ?>",<?php } ?>
            <?php if (!empty($description)) { ?>description: "<?php echo esc_js($description); ?>",<?php } ?>
            <?php if (!empty($gender)) { ?>gender: "<?php echo esc_js($gender); ?>",<?php } ?>
            <?php if (!empty($title)) { ?>title: "<?php echo esc_js($title); ?>",<?php } ?>
            <?php if (!empty($username)) { ?>username: "<?php echo esc_js($username); ?>",<?php } ?>
            <?php if (!empty($website)) { ?>website: "<?php echo esc_js($website); ?>",<?php } ?>
            <?php if (!empty($avatar)) { ?>avatar: "<?php echo esc_js($avatar); ?>",<?php } ?>
            <?php if (kepixel_is_woocommerce_installed()) { ?>
            <?php if ($is_paying_customer) { ?>isPayingCustomer: <?php echo $is_paying_customer ? 'true' : 'false'; ?>,<?php } ?>
            <?php if ($total_spent > 0) { ?>totalSpent: <?php echo floatval($total_spent); ?>,<?php } ?>
            <?php if ($order_count > 0) { ?>orderCount: <?php echo intval($order_count); ?><?php } ?>
            <?php } ?>
        };
        if (!traits.phone) {
            const extractedPhone = extractPhoneNumberFlexible(traits);
            if (extractedPhone) {
                traits.phone = extractedPhone;
            }
        }
        const options = {};

        if (window.kepixelAnalytics && typeof window.kepixelAnalytics.identify === "function") {
            window.kepixelAnalytics.identify(userId, traits, options);
        } else {
            window.kepixelAnalytics = window.kepixelAnalytics || [];
            window.kepixelAnalytics.push(["identify", userId, traits, options]);
        }

        <?php
        }
        ?>

        if (window.kepixelAnalytics && typeof window.kepixelAnalytics.page === "function") {
            window.kepixelAnalytics.page();
        } else {
            window.kepixelAnalytics = window.kepixelAnalytics || [];
            window.kepixelAnalytics.push(["page"]);
        }
    </script>
    <!-- End kepixel Code -->
    <?php
}

add_action('wp_head', 'kepixel_add_tracking_code', 10);


require_once plugin_dir_path(__FILE__) . 'whatsapp.php'; // Include whatsapp tracking class
require_once plugin_dir_path(__FILE__) . 'add-to-cart.php'; // Include add-to-cart tracking class
require_once plugin_dir_path(__FILE__) . 'forms.php'; // Include forms tracking class
require_once plugin_dir_path(__FILE__) . 'search.php'; // Include search tracking class
require_once plugin_dir_path(__FILE__) . 'registration.php'; // Include registration tracking class
require_once plugin_dir_path(__FILE__) . 'checkout-page.php'; // Include checkout page tracking class
require_once plugin_dir_path(__FILE__) . 'cart-page.php'; // Include cart page tracking class
require_once plugin_dir_path(__FILE__) . 'product-page.php'; // Include product page tracking class
require_once plugin_dir_path(__FILE__) . 'category.php'; // Include category page tracking class
require_once plugin_dir_path(__FILE__) . 'order-received-page.php'; // Include category page tracking class
