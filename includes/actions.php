<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_order_status_completed', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    // Update invoice status if this order was created for a Mostaager invoice.
    if ($invoice_id = $order->get_meta('mostaager_invoice_id')) {
        if (function_exists('ms_mark_invoice_paid')) {
            ms_mark_invoice_paid($invoice_id);

            if (function_exists('ms_get_invoice_by_id') && function_exists('ms_add_notification')) {
                $invoice = ms_get_invoice_by_id($invoice_id);
                if ($invoice && empty($invoice->expense_id)) {
                    global $wpdb;
                    $building_id = intval($invoice->building_id ?? 0);
                    if ($building_id) {
                        $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d LIMIT 1", $building_id));
                        if ($manager_id) {
                            ms_add_notification(intval($manager_id), 'payment_received', sprintf('تم استلام دفعة للفاتورة #%d', $invoice_id), $building_id, $invoice_id);
                        }
                    }
                }
            }
        }
        return;
    }

    // Credit the user wallet for completed orders when applicable.
    if (function_exists('ms_add_user_wallet_balance')) {
        ms_add_user_wallet_balance($user_id, floatval($order->get_total()), 'Order completed');
    }
});

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $months = isset($cart_item['months']) ? intval($cart_item['months']) : 0;
        if ($months >= 3 && isset($cart_item['data']) && is_object($cart_item['data'])) {
            $unit_price = floatval($cart_item['data']->get_price());
            if ($unit_price > 0) {
                $discount_price = ($months * $unit_price) / ($months + 1);
                $cart_item['data']->set_price($discount_price);
            }
        }
    }
});
