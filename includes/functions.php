<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_get_user_primary_role($user_id = 0)
{
    $user = get_userdata($user_id ?: get_current_user_id());
    if (!$user) {
        return '';
    }

    $role_priority = array('owner', 'tenant', 'agent', 'building_manager');
    $roles = (array) $user->roles;

    foreach ($role_priority as $role) {
        if (in_array($role, $roles, true)) {
            return $role;
        }
    }

    return isset($roles[0]) ? $roles[0] : '';
}

function ms_user_has_role($user_id = 0, $role = '')
{
    if (empty($role)) {
        return false;
    }

    $user = get_userdata($user_id ?: get_current_user_id());
    if (!$user) {
        return false;
    }

    return in_array($role, (array) $user->roles, true) || user_can($user, 'manage_options');
}

function ms_user_can_view_dashboard($user_id = 0, $role = '')
{
    return ms_user_has_role($user_id, $role);
}

function ms_get_property_building_id(int $post_id): int
{
    $post_id = absint($post_id);
    if (!$post_id) {
        return 0;
    }

    $building_id = absint(get_post_meta($post_id, 'ms_building_id', true));
    if ($building_id > 0) {
        return $building_id;
    }

    return absint(get_post_meta($post_id, '_ms_building_id', true));
}

function ms_login_redirect_by_role($redirect_to, $requested_redirect_to, $user)
{
    if (!is_a($user, 'WP_User')) {
        return $redirect_to;
    }

    if (ms_user_has_role($user->ID, 'owner')) {
        return home_url('/owner-dashboard/');
    }
    if (ms_user_has_role($user->ID, 'tenant')) {
        return home_url('/rent-dashboard/');
    }
    if (ms_user_has_role($user->ID, 'agent')) {
        return home_url('/agent-dashboard/');
    }
    if (ms_user_has_role($user->ID, 'building_manager')) {
        return home_url('/building-dashboard/');
    }

    return $redirect_to;
}
add_filter('login_redirect', 'ms_login_redirect_by_role', 10, 3);

/**
 * Common helpers for Mostaager Facility Pro plugin.
 */

function ms_get_tenant_units($tenant_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE tenant_id = %d", $tenant_id);
    return $wpdb->get_results($sql);
}

function ms_get_invoice_business_key($invoice)
{
    if (!$invoice || (!is_object($invoice) && !is_array($invoice))) {
        return '';
    }

    $data = (array) $invoice;
    $invoice_number = trim(strval($data['invoice_number'] ?? ''));
    if ($invoice_number !== '') {
        return 'invoice_number:' . $invoice_number;
    }

    $user_id = intval($data['user_id'] ?? $data['payer_id'] ?? 0);
    $building_id = intval($data['building_id'] ?? 0);
    $property_id = intval($data['property_id'] ?? 0);
    $amount = number_format(floatval($data['amount'] ?? $data['amount_due'] ?? 0), 2, '.', '');
    $status = strtolower(trim(strval($data['status'] ?? '')));
    $due_date = trim(strval($data['due_date'] ?? $data['created_at'] ?? ''));
    $invoice_type = strtolower(trim(strval($data['invoice_type'] ?? '')));
    $description = trim(strval($data['description'] ?? ''));

    return implode(':', array(
        'invoice',
        $invoice_type,
        $user_id,
        $building_id,
        $property_id,
        $amount,
        $due_date,
        $status,
        $description,
    ));
}

function ms_get_tenant_invoices($tenant_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY due_date DESC", $tenant_id);
    $invoices = $wpdb->get_results($sql);

    if (!empty($invoices)) {
        foreach ($invoices as $invoice) {
            if (is_object($invoice)) {
                $invoice->source = 'new';
            }
        }
    }

    $legacy_invoices = array();
    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy_invoices = ms_get_legacy_invoices_for_user($tenant_id);
    }

    $all = array_merge($invoices ?: array(), $legacy_invoices ?: array());
    $unique = array();
    $seen_keys = array();
    foreach ($all as $invoice) {
        $invoice_key = ms_get_invoice_business_key($invoice);
        if (!$invoice_key) {
            $invoice_key = 'legacy_id:' . intval($invoice->id ?? $invoice->ID ?? 0);
        }
        if ($invoice_key && isset($seen_keys[$invoice_key])) {
            continue;
        }
        if ($invoice_key) {
            $seen_keys[$invoice_key] = true;
        }
        $unique[] = $invoice;
    }
    $all = $unique;
    usort($all, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? $a->due_date ?? '');
        $b_date = strtotime($b->created_at ?? $b->due_date ?? '');
        return $b_date <=> $a_date;
    });

    return $all;
}

function ms_get_agent_invoices($agent_id, $limit = 50, $invoice_type = 'listing_fee')
{
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return array();
    }

    $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($agent_id) : array();
    if (empty($invoices)) {
        return array();
    }

    if (!empty($invoice_type)) {
        $invoices = array_filter($invoices, function ($invoice) use ($invoice_type) {
            return isset($invoice->invoice_type) && $invoice->invoice_type === $invoice_type;
        });
    }

    return array_slice(array_values($invoices), 0, absint($limit));
}

function ms_get_agent_maintenance_requests($agent_id, $limit = 50)
{
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return array();
    }

    $properties = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($agent_id) : array();
    if (empty($properties) && function_exists('ms_get_properties_by_owner')) {
        $properties = ms_get_properties_by_owner($agent_id);
    }

    $building_ids = array();
    foreach ((array) $properties as $property) {
        $property_id = 0;
        if (is_object($property) && isset($property->ID)) {
            $property_id = absint($property->ID);
        } elseif (is_array($property) && isset($property['ID'])) {
            $property_id = absint($property['ID']);
        } elseif (is_numeric($property)) {
            $property_id = absint($property);
        }

        if (!$property_id) {
            continue;
        }

        $building_id = absint(get_post_meta($property_id, 'building_id', true));
        if ($building_id && !in_array($building_id, $building_ids, true)) {
            $building_ids[] = $building_id;
        }
    }

    if (empty($building_ids)) {
        return array();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    $placeholders = implode(',', array_fill(0, count($building_ids), '%d'));
    $query = "SELECT * FROM $table WHERE building_id IN ($placeholders) ORDER BY created_at DESC LIMIT %d";
    $params = array_merge($building_ids, array(absint($limit)));
    $rows = $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $params)));

    return $rows ?: array();
}

function ms_get_agent_open_maintenance_requests_count($agent_id)
{
    $requests = ms_get_agent_maintenance_requests($agent_id, 200);
    $count = 0;
    foreach ($requests as $request) {
        if (isset($request->status) && $request->status === 'open') {
            $count++;
        }
    }

    return intval($count);
}

function ms_get_agent_invoice_total_due($agent_id, $invoice_type = 'listing_fee')
{
    $invoices = ms_get_agent_invoices($agent_id, 200, $invoice_type);
    $total = 0.0;
    foreach ($invoices as $invoice) {
        if (isset($invoice->amount) && isset($invoice->status) && $invoice->status !== 'paid') {
            $total += floatval($invoice->amount);
        }
    }

    return $total;
}

function ms_extract_meta_id($meta)
{
    if (is_array($meta)) {
        return isset($meta[0]) ? absint($meta[0]) : 0;
    }

    if (is_object($meta) && isset($meta->ID)) {
        return absint($meta->ID);
    }

    return absint($meta);
}

function ms_get_legacy_property_ids_for_user($user_id)
{
    $user_id = absint($user_id);
    if (!$user_id || !post_type_exists('property')) {
        return array();
    }

    $seen = array();

    $author_posts = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'author' => $user_id,
        'fields' => 'ids',
        'posts_per_page' => -1,
    ));

    foreach ($author_posts as $id) {
        $seen[] = absint($id);
    }

    $meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'tenant_id');
    $meta_query = array('relation' => 'OR');
    foreach ($meta_keys as $key) {
        $meta_query[] = array(
            'key' => $key,
            'value' => $user_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
        $meta_query[] = array(
            'key' => $key,
            'value' => '"' . $user_id . '"',
            'compare' => 'LIKE',
        );
    }

    $meta_posts = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => $meta_query,
    ));

    foreach ($meta_posts as $id) {
        $seen[] = absint($id);
    }

    $seen = array_values(array_unique($seen));
    if (!empty($seen)) {
        return $seen;
    }

    $all = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));

    foreach ($all as $id) {
        $raw_owner = get_post_meta($id, 'owner_id', true);
        $raw_property_owner = get_post_meta($id, 'property_owner', true);
        $raw_fave_owner = get_post_meta($id, 'fave_property_owner', true);
        $raw_tenant = get_post_meta($id, 'tenant_id', true);

        $owner = ms_extract_meta_id($raw_owner);
        $property_owner = ms_extract_meta_id($raw_property_owner);
        $fave_owner = ms_extract_meta_id($raw_fave_owner);
        $tenant = ms_extract_meta_id($raw_tenant);

        if (in_array($id, $seen, true)) {
            continue;
        }

        if ($owner === $user_id || $property_owner === $user_id || $fave_owner === $user_id || $tenant === $user_id) {
            $seen[] = absint($id);
        }
    }

    return array_values(array_unique($seen));
}

function ms_get_legacy_building_ids_for_manager($manager_id)
{
    $manager_id = absint($manager_id);
    if (!$manager_id || !post_type_exists('building')) {
        return array();
    }

    $meta_query = array(
        'relation' => 'OR',
        array(
            'key' => 'manager_id',
            'value' => $manager_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ),
        array(
            'key' => 'manager_id',
            'value' => strval($manager_id),
            'compare' => '=',
        ),
        array(
            'key' => 'manager_id',
            'value' => '"' . $manager_id . '"',
            'compare' => 'LIKE',
        ),
    );

    $posts = get_posts(array(
        'post_type' => 'building',
        'post_status' => array('publish', 'pending', 'draft', 'private', 'future', 'expired'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => $meta_query,
    ));

    if (!empty($posts)) {
        return array_values(array_unique(array_map('absint', $posts)));
    }

    $all = get_posts(array(
        'post_type' => 'building',
        'post_status' => array('publish', 'pending', 'draft', 'private', 'future', 'expired'),
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));

    $building_ids = array();
    foreach ($all as $id) {
        $raw = get_post_meta($id, 'manager_id', true);
        if (is_array($raw) && in_array($manager_id, $raw, true)) {
            $building_ids[] = absint($id);
            continue;
        }
        if (is_object($raw) && isset($raw->ID) && absint($raw->ID) === $manager_id) {
            $building_ids[] = absint($id);
            continue;
        }
        if (absint($raw) === $manager_id || strval($raw) === strval($manager_id)) {
            $building_ids[] = absint($id);
        }
    }

    return array_values(array_unique($building_ids));
}

function ms_get_legacy_buildings_for_manager($manager_id)
{
    $building_ids = ms_get_legacy_building_ids_for_manager($manager_id);
    if (empty($building_ids)) {
        return array();
    }

    $buildings = array();
    foreach ($building_ids as $id) {
        $post = get_post($id);
        if (!$post) {
            continue;
        }

        $buildings[] = (object) array(
            'id' => $post->ID,
            'name' => $post->post_title,
            'status' => $post->post_status,
            'post' => $post,
        );
    }

    return $buildings;
}

function ms_get_legacy_property_ids_by_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id || !post_type_exists('property')) {
        return array();
    }

    $meta_query = array(
        'relation' => 'OR',
        array(
            'key' => 'building_id',
            'value' => $building_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ),
        array(
            'key' => 'building_id',
            'value' => '"' . $building_id . '"',
            'compare' => 'LIKE',
        ),
    );

    $posts = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => $meta_query,
    ));

    if (!empty($posts)) {
        return array_values(array_unique(array_map('absint', $posts)));
    }

    $all = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));

    $ids = array();
    foreach ($all as $id) {
        $raw = get_post_meta($id, 'building_id', true);
        if (ms_extract_meta_id($raw) === $building_id) {
            $ids[] = absint($id);
        }
    }

    return array_values(array_unique($ids));
}

function ms_get_legacy_invoice_object($invoice_post)
{
    if (!$invoice_post || !is_object($invoice_post)) {
        return null;
    }

    $id = absint($invoice_post->ID);
    $property_id = ms_extract_meta_id(get_post_meta($id, 'property_id', true));
    $building_id = ms_extract_meta_id(get_post_meta($id, 'building_id', true));
    $amount_due = floatval(get_post_meta($id, 'amount_due', true));
    $status = get_post_meta($id, 'status', true) ?: 'pending';
    $due_date = get_post_meta($id, 'due_date', true) ?: '';
    $description = get_post_meta($id, 'description', true);
    if (empty($description)) {
        $description = get_post_meta($id, 'title', true);
    }
    if (empty($description)) {
        $description = $invoice_post->post_title;
    }

    $contacts = $property_id ? ms_get_property_contacts($property_id) : array();
    $user_id = 0;
    if (!empty($contacts['tenant']) && is_object($contacts['tenant']) && isset($contacts['tenant']->ID)) {
        $user_id = intval($contacts['tenant']->ID);
    } elseif (!empty($contacts['owner']) && is_object($contacts['owner']) && isset($contacts['owner']->ID)) {
        $user_id = intval($contacts['owner']->ID);
    }

    return (object) array(
        'id' => $id,
        'user_id' => $user_id,
        'building_id' => $building_id,
        'property_id' => $property_id,
        'amount' => $amount_due,
        'status' => $status,
        'due_date' => $due_date,
        'description' => $description,
        'invoice_type' => get_post_meta($id, 'invoice_type', true) ?: 'legacy',
        'source' => 'legacy',
        'created_at' => get_post_meta($id, 'created_at', true) ?: $invoice_post->post_date,
    );
}

if (!function_exists('mostaager_update_wallet_balance')) {
    function mostaager_update_wallet_balance($building_id, $amount)
    {
        if (function_exists('ms_update_building_wallet_balance')) {
            return ms_update_building_wallet_balance($building_id, $amount);
        }
        return false;
    }
}

if (!function_exists('mostaager_update_wallet_target')) {
    function mostaager_update_wallet_target($building_id, $amount)
    {
        if (function_exists('ms_update_building_wallet_target')) {
            return ms_update_building_wallet_target($building_id, $amount);
        }
        return false;
    }
}

if (!function_exists('mostaager_get_wallet')) {
    function mostaager_get_wallet($building_id)
    {
        if (function_exists('ms_get_building_wallet')) {
            return ms_get_building_wallet($building_id);
        }
        return null;
    }
}

if (!function_exists('mostaager_generate_invoices_from_expense')) {
    function mostaager_generate_invoices_from_expense($expense_id)
    {
        $building_raw = get_post_meta($expense_id, 'building_id', true);
        if (!$building_raw && function_exists('get_field')) {
            $building_raw = get_field('building_id', $expense_id);
        }

        $building_id = is_object($building_raw) ? $building_raw->ID : intval($building_raw);
        $amount_raw = get_post_meta($expense_id, 'total_amount', true);
        if (!$amount_raw && function_exists('get_field')) {
            $amount_raw = get_field('total_amount', $expense_id);
        }
        $amount = floatval($amount_raw);

        if (!$building_id || !$amount) {
            error_log("Mostaager Error: Building ($building_id) or Amount ($amount) missing for Expense ID $expense_id");
            return false;
        }

        $properties = ms_get_properties_by_building($building_id);
        if (empty($properties)) {
            error_log("Mostaager Error: No properties found for Building ID $building_id");
            return false;
        }

        $count = count($properties);
        $per_unit = round($amount / $count, 2);
        $generated = 0;

        foreach ($properties as $property) {
            $existing = get_posts(array(
                'post_type' => 'invoices',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array('key' => 'expense_id', 'value' => $expense_id, 'compare' => '='),
                    array('key' => 'property_id', 'value' => $property->ID, 'compare' => '='),
                ),
            ));
            if (!empty($existing)) {
                continue;
            }

            $invoice_title = 'Invoice - ' . $property->post_title . ' - ' . get_the_title($expense_id);
            $invoice_id = wp_insert_post(array(
                'post_type' => 'invoices',
                'post_status' => 'publish',
                'post_title' => $invoice_title,
            ));

            if (is_wp_error($invoice_id) || !$invoice_id) {
                error_log("Mostaager Error: Failed to create invoice for property {$property->ID}");
                continue;
            }

            update_post_meta($invoice_id, 'building_id', $building_id);
            update_post_meta($invoice_id, 'property_id', $property->ID);
            update_post_meta($invoice_id, 'expense_id', $expense_id);
            update_post_meta($invoice_id, 'amount_due', $per_unit);
            update_post_meta($invoice_id, 'status', 'pending');
            update_post_meta($invoice_id, 'receipt_image', '');

            if (function_exists('update_field')) {
                update_field('building_id', $building_id, $invoice_id);
                update_field('property_id', $property->ID, $invoice_id);
                update_field('expense_id', $expense_id, $invoice_id);
                update_field('amount_due', $per_unit, $invoice_id);
                update_field('status', 'pending', $invoice_id);
            }

            $woo_product_id = function_exists('ms_create_woocommerce_product_for_invoice') ? ms_create_woocommerce_product_for_invoice($invoice_id, $per_unit) : 0;
            if ($woo_product_id) {
                update_post_meta($invoice_id, 'woo_product_id', $woo_product_id);
            }

            $generated++;
        }

        if (function_exists('ms_update_building_wallet_target')) {
            ms_update_building_wallet_target($building_id, $amount);
        }

        error_log("Mostaager: Generated $generated invoices for Expense ID $expense_id, Building ID $building_id");
        return $generated;
    }
}

function ms_get_legacy_invoices_for_user($user_id, $status = null, $property_id = null)
{
    if (!post_type_exists('invoices')) {
        return array();
    }

    $user_id = absint($user_id);
    $property_ids = array();

    if ($property_id) {
        $property_ids[] = absint($property_id);
    } else {
        $property_ids = ms_get_legacy_property_ids_for_user($user_id);
    }

    if (empty($property_ids)) {
        return array();
    }

    $meta_query = array(
        'relation' => 'AND',
        array(
            'key' => 'property_id',
            'value' => $property_ids,
            'compare' => 'IN',
        ),
    );

    if ($status !== null) {
        $meta_query[] = array(
            'key' => 'status',
            'value' => $status,
            'compare' => '=',
        );
    }

    $posts = get_posts(array(
        'post_type' => 'invoices',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => $meta_query,
    ));

    $unique_posts = array();
    foreach ($posts as $post) {
        $unique_posts[$post->ID] = $post;
    }
    $posts = array_values($unique_posts);

    return array_values(array_filter(array_map('ms_get_legacy_invoice_object', $posts)));
}

function ms_get_legacy_invoices_for_manager($manager_id, $limit = 20)
{
    if (!post_type_exists('invoices')) {
        return array();
    }

    $building_ids = ms_get_legacy_building_ids_for_manager($manager_id);
    if (empty($building_ids)) {
        return array();
    }

    $building_meta = array('relation' => 'OR');
    $building_meta[] = array(
        'key' => 'building_id',
        'value' => $building_ids,
        'compare' => 'IN',
    );
    foreach ($building_ids as $bid) {
        $building_meta[] = array(
            'key' => 'building_id',
            'value' => '"' . $bid . '"',
            'compare' => 'LIKE',
        );
    }

    $args = array(
        'post_type' => 'invoices',
        'post_status' => 'publish',
        'posts_per_page' => $limit > 0 ? absint($limit) : -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array($building_meta),
    );

    $posts = get_posts($args);
    return array_map('ms_get_legacy_invoice_object', $posts);
}

function ms_get_legacy_expense_posts_by_building($building_id)
{
    if (!post_type_exists('expenses')) {
        return array();
    }

    $building_id = absint($building_id);
    if (!$building_id) {
        return array();
    }

    return get_posts(array(
        'post_type' => 'expenses',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'value' => $building_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    ));
}

function ms_get_legacy_expenses_count_by_building_ids($building_ids)
{
    $count = 0;
    foreach ($building_ids as $building_id) {
        $count += count(ms_get_legacy_expense_posts_by_building($building_id));
    }
    return $count;
}

function ms_get_legacy_invoices_count_for_user($user_id, $status = null, $property_id = null)
{
    $invoices = ms_get_legacy_invoices_for_user($user_id, $status, $property_id);
    return count($invoices);
}

function ms_get_legacy_invoice_totals_for_manager($manager_id)
{
    $legacy_invoices = ms_get_legacy_invoices_for_manager($manager_id, -1);
    $total_due = 0;
    $total_collected = 0;
    $paid_count = 0;

    foreach ($legacy_invoices as $inv) {
        $amount = floatval($inv->amount);
        $total_due += $amount;
        if ($inv->status === 'paid') {
            $total_collected += $amount;
            $paid_count++;
        }
    }

    return array(
        'total_due' => $total_due,
        'total_collected' => $total_collected,
        'paid_count' => $paid_count,
        'invoice_count' => count($legacy_invoices),
    );
}

function ms_get_legacy_user_overdue_count($user_id)
{
    $invoices = ms_get_legacy_invoices_for_user($user_id);
    $count = 0;
    foreach ($invoices as $invoice) {
        if ($invoice->status === 'overdue' || ($invoice->status !== 'paid' && !empty($invoice->due_date) && strtotime($invoice->due_date) < strtotime(current_time('mysql')))) {
            $count++;
        }
    }
    return $count;
}

function ms_get_legacy_latest_due_invoice($user_id)
{
    $invoices = ms_get_legacy_invoices_for_user($user_id);
    if (empty($invoices)) {
        return null;
    }

    usort($invoices, function ($a, $b) {
        $a_due = !empty($a->due_date) ? strtotime($a->due_date) : PHP_INT_MAX;
        $b_due = !empty($b->due_date) ? strtotime($b->due_date) : PHP_INT_MAX;
        return $a_due <=> $b_due;
    });

    return $invoices[0];
}

function ms_get_legacy_rent_streak_badge($user_id)
{
    $invoices = ms_get_legacy_invoices_for_user($user_id);
    if (empty($invoices)) {
        return array(
            'streak' => 0,
            'label' => 'لا توجد سجلات إيجار',
            'level' => 'none',
            'color' => '#64748b',
        );
    }

    $streak = 0;
    foreach ($invoices as $invoice) {
        if ($invoice->status === 'paid') {
            $streak++;
        } else {
            break;
        }
    }

    if ($streak >= 6) {
        $label = 'أسطورة الإيجار';
        $color = '#10b981';
    } elseif ($streak >= 3) {
        $label = 'ملتزم بالإيجار';
        $color = '#2563eb';
    } elseif ($streak >= 1) {
        $label = 'في مسار إيجار';
        $color = '#f59e0b';
    } else {
        $label = 'تحتاج متابعة';
        $color = '#ef4444';
    }

    return array(
        'streak' => $streak,
        'label' => $label,
        'level' => $streak > 0 ? 'positive' : 'negative',
        'color' => $color,
    );
}

function ms_get_legacy_owner_revenue_summary($owner_id)
{
    $invoices = ms_get_legacy_invoices_for_user($owner_id);
    $total_paid = 0;
    $total_due = 0;
    $next_due = null;

    foreach ($invoices as $invoice) {
        $amount = floatval($invoice->amount);
        $total_due += $amount;
        if ($invoice->status === 'paid') {
            $total_paid += $amount;
        }

        if ($invoice->status !== 'paid') {
            if (!$next_due || ( !empty($invoice->due_date) && strtotime($invoice->due_date) < strtotime($next_due->due_date))) {
                $next_due = $invoice;
            }
        }
    }

    return array(
        'total_paid' => $total_paid,
        'total_due' => $total_due,
        'next_due' => $next_due,
    );
}

function ms_get_legacy_payable_invoices_for_user($user_id)
{
    $invoices = ms_get_legacy_invoices_for_user($user_id);
    return array_filter($invoices, function ($invoice) {
        return isset($invoice->source) && $invoice->source === 'legacy';
    });
}

function ms_get_legacy_invoices_for_property($property_id)
{
    if (!post_type_exists('invoices')) {
        return array();
    }

    $property_id = absint($property_id);
    if (!$property_id) {
        return array();
    }

    $posts = get_posts(array(
        'post_type' => 'invoices',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'property_id',
                'value' => $property_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    ));

    return array_map('ms_get_legacy_invoice_object', $posts);
}

function ms_get_legacy_building_wallet_stats($building_id)
{
    $expenses = ms_get_legacy_expense_posts_by_building($building_id);
    $target = 0;
    foreach ($expenses as $expense) {
        $target += floatval(get_post_meta($expense->ID, 'total_amount', true));
    }

    return array(
        'target' => $target,
        'collected' => 0,
    );
}

function ms_get_legacy_building_invoice_count($building_id)
{
    if (!post_type_exists('invoices')) {
        return 0;
    }

    return count(get_posts(array(
        'post_type' => 'invoices',
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'value' => $building_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    )));
}

function ms_get_legacy_building_invoices($building_id)
{
    if (!post_type_exists('invoices')) {
        return array();
    }

    $building_id = absint($building_id);
    if (!$building_id) {
        return array();
    }

    $posts = get_posts(array(
        'post_type' => 'invoices',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'value' => $building_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    ));

    return array_map('ms_get_legacy_invoice_object', $posts);
}

function ms_get_legacy_invoices_count_by_user_status_and_property($user_id, $status, $property_id)
{
    return ms_get_legacy_invoices_count_for_user($user_id, $status, $property_id);
}

function ms_get_legacy_invoices_count_by_user_and_status($user_id, $status)
{
    return ms_get_legacy_invoices_count_for_user($user_id, $status);
}

function ms_get_legacy_buildings_by_manager($user_id)
{
    return ms_get_legacy_buildings_for_manager($user_id);
}

function ms_get_legacy_manager_invoices_count($manager_id)
{
    $invoices = ms_get_legacy_invoices_for_manager($manager_id, -1);
    return count($invoices);
}

function ms_get_legacy_manager_paid_invoice_stats($manager_id)
{
    return ms_get_legacy_invoice_totals_for_manager($manager_id);
}

function ms_get_legacy_manager_property_ids($manager_id)
{
    $building_ids = ms_get_legacy_building_ids_for_manager($manager_id);
    $property_ids = array();
    foreach ($building_ids as $building_id) {
        $property_ids = array_merge($property_ids, ms_get_legacy_property_ids_by_building($building_id));
    }
    return array_values(array_unique($property_ids));
}

function ms_get_legacy_manager_property_count($manager_id)
{
    return count(ms_get_legacy_manager_property_ids($manager_id));
}

function ms_get_legacy_manager_owner_ids($manager_id)
{
    $property_ids = ms_get_legacy_manager_property_ids($manager_id);
    $owners = array();
    foreach ($property_ids as $property_id) {
        $post_author = absint(get_post_field('post_author', $property_id));
        if ($post_author) {
            $owners[] = $post_author;
        }
        $owner_meta = get_post_meta($property_id, 'owner_id', true);
        $owner_id = ms_extract_meta_id($owner_meta);
        if ($owner_id) {
            $owners[] = $owner_id;
        }
        $property_owner_meta = get_post_meta($property_id, 'property_owner', true);
        $property_owner_id = ms_extract_meta_id($property_owner_meta);
        if ($property_owner_id) {
            $owners[] = $property_owner_id;
        }
        $legacy_owner_meta = get_post_meta($property_id, 'ms_property_owner_id', true);
        $legacy_owner_id = ms_extract_meta_id($legacy_owner_meta);
        if ($legacy_owner_id) {
            $owners[] = $legacy_owner_id;
        }
    }
    return array_values(array_unique(array_filter($owners)));
}

function ms_get_legacy_manager_tenant_ids($manager_id)
{
    $property_ids = ms_get_legacy_manager_property_ids($manager_id);
    $tenants = array();
    foreach ($property_ids as $property_id) {
        $tenant_meta = get_post_meta($property_id, 'tenant_id', true);
        $tenant_id = ms_extract_meta_id($tenant_meta);
        if ($tenant_id) {
            $tenants[] = $tenant_id;
        }
    }
    return array_values(array_unique(array_filter($tenants)));
}

function ms_get_legacy_invoices_for_owner($owner_id)
{
    return ms_get_legacy_invoices_for_user($owner_id);
}

function ms_get_legacy_invoices_for_tenant($tenant_id)
{
    return ms_get_legacy_invoices_for_user($tenant_id);
}

function ms_get_legacy_invoices_count_for_owner($owner_id, $status)
{
    return ms_get_legacy_invoices_count_for_user($owner_id, $status);
}

function ms_get_legacy_invoices_count_for_tenant($tenant_id, $status)
{
    return ms_get_legacy_invoices_count_for_user($tenant_id, $status);
}

function ms_get_legacy_user_overdue_invoices_count($user_id)
{
    return ms_get_legacy_user_overdue_count($user_id);
}

function ms_get_legacy_user_latest_due_invoice($user_id)
{
    return ms_get_legacy_latest_due_invoice($user_id);
}

function ms_get_legacy_user_rent_streak_badge($user_id)
{
    return ms_get_legacy_rent_streak_badge($user_id);
}

function ms_get_legacy_manager_building_invoices($manager_id)
{
    return ms_get_legacy_invoices_for_manager($manager_id, -1);
}

function ms_get_legacy_property_invoices($property_id)
{
    return ms_get_legacy_invoices_for_property($property_id);
}

function ms_get_legacy_expense_ids_by_building($building_id)
{
    return wp_list_pluck(ms_get_legacy_expense_posts_by_building($building_id), 'ID');
}

function ms_get_legacy_building_expenses_count($building_id)
{
    return count(ms_get_legacy_expense_posts_by_building($building_id));
}

function ms_get_legacy_building_expenses($building_id)
{
    return ms_get_legacy_expense_posts_by_building($building_id);
}

function ms_get_legacy_invoice_owner_id($invoice_id)
{
    $invoice = get_post($invoice_id);
    if (!$invoice) {
        return 0;
    }
    $property_id = ms_extract_meta_id(get_post_meta($invoice_id, 'property_id', true));
    if (!$property_id) {
        return 0;
    }
    $contacts = ms_get_property_contacts($property_id);
    return (!empty($contacts['owner']) && is_object($contacts['owner']) && isset($contacts['owner']->ID)) ? intval($contacts['owner']->ID) : 0;
}

function ms_get_legacy_invoice_tenant_id($invoice_id)
{
    $invoice = get_post($invoice_id);
    if (!$invoice) {
        return 0;
    }
    $property_id = ms_extract_meta_id(get_post_meta($invoice_id, 'property_id', true));
    if (!$property_id) {
        return 0;
    }
    $contacts = ms_get_property_contacts($property_id);
    return (!empty($contacts['tenant']) && is_object($contacts['tenant']) && isset($contacts['tenant']->ID)) ? intval($contacts['tenant']->ID) : 0;
}

function ms_get_legacy_invoice_manager_id($invoice_id)
{
    return 0;
}

function ms_get_legacy_payable_invoice_ids_for_user($user_id)
{
    return wp_list_pluck(ms_get_legacy_invoices_for_user($user_id), 'id');
}

function ms_get_legacy_invoice_ids_for_manager($manager_id)
{
    return wp_list_pluck(ms_get_legacy_invoices_for_manager($manager_id, -1), 'id');
}

function ms_get_legacy_invoice_ids_for_property($property_id)
{
    return wp_list_pluck(ms_get_legacy_invoices_for_property($property_id), 'id');
}

function ms_get_user_wallet_balance($user_id)
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $wallet_table WHERE user_id = %d", $user_id));
    if ($balance !== null) {
        return floatval($balance);
    }

    // Fallback to transaction log if wallet row does not exist.
    $tx_table = $wpdb->prefix . 'ms_wallet_transactions';
    $credit = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM $tx_table WHERE user_id = %d AND type = 'credit'", $user_id));
    $debit = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM $tx_table WHERE user_id = %d AND type = 'debit'", $user_id));
    return floatval($credit) - floatval($debit);
}

function ms_get_or_create_user_wallet($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_user_wallet';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $user_id));
    if ($existing) {
        return intval($existing);
    }

    $wpdb->insert($table, [
        'user_id' => $user_id,
        'balance' => 0.00,
        'currency' => ms_get_currency(),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d', '%f', '%s', '%s', '%s']);

    return intval($wpdb->insert_id);
}

function ms_add_user_wallet_balance($user_id, $amount, $meta = '')
{
    if (empty($user_id) || floatval($amount) <= 0) {
        return false;
    }

    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    ms_get_or_create_user_wallet($user_id);

    $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance + %f, updated_at = %s WHERE user_id = %d",
        $amount,
        current_time('mysql'),
        $user_id
    ));

    $tx_table = $wpdb->prefix . 'ms_wallet_transactions';
    $wpdb->insert($tx_table, [
        'user_id' => $user_id,
        'type' => 'credit',
        'amount' => $amount,
        'meta' => maybe_serialize($meta),
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%f', '%s', '%s']);

    return true;
}

function ms_deduct_user_wallet_balance($user_id, $amount, $meta = '')
{
    if (empty($user_id) || floatval($amount) <= 0) {
        return false;
    }

    $balance = ms_get_user_wallet_balance($user_id);
    if ($balance < $amount) {
        return false;
    }

    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    ms_get_or_create_user_wallet($user_id);

    $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance - %f, updated_at = %s WHERE user_id = %d",
        $amount,
        current_time('mysql'),
        $user_id
    ));

    $tx_table = $wpdb->prefix . 'ms_wallet_transactions';
    $wpdb->insert($tx_table, [
        'user_id' => $user_id,
        'type' => 'debit',
        'amount' => $amount,
        'meta' => maybe_serialize($meta),
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%f', '%s', '%s']);

    return true;
}

function ms_get_owner_properties_with_invoices($owner_id)
{
    $properties = ms_get_properties_by_owner($owner_id);
    if (empty($properties)) {
        return [];
    }

    foreach ($properties as &$property) {
        $property->invoices = ms_get_user_invoices($owner_id);
    }

    return $properties;
}

function ms_get_agent_subscription($agent_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_agent_subscriptions';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE agent_id = %d LIMIT 1", $agent_id));
    }
    return null;
}

/**
 * Return configured currency code for Mostaager plugin.
 * Defaults to 'EGP' per spec.
 */
function ms_get_currency()

{
    $c = get_option('ms_currency', 'EGP');
    if (!is_string($c) || empty($c)) {
        return 'EGP';
    }
    return strtoupper(trim($c));
}

function ms_get_currency_symbol() {
    $currency = ms_get_currency();
    switch ($currency) {
        case 'USD':
            return '$';
        case 'EGP':
        default:
            return 'ج.م';
    }
}

function ms_get_building_discussions($building_id, $limit = 50) {
    $building_id = intval($building_id);
    if (!$building_id) {
        return array();
    }

    $posts = get_posts(array(
        'post_type' => 'ms_discussion',
        'post_status' => 'publish',
        'posts_per_page' => intval($limit),
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'value' => $building_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    ));

    $items = array();
    foreach ($posts as $post) {
        $items[] = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'excerpt' => wp_trim_words($post->post_content, 20, '...'),
            'created_at' => get_the_date('Y-m-d H:i', $post),
        );
    }

    return $items;
}

function ms_get_discussion_replies($discussion_id) {
    $discussion_id = intval($discussion_id);
    if (!$discussion_id) {
        return array();
    }

    $comments = get_comments(array(
        'post_id' => $discussion_id,
        'status' => 'approve',
        'orderby' => 'comment_date',
        'order' => 'ASC',
    ));

    $replies = array();
    foreach ($comments as $comment) {
        $replies[] = array(
            'id' => $comment->comment_ID,
            'author_name' => $comment->comment_author,
            'created_at' => get_comment_date('Y-m-d H:i', $comment),
            'content' => wpautop(esc_html($comment->comment_content)),
        );
    }

    return $replies;
}

function ms_add_discussion_reply($discussion_id, $user_id, $content) {
    $discussion_id = intval($discussion_id);
    $user_id = intval($user_id);
    $content = sanitize_textarea_field($content);
    if (!$discussion_id || !$user_id || empty($content)) {
        return false;
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }

    $comment_data = array(
        'comment_post_ID' => $discussion_id,
        'comment_author' => $user->display_name ?: $user->user_login,
        'comment_author_email' => $user->user_email,
        'comment_content' => $content,
        'user_id' => $user_id,
        'comment_approved' => 1,
    );

    $comment_id = wp_insert_comment($comment_data);
    if (!$comment_id) {
        return false;
    }

    return get_comment($comment_id);
}

function ms_create_building_discussion($building_id, $title, $content, $user_id) {
    $building_id = intval($building_id);
    $user_id = intval($user_id);
    $title = sanitize_text_field($title);
    $content = sanitize_textarea_field($content);
    if (!$building_id || !$user_id || empty($title) || empty($content)) {
        return false;
    }

    $post_id = wp_insert_post(array(
        'post_title' => $title,
        'post_content' => $content,
        'post_type' => 'ms_discussion',
        'post_status' => 'publish',
        'post_author' => $user_id,
        'meta_input' => array(
            'building_id' => $building_id,
        ),
    ));

    return $post_id ? $post_id : false;
}


// Admin: register simple settings page for currency
add_action('admin_menu', function () {
    add_options_page('Mostaager Settings', 'Mostaager', 'manage_options', 'mostaager-settings', function () {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['ms_currency_nonce']) && wp_verify_nonce($_POST['ms_currency_nonce'], 'ms_save_currency')) {
            if (isset($_POST['ms_currency'])) {
                update_option('ms_currency', sanitize_text_field($_POST['ms_currency']));
                echo '<div class="updated"><p>تم حفظ إعدادات العملة.</p></div>';
            }
        }
        $val = esc_attr(ms_get_currency());
        ?>
        <div class="wrap">
            <h1>Mostaager Settings</h1>
            <form method="post">
                <?php wp_nonce_field('ms_save_currency', 'ms_currency_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ms_currency">Currency Code</label></th>
                        <td><input name="ms_currency" id="ms_currency" type="text" value="<?php echo $val; ?>" class="regular-text" />
<p class="description">3-letter ISO currency code (e.g. USD, EGP). Default: EGP</p>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    });
});

function ms_set_agent_subscription($agent_id, $monthly_fee, $status = 'active', $renewal_date = null)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_agent_subscriptions';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE agent_id = %d", $agent_id));
    $data = array(
        'agent_id' => $agent_id,
        'monthly_fee' => $monthly_fee,
        'status' => $status,
        'renewal_date' => $renewal_date,
        'updated_at' => current_time('mysql'),
    );
    $formats = array('%d', '%f', '%s', '%s', '%s');

    if ($exists) {
        return $wpdb->update($table, $data, array('id' => $exists), $formats, array('%d')) !== false;
    }

    $data['created_at'] = current_time('mysql');
    return $wpdb->insert($table, $data, $formats) !== false;
}

function ms_get_rent_streak_record($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_rent_streaks';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d LIMIT 1", $user_id));
    }
    return null;
}

function ms_upsert_rent_streak($user_id, $streak, $last_payment_date = null)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_rent_streaks';
    $record = ms_get_rent_streak_record($user_id);
    $data = array(
        'user_id' => $user_id,
        'streak' => intval($streak),
        'last_payment_date' => $last_payment_date,
        'updated_at' => current_time('mysql'),
    );
    $formats = array('%d', '%d', '%s', '%s');

    if ($record) {
        return $wpdb->update($table, $data, array('id' => $record->id), $formats) !== false;
    }

    $data['created_at'] = current_time('mysql');
    return $wpdb->insert($table, $data, $formats) !== false;
}

function ms_get_agent_subscription_status($agent_id)
{
    $subscription = ms_get_agent_subscription($agent_id);
    if ($subscription) {
        return array(
            'monthly_fee' => floatval($subscription->monthly_fee),
            'status' => $subscription->status,
            'due' => 0,
            'renewal_date' => $subscription->renewal_date,
        );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $monthly_fee = 20.00;
    $current_month = date('Y-m');
    $query = $wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $table WHERE user_id = %d AND status = %s AND DATE_FORMAT(created_at, '%%Y-%%m') = %s", $agent_id, 'pending', $current_month);
    $due = floatval($wpdb->get_var($query));

    return array(
        'monthly_fee' => $monthly_fee,
        'status' => $due > 0 ? 'due' : 'paid',
        'due' => $due,
        'renewal_date' => null,
    );
}

function ms_get_invoice_by_id($invoice_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $invoice_id);
    return $wpdb->get_row($sql);
}

function ms_mark_invoice_paid($invoice_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $updated = $wpdb->update($table, ['status' => 'paid', 'paid_date' => current_time('mysql')], ['id' => $invoice_id], ['%s','%s'], ['%d']);
    if ($updated === false) {
        return false;
    }

    $inv = ms_get_invoice_by_id($invoice_id);
    if ($inv && isset($inv->user_id)) {
        ms_add_notification(intval($inv->user_id), 'invoice_paid', "تم دفع الفاتورة #{$invoice_id}", intval($inv->building_id), $invoice_id);
    }

    if ($inv && isset($inv->expense_id) && $inv->expense_id) {
        $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_maintenance_requests WHERE id = %d", intval($inv->expense_id)));
        if ($manager_id) {
            ms_add_notification(intval($manager_id), 'invoice_paid', "تم دفع فاتورة الصيانة #{$invoice_id}", intval($inv->building_id), $invoice_id);
            if (class_exists('Mostager_WhatsApp_Integration')) {
                $phone = ms_get_user_whatsapp_phone($manager_id);
                if ($phone) {
                    $whatsapp = new Mostager_WhatsApp_Integration();
                    if ($whatsapp->is_enabled()) {
                        $payer_user = get_userdata($inv->user_id);
                        $whatsapp->send_payment_confirmation($phone, array(
                            'tenant_name' => $payer_user ? $payer_user->display_name : '',
                            'invoice_number' => $invoice_id,
                            'amount_paid' => floatval($inv->amount),
                            'payment_date' => current_time('Y-m-d'),
                        ));
                    }
                }
            }
        }
        ms_check_collection_completion(intval($inv->building_id), intval($inv->expense_id));
    }

    /**
     * Trigger payment hook for integrations (WhatsApp, webhooks)
     * Provide both invoice id and invoice object/array
     */
    do_action('ms_invoice_paid', $invoice_id, $inv);

    return true;
}

function ms_cancel_invoice($invoice_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';

    $inv = ms_get_invoice_by_id($invoice_id);
    if (!$inv) {
        return false;
    }

    $current_status = strtolower(trim($inv->status ?? ''));
    if ($current_status !== 'pending') {
        return false;
    }

    $updated = $wpdb->update($table, ['status' => 'canceled'], ['id' => $invoice_id], ['%s'], ['%d']);
    if ($updated === false) {
        return false;
    }

    if (isset($inv->user_id)) {
        ms_add_notification(intval($inv->user_id), 'invoice_canceled', "تم إلغاء الفاتورة #{$invoice_id}", intval($inv->building_id), $invoice_id);
    }

    if (isset($inv->expense_id) && $inv->expense_id) {
        $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_maintenance_requests WHERE id = %d", intval($inv->expense_id)));
        if ($manager_id) {
            ms_add_notification(intval($manager_id), 'invoice_canceled', "تم إلغاء فاتورة الصيانة #{$invoice_id}", intval($inv->building_id), $invoice_id);
        }
    }

    return true;
}

function ms_create_agent_subscription_invoice($agent_id, $amount)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';

    $month = date('Y-m');
    $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table WHERE user_id = %d AND status = %s AND DATE_FORMAT(created_at, '%%Y-%%m') = %s", $agent_id, 'pending', $month));
    if ($existing) {
        return false;
    }

    $wpdb->insert($table, [
        'user_id' => $agent_id,
        'amount' => $amount,
        'status' => 'pending',
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'created_at' => current_time('mysql'),
    ], ['%d', '%f', '%s', '%s', '%s']);

    return intval($wpdb->insert_id);
}

function ms_create_subscription_package_invoice($agent_id, $amount, $plan_name)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $description = sprintf('فاتورة اشتراك باقة %s', $plan_name);

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND status = %s AND description = %s LIMIT 1",
        $agent_id,
        'pending',
        $description
    ));
    if ($existing) {
        return intval($existing);
    }

    $wpdb->insert($table, [
        'user_id' => $agent_id,
        'description' => $description,
        'amount' => floatval($amount),
        'status' => 'pending',
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'invoice_type' => 'subscription',
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%f', '%s', '%s', '%s', '%s']);

    return intval($wpdb->insert_id);
}

function ms_get_telr_credentials()
{
    $settings = get_option('woocommerce_mostaager_telr_settings', []);
    return [
        'merchant_id' => isset($settings['merchant_id']) ? $settings['merchant_id'] : '',
        'auth_key' => isset($settings['auth_key']) ? $settings['auth_key'] : '',
        'test_mode' => isset($settings['test_mode']) ? $settings['test_mode'] : 'yes',
    ];
}

function ms_get_maintenance_requests($args = array())
{
    global $wpdb;
    $building_id = isset($args['building_id']) ? intval($args['building_id']) : 0;
    $manager_id = isset($args['manager_id']) ? intval($args['manager_id']) : 0;
    $status = isset($args['status']) ? sanitize_text_field($args['status']) : '';
    $limit = isset($args['limit']) ? intval($args['limit']) : 50;

    $table = $wpdb->prefix . 'ms_maintenance_requests';
    $sql = "SELECT m.* FROM {$table} m";
    $where = array();
    $params = array();

    if ($manager_id) {
        $sql .= " INNER JOIN {$wpdb->prefix}ms_buildings b ON (m.building_id = b.id OR (b.wp_post_id > 0 AND m.building_id = b.wp_post_id))";
        $where[] = 'b.manager_id = %d';
        $params[] = $manager_id;
    }

    if ($building_id) {
        if (function_exists('ms_get_building_id_values_for_query')) {
            $values = ms_get_building_id_values_for_query($building_id);
        } else {
            $values = array($building_id);
        }
        if (!empty($values)) {
            $placeholders = implode(',', array_fill(0, count($values), '%d'));
            $where[] = "m.building_id IN ($placeholders)";
            $params = array_merge($params, $values);
        }
    }

    if ($status) {
        $where[] = 'm.status = %s';
        $params[] = $status;
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY m.created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . intval($limit);
    }

    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    return $wpdb->get_results($sql);
}

function ms_mark_notification_read($notification_id, $user_id)
{
    global $wpdb;
    $notification_id = intval($notification_id);
    $user_id = intval($user_id);
    if (!$notification_id || !$user_id) {
        return false;
    }

    $table = $wpdb->prefix . 'ms_notifications';
    $owned = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND user_id = %d LIMIT 1", $notification_id, $user_id));
    if (!$owned) {
        return false;
    }

    $updated = $wpdb->update($table, array('is_read' => 1), array('id' => $notification_id), array('%d'), array('%d'));
    return ($updated !== false);
}

function ms_create_discussion_reply($discussion_id, $user_id, $message)
{
    $discussion_id = intval($discussion_id);
    $user_id = intval($user_id);
    $message = sanitize_textarea_field($message);

    if (!$discussion_id || !$user_id || empty($message)) {
        return false;
    }

    $discussion = get_post($discussion_id);
    if (!$discussion || $discussion->post_type !== 'ms_discussion') {
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $discussion_id,
        'comment_author' => $user->display_name ?: $user->user_login,
        'comment_author_email' => $user->user_email,
        'comment_content' => $message,
        'user_id' => $user_id,
        'comment_approved' => 1,
    ));

    return $comment_id ? intval($comment_id) : false;
}

function ms_distribute_maintenance_invoices($maintenance_id, $building_id, $total_cost, $payer_type)
{
    global $wpdb;
    $maintenance_id = intval($maintenance_id);
    $building_id = intval($building_id);
    $total_cost = floatval($total_cost);
    $payer_type = sanitize_text_field($payer_type);

    if (!$maintenance_id || !$building_id || $total_cost <= 0 || !in_array($payer_type, array('owner', 'tenant'), true)) {
        return 0;
    }

    if (!function_exists('ms_get_units_by_building')) {
        return 0;
    }

    $units = ms_get_units_by_building($building_id);
    if (empty($units)) {
        return 0;
    }

    $unit_count = count($units);
    $per_unit = round($total_cost / $unit_count, 2);
    if ($per_unit <= 0) {
        return 0;
    }

    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $created = 0;

    foreach ($units as $unit) {
        $unit_id = intval($unit->id ?? $unit->ID ?? 0);
        if (!$unit_id) {
            continue;
        }

        $user_id = 0;
        if ($payer_type === 'tenant') {
            if (!empty($unit->tenant_id)) {
                $user_id = intval($unit->tenant_id);
            } elseif (function_exists('ms_get_tenant_by_unit')) {
                $tenant = ms_get_tenant_by_unit($unit_id);
                if ($tenant && isset($tenant->tenant_user_id)) {
                    $user_id = intval($tenant->tenant_user_id);
                }
            }
        } else {
            $user_id = intval($unit->owner_id ?? $unit->user_id ?? 0);
            if (!$user_id && !empty($unit->owner)) {
                $user_id = intval($unit->owner);
            }
        }

        if (!$user_id) {
            continue;
        }

        $inserted = $wpdb->insert($invoice_table, array(
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'user_id' => $user_id,
            'expense_id' => $maintenance_id,
            'payer_type' => $payer_type,
            'amount' => $per_unit,
            'status' => 'pending',
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'created_at' => current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%f','%s','%s','%s'));

        if ($inserted !== false) {
            $invoice_id = intval($wpdb->insert_id);
            if (function_exists('ms_add_notification')) {
                ms_add_notification($user_id, 'maintenance_invoice_created', "تم إنشاء فاتورة صيانة للوحدة #{$unit_id}", $building_id, $invoice_id);
            }
            // Trigger invoice created hook for integrations (WhatsApp / webhooks)
            do_action('ms_invoice_created', $invoice_id, array(
                'building_id' => $building_id,
                'unit_id' => $unit_id,
                'user_id' => $user_id,
                'amount' => $per_unit,
                'expense_id' => $maintenance_id,
                'payer_type' => $payer_type,
            ));
            $created++;
        }
    }

    return $created;
}

function ms_build_telr_payload($order)
{
    $creds = ms_get_telr_credentials();
    if (empty($creds['merchant_id']) || empty($creds['auth_key'])) {
        return false;
    }

    return array(
        'ivp_method' => 'create',
        'ivp_store' => $creds['merchant_id'],
        'ivp_authkey' => $creds['auth_key'],
        'ivp_cart' => 'order-' . $order->get_id(),
        'ivp_test' => $creds['test_mode'] === 'yes' ? 1 : 0,
        'ivp_amount' => number_format($order->get_total(), 2, '.', ''),
        'ivp_currency' => $order->get_currency(),
        'return_auth' => $order->get_checkout_order_received_url(),
        'return_decl' => $order->get_checkout_order_received_url(),
        'return_can' => wc_get_cart_url(),
        'bill_fname' => $order->get_billing_first_name(),
        'bill_lname' => $order->get_billing_last_name(),
        'bill_addr1' => $order->get_billing_address_1(),
        'bill_city' => $order->get_billing_city(),
        'bill_country' => $order->get_billing_country(),
        'bill_email' => $order->get_billing_email(),
    );
}

function ms_request_telr_payment_url($order)
{
    $payload = ms_build_telr_payload($order);
    if (!$payload) {
        return false;
    }

    $request = wp_remote_post('https://secure.telr.com/gateway/order.json', array(
        'method' => 'POST',
        'body' => wp_json_encode($payload),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 30,
    ));

    if (is_wp_error($request)) {
        error_log('[Mostaager Telr] create order request failed: ' . $request->get_error_message());
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($request), true);
    if (!empty($body['order']['url'])) {
        return $body['order']['url'];
    }

    error_log('[Mostaager Telr] create order response missing order.url: ' . wp_json_encode($body));
    return false;
}

function ms_get_mostaager_telr_gateway()
{
    if (!function_exists('WC') || !class_exists('WC_Payment_Gateway')) {
        return false;
    }

    $gateways = WC()->payment_gateways();
    if (!is_object($gateways) || !method_exists($gateways, 'payment_gateways')) {
        return false;
    }

    $all_gateways = $gateways->payment_gateways();
    if (empty($all_gateways['mostaager_telr'])) {
        return false;
    }

    $gateway = $all_gateways['mostaager_telr'];
    if (empty($gateway->enabled) || $gateway->enabled !== 'yes') {
        return false;
    }

    return $gateway;
}

function ms_create_woocommerce_product_for_invoice($invoice_id, $amount)
{
    if (!function_exists('wc_get_product_id_by_sku')) {
        return 0;
    }

    $sku = 'ms-invoice-' . $invoice_id;
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {
        return $product_id;
    }

    $product = new WC_Product_Simple();
    $product->set_name('فاتورة Mostaager #' . $invoice_id);
    $product->set_regular_price(number_format($amount, 2, '.', ''));
    $product->set_sku($sku);
    $product->set_catalog_visibility('hidden');
    $product->set_status('publish');
    return intval($product->save());
}

function ms_create_woo_order_for_invoice($invoice_id)
{
    if (!function_exists('wc_create_order') || !function_exists('wc_get_product')) {
        return false;
    }

    $invoice = ms_get_invoice_by_id($invoice_id);
    if (!$invoice || !isset($invoice->amount) || !isset($invoice->user_id)) {
        return false;
    }

    $product_id = ms_create_woocommerce_product_for_invoice($invoice_id, $invoice->amount);
    if (!$product_id) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    $order = wc_create_order(array('customer_id' => intval($invoice->user_id)));
    if (!$order) {
        return false;
    }

    $order->add_product($product, 1);
    $order->set_customer_id(intval($invoice->user_id));
    $order->set_payment_method('mostaager_telr');
    $order->set_payment_method_title('Telr Payment');
    $order->update_meta_data('mostaager_invoice_id', $invoice_id);
    $order->calculate_totals();
    $order->update_status('pending', 'Invoice order created by Mostaager');
    $order->save();

    // Persist wc_order_id into ms_invoices and record bridge table
    global $wpdb;
    $order_id = intval($order->get_id());
    $inv_table = $wpdb->prefix . 'ms_invoices';
    $wpdb->query($wpdb->prepare("UPDATE $inv_table SET wc_order_id = %d WHERE id = %d", $order_id, $invoice_id));

    $bridge_table = $wpdb->prefix . 'ms_wc_invoice_orders';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $bridge_table));
    if ($exists) {
        $already = $wpdb->get_var($wpdb->prepare("SELECT id FROM $bridge_table WHERE invoice_id = %d AND order_id = %d LIMIT 1", $invoice_id, $order_id));
        if (!$already) {
            $wpdb->insert($bridge_table, [
                'invoice_id' => $invoice_id,
                'order_id' => $order_id,
                'created_at' => current_time('mysql'),
            ], ['%d','%d','%s']);
        }
    }

    $telr_gateway = ms_get_mostaager_telr_gateway();
    if ($telr_gateway && method_exists($telr_gateway, 'process_payment')) {
        $result = $telr_gateway->process_payment($order->get_id());
        if (!empty($result['result']) && $result['result'] === 'success' && !empty($result['redirect'])) {
            return $result['redirect'];
        }
    }

    $payment_url = $order->get_checkout_payment_url(true);
    if (!empty($payment_url)) {
        return $payment_url;
    }

    if (function_exists('wc_get_checkout_url') && method_exists($order, 'get_order_key')) {
        $checkout_url = wc_get_checkout_url();
        if (!empty($checkout_url)) {
            return add_query_arg(
                array(
                    'pay_for_order' => 'true',
                    'order' => $order->get_id(),
                    'key' => $order->get_order_key(),
                ),
                wc_get_endpoint_url('order-pay', '', $checkout_url)
            );
        }
    }

    return false;
}

function ms_create_woocommerce_product_for_wallet_recharge($order_id, $amount)
{
    if (!function_exists('wc_get_product_id_by_sku')) {
        return 0;
    }

    $sku = 'ms-wallet-recharge-' . $order_id;
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {
        return $product_id;
    }

    $product = new WC_Product_Simple();
    $product->set_name('شحن محفظة Mostaager #' . $order_id);
    $product->set_regular_price(number_format(floatval($amount), 2, '.', ''));
    $product->set_sku($sku);
    $product->set_catalog_visibility('hidden');
    $product->set_status('publish');
    return intval($product->save());
}

function ms_create_woo_order_for_wallet_recharge($user_id, $amount)
{
    if (!function_exists('wc_create_order') || !function_exists('wc_get_product')) {
        return false;
    }

    $user_id = absint($user_id);
    $amount = floatval($amount);
    if (!$user_id || $amount <= 0) {
        return false;
    }

    $order = wc_create_order(array('customer_id' => $user_id));
    if (!$order) {
        return false;
    }

    $product_id = ms_create_woocommerce_product_for_wallet_recharge($order->get_id(), $amount);
    if (!$product_id) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    $order->add_product($product, 1);
    $order->set_customer_id($user_id);
    $order->set_payment_method('mostaager_telr');
    $order->set_payment_method_title('Telr Payment');
    $order->update_meta_data('mostaager_wallet_recharge_amount', $amount);
    $order->update_meta_data('mostaager_wallet_recharge_user_id', $user_id);
    $order->update_meta_data('mostaager_wallet_recharge_type', 'wallet_recharge');
    $order->calculate_totals();
    $order->update_status('pending', 'Wallet recharge order created by Mostaager');
    $order->save();

    $telr_gateway = ms_get_mostaager_telr_gateway();
    if ($telr_gateway && method_exists($telr_gateway, 'process_payment')) {
        $result = $telr_gateway->process_payment($order->get_id());
        if (!empty($result['result']) && $result['result'] === 'success' && !empty($result['redirect'])) {
            return $result['redirect'];
        }
    }

    $payment_url = $order->get_checkout_payment_url(true);
    if (!empty($payment_url)) {
        return $payment_url;
    }

    if (function_exists('wc_get_checkout_url') && method_exists($order, 'get_order_key')) {
        $checkout_url = wc_get_checkout_url();
        if (!empty($checkout_url)) {
            return add_query_arg(
                array(
                    'pay_for_order' => 'true',
                    'order' => $order->get_id(),
                    'key' => $order->get_order_key(),
                ),
                wc_get_endpoint_url('order-pay', '', $checkout_url)
            );
        }
    }

    return false;
}

function ms_get_rent_streak_badge($user_id)
{
    global $wpdb;
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT status, due_date FROM $inv_tbl WHERE user_id = %d ORDER BY due_date DESC LIMIT 6",
        $user_id
    ));

    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy = ms_get_legacy_invoices_for_user($user_id);
        if (!empty($legacy)) {
            $invoices = array_merge($invoices ?: array(), array_slice($legacy, 0, 6));
        }
    }

    if (empty($invoices)) {
        return array(
            'streak' => 0,
            'label' => 'لا توجد سجلات إيجار',
            'level' => 'none',
            'color' => '#64748b',
        );
    }

    $streak = 0;
    foreach ($invoices as $invoice) {
        if ($invoice->status === 'paid') {
            $streak++;
        } else {
            break;
        }
    }

    if ($streak >= 6) {
        $label = 'أسطورة الإيجار';
        $color = '#10b981';
    } elseif ($streak >= 3) {
        $label = 'ملتزم بالإيجار';
        $color = '#2563eb';
    } elseif ($streak >= 1) {
        $label = 'في مسار إيجار';
        $color = '#f59e0b';
    } else {
        $label = 'تحتاج متابعة';
        $color = '#ef4444';
    }

    return array(
        'streak' => $streak,
        'label' => $label,
        'level' => $streak > 0 ? 'positive' : 'negative',
        'color' => $color,
    );
}

function ms_get_latest_due_invoice($user_id)
{
    global $wpdb;
    $inv_tbl = $wpdb->prefix . 'ms_invoices';
    $next_due = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $inv_tbl WHERE user_id = %d AND status != %s ORDER BY due_date ASC LIMIT 1",
        $user_id,
        'paid'
    ));

    if (function_exists('ms_get_legacy_latest_due_invoice')) {
        $legacy = ms_get_legacy_latest_due_invoice($user_id);
        if ($legacy && (!$next_due || (isset($legacy->due_date) && strtotime($legacy->due_date) < strtotime($next_due->due_date)))) {
            return $legacy;
        }
    }

    return $next_due;
}

function ms_generate_invoices_from_maintenance($maintenance_id, $payer_type = 'owner_or_tenant')
{
    global $wpdb;
    $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
    $invoice_table = $wpdb->prefix . 'ms_invoices';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $maint_table WHERE id = %d", $maintenance_id));
    if (!$row) {
        return 0;
    }

    // Prefer ms_units for splitting cost per unit
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $units = $wpdb->get_results($wpdb->prepare("SELECT * FROM $unit_tbl WHERE building_id = %d", $row->building_id));
    // Prefer plugin DB wrapper for units when available (unified source)
    if (empty($units) && function_exists('ms_get_units_by_building')) {
        $units = ms_get_units_by_building($row->building_id);
    }
    if (empty($units)) {
        // Fallback to legacy properties-by-building
        $properties = ms_get_properties_by_building($row->building_id);
        if (!$properties || empty($properties)) {
            return 0;
        }

        $count = count($properties);
        $share = $row->cost / max(1, $count);
        $invoices_created = 0;

        foreach ($properties as $index => $property) {
            $effective_share = $index === $count - 1 ? round($row->cost - ($share * ($count - 1)), 2) : round($share, 2);
            $contacts = ms_get_property_contacts($property->ID);
            $payer_id = 0;

            // Try to resolve tenant via ms_unit_tenants first (unit tenancy table)
            if ($payer_type === 'tenant' || $payer_type === 'owner_or_tenant') {
                if (function_exists('ms_get_tenant_by_unit')) {
                    $tenant_row = ms_get_tenant_by_unit($property->ID);
                    if ($tenant_row && !empty($tenant_row->tenant_user_id)) {
                        $payer_id = intval($tenant_row->tenant_user_id);
                    }
                }
            }

            if (!$payer_id) {
                if ($payer_type === 'owner') {
                    $payer_id = intval($contacts['owner']->ID ?? 0);
                } elseif ($payer_type === 'tenant') {
                    $payer_id = intval($contacts['tenant']->ID ?? 0);
                    if (!$payer_id) {
                        $payer_id = intval($contacts['owner']->ID ?? 0);
                    }
                } elseif ($payer_type === 'agent') {
                    $payer_id = intval($contacts['agent']->ID ?? 0);
                } else {
                    $payer_id = isset($contacts['tenant']) && $contacts['tenant'] ? intval($contacts['tenant']->ID) : intval($contacts['owner']->ID ?? 0);
                }
            }

            if (!$payer_id) {
                continue;
            }

            $payer_type_insert = in_array($payer_type, array('owner', 'tenant', 'agent', 'owner_or_tenant'), true) ? $payer_type : 'owner_or_tenant';
            $wpdb->insert($invoice_table, [
                'user_id' => $payer_id,
                'building_id' => $row->building_id,
                'unit_id' => 0,
                'expense_id' => $maintenance_id,
                'amount' => $effective_share,
                'status' => 'pending',
                'due_date' => $row->due_date,
                'invoice_type' => 'maintenance',
                'description' => $row->title,
                'created_at' => current_time('mysql'),
                'payer_type' => $payer_type_insert,
            ], ['%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s']);

            $invoices_created++;
            ms_add_notification($payer_id, 'maintenance_invoice', "تم إنشاء فاتورة صيانة بمبلغ {$effective_share}", $row->building_id, $maintenance_id);

            // Notify agent if present in property contacts
            if (!empty($contacts['agent']) && !empty($contacts['agent']->ID)) {
                ms_add_notification(intval($contacts['agent']->ID), 'maintenance_notice', "إشعار صيانة: {$row->title}", $row->building_id, $maintenance_id);
            }

            if (class_exists('Mostager_WhatsApp_Integration')) {
                $phone = ms_get_user_whatsapp_phone($payer_id);
                if ($phone) {
                    $building = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}ms_buildings WHERE id = %d", $row->building_id));
                    $whatsapp = new Mostager_WhatsApp_Integration();
                    if ($whatsapp->is_enabled()) {
                        $payer_user = get_userdata($payer_id);
                        $whatsapp->send_invoice_notification($phone, array(
                            'tenant_name' => $payer_user ? $payer_user->display_name : '',
                            'building_name' => $building ? $building->title : '',
                            'unit_number' => '#0',
                            'invoice_number' => $wpdb->insert_id,
                            'total_amount' => $effective_share,
                            'due_date' => $row->due_date,
                        ));
                    }
                }
            }
        }

        ms_add_notification(intval($row->manager_id), 'maintenance_confirmed', "تم إنشاء {$invoices_created} فاتورة صيانة للطلب: {$row->title}", $row->building_id, $maintenance_id);
        ms_update_building_wallet_target($row->building_id, $row->cost);
        return $invoices_created;
    }

    $unit_count = count($units);
    $share = $row->cost / max(1, $unit_count);
    $invoices_created = 0;

    foreach ($units as $index => $unit) {
        $effective_share = $index === $unit_count - 1 ? round($row->cost - ($share * ($unit_count - 1)), 2) : round($share, 2);
        $payer_id = 0;
        $ptype = $payer_type;

        if ($payer_type === 'owner') {
            $payer_id = intval($unit->owner_id);
        } elseif ($payer_type === 'tenant') {
            $tenant = ms_get_tenant_by_unit($unit->id);
            if ($tenant && !empty($tenant->tenant_user_id)) {
                $payer_id = intval($tenant->tenant_user_id);
            }
            if (!$payer_id) {
                $payer_id = intval($unit->tenant_id);
            }
            if (!$payer_id) {
                $payer_id = intval($unit->owner_id);
            }
        } elseif ($payer_type === 'agent') {
            $payer_id = intval($unit->agent_id);
        } else {
            $tenant = ms_get_tenant_by_unit($unit->id);
            if ($tenant && !empty($tenant->tenant_user_id)) {
                $payer_id = intval($tenant->tenant_user_id);
                $ptype = 'tenant';
            } else {
                $payer_id = intval($unit->owner_id);
                $ptype = 'owner';
            }
        }

        if (!$payer_id) {
            continue;
        }

        $wpdb->insert($invoice_table, [
            'user_id' => $payer_id,
            'building_id' => $row->building_id,
            'unit_id' => intval($unit->id),
            'expense_id' => $maintenance_id,
            'amount' => $effective_share,
            'status' => 'pending',
            'due_date' => $row->due_date,
            'invoice_type' => 'maintenance',
            'description' => $row->title,
            'created_at' => current_time('mysql'),
            'payer_type' => $ptype,
        ], ['%d','%d','%d','%d','%f','%s','%s','%s','%s','%s']);

        $invoices_created++;
        ms_add_notification($payer_id, 'maintenance_invoice', "تم إنشاء فاتورة صيانة بمبلغ {$effective_share}", $row->building_id, $maintenance_id);

        if (class_exists('Mostager_WhatsApp_Integration')) {
            $phone = ms_get_user_whatsapp_phone($payer_id);
            if ($phone) {
                $building = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}ms_buildings WHERE id = %d", $row->building_id));
                $whatsapp = new Mostager_WhatsApp_Integration();
                if ($whatsapp->is_enabled()) {
                    $unit_number = isset($unit->unit_number) ? $unit->unit_number : ('#' . intval($unit->id));
                    $payer_user = get_userdata($payer_id);
                    $whatsapp->send_invoice_notification($phone, array(
                        'tenant_name' => $payer_user ? $payer_user->display_name : '',
                        'building_name' => $building ? $building->title : '',
                        'unit_number' => $unit_number,
                        'invoice_number' => $wpdb->insert_id,
                        'total_amount' => $effective_share,
                        'due_date' => $row->due_date,
                    ));
                }
            }
        }

        if (!empty($unit->agent_id)) {
            ms_add_notification(intval($unit->agent_id), 'maintenance_notice', "إشعار صيانة: {$row->title}", $row->building_id, $maintenance_id);
        }
    }

    ms_add_notification(intval($row->manager_id), 'maintenance_confirmed', "تم إنشاء {$invoices_created} فاتورة صيانة للطلب: {$row->title}", $row->building_id, $maintenance_id);
    ms_update_building_wallet_target($row->building_id, $row->cost);

    return $invoices_created;
}

function ms_check_collection_completion($building_id, $maintenance_id)
{
    global $wpdb;
    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $maint_table = $wpdb->prefix . 'ms_maintenance_requests';

    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT status FROM $invoice_table WHERE expense_id = %d AND invoice_type = 'maintenance'",
        $maintenance_id
    ));

    if (empty($invoices)) {
        return;
    }

    $all_paid = true;
    foreach ($invoices as $invoice) {
        if ($invoice->status !== 'paid') {
            $all_paid = false;
            break;
        }
    }

    if ($all_paid) {
        $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM $maint_table WHERE id = %d", $maintenance_id));
        if ($manager_id) {
            ms_add_notification($manager_id, 'collection_complete', "اكتمل تحصيل فواتير الصيانة #{$maintenance_id}", $building_id, $maintenance_id);
            if (class_exists('Mostager_WhatsApp_Integration')) {
                $phone = ms_get_user_whatsapp_phone($manager_id);
                if ($phone) {
                    $whatsapp = new Mostager_WhatsApp_Integration();
                    if ($whatsapp->is_enabled()) {
                        $manager_user = get_userdata($manager_id);
                        $whatsapp->send_payment_confirmation($phone, array(
                            'tenant_name' => $manager_user ? $manager_user->display_name : '',
                            'invoice_number' => 'N/A',
                            'amount_paid' => '',
                            'payment_date' => date('Y-m-d'),
                        ));
                    }
                }
            }
        }
    }
}

function ms_get_maintenance_collection_progress($maintenance_id)
{
    global $wpdb;
    $invoice_table = $wpdb->prefix . 'ms_invoices';

    $total = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $invoice_table WHERE expense_id = %d AND invoice_type = 'maintenance'",
        $maintenance_id
    )));

    $paid = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $invoice_table WHERE expense_id = %d AND invoice_type = 'maintenance' AND status = %s",
        $maintenance_id,
        'paid'
    )));

    $percent = $total > 0 ? round(($paid / $total) * 100, 2) : 0;

    return array(
        'total' => $total,
        'paid' => $paid,
        'percent' => $percent,
    );
}

function ms_process_recurring_maintenance()
{
    global $wpdb;
    $maint_table = $wpdb->prefix . 'ms_maintenance_requests';

    $templates = $wpdb->get_results(
        "SELECT * FROM $maint_table WHERE is_recurring = 1 AND status = 'open'"
    );

    foreach ($templates as $row) {
        $current_month = date('Y-m');
        $recurrence_day = intval($row->recurrence_day);
        if ($recurrence_day < 1 || $recurrence_day > 28) {
            $recurrence_day = 1;
        }
        $due_date = date('Y-m-d', strtotime($current_month . '-' . $recurrence_day));

        $payer_type = in_array($row->payer_type, array('owner', 'tenant', 'agent', 'owner_or_tenant'), true) ? $row->payer_type : 'owner_or_tenant';

        $wpdb->insert($maint_table, [
            'building_id' => $row->building_id,
            'title' => $row->title,
            'description' => $row->description,
            'cost' => $row->cost,
            'maintenance_type' => $row->maintenance_type,
            'is_recurring' => 0,
            'recurrence_day' => $row->recurrence_day,
            'manager_id' => $row->manager_id,
            'payer_type' => $payer_type,
            'start_date' => current_time('Y-m-d'),
            'due_date' => $due_date,
            'status' => 'open',
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']);

        $new_id = $wpdb->insert_id;

        ms_generate_invoices_from_maintenance($new_id, $payer_type);

        ms_add_notification($row->manager_id, 'recurring_maintenance', "تم توليد فواتير الصيانة الشهرية تلقائياً", $row->building_id, $new_id);
    }
}

/**
 * Return SQL clause and value for company scoping in queries.
 * If no company configured, return a safe clause that evaluates to true (1)
 * to avoid WPDB prepare errors when adding WHERE fragments.
 *
 * @return array [string $clause, mixed $value]
 */
function ms_get_company_clause($alias = '')
{
    // follow spec: option name 'mostaager_company_id'
    $company_id = get_option('mostaager_company_id', 0);
    $company_id = intval($company_id);
    $alias = trim((string) $alias);
    if ($alias !== '') {
        $prefix = $alias . '.';
    } else {
        $prefix = '';
    }

    if ($company_id > 0) {
        return array('clause' => $prefix . 'company_id = %d', 'value' => $company_id);
    }

    // Fallback: tautology and value 1 to satisfy prepare() callers
    return array('clause' => '1 = 1', 'value' => 1);
}
