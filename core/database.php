<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_create_tables()
{
    if (function_exists('ms_plugin_install')) {
        ms_plugin_install();
    }
}

/** Minimal DB helper functions used by dashboards **/
function ms_get_buildings_by_manager($user_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'ms_buildings';
    if ($user_id && user_can($user_id, 'manage_options')) {
        $sql = "SELECT * FROM $table";
    } else {
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE manager_id = %d", $user_id);
    }

    $rows = $wpdb->get_results($sql);
    if (!empty($rows)) {
        return $rows;
    }

    if (function_exists('ms_get_legacy_buildings_for_manager')) {
        $legacy = ms_get_legacy_buildings_for_manager($user_id);
        if (!empty($legacy)) {
            return $legacy;
        }
    }

    if (function_exists('ms_get_buildings_by_manager_cpt')) {
        return ms_get_buildings_by_manager_cpt($user_id);
    }

    return array();
}

/**
 * Resolve a Mostaager building id to a linked WordPress post id if available.
 * Returns 0 if no linked post is found.
 */
function ms_get_linked_wp_post_id_for_building($ms_building_id)
{
    global $wpdb;
    $ms_building_id = absint($ms_building_id);
    if (!$ms_building_id) {
        return 0;
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $wp_post_id = $wpdb->get_var($wpdb->prepare("SELECT wp_post_id FROM {$table} WHERE id = %d LIMIT 1", $ms_building_id));
    return $wp_post_id ? intval($wp_post_id) : 0;
}

function ms_get_building_id_values_for_query($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return array();
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, wp_post_id FROM {$table} WHERE id = %d OR wp_post_id = %d LIMIT 1",
        $building_id,
        $building_id
    ));

    $values = array();
    if ($row) {
        if (!empty($row->id)) {
            $values[] = intval($row->id);
        }
        if (!empty($row->wp_post_id) && intval($row->wp_post_id) !== intval($row->id)) {
            $values[] = intval($row->wp_post_id);
        }
    }

    if (empty($values)) {
        $values[] = $building_id;
    }

    return array_values(array_unique($values));
}

function ms_get_tenant_by_unit($unit_id)
{
    global $wpdb;
    $unit_id = absint($unit_id);
    if (!$unit_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE unit_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
        $unit_id
    ));

    if ($row) {
        return $row;
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $tenant_id = $wpdb->get_var($wpdb->prepare(
        "SELECT tenant_id FROM $unit_tbl WHERE id = %d LIMIT 1",
        $unit_id
    ));

    if ($tenant_id) {
        return (object) array(
            'tenant_id' => absint($tenant_id),
            'unit_id' => $unit_id,
            'source' => 'fallback_ms_units',
        );
    }

    return null;
}

function ms_get_units_by_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    $properties = ms_get_properties_by_building($building_id);
    if (!empty($properties)) {
        return $properties;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE building_id = %d", $building_id));
}

function ms_get_tenant_unit($tenant_id)
{
    global $wpdb;
    $tenant_id = absint($tenant_id);
    if (!$tenant_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE tenant_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
        $tenant_id
    ));
}

function ms_link_tenant_to_unit($tenant_id, $unit_id, $building_id, $start_date)
{
    global $wpdb;
    $tenant_id = absint($tenant_id);
    $unit_id = absint($unit_id);
    $building_id = absint($building_id);
    $start_date = sanitize_text_field($start_date);

    if (!$tenant_id || !$unit_id || !$building_id || empty($start_date)) {
        return false;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE unit_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) LIMIT 1",
        $unit_id
    ));

    if ($existing) {
        $wpdb->update($table, array('end_date' => current_time('Y-m-d'), 'status' => 'ended'), array('id' => intval($existing->id)), array('%s','%s'), array('%d'));
    }

    $inserted = $wpdb->insert($table, array(
        'unit_id' => $unit_id,
        'tenant_id' => $tenant_id,
        'building_id' => $building_id,
        'start_date' => $start_date,
        'end_date' => null,
        'status' => 'active',
        'created_at' => current_time('mysql'),
    ), array('%d','%d','%d','%s','%s','%s','%s'));

    if ($inserted === false) {
        return false;
    }

    return intval($wpdb->insert_id);
}

function ms_get_tenants_by_building($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE building_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC",
        $building_id
    ));
}

function ms_get_maintenance_by_property_ids(array $property_ids, $limit = 20)
{
    global $wpdb;
    $property_ids = array_filter(array_map('absint', $property_ids));
    if (empty($property_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($property_ids), '%d'));
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    $sql = "SELECT * FROM $table WHERE unit_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d";
    $params = array_merge($property_ids, array(absint($limit)));

    return $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params)));
}

function ms_get_building_id_for_agent($agent_id)
{
    global $wpdb;
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return 0;
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $building_id = $wpdb->get_var($wpdb->prepare("SELECT building_id FROM $unit_tbl WHERE agent_id = %d LIMIT 1", $agent_id));
    if ($building_id) {
        return intval($building_id);
    }

    if (function_exists('ms_get_properties_by_agent')) {
        $properties = ms_get_properties_by_agent($agent_id);
        foreach ((array) $properties as $property) {
            $prop_id = isset($property->ID) ? intval($property->ID) : (isset($property->id) ? intval($property->id) : 0);
            if ($prop_id) {
                $b = absint(get_post_meta($prop_id, 'building_id', true));
                if ($b) {
                    return $b;
                }
            }
        }
    }

    return 0;
}

function ms_get_properties_by_owner($user_id)
{
    global $wpdb;

    $merged = array();
    $seen_ids = array();

    // 1) Query Houzez property posts by author first
    if (post_type_exists('property')) {
        $args = array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'author' => $user_id,
            'fields' => 'all',
        );
        $author_posts = get_posts($args);

        foreach ($author_posts as $p) {
            if (!in_array($p->ID, $seen_ids, true)) {
                $seen_ids[] = $p->ID;
                // Normalize to consistent structure
                $merged[] = (object) array(
                    'id' => $p->ID,
                    'type' => 'houzez',
                    'name' => $p->post_title,
                    'status' => $p->post_status,
                    'post' => $p,
                );
            }
        }

        // 2) Search Houzez property posts by owner meta keys
        $owner_meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id');
        $meta_query = array('relation' => 'OR');
        foreach ($owner_meta_keys as $key) {
            $meta_query[] = array(
                'key' => $key,
                'value' => $user_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
            // also support serialized / ACF-stored post object values
            $meta_query[] = array(
                'key' => $key,
                'value' => '"' . $user_id . '"',
                'compare' => 'LIKE',
            );
        }

        $args_meta = array(
            'post_type' => 'property',
            'post_status' => $args['post_status'],
            'posts_per_page' => -1,
            'meta_query' => $meta_query,
            'fields' => 'all',
        );
        $meta_posts = get_posts($args_meta);

        foreach ($meta_posts as $p) {
            if (!in_array($p->ID, $seen_ids, true)) {
                $seen_ids[] = $p->ID;
                // Normalize to consistent structure
                $merged[] = (object) array(
                    'id' => $p->ID,
                    'type' => 'houzez',
                    'name' => $p->post_title,
                    'status' => $p->post_status,
                    'post' => $p,
                );
            }
        }
    }

    // 3) Incorporate ms_units rows
    $table = $wpdb->prefix . 'ms_units';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE owner_id = %d", $user_id);
    $unit_rows = $wpdb->get_results($sql);

    foreach ($unit_rows as $unit) {
        $unit_id = isset($unit->id) ? $unit->id : (isset($unit->unit_id) ? $unit->unit_id : null);
        if ($unit_id && !in_array($unit_id, $seen_ids, true)) {
            $seen_ids[] = $unit_id;
            // Normalize to consistent structure
            $merged[] = (object) array(
                'id' => $unit_id,
                'type' => 'unit',
                'name' => isset($unit->unit_name) ? $unit->unit_name : (isset($unit->name) ? $unit->name : 'Unit #' . $unit_id),
                'status' => isset($unit->status) ? $unit->status : 'active',
                'unit' => $unit,
            );
        }
    }

    return $merged;
}


function ms_get_properties_by_agent($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    // 1) Preferred source: ms_units table
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE agent_id = %d", $user_id);
    $rows = $wpdb->get_results($sql);
    if (!empty($rows)) {
        return $rows;
    }

    if (!post_type_exists('property')) {
        return array();
    }

    // 2) Search Houzez property posts by agent meta keys
    $agent_meta_keys = array('fave_agents', 'fave_property_agent', 'fave_property_agency');
    $meta_query = array('relation' => 'OR');
    foreach ($agent_meta_keys as $key) {
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

    $args_meta = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'meta_query' => $meta_query,
        'fields' => 'all',
    );
    $meta_posts = get_posts($args_meta);
    if (!empty($meta_posts)) {
        return $meta_posts;
    }

    // 3) Fallback: some agent-linked properties may use owner-style fields in Houzez post meta.
    $owner_meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id');
    $owner_meta_query = array('relation' => 'OR');
    foreach ($owner_meta_keys as $key) {
        $owner_meta_query[] = array(
            'key' => $key,
            'value' => $user_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
        $owner_meta_query[] = array(
            'key' => $key,
            'value' => '"' . $user_id . '"',
            'compare' => 'LIKE',
        );
    }

    $args_owner_meta = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'meta_query' => $owner_meta_query,
        'fields' => 'all',
    );
    $owner_posts = get_posts($args_owner_meta);
    if (!empty($owner_posts)) {
        return $owner_posts;
    }

    // 4) Final fallback: if the agent is author of the property post.
    return get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'author' => $user_id,
        'fields' => 'all',
    ));
}

function ms_get_user_invoices($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC", $user_id);
    $invoices = $wpdb->get_results($sql);

    $legacy_invoices = array();
    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy_invoices = ms_get_legacy_invoices_for_user($user_id);
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

function ms_get_owner_invoice_scope($owner_id)
{
    global $wpdb;
    $owner_id = absint($owner_id);
    if (!$owner_id) {
        return null;
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $unit_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $unit_tbl WHERE owner_id = %d", $owner_id));
    $building_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT building_id FROM $unit_tbl WHERE owner_id = %d AND building_id > 0", $owner_id));

    $clauses = array();
    $params = array();

    if (!empty($unit_ids)) {
        $placeholders = implode(',', array_fill(0, count($unit_ids), '%d'));
        $clauses[] = "unit_id IN ($placeholders)";
        $params = array_merge($params, $unit_ids);
    }

    if (!empty($building_ids)) {
        $expanded_building_ids = array();
        foreach ($building_ids as $id) {
            if (function_exists('ms_get_building_id_values_for_query')) {
                $expanded_building_ids = array_merge($expanded_building_ids, ms_get_building_id_values_for_query($id));
            } else {
                $expanded_building_ids[] = $id;
            }
        }
        $expanded_building_ids = array_values(array_unique(array_filter($expanded_building_ids, 'absint')));
        if (!empty($expanded_building_ids)) {
            $placeholders = implode(',', array_fill(0, count($expanded_building_ids), '%d'));
            $clauses[] = "building_id IN ($placeholders)";
            $params = array_merge($params, $expanded_building_ids);
        }
    }

    if (empty($clauses)) {
        return null;
    }

    return array(
        'where' => '(' . implode(' OR ', $clauses) . ')',
        'params' => $params,
    );
}

function ms_invoice_belongs_to_owner($invoice_id, $owner_id)
{
    $scope = ms_get_owner_invoice_scope($owner_id);
    if (empty($scope) || !$invoice_id) {
        return false;
    }

    global $wpdb;
    $invoice_tbl = $wpdb->prefix . 'ms_invoices';
    $query = "SELECT COUNT(id) FROM $invoice_tbl WHERE id = %d AND {$scope['where']}";
    $params = array_merge(array($invoice_id), $scope['params']);

    return intval($wpdb->get_var($wpdb->prepare($query, ...$params))) > 0;
}

function ms_get_owner_invoices($owner_id)
{
    global $wpdb;
    $invoice_tbl = $wpdb->prefix . 'ms_invoices';
    $scope = ms_get_owner_invoice_scope($owner_id);
    $invoices = array();

    if (!empty($scope)) {
        $query = "SELECT * FROM $invoice_tbl WHERE {$scope['where']} ORDER BY created_at DESC";
        $invoices = $wpdb->get_results($wpdb->prepare($query, ...$scope['params']));
    }

    if (!empty($invoices)) {
        foreach ($invoices as $invoice) {
            if (is_object($invoice)) {
                $invoice->source = 'new';
            }
        }
    }

    $legacy_invoices = array();
    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy_invoices = ms_get_legacy_invoices_for_user($owner_id);
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

function ms_get_owner_invoices_count_by_status($owner_id, $status)
{
    $invoices = ms_get_owner_invoices($owner_id);
    $count = 0;

    foreach ($invoices as $invoice) {
        if (isset($invoice->status) && $invoice->status === $status) {
            $count++;
        }
    }

    return $count;
}

function ms_get_owner_overdue_count($owner_id)
{
    $invoices = ms_get_owner_invoices($owner_id);
    $count = 0;
    $today = strtotime(current_time('mysql'));

    foreach ($invoices as $invoice) {
        $status = isset($invoice->status) ? $invoice->status : '';
        $due_date = !empty($invoice->due_date) ? strtotime($invoice->due_date) : 0;

        if ($status === 'overdue' || ($status !== 'paid' && $due_date > 0 && $due_date < $today)) {
            $count++;
        }
    }

    return $count;
}

function ms_get_wallet_balance($user_id)
{
    global $wpdb;

    // 1) Preferred source: ms_user_wallet (single source of truth)
    $wallet_tbl = $wpdb->prefix . 'ms_user_wallet';
    $balance = $wpdb->get_var(
        $wpdb->prepare("SELECT balance FROM $wallet_tbl WHERE user_id=%d LIMIT 1", $user_id)
    );

    if ($balance !== null) {
        return floatval($balance);
    }

    // 2) Fallback: compute from transactions table with multiple type names
    $txn_tbl = $wpdb->prefix . 'ms_wallet_transactions';

    // Credits (topups / recharges / credit)
    $credit_types = array('credit', 'topup', 'recharge', 'deposit');
    $debit_types  = array('debit', 'deduct', 'withdraw', 'payment');

    $credit_placeholders = implode(',', array_fill(0, count($credit_types), '%s'));
    $debit_placeholders  = implode(',', array_fill(0, count($debit_types), '%s'));

    $sql_credit = "SELECT COALESCE(SUM(amount),0) FROM {$txn_tbl} WHERE user_id=%d AND type IN ($credit_placeholders)";
    $sql_debit  = "SELECT COALESCE(SUM(amount),0) FROM {$txn_tbl} WHERE user_id=%d AND type IN ($debit_placeholders)";

    $credit_params = array_merge(array($user_id), $credit_types);
    $debit_params  = array_merge(array($user_id), $debit_types);

    $credit = $wpdb->get_var($wpdb->prepare($sql_credit, $credit_params));
    $debit  = $wpdb->get_var($wpdb->prepare($sql_debit, $debit_params));

    return floatval($credit) - floatval($debit);
}

function ms_get_wallet_transactions_for_user($user_id, $limit = 20)
{
    global $wpdb;
    $user_id = absint($user_id);
    $limit = $limit > 0 ? intval($limit) : 20;
    if (!$user_id) {
        return array();
    }

    $table = $wpdb->prefix . 'ms_wallet_transactions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit
    ));
}

function ms_get_wallet_transactions($user_id, $limit = 20)
{
    return ms_get_wallet_transactions_for_user($user_id, $limit);
}

function ms_get_units_count_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = "SELECT COUNT(id) FROM $unit_tbl";
        $total = intval($wpdb->get_var($sql));
    } else {
        $sql = $wpdb->prepare("SELECT COUNT(u.id) FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d", $manager_id);
        $total = intval($wpdb->get_var($sql));
    }

    if ($total > 0) {
        return $total;
    }

    if (function_exists('ms_get_legacy_manager_property_count')) {
        return ms_get_legacy_manager_property_count($manager_id);
    }

    return 0;
}

function ms_get_active_maintenance_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $maint_tbl = $wpdb->prefix . 'ms_maintenance_requests';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = $wpdb->prepare("SELECT COUNT(id) FROM $maint_tbl WHERE status != %s", 'closed');
        $count = intval($wpdb->get_var($sql));
    } else {
        $sql = $wpdb->prepare("SELECT COUNT(m.id) FROM $maint_tbl m JOIN $build_tbl b ON (m.building_id = b.id OR (b.wp_post_id > 0 AND m.building_id = b.wp_post_id)) WHERE b.manager_id = %d AND m.status != %s", $manager_id, 'closed');
        $count = intval($wpdb->get_var($sql));
    }

    if ($count > 0) {
        return $count;
    }

    if (function_exists('ms_get_legacy_building_ids_for_manager') && function_exists('ms_get_legacy_expenses_count_by_building_ids')) {
        $building_ids = ms_get_legacy_building_ids_for_manager($manager_id);
        if (!empty($building_ids)) {
            return ms_get_legacy_expenses_count_by_building_ids($building_ids);
        }
    }

    return 0;
}

function ms_get_invoices_count_by_user_and_status($user_id, $status)
{
    $invoices = ms_get_user_invoices($user_id);
    $count = 0;

    foreach ($invoices as $invoice) {
        if (isset($invoice->status) && $invoice->status === $status) {
            $count++;
        }
    }

    return $count;
}

function ms_get_invoices_count_by_user_status_and_property($user_id, $status, $property_id)
{
    $invoices = ms_get_user_invoices($user_id);
    $count = 0;
    $property_id = absint($property_id);

    foreach ($invoices as $invoice) {
        if (!isset($invoice->status) || $invoice->status !== $status) {
            continue;
        }

        if (!isset($invoice->property_id) || intval($invoice->property_id) !== $property_id) {
            continue;
        }

        $count++;
    }

    return $count;
}

function ms_get_paid_invoices_count_for_manager($manager_id)
{
    if (function_exists('ms_get_manager_invoices')) {
        $invoices = ms_get_manager_invoices($manager_id, -1, 'maintenance');
        $paid_count = 0;
        foreach ($invoices as $invoice) {
            if (isset($invoice->status) && $invoice->status === 'paid') {
                $paid_count++;
            }
        }
        return $paid_count;
    }

    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = $wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE status = %s AND invoice_type = %s", 'paid', 'maintenance');
        $paid_count = intval($wpdb->get_var($sql));
    } else {
        // get owners for manager's buildings
        $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
        $paid_count = 0;

        if (!empty($owners)) {
            $placeholders = implode(',', array_fill(0, count($owners), '%d'));
            $query = "SELECT COUNT(id) FROM $inv_tbl WHERE status = 'paid' AND invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared = $wpdb->prepare($query, $owners);
            $paid_count = intval($wpdb->get_var($prepared));
        }
    }

    if (function_exists('ms_get_legacy_manager_paid_invoice_stats')) {
        $legacy = ms_get_legacy_manager_paid_invoice_stats($manager_id);
        $paid_count += intval($legacy['paid_count']);
    }

    return $paid_count;
}

function ms_get_collection_stats_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql_total = $wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE invoice_type = %s", 'maintenance');
        $sql_collected = $wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE status = %s AND invoice_type = %s", 'paid', 'maintenance');
        $total_due = floatval($wpdb->get_var($sql_total));
        $total_collected = floatval($wpdb->get_var($sql_collected));
    } else {
        $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
        $total_due = 0;
        $total_collected = 0;

        if (!empty($owners)) {
            $placeholders = implode(',', array_fill(0, count($owners), '%d'));
            $query_total = "SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared_total = $wpdb->prepare($query_total, $owners);
            $total_due = floatval($wpdb->get_var($prepared_total));

            $query_collected = "SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE status = 'paid' AND invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared_collected = $wpdb->prepare($query_collected, $owners);
            $total_collected = floatval($wpdb->get_var($prepared_collected));
        }
    }

    if (function_exists('ms_get_legacy_invoice_totals_for_manager')) {
        $legacy = ms_get_legacy_invoice_totals_for_manager($manager_id);
        $total_due += floatval($legacy['total_due']);
        $total_collected += floatval($legacy['total_collected']);
    }

    $percent = $total_due > 0 ? round(($total_collected / $total_due) * 100, 2) : 0;

    return array('total_due' => $total_due, 'total_collected' => $total_collected, 'percent' => $percent);
}

function ms_get_paid_unpaid_units_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    // total units
    $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(u.id) FROM $unit_tbl u JOIN $build_tbl b ON u.building_id=b.id WHERE b.manager_id=%d", $manager_id)));

    if ($total === 0 && function_exists('ms_get_legacy_manager_property_count')) {
        $total = ms_get_legacy_manager_property_count($manager_id);
    }

    // paid units heuristic: owner has no pending/overdue invoices
    $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
    $paid_units = 0;

    if (empty($owners) && function_exists('ms_get_legacy_manager_owner_ids')) {
        $owners = ms_get_legacy_manager_owner_ids($manager_id);
    }

    if (!empty($owners)) {
        foreach ($owners as $owner_id) {
            $cnt = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE user_id=%d AND (status='pending' OR status='overdue')", $owner_id)));
            if (function_exists('ms_get_legacy_invoices_count_for_user')) {
                $cnt += ms_get_legacy_invoices_count_for_user($owner_id, 'pending');
                $cnt += ms_get_legacy_invoices_count_for_user($owner_id, 'overdue');
            }
            if ($cnt == 0) {
                if (function_exists('ms_get_legacy_manager_property_ids')) {
                    $legacy_property_ids = ms_get_legacy_manager_property_ids($manager_id);
                    foreach ($legacy_property_ids as $property_id) {
                        $property_owner = absint(get_post_field('post_author', $property_id));
                        if ($property_owner === absint($owner_id)) {
                            $paid_units++;
                        }
                    }
                }

                $owner_units = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $unit_tbl WHERE owner_id = %d", $owner_id)));
                $paid_units += $owner_units;
            }
        }
    }

    $unpaid = max(0, $total - $paid_units);
    return array('total' => $total, 'paid' => $paid_units, 'unpaid' => $unpaid);
}

function ms_get_manager_invoices($manager_id, $limit = 20, $invoice_type = 'maintenance', $building_id = 0)
{
    global $wpdb;

    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $building_table = $wpdb->prefix . 'ms_buildings';
    $params = array();

    $building_id = absint($building_id);

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = "SELECT i.* FROM {$invoice_table} i";
        $where_added = false;
        if (!empty($invoice_type)) {
            $sql .= " WHERE i.invoice_type = %s";
            $params[] = $invoice_type;
            $where_added = true;
        }
        if ($building_id) {
            $sql .= $where_added ? " AND i.building_id = %d" : " WHERE i.building_id = %d";
            $params[] = $building_id;
        }
        $sql .= " ORDER BY i.created_at DESC LIMIT %d";
        $params[] = $limit;
    } else {
        $sql = "SELECT i.* FROM {$invoice_table} i INNER JOIN {$building_table} b ON (b.id = i.building_id OR (b.wp_post_id > 0 AND b.wp_post_id = i.building_id)) WHERE b.manager_id = %d";
        $params[] = $manager_id;
        if (!empty($invoice_type)) {
            $sql .= " AND i.invoice_type = %s";
            $params[] = $invoice_type;
        }
        if ($building_id) {
            if (function_exists('ms_get_building_id_values_for_query')) {
                $building_values = ms_get_building_id_values_for_query($building_id);
            } else {
                $building_values = array($building_id);
            }
            if (!empty($building_values)) {
                $placeholders = implode(',', array_fill(0, count($building_values), '%d'));
                $sql .= " AND i.building_id IN ($placeholders)";
                $params = array_merge($params, $building_values);
            }
        }
        $sql .= " ORDER BY i.created_at DESC LIMIT %d";
        $params[] = $limit;
    }

    $rows = $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params)));

    $legacy = array();
    if (function_exists('ms_get_legacy_invoices_for_manager')) {
        $legacy = ms_get_legacy_invoices_for_manager($manager_id, $limit);
    }

    $all = array_merge($rows ?: array(), $legacy ?: array());
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

function ms_get_owner_revenue_summary($owner_id)
{
    global $wpdb;
    $owner_id = absint($owner_id);
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    $scope = ms_get_owner_invoice_scope($owner_id);
    $total_paid = 0.0;
    $total_due = 0.0;
    $upcoming = null;

    if (!empty($scope)) {
        $total_paid = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE {$scope['where']} AND status='paid'", ...$scope['params'])));
        $total_due = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE {$scope['where']}", ...$scope['params'])));
        $upcoming = $wpdb->get_row($wpdb->prepare("SELECT id,amount,due_date FROM $inv_tbl WHERE {$scope['where']} AND status!='paid' ORDER BY due_date ASC LIMIT 1", ...$scope['params']));
    }

    if (function_exists('ms_get_legacy_owner_revenue_summary')) {
        $legacy = ms_get_legacy_owner_revenue_summary($owner_id);
        $total_paid += floatval($legacy['total_paid']);
        $total_due += floatval($legacy['total_due']);

        if (!$upcoming) {
            $upcoming = $legacy['next_due'];
        } elseif (!empty($legacy['next_due']) && !empty($legacy['next_due']->due_date)) {
            $legacy_due = strtotime($legacy['next_due']->due_date);
            $current_due = !empty($upcoming->due_date) ? strtotime($upcoming->due_date) : PHP_INT_MAX;
            if ($legacy_due < $current_due) {
                $upcoming = $legacy['next_due'];
            }
        }
    }

    return array('total_paid' => $total_paid, 'total_due' => $total_due, 'next_due' => $upcoming);
}

function ms_get_user_overdue_count($user_id)
{
    $user_id = absint($user_id);
    if (!$user_id) {
        return 0;
    }

    if (function_exists('ms_get_user_invoices')) {
        $invoices = ms_get_user_invoices($user_id);
        $count = 0;
        $today = strtotime(current_time('mysql'));

        foreach ($invoices as $invoice) {
            $status = isset($invoice->status) ? $invoice->status : '';
            $due_date = !empty($invoice->due_date) ? strtotime($invoice->due_date) : 0;

            if ($status === 'overdue' || ($status !== 'paid' && $due_date > 0 && $due_date < $today)) {
                $count++;
            }
        }

        return $count;
    }

    global $wpdb;
    $inv_tbl = $wpdb->prefix . 'ms_invoices';
    $today = current_time('mysql');
    $sql = $wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE user_id=%d AND (status='overdue' OR (status!='paid' AND due_date < %s))", $user_id, $today);
    $count = intval($wpdb->get_var($sql));

    if (function_exists('ms_get_legacy_user_overdue_count')) {
        $count += ms_get_legacy_user_overdue_count($user_id);
    }

    return $count;
}

function ms_get_building_wallet($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_building_wallet';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE building_id=%d LIMIT 1", $building_id));
    if ($row) {
        return $row;
    }

    $inserted = $wpdb->insert($table, [
        'building_id' => $building_id,
        'balance' => 0.00,
        'target_amount' => 0.00,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d', '%f', '%f', '%s', '%s', '%s']);

    if ($inserted === false) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE building_id=%d LIMIT 1", $building_id));
}

function ms_update_building_wallet_balance($building_id, $amount)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = floatval($amount);
    if (!$building_id || $amount == 0) {
        return false;
    }

    ms_get_building_wallet($building_id);

    $table = $wpdb->prefix . 'ms_building_wallet';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET balance = balance + %f, updated_at=%s WHERE building_id=%d",
        $amount,
        current_time('mysql'),
        $building_id
    ));
}

function ms_update_building_wallet_target($building_id, $amount)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = floatval($amount);
    if (!$building_id) {
        return false;
    }

    ms_get_building_wallet($building_id);

    $table = $wpdb->prefix . 'ms_building_wallet';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET target_amount = target_amount + %f, updated_at=%s WHERE building_id=%d",
        $amount,
        current_time('mysql'),
        $building_id
    ));
}

function ms_get_building_wallet_transactions($building_id, $limit = 20)
{
    global $wpdb;
    $building_id = absint($building_id);
    $limit = absint($limit);
    if (!$building_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_building_wallet_transactions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE building_id=%d ORDER BY created_at DESC LIMIT %d",
        $building_id,
        $limit > 0 ? $limit : 20
    ));
}

function ms_get_properties_by_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    // If the Mostaager building row is linked to a WP post, prefer that post ID
    $linked_wp_id = ms_get_linked_wp_post_id_for_building($building_id);
    $meta_values = array();
    if ($linked_wp_id) {
        $meta_values[] = intval($linked_wp_id);
    }
    // Also include the original value as a fallback (in case legacy data stored ms_buildings.id)
    $meta_values[] = $building_id;

    if (post_type_exists('property')) {
        $meta_query = array('relation' => 'OR');
        foreach ($meta_values as $val) {
            $meta_query[] = array(
                'key' => 'building_id',
                'value' => $val,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
        }

        $args = [
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => $meta_query,
            'fields' => 'all',
        ];
        return get_posts($args);
    }

    return [];
}

function ms_get_property_contacts($property_id)
{
    $property_id = absint($property_id);
    if (!$property_id) {
        return [];
    }

    $owner_id = absint(get_post_meta($property_id, 'owner_id', true));
    if (!$owner_id) {
        $owner_id = absint(get_post_meta($property_id, 'property_owner', true));
    }
    if (!$owner_id) {
        $owner_id = absint(get_post_meta($property_id, 'ms_property_owner_id', true));
    }
    if (!$owner_id) {
        $owner_id = absint(get_post_field('post_author', $property_id));
    }

    $agent_id = absint(get_post_meta($property_id, 'fave_agents', true));
    if (!$agent_id) {
        $agent_id = absint(get_post_meta($property_id, 'fave_property_agent', true));
    }

    $tenant_id = absint(get_post_meta($property_id, 'tenant_id', true));

    return [
        'owner' => $owner_id ? get_userdata($owner_id) : null,
        'agent' => $agent_id ? get_userdata($agent_id) : null,
        'tenant' => $tenant_id ? get_userdata($tenant_id) : null,
    ];
}

function ms_add_notification($user_id, $type, $message, $building_id = 0, $related_id = 0)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $type = sanitize_text_field($type);
    $message = wp_kses_post($message);
    $building_id = absint($building_id);
    $related_id = absint($related_id);

    $table = $wpdb->prefix . 'ms_notifications';

    return (bool) $wpdb->insert($table, [
        'user_id' => $user_id,
        'type' => $type,
        'message' => $message,
        'building_id' => $building_id,
        'related_id' => $related_id,
        'is_read' => 0,
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%d', '%d', '%d', '%s']);
}

function ms_get_notifications_by_user($user_id, $limit = 20)
{
    global $wpdb;
    $user_id = absint($user_id);
    $limit = absint($limit);
    if (!$user_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit > 0 ? $limit : 20
    ));
}

function ms_get_unread_notifications_count($user_id)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return 0;
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table WHERE user_id=%d AND is_read=0",
        $user_id
    )));
}

function ms_mark_notifications_read($user_id)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET is_read=1 WHERE user_id=%d",
        $user_id
    ));
}

function ms_get_buildings_by_manager_cpt($user_id)
{
    $user_id = absint($user_id);
    if (!$user_id) {
        return [];
    }

    // legacy fallback: ms_buildings table is preferred by current code.
    // but we provide CPT lookup for spec.
    $q = new WP_Query([
        'post_type' => 'building',
        'post_status' => ['publish', 'pending', 'draft', 'private', 'future', 'expired'],
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'manager_id',
                'value' => $user_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    return $q->have_posts() ? $q->posts : [];
}

