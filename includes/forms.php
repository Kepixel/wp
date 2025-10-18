<?php
defined('ABSPATH') || die();

/**
 * Forms Tracking Class
 * Handles server-side forms detection and tracking enhancement
 */
class Kepixel_Forms_Tracking
{
    /**
     * Initialize Forms tracking
     */
    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'add_forms_tracking_script'));
    }

    /**
     * Add Forms tracking script to head
     */
    public static function add_forms_tracking_script()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        // Only add tracking if tracking is enabled
        if (!$enable_tracking) {
            return;
        }

        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function prepare_cf7_data( eventdata ) {
                    let cf7data = {
                        formid: '(not set)',
                        inputs: []
                    }

                    if ( eventdata && eventdata.detail && eventdata.detail.contactFormId ) {
                        cf7data.formid = eventdata.detail.contactFormId;
                    }

                    if ( event && event.detail && event.detail.inputs ) {
                        cf7data.inputs = event.detail.inputs;
                    }

                    return cf7data;
                }

                /**
                 * Contact Form 7 DOM and Google Tag Manager for WordPress data layer event pairs
                 * @see https://contactform7.com/dom-events/
                 * @const
                 * @type {Object}
                 */
                const event_pairs = {
                    // wpcf7invalid: 'Form Invalid Input',
                    // wpcf7spam: 'Form Spam Detected',
                    // wpcf7mailsent: 'Form Mail Sent',
                    // wpcf7mailfailed: 'Form Mail Failed',
                    wpcf7submit: 'Form Submitted',
                };

                /**
                 * Handle Contact Form 7 DOM events
                 * If CTF7 event fired push a data layer event with form data(id, inputs)
                 * @param {Object} w Window
                 * @param {Object} d Document
                 * @param {Object} p CTF7 - kepixel event pairs
                 * @return void
                 */
                (function ( w, d, p ) {
                    for ( let ctf7event in p ) {
                        d.addEventListener( ctf7event, function( event ) {
                            const cf7data = prepare_cf7_data( event );

                            if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                                window.kepixelAnalytics.track(p[ ctf7event ], {
                                    'formid': cf7data.formid,
                                    'inputs': cf7data.inputs
                                });
                            } else {
                                window.kepixelAnalytics = window.kepixelAnalytics || [];
                                window.kepixelAnalytics.push(["track", p[ ctf7event ], {
                                    'formid': cf7data.formid,
                                    'inputs': cf7data.inputs
                                }]);
                            }
                        });
                    }
                }( window, document, event_pairs ));

                (function () {
                    // init buffer if SDK not loaded yet
                    window.kepixelAnalytics = window.kepixelAnalytics || []

                    function serializeForm(form) {
                        const fd = new FormData(form)
                        const data = {}
                        fd.forEach((v, k) => {
                            if (!k.startsWith('_') && v !== '') data[k] = v
                        })
                        return data
                    }

                    function formMeta(form) {
                        return {
                            form_id: form.querySelector('input[name="_wpcf7"]')?.value || '',
                            form_unit_tag: form.querySelector('input[name="_wpcf7_unit_tag"]')?.value || '',
                            form_locale: form.querySelector('input[name="_wpcf7_locale"]')?.value || '',
                            form_label: form.getAttribute('aria-label') || form.id || 'wpcf7',
                            action: form.getAttribute('action') || '',
                            url: window.location.href,
                            title: document.title
                        }
                    }

                    function pushFormSubmitted(form, status) {
                        const properties = {
                            status,                              // mail_sent, validation_failed, mail_failed, spam, etc
                            ...formMeta(form),
                            fields: serializeForm(form)
                        }

                        if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                            window.kepixelAnalytics.track('Form Submitted', properties);
                        } else {
                            window.kepixelAnalytics = window.kepixelAnalytics || [];
                            window.kepixelAnalytics.push(["track", 'Form Submitted', properties]);
                        }
                    }

                    // fires after CF7 handles submission
                    document.addEventListener('wpcf7submit', function (e) {
                        const status = e.detail?.status || 'submitted'
                        pushFormSubmitted(e.target, status)
                    }, false)

                    // optional early click capture
                    document.addEventListener('click', function (e) {
                        const btn = e.target.closest('input.wpcf7-submit, button.wpcf7-submit')
                        if (!btn) return
                        const form = btn.closest('form.wpcf7-form')
                        if (!form) return
                        pushFormSubmitted(form, 'click')
                    }, true)
                })()

                // Handle Elementor form submissions with pure JavaScript
                function serializeArray(form) {
                    const formData = new FormData(form);
                    const serialized = [];
                    for (let [name, value] of formData.entries()) {
                        serialized.push({ name: name, value: value });
                    }
                    return serialized;
                }

                document.addEventListener("submit", function(e) {
                    if (e.target.matches("form.elementor-form")) {
                        e.preventDefault(); // stop reload

                        let formData = serializeArray(e.target);

                        if (window.kepixelAnalytics && typeof window.kepixelAnalytics.track === "function") {
                            window.kepixelAnalytics.track("Form Submitted", formData);
                        } else {
                            window.kepixelAnalytics = window.kepixelAnalytics || [];
                            window.kepixelAnalytics.push(["track", "Form Submitted", formData]);
                        }

                        // do not call this.submit()
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Get Forms tracking statistics (server-side functionality)
     */
    public static function get_forms_stats()
    {
        // This could be extended to track server-side forms related data
        // For now, it's a placeholder for future server-side analytics
        return array(
            'tracked_forms' => 0,
            'enhanced_content' => 0,
        );
    }

    /**
     * Admin hook to display Forms tracking status
     */
    public static function admin_notices()
    {
        $enable_tracking = get_option('kepixel_enable_tracking', true);

        if (!$enable_tracking) {
            return;
        }

        // Could add admin notices about Forms tracking status
    }
}

// Initialize the Forms tracking
add_action('init', array('Kepixel_Forms_Tracking', 'init'));
