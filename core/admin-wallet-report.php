<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin: Wallet report for buildings (plan A5)
 */
function ms_admin_wallet_report_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;

    $building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;

    $buildings = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");

    $units = [];

    $wallet = null;
    $wallet_balance = 0.0;
    $wallet_target = 0.0;

    if ($building_id) {
        $wallet = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ms_building_wallet WHERE building_id = %d LIMIT 1",
                $building_id
            )
        );

        if ($wallet) {
            $wallet_balance = floatval($wallet->balance ?? 0);
            $wallet_target = floatval($wallet->target_amount ?? 0);
        }
    }

    $percent = $wallet_target > 0 ? min(100, round(($wallet_balance / $wallet_target) * 100, 2)) : 0;

    // Utility bills summary (using ms_utility_bills)
    $utility_bills_monthly = [];
    $utility_total_paid = 0.0;

    if ($building_id) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(billing_period_start, '%%Y-%%m') AS ym, SUM(total_amount) AS total
                 FROM {$wpdb->prefix}ms_utility_bills
                 WHERE building_id = %d
                 AND billing_period_start IS NOT NULL
                 GROUP BY ym
                 ORDER BY ym DESC
                 LIMIT 12",
                $building_id
            )
        );

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $utility_bills_monthly[] = [
                    'ym' => $r->ym,
                    'total' => floatval($r->total ?? 0),
                ];
            }
        }

        $sumRow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(total_amount) AS total_paid
                 FROM {$wpdb->prefix}ms_utility_bills
                 WHERE building_id = %d",
                $building_id
            )
        );
        $utility_total_paid = floatval($sumRow->total_paid ?? 0);
    }

    // Distributed vs collected (placeholder logic)
    // We don't have ms_utility_bill distribution/invoicing paid status tracking beyond utility_bill_items.
    // We'll show distributed total from items.
    $distributed_total = 0.0;
    if ($building_id) {
        $sumRow2 = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount),0) AS distributed
                 FROM {$wpdb->prefix}ms_utility_bill_items i
                 INNER JOIN {$wpdb->prefix}ms_utility_bills b ON b.id = i.bill_id
                 WHERE b.building_id = %d",
                $building_id
            )
        );
        $distributed_total = floatval($sumRow2->distributed ?? 0);
    }

    echo '<div class="wrap"><h1>تقرير المحفظة (Wallet Report)</h1>';

    echo '<form method="get" style="margin:16px 0">';
    echo '<input type="hidden" name="page" value="mostaager-wallet-report">';
    echo '<label style="margin-right:8px">اختر المبنى: </label>';
    echo '<select name="building_id">';
    echo '<option value="0">-- اختر --</option>';
    foreach ($buildings as $b) {
        $bid = intval($b->id);
        $sel = $bid === $building_id ? 'selected' : '';
        echo '<option value="' . esc_attr($bid) . '" ' . $sel . '>' . esc_html($b->title) . ' (#' . esc_html((string)$bid) . ')</option>';
    }
    echo '</select> <button class="button">عرض</button>';
    echo '</form>';

    if (!$building_id) {
        echo '<p>اختر مبنى لعرض التقرير.</p>';
        echo '</div>';
        return;
    }

    echo '<h2 style="margin-top:22px">ملخص</h2>';

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">';
    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';
    echo '<h3 style="margin:0 0 8px 0">رصيد المحفظة</h3>';
    echo '<div style="font-size:22px;font-weight:800">ج.م ' . esc_html(number_format_i18n($wallet_balance, 2)) . '</div>';
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';
    echo '<h3 style="margin:0 0 8px 0">الهدف</h3>';
    echo '<div style="font-size:22px;font-weight:800">ج.م ' . esc_html(number_format_i18n($wallet_target, 2)) . '</div>';
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';
    echo '<h3 style="margin:0 0 10px 0">نسبة التحصيل</h3>';
    echo '<div style="background:#f3f4f6;border-radius:999px;overflow:hidden;height:18px">';
    echo '<div style="width:' . esc_attr((string)$percent) . '%;background:#10b981;height:100%"></div>';
    echo '</div>';
    echo '<div style="margin-top:8px;color:#475569;font-size:13px">' . esc_html((string)$percent) . '%</div>';
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';
    echo '<h3 style="margin:0 0 8px 0">Utility Bills (إجمالي)</h3>';
    echo '<div style="font-size:22px;font-weight:800">ج.م ' . esc_html(number_format_i18n($utility_total_paid, 2)) . '</div>';
    echo '</div>';
    echo '</div>';

    echo '<h2 style="margin-top:22px">Utility Bills - شهرياً</h2>';
    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';

    if (empty($utility_bills_monthly)) {
        echo '<p>لا توجد بيانات كافية للفواتير التشغيلية لهذا المبنى.</p>';
    } else {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>الشهر</th><th>إجمالي</th></tr></thead><tbody>';
        foreach ($utility_bills_monthly as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['ym']) . '</td>';
            echo '<td>ج.م ' . esc_html(number_format_i18n($row['total'], 2)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';

    echo '<h2 style="margin-top:22px">مقارنة (Distributed)</h2>';
    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px">';
    echo '<p style="margin:0;color:#475569">Distributed total (from ms_utility_bill_items): <strong>ج.م ' . esc_html(number_format_i18n($distributed_total, 2)) . '</strong></p>';
    echo '</div>';

    echo '</div>';
}

