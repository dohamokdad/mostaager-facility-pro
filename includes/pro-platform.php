<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostaager Pro Platform layer.
 * Adds normalized SaaS-grade modules while preserving the legacy ms_* and Houzez integrations.
 */

if (!defined('MOSTAAGER_PRO_PLATFORM_DB_VERSION')) {
    define('MOSTAAGER_PRO_PLATFORM_DB_VERSION', '14.0.0');
}

function msfp_install_pro_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $sqls = array();

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_expenses (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        vendor_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        expense_type VARCHAR(80) NOT NULL DEFAULT 'other',
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'EGP',
        expense_date DATE NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'approved',
        payment_status VARCHAR(40) NOT NULL DEFAULT 'paid',
        source VARCHAR(40) NOT NULL DEFAULT 'manual',
        reference_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY expense_type (expense_type),
        KEY status (status),
        KEY expense_date (expense_date)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_expense_attachments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        expense_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        file_type VARCHAR(100) DEFAULT '',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY expense_id (expense_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_orders (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        request_id BIGINT(20) UNSIGNED DEFAULT 0,
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'request',
        priority VARCHAR(40) NOT NULL DEFAULT 'medium',
        technician_id BIGINT(20) UNSIGNED DEFAULT 0,
        requested_by BIGINT(20) UNSIGNED DEFAULT 0,
        assigned_by BIGINT(20) UNSIGNED DEFAULT 0,
        estimated_cost DECIMAL(15,2) DEFAULT 0.00,
        actual_cost DECIMAL(15,2) DEFAULT 0.00,
        due_date DATE NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        closed_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY status (status),
        KEY technician_id (technician_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_order_events (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        event_type VARCHAR(80) NOT NULL DEFAULT 'note',
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        old_status VARCHAR(40) DEFAULT '',
        new_status VARCHAR(40) DEFAULT '',
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id),
        KEY event_type (event_type)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_order_attachments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        file_type VARCHAR(100) DEFAULT '',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_technician_ratings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        technician_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        rated_by BIGINT(20) UNSIGNED DEFAULT 0,
        rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
        review TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id),
        KEY technician_id (technician_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_owner_reports (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        report_month VARCHAR(7) NOT NULL DEFAULT '',
        income DECIMAL(15,2) DEFAULT 0.00,
        expenses DECIMAL(15,2) DEFAULT 0.00,
        maintenance DECIMAL(15,2) DEFAULT 0.00,
        profit DECIMAL(15,2) DEFAULT 0.00,
        file_url TEXT NULL,
        emailed_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_month (owner_id, report_month),
        KEY building_id (building_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_documents (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        document_type VARCHAR(80) NOT NULL DEFAULT 'other',
        title VARCHAR(191) NOT NULL,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        expiry_date DATE NULL,
        visibility VARCHAR(40) DEFAULT 'private',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY document_type (document_type)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_meter_readings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        tenant_id BIGINT(20) UNSIGNED DEFAULT 0,
        meter_type VARCHAR(40) NOT NULL DEFAULT 'electricity',
        reading_value DECIMAL(15,3) NOT NULL DEFAULT 0.000,
        reading_date DATE NULL,
        image_id BIGINT(20) UNSIGNED DEFAULT 0,
        image_url TEXT NULL,
        notes TEXT NULL,
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY meter_type (meter_type),
        KEY reading_date (reading_date)
    ) $charset_collate";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }

    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'building_type', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN building_type VARCHAR(80) DEFAULT 'residential'");
    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'address', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN address TEXT NULL");
    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'status', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN status VARCHAR(40) DEFAULT 'active'");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'unit_number', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN unit_number VARCHAR(80) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'floor', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN floor VARCHAR(80) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'monthly_rent', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN monthly_rent DECIMAL(15,2) DEFAULT 0.00");

    update_option('mostaager_pro_platform_db_version', MOSTAAGER_PRO_PLATFORM_DB_VERSION);
}

function msfp_add_missing_column($table, $column, $alter_sql)
{
    global $wpdb;

    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
        $table
    ));
    if (empty($table_exists)) {
        return;
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table,
        $column
    ));
    if (empty($exists)) {
        $wpdb->query($alter_sql);
    }
}

add_action('plugins_loaded', function () {
    if (get_option('mostaager_pro_platform_db_version') !== MOSTAAGER_PRO_PLATFORM_DB_VERSION) {
        msfp_install_pro_tables();
    }
}, 30);

function msfp_current_user_can_manage_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id || !is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    $user_id = get_current_user_id();
    if (function_exists('ms_current_user_manages_building') && ms_current_user_manages_building($user_id, $building_id)) {
        return true;
    }
    $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user_id) : array();
    foreach ((array) $buildings as $building) {
        if (intval($building->id ?? 0) === $building_id) {
            return true;
        }
    }
    return false;
}

function msfp_expense_type_labels()
{
    return array(
        'electricity' => 'كهرباء',
        'water' => 'مياه',
        'security' => 'حراسة',
        'cleaning' => 'تنظيف',
        'maintenance' => 'صيانة',
        'supplier' => 'موردين',
        'other' => 'أخرى',
    );
}

function msfp_work_order_status_labels()
{
    return array(
        'request' => 'طلب جديد',
        'assigned' => 'تم الإسناد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'closed' => 'مغلق',
    );
}

function msfp_get_financial_summary($building_id, $month = '')
{
    global $wpdb;
    $building_id = absint($building_id);
    $month = $month ? sanitize_text_field($month) : current_time('Y-m');

    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));

    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $expense_table = $wpdb->prefix . 'ms_expenses';
    $wallet_table = $wpdb->prefix . 'ms_building_wallet';

    $monthly_income = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$invoice_table} WHERE building_id = %d AND status = 'paid' AND DATE(COALESCE(paid_date, created_at)) BETWEEN %s AND %s",
        $building_id,
        $start,
        $end
    ));

    $monthly_expenses = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$expense_table} WHERE building_id = %d AND status IN ('approved','paid') AND DATE(COALESCE(expense_date, created_at)) BETWEEN %s AND %s",
        $building_id,
        $start,
        $end
    ));

    $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM {$wallet_table} WHERE building_id = %d", $building_id));
    $balance = $balance !== null ? (float) $balance : ($monthly_income - $monthly_expenses);

    return array(
        'current_balance' => $balance,
        'monthly_income' => $monthly_income,
        'monthly_expenses' => $monthly_expenses,
        'net_profit' => $monthly_income - $monthly_expenses,
        'month' => $month,
    );
}

function msfp_get_building_expenses($building_id, $limit = 50)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_expenses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE building_id = %d ORDER BY COALESCE(expense_date, DATE(created_at)) DESC, id DESC LIMIT %d",
        absint($building_id),
        absint($limit)
    ));
}

function msfp_record_wallet_transaction($building_id, $amount, $type, $description, $reference_id = 0)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = (float) $amount;
    if (!$building_id || $amount == 0.0) {
        return false;
    }

    $wallet_table = $wpdb->prefix . 'ms_building_wallet';
    $tx_table = $wpdb->prefix . 'ms_building_wallet_transactions';

    $wallet_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wallet_table} WHERE building_id = %d", $building_id));
    if (!$wallet_id) {
        $wpdb->insert($wallet_table, array(
            'building_id' => $building_id,
            'balance' => 0,
            'target_amount' => 0,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ), array('%d', '%f', '%f', '%s', '%s'));
    }

    $wpdb->query($wpdb->prepare("UPDATE {$wallet_table} SET balance = balance + %f, updated_at = %s WHERE building_id = %d", $amount, current_time('mysql'), $building_id));
    return $wpdb->insert($tx_table, array(
        'building_id' => $building_id,
        'amount' => $amount,
        'type' => sanitize_key($type),
        'description' => sanitize_text_field($description),
        'reference_id' => absint($reference_id),
        'created_at' => current_time('mysql'),
    ), array('%d', '%f', '%s', '%s', '%d', '%s'));
}

function msfp_render_financial_center($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>المركز المالي</h3><p>يرجى اختيار مبنى لعرض المركز المالي.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>المركز المالي</h3><p>ليس لديك صلاحية لعرض بيانات هذا المبنى.</p></div>';
    }

    $summary = msfp_get_financial_summary($building_id);
    ob_start();
    ?>
    <div class="ms-card msfp-financial-center">
        <h3>Financial Center - المركز المالي</h3>
        <p style="color:#64748b;margin-top:4px;">ملخص مالي شهري للمبنى المختار، يشمل الرصيد الحالي والدخل والمصروفات وصافي الربح.</p>
        <div class="ms-grid" style="margin-top:16px;">
            <div class="ms-card"><h4>Current Balance</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['current_balance'], 2); ?></div></div>
            <div class="ms-card"><h4>Monthly Income</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['monthly_income'], 2); ?></div></div>
            <div class="ms-card"><h4>Monthly Expenses</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['monthly_expenses'], 2); ?></div></div>
            <div class="ms-card"><h4>Net Profit</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['net_profit'], 2); ?></div></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function msfp_render_expenses_center($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>المصروفات</h3><p>يرجى اختيار مبنى لإدارة المصروفات.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>المصروفات</h3><p>ليس لديك صلاحية لإدارة مصروفات هذا المبنى.</p></div>';
    }

    $types = msfp_expense_type_labels();
    $expenses = msfp_get_building_expenses($building_id, 100);
    ob_start();
    ?>
    <div class="ms-card msfp-expenses-center">
        <h3>Expenses - إدارة المصروفات</h3>
        <form id="msfp-expense-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;align-items:end;">
            <input type="hidden" name="action" value="msfp_add_expense">
            <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_expense_nonce')); ?>">
            <label>العنوان<input type="text" name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>النوع<select name="expense_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                <?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select></label>
            <label>المبلغ<input type="number" name="amount" min="0" step="0.01" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ المصروف<input type="date" name="expense_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>مرفق PDF/صورة<input type="file" name="attachment" accept="image/*,.pdf" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label style="grid-column:1/-1;">الوصف<textarea name="description" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></textarea></label>
            <button type="submit" style="padding:11px 18px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">حفظ المصروف</button>
            <div id="msfp-expense-message" style="display:none;color:#059669;font-weight:600;"></div>
        </form>

        <h4 style="margin-top:24px;">آخر المصروفات</h4>
        <?php if (empty($expenses)) : ?>
            <p>لا توجد مصروفات مسجلة لهذا المبنى بعد.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">التاريخ</th><th style="padding:12px;">العنوان</th><th style="padding:12px;">النوع</th><th style="padding:12px;">المبلغ</th><th style="padding:12px;">الحالة</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $expense) : ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;"><?php echo esc_html($expense->expense_date ?: mysql2date('Y-m-d', $expense->created_at)); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($expense->title); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($types[$expense->expense_type] ?? $expense->expense_type); ?></td>
                        <td style="padding:12px;">ج.م <?php echo number_format_i18n((float) $expense->amount, 2); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($expense->status); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var form = document.getElementById('msfp-expense-form');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var msg = document.getElementById('msfp-expense-message');
            var data = new FormData(form);
            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', { method: 'POST', credentials: 'same-origin', body: data })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    msg.style.display = 'block';
                    msg.style.color = res.success ? '#059669' : '#dc2626';
                    msg.textContent = res.data && res.data.message ? res.data.message : (res.success ? 'تم الحفظ بنجاح.' : 'تعذر حفظ المصروف.');
                    if (res.success) { setTimeout(function(){ window.location.reload(); }, 900); }
                })
                .catch(function(){ msg.style.display = 'block'; msg.style.color = '#dc2626'; msg.textContent = 'حدث خطأ في الاتصال.'; });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_financial_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_financial_center');
    return msfp_render_financial_center(absint($atts['building_id']));
});

add_shortcode('mostaager_expenses_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_expenses_center');
    return msfp_render_expenses_center(absint($atts['building_id']));
});

add_action('wp_ajax_msfp_add_expense', 'msfp_ajax_add_expense');
function msfp_ajax_add_expense()
{
    check_ajax_referer('msfp_expense_nonce', 'security');

    $building_id = absint($_POST['building_id'] ?? 0);
    if (!msfp_current_user_can_manage_building($building_id)) {
        wp_send_json_error(array('message' => 'ليست لديك صلاحية لإضافة مصروف لهذا المبنى.'), 403);
    }

    $title = sanitize_text_field($_POST['title'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($title === '' || $amount <= 0) {
        wp_send_json_error(array('message' => 'يرجى إدخال عنوان ومبلغ صحيحين.'), 422);
    }

    global $wpdb;
    $expense_table = $wpdb->prefix . 'ms_expenses';
    $inserted = $wpdb->insert($expense_table, array(
        'building_id' => $building_id,
        'created_by' => get_current_user_id(),
        'expense_type' => sanitize_key($_POST['expense_type'] ?? 'other'),
        'title' => $title,
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'amount' => $amount,
        'currency' => 'EGP',
        'expense_date' => sanitize_text_field($_POST['expense_date'] ?? current_time('Y-m-d')),
        'status' => 'approved',
        'payment_status' => 'paid',
        'source' => 'manual',
        'created_at' => current_time('mysql'),
    ), array('%d','%d','%s','%s','%s','%f','%s','%s','%s','%s','%s','%s'));

    if (!$inserted) {
        wp_send_json_error(array('message' => 'تعذر حفظ المصروف في قاعدة البيانات.'), 500);
    }

    $expense_id = (int) $wpdb->insert_id;

    if (!empty($_FILES['attachment']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload('attachment', 0);
        if (!is_wp_error($attachment_id)) {
            $wpdb->insert($wpdb->prefix . 'ms_expense_attachments', array(
                'expense_id' => $expense_id,
                'file_id' => $attachment_id,
                'file_url' => wp_get_attachment_url($attachment_id),
                'file_type' => get_post_mime_type($attachment_id),
                'uploaded_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ), array('%d','%d','%s','%s','%d','%s'));
        }
    }

    msfp_record_wallet_transaction($building_id, -1 * $amount, 'expense', 'مصروف: ' . $title, $expense_id);
    wp_send_json_success(array('message' => 'تم حفظ المصروف وتحديث محفظة المبنى بنجاح.', 'expense_id' => $expense_id));
}

function msfp_get_building_work_orders($building_id, $limit = 50)
{
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE building_id = %d ORDER BY id DESC LIMIT %d",
        absint($building_id),
        absint($limit)
    ));
}

function msfp_add_work_order_event($work_order_id, $event_type, $title, $description = '', $old_status = '', $new_status = '')
{
    global $wpdb;
    return $wpdb->insert($wpdb->prefix . 'ms_work_order_events', array(
        'work_order_id' => absint($work_order_id),
        'event_type' => sanitize_key($event_type),
        'title' => sanitize_text_field($title),
        'description' => sanitize_textarea_field($description),
        'old_status' => sanitize_key($old_status),
        'new_status' => sanitize_key($new_status),
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%s','%d','%s'));
}

function msfp_render_maintenance_pro($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>Maintenance Center PRO</h3><p>يرجى اختيار مبنى لعرض مركز الصيانة.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>Maintenance Center PRO</h3><p>ليس لديك صلاحية لإدارة صيانة هذا المبنى.</p></div>';
    }

    $orders = msfp_get_building_work_orders($building_id, 100);
    $statuses = msfp_work_order_status_labels();
    ob_start();
    ?>
    <div class="ms-card msfp-maintenance-pro">
        <h3>Maintenance Center PRO - مركز الصيانة المتقدم</h3>
        <form id="msfp-work-order-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;align-items:end;">
            <input type="hidden" name="action" value="msfp_add_work_order">
            <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>">
            <label>العنوان<input type="text" name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>الأولوية<select name="priority" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="low">منخفضة</option><option value="medium" selected>متوسطة</option><option value="high">عالية</option><option value="emergency">طارئة</option></select></label>
            <label>الفني / المستخدم<input type="number" name="technician_id" min="0" placeholder="User ID" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>التكلفة المتوقعة<input type="number" name="estimated_cost" min="0" step="0.01" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ الإنجاز المتوقع<input type="date" name="due_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label style="grid-column:1/-1;">الوصف<textarea name="description" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></textarea></label>
            <button type="submit" style="padding:11px 18px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">إنشاء أمر عمل</button>
            <div id="msfp-work-order-message" style="display:none;font-weight:600;"></div>
        </form>
        <h4 style="margin-top:24px;">أوامر العمل</h4>
        <?php if (empty($orders)) : ?>
            <p>لا توجد أوامر صيانة متقدمة بعد.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">#</th><th style="padding:12px;">العنوان</th><th style="padding:12px;">الحالة</th><th style="padding:12px;">الفني</th><th style="padding:12px;">التكلفة</th><th style="padding:12px;">إجراء</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order) : ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;">#<?php echo esc_html($order->id); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($order->title); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($statuses[$order->status] ?? $order->status); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($order->technician_id ? (get_userdata($order->technician_id)->display_name ?? '#' . $order->technician_id) : 'غير مسند'); ?></td>
                        <td style="padding:12px;">ج.م <?php echo number_format_i18n((float) $order->actual_cost ?: (float) $order->estimated_cost, 2); ?></td>
                        <td style="padding:12px;">
                            <select class="msfp-wo-status" data-id="<?php echo esc_attr($order->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>">
                                <?php foreach ($statuses as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($order->status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var form = document.getElementById('msfp-work-order-form');
        var ajax = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        if (form && form.dataset.bound !== '1') {
            form.dataset.bound = '1';
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var msg = document.getElementById('msfp-work-order-message');
                fetch(ajax, {method:'POST', credentials:'same-origin', body:new FormData(form)}).then(function(r){return r.json();}).then(function(res){
                    msg.style.display='block'; msg.style.color=res.success?'#059669':'#dc2626'; msg.textContent=(res.data&&res.data.message)?res.data.message:'تمت العملية.'; if(res.success){setTimeout(function(){location.reload();},900);}
                }).catch(function(){msg.style.display='block';msg.style.color='#dc2626';msg.textContent='حدث خطأ في الاتصال.';});
            });
        }
        document.querySelectorAll('.msfp-wo-status').forEach(function(sel){
            if (sel.dataset.bound === '1') return; sel.dataset.bound='1';
            sel.addEventListener('change', function(){
                var fd = new FormData(); fd.append('action','msfp_update_work_order_status'); fd.append('work_order_id', sel.dataset.id); fd.append('status', sel.value); fd.append('security', sel.dataset.security);
                fetch(ajax, {method:'POST', credentials:'same-origin', body:fd}).then(function(){ setTimeout(function(){location.reload();},400); });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_maintenance_pro', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_maintenance_pro');
    return msfp_render_maintenance_pro(absint($atts['building_id']));
});

add_action('wp_ajax_msfp_add_work_order', 'msfp_ajax_add_work_order');
function msfp_ajax_add_work_order()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    $building_id = absint($_POST['building_id'] ?? 0);
    if (!msfp_current_user_can_manage_building($building_id)) {
        wp_send_json_error(array('message' => 'ليست لديك صلاحية لإدارة هذا المبنى.'), 403);
    }
    $title = sanitize_text_field($_POST['title'] ?? '');
    if ($title === '') {
        wp_send_json_error(array('message' => 'يرجى إدخال عنوان أمر العمل.'), 422);
    }
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ms_work_orders', array(
        'building_id' => $building_id,
        'title' => $title,
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'status' => !empty($_POST['technician_id']) ? 'assigned' : 'request',
        'priority' => sanitize_key($_POST['priority'] ?? 'medium'),
        'technician_id' => absint($_POST['technician_id'] ?? 0),
        'requested_by' => get_current_user_id(),
        'assigned_by' => !empty($_POST['technician_id']) ? get_current_user_id() : 0,
        'estimated_cost' => (float) ($_POST['estimated_cost'] ?? 0),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%d','%d','%d','%f','%s','%s'));
    $id = (int) $wpdb->insert_id;
    msfp_add_work_order_event($id, 'created', 'Request Created', 'تم إنشاء أمر العمل.', '', !empty($_POST['technician_id']) ? 'assigned' : 'request');
    wp_send_json_success(array('message' => 'تم إنشاء أمر العمل بنجاح.', 'work_order_id' => $id));
}

add_action('wp_ajax_msfp_update_work_order_status', 'msfp_ajax_update_work_order_status');
function msfp_ajax_update_work_order_status()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    global $wpdb;
    $id = absint($_POST['work_order_id'] ?? 0);
    $status = sanitize_key($_POST['status'] ?? 'request');
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE id = %d", $id));
    if (!$order || !msfp_current_user_can_manage_building($order->building_id)) {
        wp_send_json_error(array('message' => 'غير مسموح.'), 403);
    }
    $extra = array('status' => $status, 'updated_at' => current_time('mysql'));
    if ($status === 'in_progress') { $extra['started_at'] = current_time('mysql'); }
    if ($status === 'completed') { $extra['completed_at'] = current_time('mysql'); }
    if ($status === 'closed') { $extra['closed_at'] = current_time('mysql'); }
    $wpdb->update($wpdb->prefix . 'ms_work_orders', $extra, array('id' => $id));
    msfp_add_work_order_event($id, 'status_change', 'Status Updated', 'تم تحديث حالة أمر العمل.', $order->status, $status);
    wp_send_json_success(array('message' => 'تم تحديث الحالة.'));
}

function msfp_render_owner_reports($owner_id = 0)
{
    $owner_id = absint($owner_id ?: get_current_user_id());
    if (!$owner_id || !is_user_logged_in()) {
        return '<div class="ms-card"><p>يرجى تسجيل الدخول لعرض تقارير المالك.</p></div>';
    }
    global $wpdb;
    $reports = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_owner_reports WHERE owner_id = %d ORDER BY report_month DESC, id DESC LIMIT 24", $owner_id));
    ob_start();
    ?>
    <div class="ms-card msfp-owner-reports"><h3>Owner Reports PDF - تقارير المالك</h3>
        <form method="post" style="margin:12px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            <?php wp_nonce_field('msfp_generate_owner_report', 'msfp_owner_report_nonce'); ?>
            <input type="hidden" name="msfp_owner_report_action" value="generate">
            <label>الشهر<input type="month" name="report_month" value="<?php echo esc_attr(current_time('Y-m')); ?>" style="padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">إنشاء تقرير شهري</button>
        </form>
        <?php if (empty($reports)) : ?><p>لا توجد تقارير محفوظة بعد.</p><?php else : ?>
        <table style="width:100%;border-collapse:collapse;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">الشهر</th><th style="padding:12px;">الإيرادات</th><th style="padding:12px;">المصروفات</th><th style="padding:12px;">الأرباح</th><th style="padding:12px;">الملف</th></tr></thead><tbody>
        <?php foreach ($reports as $report) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php echo esc_html($report->report_month); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->income,2); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->expenses,2); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->profit,2); ?></td><td style="padding:12px;"><?php echo $report->file_url ? '<a href="'.esc_url($report->file_url).'" target="_blank">تحميل</a>' : '—'; ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_owner_reports', function ($atts) {
    $atts = shortcode_atts(array('owner_id' => 0), $atts, 'mostaager_owner_reports');
    return msfp_render_owner_reports(absint($atts['owner_id']));
});

add_action('init', function () {
    if (empty($_POST['msfp_owner_report_action']) || $_POST['msfp_owner_report_action'] !== 'generate') {
        return;
    }
    if (!is_user_logged_in() || empty($_POST['msfp_owner_report_nonce']) || !wp_verify_nonce($_POST['msfp_owner_report_nonce'], 'msfp_generate_owner_report')) {
        return;
    }
    global $wpdb;
    $owner_id = get_current_user_id();
    $month = sanitize_text_field($_POST['report_month'] ?? current_time('Y-m'));
    $income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ms_invoices WHERE status='paid' AND DATE_FORMAT(COALESCE(paid_date, created_at), '%%Y-%%m') = %s", $month));
    $expenses = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ms_expenses WHERE DATE_FORMAT(COALESCE(expense_date, created_at), '%%Y-%%m') = %s", $month));
    $profit = $income - $expenses;
    $wpdb->insert($wpdb->prefix . 'ms_owner_reports', array('owner_id'=>$owner_id,'report_month'=>$month,'income'=>$income,'expenses'=>$expenses,'maintenance'=>0,'profit'=>$profit,'created_at'=>current_time('mysql')), array('%d','%s','%f','%f','%f','%f','%s'));
});

function msfp_render_documents_center($user_id = 0)
{
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !is_user_logged_in()) { return '<div class="ms-card"><p>يرجى تسجيل الدخول لعرض مركز المستندات.</p></div>'; }
    global $wpdb;
    $docs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_documents WHERE user_id = %d ORDER BY id DESC LIMIT 100", $user_id));
    ob_start(); ?>
    <div class="ms-card msfp-documents"><h3>Documents Center - مركز المستندات</h3>
        <form id="msfp-document-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end;">
            <input type="hidden" name="action" value="msfp_upload_document"><input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_document_nonce')); ?>">
            <label>العنوان<input name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>النوع<select name="document_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="lease_contract">عقد الإيجار</option><option value="identity">الهوية</option><option value="insurance">التأمين</option><option value="other">أخرى</option></select></label>
            <label>الملف<input type="file" name="document_file" required accept="image/*,.pdf,.doc,.docx" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">رفع المستند</button><div id="msfp-document-message" style="display:none;font-weight:600;"></div>
        </form>
        <?php if (empty($docs)) : ?><p style="margin-top:16px;">لا توجد مستندات بعد.</p><?php else : ?><table style="width:100%;border-collapse:collapse;margin-top:16px;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">العنوان</th><th style="padding:12px;">النوع</th><th style="padding:12px;">تاريخ الرفع</th><th style="padding:12px;">تحميل</th></tr></thead><tbody><?php foreach ($docs as $doc) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php echo esc_html($doc->title); ?></td><td style="padding:12px;"><?php echo esc_html($doc->document_type); ?></td><td style="padding:12px;"><?php echo esc_html($doc->created_at); ?></td><td style="padding:12px;"><a href="<?php echo esc_url($doc->file_url); ?>" target="_blank">تحميل</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    </div><script>(function(){var f=document.getElementById('msfp-document-form');if(!f||f.dataset.bound==='1')return;f.dataset.bound='1';f.addEventListener('submit',function(e){e.preventDefault();var m=document.getElementById('msfp-document-message');fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>',{method:'POST',credentials:'same-origin',body:new FormData(f)}).then(function(r){return r.json();}).then(function(res){m.style.display='block';m.style.color=res.success?'#059669':'#dc2626';m.textContent=res.data&&res.data.message?res.data.message:'تمت العملية.';if(res.success)setTimeout(function(){location.reload();},800);});});})();</script>
    <?php return ob_get_clean();
}
add_shortcode('mostaager_tenant_documents', function ($atts) { $atts = shortcode_atts(array('user_id'=>0), $atts, 'mostaager_tenant_documents'); return msfp_render_documents_center(absint($atts['user_id'])); });

add_action('wp_ajax_msfp_upload_document', function () {
    check_ajax_referer('msfp_document_nonce', 'security');
    if (!is_user_logged_in()) { wp_send_json_error(array('message'=>'يرجى تسجيل الدخول.'),403); }
    if (empty($_FILES['document_file']['name'])) { wp_send_json_error(array('message'=>'يرجى اختيار ملف.'),422); }
    require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
    $file_id = media_handle_upload('document_file', 0);
    if (is_wp_error($file_id)) { wp_send_json_error(array('message'=>$file_id->get_error_message()),500); }
    global $wpdb; $wpdb->insert($wpdb->prefix.'ms_documents', array('user_id'=>get_current_user_id(),'document_type'=>sanitize_key($_POST['document_type'] ?? 'other'),'title'=>sanitize_text_field($_POST['title'] ?? 'Document'),'file_id'=>$file_id,'file_url'=>wp_get_attachment_url($file_id),'uploaded_by'=>get_current_user_id(),'created_at'=>current_time('mysql')), array('%d','%s','%s','%d','%s','%d','%s'));
    wp_send_json_success(array('message'=>'تم رفع المستند بنجاح.'));
});

function msfp_render_meter_readings($unit_id = 0)
{
    $unit_id = absint($unit_id ?: ($_GET['unit_id'] ?? 0));
    if (!is_user_logged_in()) { return '<div class="ms-card"><p>يرجى تسجيل الدخول لرفع قراءات العدادات.</p></div>'; }
    global $wpdb; $where = $unit_id ? $wpdb->prepare('unit_id=%d', $unit_id) : $wpdb->prepare('tenant_id=%d OR created_by=%d', get_current_user_id(), get_current_user_id());
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_meter_readings WHERE {$where} ORDER BY reading_date DESC, id DESC LIMIT 100");
    ob_start(); ?>
    <div class="ms-card msfp-meters"><h3>Meter Readings - قراءات العدادات</h3>
        <form id="msfp-meter-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
            <input type="hidden" name="action" value="msfp_add_meter_reading"><input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_meter_nonce')); ?>"><input type="hidden" name="unit_id" value="<?php echo esc_attr($unit_id); ?>">
            <label>نوع العداد<select name="meter_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="electricity">Electricity</option><option value="water">Water</option><option value="gas">Gas</option></select></label>
            <label>القراءة<input type="number" name="reading_value" step="0.001" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>التاريخ<input type="date" name="reading_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>صورة العداد<input type="file" name="meter_image" accept="image/*" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">حفظ القراءة</button><div id="msfp-meter-message" style="display:none;font-weight:600;"></div>
        </form>
        <?php if (empty($rows)) : ?><p style="margin-top:16px;">لا توجد قراءات مسجلة بعد.</p><?php else : ?><table style="width:100%;border-collapse:collapse;margin-top:16px;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">النوع</th><th style="padding:12px;">القراءة</th><th style="padding:12px;">التاريخ</th><th style="padding:12px;">الصورة</th></tr></thead><tbody><?php foreach ($rows as $row) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php echo esc_html($row->meter_type); ?></td><td style="padding:12px;"><?php echo esc_html($row->reading_value); ?></td><td style="padding:12px;"><?php echo esc_html($row->reading_date); ?></td><td style="padding:12px;"><?php echo $row->image_url ? '<a href="'.esc_url($row->image_url).'" target="_blank">عرض</a>' : '—'; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    </div><script>(function(){var f=document.getElementById('msfp-meter-form');if(!f||f.dataset.bound==='1')return;f.dataset.bound='1';f.addEventListener('submit',function(e){e.preventDefault();var m=document.getElementById('msfp-meter-message');fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>',{method:'POST',credentials:'same-origin',body:new FormData(f)}).then(function(r){return r.json();}).then(function(res){m.style.display='block';m.style.color=res.success?'#059669':'#dc2626';m.textContent=res.data&&res.data.message?res.data.message:'تمت العملية.';if(res.success)setTimeout(function(){location.reload();},800);});});})();</script>
    <?php return ob_get_clean();
}
add_shortcode('mostaager_meter_readings', function ($atts) { $atts = shortcode_atts(array('unit_id'=>0), $atts, 'mostaager_meter_readings'); return msfp_render_meter_readings(absint($atts['unit_id'])); });

add_action('wp_ajax_msfp_add_meter_reading', function () {
    check_ajax_referer('msfp_meter_nonce', 'security'); if (!is_user_logged_in()) { wp_send_json_error(array('message'=>'يرجى تسجيل الدخول.'),403); }
    $image_id = 0; $image_url = '';
    if (!empty($_FILES['meter_image']['name'])) { require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php'; $image_id = media_handle_upload('meter_image', 0); if (!is_wp_error($image_id)) { $image_url = wp_get_attachment_url($image_id); } else { $image_id = 0; } }
    global $wpdb; $wpdb->insert($wpdb->prefix.'ms_meter_readings', array('unit_id'=>absint($_POST['unit_id'] ?? 0),'tenant_id'=>get_current_user_id(),'meter_type'=>sanitize_key($_POST['meter_type'] ?? 'electricity'),'reading_value'=>(float)($_POST['reading_value'] ?? 0),'reading_date'=>sanitize_text_field($_POST['reading_date'] ?? current_time('Y-m-d')),'image_id'=>$image_id,'image_url'=>$image_url,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')), array('%d','%d','%s','%f','%s','%d','%s','%d','%s'));
    wp_send_json_success(array('message'=>'تم حفظ قراءة العداد بنجاح.'));
});
