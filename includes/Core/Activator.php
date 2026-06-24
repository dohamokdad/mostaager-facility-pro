<?php
namespace MostaagerFacilitiesPro\Core;

if (!defined('ABSPATH')) exit;

class Activator {

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        // Buildings Table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_buildings (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            city VARCHAR(100),
            country VARCHAR(100),
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) $charset_collate;";

        // Invoices Table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            invoice_number VARCHAR(100) NOT NULL UNIQUE,
            building_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            paid_amount DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'EGP',
            status VARCHAR(50) DEFAULT 'pending',
            invoice_type VARCHAR(100),
            due_date DATE,
            PRIMARY KEY(id)
        ) $charset_collate;";

        // Maintenance Table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_maintenance (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            building_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            priority VARCHAR(50) DEFAULT 'medium',
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) $charset_collate;";

        // Transactions Table
        $tables[] = "CREATE TABLE {$wpdb->prefix}mfp_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            gateway VARCHAR(100),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'EGP',
            payment_status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(100),
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) $charset_collate;";

        foreach($tables as $sql){
            dbDelta($sql);
        }
    }
}
