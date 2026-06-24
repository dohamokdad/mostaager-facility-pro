<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ms_get_dashboard_template_path')) {
    function ms_get_dashboard_template_path($relative_path)
    {
        $base_path = defined('MS_PLUGIN_PATH') ? MS_PLUGIN_PATH : rtrim(plugin_dir_path(dirname(__FILE__, 2)), '/\\') . '/';
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        return $base_path . $relative_path;
    }
}

if (!function_exists('ms_load_dashboard_sidebar')) {
    function ms_load_dashboard_sidebar($menu_items = array())
    {
        $sidebar_path = ms_get_dashboard_template_path('templates/partials/sidebar.php');

        if (file_exists($sidebar_path)) {
            // expose the passed menu items to the template as a compatibility variable
            $ms_dashboard_menu_items = $menu_items;
            include $sidebar_path;
            return;
        }

        echo '<aside class="ms-sidebar">';
        echo '<div class="ms-sidebar-title">القائمة</div>';
        echo '<ul class="ms-sidebar-menu">';

        foreach ((array) $menu_items as $item) {
            $href = isset($item['href']) ? esc_url($item['href']) : '#';
            $label = isset($item['label']) ? esc_html($item['label']) : '';
            echo '<li><a href="' . $href . '">' . $label . '</a></li>';
        }

        echo '</ul>';
        echo '</aside>';
    }
}

// Load core components
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/install.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/install.php';
}

// Load helper DB layer if present
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/database.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/database.php';
}

// Load Mostaager DB wrapper and block registration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/blocks.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/blocks.php';
}

// Ensure DB versioned upgrades for existing installs
if (!defined('MOSTAGER_DB_VERSION')) {
    define('MOSTAGER_DB_VERSION', '2.0');
}


$installed_db_version = get_option('mostager_db_version', '');
if ($installed_db_version !== MOSTAGER_DB_VERSION) {
    // Attempt to create/update required tables
    if (function_exists('ms_create_tables')) {
        ms_create_tables();
    }
    // Also run compatibility installer if available
    if (function_exists('mostager_plugin_install_compat')) {
        mostager_plugin_install_compat();
    }
    update_option('mostager_db_version', MOSTAGER_DB_VERSION);
}

// Load includes and integration files
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/functions.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/functions.php';
}
// Migration runner (handles legacy -> ms_ migrations)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-migration-runner.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-migration-runner.php';
}

// WooCommerce invoice bridge
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-wc-invoice-bridge.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-wc-invoice-bridge.php';
}

// Reports engine
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-reports-engine.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-reports-engine.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-map-status.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-map-status.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-ratings.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-ratings.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-search-filter.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-search-filter.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-notifications.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-notifications.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-property-sync.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-property-sync.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-lead-converter.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-lead-converter.php';
}
if (class_exists('MFP_Houzez_Map_Status')) {
    MFP_Houzez_Map_Status::init();
}
if (class_exists('MFP_Houzez_Ratings')) {
    MFP_Houzez_Ratings::init();
}
if (class_exists('MFP_Houzez_Search_Filter')) {
    MFP_Houzez_Search_Filter::init();
}
if (class_exists('MFP_Houzez_Notifications')) {
    MFP_Houzez_Notifications::init();
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/pro-platform.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/pro-platform.php';
}
// Load advanced modules (Phase 5, 6, 7)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/smart-automation.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/smart-automation.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/advanced-financial.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/advanced-financial.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/user-experience.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/user-experience.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApi.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApi.php';
    add_action('rest_api_init', array('MostaagerFacilitiesPro\API\RestApi', 'register_routes'));
}

if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApiV2.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApiV2.php';
    MostaagerFacilitiesPro\API\RestApiV2::init();
}

// Load utility bills REST API
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-utility-bills-api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-utility-bills-api.php';
}

// Load facilities API (including derived status endpoint)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-facilities-api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-facilities-api.php';
}
// Load roles registration (if present)

if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/roles.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/roles.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/shortcodes.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/shortcodes.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/api.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/telr-integration.php')) {
    add_action('plugins_loaded', function() {
        require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/telr-integration.php';
    }, 20);
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-subscription.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-subscription.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/rent-invoices.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/rent-invoices.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/actions.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/actions.php';
}

// load ajax handlers
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/ajax.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/ajax.php';
}

// register plugin post types
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/post-types.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/post-types.php';
}

// admin pages for ms_* tables
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/admin-wallet-report.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/admin-wallet-report.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/admin.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/admin.php';
}

// Load plugin admin settings UI
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'admin/settings-page.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'admin/settings-page.php';
}

// Load dashboards
$dash_path = MOSTAAGER_ENTERPRISE_PATH . 'app/Dashboards/';
if (file_exists($dash_path . 'building-dashboard.php')) {
    require_once $dash_path . 'building-dashboard.php';
}
if (file_exists($dash_path . 'owner-dashboard.php')) {
    require_once $dash_path . 'owner-dashboard.php';
}
if (file_exists($dash_path . 'agent-dashboard.php')) {
    require_once $dash_path . 'agent-dashboard.php';
}
if (file_exists($dash_path . 'rent-dashboard.php')) {
    require_once $dash_path . 'rent-dashboard.php';
}

// Enqueue styles & scripts
add_action('wp_enqueue_scripts', function () {
    $css_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/dashboard.css';
    $js_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/dashboard.js';

    wp_enqueue_style(
        'ms-dashboard',
        MS_PLUGIN_URL . 'assets/css/dashboard.css',
        [],
        file_exists($css_path) ? filemtime($css_path) : '1.0'
    );

    // Design system CSS overrides (load after dashboard to override legacy rules)
    $design_css_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/design-system.css';
    wp_enqueue_style(
        'mostaager-design-system',
        MS_PLUGIN_URL . 'assets/css/design-system.css',
        array('ms-dashboard'),
        file_exists($design_css_path) ? filemtime($design_css_path) : '1.0'
    );

    wp_enqueue_script(
        'mostaager-dashboard-js',
        MOSTAAGER_ENTERPRISE_URL . 'assets/js/dashboard.js',
        [],
        file_exists($js_path) ? filemtime($js_path) : '1.0',
        true
    );
    // dashboard-tabs should load after main dashboard script (lightweight helpers)
    $tabs_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/dashboard-tabs.js';
    if (file_exists($tabs_js)) {
        wp_enqueue_script(
            'mostaager-dashboard-tabs-js',
            MOSTAAGER_ENTERPRISE_URL . 'assets/js/dashboard-tabs.js',
            array('mostaager-dashboard-js'),
            filemtime($tabs_js),
            true
        );
    }
    wp_localize_script('mostaager-dashboard-js', 'MostaagerAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mostaager-ajax-nonce')
    ));
});

if (defined('WP_CLI') && WP_CLI) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/cli.php';
}

// Add monthly cron schedule
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Once Monthly', 'mostaager-facility-pro')
        );
    }
    return $schedules;
});

// Schedule recurring maintenance cron
add_action('init', function () {
    if (!wp_next_scheduled('ms_recurring_maintenance_cron')) {
        wp_schedule_event(time(), 'monthly', 'ms_recurring_maintenance_cron');
    }
});

add_action('ms_recurring_maintenance_cron', 'ms_process_recurring_maintenance');

