<?php
if (!defined('ABSPATH')) exit;

/**
 * Autoloader for Mostaager Facilities Pro
 */
spl_autoload_register(function ($class_name) {
    $namespace = 'MostaagerFacilitiesPro\\';
    if (strpos($class_name, $namespace) === 0) {
        $relative_class = substr($class_name, strlen($namespace));
        $file = MFP_PATH . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize the plugin components
 */
add_action('plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain(MFP_TEXT_DOMAIN, false, dirname(MFP_BASENAME) . '/languages/');

    // Initialize Admin Features
    if (is_admin()) {
        MostaagerFacilitiesPro\Admin\CustomPostTypes::init();
    }

    // Initialize REST API
    MostaagerFacilitiesPro\API\RestApi::init();
});
