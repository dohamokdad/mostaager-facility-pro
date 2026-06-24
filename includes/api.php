<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php';
}

add_action('rest_api_init', function () {
    register_rest_route('ms/v1', '/facilities', array(
        'methods' => 'GET',
        'callback' => 'ms_rest_get_facilities',
        'permission_callback' => 'ms_rest_user_can_view_dashboard',
    ));

    register_rest_route('ms/v1', '/invoices', array(
        'methods' => 'GET',
        'callback' => 'ms_rest_get_invoices',
        'permission_callback' => 'ms_rest_user_can_view_dashboard',
        'args' => array(
            'building_id' => array(
                'required' => false,
                'validate_callback' => function ($param) {
                    return empty($param) || is_numeric($param);
                },
            ),
            'status' => array(
                'required' => false,
            ),
        ),
    ));

    register_rest_route('ms/v1', '/expenses', array(
        'methods' => 'POST',
        'callback' => 'ms_rest_create_expense',
        'permission_callback' => 'ms_rest_user_can_manage_expenses',
        'args' => array(
            'building_id' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && intval($param) > 0;
                },
            ),
            'total_amount' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && floatval($param) > 0;
                },
            ),
            'title' => array(
                'required' => false,
            ),
            'description' => array(
                'required' => false,
            ),
            'expense_type' => array(
                'required' => false,
            ),
        ),
    ));
});

function ms_rest_user_can_view_dashboard()
{
    return is_user_logged_in() && current_user_can('ms_view_dashboard');
}

function ms_rest_user_can_manage_expenses()
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    return function_exists('ms_user_has_role') && ms_user_has_role(get_current_user_id(), 'building_manager');
}

function ms_rest_get_facilities($request)
{
    $db = Mostaager_DB::get_instance();
    $user_id = get_current_user_id();
    $buildings = $db->get_facilities_by_manager($user_id);
    $data = array();

    foreach ((array) $buildings as $building) {
        $data[] = array(
            'id' => intval($building->id ?? 0),
            'title' => sanitize_text_field($building->title ?? $building->name ?? ''),
            'manager_id' => intval($building->manager_id ?? 0),
            'created_at' => isset($building->created_at) ? $building->created_at : '',
        );
    }

    return rest_ensure_response($data);
}

function ms_rest_get_invoices($request)
{
    $db = Mostaager_DB::get_instance();
    $user_id = get_current_user_id();
    $params = $request->get_params();
    $building_id = isset($params['building_id']) ? intval($params['building_id']) : 0;
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

    $invoices = array();
    if (current_user_can('manage_options') || (function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'mostaager_manager'))) {
        $invoices = $db->get_manager_invoices($user_id, 100, $building_id);
    } elseif (function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'building_manager')) {
        if ($building_id && !$db->current_user_manages_building($user_id, $building_id)) {
            return new WP_Error('rest_forbidden', __('ليس لديك صلاحية على هذا المبنى.', 'mostaager-facility-pro'), array('status' => 403));
        }
        $invoices = $db->get_manager_invoices($user_id, 100, $building_id);
    } else {
        $invoices = $db->get_user_invoices($user_id, 100);
    }

    if (!empty($status)) {
        $status = strtolower($status);
        $invoices = array_filter($invoices, function ($invoice) use ($status) {
            return strtolower(trim($invoice->status ?? '')) === $status;
        });
    }

    $data = array();
    foreach ((array) $invoices as $invoice) {
        $data[] = array(
            'id' => intval($invoice->id ?? 0),
            'building_id' => intval($invoice->building_id ?? 0),
            'property_id' => intval($invoice->property_id ?? 0),
            'amount' => floatval($invoice->amount ?? $invoice->amount_due ?? 0),
            'status' => sanitize_text_field($invoice->status ?? ''),
            'invoice_type' => sanitize_text_field($invoice->invoice_type ?? ''),
            'created_at' => $invoice->created_at ?? '',
            'due_date' => $invoice->due_date ?? '',
        );
    }

    return rest_ensure_response($data);
}

function ms_rest_create_expense($request)
{
    $params = $request->get_json_params();
    if (empty($params) || !is_array($params)) {
        $params = $request->get_body_params();
    }

    $building_id = intval($params['building_id'] ?? 0);
    $total_amount = floatval($params['total_amount'] ?? 0);
    $title = sanitize_text_field($params['title'] ?? 'Expense for building ' . $building_id);
    $description = sanitize_textarea_field($params['description'] ?? '');
    $expense_type = sanitize_text_field($params['expense_type'] ?? 'maintenance');

    if (!$building_id || $total_amount <= 0) {
        return new WP_Error('invalid_data', __('يرجى تقديم معرف المبنى والمبلغ الإجمالي الصحيح.', 'mostaager-facility-pro'), array('status' => 400));
    }

    if (!post_type_exists('expenses')) {
        return new WP_Error('missing_post_type', __('نوع المنشور expenses غير موجود.', 'mostaager-facility-pro'), array('status' => 500));
    }

    if (!current_user_can('manage_options') && function_exists('ms_current_user_manages_building') && !ms_current_user_manages_building(get_current_user_id(), $building_id)) {
        return new WP_Error('rest_forbidden', __('ليس لديك صلاحية على هذا المبنى.', 'mostaager-facility-pro'), array('status' => 403));
    }

    $expense_id = wp_insert_post(array(
        'post_type' => 'expenses',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $description,
        'post_author' => get_current_user_id(),
    ));

    if (is_wp_error($expense_id) || !$expense_id) {
        return new WP_Error('insert_failed', __('تعذر إنشاء طلب المصاريف.', 'mostaager-facility-pro'), array('status' => 500));
    }

    update_post_meta($expense_id, 'building_id', $building_id);
    update_post_meta($expense_id, 'total_amount', $total_amount);
    update_post_meta($expense_id, 'expense_type', $expense_type);
    update_post_meta($expense_id, 'status', 'open');

    if (function_exists('update_field')) {
        update_field('building_id', $building_id, $expense_id);
        update_field('total_amount', $total_amount, $expense_id);
        update_field('expense_type', $expense_type, $expense_id);
        update_field('status', 'open', $expense_id);
    }

    $generated = 0;
    if (function_exists('mostaager_generate_invoices_from_expense')) {
        $generated = mostaager_generate_invoices_from_expense($expense_id);
    }

    return rest_ensure_response(array(
        'expense_id' => intval($expense_id),
        'generated_invoices' => intval($generated),
    ));
}
