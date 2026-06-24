<?php
namespace MostaagerFacilitiesPro\Admin;

if (!defined('ABSPATH')) exit;

class CustomPostTypes {

    public static function init() {
        add_action('init', [__CLASS__, 'register_buildings']);
        add_action('init', [__CLASS__, 'register_invoices']);
    }

    public static function register_buildings() {
        $labels = [
            'name' => __('Buildings', MFP_TEXT_DOMAIN),
            'singular_name' => __('Building', MFP_TEXT_DOMAIN),
        ];
        register_post_type('mfp_building', [
            'labels' => $labels,
            'public' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-admin-multisite',
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_invoices() {
        $labels = [
            'name' => __('Invoices', MFP_TEXT_DOMAIN),
            'singular_name' => __('Invoice', MFP_TEXT_DOMAIN),
        ];
        register_post_type('mfp_invoice', [
            'labels' => $labels,
            'public' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-media-text',
            'supports' => ['title', 'custom-fields'],
            'show_in_rest' => true,
        ]);
    }
}
