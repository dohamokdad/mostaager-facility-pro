<?php
/**
 * Mostager Facilities Pro - Invoice PDF Generator
 * Generates branded Arabic RTL PDF invoices using TCPDF
 * 
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 * 
 * REQUIREMENT: composer require tecnickcom/tcpdf
 * Place in: mostager-facilities-pro/vendor/
 */

if (!defined('ABSPATH')) exit;

// Autoload TCPDF if available
$tcpdf_autoload = MOSTAGER_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($tcpdf_autoload)) {
    require_once $tcpdf_autoload;
}

class Mostager_Invoice_PDF {
    
    private $plugin_url;
    private $plugin_dir;
    
    public function __construct() {
        $this->plugin_url = defined('MOSTAGER_PLUGIN_URL') ? MOSTAGER_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
        $this->plugin_dir = defined('MOSTAGER_PLUGIN_DIR') ? MOSTAGER_PLUGIN_DIR : plugin_dir_path(dirname(__FILE__));
    }
    
    /**
     * Generate and output invoice PDF
     * 
     * @param int $invoice_id Invoice ID
     * @param string $output 'D' for download, 'I' for inline, 'S' for string
     * @return void|string
     */
    public function generate_invoice($invoice_id, $output = 'D') {
        global $wpdb;
        
        // Validate TCPDF is available
        if (!class_exists('TCPDF')) {
            wp_die('مكتبة TCPDF غير متوفرة. يرجى تثبيتها عبر Composer: composer require tecnickcom/tcpdf');
        }
        
        // Get invoice data with JOINs (canonical ms_ tables)
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, b.title AS building_name, b.address AS building_address, u.unit_number, u.floor, u.id AS unit_id, usr.display_name AS owner_name, usr.user_email AS owner_email
            FROM {$wpdb->prefix}ms_invoices i
            LEFT JOIN {$wpdb->prefix}ms_buildings b ON i.building_id = b.id
            LEFT JOIN {$wpdb->prefix}ms_units u ON i.unit_id = u.id
            LEFT JOIN {$wpdb->users} usr ON i.user_id = usr.ID
            WHERE i.id = %d",
            $invoice_id
        ));
        
        if (!$invoice) {
            return new WP_Error('not_found', 'الفاتورة غير موجودة');
        }
        
        // Use single-line invoice approach: represent invoice as single item
        $items = array();
        $desc = '';
        if (!empty($invoice->description)) {
            $desc = wp_strip_all_tags($invoice->description);
        } else {
            $desc = 'فاتورة خدمات';
        }
        $items[] = (object) array(
            'description' => $desc,
            'quantity' => 1,
            'unit' => '',
            'unit_price' => floatval($invoice->amount ?? 0),
            'total' => floatval($invoice->amount ?? 0),
        );
        
        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item->total);
        }
        $tax_rate = 0.14; // 14% Egyptian VAT
        $tax_amount = $subtotal * $tax_rate;
        $total = $subtotal + $tax_amount;
        
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Document settings
        $pdf->SetCreator('Mostager Facilities Pro');
        $pdf->SetAuthor('منصة مستأجر العقاري');
        $pdf->SetTitle('فاتورة #' . ($invoice->invoice_number ?: $invoice_id));
        $pdf->SetSubject('فاتورة مصاريف مرافق - ' . $invoice->building_name);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterFont(['aealarabiya', '', 9]);
        $pdf->setFooterMargin(10);
        
        // RTL mode
        $pdf->setRTL(true);
        
        // Set margins
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Set Arabic font
        $pdf->SetFont('aealarabiya', '', 11);
        
        // Add page
        $pdf->AddPage();
        
        // ========== HEADER ==========
        $this->render_header($pdf, $invoice);
        
        // ========== INVOICE INFO ==========
        $this->render_invoice_info($pdf, $invoice);
        
        // ========== ITEMS TABLE ==========
        $this->render_items_table($pdf, $items);
        
        // ========== TOTALS ==========
        $this->render_totals($pdf, $subtotal, $tax_amount, $total);
        
        // ========== NOTES & FOOTER ==========
        $this->render_footer_notes($pdf, $invoice);
        
        // ========== QR CODE ==========
        if (method_exists($pdf, 'write2DBarcode')) {
            $qr_data = json_encode([
                'invoice' => $invoice->invoice_number,
                'amount' => $total,
                'building' => $invoice->building_name,
                'date' => $invoice->issue_date,
            ]);
            $pdf->SetXY(12, -45);
            $pdf->write2DBarcode($qr_data, 'QRCODE,L', '', '', 30, 30);
        }
        
        // ========== OUTPUT ==========
        $filename = 'فاتورة_' . ($invoice->invoice_number ?: $invoice_id) . '_' . sanitize_file_name($invoice->building_name) . '.pdf';
        
        return $pdf->Output($filename, $output);
    }
    
    /**
     * Render PDF header with branding
     */
    private function render_header($pdf, $invoice) {
        // Blue header bar
        $pdf->SetFillColor(10, 42, 74);
        $pdf->Rect(0, 0, 210, 38, 'F');
        
        // Company name
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('aealarabiya', 'B', 18);
        $pdf->SetXY(15, 8);
        $pdf->Cell(100, 10, 'منصة مستأجر العقاري', 0, 0, 'R');
        
        // Subtitle
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetXY(15, 19);
        $pdf->Cell(100, 6, 'نظام إدارة المرافق والعقارات', 0, 0, 'R');
        
        // Contact info
        $pdf->SetFont('aealarabiya', '', 8);
        $pdf->SetXY(15, 26);
        $pdf->Cell(100, 5, 'info@ejar-egy.com | 01010756695 | www.ejar-egy.com', 0, 0, 'R');
        
        // Invoice type badge
        $pdf->SetFillColor(212, 175, 55);
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 13);
        $pdf->SetXY(140, 10);
        $pdf->Cell(55, 10, 'فاتورة مصاريف مرافق', 0, 1, 'C', true);
        
        // Invoice number
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(140, 21);
        $pdf->Cell(55, 7, 'رقم: ' . ($invoice->invoice_number ?: $invoice->id), 0, 1, 'C');
        
        // Gold line
        $pdf->SetFillColor(212, 175, 55);
        $pdf->Rect(0, 38, 210, 1.5, 'F');
    }
    
    /**
     * Render invoice info section
     */
    private function render_invoice_info($pdf, $invoice) {
        $pdf->SetY(48);
        $pdf->SetTextColor(10, 42, 74);
        
        // Section title
        $pdf->SetFont('aealarabiya', 'B', 12);
        $pdf->Cell(0, 8, 'معلومات الفاتورة', 0, 1, 'R');
        
        // Two-column layout
        $col_width = 88;
        $line_height = 7;
        
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Right column - Building & Unit info
        $pdf->SetX(12);
        $pdf->Cell($col_width, $line_height, 'المبنى: ' . ($invoice->building_name ?: '---'), 0, 0, 'R');
        $pdf->Cell($col_width, $line_height, 'تاريخ الإصدار: ' . $this->format_date($invoice->issue_date), 0, 1, 'R');
        
        $pdf->SetX(12);
        $pdf->Cell($col_width, $line_height, 'العنوان: ' . ($invoice->building_address ?: '---'), 0, 0, 'R');
        $pdf->Cell($col_width, $line_height, 'تاريخ الاستحقاق: ' . $this->format_date($invoice->due_date), 0, 1, 'R');
        
        $pdf->SetX(12);
        $pdf->Cell($col_width, $line_height, 'الوحدة: ' . ($invoice->unit_number ?: '---') . ($invoice->floor ? ' (الدور ' . $invoice->floor . ')' : ''), 0, 0, 'R');
        $pdf->Cell($col_width, $line_height, 'حالة الدفع: ' . $this->get_status_label($invoice->status), 0, 1, 'R');
        
        // Owner info if available
        if ($invoice->owner_name) {
            $pdf->SetX(12);
            $pdf->Cell($col_width, $line_height, 'رئيس اتحاد الملاك: ' . $invoice->owner_name, 0, 0, 'R');
            $pdf->Cell($col_width, $line_height, '', 0, 1, 'R');
        }
        
        // Separator line
        $pdf->SetY($pdf->GetY() + 5);
        $pdf->SetDrawColor(212, 175, 55);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->SetY($pdf->GetY() + 5);
    }
    
    /**
     * Render items table
     */
    private function render_items_table($pdf, $items) {
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 11);
        $pdf->Cell(0, 8, 'تفاصيل المصاريف', 0, 1, 'R');
        
        // Table header
        $pdf->SetFillColor(10, 42, 74);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('aealarabiya', 'B', 10);
        
        $col_desc = 70;
        $col_qty = 30;
        $col_unit = 35;
        $col_total = 40;
        $row_height = 9;
        
        $pdf->Cell($col_desc, $row_height, 'البيان', 1, 0, 'C', true);
        $pdf->Cell($col_qty, $row_height, 'الكمية', 1, 0, 'C', true);
        $pdf->Cell($col_unit, $row_height, 'السعر الوحدة', 1, 0, 'C', true);
        $pdf->Cell($col_total, $row_height, 'الإجمالي', 1, 1, 'C', true);
        
        // Table rows
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('aealarabiya', '', 10);
        
        $fill = false;
        foreach ($items as $item) {
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
            
            $pdf->Cell($col_desc, $row_height, $item->description, 1, 0, 'R', true);
            $pdf->Cell($col_qty, $row_height, $item->quantity . ' ' . ($item->unit ?: ''), 1, 0, 'C', true);
            $pdf->Cell($col_unit, $row_height, number_format($item->unit_price, 2) . ' ج.م', 1, 0, 'C', true);
            $pdf->Cell($col_total, $row_height, number_format($item->total, 2) . ' ج.م', 1, 1, 'C', true);
            
            $fill = !$fill;
        }
        
        // If no items, show empty row
        if (empty($items)) {
            $pdf->SetFillColor(248, 248, 248);
            $pdf->Cell($col_desc + $col_qty + $col_unit + $col_total, $row_height, 'لا توجد بنود', 1, 1, 'C', true);
        }
        
        $pdf->Ln(3);
    }
    
    /**
     * Render totals section
     */
    private function render_totals($pdf, $subtotal, $tax_amount, $total) {
        $col_label = 55;
        $col_value = 40;
        $row_height = 8;
        
        $pdf->SetX(103);
        
        // Subtotal
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($col_label, $row_height, 'المجموع الفرعي:', 0, 0, 'R');
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 10);
        $pdf->Cell($col_value, $row_height, number_format($subtotal, 2) . ' ج.م', 0, 1, 'L');
        
        // Tax
        $pdf->SetX(103);
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($col_label, $row_height, 'ضريبة القيمة المضافة (14%):', 0, 0, 'R');
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 10);
        $pdf->Cell($col_value, $row_height, number_format($tax_amount, 2) . ' ج.م', 0, 1, 'L');
        
        // Discount if any
        // $pdf->SetX(103);
        // $pdf->SetFont('aealarabiya', '', 10);
        // $pdf->SetTextColor(80, 80, 80);
        // $pdf->Cell($col_label, $row_height, 'الخصم:', 0, 0, 'R');
        // $pdf->SetTextColor(244, 67, 54);
        // $pdf->SetFont('aealarabiya', 'B', 10);
        // $pdf->Cell($col_value, $row_height, '- 0.00 ج.م', 0, 1, 'L');
        
        // Total with highlight
        $pdf->SetX(103);
        $pdf->SetFillColor(212, 175, 55);
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 13);
        $pdf->Cell($col_label, 12, 'الإجمالي:', 0, 0, 'R', true);
        $pdf->Cell($col_value, 12, number_format($total, 2) . ' ج.م', 0, 1, 'L', true);
        
        $pdf->Ln(5);
    }
    
    /**
     * Render footer notes
     */
    private function render_footer_notes($pdf, $invoice) {
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('aealarabiya', '', 9);
        
        // Notes
        $notes = $invoice->notes ?: "شروط السداد:\n";
        $notes .= "- يرجى سداد الفاتورة قبل تاريخ الاستحقاق لتجنب تطبيق غرامات التأخير بنسبة 2% شهرياً.\n";
        $notes .= "- للاستفسارات، يرجى التواصل مع إدارة المبنى على الرقم 01010756695.\n";
        $notes .= "- يمكن السداد عبر التحويل البنكي أو فوري أو Vodafone Cash.";
        
        $pdf->MultiCell(0, 6, $notes, 0, 'R');
        
        // Bank details box
        $pdf->Ln(3);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(212, 175, 55);
        $pdf->SetTextColor(10, 42, 74);
        $pdf->SetFont('aealarabiya', 'B', 9);
        $pdf->Cell(0, 8, 'تفاصيل الحساب البنكي للتحويل', 1, 1, 'C', true);
        $pdf->SetFont('aealarabiya', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, 'البنك: البنك الأهلي المصري | اسم الحساب: مستأجر العقاري | رقم الحساب: سيتم إضافته', 1, 1, 'C', true);
    }
    
    /**
     * Custom footer
     */
    public function render_pdf_footer($pdf) {
        $pdf->SetY(-12);
        $pdf->SetFont('aealarabiya', '', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, 'تم إنشاء هذه الفاتورة إلكترونياً بواسطة نظام مستأجر لإدارة المرافق | صفحة ' . $pdf->getAliasNumPage() . ' من ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    }
    
    /**
     * Format date to Arabic
     */
    private function format_date($date) {
        if (!$date) return '---';
        
        $timestamp = strtotime($date);
        $months = [
            '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس',
            '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو',
            '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر',
            '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
        ];
        
        $day = date('d', $timestamp);
        $month = $months[date('m', $timestamp)] ?? date('m', $timestamp);
        $year = date('Y', $timestamp);
        
        return $day . ' ' . $month . ' ' . $year;
    }
    
    /**
     * Get Arabic status label
     */
    private function get_status_label($status) {
        $labels = [
            'paid' => 'مسددة',
            'pending' => 'معلقة',
            'overdue' => 'متأخرة',
            'cancelled' => 'ملغية',
            'partial' => 'مسددة جزئياً',
        ];
        return $labels[$status] ?? $status;
    }
    
    /**
     * Send invoice via email with PDF attachment
     * 
     * @param int $invoice_id Invoice ID
     * @param string $email Recipient email
     * @return bool Success
     */
    public function email_invoice($invoice_id, $email) {
        // Generate PDF to temporary file
        $temp_file = wp_tempnam('invoice_' . $invoice_id . '.pdf');
        
        ob_start();
        $result = $this->generate_invoice($invoice_id, 'F');
        if (is_wp_error($result)) {
            ob_end_clean();
            @unlink($temp_file);
            return false;
        }
        ob_end_clean();
        
        // Get invoice info for email from ms_ tables
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, b.title AS building_name, u.unit_number
            FROM {$wpdb->prefix}ms_invoices i
            LEFT JOIN {$wpdb->prefix}ms_buildings b ON i.building_id = b.id
            LEFT JOIN {$wpdb->prefix}ms_units u ON i.unit_id = u.id
            WHERE i.id = %d",
            $invoice_id
        ));
        
        $subject = 'فاتورة مصاريف مرافق #' . ($invoice->invoice_number ?: $invoice_id);
        
        $message = "مرحباً،\n\n";
        $message .= "تم إصدار فاتورة جديدة للعقار.\n\n";
        $message .= "المبنى: {$invoice->building_name}\n";
        $message .= "الوحدة: {$invoice->unit_number}\n";
        $message .= "رقم الفاتورة: {$invoice->invoice_number}\n\n";
        $message .= "يمكنكم الاطلاع على الفاتورة المرفقة.\n\n";
        $message .= "منصة مستأجر العقاري\n";
        $message .= "info@ejar-egy.com | 01010756695";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $attachments = [$temp_file];
        
        $sent = wp_mail($email, $subject, $message, $headers, $attachments);
        
        @unlink($temp_file);
        
        return $sent;
    }
}

/**
 * AJAX handler: Generate invoice PDF for download
 */
add_action('wp_ajax_mostager_generate_invoice_pdf', 'mostager_ajax_generate_pdf');
add_action('wp_ajax_nopriv_mostager_generate_invoice_pdf', 'mostager_ajax_generate_pdf');

function mostager_ajax_generate_pdf() {
    // Check permissions
    if (!is_user_logged_in()) {
        wp_send_json_error('يجب تسجيل الدخول');
    }
    
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    
    if (!$invoice_id) {
        wp_send_json_error('رقم الفاتورة مطلوب');
    }
    
    $generator = new Mostager_Invoice_PDF();
    $result = $generator->generate_invoice($invoice_id, 'D');
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success(['message' => 'تم إنشاء الفاتورة بنجاح']);
}

/**
 * AJAX handler: Send invoice via email
 */
add_action('wp_ajax_mostager_email_invoice', 'mostager_ajax_email_invoice');
function mostager_ajax_email_invoice() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error('غير مصرح');
    }
    
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!$invoice_id || !$email) {
        wp_send_json_error('البيانات غير مكتملة');
    }
    
    $generator = new Mostager_Invoice_PDF();
    $sent = $generator->email_invoice($invoice_id, $email);
    
    if ($sent) {
        wp_send_json_success('تم إرسال الفاتورة بنجاح');
    } else {
        wp_send_json_error('فشل إرسال الفاتورة');
    }
}
