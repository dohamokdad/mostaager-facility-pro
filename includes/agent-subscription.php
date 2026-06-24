<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent subscription billing for Mostaager.
 */

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Monthly', 'mostaager-facility-pro'),
        );
    }
    return $schedules;
});

add_action('mostaager_agent_subscription_cron', function () {
    if (!function_exists('ms_get_properties_by_agent')) {
        return;
    }

    $agents = get_users(array('role__in' => array('agent', 'houzez_agent')));
    foreach ($agents as $agent) {
        $properties = ms_get_properties_by_agent($agent->ID);
        if (empty($properties)) {
            continue;
        }

        $monthly_fee = 20.00;
        $invoice_amount = count($properties) * $monthly_fee;

        $invoice_id = ms_create_agent_subscription_invoice($agent->ID, $invoice_amount);
        if ($invoice_id) {
            error_log('Mostaager Agent Subscription created invoice: agent=' . $agent->ID . ' invoice=' . $invoice_id . ' amount=' . $invoice_amount);
        } else {
            error_log('Mostaager Agent Subscription skipped creating duplicate invoice for agent=' . $agent->ID . ' amount=' . $invoice_amount);
        }
    }
});

add_action('init', function () {
    if (!wp_next_scheduled('mostaager_agent_subscription_cron')) {
        wp_schedule_event(time(), 'monthly', 'mostaager_agent_subscription_cron');
    }
});
