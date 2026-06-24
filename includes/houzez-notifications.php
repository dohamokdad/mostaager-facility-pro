<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'ms_enqueue_houzez_bell_assets');
add_action('wp_ajax_ms_get_houzez_notifications', 'ms_ajax_get_houzez_notifications');
add_action('wp_ajax_ms_mark_houzez_notifications_read', 'ms_ajax_mark_houzez_notifications_read');

function ms_enqueue_houzez_bell_assets()
{
    if (is_admin() || !is_user_logged_in()) {
        return;
    }

    $js_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/houzez-bell.js';
    if (!file_exists($js_path)) {
        return;
    }

    wp_enqueue_script(
        'ms-houzez-bell',
        MS_PLUGIN_URL . 'assets/js/houzez-bell.js',
        array(),
        filemtime($js_path),
        true
    );

    wp_localize_script('ms-houzez-bell', 'msHouzezBell', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ms_houzez_notifications'),
        'mark_read_nonce' => wp_create_nonce('ms_mark_houzez_notifications_read'),
        'dashboard_url' => home_url('/'),
    ));
}

function ms_ajax_get_houzez_notifications()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'غير مصرح'), 401);
    }

    $user_id = get_current_user_id();
    if (!function_exists('ms_get_notifications_by_user')) {
        wp_send_json_error(array('message' => 'الخدمة غير متاحة'), 500);
    }

    $notifications = ms_get_notifications_by_user($user_id, 10);
    wp_send_json_success(array('notifications' => $notifications));
}

function ms_ajax_mark_houzez_notifications_read()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'غير مصرح'), 401);
    }

    if (!isset($_POST['notification_id']) || !isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field($_POST['security']), 'ms_mark_houzez_notifications_read')) {
        wp_send_json_error(array('message' => 'فشل التحقق'), 403);
    }

    $notification_id = intval($_POST['notification_id']);
    if (!$notification_id) {
        wp_send_json_error(array('message' => 'معرف إشعار غير صالح'), 400);
    }

    $user_id = get_current_user_id();

    if (function_exists('ms_mark_notification_read')) {
        $success = ms_mark_notification_read($notification_id, $user_id);
    } elseif (function_exists('ms_mark_notifications_read')) {
        $success = ms_mark_notifications_read($notification_id, $user_id);
    } else {
        wp_send_json_error(array('message' => 'وظيفة غير متاحة'), 500);
    }

    if (!$success) {
        wp_send_json_error(array('message' => 'تعذر وضع الإشعار كمقروء'), 500);
    }

    wp_send_json_success(array('notification_id' => $notification_id));
}
