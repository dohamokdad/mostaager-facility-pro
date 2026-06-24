<?php
/**
 * Mostaager Smart Automation Module
 * Handles automated reminders, notifications, and intelligent scheduling
 * 
 * @package Mostaager_Facility_Pro
 * @version 15.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Smart Automation
 */
function msfp_init_smart_automation()
{
    // Register cron jobs
    if (!wp_next_scheduled('msfp_payment_reminder_cron')) {
        wp_schedule_event(time(), 'daily', 'msfp_payment_reminder_cron');
    }
    if (!wp_next_scheduled('msfp_late_payment_penalty_cron')) {
        wp_schedule_event(time(), 'daily', 'msfp_late_payment_penalty_cron');
    }
    if (!wp_next_scheduled('msfp_maintenance_followup_cron')) {
        wp_schedule_event(time(), 'daily', 'msfp_maintenance_followup_cron');
    }
}
add_action('plugins_loaded', 'msfp_init_smart_automation');

/**
 * Daily payment reminder cron job
 * Sends reminders 3 days before due date
 */
add_action('msfp_payment_reminder_cron', 'msfp_send_payment_reminders');
function msfp_send_payment_reminders()
{
    global $wpdb;
    
    // Get invoices due in 3 days
    $three_days_later = date('Y-m-d', strtotime('+3 days'));
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, u.tenant_phone, u.tenant_email, b.building_name 
         FROM {$wpdb->prefix}ms_invoices i
         JOIN {$wpdb->prefix}ms_units u ON i.unit_id = u.id
         JOIN {$wpdb->prefix}ms_buildings b ON i.building_id = b.id
         WHERE DATE(i.due_date) = %s AND i.status = 'pending'",
        $three_days_later
    ));
    
    foreach ($invoices as $invoice) {
        // Send WhatsApp reminder
        if (!empty($invoice->tenant_phone)) {
            msfp_send_payment_reminder_whatsapp($invoice);
        }
        
        // Send Email reminder
        if (!empty($invoice->tenant_email)) {
            msfp_send_payment_reminder_email($invoice);
        }
        
        // Log reminder
        msfp_log_automation_event('payment_reminder', $invoice->id, $invoice->unit_id);
    }
}

/**
 * Send WhatsApp payment reminder
 */
function msfp_send_payment_reminder_whatsapp($invoice)
{
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) {
        return;
    }
    
    $invoice_data = [
        'tenant_name' => $invoice->tenant_name ?? 'المستأجر',
        'invoice_number' => $invoice->id,
        'total_amount' => $invoice->amount,
        'due_date' => $invoice->due_date,
    ];
    
    $whatsapp->send_payment_reminder($invoice->tenant_phone, $invoice_data);
}

/**
 * Send Email payment reminder
 */
function msfp_send_payment_reminder_email($invoice)
{
    $to = $invoice->tenant_email;
    $subject = sprintf('تذكير: الفاتورة رقم %d تستحق الدفع', $invoice->id);
    
    $message = sprintf(
        'السلام عليكم ورحمة الله وبركاته،<br><br>
        نذكرك بأن الفاتورة رقم <strong>%d</strong> للمبنى <strong>%s</strong> تستحق الدفع في <strong>%s</strong>.<br>
        المبلغ المستحق: <strong>%.2f ج.م</strong><br><br>
        يرجى تسديد المبلغ في الموعد المحدد لتجنب أي رسوم إضافية.<br><br>
        شكراً لك',
        $invoice->id,
        $invoice->building_name,
        $invoice->due_date,
        $invoice->amount
    );
    
    wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
}

/**
 * Late payment penalty cron job
 * Applies late fees to overdue invoices
 */
add_action('msfp_late_payment_penalty_cron', 'msfp_apply_late_payment_penalties');
function msfp_apply_late_payment_penalties()
{
    global $wpdb;
    
    // Get overdue invoices that haven't been penalized yet
    $overdue_invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, u.tenant_phone, u.tenant_email, b.building_name
         FROM {$wpdb->prefix}ms_invoices i
         JOIN {$wpdb->prefix}ms_units u ON i.unit_id = u.id
         JOIN {$wpdb->prefix}ms_buildings b ON i.building_id = b.id
         WHERE DATE(i.due_date) < DATE(NOW()) 
         AND i.status = 'pending'
         AND (i.meta_value IS NULL OR i.meta_value NOT LIKE '%penalty_applied%')",
        []
    ));
    
    $penalty_config = get_option('msfp_late_payment_config', [
        'enabled' => false,
        'type' => 'percentage',
        'value' => 5,
        'days_delay' => 1,
    ]);
    
    if (!$penalty_config['enabled']) {
        return;
    }
    
    foreach ($overdue_invoices as $invoice) {
        $days_overdue = (int) ((time() - strtotime($invoice->due_date)) / 86400);
        
        if ($days_overdue >= $penalty_config['days_delay']) {
            $penalty_amount = msfp_calculate_penalty($invoice->amount, $penalty_config);
            
            // Create penalty expense
            $wpdb->insert($wpdb->prefix . 'ms_expenses', [
                'building_id' => $invoice->building_id,
                'unit_id' => $invoice->unit_id,
                'expense_type' => 'late_payment_penalty',
                'title' => sprintf('غرامة تأخير الفاتورة #%d', $invoice->id),
                'amount' => $penalty_amount,
                'currency' => 'EGP',
                'expense_date' => current_time('Y-m-d'),
                'status' => 'approved',
                'payment_status' => 'pending',
                'source' => 'automatic',
                'reference_id' => $invoice->id,
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            
            // Update invoice meta to mark penalty as applied
            update_post_meta($invoice->id, '_penalty_applied', 'yes');
            
            // Notify tenant
            if (!empty($invoice->tenant_phone)) {
                msfp_send_penalty_notification_whatsapp($invoice, $penalty_amount);
            }
            
            if (!empty($invoice->tenant_email)) {
                msfp_send_penalty_notification_email($invoice, $penalty_amount);
            }
            
            msfp_log_automation_event('late_payment_penalty', $invoice->id, $invoice->unit_id, $penalty_amount);
        }
    }
}

/**
 * Calculate late payment penalty
 */
function msfp_calculate_penalty($amount, $config)
{
    if ($config['type'] === 'percentage') {
        return ($amount * $config['value']) / 100;
    } else {
        return (float) $config['value'];
    }
}

/**
 * Send penalty notification via WhatsApp
 */
function msfp_send_penalty_notification_whatsapp($invoice, $penalty_amount)
{
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) {
        return;
    }
    
    // Send custom message (using text API if template not available)
    $message = sprintf(
        'تنبيه: تم إضافة غرامة تأخير بمبلغ %.2f ج.م على الفاتورة رقم %d بسبب التأخر عن موعد الدفع. يرجى تسديد المبلغ الكامل في أقرب وقت.',
        $penalty_amount,
        $invoice->id
    );
    
    // Log the attempt (WhatsApp text API may require additional setup)
    msfp_log_automation_event('penalty_notification_whatsapp', $invoice->id, $invoice->unit_id);
}

/**
 * Send penalty notification via Email
 */
function msfp_send_penalty_notification_email($invoice, $penalty_amount)
{
    $to = $invoice->tenant_email;
    $subject = sprintf('تنبيه: تم إضافة غرامة تأخير على الفاتورة رقم %d', $invoice->id);
    
    $message = sprintf(
        'السلام عليكم ورحمة الله وبركاته،<br><br>
        نود إبلاغك بأنه تم إضافة <strong>غرامة تأخير</strong> على الفاتورة رقم <strong>%d</strong>.<br><br>
        <strong>التفاصيل:</strong><br>
        المبلغ الأصلي: %.2f ج.م<br>
        غرامة التأخير: %.2f ج.م<br>
        المبلغ الإجمالي المستحق: %.2f ج.م<br><br>
        يرجى تسديد المبلغ الكامل في أقرب وقت لتجنب مزيد من الرسوم الإضافية.<br><br>
        شكراً لك',
        $invoice->id,
        $invoice->amount,
        $penalty_amount,
        $invoice->amount + $penalty_amount
    );
    
    wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
}

/**
 * Maintenance follow-up cron job
 * Sends follow-up reminders for incomplete maintenance requests
 */
add_action('msfp_maintenance_followup_cron', 'msfp_send_maintenance_followups');
function msfp_send_maintenance_followups()
{
    global $wpdb;
    
    // Get maintenance requests older than 7 days without completion
    $pending_maintenance = $wpdb->get_results($wpdb->prepare(
        "SELECT wo.*, u.tenant_phone, u.tenant_email, b.building_name
         FROM {$wpdb->prefix}ms_work_orders wo
         JOIN {$wpdb->prefix}ms_units u ON wo.unit_id = u.id
         JOIN {$wpdb->prefix}ms_buildings b ON wo.building_id = b.id
         WHERE wo.status IN ('request', 'assigned', 'in_progress')
         AND DATE_ADD(wo.created_at, INTERVAL 7 DAY) <= NOW()
         AND (wo.meta_value IS NULL OR wo.meta_value NOT LIKE '%followup_sent%')"
    ));
    
    foreach ($pending_maintenance as $request) {
        // Send follow-up notification
        if (!empty($request->tenant_phone)) {
            msfp_send_maintenance_followup_whatsapp($request);
        }
        
        if (!empty($request->tenant_email)) {
            msfp_send_maintenance_followup_email($request);
        }
        
        // Mark as followed up
        update_post_meta($request->id, '_followup_sent', 'yes');
        msfp_log_automation_event('maintenance_followup', $request->id, $request->unit_id);
    }
}

/**
 * Send maintenance follow-up via WhatsApp
 */
function msfp_send_maintenance_followup_whatsapp($request)
{
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (!$whatsapp->is_enabled()) {
        return;
    }
    
    $ticket_data = [
        'tenant_name' => 'المستأجر',
        'ticket_id' => $request->id,
        'status' => $request->status,
        'title' => $request->title,
    ];
    
    $whatsapp->send_maintenance_status($request->tenant_phone, $ticket_data);
}

/**
 * Send maintenance follow-up via Email
 */
function msfp_send_maintenance_followup_email($request)
{
    $to = $request->tenant_email;
    $subject = sprintf('تحديث: طلب الصيانة رقم %d', $request->id);
    
    $status_labels = [
        'request' => 'قيد الانتظار',
        'assigned' => 'تم التعيين',
        'in_progress' => 'قيد التنفيذ',
    ];
    
    $message = sprintf(
        'السلام عليكم ورحمة الله وبركاته،<br><br>
        نود إبلاغك بأن طلب الصيانة رقم <strong>%d</strong> في المبنى <strong>%s</strong> لا يزال قيد المتابعة.<br><br>
        <strong>تفاصيل الطلب:</strong><br>
        الموضوع: %s<br>
        الحالة الحالية: <strong>%s</strong><br><br>
        سيتم إكمال الصيانة في أقرب وقت ممكن. شكراً لصبرك.',
        $request->id,
        $request->building_name,
        $request->title,
        $status_labels[$request->status] ?? $request->status
    );
    
    wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
}

/**
 * Log automation event for tracking and debugging
 */
function msfp_log_automation_event($event_type, $reference_id, $unit_id = 0, $amount = 0)
{
    global $wpdb;
    
    $wpdb->insert($wpdb->prefix . 'ms_automation_logs', [
        'event_type' => sanitize_key($event_type),
        'reference_id' => absint($reference_id),
        'unit_id' => absint($unit_id),
        'amount' => (float) $amount,
        'created_at' => current_time('mysql'),
    ], ['%s', '%d', '%d', '%f', '%s']);
}

/**
 * Create automation logs table on plugin activation
 */
function msfp_create_automation_tables()
{
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_automation_logs (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(80) NOT NULL DEFAULT 'manual',
        reference_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        amount DECIMAL(15,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY event_type (event_type),
        KEY reference_id (reference_id),
        KEY created_at (created_at)
    ) $charset_collate";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('plugins_loaded', 'msfp_create_automation_tables');

/**
 * Admin settings page for Smart Automation
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'mostaager-admin',
        'الأتمتة الذكية',
        'الأتمتة الذكية',
        'manage_options',
        'msfp-smart-automation',
        'msfp_render_automation_settings'
    );
}, 35);

function msfp_render_automation_settings()
{
    // Save settings
    if (isset($_POST['msfp_save_automation'])) {
        check_admin_referer('msfp_automation_settings');
        
        $config = [
            'enabled' => isset($_POST['penalty_enabled']),
            'type' => sanitize_text_field($_POST['penalty_type'] ?? 'percentage'),
            'value' => (float) ($_POST['penalty_value'] ?? 5),
            'days_delay' => (int) ($_POST['penalty_days'] ?? 1),
        ];
        
        update_option('msfp_late_payment_config', $config);
        echo '<div class="notice notice-success"><p>تم حفظ الإعدادات بنجاح</p></div>';
    }
    
    $config = get_option('msfp_late_payment_config', [
        'enabled' => false,
        'type' => 'percentage',
        'value' => 5,
        'days_delay' => 1,
    ]);
    ?>
    <div class="wrap">
        <h1>إعدادات الأتمتة الذكية</h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>غرامات التأخير التلقائية</h2>
            <form method="post">
                <?php wp_nonce_field('msfp_automation_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="penalty_enabled">تفعيل غرامات التأخير</label></th>
                        <td>
                            <input type="checkbox" id="penalty_enabled" name="penalty_enabled" value="1" <?php checked($config['enabled']); ?>>
                            <p class="description">تطبيق غرامات تلقائية على الفواتير المتأخرة</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="penalty_type">نوع الغرامة</label></th>
                        <td>
                            <select id="penalty_type" name="penalty_type">
                                <option value="percentage" <?php selected($config['type'], 'percentage'); ?>>نسبة مئوية (%)</option>
                                <option value="fixed" <?php selected($config['type'], 'fixed'); ?>>مبلغ ثابت (ج.م)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="penalty_value">قيمة الغرامة</label></th>
                        <td>
                            <input type="number" id="penalty_value" name="penalty_value" value="<?php echo esc_attr($config['value']); ?>" step="0.01" min="0">
                            <p class="description" id="penalty_desc">نسبة مئوية من قيمة الفاتورة</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="penalty_days">عدد أيام التأخير قبل تطبيق الغرامة</label></th>
                        <td>
                            <input type="number" id="penalty_days" name="penalty_days" value="<?php echo esc_attr($config['days_delay']); ?>" min="1">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="msfp_save_automation" class="button button-primary">حفظ الإعدادات</button>
                </p>
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>سجل الأتمتة</h2>
            <?php msfp_render_automation_logs(); ?>
        </div>
    </div>
    
    <script>
    (function(){
        var typeSelect = document.getElementById('penalty_type');
        var descEl = document.getElementById('penalty_desc');
        function updateDesc() {
            descEl.textContent = typeSelect.value === 'percentage' ? 'نسبة مئوية من قيمة الفاتورة' : 'مبلغ ثابت بالجنيه المصري';
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', updateDesc);
        }
    })();
    </script>
    <?php
}

/**
 * Render automation logs table
 */
function msfp_render_automation_logs()
{
    global $wpdb;
    
    $logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}ms_automation_logs 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    
    if (empty($logs)) {
        echo '<p>لا توجد سجلات أتمتة حتى الآن.</p>';
        return;
    }
    
    echo '<table class="widefat"><thead><tr><th>النوع</th><th>المرجع</th><th>المبلغ</th><th>التاريخ</th></tr></thead><tbody>';
    
    foreach ($logs as $log) {
        echo sprintf(
            '<tr><td>%s</td><td>#%d</td><td>%.2f</td><td>%s</td></tr>',
            esc_html($log->event_type),
            $log->reference_id,
            $log->amount,
            esc_html($log->created_at)
        );
    }
    
    echo '</tbody></table>';
}

/**
 * Clean up automation logs older than 90 days
 */
add_action('msfp_cleanup_automation_logs', 'msfp_cleanup_old_logs');
function msfp_cleanup_old_logs()
{
    global $wpdb;
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}ms_automation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    ));
}

if (!wp_next_scheduled('msfp_cleanup_automation_logs')) {
    wp_schedule_event(time(), 'weekly', 'msfp_cleanup_automation_logs');
}
