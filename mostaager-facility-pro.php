<?php
/*
Plugin Name: Mostaager Facility PRO
Plugin URI:  https://ejar-egy.com
Description: نظام إدارة المرافق لقالب Houzez — يتيح للناطور إنشاء طلبات صيانة وتوزيع تكاليفها على الشقق وتحصيل المبالغ عبر فواتير إلكترونية وإدارة محفظة المبنى. يدعم الدفع عبر WooCommerce مع بوابة Telr.
Version:     15.0.0
Author:      Doha Mokdad
Text Domain: mostaager-facility
*/

// Autoload vendor libraries
$autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Define plugin URL constant (if not already defined)
if (!defined('MOSTAGER_PLUGIN_URL')) {
    define('MOSTAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('MOSTAGER_PLUGIN_DIR')) {
    define('MOSTAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Load enhancement classes
require_once MOSTAGER_PLUGIN_DIR . 'includes/class-dashboard-charts.php';
require_once MOSTAGER_PLUGIN_DIR . 'includes/class-invoice-pdf.php';
require_once MOSTAGER_PLUGIN_DIR . 'includes/class-maintenance-api.php';
require_once MOSTAGER_PLUGIN_DIR . 'includes/class-whatsapp-integration.php';
require_once MOSTAGER_PLUGIN_DIR . 'includes/telr-integration.php';

if (!defined('ABSPATH')) {
    exit;
}

define('MOSTAAGER_ENTERPRISE_PATH', plugin_dir_path(__FILE__));
define('MOSTAAGER_ENTERPRISE_URL', plugin_dir_url(__FILE__));

if (!defined('MS_PLUGIN_PATH')) {
    define('MS_PLUGIN_PATH', MOSTAAGER_ENTERPRISE_PATH);
}
if (!defined('MS_PLUGIN_URL')) {
    define('MS_PLUGIN_URL', MOSTAAGER_ENTERPRISE_URL);
}

// Backward-compatible path constant (some installations/versions reference MOSTAAGER_FACILITY_PRO_PATH)
if (!defined('MOSTAAGER_FACILITY_PRO_PATH')) {
    define('MOSTAAGER_FACILITY_PRO_PATH', MOSTAAGER_ENTERPRISE_PATH);
}


register_activation_hook(__FILE__, 'ms_run_installer');
register_deactivation_hook(__FILE__, 'ms_cleanup_on_deactivate');

function ms_run_installer()
{
    if (!function_exists('ms_create_tables')) {
        return;
    }

    ms_create_tables();

    // maintenance tables managed by unified installer (ms_ tables)

    if (function_exists('ms_register_facility_roles')) {
        ms_register_facility_roles();
    }
}

function ms_cleanup_on_deactivate()
{
    if (!function_exists('wp_clear_scheduled_hook')) {
        return;
    }

    wp_clear_scheduled_hook('ms_recurring_maintenance_cron');
    // remove roles if defined
    if (function_exists('ms_remove_facility_roles')) {
        ms_remove_facility_roles();
    }
}

// Legacy mostager_ maintenance table creator removed — using `ms_` tables instead.

require_once MOSTAAGER_ENTERPRISE_PATH . 'core/bootstrap.php';
