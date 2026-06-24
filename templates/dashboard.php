<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
if (!is_user_logged_in()) {
    echo '<div class="ms-dashboard-card"><p>يرجى تسجيل الدخول لعرض لوحة المعلومات.</p></div>';
    return;
}

$invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($current_user->ID) : array();
$wallet_balance = function_exists('ms_get_user_wallet_balance') ? ms_get_user_wallet_balance($current_user->ID) : 0;
$total_due = 0.0;
$total_paid = 0.0;
$pending_count = 0;
$recent = array_slice((array) $invoices, 0, 6);

foreach ($invoices as $invoice) {
    $amount = floatval($invoice->amount ?? $invoice->amount_due ?? 0);
    if (strtolower($invoice->status ?? '') === 'paid') {
        $total_paid += $amount;
    } else {
        $total_due += $amount;
        $pending_count++;
    }
}

$managed_buildings = array();
$open_requests = 0;
if (current_user_can('manage_options') || (function_exists('ms_user_has_role') && ms_user_has_role($current_user->ID, 'building_manager'))) {
    if (function_exists('ms_get_buildings_by_manager')) {
        $managed_buildings = ms_get_buildings_by_manager($current_user->ID);
    }
    if (function_exists('ms_get_active_maintenance_by_manager')) {
        $open_requests = ms_get_active_maintenance_by_manager($current_user->ID);
    }
}
?>
<div class="ms-dashboard-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;max-width:1000px;margin:auto;">
    <div class="ms-dashboard-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="margin:0 0 8px;"><?php echo esc_html($atts['title'] ?? 'لوحة Mostaager'); ?></h2>
            <p style="margin:0;color:#475569;">مرحباً <?php echo esc_html($current_user->display_name); ?>، هذه الإحصائيات تعرض أحدث حالة الفواتير والمحفظة.</p>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;min-width:180px;">
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">الرصيد في المحفظة</div>
                <div style="font-size:22px;font-weight:700;">ج.م <?php echo number_format_i18n($wallet_balance, 2); ?></div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;min-width:180px;">
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">إجمالي المستحق</div>
                <div style="font-size:22px;font-weight:700;">ج.م <?php echo number_format_i18n($total_due, 2); ?></div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;min-width:180px;">
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">عدد الفواتير المعلقة</div>
                <div style="font-size:22px;font-weight:700;"><?php echo intval($pending_count); ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($managed_buildings)): ?>
        <div class="ms-dashboard-managed-buildings" style="margin-top:26px;">
            <h3 style="margin-bottom:14px;">المباني التي تديرها</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                <?php foreach ($managed_buildings as $building): ?>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;">
                        <strong><?php echo esc_html($building->title ?? $building->name ?? ''); ?></strong>
                        <div style="font-size:13px;color:#64748b;margin-top:8px;">معرف المبنى: <?php echo intval($building->id ?? 0); ?></div>
                        <div style="font-size:13px;color:#334155;margin-top:8px;">طلبات صيانة مفتوحة: <?php echo intval($open_requests); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="ms-dashboard-recent-invoices" style="margin-top:26px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <h3 style="margin:0;">أحدث الفواتير</h3>
            <a href="<?php echo esc_url(site_url('/')); ?>" style="font-size:14px;color:#2563eb;text-decoration:none;">عرض المزيد</a>
        </div>
        <?php if (empty($recent)): ?>
            <p style="margin-top:16px;color:#475569;">لا توجد فواتير لعرضها.</p>
        <?php else: ?>
            <div style="margin-top:16px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:680px;">
                    <thead>
                        <tr style="background:#f8fafc;color:#0f172a;text-align:left;">
                            <th style="padding:12px;border-bottom:1px solid #e2e8f0;">#</th>
                            <th style="padding:12px;border-bottom:1px solid #e2e8f0;">المبلغ</th>
                            <th style="padding:12px;border-bottom:1px solid #e2e8f0;">الحالة</th>
                            <th style="padding:12px;border-bottom:1px solid #e2e8f0;">النوع</th>
                            <th style="padding:12px;border-bottom:1px solid #e2e8f0;">تاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $invoice): ?>
                            <tr style="border-top:1px solid #e2e8f0;">
                                <td style="padding:12px;">#<?php echo intval($invoice->id ?? 0); ?></td>
                                <td style="padding:12px;">ج.م <?php echo number_format_i18n(floatval($invoice->amount ?? $invoice->amount_due ?? 0), 2); ?></td>
                                <td style="padding:12px;"><?php echo esc_html($invoice->status ?? ''); ?></td>
                                <td style="padding:12px;"><?php echo esc_html($invoice->invoice_type ?? ''); ?></td>
                                <td style="padding:12px;"><?php echo esc_html($invoice->created_at ?? $invoice->due_date ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
