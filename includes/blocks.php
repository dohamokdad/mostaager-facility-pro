<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('mostaager-facility-pro/ms-dashboard', array(
        'render_callback' => function ($attributes) {
            if (!is_user_logged_in()) {
                return '<p>يرجى تسجيل الدخول لعرض لوحة Mostaager.</p>';
            }

            $title = isset($attributes['title']) ? sanitize_text_field($attributes['title']) : 'لوحة Mostaager';
            return do_shortcode('[ms_dashboard title="' . esc_attr($title) . '"]');
        },
        'attributes' => array(
            'title' => array(
                'type' => 'string',
                'default' => 'لوحة Mostaager',
            ),
        ),
        'supports' => array(
            'html' => false,
        ),
    ));
});
