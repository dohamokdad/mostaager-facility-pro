<?php
namespace MostaagerFacilitiesPro\API;

if (!defined('ABSPATH')) exit;

class RestApi {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('mfp/v1', '/auth/token', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_auth_token'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('mfp/v1', '/buildings', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_buildings'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/buildings/(?P<id>\d+)/units', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_units'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/invoices', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_invoices'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/invoices/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array(__CLASS__, 'patch_invoice'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/maintenance', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_maintenance'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/maintenance', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_maintenance'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/maintenance/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array(__CLASS__, 'patch_maintenance'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/notifications', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_notifications'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'PATCH',
            'callback' => array(__CLASS__, 'mark_notification_read'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/discussions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_discussions'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/discussions/(?P<id>\d+)/replies', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_reply'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));

        register_rest_route('mfp/v1', '/wallet', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_wallet'),
            'permission_callback' => array(__CLASS__, 'verify_jwt_token'),
        ));
    }

    private static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data)
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private static function generate_jwt($user_id, $role)
    {
        $header = array('alg' => 'HS256', 'typ' => 'JWT');
        $payload = array(
            'iss' => home_url(),
            'iat' => time(),
            'exp' => time() + WEEK_IN_SECONDS,
            'user_id' => intval($user_id),
            'role' => sanitize_text_field($role),
        );

        $secret = wp_salt('auth');
        $segments = array(
            self::base64url_encode(json_encode($header)),
            self::base64url_encode(json_encode($payload)),
        );
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::base64url_encode($signature);

        return implode('.', $segments);
    }

    public static function verify_jwt_token($request)
    {
        $auth_header = '';
        if (method_exists($request, 'get_header')) {
            $auth_header = $request->get_header('authorization');
        }

        if (empty($auth_header) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (empty($auth_header) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        if (empty($auth_header) || stripos($auth_header, 'Bearer ') !== 0) {
            return new \WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
        }

        $token = trim(substr($auth_header, 7));
        if (empty($token)) {
            return new \WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new \WP_Error('invalid_token', 'Invalid token', array('status' => 401));
        }

        list($encoded_header, $encoded_payload, $encoded_signature) = $parts;
        $header = json_decode(self::base64url_decode($encoded_header), true);
        $payload = json_decode(self::base64url_decode($encoded_payload), true);
        $signature = self::base64url_decode($encoded_signature);

        if (empty($header) || empty($payload) || $signature === false) {
            return new \WP_Error('invalid_token', 'Invalid token', array('status' => 401));
        }

        $secret = wp_salt('auth');
        $expected = hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, $secret, true);
        if (!hash_equals($expected, $signature)) {
            return new \WP_Error('invalid_token', 'Invalid token', array('status' => 401));
        }

        if (empty($payload['exp']) || intval($payload['exp']) < time()) {
            return new \WP_Error('expired_token', 'Token expired', array('status' => 401));
        }

        if (empty($payload['user_id'])) {
            return new \WP_Error('invalid_token', 'Invalid token payload', array('status' => 401));
        }

        if (method_exists($request, 'set_param')) {
            $request->set_param('jwt_user_id', intval($payload['user_id']));
            $request->set_param('jwt_user_role', sanitize_text_field($payload['role'] ?? ''));
        }

        return true;
    }

    private static function get_jwt_user_id($request)
    {
        return intval($request->get_param('jwt_user_id') ?? 0);
    }

    private static function get_jwt_user_role($request)
    {
        return sanitize_text_field($request->get_param('jwt_user_role') ?? '');
    }

    private static function user_has_role($user_id, $role)
    {
        if (!$user_id || empty($role)) {
            return false;
        }

        return function_exists('ms_user_has_role') && ms_user_has_role($user_id, $role);
    }

    private static function user_is_admin($user_id)
    {
        return $user_id && user_can($user_id, 'manage_options');
    }

    private static function user_is_building_manager($user_id)
    {
        return self::user_has_role($user_id, 'building_manager');
    }

    private static function user_is_owner($user_id)
    {
        return self::user_has_role($user_id, 'owner');
    }

    private static function user_is_tenant($user_id)
    {
        return self::user_has_role($user_id, 'tenant');
    }

    private static function api_response($success, $data_or_message, $status = 200)
    {
        if ($success) {
            return new \WP_REST_Response(array('success' => true, 'data' => $data_or_message), $status);
        }
        return new \WP_REST_Response(array('success' => false, 'message' => $data_or_message), $status);
    }

    public static function handle_auth_token($request)
    {
        $params = $request->get_json_params();
        $username = isset($params['username']) ? sanitize_text_field($params['username']) : '';
        $password = isset($params['password']) ? sanitize_text_field($params['password']) : '';

        if (empty($username) || empty($password)) {
            return self::api_response(false, 'Missing credentials', 400);
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return self::api_response(false, 'Invalid credentials', 401);
        }

        $role = !empty($user->roles) ? sanitize_text_field($user->roles[0]) : 'subscriber';
        $token = self::generate_jwt($user->ID, $role);

        return self::api_response(true, array(
            'token' => $token,
            'user_id' => intval($user->ID),
            'role' => $role,
        ));
    }

    public static function get_buildings($request)
    {
        $user_id = self::get_jwt_user_id($request);

        if (!self::user_is_admin($user_id) && !self::user_is_building_manager($user_id)) {
            return self::api_response(false, 'غير مصرح', 403);
        }

        $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user_id) : array();
        return self::api_response(true, $buildings);
    }

    public static function get_units($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $building_id = intval($request->get_param('id'));

        if (!$building_id) {
            return self::api_response(false, 'Invalid building id', 400);
        }

        if (!self::user_is_admin($user_id) && !self::user_is_building_manager($user_id)) {
            return self::api_response(false, 'غير مصرح', 403);
        }

        if (self::user_is_building_manager($user_id)) {
            $allowed = false;
            $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user_id) : array();
            foreach ($buildings as $building) {
                $bid = intval($building->id ?? $building->ID ?? 0);
                if ($bid === $building_id) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return self::api_response(false, 'غير مصرح', 403);
            }
        }

        $units = function_exists('ms_get_units_by_building') ? ms_get_units_by_building($building_id) : array();
        return self::api_response(true, $units);
    }

    public static function get_invoices($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $building_id = intval($request->get_param('building_id'));

        if (self::user_is_admin($user_id) || self::user_is_building_manager($user_id)) {
            $invoices = function_exists('ms_get_manager_invoices') ? ms_get_manager_invoices($user_id, 100, '', $building_id) : array();
            return self::api_response(true, $invoices);
        }

        if (self::user_is_owner($user_id)) {
            $invoices = function_exists('ms_get_owner_invoices') ? ms_get_owner_invoices($user_id) : array();
            return self::api_response(true, $invoices);
        }

        $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($user_id) : array();
        return self::api_response(true, $invoices);
    }

    public static function patch_invoice($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $invoice_id = intval($request->get_param('id'));

        if (!$invoice_id) {
            return self::api_response(false, 'Invalid invoice id', 400);
        }

        if (!self::user_is_admin($user_id) && !self::user_is_building_manager($user_id)) {
            return self::api_response(false, 'غير مصرح', 403);
        }

        $invoice = function_exists('ms_get_invoice_by_id') ? ms_get_invoice_by_id($invoice_id) : null;
        if (!$invoice) {
            return self::api_response(false, 'Invoice not found', 404);
        }

        if (!function_exists('ms_mark_invoice_paid')) {
            return self::api_response(false, 'Function unavailable', 500);
        }

        $result = ms_mark_invoice_paid($invoice_id);
        if (!$result) {
            return self::api_response(false, 'Failed to mark invoice paid', 500);
        }

        return self::api_response(true, array('invoice_id' => $invoice_id, 'status' => 'paid'));
    }

    public static function get_maintenance($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $building_id = intval($request->get_param('building_id'));
        $args = array();

        if (self::user_is_building_manager($user_id)) {
            $args['manager_id'] = $user_id;
            if ($building_id) {
                $args['building_id'] = $building_id;
            }
        } elseif (self::user_is_admin($user_id)) {
            if ($building_id) {
                $args['building_id'] = $building_id;
            }
        } else {
            if (!$building_id) {
                return self::api_response(false, 'building_id is required for this role', 400);
            }
            $args['building_id'] = $building_id;
        }

        $requests = function_exists('ms_get_maintenance_requests') ? ms_get_maintenance_requests($args) : array();
        return self::api_response(true, $requests);
    }

    public static function create_maintenance($request)
    {
        $user_id = self::get_jwt_user_id($request);

        if (!self::user_is_building_manager($user_id)) {
            return self::api_response(false, 'غير مصرح', 403);
        }

        $params = $request->get_json_params();
        $building_id = intval($params['building_id'] ?? 0);
        $unit_id = isset($params['unit_id']) ? intval($params['unit_id']) : 0;
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $description = isset($params['description']) ? sanitize_textarea_field($params['description']) : '';
        $cost = isset($params['cost']) ? floatval($params['cost']) : 0;
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'open';
        $payer_type = isset($params['payer_type']) ? sanitize_text_field($params['payer_type']) : 'tenant';

        if (!$building_id || empty($title) || $cost <= 0) {
            return self::api_response(false, 'Missing required maintenance details', 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        $inserted = $wpdb->insert($table, array(
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'title' => $title,
            'description' => $description,
            'tenant_name' => '',
            'tenant_phone' => '',
            'cost' => $cost,
            'status' => $status,
            'priority' => isset($params['priority']) ? sanitize_text_field($params['priority']) : 'medium',
            'maintenance_type' => isset($params['maintenance_type']) ? sanitize_text_field($params['maintenance_type']) : 'general',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ), array('%d','%d','%s','%s','%s','%s','%f','%s','%s','%s','%s'));

        if ($inserted === false) {
            return self::api_response(false, 'Failed to create maintenance request', 500);
        }

        $maintenance_id = intval($wpdb->insert_id);
        if (function_exists('ms_distribute_maintenance_invoices')) {
            ms_distribute_maintenance_invoices($maintenance_id, $building_id, $cost, $payer_type);
        }

        return self::api_response(true, array('maintenance_id' => $maintenance_id));
    }

    public static function patch_maintenance($request)
    {
        $user_id = self::get_jwt_user_id($request);

        if (!self::user_is_building_manager($user_id)) {
            return self::api_response(false, 'غير مصرح', 403);
        }

        $maintenance_id = intval($request->get_param('id'));
        if (!$maintenance_id) {
            return self::api_response(false, 'Invalid maintenance id', 400);
        }

        $params = $request->get_json_params();
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
        if (!$status) {
            return self::api_response(false, 'Missing status', 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        $updated = $wpdb->update($table, array('status' => $status, 'updated_at' => current_time('mysql')), array('id' => $maintenance_id), array('%s','%s'), array('%d'));
        if ($updated === false) {
            return self::api_response(false, 'Failed to update maintenance request', 500);
        }

        return self::api_response(true, array('maintenance_id' => $maintenance_id, 'status' => $status));
    }

    public static function get_notifications($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user_id) : array();
        return self::api_response(true, $notifications);
    }

    public static function mark_notification_read($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $notification_id = intval($request->get_param('id'));

        if (!$notification_id) {
            return self::api_response(false, 'Invalid notification id', 400);
        }

        if (!function_exists('ms_mark_notification_read')) {
            return self::api_response(false, 'Function unavailable', 500);
        }

        $result = ms_mark_notification_read($notification_id, $user_id);
        if (!$result) {
            return self::api_response(false, 'Failed to mark notification as read', 500);
        }

        return self::api_response(true, array('notification_id' => $notification_id, 'read' => true));
    }

    public static function get_discussions($request)
    {
        $building_id = intval($request->get_param('building_id'));
        if (!$building_id) {
            return self::api_response(false, 'building_id is required', 400);
        }

        $discussions = function_exists('ms_get_building_discussions') ? ms_get_building_discussions($building_id) : array();
        return self::api_response(true, $discussions);
    }

    public static function create_reply($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $discussion_id = intval($request->get_param('id'));
        $params = $request->get_json_params();
        $message = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';

        if (!$discussion_id || empty($message)) {
            return self::api_response(false, 'Invalid discussion or message', 400);
        }

        if (!function_exists('ms_create_discussion_reply')) {
            return self::api_response(false, 'Function unavailable', 500);
        }

        $comment_id = ms_create_discussion_reply($discussion_id, $user_id, $message);
        if (!$comment_id) {
            return self::api_response(false, 'Failed to create reply', 500);
        }

        return self::api_response(true, array('reply_id' => $comment_id));
    }

    public static function get_wallet($request)
    {
        $user_id = self::get_jwt_user_id($request);
        $building_id = intval($request->get_param('building_id'));

        if (self::user_is_admin($user_id) || self::user_is_building_manager($user_id)) {
            if (!$building_id) {
                return self::api_response(false, 'building_id is required for building wallet', 400);
            }
            $wallet = function_exists('ms_get_building_wallet') ? ms_get_building_wallet($building_id) : null;
            return self::api_response(true, $wallet);
        }

        $wallet = function_exists('ms_get_user_wallet_balance') ? ms_get_user_wallet_balance($user_id) : 0;
        return self::api_response(true, array('balance' => floatval($wallet)));
    }
}
