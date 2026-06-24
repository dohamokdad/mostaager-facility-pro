<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostaager Utility Bills API
 * REST API endpoints for operational utility bills + distribution
 */
class Mostager_Utility_Bills_API
{
    private $namespace = 'mostager/v1';

    public function register_routes()
    {
        register_rest_route($this->namespace, '/utility-bills', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_utility_bills'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'building_id' => ['type' => 'integer', 'required' => true],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_utility_bill'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'building_id' => ['type' => 'integer', 'required' => true],
                    'title' => ['type' => 'string', 'required' => true],
                    'bill_type' => ['type' => 'string', 'required' => false],
                    'total_amount' => ['type' => 'number', 'required' => true],
                    'billing_period_start' => ['type' => 'string', 'required' => false],
                    'billing_period_end' => ['type' => 'string', 'required' => false],
                    'distribution_method' => ['type' => 'string', 'required' => false],
                    'notes' => ['type' => 'string', 'required' => false],
                    'auto_distribute' => ['type' => 'boolean', 'required' => false],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/utility-bills/(?P<id>\\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_utility_bill'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/utility-bills/(?P<id>\\d+)/distribute', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'distribute_utility_bill'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'distribution_method' => ['type' => 'string', 'required' => false],
                ],
            ],
        ]);
    }

    public function check_permission()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            return true;
        }

        if (function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'building_manager')) {
            return true;
        }

        return false;
    }

    private function normalize_date($value)
    {
        $value = is_string($value) ? trim($value) : '';
        if (!$value) {
            return null;
        }
        // Accept YYYY-MM-DD
        $ts = strtotime($value);
        if (!$ts) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function get_units_for_building(int $building_id): array
    {
        global $wpdb;
        $units = [];

        // Prefer unified wrapper if exists
        if (function_exists('ms_get_units_by_building')) {
            $units = ms_get_units_by_building($building_id);
        }

        if (!empty($units)) {
            return $units;
        }

        // Fallback to ms_units table
        $table = $wpdb->prefix . 'ms_units';
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE building_id = %d", $building_id);
        $units = $wpdb->get_results($sql);

        return is_array($units) ? $units : [];
    }

    private function resolve_payer_user_id($unit, string $payer_type): int
    {
        // payer_type: owner|tenant|agent|owner_or_tenant
        $payer_type = strtolower(trim($payer_type));

        if ($payer_type === 'owner') {
            return intval($unit->owner_id ?? 0);
        }
        if ($payer_type === 'tenant') {
            return intval($unit->tenant_id ?? 0);
        }
        if ($payer_type === 'agent') {
            return intval($unit->agent_id ?? 0);
        }
        // owner_or_tenant
        $tenant = intval($unit->tenant_id ?? 0);
        if ($tenant) {
            return $tenant;
        }
        return intval($unit->owner_id ?? 0);
    }

    private function create_invoice_for_bill_item($bill, $unit, float $amount, string $payer_type): int
    {
        global $wpdb;

        $user_id = $this->resolve_payer_user_id($unit, $payer_type);
        if (!$user_id || $amount <= 0) {
            return 0;
        }

        $invoice_table = $wpdb->prefix . 'ms_invoices';
        $due_date = $this->normalize_date($bill->billing_period_end) ?: date('Y-m-d', strtotime('+14 days'));
        $description = sprintf('Utility bill #%d - %s', intval($bill->id ?? 0), sanitize_text_field($bill->title ?? 'Utility charge'));
        $inserted = $wpdb->insert($invoice_table, [
            'user_id' => $user_id,
            'building_id' => intval($bill->building_id),
            'unit_id' => intval($unit->id ?? 0),
            'expense_id' => 0,
            'description' => $description,
            'amount' => $amount,
            'status' => 'pending',
            'due_date' => $due_date,
            'paid_date' => null,
            'invoice_type' => 'utility_bill',
            'payer_type' => $payer_type,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']);

        if ($inserted === false) {
            return 0;
        }

        $invoice_id = intval($wpdb->insert_id);
        if ($invoice_id > 0) {
            if (function_exists('ms_add_notification')) {
                ms_add_notification($user_id, 'utility_bill_invoice_created', sprintf('تم إنشاء فاتورة مرافق للوحدة #%d', intval($unit->id ?? 0)), intval($bill->building_id), $invoice_id);
            }
            do_action('ms_invoice_created', $invoice_id, [
                'building_id' => intval($bill->building_id),
                'unit_id' => intval($unit->id ?? 0),
                'user_id' => $user_id,
                'amount' => $amount,
                'bill_id' => intval($bill->id ?? 0),
                'invoice_type' => 'utility_bill',
            ]);
        }

        return $invoice_id;
    }

    private function distribute_bill_to_units($bill, string $distribution_method): array
    {
        global $wpdb;

        $bill_id = intval($bill->id ?? 0);
        $building_id = intval($bill->building_id ?? 0);
        $total_amount = floatval($bill->total_amount ?? 0);
        if (!$bill_id || !$building_id || $total_amount <= 0) {
            return ['created' => 0, 'errors' => ['invalid_bill']];
        }

        $item_table = $wpdb->prefix . 'ms_utility_bill_items';
        $units = $this->get_units_for_building($building_id);
        if (empty($units)) {
            return ['created' => 0, 'errors' => ['no_units']];
        }

        $wpdb->delete($item_table, ['bill_id' => $bill_id], ['%d']);

        $eligible_units = [];
        foreach ($units as $unit) {
            $tenant_id = intval($unit->tenant_id ?? 0);
            if ($tenant_id > 0) {
                $eligible_units[] = $unit;
            }
        }
        if (empty($eligible_units)) {
            $eligible_units = $units;
        }

        $count = count($eligible_units);
        if ($count <= 0) {
            return ['created' => 0, 'errors' => ['no_units']];
        }

        $created = 0;
        if ($distribution_method === 'equal') {
            $per_unit = round($total_amount / $count, 2);
            foreach ($eligible_units as $idx => $unit) {
                $amount = ($idx === $count - 1) ? round($total_amount - ($per_unit * ($count - 1)), 2) : $per_unit;
                $tenant_id = intval($unit->tenant_id ?? 0);
                $owner_id = intval($unit->owner_id ?? 0);
                $payer_user_id = $tenant_id ? $tenant_id : $owner_id;
                if (!$payer_user_id) {
                    continue;
                }
                $invoice_id = $this->create_invoice_for_bill_item($bill, $unit, $amount, $tenant_id ? 'tenant' : 'owner');
                if ($invoice_id <= 0) {
                    continue;
                }
                $wpdb->insert($item_table, [
                    'bill_id' => $bill_id,
                    'unit_id' => intval($unit->id ?? 0),
                    'tenant_id' => $tenant_id,
                    'owner_id' => $owner_id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'invoice_id' => $invoice_id,
                    'created_at' => current_time('mysql'),
                ], ['%d', '%d', '%d', '%d', '%f', '%s', '%d', '%s']);
                if (intval($wpdb->insert_id) > 0) {
                    $created++;
                }
            }
        } else {
            $shares = [];
            $total_shares = 0.0;
            foreach ($eligible_units as $unit) {
                $share = 1.0;
                if (!empty($unit->utility_share)) {
                    $share = floatval($unit->utility_share);
                } elseif (!empty($unit->share)) {
                    $share = floatval($unit->share);
                } elseif (!empty($unit->unit_share)) {
                    $share = floatval($unit->unit_share);
                } elseif ($distribution_method === 'by_size') {
                    if (!empty($unit->size)) {
                        $share = floatval($unit->size);
                    } elseif (!empty($unit->area)) {
                        $share = floatval($unit->area);
                    } else {
                        $share = 1.0;
                    }
                }
                if ($share <= 0) {
                    $share = 1.0;
                }
                $shares[] = $share;
                $total_shares += $share;
            }

            if ($total_shares <= 0) {
                $per_unit = round($total_amount / $count, 2);
                foreach ($eligible_units as $idx => $unit) {
                    $amount = ($idx === $count - 1) ? round($total_amount - ($per_unit * ($count - 1)), 2) : $per_unit;
                    $tenant_id = intval($unit->tenant_id ?? 0);
                    $owner_id = intval($unit->owner_id ?? 0);
                    $payer_user_id = $tenant_id ? $tenant_id : $owner_id;
                    if (!$payer_user_id) {
                        continue;
                    }
                    $invoice_id = $this->create_invoice_for_bill_item($bill, $unit, $amount, $tenant_id ? 'tenant' : 'owner');
                    $wpdb->insert($item_table, [
                        'bill_id' => $bill_id,
                        'unit_id' => intval($unit->id ?? 0),
                        'tenant_id' => $tenant_id,
                        'owner_id' => $owner_id,
                        'amount' => $amount,
                        'status' => 'pending',
                        'invoice_id' => $invoice_id,
                        'created_at' => current_time('mysql'),
                    ], ['%d', '%d', '%d', '%d', '%f', '%s', '%d', '%s']);
                    if (intval($wpdb->insert_id) > 0) {
                        $created++;
                    }
                }
            } else {
                $allocated = 0.0;
                foreach ($eligible_units as $idx => $unit) {
                    $share = $shares[$idx];
                    $amount = ($idx === $count - 1) ? round($total_amount - $allocated, 2) : round(($total_amount * ($share / $total_shares)), 2);
                    $allocated += $amount;
                    $tenant_id = intval($unit->tenant_id ?? 0);
                    $owner_id = intval($unit->owner_id ?? 0);
                    $payer_user_id = $tenant_id ? $tenant_id : $owner_id;
                    if (!$payer_user_id) {
                        continue;
                    }
                    $invoice_id = $this->create_invoice_for_bill_item($bill, $unit, $amount, $tenant_id ? 'tenant' : 'owner');
                    $wpdb->insert($item_table, [
                        'bill_id' => $bill_id,
                        'unit_id' => intval($unit->id ?? 0),
                        'tenant_id' => $tenant_id,
                        'owner_id' => $owner_id,
                        'amount' => $amount,
                        'status' => 'pending',
                        'invoice_id' => $invoice_id,
                        'created_at' => current_time('mysql'),
                    ], ['%d', '%d', '%d', '%d', '%f', '%s', '%d', '%s']);
                    if (intval($wpdb->insert_id) > 0) {
                        $created++;
                    }
                }
            }
        }

        return ['created' => $created];
    }

    public function get_utility_bills(WP_REST_Request $request)
    {
        global $wpdb;

        $building_id = intval($request->get_param('building_id'));
        if (!$building_id) {
            return new WP_Error('invalid_building_id', 'building_id غير صالح', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'ms_utility_bills';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE building_id = %d ORDER BY created_at DESC LIMIT 200",
                $building_id
            )
        );

        return new WP_REST_Response([
            'success' => true,
            'data' => $rows,
        ], 200);
    }

    public function create_utility_bill(WP_REST_Request $request)
    {
        global $wpdb;

        $building_id = intval($request->get_param('building_id'));
        $title = sanitize_text_field($request->get_param('title'));
        $bill_type = sanitize_text_field($request->get_param('bill_type') ?: 'utility');
        $total_amount = floatval($request->get_param('total_amount'));

        if (!$building_id || $title === '' || $total_amount <= 0) {
            return new WP_Error('invalid_params', 'بيانات غير صالحة', ['status' => 400]);
        }

        $distribution_method = sanitize_text_field($request->get_param('distribution_method') ?: 'equal');
        $allowed_methods = ['equal', 'by_unit', 'by_size'];
        if (!in_array($distribution_method, $allowed_methods, true)) {
            $distribution_method = 'equal';
        }

        $period_start = $this->normalize_date($request->get_param('billing_period_start'));
        $period_end = $this->normalize_date($request->get_param('billing_period_end'));
        $notes = sanitize_textarea_field($request->get_param('notes') ?: '');

        $table = $wpdb->prefix . 'ms_utility_bills';

        $wpdb->insert($table, [
            'building_id' => $building_id,
            'title' => $title,
            'bill_type' => $bill_type,
            'total_amount' => $total_amount,
            'billing_period_start' => $period_start,
            'billing_period_end' => $period_end,
            'distribution_method' => $distribution_method,
            'status' => 'draft',
            'created_by' => get_current_user_id(),
            'notes' => $notes,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);

        $bill_id = intval($wpdb->insert_id);
        if (!$bill_id) {
            return new WP_Error('db_error', 'فشل إنشاء فاتورة', ['status' => 500]);
        }

        $auto_distribute = filter_var($request->get_param('auto_distribute'), FILTER_VALIDATE_BOOLEAN);
        $result = null;

        if ($auto_distribute) {
            $bill = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $bill_id));
            if ($bill) {
                $result = $this->distribute_bill_to_units($bill, $distribution_method);
                if (empty($result['errors'])) {
                    $wpdb->update($table, [
                        'status' => 'distributed',
                        'updated_at' => current_time('mysql'),
                    ], ['id' => $bill_id], ['%s', '%s'], ['%d']);
                }
            }
        }

        $response_data = [
            'id' => $bill_id,
            'status' => $auto_distribute && empty($result['errors']) ? 'distributed' : 'draft',
        ];

        if ($auto_distribute) {
            $response_data['created_items'] = intval($result['created'] ?? 0);
            if (!empty($result['errors'])) {
                $response_data['errors'] = $result['errors'];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $response_data,
        ], 201);
    }

    public function get_utility_bill(WP_REST_Request $request)
    {
        global $wpdb;

        $bill_id = intval($request->get_param('id'));
        if (!$bill_id) {
            return new WP_Error('invalid_bill_id', 'id غير صالح', ['status' => 400]);
        }

        $bill_table = $wpdb->prefix . 'ms_utility_bills';
        $item_table = $wpdb->prefix . 'ms_utility_bill_items';

        $bill = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bill_table} WHERE id = %d", $bill_id));
        if (!$bill) {
            return new WP_Error('not_found', 'فاتورة غير موجودة', ['status' => 404]);
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$item_table} WHERE bill_id = %d ORDER BY id ASC",
            $bill_id
        ));

        $bill->items = $items;

        return new WP_REST_Response([
            'success' => true,
            'data' => $bill,
        ], 200);
    }

    public function distribute_utility_bill(WP_REST_Request $request)
    {
        global $wpdb;

        $bill_id = intval($request->get_param('id'));
        if (!$bill_id) {
            return new WP_Error('invalid_bill_id', 'id غير صالح', ['status' => 400]);
        }

        $bill_table = $wpdb->prefix . 'ms_utility_bills';
        $bill = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bill_table} WHERE id = %d", $bill_id));
        if (!$bill) {
            return new WP_Error('not_found', 'فاتورة غير موجودة', ['status' => 404]);
        }

        $distribution_method = sanitize_text_field($request->get_param('distribution_method') ?: ($bill->distribution_method ?? 'equal'));
        $allowed_methods = ['equal', 'by_unit', 'by_size'];
        if (!in_array($distribution_method, $allowed_methods, true)) {
            $distribution_method = 'equal';
        }

        $result = $this->distribute_bill_to_units($bill, $distribution_method);
        if (!empty($result['errors'])) {
            return new WP_Error('distribution_failed', 'فشل توزيع الفاتورة', ['status' => 400, 'errors' => $result['errors']]);
        }

        $wpdb->update($bill_table, [
            'distribution_method' => $distribution_method,
            'status' => 'distributed',
            'updated_at' => current_time('mysql'),
        ], ['id' => $bill_id], ['%s', '%s', '%s'], ['%d']);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'bill_id' => $bill_id,
                'created_items' => intval($result['created'] ?? 0),
                'distribution_method' => $distribution_method,
                'status' => 'distributed',
            ],
        ], 200);
    }
}

add_action('rest_api_init', function () {
    $api = new Mostager_Utility_Bills_API();
    $api->register_routes();
});

