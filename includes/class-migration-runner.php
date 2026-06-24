<?php
if (!defined('ABSPATH')) exit;

class MS_Migration_Runner
{
    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'maybe_run_migrations']);
    }

    public static function maybe_run_migrations()
    {
        $current = get_option('ms_migration_version', '0.0');
        if ($current === '2.0') {
            return;
        }
        self::run_migrations();
        update_option('ms_migration_version', '2.0');
        add_option('ms_migration_maintenance_done', time());
    }

    public static function run_migrations()
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Add missing columns to ms_maintenance_requests
        $maint_table = $prefix . 'ms_maintenance_requests';
        $assigned_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'assigned_to'", $maint_table));
        if (empty($assigned_exists)) {
            $wpdb->query("ALTER TABLE {$maint_table} ADD COLUMN assigned_to BIGINT(20) UNSIGNED DEFAULT 0");
        }
        $completed_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'completed_date'", $maint_table));
        if (empty($completed_exists)) {
            $wpdb->query("ALTER TABLE {$maint_table} ADD COLUMN completed_date DATETIME NULL");
        }
        // Ensure wc_order_id exists on maintenance requests for payment linkage
        $wc_col_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'wc_order_id'", $maint_table));
        if (empty($wc_col_exists)) {
            $wpdb->query("ALTER TABLE {$maint_table} ADD COLUMN wc_order_id BIGINT(20) UNSIGNED DEFAULT 0");
        }

        // Add wc_order_id to invoices if missing
        $inv_table = $prefix . 'ms_invoices';
        $wc_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'wc_order_id'", $inv_table));
        if (empty($wc_exists)) {
            $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN wc_order_id BIGINT(20) UNSIGNED DEFAULT 0");
        }

        // Migrate legacy maintenance tables to ms_maintenance_requests if present
        $legacy_tables = array(
            $prefix . 'mostager_maintenance',
            $prefix . 'wp_mostager_maintenance',
            $prefix . 'mostager_maintenance_requests',
        );

        foreach ($legacy_tables as $legacy_maint) {
            $legacy_like = $wpdb->esc_like($legacy_maint);
            $has_legacy = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $legacy_like)) == $legacy_maint;
            if (!$has_legacy) continue;

            $rows = $wpdb->get_results("SELECT * FROM {$legacy_maint} LIMIT 1000");
            if (empty($rows)) continue;

            foreach ($rows as $r) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$maint_table} WHERE title = %s AND created_at = %s", $r->title ?? '', $r->created_at ?? ''));
                if ($exists) continue;

                $wpdb->insert($maint_table, array(
                    'building_id' => intval($r->building_id ?? 0),
                    'unit_id' => intval($r->unit_id ?? 0),
                    'facility_id' => intval($r->facility_id ?? 0),
                    'title' => sanitize_text_field($r->title ?? ''),
                    'description' => isset($r->description) ? $r->description : null,
                    'cost' => floatval($r->cost ?? 0),
                    'status' => $r->status ?? 'open',
                    'priority' => $r->priority ?? 'medium',
                    'maintenance_type' => $r->maintenance_type ?? 'emergency',
                    'is_recurring' => intval($r->is_recurring ?? 0),
                    'manager_id' => intval($r->manager_id ?? 0),
                    'payer_type' => $r->payer_type ?? 'owner',
                    'start_date' => $r->start_date ?? null,
                    'due_date' => $r->due_date ?? null,
                    'completed_date' => $r->completed_date ?? null,
                    'assigned_to' => intval($r->assigned_to ?? 0),
                    'wc_order_id' => intval($r->wc_order_id ?? 0),
                    'created_at' => $r->created_at ?? current_time('mysql'),
                ), array('%d','%d','%d','%s','%s','%f','%s','%s','%s','%d','%d','%s','%s','%d','%d','%s'));
            }
        }

        // Mark migration done
        update_option('ms_migration_last_run', time());
    }
}

MS_Migration_Runner::init();
