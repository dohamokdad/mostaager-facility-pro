<?php
namespace MostaagerFacilitiesPro\API;

if (!defined('ABSPATH')) {
    exit;
}

class RestApiV2
{
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route('mostaager/v2', '/buildings', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_buildings'],
            'permission_callback' => [__CLASS__, 'verify_jwt_token'],
        ]);

        register_rest_route('mostaager/v2', '/buildings/(?P<id>\d+)/units', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_units'],
            'permission_callback' => [__CLASS__, 'verify_jwt_token'],
        ]);

        register_rest_route('mostaager/v2', '/invoices', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_invoices'],
            'permission_callback' => [__CLASS__, 'verify_jwt_token'],
        ]);

        register_rest_route('mostaager/v2', '/invoices/(?P<id>\d+)/pay', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'pay_invoice'],
            'permission_callback' => [__CLASS__, 'verify_jwt_token'],
        ]);

        register_rest_route('mostaager/v2', '/utility-bills', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_utility_bills'],
                'permission_callback' => [__CLASS__, 'verify_jwt_token'],
                'args' => [
                    'building_id' => ['type' => 'integer', 'required' => true],
                ],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'create_utility_bill'],
                'permission_callback' => [__CLASS__, 'verify_jwt_token'],
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

        register_rest_route('mostaager/v2', '/utility-bills/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_utility_bill'],
                'permission_callback' => [__CLASS__, 'verify_jwt_token'],
            ],
        ]);

        register_rest_route('mostaager/v2', '/utility-bills/(?P<id>\d+)/distribute', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'distribute_utility_bill'],
                'permission_callback' => [__CLASS__, 'verify_jwt_token'],
            ],
        ]);
    }

    public static function verify_jwt_token($request)
    {
        if (!class_exists('MostaagerFacilitiesPro\\API\\RestApi')) {
            return new \WP_Error('jwt_unavailable', 'JWT authentication unavailable', ['status' => 500]);
        }

        return RestApi::verify_jwt_token($request);
    }

    private static function get_jwt_user_id($request)
    {
        return intval($request->get_param('jwt_user_id') ?? 0);
    }

    public static function get_buildings($request)
    {
        $user_id = self::get_jwt_user_id($request);
        if (!$user_id) {
            return self::api_response(false, 'Unauthorized', 401);
        }

        if (!function_exists('ms_get_buildings_by_manager')) {
            return self::api_response(true, []);
        }

        $buildings = ms_get_buildings_by_manager($user_id);
        return self::api_response(true, $buildings);
    }

    public static function get_units($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $building_id = intval($request->get_param('id'));

        if (!$user_id || !$building_id) {
            return self::api_response(false, 'Invalid request', 400);
        }

        if (!function_exists('ms_get_units_by_building')) {
            return self::api_response(true, []);
        }

        $units = ms_get_units_by_building($building_id);
        return self::api_response(true, $units);
    }

    public static function get_invoices($request)
    {
        $user_id = self::get_jwt_user_id($request);
        if (!$user_id) {
            return self::api_response(false, 'Unauthorized', 401);
        }

        if (function_exists('ms_get_manager_invoices') && function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'building_manager')) {
            $invoices = ms_get_manager_invoices($user_id, 100, '');
            return self::api_response(true, $invoices);
        }

        if (function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'owner')) {
            $invoices = function_exists('ms_get_owner_invoices') ? ms_get_owner_invoices($user_id) : array();
            return self::api_response(true, $invoices);
        }

        $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($user_id) : array();
        return self::api_response(true, $invoices);
    }

    public static function pay_invoice($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $invoice_id = intval($request->get_param('id'));

        if (!$user_id || !$invoice_id) {
            return self::api_response(false, 'Invalid request', 400);
        }

        if (!function_exists('ms_get_invoice_by_id') || !function_exists('ms_create_woo_order_for_invoice')) {
            return self::api_response(false, 'Payment flow unavailable', 500);
        }

        $invoice = ms_get_invoice_by_id($invoice_id);
        if (!$invoice) {
            return self::api_response(false, 'Invoice not found', 404);
        }

        if (intval($invoice->user_id) !== $user_id && !user_can($user_id, 'manage_options')) {
            return self::api_response(false, 'Unauthorized to pay this invoice', 403);
        }

        $payment_url = ms_create_woo_order_for_invoice($invoice_id);
        if (!$payment_url) {
            return self::api_response(false, 'Unable to create payment order', 500);
        }

        return self::api_response(true, ['payment_url' => $payment_url]);
    }

    public static function get_utility_bills($request)
    {
        if (!class_exists('Mostager_Utility_Bills_API')) {
            return self::api_response(false, 'Utility bills API unavailable', 500);
        }

        $api = new \Mostager_Utility_Bills_API();
        return $api->get_utility_bills($request);
    }

    public static function get_utility_bill($request)
    {
        if (!class_exists('Mostager_Utility_Bills_API')) {
            return self::api_response(false, 'Utility bills API unavailable', 500);
        }

        $api = new \Mostager_Utility_Bills_API();
        return $api->get_utility_bill($request);
    }

    public static function create_utility_bill($request)
    {
        if (!class_exists('Mostager_Utility_Bills_API')) {
            return self::api_response(false, 'Utility bills API unavailable', 500);
        }

        $api = new \Mostager_Utility_Bills_API();
        return $api->create_utility_bill($request);
    }

    public static function distribute_utility_bill($request)
    {
        if (!class_exists('Mostager_Utility_Bills_API')) {
            return self::api_response(false, 'Utility bills API unavailable', 500);
        }

        $api = new \Mostager_Utility_Bills_API();
        return $api->distribute_utility_bill($request);
    }

    private static function api_response($success, $data_or_message, $status = 200)
    {
        if ($success) {
            return new \WP_REST_Response(array('success' => true, 'data' => $data_or_message), $status);
        }

        return new \WP_REST_Response(array('success' => false, 'message' => $data_or_message), $status);
    }
}
