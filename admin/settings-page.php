<?php
if (!defined('ABSPATH')) exit;

/**
 * Mostaager admin settings page: register and render settings
 */
add_action('admin_menu', function () {
    add_options_page('Mostaager Facility', 'Mostaager Facility', 'manage_options', 'mostaager-facility-settings', function () {
        if (!current_user_can('manage_options')) return;
        // Settings saved message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('mostaager_messages', 'mostaager_message', __('Settings Saved', 'mostaager-facility-pro'), 'updated');
        }
        settings_errors('mostaager_messages');
        ?>
        <div class="wrap">
            <h1><?php _e('Mostaager Facility Settings', 'mostaager-facility-pro'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mostaager_settings_group');
                do_settings_sections('mostaager-facility-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    });
});

add_action('admin_init', function () {
    register_setting('mostaager_settings_group', 'ms_default_invoice_type', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('mostaager_settings_group', 'ms_wallet_target_default', array('sanitize_callback' => 'absint'));

    add_settings_section('mostaager_main_section', __('General Settings', 'mostaager-facility-pro'), function () {
        echo '<p>' . __('General settings for Mostaager Facility plugin.', 'mostaager-facility-pro') . '</p>';
    }, 'mostaager-facility-settings');

    add_settings_field('ms_default_invoice_type', __('Default Invoice Type', 'mostaager-facility-pro'), function () {
        $v = esc_attr(get_option('ms_default_invoice_type', 'monthly'));
        echo '<input type="text" name="ms_default_invoice_type" value="' . $v . '" class="regular-text" />';
        echo '<p class="description">' . __('Default invoice type (e.g., monthly, one-time).', 'mostaager-facility-pro') . '</p>';
    }, 'mostaager-facility-settings', 'mostaager_main_section');

    add_settings_field('ms_wallet_target_default', __('Default Wallet Target (Building ID)', 'mostaager-facility-pro'), function () {
        $v = intval(get_option('ms_wallet_target_default', 0));
        echo '<input type="number" name="ms_wallet_target_default" value="' . $v . '" class="small-text" />';
        echo '<p class="description">' . __('Default building id to receive wallet allocations when creating quick invoices.', 'mostaager-facility-pro') . '</p>';
    }, 'mostaager-facility-settings', 'mostaager_main_section');
});
