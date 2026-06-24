<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_plugin_install()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $tables = [];

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_buildings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(191) NOT NULL,
        manager_id BIGINT(20) UNSIGNED DEFAULT 0,
        wp_post_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_units (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        owner_id BIGINT(20) UNSIGNED DEFAULT 0,
        tenant_id BIGINT(20) UNSIGNED DEFAULT 0,
        agent_id BIGINT(20) UNSIGNED DEFAULT 0,
        status VARCHAR(50) DEFAULT 'available',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_unit_tenants (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        unit_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        tenant_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        start_date DATE NULL,
        end_date DATE NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY unit_tenant (unit_id, tenant_id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_invoices (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        expense_id BIGINT(20) UNSIGNED DEFAULT 0,
        description TEXT DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        due_date DATE NULL,
        paid_date DATETIME NULL,
        invoice_type VARCHAR(50) DEFAULT 'rent',
        payer_type VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    // Dedicated maintenance invoices table (optional separate store)
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_maintenance_invoices (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        expense_id BIGINT(20) UNSIGNED DEFAULT 0,
        description TEXT DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        due_date DATE NULL,
        paid_date DATETIME NULL,
        payer_type VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";


    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_wallet_transactions (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        meta TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_user_wallet (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'EGP',

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_building_wallet (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        target_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY building_id (building_id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_building_wallet_transactions (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        type VARCHAR(30) NOT NULL,
        description TEXT NULL,
        reference_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_notifications (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        type VARCHAR(100) DEFAULT '',
        message TEXT,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        related_id BIGINT(20) UNSIGNED DEFAULT 0,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_maintenance_requests (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        facility_id BIGINT(20) UNSIGNED DEFAULT 0,
        title VARCHAR(191) DEFAULT '',
        description TEXT DEFAULT NULL,
        cost DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'open',
        priority VARCHAR(50) DEFAULT 'medium',
        maintenance_type VARCHAR(50) DEFAULT 'emergency',
        is_recurring TINYINT(1) DEFAULT 0,
        recurrence_day TINYINT UNSIGNED DEFAULT 1,
        manager_id BIGINT(20) UNSIGNED DEFAULT 0,
        assigned_to BIGINT(20) UNSIGNED DEFAULT 0,
        payer_type VARCHAR(20) DEFAULT 'owner',
        start_date DATE NULL,
        due_date DATE NULL,
        completed_date DATE NULL,
        wc_order_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_maintenance_comments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        comment TEXT NOT NULL,
        status_change VARCHAR(50) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_maintenance_attachments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT(20) UNSIGNED NOT NULL,
        file_path TEXT NOT NULL,
        file_url TEXT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id)
    ) $charset_collate";

    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_agent_subscriptions (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        agent_id BIGINT(20) UNSIGNED NOT NULL,
        monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) DEFAULT 'active',
        renewal_date DATE NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate";


    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_rent_streaks (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        streak INT(11) NOT NULL DEFAULT 0,
        last_payment_date DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_unit (user_id, unit_id)
    ) $charset_collate";


    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_maintenance_timeline (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id BIGINT(20) UNSIGNED NOT NULL,
        status VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        changed_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate";

    // ── فواتير الخدمات التشغيلية (Utility Bills) ──────────────────────────────
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_utility_bills (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        title VARCHAR(191) NOT NULL DEFAULT '',
        bill_type VARCHAR(50) NOT NULL DEFAULT 'utility',
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        billing_period_start DATE NULL,
        billing_period_end DATE NULL,
        distribution_method VARCHAR(20) NOT NULL DEFAULT 'equal',
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY status (status)
    ) $charset_collate";

    // ── بنود توزيع فاتورة الخدمات على الوحدات ─────────────────────────────────
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_utility_bill_items (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        bill_id BIGINT(20) UNSIGNED NOT NULL,
        unit_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        tenant_id BIGINT(20) UNSIGNED DEFAULT 0,
        owner_id BIGINT(20) UNSIGNED DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        invoice_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY bill_id (bill_id),
        KEY unit_id (unit_id),
        KEY status (status)
    ) $charset_collate";

    // ── تقييمات المستأجرين للوحدات (مختلف عن تقييمات الفنيين) ────────────────
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_property_reviews (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        property_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        reviewer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        rating TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        review_text TEXT NULL,
        review_type VARCHAR(50) NOT NULL DEFAULT 'unit_quality',
        is_approved TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY property_id (property_id),
        KEY building_id (building_id),
        KEY reviewer_id (reviewer_id),
        KEY is_approved (is_approved)
    ) $charset_collate";
    // Add ms_wc_invoice_orders per plan
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_wc_invoice_orders (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id BIGINT(20) UNSIGNED NOT NULL,
        wc_order_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY invoice_order (invoice_id, wc_order_id)
    ) $charset_collate";

    // Add ms_report_cache per plan
    $tables[] = "CREATE TABLE {$wpdb->prefix}ms_report_cache (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        report_type VARCHAR(50) NOT NULL,
        entity_id BIGINT(20) UNSIGNED NOT NULL,
        period VARCHAR(20) DEFAULT NULL,
        data LONGTEXT NULL,
        generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY report_entry (report_type, entity_id, period)
    ) $charset_collate";

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    // Explicit migration for ms_rent_streaks to handle index change
    // dbDelta does not remove obsolete indexes, so we need to manually
    // drop the legacy unique key on user_id and add the composite key on (user_id, unit_id)
    $table_name = $wpdb->prefix . 'ms_rent_streaks';
    $table_like = $wpdb->esc_like($table_name);
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_like)) == $table_name;

    if ($table_exists) {
        // Check if legacy unique index on user_id exists
        $legacy_index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s
            AND INDEX_NAME = 'user_id'
            AND NON_UNIQUE = 0",
            $table_name
        ));

        // Drop legacy unique index on user_id if it exists
        if ($legacy_index_exists) {
            $wpdb->query("ALTER TABLE $table_name DROP INDEX user_id");
        }

        // Check if composite unique index on (user_id, unit_id) exists
        $composite_index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s
            AND INDEX_NAME = 'user_unit'
            AND NON_UNIQUE = 0",
            $table_name
        ));

        // Add composite unique index if it doesn't exist
        if (!$composite_index_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY user_unit (user_id, unit_id)");
        }
    }

    // Ensure ms_maintenance_requests has payer_type for compatibility
    $maintenance_requests_table = $wpdb->prefix . 'ms_maintenance_requests';
    $column_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %s
         AND COLUMN_NAME = 'payer_type'",
        $maintenance_requests_table
    ));

    if ($column_exists === '0' || $column_exists === 0) {
        $wpdb->query("ALTER TABLE $maintenance_requests_table ADD COLUMN payer_type VARCHAR(20) DEFAULT 'owner'");
    }

    // Ensure ms_maintenance_requests has facility_id for facility linkage
    $col_fac_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %s
         AND COLUMN_NAME = 'facility_id'",
        $maintenance_requests_table
    ));

    if ($col_fac_exists === '0' || $col_fac_exists === 0) {
        $wpdb->query("ALTER TABLE $maintenance_requests_table ADD COLUMN facility_id BIGINT(20) UNSIGNED DEFAULT 0");
    }

    // Ensure ms_buildings has wp_post_id for linking to WP posts
    $buildings_table = $wpdb->prefix . 'ms_buildings';
    $col_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %s
         AND COLUMN_NAME = 'wp_post_id'",
        $buildings_table
    ));

    if ($col_exists === '0' || $col_exists === 0) {
        $wpdb->query("ALTER TABLE {$buildings_table} ADD COLUMN wp_post_id BIGINT(20) UNSIGNED DEFAULT 0");
    }

    // Ensure ms_buildings.address exists
    $col_addr_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %s
         AND COLUMN_NAME = 'address'",
        $buildings_table
    ));

    if ($col_addr_exists === '0' || $col_addr_exists === 0) {
        $wpdb->query("ALTER TABLE {$buildings_table} ADD COLUMN address TEXT DEFAULT NULL");
    }

    $unit_tenants_table = $wpdb->prefix . 'ms_unit_tenants';
    $unit_tenants_like = $wpdb->esc_like($unit_tenants_table);
    $unit_tenants_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $unit_tenants_like)) == $unit_tenants_table;
    if (!$unit_tenants_exists) {
        $sql = "CREATE TABLE {$unit_tenants_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            tenant_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            start_date DATE NULL,
            end_date DATE NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unit_tenant (unit_id, tenant_id)
        ) $charset_collate";
        dbDelta($sql);
    } else {
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'tenant_id'",
            $unit_tenants_table
        ));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$unit_tenants_table} ADD COLUMN tenant_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0");
        }
        $status_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
            $unit_tenants_table
        ));
        if (empty($status_exists)) {
            $wpdb->query("ALTER TABLE {$unit_tenants_table} ADD COLUMN status VARCHAR(50) DEFAULT 'active'");
        }
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'unit_tenant'",
            $unit_tenants_table
        ));
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE {$unit_tenants_table} ADD UNIQUE KEY unit_tenant (unit_id, tenant_id)");
        }
    }

    // Ensure ms_units has unit_number and floor
    $units_table = $wpdb->prefix . 'ms_units';
    $unit_num_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'unit_number'",
        $units_table
    ));
    if (empty($unit_num_exists)) {
        $wpdb->query("ALTER TABLE {$units_table} ADD COLUMN unit_number VARCHAR(50) DEFAULT NULL");
    }
    $unit_floor_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'floor'",
        $units_table
    ));
    if (empty($unit_floor_exists)) {
        $wpdb->query("ALTER TABLE {$units_table} ADD COLUMN floor VARCHAR(20) DEFAULT NULL");
    }

    // Ensure ms_invoices has wc_order_id and invoice_number
    $invoices_table = $wpdb->prefix . 'ms_invoices';
    $wc_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'wc_order_id'",
        $invoices_table
    ));
    if (empty($wc_exists)) {
        $wpdb->query("ALTER TABLE {$invoices_table} ADD COLUMN wc_order_id BIGINT(20) UNSIGNED DEFAULT 0");
    }
    $invnum_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'invoice_number'",
        $invoices_table
    ));
    if (empty($invnum_exists)) {
        $wpdb->query("ALTER TABLE {$invoices_table} ADD COLUMN invoice_number VARCHAR(50) DEFAULT NULL");
    }

    add_option('ms_plugin_installed', time());
}

// Compatibility installer removed — unify on `ms_` tables
