<?php
/**
 * Mostager Facilities Pro - WhatsApp Business API Integration
 * Supports: 360dialog, WATI, and similar WhatsApp Business API providers
 * 
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Mostager_WhatsApp_Integration {
    
    private $api_key;
    private $api_url;
    private $phone_number_id;
    private $enabled;
    
    /**
     * Template definitions for WhatsApp
     */
    private $templates = [
        'invoice_notification' => [
            'name' => 'invoice_notification',
            'language' => 'ar',
            'params_count' => 6,
            'param_labels' => ['tenant_name', 'building_name', 'unit_number', 'invoice_number', 'total_amount', 'due_date'],
        ],
        'payment_reminder' => [
            'name' => 'payment_reminder',
            'language' => 'ar',
            'params_count' => 4,
            'param_labels' => ['tenant_name', 'invoice_number', 'total_amount', 'due_date'],
        ],
        'payment_confirmation' => [
            'name' => 'payment_confirmation',
            'language' => 'ar',
            'params_count' => 4,
            'param_labels' => ['tenant_name', 'invoice_number', 'amount_paid', 'payment_date'],
        ],
        'maintenance_new' => [
            'name' => 'maintenance_new_ticket',
            'language' => 'ar',
            'params_count' => 5,
            'param_labels' => ['tenant_name', 'ticket_id', 'category', 'title', 'building_name'],
        ],
        'maintenance_status' => [
            'name' => 'maintenance_status_update',
            'language' => 'ar',
            'params_count' => 4,
            'param_labels' => ['tenant_name', 'ticket_id', 'status', 'title'],
        ],
        'maintenance_completed' => [
            'name' => 'maintenance_completed',
            'language' => 'ar',
            'params_count' => 4,
            'param_labels' => ['tenant_name', 'ticket_id', 'title', 'completed_date'],
        ],
        'welcome_message' => [
            'name' => 'welcome_message',
            'language' => 'ar',
            'params_count' => 2,
            'param_labels' => ['tenant_name', 'building_name'],
        ],
    ];
    
    public function __construct() {
        $this->api_key = get_option('mostager_whatsapp_api_key', '');
        $this->api_url = get_option('mostager_whatsapp_api_url', 'https://waba.360dialog.io/v1');
        $this->phone_number_id = get_option('mostager_whatsapp_phone_id', '');
        $this->enabled = !empty($this->api_key) && !empty($this->api_url);
    }
    
    /**
     * Check if WhatsApp integration is enabled and configured
     * 
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Send templated WhatsApp message
     * 
     * @param string $to Phone number (Egyptian format)
     * @param string $template_key Template key from $templates
     * @param array $params Parameter values for the template
     * @return array|WP_Error Response or error
     */
    public function send_template($to, $template_key, $params = []) {
        if (!$this->is_enabled()) {
            return new WP_Error('not_configured', 'WhatsApp integration is not configured');
        }
        
        $template = $this->templates[$template_key] ?? null;
        if (!$template) {
            return new WP_Error('invalid_template', 'Template not found: ' . $template_key);
        }
        
        $url = rtrim($this->api_url, '/') . '/messages';
        
        // Format parameters
        $body_params = [];
        for ($i = 0; $i < $template['params_count']; $i++) {
            $body_params[] = [
                'type' => 'text',
                'text' => isset($params[$i]) ? (string) $params[$i] : '',
            ];
        }
        
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->format_phone($to),
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => ['code' => $template['language']],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $body_params,
                    ],
                ],
            ],
        ];
        
        return $this->make_request($url, $body);
    }
    
    /**
     * Send invoice notification
     * 
     * @param string $phone Recipient phone
     * @param array $invoice_data Invoice data
     * @return array|WP_Error
     */
    public function send_invoice_notification($phone, $invoice_data) {
        return $this->send_template($phone, 'invoice_notification', [
            $invoice_data['tenant_name'] ?? '',
            $invoice_data['building_name'] ?? '',
            $invoice_data['unit_number'] ?? '',
            $invoice_data['invoice_number'] ?? '',
            isset($invoice_data['total_amount']) ? number_format($invoice_data['total_amount'], 2) . ' ج.م' : '',
            $invoice_data['due_date'] ?? '',
        ]);
    }
    
    /**
     * Send payment reminder
     * 
     * @param string $phone Recipient phone
     * @param array $invoice_data Invoice data
     * @return array|WP_Error
     */
    public function send_payment_reminder($phone, $invoice_data) {
        return $this->send_template($phone, 'payment_reminder', [
            $invoice_data['tenant_name'] ?? '',
            $invoice_data['invoice_number'] ?? '',
            isset($invoice_data['total_amount']) ? number_format($invoice_data['total_amount'], 2) . ' ج.م' : '',
            $invoice_data['due_date'] ?? '',
        ]);
    }
    
    /**
     * Send payment confirmation
     * 
     * @param string $phone Recipient phone
     * @param array $payment_data Payment data
     * @return array|WP_Error
     */
    public function send_payment_confirmation($phone, $payment_data) {
        return $this->send_template($phone, 'payment_confirmation', [
            $payment_data['tenant_name'] ?? '',
            $payment_data['invoice_number'] ?? '',
            isset($payment_data['amount_paid']) ? number_format($payment_data['amount_paid'], 2) . ' ج.م' : '',
            $payment_data['payment_date'] ?? '',
        ]);
    }
    
    /**
     * Send maintenance ticket notification
     * 
     * @param string $phone Recipient phone
     * @param array $ticket_data Ticket data
     * @return array|WP_Error
     */
    public function send_maintenance_notification($phone, $ticket_data) {
        return $this->send_template($phone, 'maintenance_new', [
            $ticket_data['tenant_name'] ?? '',
            '#' . ($ticket_data['ticket_id'] ?? ''),
            $ticket_data['category'] ?? '',
            $ticket_data['title'] ?? '',
            $ticket_data['building_name'] ?? '',
        ]);
    }
    
    /**
     * Send maintenance status update
     * 
     * @param string $phone Recipient phone
     * @param array $ticket_data Ticket data
     * @return array|WP_Error
     */
    public function send_maintenance_status($phone, $ticket_data) {
        return $this->send_template($phone, 'maintenance_status', [
            $ticket_data['tenant_name'] ?? '',
            '#' . ($ticket_data['ticket_id'] ?? ''),
            $this->get_arabic_status($ticket_data['status'] ?? ''),
            $ticket_data['title'] ?? '',
        ]);
    }
    
    /**
     * Send welcome message to new tenant
     * 
     * @param string $phone Recipient phone
     * @param array $tenant_data Tenant data
     * @return array|WP_Error
     */
    public function send_welcome($phone, $tenant_data) {
        return $this->send_template($phone, 'welcome_message', [
            $tenant_data['tenant_name'] ?? '',
            $tenant_data['building_name'] ?? '',
        ]);
    }
    
    /**
     * Broadcast message to all tenants in a building
     * 
     * @param int $building_id Building ID
     * @param string $template_key Template to use
     * @param array $base_params Base parameters (tenant-specific added automatically)
     * @return array Results per tenant
     */
    public function broadcast_to_building($building_id, $template_key, $base_params = []) {
        global $wpdb;
        
        $tenants = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT tenant_phone, tenant_name, unit_number 
             FROM {$wpdb->prefix}ms_units 
             WHERE building_id = %d AND tenant_phone IS NOT NULL AND tenant_phone != ''",
            $building_id
        ));
        
        $results = [];
        foreach ($tenants as $tenant) {
            $params = array_merge(
                [$tenant->tenant_name, $tenant->unit_number],
                $base_params
            );
            
            $result = $this->send_template($tenant->tenant_phone, $template_key, $params);
            
            $results[] = [
                'phone' => $tenant->tenant_phone,
                'name' => $tenant->tenant_name,
                'success' => !is_wp_error($result),
                'response' => is_wp_error($result) ? $result->get_error_message() : $result,
            ];
        }
        
        return $results;
    }
    
    /**
     * Get available templates from WhatsApp API
     * 
     * @return array Templates list
     */
    public function get_templates() {
        if (!$this->is_enabled()) {
            return [];
        }
        
        $url = rtrim($this->api_url, '/') . '/configs/templates';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'D360-API-KEY' => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['waba_templates'] ?? [];
    }
    
    /**
     * Get locally defined templates
     * 
     * @return array
     */
    public function get_local_templates() {
        return $this->templates;
    }
    
    /**
     * Make HTTP request to WhatsApp API
     * 
     * @param string $url API endpoint
     * @param array $body Request body
     * @return array|WP_Error
     */
    private function make_request($url, $body) {
        $response = wp_remote_post($url, [
            'headers' => [
                'D360-API-KEY' => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code >= 400) {
            $error_msg = $body['error']['message'] ?? 'Unknown API error';
            return new WP_Error('api_error', $error_msg, ['status' => $status_code]);
        }
        
        return $body;
    }
    
    /**
     * Format Egyptian phone number for WhatsApp
     * 
     * @param string $phone Raw phone number
     * @return string Formatted international number
     */
    private function format_phone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with Egypt country code
        if (strpos($phone, '0') === 0) {
            $phone = '20' . substr($phone, 1);
        }
        
        // Remove + if present
        if (strpos($phone, '+') === 0) {
            $phone = substr($phone, 1);
        }
        
        // Ensure starts with 20
        if (strpos($phone, '20') !== 0) {
            $phone = '20' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get Arabic status label
     * 
     * @param string $status English status
     * @return string Arabic status
     */
    private function get_arabic_status($status) {
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
}

/**
 * Register settings page for WhatsApp configuration
 */
add_action('admin_menu', 'mostager_whatsapp_settings_menu', 30);
function mostager_whatsapp_settings_menu() {
    add_submenu_page(
        'mostaager-admin',
        'إعدادات WhatsApp',
        'WhatsApp',
        'manage_options',
        'mostager-whatsapp',
        'mostager_whatsapp_settings_page'
    );
}

/**
 * WhatsApp settings page
 */
function mostager_whatsapp_settings_page() {
    // Save settings
    if (isset($_POST['mostager_save_whatsapp'])) {
        check_admin_referer('mostager_whatsapp_settings');
        
        update_option('mostager_whatsapp_api_key', sanitize_text_field($_POST['api_key']));
        update_option('mostager_whatsapp_api_url', esc_url_raw($_POST['api_url']));
        update_option('mostager_whatsapp_phone_id', sanitize_text_field($_POST['phone_id']));
        
        echo '<div class="notice notice-success"><p>تم حفظ الإعدادات بنجاح</p></div>';
    }
    
    $api_key = get_option('mostager_whatsapp_api_key', '');
    $api_url = get_option('mostager_whatsapp_api_url', 'https://waba.360dialog.io/v1');
    $phone_id = get_option('mostager_whatsapp_phone_id', '');
    
    // Check connection
    $whatsapp = new Mostager_WhatsApp_Integration();
    $is_connected = $whatsapp->is_enabled();
    ?>
    <div class="wrap">
        <h1>إعدادات WhatsApp Business API</h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>حالة الاتصال</h2>
            <p>
                <?php if ($is_connected): ?>
                    <span style="color: #4CAF50;"><i class="fas fa-check-circle"></i> متصل</span>
                <?php else: ?>
                    <span style="color: #F44336;"><i class="fas fa-times-circle"></i> غير متصل - يرجى إدخال إعدادات API</span>
                <?php endif; ?>
            </p>
                <p>
                    <button id="mostager-test-whatsapp" class="button">اختبار الاتصال</button>
                    <span id="mostager-test-result" style="margin-left:10px; vertical-align: middle;"></span>
                </p>
        </div>
        
        <form method="post" style="max-width: 800px;">
            <?php wp_nonce_field('mostager_whatsapp_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="api_url">رابط API</label></th>
                    <td>
                        <input type="url" id="api_url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text">
                        <p class="description">مثال: https://waba.360dialog.io/v1</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="api_key">مفتاح API</label></th>
                    <td>
                        <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description">مفتاح API من 360dialog أو مزود الخدمة</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="phone_id">معرف رقم الهاتف</label></th>
                    <td>
                        <input type="text" id="phone_id" name="phone_id" value="<?php echo esc_attr($phone_id); ?>" class="regular-text">
                        <p class="description">معرف رقم الهاتف المسجل في WhatsApp Business</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('حفظ الإعدادات', 'primary', 'mostager_save_whatsapp'); ?>
        </form>
        
        <div class="card" style="max-width: 800px;">
            <h2>القوالب المتوفرة</h2>
            <p>يجب إنشاء هذه القوالب في لوحة تحكم WhatsApp Business API وإرسالها للموافقة:</p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>اسم القالب</th>
                        <th>الغرض</th>
                        <th>المعاملات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>invoice_notification</code></td>
                        <td>إشعار بإصدار فاتورة جديدة</td>
                        <td>اسم المستأجر، المبنى، الوحدة، رقم الفاتورة، المبلغ، تاريخ الاستحقاق</td>
                    </tr>
                    <tr>
                        <td><code>payment_reminder</code></td>
                        <td>تذكير بسداد الفاتورة</td>
                        <td>اسم المستأجر، رقم الفاتورة، المبلغ، تاريخ الاستحقاق</td>
                    </tr>
                    <tr>
                        <td><code>payment_confirmation</code></td>
                        <td>تأكيد استلام المبلغ</td>
                        <td>اسم المستأجر، رقم الفاتورة، المبلغ المدفوع، تاريخ الدفع</td>
                    </tr>
                    <tr>
                        <td><code>maintenance_new_ticket</code></td>
                        <td>إشعار بطلب صيانة جديد</td>
                        <td>اسم المستأجر، رقم التذكرة، التصنيف، العنوان، المبنى</td>
                    </tr>
                    <tr>
                        <td><code>maintenance_status_update</code></td>
                        <td>تحديث حالة طلب الصيانة</td>
                        <td>اسم المستأجر، رقم التذكرة، الحالة، العنوان</td>
                    </tr>
                    <tr>
                        <td><code>welcome_message</code></td>
                        <td>رسالة ترحيب للمستأجر الجديد</td>
                        <td>اسم المستأجر، اسم المبنى</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// AJAX handler for testing WhatsApp connection
add_action('wp_ajax_mostager_test_whatsapp', 'mostager_ajax_test_whatsapp');
function mostager_ajax_test_whatsapp() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    check_ajax_referer('mostager_test_whatsapp');

    $whatsapp = new Mostager_WhatsApp_Integration();

    $templates = $whatsapp->get_templates();

    if (is_array($templates) && count($templates) > 0) {
        wp_send_json_success(['count' => count($templates), 'templates' => $templates]);
    }

    wp_send_json_error(['message' => 'No templates returned or connection failed']);
}

// Enqueue inline JS for the test button
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_mostaager-admin' && strpos($screen->id, 'mostager') === false) {
        return;
    }
    $nonce = wp_create_nonce('mostager_test_whatsapp');
    ?>
    <script type="text/javascript">
    (function($){
        $(function(){
            $('#mostager-test-whatsapp').on('click', function(e){
                e.preventDefault();
                var btn = $(this).prop('disabled', true).text('جارٍ الاختبار...');
                $('#mostager-test-result').text('');
                $.post(ajaxurl, { action: 'mostager_test_whatsapp', _ajax_nonce: '<?php echo $nonce; ?>' }, function(resp){
                    btn.prop('disabled', false).text('اختبار الاتصال');
                    if (resp && resp.success) {
                        $('#mostager-test-result').html('<span style="color: #4CAF50;">نجح — ' + (resp.data.count || 0) + ' قوالب</span>');
                    } else {
                        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'فشل الاتصال';
                        $('#mostager-test-result').html('<span style="color: #F44336;">' + msg + '</span>');
                    }
                }).fail(function(){
                    btn.prop('disabled', false).text('اختبار الاتصال');
                    $('#mostager-test-result').html('<span style="color: #F44336;">خطأ في الاتصال</span>');
                });
            });
        });
    })(jQuery);
    </script>
    <?php
});

/**
 * Auto-send WhatsApp notifications on invoice creation
 */
add_action('ms_invoice_created', 'mostager_auto_whatsapp_invoice', 10, 2);
function mostager_auto_whatsapp_invoice($invoice_id, $invoice_data) {
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) return;
    
    // Get tenant phone
    global $wpdb;
    $phone = $wpdb->get_var($wpdb->prepare(
        "SELECT tenant_phone FROM {$wpdb->prefix}mostager_units WHERE id = %d",
        $invoice_data['unit_id'] ?? 0
    ));
    
    if ($phone) {
        $whatsapp->send_invoice_notification($phone, $invoice_data);
    }
}

/**
 * Auto-send WhatsApp on maintenance ticket creation
 */
add_action('ms_new_maintenance_ticket', 'mostager_auto_whatsapp_maintenance', 10, 2);
function mostager_auto_whatsapp_maintenance($ticket_id, $ticket_data) {
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) return;
    
    if (!empty($ticket_data['tenant_phone'])) {
        $whatsapp->send_maintenance_notification($ticket_data['tenant_phone'], array_merge(
            $ticket_data,
            ['ticket_id' => $ticket_id]
        ));
    }
}

/**
 * Send confirmation on invoice payment
 */
add_action('ms_invoice_paid', 'mostager_auto_whatsapp_invoice_paid', 10, 2);
function mostager_auto_whatsapp_invoice_paid($invoice_id, $invoice_data) {
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) return;

    global $wpdb;
    $unit_id = intval($invoice_data->unit_id ?? $invoice_data['unit_id'] ?? 0);
    $phone = '';
    if ($unit_id) {
        $phone = $wpdb->get_var($wpdb->prepare("SELECT tenant_phone FROM {$wpdb->prefix}ms_units WHERE id = %d", $unit_id));
    }

    if ($phone) {
        $whatsapp->send_payment_confirmation($phone, array(
            'tenant_name' => $invoice_data->tenant_name ?? '',
            'invoice_number' => $invoice_id,
            'amount_paid' => $invoice_data->amount ?? ($invoice_data->total_amount ?? 0),
            'payment_date' => current_time('Y-m-d'),
        ));
    }
}
