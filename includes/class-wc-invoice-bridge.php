<?php
if (!defined('ABSPATH')) exit;

class MS_WC_Invoice_Bridge
{
    public static function init()
    {
        add_action('woocommerce_payment_complete', [__CLASS__, 'on_payment_complete'], 10, 1);
    }

    public static function on_payment_complete($order_id)
    {
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $invoice_id = intval($order->get_meta('mostaager_invoice_id') ?: $order->get_meta('mostager_invoice_id'));
        if (!$invoice_id) return;

        global $wpdb;
        $inv_table = $wpdb->prefix . 'ms_invoices';
        $wpdb->query($wpdb->prepare("UPDATE $inv_table SET wc_order_id = %d WHERE id = %d", $order_id, $invoice_id));

        // mark invoice paid via canonical helper if not already paid
        $inv = ms_get_invoice_by_id($invoice_id);
        if ($inv && strtolower(trim($inv->status ?? '')) !== 'paid') {
            ms_mark_invoice_paid($invoice_id);
        }

        $bridge_table = $wpdb->prefix . 'ms_wc_invoice_orders';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $bridge_table));
        if ($exists) {
            $already = $wpdb->get_var($wpdb->prepare("SELECT id FROM $bridge_table WHERE invoice_id = %d AND wc_order_id = %d LIMIT 1", $invoice_id, $order_id));
            if (!$already) {
                $wpdb->insert($bridge_table, [
                    'invoice_id' => $invoice_id,
                    'wc_order_id' => $order_id,
                    'created_at' => current_time('mysql'),
                ], ['%d','%d','%s']);
            }
        }
    }
}

// Initialize when WooCommerce is loaded
add_action('plugins_loaded', ['MS_WC_Invoice_Bridge', 'init'], 25);
