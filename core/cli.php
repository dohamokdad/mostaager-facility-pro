<?php

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Mostaager_Sanity_Check_Command
{
    public function run($args, $assoc_args)
    {
        global $wpdb;

        $commands = array(
            'tables' => array(
                'ms_buildings',
                'ms_units',
                'ms_invoices',
                'ms_notifications',
                'ms_wallet_transactions',
                'ms_user_wallet',
                'ms_maintenance_requests',
            ),
            'functions' => array(
                'ms_get_owner_invoices',
                'ms_get_owner_invoices_count_by_status',
                'ms_get_owner_invoice_scope',
                'ms_invoice_belongs_to_owner',
                'ms_get_owner_revenue_summary',
                'ms_get_owner_overdue_count',
                'ms_get_notifications_by_user',
                'ms_get_unread_notifications_count',
                'ms_mark_notifications_read',
                'ms_get_invoice_by_id',
                'ms_create_woo_order_for_invoice',
            ),
        );

        $results = array();

        foreach ($commands['tables'] as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table));
            $results[] = array(
                'name' => $full_table,
                'type' => 'table',
                'status' => $exists ? 'ok' : 'missing',
            );
        }

        foreach ($commands['functions'] as $function) {
            $results[] = array(
                'name' => $function,
                'type' => 'function',
                'status' => function_exists($function) ? 'ok' : 'missing',
            );
        }

        if (function_exists('is_plugin_active')) {
            $results[] = array(
                'name' => 'woocommerce',
                'type' => 'plugin',
                'status' => is_plugin_active('woocommerce/woocommerce.php') ? 'active' : 'inactive',
            );
        }

        foreach ($results as $result) {
            if ($result['status'] === 'ok' || $result['status'] === 'active') {
                WP_CLI::success("{$result['type']} {$result['name']} is {$result['status']}");
            } else {
                WP_CLI::warning("{$result['type']} {$result['name']} is {$result['status']}");
            }
        }

        $failed = array_filter($results, function ($result) {
            return !in_array($result['status'], array('ok', 'active'), true);
        });

        if (!empty($failed)) {
            WP_CLI::error('Mostaager sanity check failed. Review warnings above.');
        }

        WP_CLI::success('Mostaager sanity check passed.');
    }
}

WP_CLI::add_command('mostaager sanity-check', 'Mostaager_Sanity_Check_Command');
