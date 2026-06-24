<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostaager shortcode definitions.
 */

add_shortcode('owner_dashboard', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض لوحة المالك.</p>';
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'owner')) {
        return '<p>هذه اللوحة مخصصة للمالكين فقط.</p>';
    }

    $properties = function_exists('ms_get_owner_properties_with_invoices') ? ms_get_owner_properties_with_invoices($user->ID) : [];
    $wallet_balance = function_exists('ms_get_user_wallet_balance') ? ms_get_user_wallet_balance($user->ID) : 0;

    ob_start();
    ?>
    <div class="ms-owner-dashboard-shortcode">
        <h2>لوحة تحكم المالك</h2>
        <p>مرحباً <?php echo esc_html($user->display_name); ?></p>
        <div class="ms-owner-summary">
            <div>عدد العقارات: <?php echo intval(count($properties)); ?></div>
            <div>رصيد المحفظة: <?php echo 'ج.م ' . number_format_i18n($wallet_balance,2); ?></div>
        </div>
        <div class="ms-owner-properties">
            <?php if (empty($properties)): ?>
                <p>لا توجد عقارات مسجلة.</p>
            <?php else: ?>
                <?php foreach ($properties as $property): ?>
                    <div class="ms-owner-property-card">
                        <h3>العقار #<?php echo intval($property->id); ?></h3>
                        <p>مالك الوحدة: <?php echo intval($property->owner_id); ?></p>
                        <p>حالة: <?php echo esc_html($property->status); ?></p>
                        <h4>الفواتير</h4>
                        <?php if (!empty($property->invoices)): ?>
                            <ul>
                                <?php foreach ($property->invoices as $invoice): ?>
                                    <li>#<?php echo intval($invoice->id); ?> — <?php echo esc_html($invoice->status); ?> — <?php echo 'ج.م ' . number_format_i18n($invoice->amount,2); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>لا توجد فواتير لهذا العقار.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// --- Additional shortcodes required by SaaS plan (B3) ----------------------

add_shortcode('mostaager_financial_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_financial_center');
    $building_id = intval($atts['building_id']);
    if (!$building_id) return '<p>يرجى تحديد معرف المبنى.</p>';
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>غير مصرح لك بعرض هذا المحتوى.</p>';
    }
    if (function_exists('msfp_render_financial_center')) {
        return msfp_render_financial_center($building_id);
    }
    return '<p>وحدة المركز المالي غير متاحة حالياً.</p>';
});

add_shortcode('mostaager_expenses_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_expenses_center');
    $building_id = intval($atts['building_id']);
    if (!$building_id) return '<p>يرجى تحديد معرف المبنى.</p>';
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>غير مصرح لك بعرض هذا المحتوى.</p>';
    }
    if (function_exists('msfp_render_expenses_center')) {
        return msfp_render_expenses_center($building_id);
    }
    return '<p>وحدة المصروفات غير متاحة حالياً.</p>';
});

add_shortcode('mostaager_maintenance_pro', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_maintenance_pro');
    $building_id = intval($atts['building_id']);
    if (!$building_id) return '<p>يرجى تحديد معرف المبنى.</p>';
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>غير مصرح لك بعرض هذا المحتوى.</p>';
    }
    if (function_exists('msfp_render_maintenance_pro')) {
        return msfp_render_maintenance_pro($building_id);
    }
    return '<p>وحدة صيانة Pro غير متاحة حالياً.</p>';
});

add_shortcode('mostaager_owner_reports', function ($atts) {
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    $user = wp_get_current_user();
    if (!ms_user_has_role($user->ID, 'owner')) return '<p>هذا المحتوى مخصص للمالكين فقط.</p>';
    if (function_exists('msfp_render_owner_reports')) {
        return msfp_render_owner_reports($user->ID);
    }
    return '<p>تقارير المالك غير متاحة حالياً.</p>';
});

add_shortcode('mostaager_tenant_documents', function ($atts) {
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    $user = wp_get_current_user();
    if (!ms_user_has_role($user->ID, 'tenant')) return '<p>هذا المحتوى مخصص للمستأجرين فقط.</p>';
    if (function_exists('msfp_render_tenant_documents')) {
        return msfp_render_tenant_documents($user->ID);
    }
    return '<p>وحدة مستندات المستأجر غير متاحة حالياً.</p>';
});

add_shortcode('mostaager_meter_readings', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_meter_readings');
    $building_id = intval($atts['building_id']);
    if (!$building_id) return '<p>يرجى تحديد معرف المبنى.</p>';
    if (!is_user_logged_in()) return '<p>يرجى تسجيل الدخول لعرض المحتوى.</p>';
    if (function_exists('msfp_render_meter_readings')) {
        return msfp_render_meter_readings($building_id);
    }
    return '<p>وحدة قراءات العدادات غير متاحة حالياً.</p>';
});

add_action('wp', function () {
    if (!is_admin()) {
        global $post;
        if ($post instanceof WP_Post) {
            $content = $post->post_content;
            if (has_shortcode($content, 'agent_dashboard_v4') || has_shortcode($content, 'manager_full_dashboard')) {
                $GLOBALS['mostaager_agent_add_property_dashboard'] = true;
            }
        }
    }
});

/**
 * Backwards-compatible shortcode: map legacy `manager_full_dashboard` to `manager_dashboard_v4`.
 * This ensures pages still using the old shortcode will render the new building dashboard.
 */
add_shortcode('manager_full_dashboard', function () {
        return do_shortcode('[manager_dashboard_v4]');
    });

add_shortcode('tenant_dashboard', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض لوحة المستأجر.</p>';
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'tenant')) {
        return '<p>هذه اللوحة مخصصة للمستأجرين فقط.</p>';
    }

    $invoices = function_exists('ms_get_tenant_invoices') ? ms_get_tenant_invoices($user->ID) : [];
    $wallet_balance = function_exists('ms_get_user_wallet_balance') ? ms_get_user_wallet_balance($user->ID) : 0;

    ob_start();
    ?>
    <div class="ms-tenant-dashboard-shortcode">
        <h2>لوحة تحكم المستأجر</h2>
        <p>مرحباً <?php echo esc_html($user->display_name); ?></p>
        <div class="ms-tenant-summary">
            <div>رصيد المحفظة: <?php echo 'ج.م ' . number_format_i18n($wallet_balance,2); ?></div>
            <div>عدد الفواتير: <?php echo intval(count($invoices)); ?></div>
        </div>
        <div class="ms-tenant-invoices">
            <?php if (empty($invoices)): ?>
                <p>لا توجد فواتير حالياً.</p>
            <?php else: ?>
                <table class="ms-invoice-table">
                    <thead>
                        <tr><th>رقم الفاتورة</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الاستحقاق</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?php echo intval($invoice->id); ?></td>
                                <td><?php echo 'ج.م ' . number_format_i18n($invoice->amount,2); ?></td>
                                <td><?php echo esc_html($invoice->status); ?></td>
                                <td><?php echo esc_html($invoice->due_date); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('user_wallet', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض المحفظة.</p>';
    }

    $user = wp_get_current_user();
    $wallet_balance = function_exists('ms_get_user_wallet_balance') ? ms_get_user_wallet_balance($user->ID) : 0;

    ob_start();
    ?>
    <div class="ms-user-wallet-shortcode">
        <h2>محفظتي</h2>
        <p>رصيدك الحالي: <?php echo 'ج.م ' . number_format_i18n($wallet_balance,2); ?></p>
        <p>يمكنك إضافة رصيد ودفع الفواتير الخاصة بك من هنا.</p>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('agent_subscription_status', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض حالة الاشتراك.</p>';
    }

    $user = wp_get_current_user();
    $status = function_exists('ms_get_agent_subscription_status') ? ms_get_agent_subscription_status($user->ID) : [];

    ob_start();
    ?>
    <div class="ms-agent-subscription-status">
        <h2>حالة اشتراك الوسيط</h2>
        <p>الرسوم الشهرية لكل عقار: <?php echo 'ج.م ' . number_format_i18n($status['monthly_fee'],2); ?></p>
        <p>الحالة: <?php echo esc_html($status['status']); ?></p>
        <p>المستحق الآن: <?php echo 'ج.م ' . number_format_i18n($status['due'],2); ?></p>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('rent_streak_badge', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض شارة التزام الإيجار.</p>';
    }

    $user = wp_get_current_user();
    $badge = function_exists('ms_get_rent_streak_badge') ? ms_get_rent_streak_badge($user->ID) : array('streak' => 0, 'label' => 'غير متوفر', 'color' => '#64748b');

    ob_start();
    ?>
    <div class="ms-rent-streak-badge" style="border-left:4px solid <?php echo esc_attr($badge['color']); ?>;padding:18px 16px;background:#fff;border-radius:18px;box-shadow:0 10px 25px rgba(0,0,0,.05);margin-top:18px;">
        <h2>مستوى التزام الإيجار</h2>
        <div style="font-size:32px;font-weight:700;color:<?php echo esc_attr($badge['color']); ?>;margin:10px 0;"><?php echo intval($badge['streak']); ?> شهر</div>
        <p style="margin:0;color:#334155"><?php echo esc_html($badge['label']); ?></p>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('building_wallet', function ($atts) {
    $atts = shortcode_atts(array(
        'building_id' => 0,
    ), $atts, 'building_wallet');

    $building_id = intval($atts['building_id']);
    if (!$building_id) {
        return '<p>يرجى تحديد معرف المبنى في الوسم.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض محفظة المبنى.</p>';
    }

    $wallet = function_exists('ms_get_building_wallet') ? ms_get_building_wallet($building_id) : null;
    if (!$wallet) {
        return '<p>لا تتوفر بيانات محفظة لهذا المبنى.</p>';
    }

    $collected = floatval($wallet->balance);
    $target = floatval($wallet->target_amount);
    $percent = $target > 0 ? min(100, round(($collected / $target) * 100, 2)) : 0;

    ob_start();
    ?>
    <div class="ms-building-wallet-shortcode" style="background:#fff;border:1px solid #e5e7eb;padding:18px;border-radius:12px;">
        <h2>محفظة التحصيل للمبنى</h2>
        <div style="display:flex;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:12px;">
            <div>المجموع المحصل: <strong>ج.م <?php echo number_format_i18n($collected, 2); ?></strong></div>
            <div>الهدف: <strong>ج.م <?php echo number_format_i18n($target, 2); ?></strong></div>
        </div>
        <div style="background:#f3f4f6;border-radius:999px;overflow:hidden;height:18px;margin-bottom:8px;">
            <div style="width:<?php echo esc_attr($percent); ?>%;background:#10b981;height:100%;"></div>
        </div>
        <div style="font-size:13px;color:#475569;">نسبة التحصيل: <strong><?php echo esc_html($target > 0 ? $percent . '%' : 'غير محدد'); ?></strong></div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('building_invoices_table', function ($atts) {
    $atts = shortcode_atts(array(
        'building_id' => 0,
    ), $atts, 'building_invoices_table');

    $building_id = intval($atts['building_id']);
    if (!$building_id) {
        return '<p>يرجى تحديد معرف المبنى في الوسم.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض جدول الفواتير.</p>';
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>هذه الصفحة مخصصة لمديري المباني فقط.</p>';
    }

    if (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id)) {
        return '<p>ليس لديك صلاحية على هذا المبنى.</p>';
    }

    $invoices = function_exists('ms_get_manager_invoices') ? ms_get_manager_invoices($user->ID, 50, 'maintenance', $building_id) : array();
    $filtered = array_filter($invoices, function ($invoice) use ($building_id) {
        return intval($invoice->building_id ?? $invoice->building_id) === $building_id;
    });

    ob_start();
    ?>
    <div class="ms-building-invoices-shortcode">
        <h2>فواتير المبنى</h2>
        <?php if (empty($filtered)) : ?>
            <p>لا توجد فواتير لهذا المبنى.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead>
                    <tr style="background:#f3f4f6;text-align:left;">
                        <th style="padding:12px">#</th>
                        <th style="padding:12px">المستخدم</th>
                        <th style="padding:12px">المبلغ</th>
                        <th style="padding:12px">الحالة</th>
                        <th style="padding:12px">تاريخ الاستحقاق</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $invoice) : ?>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:12px">#<?php echo intval($invoice->id); ?></td>
                            <td style="padding:12px"><?php echo esc_html($invoice->user_id); ?></td>
                            <td style="padding:12px">ج.م <?php echo number_format_i18n($invoice->amount, 2); ?></td>
                            <td style="padding:12px;text-transform:capitalize"><?php echo esc_html($invoice->status); ?></td>
                            <td style="padding:12px"><?php echo esc_html($invoice->due_date); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('building_discussions', function ($atts) {
    $atts = shortcode_atts(array(
        'building_id' => 0,
    ), $atts, 'building_discussions');

    $building_id = intval($atts['building_id']);
    if (!$building_id) {
        return '<p>يرجى تحديد معرف المبنى في الوسم.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض مناقشات المبنى.</p>';
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>هذه الصفحة مخصصة لمديري المباني فقط.</p>';
    }

    if (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id)) {
        return '<p>ليس لديك صلاحية على هذا المبنى.</p>';
    }

    ob_start();
    ?>
    <div id="building-discussions" class="ms-discussions-panel" data-building-id="<?php echo esc_attr($building_id); ?>" style="display:flex;gap:20px;flex-wrap:wrap;">
        <div style="flex:1 1 320px;min-width:320px;">
            <h3>مواضيع المناقشة</h3>
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
    <?php
    return ob_get_clean();
});

add_shortcode('manager_add_expense', function ($atts) {
    $atts = shortcode_atts(array(
        'building_id' => 0,
    ), $atts, 'manager_add_expense');

    $building_id = intval($atts['building_id']);
    if (!$building_id) {
        return '<p>يرجى تحديد معرف المبنى في الوسم.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض نموذج طلب الصيانة.</p>';
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>هذه الصفحة مخصصة لمديري المباني فقط.</p>';
    }

    if (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id)) {
        return '<p>ليس لديك صلاحية على هذا المبنى.</p>';
    }

    ob_start();
    ?>
    <div class="ms-manager-add-expense" style="background:#fff;border:1px solid #e5e7eb;padding:18px;border-radius:12px;">
        <h3>إنشاء طلب صيانة</h3>
        <form id="ms-manager-maintenance-form">
            <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('mostaager-ajax-nonce')); ?>">
            <div style="margin-bottom:15px"><label>عنوان الطلب</label><input type="text" name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
            <div style="margin-bottom:15px"><label>نوع الصيانة</label><select name="maintenance_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"><option value="monthly">صيانة شهرية</option><option value="emergency">طوارئ</option><option value="capital">رأسمالية</option></select></div>
            <div style="margin-bottom:15px"><label>التكلفة</label><input type="number" name="cost" step="0.01" min="0" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
            <div style="margin-bottom:15px"><label>تاريخ البدء</label><input type="date" name="start_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
            <div style="margin-bottom:15px"><label>الموعد النهائي</label><input type="date" name="due_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
            <div style="margin-bottom:15px"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_recurring" value="1"> <span>صيانة دورية</span></label></div>
            <div id="ms-manager-recurrence-day-wrap" style="margin-bottom:15px;display:none"><label>يوم الشهر (1-28)</label><input type="number" name="recurrence_day" min="1" max="28" value="1" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px"></div>
            <button type="submit" style="padding:10px 20px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer">إنشاء طلب الصيانة</button>
        </form>
        <div id="ms-manager-maintenance-success" style="display:none;margin-top:15px;padding:10px;background:#10b981;color:#fff;border-radius:4px"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            var form = document.getElementById('ms-manager-maintenance-form');
            if (!form || typeof MostaagerAjax === 'undefined') return;
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var params = new URLSearchParams(new FormData(form));
                params.append('action', 'ms_create_maintenance_request');
                params.append('security', MostaagerAjax.nonce);
                fetch(MostaagerAjax.ajax_url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body:params })
                .then(r=>r.json()).then(function(json){
                    if(json.success){
                        var success = document.getElementById('ms-manager-maintenance-success');
                        if(success){ success.textContent = json.data.message || 'تم إنشاء الطلب بنجاح'; success.style.display = 'block'; setTimeout(function(){ success.style.display='none'; }, 3000); }
                        form.reset();
                    } else {
                        alert(json.data || 'فشل إنشاء الطلب.');
                    }
                }).catch(function(err){ console.error('Manager maintenance error', err); alert('حدث خطأ في الاتصال'); });
            });
            var recurring = form.querySelector('input[name="is_recurring"]');
            var recurrence = document.getElementById('ms-manager-recurrence-day-wrap');
            if(recurring && recurrence){
                recurring.addEventListener('change', function(){ recurrence.style.display = this.checked ? 'block' : 'none'; });
            }
        });
    </script>
    <?php
    return ob_get_clean();
});

add_shortcode('user_building_facility_invoices', function () {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض فواتير المرافق.</p>';
    }

    $user = wp_get_current_user();
    $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($user->ID) : array();
    $filtered = array_filter($invoices, function ($invoice) {
        if (empty($invoice->invoice_type)) {
            return true;
        }

        return in_array($invoice->invoice_type, array('maintenance', 'legacy'), true);
    });

    ob_start();
    ?>
    <div class="ms-user-building-facility-invoices">
        <h2>فواتير مرافق المستخدم</h2>
        <?php if (empty($filtered)): ?>
            <p>لا توجد فواتير مرافق.</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead>
                    <tr style="background:#f3f4f6;text-align:left;">
                        <th style="padding:12px">#</th>
                        <th style="padding:12px">المبلغ</th>
                        <th style="padding:12px">الحالة</th>
                        <th style="padding:12px">النوع</th>
                        <th style="padding:12px">تاريخ الاستحقاق</th>
                        <th style="padding:12px">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $invoice) : ?>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:12px">#<?php echo intval($invoice->id); ?></td>
                            <td style="padding:12px">ج.م <?php echo number_format_i18n($invoice->amount, 2); ?></td>
                            <td style="padding:12px;text-transform:capitalize"><?php echo esc_html($invoice->status); ?></td>
                            <td style="padding:12px"><?php echo esc_html($invoice->invoice_type); ?></td>
                            <td style="padding:12px"><?php echo esc_html($invoice->due_date); ?></td>
                            <td style="padding:12px">
                                <?php if ($invoice->status !== 'paid') : ?>
                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($invoice->id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('ms_pay_invoice_' . intval($invoice->id))); ?>" style="padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer">ادفع الآن</button>
                                <?php else : ?>
                                    <span>مدفوع</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('ms_dashboard', function ($atts) {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لعرض لوحة Mostaager.</p>';
    }

    $atts = shortcode_atts(array(
        'title' => 'لوحة Mostaager',
    ), $atts, 'ms_dashboard');

    $dashboard_path = MOSTAAGER_ENTERPRISE_PATH . 'templates/dashboard.php';
    if (!file_exists($dashboard_path)) {
        return '<p>تعذر العثور على قالب لوحة Mostaager.</p>';
    }

    ob_start();
    include $dashboard_path;
    return ob_get_clean();
});
