<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    $args = array(
        'labels' => array(
            'name' => __('Buildings', 'mostaager'),
            'singular_name' => __('Building', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-building'),
    );
    register_post_type('ms_building', $args);

    $args = array(
        'labels' => array(
            'name' => __('Expenses', 'mostaager'),
            'singular_name' => __('Expense', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-expense'),
    );
    register_post_type('ms_expense', $args);

    $args = array(
        'labels' => array(
            'name' => __('Invoices', 'mostaager'),
            'singular_name' => __('Invoice', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-invoice'),
    );
    register_post_type('ms_invoice', $args);

    $args = array(
        'labels' => array(
            'name' => __('Discussions', 'mostaager'),
            'singular_name' => __('Discussion', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'comments', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-discussion'),
    );
    register_post_type('ms_discussion', $args);

    $args = array(
        'labels' => array(
            'name' => __('Transfers', 'mostaager'),
            'singular_name' => __('Transfer', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-transfer'),
    );
    register_post_type('ms_transfer', $args);

    if (!post_type_exists('building')) {
        register_post_type('building', [
            'labels' => [
                'name' => 'المباني',
                'singular_name' => 'مبنى',
                'add_new' => 'إضافة مبنى',
                'add_new_item' => 'إضافة مبنى جديد',
                'edit_item' => 'تعديل المبنى',
                'view_item' => 'عرض المبنى',
                'search_items' => 'بحث في المباني',
                'not_found' => 'لا توجد مباني',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'rewrite' => ['slug' => 'buildings'],
            'menu_icon' => 'dashicons-building',
            'menu_position' => 25,
        ]);
    }

    if (!post_type_exists('expenses')) {
        register_post_type('expenses', [
            'labels' => [
                'name' => 'طلبات الصيانة',
                'singular_name' => 'طلب صيانة',
                'add_new' => 'إضافة طلب',
                'add_new_item' => 'إضافة طلب صيانة جديد',
                'edit_item' => 'تعديل طلب الصيانة',
                'view_item' => 'عرض طلب الصيانة',
                'search_items' => 'بحث في طلبات الصيانة',
                'not_found' => 'لا توجد طلبات صيانة',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'custom-fields'],
            'rewrite' => ['slug' => 'expenses'],
            'menu_icon' => 'dashicons-hammer',
            'menu_position' => 26,
        ]);
    }

    if (!post_type_exists('invoices')) {
        register_post_type('invoices', [
            'labels' => [
                'name' => 'الفواتير',
                'singular_name' => 'فاتورة',
                'add_new' => 'إضافة فاتورة',
                'add_new_item' => 'إضافة فاتورة جديدة',
                'edit_item' => 'تعديل الفاتورة',
                'view_item' => 'عرض الفاتورة',
                'search_items' => 'بحث في الفواتير',
                'not_found' => 'لا توجد فواتير',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'custom-fields'],
            'rewrite' => ['slug' => 'invoices'],
            'menu_icon' => 'dashicons-media-spreadsheet',
            'menu_position' => 27,
        ]);
    }

    if (!post_type_exists('discussions')) {
        register_post_type('discussions', [
            'labels' => [
                'name' => 'المناقشات',
                'singular_name' => 'مناقشة',
                'add_new' => 'إضافة مناقشة',
                'add_new_item' => 'إضافة مناقشة جديدة',
                'edit_item' => 'تعديل المناقشة',
                'view_item' => 'عرض المناقشة',
                'search_items' => 'بحث في المناقشات',
                'not_found' => 'لا توجد مناقشات',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'author', 'comments', 'custom-fields'],
            'rewrite' => ['slug' => 'discussions'],
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 28,
        ]);
    }

    if (!post_type_exists('transfers')) {
        register_post_type('transfers', [
            'labels' => [
                'name' => 'طلبات التحويل',
                'singular_name' => 'طلب تحويل',
                'add_new' => 'إضافة طلب',
                'add_new_item' => 'إضافة طلب تحويل جديد',
                'edit_item' => 'تعديل طلب التحويل',
                'view_item' => 'عرض طلب التحويل',
                'search_items' => 'بحث في طلبات التحويل',
                'not_found' => 'لا توجد طلبات تحويل',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'rewrite' => ['slug' => 'transfers'],
            'menu_icon' => 'dashicons-money',
            'menu_position' => 29,
        ]);
    }
});

