<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rent invoice helpers for Mostaager.
 */

function ms_get_rent_invoice_payment_link($invoice_id)
{
    return ms_create_woo_order_for_invoice($invoice_id);
}

function ms_create_rent_invoice_if_needed($user_id, $amount)
{
    if (empty($user_id) || floatval($amount) <= 0) {
        return false;
    }

    return ms_create_agent_subscription_invoice($user_id, $amount);
}
