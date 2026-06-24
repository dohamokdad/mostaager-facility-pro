<?php
/**
 * Mostager Facilities Pro - Facilities Registry API
 * REST API endpoints for facility registry + status
 *
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Mostager_Facilities_API {

    private $namespace = 'mostager/v1';

    public function register_routes() {
        register_rest_route($this->namespace, '/facilities', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_facilities'],
                'permission_callback' => function(WP_REST_Request $request) {
                    return $this->check_permission($request);
                },
                'args' => [
                    'building_id' => ['type' => 'integer', 'required' => true],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/facilities/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_facilities_status'],
                'permission_callback' => function(WP_REST_Request $request) {
                    return $this->check_permission($request);
                },
                'args' => [
                    'building_id' => ['type' => 'integer', 'required' => true],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/facilities/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_facility'],
                'permission_callback' => function(WP_REST_Request $request) {
                    return $this->check_permission($request);
                },
            ],
        ]);
    }


    public function get_facilities(WP_REST_Request $request) {
        global $wpdb;


        $building_id = intval($request->get_param('building_id'));
        if (!$building_id) {
            return new WP_Error('invalid_building_id', 'building_id غير صالح', ['status' => 400]);
        }

        $company = ms_get_company_clause('f');

        $facilities_table = $wpdb->prefix . 'ms_facilities';
        $types_table = $wpdb->prefix . 'ms_facility_types';

        $sql = "SELECT f.*, ft.name AS facility_type_name
            FROM {$facilities_table} f
            LEFT JOIN {$types_table} ft ON f.facility_type_id = ft.id
            WHERE f.building_id = %d AND {$company['clause']}
            ORDER BY f.status ASC, f.created_at DESC";

        $facilities = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $building_id,
                $company['value']
            )
        );

        // Compute derived status from maintenance requests.
        // Mapping: open/in_progress/waiting_parts => under_maintenance, completed => working.
        $requests_table = $wpdb->prefix . 'ms_maintenance_requests';

        foreach ($facilities as $facility) {
            $latest = $wpdb->get_row($wpdb->prepare(
                "SELECT status, updated_at
                 FROM {$requests_table}
                 WHERE facility_id = %d AND company_id = %d
                 ORDER BY updated_at DESC
                 LIMIT 1",
                intval($facility->id),
                intval($company['value'])
            ));

            $facility->maintenance_status = $this->map_status_to_derived($facility, $latest ? $latest->status : null);
            $facility->latest_maintenance_status = $latest ? $latest->status : null;
            $facility->latest_maintenance_updated_at = $latest ? $latest->updated_at : null;
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $facilities,
        ], 200);
    }

    public function get_facility(WP_REST_Request $request) {
        global $wpdb;


        $facility_id = intval($request->get_param('id'));

        if (!$facility_id) {
            return new WP_Error('invalid_facility_id', 'facility_id غير صالح', ['status' => 400]);
        }

        $company = ms_get_company_clause('f');

        $facilities_table = $wpdb->prefix . 'ms_facilities';
        $types_table = $wpdb->prefix . 'ms_facility_types';
        $requests_table = $wpdb->prefix . 'ms_maintenance_requests';

        $sql = "SELECT f.*, ft.name AS facility_type_name
            FROM {$facilities_table} f
            LEFT JOIN {$types_table} ft ON f.facility_type_id = ft.id
            WHERE f.id = %d AND {$company['clause']}";

        $facility = $wpdb->get_row($wpdb->prepare($sql, $facility_id, $company['value']));
        if (!$facility) {
            return new WP_Error('not_found', 'المرفق غير موجود', ['status' => 404]);
        }

        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT status, updated_at
             FROM {$requests_table}
             WHERE facility_id = %d AND company_id = %d
             ORDER BY updated_at DESC
             LIMIT 1",
            $facility_id,
            intval($company['value'])
        ));

        $facility->maintenance_status = $this->map_status_to_derived($facility, $latest ? $latest->status : null);
        $facility->latest_maintenance_status = $latest ? $latest->status : null;
        $facility->latest_maintenance_updated_at = $latest ? $latest->updated_at : null;

        return new WP_REST_Response([
            'success' => true,
            'data' => $facility,
        ], 200);
    }

    /**
     * الحصول على قائمة المرافق لمبنى محدد مع حالتها المشتقة
     * 
     * Endpoint: GET /mostager/v1/facilities/status?building_id=X
     * 
     * @param WP_REST_Request $request طلب REST يحتوي على building_id
     * @return WP_REST_Response|WP_Error قائمة المرافق مع الحالة المشتقة
     * 
     * @since 2.0
     */
    public function get_facilities_status(WP_REST_Request $request)
    {
        global $wpdb;

        $building_id = intval($request->get_param('building_id'));
        if (!$building_id) {
            return new WP_Error('invalid_building_id', 'building_id غير صالح', ['status' => 400]);
        }

        $company = ms_get_company_clause('f');

        $facilities_table = $wpdb->prefix . 'ms_facilities';
        $types_table = $wpdb->prefix . 'ms_facility_types';
        $requests_table = $wpdb->prefix . 'ms_maintenance_requests';

        $sql = "SELECT f.*, ft.name AS facility_type_name
            FROM {$facilities_table} f
            LEFT JOIN {$types_table} ft ON f.facility_type_id = ft.id
            WHERE f.building_id = %d AND {$company['clause']}
            ORDER BY f.created_at DESC";

        $facilities = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $building_id,
                $company['value']
            )
        );

        $result = [];

        foreach ($facilities as $facility) {
            $latest = $wpdb->get_row($wpdb->prepare(
                "SELECT status, updated_at, title
                 FROM {$requests_table}
                 WHERE facility_id = %d AND company_id = %d
                 ORDER BY updated_at DESC
                 LIMIT 1",
                intval($facility->id),
                intval($company['value'])
            ));

            $derived = $this->map_status_to_derived($facility, $latest ? $latest->status : null);

            $color = '#10b981'; // working/green
            if ($derived === 'under_maintenance') {
                $color = '#f59e0b';
            } elseif ($derived === 'critical') {
                $color = '#ef4444';
            }

            $result[] = [
                'facility_id' => intval($facility->id),
                'facility_name' => $facility->facility_type_name ?? ($facility->title ?? ''),
                'facility_type_name' => $facility->facility_type_name ?? '',
                'base_status' => $facility->status ?? '',
                'derived_status' => $derived,
                'color' => $color,
                'latest_request_title' => $latest ? ($latest->title ?? null) : null,
                'latest_request_status' => $latest ? $latest->status : null,
                'latest_request_updated_at' => $latest ? $latest->updated_at : null,
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);
    }



    private function map_status_to_derived($facility, $maintenance_status) {
        // Base facility status from ms_facilities.status

        $base = $facility && isset($facility->status) ? $facility->status : 'working';

        if ($maintenance_status) {
            if (in_array($maintenance_status, ['completed'], true)) {
                return 'working';
            }

            // Any non-completed ticket means under maintenance.
            if (in_array($maintenance_status, ['cancelled'], true)) {
                return $base === 'active' ? 'working' : $base;
            }

            return 'under_maintenance';
        }

        // If no maintenance tickets exist yet, use facility status.
        if ($base === 'active') return 'working';
        if ($base === 'working') return 'working';
        return $base;
    }

    public function check_permission(WP_REST_Request $request = null) {
        // Require user to be logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        // Site admins have full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // For building managers, verify they manage the requested building
        if (function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'building_manager')) {
            if ($request) {
                $building_id = intval($request->get_param('building_id'));
                if ($building_id && function_exists('ms_current_user_manages_building')) {
                    return ms_current_user_manages_building($user_id, $building_id);
                }
            }
            return true; // Allow if building_id not specified
        }
        
        return false;
    }
}

add_action('rest_api_init', function () {
    $api = new Mostager_Facilities_API();
    $api->register_routes();
});

