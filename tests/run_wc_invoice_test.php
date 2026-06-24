<?php
/**
 * اختبار وظيفي: إنشاء فاتورة ثم إنشاء طلب WooCommerce لها
 *
 * طريقة التشغيل (من جذر تثبيت WordPress حيث الإضافة مفعّلة):
 * php tests/run_wc_invoice_test.php
 *
 * ملاحظات:
 * - يجب تشغيل هذا الملف داخل جذر WordPress (حيث يوجد wp-load.php)
 * - يجب تفعيل الإضافة وووكومرس قبل التشغيل
 */

// حاول تحميل WordPress
$pathsToCheck = [
    __DIR__ . '/../../wp-load.php',      // when running from plugin folder
    __DIR__ . '/../../../wp-load.php',    // alternative
    __DIR__ . '/wp-load.php',             // if placed in WP root/tests
    __DIR__ . '/../wp-load.php',
];

$loaded = false;
foreach ($pathsToCheck as $p) {
    if (file_exists($p)) {
        require_once $p;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    fwrite(STDERR, "خطأ: لم أجد wp-load.php. ضع هذا الملف داخل جذر WordPress أو عدّل المسار في بداية الملف.\n");
    exit(2);
}

if (!defined('ABSPATH')) {
    fwrite(STDERR, "خطأ: لم يتم تحميل WordPress بشكل صحيح.\n");
    exit(2);
}

global $wpdb;

if (!function_exists('ms_create_woo_order_for_invoice')) {
    fwrite(STDERR, "خطأ: الدالة ms_create_woo_order_for_invoice غير متاحة. تأكد أن الإضافة مُفعّلة.\n");
    exit(2);
}

// 1) Insert a test invoice into ms_invoices
$inv_table = $wpdb->prefix . 'ms_invoices';
$test_user = get_current_user_id();
if (empty($test_user)) {
    // Find any user
    $test_user = $wpdb->get_var("SELECT ID FROM {$wpdb->users} LIMIT 1");
}

$inserted = $wpdb->insert($inv_table, [
    'user_id' => intval($test_user ?: 1),
    'building_id' => 0,
    'unit_id' => 0,
    'description' => 'اختبار فاتورة تلقائي',
    'amount' => 10.00,
    'status' => 'pending',
    'due_date' => date('Y-m-d', strtotime('+7 days')),
    'invoice_type' => 'test',
    'created_at' => current_time('mysql'),
], ['%d','%d','%d','%s','%f','%s','%s','%s','%s']);

if (!$inserted) {
    fwrite(STDERR, "فشل إدراج الفاتورة الاختبارية في جدول {$inv_table}\n");
    exit(3);
}

$invoice_id = intval($wpdb->insert_id);

fwrite(STDOUT, "تم إنشاء فاتورة اختبارية id={$invoice_id}\n");

// 2) Attempt to create WooCommerce order for the invoice
$result = ms_create_woo_order_for_invoice($invoice_id);

if ($result === false) {
    fwrite(STDERR, "فشل إنشاء طلب WooCommerce للفاتورة {$invoice_id}. تأكد من تفعيل WooCommerce وتهيئة بوابة mostaager_telr.\n");
    exit(4);
}

fwrite(STDOUT, "نتيجة ms_create_woo_order_for_invoice: ");
if (is_string($result)) {
    fwrite(STDOUT, "Redirect/payment URL: {$result}\n");
} elseif (is_array($result)) {
    fwrite(STDOUT, json_encode($result) . "\n");
} else {
    fwrite(STDOUT, print_r($result, true) . "\n");
}

// 3) Show invoice row after update (check wc_order_id)
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $inv_table WHERE id = %d", $invoice_id));

fwrite(STDOUT, "سطر الفاتورة بعد العملية:\n");
if ($row) fwrite(STDOUT, print_r($row, true) . "\n");

fwrite(STDOUT, "انتهى الاختبار. يرجى مراجعة المخرجات أعلاه وملفات السجلات إذا لزم الأمر.\n");

return 0;
