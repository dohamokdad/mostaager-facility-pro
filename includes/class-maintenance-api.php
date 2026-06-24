<?php
/**
 * Mostager Facilities Pro - Maintenance Ticket System
 * REST API endpoints for maintenance ticket CRUD operations
 * 
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

class MS_Maintenance_API {
    
    private $namespace = 'mostager/v1';
    private $capability = 'read';
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        
        // Get all tickets / Create ticket
        register_rest_route($this->namespace, '/maintenance', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_tickets'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'building_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string'],
                    'priority' => ['type' => 'string'],
                    'page' => ['type' => 'integer', 'default' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 20],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_ticket'],
                'permission_callback' => '__return_true',
                'args' => [
                    'building_id' => ['required' => true, 'type' => 'integer'],
                    'unit_id' => ['required' => true, 'type' => 'integer'],
                    'facility_id' => ['required' => false, 'type' => 'integer'],
                    'tenant_name' => ['required' => true, 'type' => 'string'],
                    'title' => ['required' => true, 'type' => 'string'],
                    'description' => ['type' => 'string'],
                    'category' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'default' => 'medium'],
                    'tenant_phone' => ['type' => 'string'],
                ],
            ],
        ]);
        
        // Get single ticket
        register_rest_route($this->namespace, '/maintenance/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_ticket'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_ticket'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'status' => ['type' => 'string'],
                    'priority' => ['type' => 'string'],
                    'assigned_to' => ['type' => 'integer'],
                    'cost_estimate' => ['type' => 'number'],
                    'actual_cost' => ['type' => 'number'],
                ],
            ],
        ]);
        
        // Update ticket status
        register_rest_route($this->namespace, '/maintenance/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'status' => ['required' => true, 'type' => 'string'],
                'comment' => ['type' => 'string'],
            ],
        ]);
        
        // Get ticket comments
        register_rest_route($this->namespace, '/maintenance/(?P<id>\d+)/comments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_comments'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_comment'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'comment' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
        
        // Upload attachment
        register_rest_route($this->namespace, '/maintenance/(?P<id>\d+)/attachments', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'upload_attachment'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get dashboard stats
        register_rest_route($this->namespace, '/maintenance/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'building_id' => ['type' => 'integer'],
            ],
        ]);
    }
    
    /**
     * Get tickets list with filtering
     */
    public function get_tickets(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $building_id = $request->get_param('building_id');
        $status = sanitize_text_field($request->get_param('status') ?: '');
        $priority = sanitize_text_field($request->get_param('priority') ?: '');
        $page = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page'))));
        
        $where = ['1=1'];
        if ($building_id) $where[] = $wpdb->prepare("building_id = %d", $building_id);
        if ($status) $where[] = $wpdb->prepare("status = %s", $status);
        if ($priority) $where[] = $wpdb->prepare("priority = %s", $priority);
        
        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, b.building_name, u.unit_number, f.title AS facility_name
            FROM {$table} m
            LEFT JOIN {$wpdb->prefix}ms_buildings b ON m.building_id = b.id
            LEFT JOIN {$wpdb->prefix}ms_units u ON m.unit_id = u.id
            LEFT JOIN {$wpdb->prefix}ms_facilities f ON m.facility_id = f.id
            WHERE {$where_clause}
            ORDER BY m.created_at DESC
            LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        // Fix: use separate query for count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}");
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $tickets,
            'total' => intval($total),
            'pages' => ceil($total / $per_page),
            'page' => $page,
        ], 200);
    }
    
    /**
     * Get single ticket
     */
    public function get_ticket(WP_REST_Request $request) {
        global $wpdb;
        $ticket_id = $request->get_param('id');
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, b.building_name, u.unit_number, u.floor, f.title AS facility_name
            FROM {$table} m
            LEFT JOIN {$wpdb->prefix}ms_buildings b ON m.building_id = b.id
            LEFT JOIN {$wpdb->prefix}ms_units u ON m.unit_id = u.id
            LEFT JOIN {$wpdb->prefix}ms_facilities f ON m.facility_id = f.id
            WHERE m.id = %d",
            $ticket_id
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'طلب الصيانة غير موجود', ['status' => 404]);
        }
        
        // Get comments
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_name
            FROM {$wpdb->prefix}ms_maintenance_comments c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.ticket_id = %d
            ORDER BY c.created_at ASC",
            $ticket_id
        ));
        
        // Get attachments
        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_maintenance_attachments WHERE ticket_id = %d",
            $ticket_id
        ));
        
        return new WP_REST_Response([
            'success' => true,
            'ticket' => $ticket,
            'comments' => $comments,
            'attachments' => $attachments,
        ], 200);
    }
    
    /**
     * Create new maintenance ticket
     */
    public function create_ticket(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $data = [
            'building_id' => intval($request->get_param('building_id')),
            'unit_id' => intval($request->get_param('unit_id')),
            'facility_id' => intval($request->get_param('facility_id') ?: 0),
            'tenant_name' => sanitize_text_field($request->get_param('tenant_name')),
            'tenant_phone' => sanitize_text_field($request->get_param('tenant_phone') ?: ''),
            'category' => sanitize_text_field($request->get_param('category') ?: 'general'),
            'priority' => sanitize_text_field($request->get_param('priority') ?: 'medium'),
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => sanitize_textarea_field($request->get_param('description') ?: ''),
            'status' => 'new',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'فشل في إنشاء طلب الصيانة', ['status' => 500]);
        }
        
        $ticket_id = $wpdb->insert_id;
        
        // Send notifications
        $this->notify_new_ticket($ticket_id, $data);
        
        return new WP_REST_Response([
            'success' => true,
            'ticket_id' => $ticket_id,
            'message' => 'تم إنشاء طلب الصيانة بنجاح، سنتواصل معك قريباً',
        ], 201);
    }
    
    /**
     * Update ticket
     */
    public function update_ticket(WP_REST_Request $request) {
        global $wpdb;
        $ticket_id = $request->get_param('id');
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $update_data = ['updated_at' => current_time('mysql')];
        
        if ($request->has_param('status')) {
            $update_data['status'] = sanitize_text_field($request->get_param('status'));
        }
        if ($request->has_param('priority')) {
            $update_data['priority'] = sanitize_text_field($request->get_param('priority'));
        }
        if ($request->has_param('assigned_to')) {
            $update_data['assigned_to'] = intval($request->get_param('assigned_to'));
        }
        if ($request->has_param('cost_estimate')) {
            $update_data['cost_estimate'] = floatval($request->get_param('cost_estimate'));
        }
        if ($request->has_param('actual_cost')) {
            $update_data['actual_cost'] = floatval($request->get_param('actual_cost'));
        }
        
        $wpdb->update($table, $update_data, ['id' => $ticket_id]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'تم التحديث بنجاح',
        ], 200);
    }
    
    /**
     * Update ticket status with optional comment
     */
    public function update_status(WP_REST_Request $request) {
        global $wpdb;
        $ticket_id = $request->get_param('id');
        $new_status = sanitize_text_field($request->get_param('status'));
        $comment = sanitize_textarea_field($request->get_param('comment') ?: '');
        
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        // Update status
        $update_data = [
            'status' => $new_status,
            'updated_at' => current_time('mysql'),
        ];
        
        // If completed, set completed date
        if ($new_status === 'completed') {
            $update_data['completed_date'] = current_time('mysql');
        }
        
        $wpdb->update($table, $update_data, ['id' => $ticket_id]);
        
        // Add comment if provided
        if ($comment) {
            $wpdb->insert($wpdb->prefix . 'ms_maintenance_comments', [
                'ticket_id' => $ticket_id,
                'user_id' => get_current_user_id(),
                'comment' => $comment,
                'status_change' => $new_status,
                'created_at' => current_time('mysql'),
            ]);
        }
        
        // Send notification
        $this->notify_status_change($ticket_id, $new_status);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'تم تحديث الحالة إلى: ' . $this->get_status_label($new_status),
        ], 200);
    }
    
    /**
     * Get ticket comments
     */
    public function get_comments(WP_REST_Request $request) {
        global $wpdb;
        $ticket_id = $request->get_param('id');
        
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_name
            FROM {$wpdb->prefix}ms_maintenance_comments c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.ticket_id = %d
            ORDER BY c.created_at ASC",
            $ticket_id
        ));
        
        return new WP_REST_Response([
            'success' => true,
            'comments' => $comments,
        ], 200);
    }
    
    /**
     * Add comment to ticket
     */
    public function add_comment(WP_REST_Request $request) {
        global $wpdb;
        $ticket_id = $request->get_param('id');
        
        $result = $wpdb->insert($wpdb->prefix . 'ms_maintenance_comments', [
            'ticket_id' => $ticket_id,
            'user_id' => get_current_user_id(),
            'comment' => sanitize_textarea_field($request->get_param('comment')),
            'created_at' => current_time('mysql'),
        ]);
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'فشل في إضافة التعليق', ['status' => 500]);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'comment_id' => $wpdb->insert_id,
            'message' => 'تم إضافة التعليق بنجاح',
        ], 201);
    }
    
    /**
     * Upload file attachment
     */
    public function upload_attachment(WP_REST_Request $request) {
        $ticket_id = $request->get_param('id');
        $files = $request->get_file_params();
        
        if (empty($files['attachment'])) {
            return new WP_Error('no_file', 'لم يتم رفع أي ملف', ['status' => 400]);
        }
        
        $file = $files['attachment'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', 'نوع الملف غير مسموح به. الأنواع المسموحة: JPG, PNG, GIF, PDF, MP4', ['status' => 400]);
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت', ['status' => 400]);
        }
        
        // Upload using WordPress
        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], ['status' => 500]);
        }
        
        // Save to database
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ms_maintenance_attachments', [
            'ticket_id' => $ticket_id,
            'file_path' => $upload['file'],
            'file_url' => $upload['url'],
            'file_type' => $upload['type'],
            'uploaded_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'attachment_id' => $wpdb->insert_id,
            'file_url' => $upload['url'],
            'file_type' => $upload['type'],
        ], 201);
    }
    
    /**
     * Get dashboard stats
     */
    public function get_stats(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        $building_id = $request->get_param('building_id');
        
        $where = $building_id ? $wpdb->prepare("WHERE building_id = %d", $building_id) : '';
        
        $stats = [
            'total' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}") ?: 0),
            'new' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'new'") ?: 0),
            'in_progress' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'in_progress'") ?: 0),
            'completed' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'completed'") ?: 0),
            'high_priority' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} " . ($where ? 'AND' : 'WHERE') . " priority = 'high' AND status != 'completed'") ?: 0),
        ];
        
        return new WP_REST_Response([
            'success' => true,
            'stats' => $stats,
        ], 200);
    }
    
    /**
     * Check permissions
     */
    public function check_permission() {
        return is_user_logged_in();
    }
    
    /**
     * Send notification for new ticket
     */
    private function notify_new_ticket($ticket_id, $data) {
        global $wpdb;
        
        // Get building info
        $building = $wpdb->get_row($wpdb->prepare(
            "SELECT building_name FROM {$wpdb->prefix}mostager_buildings WHERE id = %d",
            $data['building_id']
        ));
        
        $message = "طلب صيانة جديد #{$ticket_id}\n";
        $message .= "المبنى: " . ($building->building_name ?? '') . "\n";
        $message .= "المستأجر: {$data['tenant_name']}\n";
        $message .= "النوع: {$data['category']}\n";
        $message .= "الأولوية: " . $this->get_priority_label($data['priority']) . "\n";
        $message .= "العنوان: {$data['title']}";
        
        // Send email to admin
        wp_mail(
            get_option('admin_email'),
            'طلب صيانة جديد #' . $ticket_id . ' - ' . $data['title'],
            $message
        );
        
        // Hook for WhatsApp integration
        do_action('ms_new_maintenance_ticket', $ticket_id, $data);
    }
    
    /**
     * Send notification for status change
     */
    private function notify_status_change($ticket_id, $new_status) {
        do_action('ms_maintenance_status_changed', $ticket_id, $new_status);
    }
    
    /**
     * Get Arabic status label
     */
    private function get_status_label($status) {
        $labels = [
            'new' => 'جديد',
            'assigned' => 'تم التعيين',
            'in_progress' => 'قيد التنفيذ',
            'waiting_parts' => 'بانتظار قطع الغيار',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
        return $labels[$status] ?? $status;
    }
    
    /**
     * Get Arabic priority label
     */
    private function get_priority_label($priority) {
        $labels = [
            'low' => 'منخفضة',
            'medium' => 'متوسطة',
            'high' => 'عالية',
            'emergency' => 'طارئة',
        ];
        return $labels[$priority] ?? $priority;
    }
}

// Register routes
add_action('rest_api_init', function() {
    $api = new MS_Maintenance_API();
    $api->register_routes();
});
