<?php
if (!defined('ABSPATH')) exit;

add_shortcode('manager_dashboard_v4', function () {
    ob_start();

    $user = wp_get_current_user();
    $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user->ID) : [];
    $building_ids = array_map(function ($building) {
        return intval($building->id ?? $building->ID ?? 0);
    }, $buildings);

    $selected_building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;
    $is_site_admin = current_user_can('manage_options');
    if ($selected_building_id && !in_array($selected_building_id, $building_ids, true)) {
        $selected_building_id = 0;
    }
    if (!$selected_building_id && !empty($building_ids)) {
        $selected_building_id = $building_ids[0];
    }

    $building_expenses = array();
    if ($selected_building_id) {
        if (function_exists('ms_get_legacy_expense_posts_by_building')) {
            $building_expenses = ms_get_legacy_expense_posts_by_building($selected_building_id);
        } else {
            $building_expenses = get_posts(array(
                'post_type' => 'expenses',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'building_id',
                        'value' => $selected_building_id,
                        'compare' => '=',
                    ),
                ),
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        }
    }

    $requested_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
    if ($requested_tab === 'utility-bills') {
        $requested_tab = 'invoices';
    } elseif ($requested_tab === 'collections') {
        $requested_tab = 'financial';
    }
    $active_tab = $requested_tab ? $requested_tab : (isset($_GET['invoice_status']) ? 'invoices' : 'overview');

    $invoices = function_exists('ms_get_manager_invoices') ? ms_get_manager_invoices($user->ID, 50, 'maintenance', $selected_building_id) : [];
    $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : [];
    $unread_notifications = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;
    $wallet = $selected_building_id && function_exists('ms_get_building_wallet') ? ms_get_building_wallet($selected_building_id) : null;
    $wallet_transactions = $selected_building_id && function_exists('ms_get_building_wallet_transactions') ? ms_get_building_wallet_transactions($selected_building_id, 20) : [];
    $units_count = function_exists('ms_get_units_count_by_manager') ? ms_get_units_count_by_manager($user->ID) : 0;
    $active_maintenance = function_exists('ms_get_active_maintenance_by_manager') ? ms_get_active_maintenance_by_manager($user->ID) : 0;
    $paid_invoices_count = function_exists('ms_get_paid_invoices_count_for_manager') ? ms_get_paid_invoices_count_for_manager($user->ID) : 0;
    $collection_stats = function_exists('ms_get_collection_stats_by_manager') ? ms_get_collection_stats_by_manager($user->ID) : array('percent' => 0, 'total_collected' => 0);

    // If a building is selected, compute building-specific invoice collection stats
    $building_collection = array('total_invoiced' => 0, 'total_collected' => 0, 'total_pending' => 0, 'percent' => 0);
    if ($selected_building_id && !empty($selected_building_id)) {
        global $wpdb;
        $inv_table = $wpdb->prefix . 'ms_invoices';
        $row = $wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(amount),0) AS total_invoiced, COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END),0) AS total_collected, COALESCE(SUM(CASE WHEN status != 'paid' AND status != 'canceled' THEN amount ELSE 0 END),0) AS total_pending FROM {$inv_table} WHERE building_id = %d", $selected_building_id));
        if ($row) {
            $building_collection['total_invoiced'] = floatval($row->total_invoiced);
            $building_collection['total_collected'] = floatval($row->total_collected);
            $building_collection['total_pending'] = floatval($row->total_pending);
            $building_collection['percent'] = $building_collection['total_invoiced'] > 0 ? round(($building_collection['total_collected'] / $building_collection['total_invoiced']) * 100, 2) : 0;
        }
    }

    ?>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '🏢'),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'محفظة', 'data_tab' => 'wallet', 'icon' => '💰'),
            array('label' => 'المرافق', 'data_tab' => 'facilities', 'icon' => '🏗️'),
            array('label' => 'المركز المالي', 'data_tab' => 'financial', 'icon' => '📊'),
            array('label' => 'المصروفات', 'data_tab' => 'expenses', 'icon' => '🧾'),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾'),
            array('label' => 'الإشعارات', 'data_tab' => 'notifications', 'icon' => '🔔', 'badge' => $unread_notifications),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">
            <div class="ms-card" style="margin-bottom:20px;">
                <h3>اختر المبنى</h3>
                <?php if (empty($buildings)) : ?>
                    <p>لا توجد أبنية مصرح لك بإدارتها.</p>
                <?php else : ?>
                    <?php if ($is_site_admin) : ?>
                        <p style="margin-bottom:12px;color:#475569;font-size:14px;">أنت مسؤول الموقع ويمكنك عرض وإدارة جميع الأبنية.</p>
                    <?php endif; ?>
                    <select id="ms-building-selector" class="ms-input">
                        <?php foreach ($buildings as $building) : ?>
                            <?php $building_id = intval($building->id ?? $building->ID ?? 0); ?>
                            <option value="<?php echo esc_attr($building_id); ?>" <?php selected($building_id, $selected_building_id); ?>><?php echo esc_html($building->title ?? $building->post_title ?? 'Building #' . $building_id); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "overview" ? " active" : ""); ?>" id="overview">
                <div class="ms-grid">
                    <div class="ms-card"><h3>عدد الأبنية</h3><div id="ms-buildings-count" class="ms-number"><?php echo intval(count($buildings)); ?></div></div>
                    <div class="ms-card"><h3>الشقق المسجلة</h3><div id="ms-units-count" class="ms-number"><?php echo intval($units_count); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">مدفوعة: <span id="ms-units-paid"><?php echo esc_html('--'); ?></span> — غير مدفوعة: <span id="ms-units-unpaid"><?php echo esc_html('--'); ?></span></div>
                    </div>
                    <div class="ms-card"><h3>الفواتير المدفوعة</h3><div id="ms-paid-invoices" class="ms-number"><?php echo intval($paid_invoices_count); ?></div></div>
                    <div class="ms-card"><h3>الصيانة النشطة</h3><div id="ms-active-maintenance" class="ms-number"><?php echo intval($active_maintenance); ?></div></div>
                </div>

                <div class="ms-grid" style="margin-top:20px">
                    <div class="ms-card"><h3>نسبة التحصيل</h3><div id="ms-collection-percent" class="ms-number"><?php echo esc_html($collection_stats['percent']); ?>%</div></div>
                    <div class="ms-card"><h3>المجموع المحصل</h3><div id="ms-collection-total" class="ms-number">ج.م <?php echo number_format_i18n(floatval($collection_stats['total_collected']), 2); ?></div></div>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "maintenance" ? " active" : ""); ?>" id="maintenance">
                <div class="ms-card">
                    <h3>إنشاء طلب صيانة</h3>
                    <form id="ms-maintenance-form">
                        <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                        <input type="hidden" name="security" value="<?php echo wp_create_nonce('mostaager-ajax-nonce'); ?>">

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">عنوان الطلب</label>
                            <input type="text" name="title" required class="ms-input">
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">نوع الصيانة</label>
                            <select name="maintenance_type" class="ms-input">
                                <option value="monthly">صيانة شهرية</option>
                                <option value="emergency">طوارئ</option>
                                <option value="capital">رأسمالية</option>
                            </select>
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">من يدفع</label>
                            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;border:1px solid #ddd;padding:10px 14px;border-radius:8px;">
                                    <input type="radio" name="payer_type" value="owner" checked>
                                    <span>مالك</span>
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;border:1px solid #ddd;padding:10px 14px;border-radius:8px;">
                                    <input type="radio" name="payer_type" value="tenant">
                                    <span>مستأجر</span>
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;border:1px solid #ddd;padding:10px 14px;border-radius:8px;">
                                    <input type="radio" name="payer_type" value="agent">
                                    <span>وسيط</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">التكلفة</label>
                            <input type="number" name="cost" step="0.01" min="0" required class="ms-input">
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">تاريخ البدء</label>
                            <input type="date" name="start_date" class="ms-input">
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">الموعد النهائي</label>
                            <input type="date" name="due_date" class="ms-input">
                        </div>

                        <div style="margin-bottom:15px">
                            <label style="display:flex;align-items:center;gap:8px">
                                <input type="checkbox" name="is_recurring" value="1" id="ms-is-recurring">
                                <span>صيانة دورية</span>
                            </label>
                        </div>

                        <div id="ms-recurrence-day-wrap" style="margin-bottom:15px;display:none">
                            <label style="display:block;margin-bottom:5px;font-weight:bold">يوم الشهر (1-28)</label>
                            <input type="number" name="recurrence_day" min="1" max="28" value="1" class="ms-input">
                        </div>

                        <button type="submit" class="ms-button ms-button-primary">إنشاء طلب الصيانة</button>
                    </form>

                    <div id="ms-maintenance-success" style="display:none;margin-top:15px;padding:10px;background:#10b981;color:#fff;border-radius:4px"></div>
                </div>

                <div class="ms-card" style="margin-top:20px">
                    <h3>طلبات الصيانة</h3>
                    <div id="ms-maintenance-table-wrap">
                        <table style="width:100%;border-collapse:collapse;margin-top:15px">
                            <thead>
                                <tr style="background:#f3f4f6;text-align:right">
                                    <th style="padding:12px">العنوان</th>
                                    <th style="padding:12px">النوع</th>
                                    <th style="padding:12px">التكلفة</th>
                                    <th style="padding:12px">الحالة</th>
                                    <th style="padding:12px">الفواتير المنشأة</th>
                                    <th style="padding:12px">تاريخ الإنشاء</th>
                                    <th style="padding:12px">إجراء</th>
                                </tr>
                            </thead>
                            <tbody id="ms-maintenance-tbody">
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <?php echo function_exists('msfp_render_maintenance_pro') ? msfp_render_maintenance_pro($selected_building_id) : ''; ?>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "wallet" ? " active" : ""); ?>" id="wallet">
                <div class="ms-card">
                    <h3>محفظة التحصيل</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى لعرض المحفظة.</p>
                    <?php elseif (!$wallet) : ?>
                        <p>لم يتم العثور على بيانات محفظة لهذا المبنى.</p>
                    <?php else : ?>
                        <div style="margin-bottom:16px;max-width:520px;">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                <div>المجموع المحصل: <strong>ج.م <?php echo number_format_i18n(floatval($wallet->balance), 2); ?></strong></div>
                                <div>الهدف: <strong>ج.م <?php echo number_format_i18n(floatval($wallet->target_amount), 2); ?></strong></div>
                            </div>
                            <?php $wallet_percent = floatval($wallet->target_amount) > 0 ? min(100, round((floatval($wallet->balance) / floatval($wallet->target_amount)) * 100, 2)) : 0; ?>
                            <div style="background:#f3f4f6;border-radius:999px;overflow:hidden;height:18px;margin:14px 0;">
                                <div style="width:<?php echo esc_attr($wallet_percent); ?>%;background:#10b981;height:100%;"></div>
                            </div>
                            <div style="font-size:13px;color:#475569;">نسبة التحصيل: <strong><?php echo esc_html($wallet->target_amount > 0 ? $wallet_percent . '%' : 'غير محدد'); ?></strong></div>
                        </div>
                        <?php if (!$is_site_admin) : ?>
                            <div style="margin-top:20px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc;">
                                <h4 style="margin-bottom:12px;">طلب تحويل إلى مسؤول الموقع</h4>
                                <form id="ms-transfer-request-form" enctype="multipart/form-data">
                                    <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                                    <input type="hidden" name="security" value="<?php echo wp_create_nonce('mostaager-ajax-nonce'); ?>">

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block;margin-bottom:6px;font-weight:bold;">المصروف المرتبط</label>
                                        <select name="expense_id" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                                            <option value="">اختر مصروفًا</option>
                                            <?php foreach ((array) $building_expenses as $expense) : ?>
                                                <option value="<?php echo esc_attr($expense->ID); ?>"><?php echo esc_html($expense->post_title . ' - ج.م ' . number_format_i18n(floatval(get_post_meta($expense->ID, 'total_amount', true)), 2)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block;margin-bottom:6px;font-weight:bold;">المبلغ المطلوب</label>
                                        <input type="number" name="amount" step="0.01" min="0" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block;margin-bottom:6px;font-weight:bold;">فاتورة/مستند الدفع</label>
                                        <input type="file" name="invoice_file" accept="image/*,.pdf" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block;margin-bottom:6px;font-weight:bold;">التوقيع الإلكتروني</label>
                                        <input type="text" name="electronic_signature" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                            <input type="checkbox" name="confirm_paid_invoices" value="1" required>
                                            <span>أؤكد أن جميع الفواتير الواردة قد تم دفعها.</span>
                                        </label>
                                    </div>

                                    <?php if (empty($building_expenses)) : ?>
                                        <p style="color:#b91c1c;margin-bottom:12px;">لا توجد مصاريف مرتبطة بهذا المبنى لإرسال طلب تحويل.</p>
                                    <?php endif; ?>

                                    <button type="submit" style="padding:10px 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer" <?php echo empty($building_expenses) ? 'disabled' : ''; ?>>إرسال الطلب</button>
                                    <div id="ms-transfer-request-message" style="margin-top:12px;font-size:14px;color:#10b981;display:none"></div>
                                </form>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($wallet_transactions)) : ?>
                            <h4 style="margin-top:20px;">آخر المعاملات</h4>
                            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                                <thead>
                                    <tr style="background:#f3f4f6;text-align:left;">
                                        <th style="padding:12px">التاريخ</th>
                                        <th style="padding:12px">المبلغ</th>
                                        <th style="padding:12px">الوصف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wallet_transactions as $transaction) : ?>
                                        <tr style="border-top:1px solid #e5e7eb;">
                                            <td style="padding:12px"><?php echo esc_html($transaction->created_at ?? ''); ?></td>
                                            <td style="padding:12px">ج.م <?php echo number_format_i18n(floatval($transaction->amount ?? 0), 2); ?></td>
                                            <td style="padding:12px"><?php echo esc_html($transaction->description ?? $transaction->type ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "facilities" ? " active" : ""); ?>" id="facilities">
                <div class="ms-card">
                    <h3>🏗️ المرافق</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى أولاً.</p>
                    <?php else : ?>
                        <!-- حالة المرافق الإجمالية -->
                        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                            <h4 style="margin-top:0;margin-bottom:12px;">حالة المرافق الإجمالية</h4>
                            <div id="ms-facilities-derived-status" style="font-size:14px;color:#475569;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
                                <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">الحالة العامة</div>
                                    <div style="font-size:18px;font-weight:bold;color:#0f172a;">جاري التحميل...</div>
                                </div>
                            </div>
                        </div>

                        <!-- جدول المرافق التفصيلي -->
                        <div style="margin-bottom:20px;">
                            <h4 style="margin-bottom:12px;">قائمة المرافق</h4>
                            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f3f4f6;border-bottom:1px solid #e5e7eb;">
                                            <th style="padding:12px;text-align:right;font-weight:600;color:#0f172a;">اسم المرفق</th>
                                            <th style="padding:12px;text-align:right;font-weight:600;color:#0f172a;">النوع</th>
                                            <th style="padding:12px;text-align:right;font-weight:600;color:#0f172a;">الحالة</th>
                                            <th style="padding:12px;text-align:right;font-weight:600;color:#0f172a;">آخر تحديث</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ms-facilities-table-body">
                                        <tr style="border-bottom:1px solid #e5e7eb;">
                                            <td colspan="4" style="padding:20px;text-align:center;color:#64748b;">جاري تحميل المرافق...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "financial" ? " active" : ""); ?>" id="financial">
                <div class="ms-card">
                    <h3>إحصائيات التحصيل</h3>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                        <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                            <div style="font-size:13px;color:#6b7280;">إجمالي الفواتير</div>
                            <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_invoiced']), 2); ?></div>
                        </div>
                        <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                            <div style="font-size:13px;color:#6b7280;">المحصّل</div>
                            <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_collected']), 2); ?></div>
                        </div>
                        <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                            <div style="font-size:13px;color:#6b7280;">المتبقي</div>
                            <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_pending']), 2); ?></div>
                        </div>
                        <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                            <div style="font-size:13px;color:#6b7280;">نسبة التحصيل</div>
                            <div style="font-size:20px;font-weight:700;"><?php echo esc_html($building_collection['percent']); ?>%</div>
                        </div>
                    </div>
                </div>
                <?php echo function_exists('msfp_render_financial_center') ? msfp_render_financial_center($selected_building_id) : '<div class="ms-card"><h3>المركز المالي</h3><p>الوحدة المالية غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "expenses" ? " active" : ""); ?>" id="expenses">
                <?php echo function_exists('msfp_render_expenses_center') ? msfp_render_expenses_center($selected_building_id) : '<div class="ms-card"><h3>المصروفات</h3><p>وحدة المصروفات غير محملة.</p></div>'; ?>
            </div>


            <div class="ms-tab-content<?php echo ($active_tab === "discussions" ? " active" : ""); ?>" id="discussions">
                <div class="ms-card">
                    <h3>مناقشات المبنى</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى لعرض المناقشات.</p>
                    <?php else : ?>
                        <!-- نموذج إنشاء موضوع جديد -->
                        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                            <h4 style="margin-top:0;margin-bottom:12px;">إنشاء موضوع جديد</h4>
                            <form class="ms-discussion-create-form" style="display:flex;flex-direction:column;gap:12px;">
                                <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                                <input type="text" name="title" placeholder="عنوان الموضوع" required 
                                       style="padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                <textarea name="content" rows="3" placeholder="محتوى الموضوع" required 
                                          style="padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;resize:vertical;"></textarea>
                                <button type="submit" style="padding:10px 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                                    إنشاء الموضوع
                                </button>
                            </form>
                        </div>
                        
                        <div id="building-discussions" class="ms-discussions-panel" data-building-id="<?php echo esc_attr($selected_building_id); ?>" style="display:flex;gap:20px;flex-wrap:wrap;">
                            <div style="flex:1 1 320px;min-width:320px;">
                                <h4>المواضيع</h4>
                                <ul class="ms-discussions-list-ul" style="list-style:none;margin:0;padding:0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;"></ul>
                            </div>
                            <div style="flex:2 1 420px;min-width:320px;display:flex;flex-direction:column;gap:12px;">
                                <div class="ms-discussion-messages" style="min-height:260px;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:auto;"></div>
                                <form class="ms-discussion-reply-form" style="display:none;flex-direction:column;gap:12px;">
                                    <textarea name="reply" rows="4" placeholder="اكتب ردك هنا..." style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px"></textarea>
                                    <button type="submit" style="padding:10px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer">إرسال الرد</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "invoices" ? " active" : ""); ?>" id="invoices">
                <div class="ms-card"><h3>آخر الفواتير</h3>
                    <?php $invoice_status_filter = isset($_GET['invoice_status']) ? sanitize_text_field($_GET['invoice_status']) : ''; ?>
                    <form method="get" style="margin-bottom:12px"> 
                        <input type="hidden" name="page" value="building-dashboard">
                        <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                        <input type="hidden" name="tab" value="invoices">
                        <label style="margin-right:8px">الحالة: </label>
                        <select name="invoice_status">
                            <?php $opts = array('' => 'الكل', 'pending' => 'معلقة', 'paid' => 'مدفوع', 'canceled' => 'ملغي');
                            foreach ($opts as $k => $v) {
                                $sel = ($k === $invoice_status_filter) ? ' selected' : '';
                                echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
                            }
                            ?>
                        </select>
                        <button class="button">تصفية</button>
                    </form>
                    <?php if ($selected_building_id): ?>
                        <!-- قسم فواتير الخدمات التشغيلية -->
                        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                                <h4 style="margin:0;">فواتير الخدمات التشغيلية</h4>
                                <button type="button" id="ms-load-utility-bills" style="padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">
                                    + تحميل الفواتير
                                </button>
                            </div>
                            <div id="ms-utility-bills-wrap" style="margin-top:12px;">
                                <div style="padding:20px;text-align:center;color:#64748b;">اضغط على الزر لتحميل فواتير الخدمات</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoices)): ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:15px">
                            <thead>
                                <tr style="background:#f3f4f6;text-align:left">
                                    <th style="padding:12px">#</th>
                                    <th style="padding:12px">نوع الدافع</th>
                                    <th style="padding:12px">اسم الشخص</th>
                                    <th style="padding:12px">المبلغ</th>
                                    <th style="padding:12px">الحالة</th>
                                    <th style="padding:12px">تاريخ الاستحقاق</th>
                                    <th style="padding:12px">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // apply optional status filter
                                $filtered_invoices = $invoices;
                                if (!empty($invoice_status_filter)) {
                                    $filtered_invoices = array_values(array_filter($invoices, function($inv) use ($invoice_status_filter){ return strtolower(trim($inv->status ?? '')) === $invoice_status_filter; }));
                                }
                                foreach ($filtered_invoices as $invoice): 
                                    $payer_id = intval($invoice->user_id ?? $invoice->payer_id ?? 0);
                                    $payer = $payer_id ? get_userdata($payer_id) : null;
                                    $payer_name = $payer ? $payer->display_name : esc_html($invoice->payer_name ?? '--');
                                    $payer_email = $payer ? $payer->user_email : esc_html($invoice->payer_email ?? '');
                                    $payer_phone = $payer ? get_user_meta($payer_id, 'billing_phone', true) : esc_html($invoice->payer_phone ?? '');
                                    $payer_type_label = 'غير معروف';
                                    $payer_type_map = array(
                                        'owner' => 'مالك',
                                        'tenant' => 'مستأجر',
                                        'agent' => 'وسيط',
                                        'owner_or_tenant' => 'مالك/مستأجر',
                                    );
                                    $payer_type_label = isset($payer_type_map[strtolower(trim($invoice->payer_type ?? ''))]) ? $payer_type_map[strtolower(trim($invoice->payer_type ?? ''))] : $payer_type_label;
                                    $collection_progress = function_exists('ms_get_maintenance_collection_progress') && !empty($invoice->expense_id) ? ms_get_maintenance_collection_progress(intval($invoice->expense_id)) : null;
                                ?>
                                    <tr style="border-top:1px solid #e5e7eb">
                                        <td style="padding:12px">#<?php echo esc_html($invoice->id); ?></td>
                                        <td style="padding:12px"><?php echo esc_html($payer_type_label); ?></td>
                                        <td style="padding:12px">
                                            <div style="font-weight:600"><?php echo esc_html($payer_name); ?></div>
                                            <?php if (!empty($payer_email)): ?><div style="font-size:13px;color:#6b7280;margin-top:4px;"><?php echo esc_html($payer_email); ?></div><?php endif; ?>
                                            <?php if (!empty($payer_phone)): ?><div style="font-size:13px;color:#6b7280;margin-top:2px;"><?php echo esc_html($payer_phone); ?></div><?php endif; ?>
                                        </td>
                                        <td style="padding:12px">ج.م <?php echo number_format_i18n(floatval($invoice->amount), 2); ?></td>
                                        <?php $st = strtolower(trim($invoice->status ?? '')); ?>
                                        <td class="ms-invoice-status" style="padding:12px;text-transform:capitalize">
                                            <?php if ($st === 'paid'): ?>
                                                <span style="color:#10b981;font-weight:600">مدفوع ✓</span>
                                            <?php elseif ($st === 'canceled'): ?>
                                                <span style="color:#ef4444;font-weight:600">ملغي</span>
                                            <?php else: ?>
                                                <span style="background:#f59e0b;color:#fff;padding:4px 8px;border-radius:6px;font-weight:600">معلقة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;min-width:220px;">
                                            <div><?php echo esc_html($invoice->due_date); ?></div>
                                            <?php if (!empty($collection_progress) && $collection_progress['total'] > 0): ?>
                                                <div style="margin-top:8px;font-size:12px;color:#475569;">نسبة التحصيل: <?php echo esc_html($collection_progress['percent']); ?>%</div>
                                                <div style="background:#f3f4f6;border-radius:999px;overflow:hidden;height:10px;margin-top:6px;">
                                                    <div style="width:<?php echo esc_attr($collection_progress['percent']); ?>%;background:#2563eb;height:100%;"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px">
                                            <?php $st_status = strtolower(trim($invoice->status ?? '')); $is_pending_action = ($st_status === 'pending'); ?>
                                            <?php if ($st_status === 'paid'): ?>
                                                <span style="color:#10b981;font-weight:600">مدفوع</span>
                                            <?php elseif ($st_status === 'canceled'): ?>
                                                <span style="color:#ef4444;font-weight:600">ملغي</span>
                                            <?php else: ?>
                                                <?php if ($is_pending_action): ?>
                                                    <button type="button" class="ms-mark-paid-btn" data-invoice-id="<?php echo esc_attr($invoice->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('mostaager-ajax-nonce')); ?>" style="padding:6px 10px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer">وضع كمدفوع</button>
                                                    <button type="button" class="ms-cancel-invoice-btn" data-invoice-id="<?php echo esc_attr($invoice->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('mostaager-ajax-nonce')); ?>" style="padding:6px 10px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-left:8px">إلغاء</button>
                                                <?php else: ?>
                                                    <!-- No actions for non-pending statuses -->
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>لا توجد فواتير مرتبطة بالأبنية حتى الآن.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content<?php echo ($active_tab === "notifications" ? " active" : ""); ?>" id="notifications">
                <div class="ms-card">
                    <h3>الإشعارات</h3>
                    <?php if (empty($notifications)) : ?>
                        <p>لا توجد إشعارات جديدة.</p>
                    <?php else : ?>
                        <ul style="list-style:none;margin:0;padding:0;">
                            <?php foreach ($notifications as $notification) : ?>
                                <li style="padding:12px;border-bottom:1px solid #f3f4f6;">
                                    <div style="font-size:14px;color:#111;">
                                        <?php echo esc_html($notification->message ?? ''); ?>
                                    </div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:6px;">
                                        <?php echo esc_html($notification->created_at ?? ''); ?>
                                        <?php if (isset($notification->is_read) && intval($notification->is_read) === 0) : ?>
                                            <span style="background:#2563eb;color:#fff;padding:2px 8px;border-radius:999px;margin-left:8px;font-size:11px;">جديد</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </main>

    </div>

    <script>
        (function(){
            var selector = document.getElementById('ms-building-selector');
            if (selector) {
                selector.addEventListener('change', function() {
                    var value = this.value;
                    if (!value) return;
                    var params = new URLSearchParams(window.location.search);
                    params.set('building_id', value);
                    window.location.search = params.toString();
                });
            }

            // Facilities derived status + utility bills loader
            var loadBtn = document.getElementById('ms-load-utility-bills');
            var buildingId = document.querySelector('#facilities[data-building-id]') ? document.querySelector('#facilities').getAttribute('data-building-id') : null;
            var derivedWrap = document.getElementById('ms-facilities-derived-status');

            var inferredBuildingId = <?php echo intval($selected_building_id); ?>;
            buildingId = buildingId ? buildingId : inferredBuildingId;

            function apiGet(path) {
                return fetch(path, { credentials: 'same-origin' }).then(r => r.json());
            }

            if (buildingId && derivedWrap) {
                apiGet('/wp-json/mostager/v1/facilities/status?building_id=' + encodeURIComponent(buildingId))
                    .then(function(json){
                        if (!json || !json.data) {
                            derivedWrap.textContent = 'لم يتم العثور على مرافق للمبنى.';
                            return;
                        }
                        var data = json.data;
                        if (!data.length) {
                            derivedWrap.textContent = 'لا توجد مرافق.';
                            return;
                        }
                        var lines = data.map(function(item){
                            var dot = item.color ? item.color : '#10b981';
                            var label = item.facility_name || item.facility_type_name || ('Facility #' + item.facility_id);
                            return '<div style="display:flex;align-items:center;gap:10px;margin:6px 0">' +
                                '<span style="width:10px;height:10px;border-radius:999px;background:' + dot + '"></span>' +
                                '<span>' + (label || '') + '</span>' +
                                '<span style="color:#64748b;font-size:12px">(' + (item.derived_status || '') + ')</span>' +
                            '</div>';
                        });
                        derivedWrap.innerHTML = lines.join('');
                    })
                    .catch(function(){
                        derivedWrap.textContent = 'فشل تحميل بيانات المرافق.';
                    });
            }

            if (loadBtn) {
                loadBtn.addEventListener('click', function(){
                    var out = document.getElementById('ms-utility-bills-wrap');
                    if (!out) return;
                    out.textContent = 'جاري التحميل...';
                    apiGet('/wp-json/mostager/v1/utility-bills?building_id=' + encodeURIComponent(buildingId))
                        .then(function(json){
                            if (!json || !json.data) {
                                out.textContent = 'لا توجد فواتير للمبنى أو فشل تحميل البيانات.';
                                return;
                            }
                            var bills = json.data;
                            if (!bills.length) {
                                out.textContent = 'لا توجد فواتير للمبنى.';
                                return;
                            }
                            var html = '<div style="display:flex;flex-direction:column;gap:10px">';
                            html += bills.map(function(b){
                                return '<div style="padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff">' +
                                    '<div style="font-weight:700">' + (b.title || 'Utility Bill') + '</div>' +
                                    '<div style="color:#475569;font-size:13px;margin-top:6px">' +
                                    'الحالة: ' + (b.status || '') + ' | ' +
                                    'المبلغ: ' + (b.total_amount || 0) + '</div>' +
                                    '</div>';
                            }).join('');
                            html += '</div>';
                            out.innerHTML = html;
                        })
                        .catch(function(){
                            out.textContent = 'فشل تحميل فواتير الخدمات.';
                        });
                });
            }
        })();
    </script>

    <?php
    return ob_get_clean();

});

