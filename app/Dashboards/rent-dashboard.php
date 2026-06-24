<?php
if (!defined('ABSPATH')) exit;

add_shortcode('rent_dashboard_v4', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض لوحة المستأجر.</p>';
    }

    ob_start();

    $user = wp_get_current_user();
    $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;
    $invoices = function_exists('ms_get_tenant_invoices') ? ms_get_tenant_invoices($user->ID) : array();
    $pending_count = function_exists('ms_get_invoices_count_by_user_and_status') ? ms_get_invoices_count_by_user_and_status($user->ID, 'pending') : 0;
    $overdue_count = function_exists('ms_get_user_overdue_count') ? ms_get_user_overdue_count($user->ID) : 0;
    $next_due = function_exists('ms_get_latest_due_invoice') ? ms_get_latest_due_invoice($user->ID) : null;
    $rent_streak = function_exists('ms_get_rent_streak_badge') ? ms_get_rent_streak_badge($user->ID) : array('label' => 'غير متوفر', 'streak' => 0, 'color' => '#64748b');
    $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();
    $unread_notifications_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;

    // Tenant unit lookup (ms_unit_tenants priority)
    $tenant_unit = null;
    $tenant_building_id = 0;
    $tenant_unit_id = 0;
    if (function_exists('ms_get_tenant_unit')) {
        $tenant_unit = ms_get_tenant_unit($user->ID);
        if ($tenant_unit) {
            $tenant_unit_id = intval($tenant_unit->unit_id ?? 0);
            $tenant_building_id = intval($tenant_unit->building_id ?? 0);
        }
    } else {
        global $wpdb;
        if ($wpdb) {
            $ten_table = $wpdb->prefix . 'ms_unit_tenants';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ten_table} WHERE (tenant_id = %d OR tenant_user_id = %d) AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1", $user->ID, $user->ID));
            if ($row) {
                $tenant_unit = $row;
                $tenant_unit_id = intval($row->unit_id ?? 0);
                $tenant_building_id = intval($row->building_id ?? 0);
            }
        }
    }

    // Tenant maintenance requests and wallet transactions
    $tenant_maintenance_requests = array();
    if ($tenant_building_id) {
        $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
        $tenant_maintenance_requests = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$maint_table} WHERE building_id = %d ORDER BY created_at DESC LIMIT 50", $tenant_building_id));
    }

    $tenant_wallet_transactions = array();
    if (function_exists('ms_get_wallet_transactions_for_user')) {
        $tenant_wallet_transactions = ms_get_wallet_transactions_for_user($user->ID, 20);
    } elseif (function_exists('ms_get_wallet_transactions')) {
        $tenant_wallet_transactions = ms_get_wallet_transactions($user->ID, 20);
    } else {
        global $wpdb;
        $tx_table = $wpdb->prefix . 'ms_wallet_transactions';
        $tenant_wallet_transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tx_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user->ID,
            20
        ));
    }

    ?>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '📋', 'active' => true),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾', 'badge' => $pending_count),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'المحفظة', 'data_tab' => 'wallet', 'icon' => '💳'),
            array('label' => 'المستندات', 'data_tab' => 'documents', 'icon' => '📁'),
            array('label' => 'العدادات', 'data_tab' => 'meters', 'icon' => '📟'),
            array('label' => 'الإشعارات', 'data_tab' => 'notifications', 'icon' => '🔔', 'badge' => $unread_notifications_count),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">

            <div class="ms-tab-content active" id="overview">
                <h1>مرحباً، <?php echo esc_html($user->display_name); ?></h1>
                <div class="ms-grid">
                    <div class="ms-card"><h3>الإيجار القادم</h3><div id="ms-next-rent" class="ms-number"><?php echo $next_due ? 'ج.م ' . number_format_i18n($next_due->amount, 2) : '—'; ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">تاريخ الاستحقاق: <span id="ms-next-due-date"><?php echo $next_due ? esc_html($next_due->due_date) : '—'; ?></span></div>
                    </div>
                    <div class="ms-card"><h3>رصيد المحفظة</h3><div id="ms-rent-wallet" class="ms-number"><?php echo 'ج.م ' . number_format_i18n($wallet, 2); ?></div></div>
                    <div class="ms-card"><h3>عدد الفواتير</h3><div id="ms-rent-invoices" class="ms-number"><?php echo intval(count($invoices)); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">معلقة: <span id="ms-rent-pending"><?php echo intval($pending_count); ?></span></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">متأخرة: <span id="ms-rent-overdue"><?php echo intval($overdue_count); ?></span></div>
                    </div>
                </div>
                <?php echo do_shortcode('[rent_streak_badge]'); ?>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card">
                    <h3>فواتيري</h3>
                    <div class="ms-table-wrap">
                        <table class="ms-table" style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th>الوصف</th>
                                    <th>المبنى</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>تاريخ الاستحقاق</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($invoices)) : ?>
                                    <?php foreach ($invoices as $inv) : ?>
                                        <tr>
                                            <td><?php echo esc_html(!empty($inv->description) ? $inv->description : 'فاتورة'); ?></td>
                                            <td><?php echo esc_html((isset($inv->property_id) && $inv->property_id) ? get_the_title($inv->property_id) : '—'); ?></td>
                                            <td>ج.م <?php echo number_format_i18n(floatval($inv->amount), 2); ?></td>
                                            <td><?php echo esc_html(ucfirst($inv->status)); ?></td>
                                            <td><?php echo esc_html($inv->due_date ? $inv->due_date : '—'); ?></td>
                                            <td>
                                                <?php if ($inv->status === 'pending' && (!isset($inv->source) || $inv->source !== 'legacy')) : ?>
                                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($inv->id); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . $inv->id); ?>" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">ادفع الآن</button>
                                                <?php elseif ($inv->status === 'paid') : ?>
                                                    <span style="color:#16a34a;font-weight:bold;">✅ مدفوعة</span>
                                                <?php else : ?>
                                                    <span style="color:#6b7280;">غير متاح للمدفوعات القديمة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:16px;">لا توجد فواتير حتى الآن.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <div class="ms-card"><h3>طلبات الصيانة</h3>
                    <?php if ($tenant_building_id && !empty($tenant_maintenance_requests)) : ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">العنوان</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الحالة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الأولوية</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">التكلفة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">تاريخ الإنشاء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenant_maintenance_requests as $r) : ?>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <td style="padding:12px;"><?php echo esc_html($r->title ?? 'بدون عنوان'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($r->status ?? '—'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($r->priority ?? 'متوسط'); ?></td>
                                        <td style="padding:12px;">ج.م <?php echo isset($r->cost) ? number_format_i18n(floatval($r->cost),2) : '0.00'; ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($r->created_at ?? $r->created_on ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="padding:12px;">لم يتم العثور على طلبات صيانة لمبناك/وحدتك الحالية.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="wallet">
                <div class="ms-card"><h3>محفظتي</h3>
                    <div style="margin-top:8px;font-size:16px;font-weight:700">الرصيد: <?php echo 'ج.م ' . number_format_i18n($wallet,2); ?></div>
                    <?php if (!empty($tenant_wallet_transactions)) : ?>
                        <div class="ms-table-wrap" style="margin-top:12px;">
                            <table class="ms-table" style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th>الوصف</th>
                                        <th>المبلغ</th>
                                        <th>النوع</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenant_wallet_transactions as $t) : ?>
                                        <tr>
                                            <td><?php echo esc_html($t->description ?? $t->note ?? 'معاملة'); ?></td>
                                            <td>ج.م <?php echo isset($t->amount) ? number_format_i18n(floatval($t->amount),2) : '0.00'; ?></td>
                                            <td><?php echo esc_html($t->type ?? '—'); ?></td>
                                            <td><?php echo esc_html($t->created_at ?? $t->date ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="padding:12px;">لا توجد معاملات محفظة حديثة.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="documents">
                <?php echo function_exists('msfp_render_documents_center') ? msfp_render_documents_center($user->ID) : '<div class="ms-card"><h3>المستندات</h3><p>وحدة المستندات غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="meters">
                <?php echo function_exists('msfp_render_meter_readings') ? msfp_render_meter_readings($tenant_unit_id) : '<div class="ms-card"><h3>العدادات</h3><p>وحدة قراءات العدادات غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="notifications">
                <div class="ms-card">
                    <h3>الإشعارات</h3>
                    <?php if (!empty($notifications)) : ?>
                        <ul class="ms-notifications-list" style="list-style:none;padding:0;margin:0;">
                            <?php foreach ($notifications as $note) : ?>
                                <li style="padding:12px 0;border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:flex-start; gap:12px;" class="<?php echo $note->is_read ? 'ms-notification-read' : 'ms-notification-unread'; ?>">
                                    <div>
                                        <div style="font-size:14px;color:#111;"><?php echo esc_html($note->message); ?></div>
                                        <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at))); ?></div>
                                    </div>
                                    <?php if (empty($note->is_read)) : ?>
                                        <span style="color:#2563eb;font-size:18px;line-height:1;">●</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p>لا توجد إشعارات جديدة.</p>
                    <?php endif; ?>
                </div>
            </div>

        </main>

    </div>

    <?php
    return ob_get_clean();

});
