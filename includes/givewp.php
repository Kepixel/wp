<?php
defined('ABSPATH') || die();

/**
 * GiveWP Donation Tracking Class
 * Handles server-side donation tracking functionality for GiveWP plugin
 *
 * Events tracked following Kepixel SDK eCommerce spec:
 * - Order Completed (donation completion)
 * - Product Viewed (donation form viewed)
 * - Checkout Started (donation initiated)
 */
class Kepixel_GiveWP_Tracking
{
    /**
     * Initialize GiveWP tracking
     */
    public static function init()
    {
        // Only initialize if GiveWP is active
        if (!self::is_givewp_active()) {
            return;
        }

        // Track donation on receipt/confirmation page (client-side)
        add_action('give_payment_receipt_after_table', array(__CLASS__, 'add_donation_tracking_script'), 10, 2);

        // Alternative: Track on the thank you page shortcode
        add_action('give_payment_receipt_after', array(__CLASS__, 'add_donation_tracking_script_alt'), 10, 2);

        // Server-side tracking on donation completion (for webhooks/async payments)
        add_action('give_complete_donation', array(__CLASS__, 'track_donation_server_side'), 10, 1);
    }

    /**
     * Check if GiveWP plugin is active
     *
     * @return bool
     */
    public static function is_givewp_active()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('give/give.php');
    }

    /**
     * Add Donation tracking script to receipt page
     *
     * @param object $donation The donation object
     * @param array $receipt_args Receipt arguments
     */
    public static function add_donation_tracking_script($donation, $receipt_args)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        // Get the payment/donation object
        if (is_object($donation) && isset($donation->ID)) {
            $payment_id = $donation->ID;
        } elseif (is_numeric($donation)) {
            $payment_id = $donation;
        } else {
            return;
        }

        // Check if we've already tracked this donation in this page load
        static $tracked_donations = array();
        if (in_array($payment_id, $tracked_donations)) {
            return;
        }
        $tracked_donations[] = $payment_id;

        // Get the Give_Payment object
        $payment = new Give_Payment($payment_id);

        if (!$payment || !$payment->ID) {
            return;
        }

        // Only track completed donations
        if ($payment->status !== 'publish' && $payment->post_status !== 'publish') {
            return;
        }

        self::output_tracking_script($payment);
    }

    /**
     * Alternative hook for tracking script
     *
     * @param object $donation The donation object
     * @param array $receipt_args Receipt arguments
     */
    public static function add_donation_tracking_script_alt($donation, $receipt_args)
    {
        // This is a fallback - the main hook should handle most cases
        self::add_donation_tracking_script($donation, $receipt_args);
    }

    /**
     * Output the tracking JavaScript following Kepixel SDK eCommerce spec
     *
     * @param Give_Payment $payment The payment object
     */
    private static function output_tracking_script($payment)
    {
        // Get site name as affiliation
        $affiliation = get_bloginfo('name') ?: 'Donation Site';

        // Get form/campaign details
        $form_id = $payment->form_id;
        $form_title = $payment->form_title ?: get_the_title($form_id);

        // Get donation amount details
        $total = floatval($payment->total);
        $currency = $payment->currency ?: give_get_currency();

        // Get donor information
        $donor_email = $payment->email;
        $donor_first_name = $payment->first_name;
        $donor_last_name = $payment->last_name;
        $donor_name = trim($donor_first_name . ' ' . $donor_last_name);

        // Get donor billing address
        $payment_id = $payment->ID;
        $billing_address = array();

        // Get billing address from payment meta
        $address1 = give_get_meta($payment_id, '_give_donor_billing_address1', true);
        $address2 = give_get_meta($payment_id, '_give_donor_billing_address2', true);
        $city = give_get_meta($payment_id, '_give_donor_billing_city', true);
        $state = give_get_meta($payment_id, '_give_donor_billing_state', true);
        $zip = give_get_meta($payment_id, '_give_donor_billing_zip', true);
        $country = give_get_meta($payment_id, '_give_donor_billing_country', true);

        // Build billing_address object following SDK spec
        $has_address = !empty($address1) || !empty($city) || !empty($state) || !empty($zip) || !empty($country);
        if ($has_address || !empty($donor_name)) {
            $billing_address['name'] = $donor_name;

            if (!empty($address1)) {
                $street = $address1;
                if (!empty($address2)) {
                    $street .= ', ' . $address2;
                }
                $billing_address['street'] = $street;
            }
            if (!empty($city)) {
                $billing_address['city'] = $city;
            }
            if (!empty($state)) {
                $billing_address['state'] = $state;
            }
            if (!empty($zip)) {
                $billing_address['postal_code'] = $zip;
            }
            if (!empty($country)) {
                $billing_address['country'] = $country;
            }
        }

        // Get donor phone if available
        $donor_phone = give_get_meta($payment_id, '_give_payment_donor_phone', true);
        if (empty($donor_phone)) {
            $donor_phone = give_get_meta($payment_id, 'give_phone', true);
        }

        // Get payment gateway
        $gateway = $payment->gateway;
        $gateway_label = give_get_gateway_checkout_label($gateway) ?: $gateway;

        // Get donation type (one-time or recurring)
        $donation_type = 'one-time';
        if (function_exists('give_recurring_is_parent_donation') && give_recurring_is_parent_donation($payment->ID)) {
            $donation_type = 'recurring';
        }

        // Get price/level ID if multi-level form
        $price_id = $payment->price_id;
        $level_title = '';
        if ($price_id !== '' && $price_id !== null) {
            $level_title = give_get_price_option_name($form_id, $price_id);
        }

        // Get form categories if available
        $categories = wp_get_post_terms($form_id, 'give_forms_category', array('fields' => 'names'));
        $category = !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : 'Donations';

        // Get form image
        $form_image = get_the_post_thumbnail_url($form_id, 'full') ?: '';

        // Get form URL
        $form_url = get_permalink($form_id) ?: '';

        // Build product object following Kepixel SDK spec
        $product = array(
            'product_id' => strval($form_id),
            'sku' => 'donation-' . $form_id,
            'name' => $form_title,
            'price' => $total,
            'currency' => $currency,
            'category' => $category,
            'quantity' => 1,
            'url' => $form_url,
            'image_url' => $form_image,
        );

        // Add variant if level is set
        if (!empty($level_title)) {
            $product['variant'] = $level_title;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Kepixel GiveWP Donation Tracking
                // Following Kepixel SDK eCommerce specification

                // Product data (donation form as product)
                const product = <?php echo json_encode($product); ?>;

                // Order Completed event - following SDK spec
                const orderCompletedPayload = {
                    order_id: "<?php echo esc_js($payment->ID); ?>",
                    affiliation: "<?php echo esc_js($affiliation); ?>",
                    value: <?php echo $total; ?>,
                    revenue: <?php echo $total; ?>,
                    shipping: 0,
                    tax: 0,
                    discount: 0,
                    coupon: "",
                    currency: "<?php echo esc_js($currency); ?>",
                    products: [product],
                    payment_method: "<?php echo esc_js($gateway_label); ?>",
                    <?php if (!empty($billing_address)) { ?>
                    billing_address: <?php echo json_encode($billing_address); ?>,
                    <?php } ?>

                    // Additional donation-specific properties
                    donation_type: "<?php echo esc_js($donation_type); ?>",
                    form_id: "<?php echo esc_js($form_id); ?>",
                    form_title: "<?php echo esc_js($form_title); ?>"
                };

                // Track Order Completed event
                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track("Order Completed", orderCompletedPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", "Order Completed", orderCompletedPayload]);
                }

                // Identify donor if email is available
                <?php if (!empty($donor_email)) { ?>
                const donorTraits = {
                    email: "<?php echo esc_js($donor_email); ?>",
                    <?php if (!empty($donor_first_name)) { ?>firstName: "<?php echo esc_js($donor_first_name); ?>",<?php } ?>
                    <?php if (!empty($donor_last_name)) { ?>lastName: "<?php echo esc_js($donor_last_name); ?>",<?php } ?>
                    <?php if (!empty($donor_name)) { ?>name: "<?php echo esc_js($donor_name); ?>",<?php } ?>
                    <?php if (!empty($donor_phone)) { ?>phone: "<?php echo esc_js($donor_phone); ?>",<?php } ?>
                    <?php if (!empty($billing_address)) { ?>address: <?php echo json_encode($billing_address); ?>,<?php } ?>
                    isDonor: true,
                    lastDonationAmount: <?php echo $total; ?>,
                    lastDonationDate: "<?php echo esc_js($payment->date); ?>",
                    lastDonationCurrency: "<?php echo esc_js($currency); ?>",
                    lastDonationForm: "<?php echo esc_js($form_title); ?>"
                };

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.identify === "function") {
                    window.kepixelAnalytics.identify("<?php echo esc_js($donor_email); ?>", donorTraits);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["identify", "<?php echo esc_js($donor_email); ?>", donorTraits]);
                }
                <?php } ?>
            });
        </script>
        <?php
    }

    /**
     * Server-side tracking for donation completion
     * This handles async payments (Stripe webhooks, PayPal IPN, etc.)
     *
     * @param int $payment_id The payment/donation ID
     */
    public static function track_donation_server_side($payment_id)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);
        $write_key = get_option('kepixel_write_key');

        // Only track if enabled and write key is set
        if (!$enable_tracking || empty($write_key)) {
            return;
        }

        // Check if we've already tracked this donation server-side
        $already_tracked = get_post_meta($payment_id, '_kepixel_server_tracked', true);
        if ($already_tracked) {
            return;
        }

        $payment = new Give_Payment($payment_id);

        if (!$payment || !$payment->ID) {
            return;
        }

        $currency = $payment->currency ?: give_get_currency();
        $form_id = $payment->form_id;
        $form_title = $payment->form_title ?: get_the_title($form_id);
        $total = floatval($payment->total);

        // Get donor name
        $donor_name = trim($payment->first_name . ' ' . $payment->last_name);

        // Get billing address
        $billing_address = array();
        $address1 = give_get_meta($payment_id, '_give_donor_billing_address1', true);
        $address2 = give_get_meta($payment_id, '_give_donor_billing_address2', true);
        $city = give_get_meta($payment_id, '_give_donor_billing_city', true);
        $state = give_get_meta($payment_id, '_give_donor_billing_state', true);
        $zip = give_get_meta($payment_id, '_give_donor_billing_zip', true);
        $country = give_get_meta($payment_id, '_give_donor_billing_country', true);

        $has_address = !empty($address1) || !empty($city) || !empty($state) || !empty($zip) || !empty($country);
        if ($has_address || !empty($donor_name)) {
            $billing_address['name'] = $donor_name;
            if (!empty($address1)) {
                $street = $address1;
                if (!empty($address2)) {
                    $street .= ', ' . $address2;
                }
                $billing_address['street'] = $street;
            }
            if (!empty($city)) {
                $billing_address['city'] = $city;
            }
            if (!empty($state)) {
                $billing_address['state'] = $state;
            }
            if (!empty($zip)) {
                $billing_address['postal_code'] = $zip;
            }
            if (!empty($country)) {
                $billing_address['country'] = $country;
            }
        }

        // Get donor phone
        $donor_phone = give_get_meta($payment_id, '_give_payment_donor_phone', true);
        if (empty($donor_phone)) {
            $donor_phone = give_get_meta($payment_id, 'give_phone', true);
        }

        // Build event data following SDK spec
        $event_data = array(
            'event' => 'Order Completed',
            'properties' => array(
                'order_id' => strval($payment->ID),
                'affiliation' => get_bloginfo('name') ?: 'Donation Site',
                'value' => $total,
                'revenue' => $total,
                'shipping' => 0,
                'tax' => 0,
                'discount' => 0,
                'coupon' => '',
                'currency' => $currency,
                'payment_method' => give_get_gateway_checkout_label($payment->gateway) ?: $payment->gateway,
                'products' => array(
                    array(
                        'product_id' => strval($form_id),
                        'sku' => 'donation-' . $form_id,
                        'name' => $form_title,
                        'price' => $total,
                        'currency' => $currency,
                        'category' => 'Donations',
                        'quantity' => 1,
                    )
                ),
                'donation_type' => 'one-time',
                'form_id' => strval($form_id),
                'form_title' => $form_title,
            ),
            'userId' => $payment->email,
            'traits' => array(
                'email' => $payment->email,
                'firstName' => $payment->first_name,
                'lastName' => $payment->last_name,
                'name' => $donor_name,
                'isDonor' => true,
            ),
            'timestamp' => date('c', strtotime($payment->date)),
        );

        // Add billing address if available
        if (!empty($billing_address)) {
            $event_data['properties']['billing_address'] = $billing_address;
            $event_data['traits']['address'] = $billing_address;
        }

        // Add phone if available
        if (!empty($donor_phone)) {
            $event_data['traits']['phone'] = $donor_phone;
        }

        // Check for recurring donation
        if (function_exists('give_recurring_is_parent_donation') && give_recurring_is_parent_donation($payment->ID)) {
            $event_data['properties']['donation_type'] = 'recurring';
        }

        // Mark as tracked
        update_post_meta($payment_id, '_kepixel_server_tracked', true);
        update_post_meta($payment_id, '_kepixel_event_data', $event_data);

        /**
         * Action hook for custom server-side tracking
         *
         * @param array $event_data The event data
         * @param int $payment_id The payment ID
         * @param Give_Payment $payment The payment object
         */
        do_action('kepixel_givewp_donation_tracked', $event_data, $payment_id, $payment);
    }
}

/**
 * GiveWP Donation Form Tracking Class
 * Tracks form views and donation initiated events following Kepixel SDK spec
 */
class Kepixel_GiveWP_Form_Tracking
{
    /**
     * Initialize form tracking
     */
    public static function init()
    {
        // Only initialize if GiveWP is active
        if (!Kepixel_GiveWP_Tracking::is_givewp_active()) {
            return;
        }

        // Track form views (Product Viewed event)
        add_action('give_pre_form_output', array(__CLASS__, 'track_form_view'), 10, 1);

        // Track donation form interactions
        add_action('wp_footer', array(__CLASS__, 'add_form_tracking_scripts'));
    }

    /**
     * Track donation form view (Product Viewed event)
     *
     * @param int $form_id The form ID
     */
    public static function track_form_view($form_id)
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        static $tracked_forms = array();
        if (in_array($form_id, $tracked_forms)) {
            return;
        }
        $tracked_forms[] = $form_id;

        $form_title = get_the_title($form_id);
        $form_url = get_permalink($form_id);
        $currency = give_get_currency();

        // Get form categories
        $categories = wp_get_post_terms($form_id, 'give_forms_category', array('fields' => 'names'));
        $category = !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : 'Donations';

        // Get form image
        $form_image = get_the_post_thumbnail_url($form_id, 'full') ?: '';

        // Get default/minimum price
        $default_price = give_get_default_form_amount($form_id);
        $price = floatval($default_price) ?: 0;

        ?>
        <script>
            (function() {
                // Product Viewed event - following SDK spec
                const productViewedPayload = {
                    product_id: "<?php echo esc_js($form_id); ?>",
                    sku: "donation-<?php echo esc_js($form_id); ?>",
                    name: "<?php echo esc_js($form_title); ?>",
                    price: <?php echo $price; ?>,
                    currency: "<?php echo esc_js($currency); ?>",
                    category: "<?php echo esc_js($category); ?>",
                    url: "<?php echo esc_js($form_url); ?>",
                    <?php if (!empty($form_image)) { ?>image_url: "<?php echo esc_js($form_image); ?>",<?php } ?>

                    // Additional donation form properties
                    content_type: "donation_form",
                    form_id: "<?php echo esc_js($form_id); ?>",
                    form_title: "<?php echo esc_js($form_title); ?>"
                };

                if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                    window.kepixelAnalytics.track("Product Viewed", productViewedPayload);
                } else {
                    window.kepixelAnalytics = window.kepixelAnalytics || [];
                    window.kepixelAnalytics.push(["track", "Product Viewed", productViewedPayload]);
                }
            })();
        </script>
        <?php
    }

    /**
     * Add form tracking scripts for checkout started events
     */
    public static function add_form_tracking_scripts()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        $currency = give_get_currency();

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Track when donation form is submitted (Checkout Started event)
                const giveForms = document.querySelectorAll('form.give-form');

                giveForms.forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        const formIdInput = form.querySelector('input[name="give-form-id"]');
                        const amountInput = form.querySelector('input[name="give-amount"]');
                        const formWrap = form.closest('.give-form-wrap');
                        const formTitle = formWrap ? (formWrap.querySelector('.give-form-title')?.textContent || '') : '';

                        if (formIdInput) {
                            const formId = formIdInput.value;
                            const amount = amountInput ? parseFloat(amountInput.value) : 0;

                            // Checkout Started event - following SDK spec
                            const checkoutStartedPayload = {
                                order_id: "pending_" + formId + "_" + Date.now(),
                                value: amount,
                                revenue: amount,
                                shipping: 0,
                                tax: 0,
                                discount: 0,
                                coupon: "",
                                currency: "<?php echo esc_js($currency); ?>",
                                products: [{
                                    product_id: formId,
                                    sku: "donation-" + formId,
                                    name: formTitle.trim() || "Donation Form " + formId,
                                    price: amount,
                                    currency: "<?php echo esc_js($currency); ?>",
                                    category: "Donations",
                                    quantity: 1
                                }],

                                // Additional properties
                                form_id: formId,
                                form_title: formTitle.trim()
                            };

                            if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                                window.kepixelAnalytics.track("Checkout Started", checkoutStartedPayload);
                            } else {
                                window.kepixelAnalytics = window.kepixelAnalytics || [];
                                window.kepixelAnalytics.push(["track", "Checkout Started", checkoutStartedPayload]);
                            }
                        }
                    });
                });

                // Track donation amount selection (optional - for funnel analysis)
                document.querySelectorAll('.give-donation-levels-wrap input, .give-donation-amount input').forEach(function(input) {
                    input.addEventListener('change', function() {
                        const form = this.closest('form.give-form');
                        if (!form) return;

                        const formIdInput = form.querySelector('input[name="give-form-id"]');
                        const amount = parseFloat(this.value) || 0;

                        if (formIdInput && amount > 0) {
                            // Custom event for amount selection (useful for funnel analysis)
                            const amountSelectedPayload = {
                                product_id: formIdInput.value,
                                price: amount,
                                currency: "<?php echo esc_js($currency); ?>",
                                category: "Donations"
                            };

                            if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                                window.kepixelAnalytics.track("Product Clicked", amountSelectedPayload);
                            } else {
                                window.kepixelAnalytics = window.kepixelAnalytics || [];
                                window.kepixelAnalytics.push(["track", "Product Clicked", amountSelectedPayload]);
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }
}

// Initialize GiveWP tracking classes
add_action('init', array('Kepixel_GiveWP_Tracking', 'init'));
add_action('init', array('Kepixel_GiveWP_Form_Tracking', 'init'));
