<?php
/**
 * Mostager Facilities Pro - Dashboard Charts Data Provider
 * Provides data for Chart.js visualizations
 * 
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

class Mostager_Dashboard_Charts {
    
    private $building_id;
    private $year;
    
    /**
     * Constructor
     * 
     * @param int|null $building_id Filter by building (null for all)
     * @param int|null $year Year to analyze (default: current year)
     */
    public function __construct($building_id = null, $year = null) {
        $this->building_id = $building_id ? intval($building_id) : null;
        $this->year = $year ? intval($year) : intval(date('Y'));
    }
    
    /**
     * Get monthly expense breakdown for stacked bar chart
     * 
     * @return array Expense data by month and category
     */
    public function get_monthly_expenses() {
        global $wpdb;
        
        $expense_table = $wpdb->prefix . 'ms_expenses';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$expense_table}'") !== $expense_table) {
            return $this->get_empty_monthly_data();
        }
        
        $months = [];
        $categories = [
            'electricity' => ['label' => 'كهرباء', 'color' => 'rgba(255, 193, 7, 0.8)', 'border' => '#FFC107'],
            'water' => ['label' => 'مياه', 'color' => 'rgba(33, 150, 243, 0.8)', 'border' => '#2196F3'],
            'cleaning' => ['label' => 'نظافة', 'color' => 'rgba(76, 175, 80, 0.8)', 'border' => '#4CAF50'],
            'security' => ['label' => 'أمن', 'color' => 'rgba(244, 67, 54, 0.8)', 'border' => '#F44336'],
            'elevator' => ['label' => 'مصعد', 'color' => 'rgba(156, 39, 176, 0.8)', 'border' => '#9C27B0'],
            'management' => ['label' => 'إدارة', 'color' => 'rgba(255, 152, 0, 0.8)', 'border' => '#FF9800'],
            'maintenance' => ['label' => 'صيانة', 'color' => 'rgba(96, 125, 139, 0.8)', 'border' => '#607D8B'],
        ];
        
        $result = [];
        foreach ($categories as $key => $meta) {
            $result[$key] = array_fill(0, 12, 0);
        }
        
        for ($i = 1; $i <= 12; $i++) {
            $month_name = date_i18n('F', mktime(0, 0, 0, $i, 1));
            $months[] = $this->get_arabic_month($month_name);
            
            foreach ($categories as $key => $meta) {
                $where = $this->building_id 
                    ? $wpdb->prepare("WHERE YEAR(expense_date) = %d AND MONTH(expense_date) = %d AND category = %s AND building_id = %d", $this->year, $i, $key, $this->building_id)
                    : $wpdb->prepare("WHERE YEAR(expense_date) = %d AND MONTH(expense_date) = %d AND category = %s", $this->year, $i, $key);
                
                $amount = $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$expense_table} {$where}");
                $result[$key][$i - 1] = floatval($amount);
            }
        }
        
        $result['months'] = $months;
        return $result;
    }
    
    /**
     * Get occupancy data for doughnut chart
     * 
     * @return array Unit status counts
     */
    public function get_occupancy_data() {
        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$units_table}'") !== $units_table) {
            return ['occupied' => 0, 'vacant' => 0, 'maintenance' => 0, 'reserved' => 0];
        }
        
        $where = $this->building_id ? $wpdb->prepare("WHERE building_id = %d", $this->building_id) : '';
        
        $occupied = $wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'occupied'");
        $vacant = $wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'vacant'");
        $maintenance = $wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'maintenance'");
        $reserved = $wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'reserved'");
        
        return [
            'occupied' => intval($occupied),
            'vacant' => intval($vacant),
            'maintenance' => intval($maintenance),
            'reserved' => intval($reserved),
        ];
    }
    
    /**
     * Get payment collection data for line chart
     * 
     * @return array Monthly payment data
     */
    public function get_payment_data() {
        global $wpdb;
        $payments_table = $wpdb->prefix . 'ms_payments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$payments_table}'") !== $payments_table) {
            return $this->get_empty_monthly_data();
        }
        
        $months = [];
        $collected = [];
        $due = [];
        $overdue = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $month_name = date_i18n('F', mktime(0, 0, 0, $i, 1));
            $months[] = $this->get_arabic_month($month_name);
            
            $base_where = $this->building_id
                ? $wpdb->prepare("building_id = %d AND YEAR(payment_date) = %d AND MONTH(payment_date) = %d", $this->building_id, $this->year, $i)
                : $wpdb->prepare("YEAR(payment_date) = %d AND MONTH(payment_date) = %d", $this->year, $i);
            
            $collected[] = floatval($wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE {$base_where} AND status = 'paid'") ?: 0);
            $due[] = floatval($wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE {$base_where} AND status = 'pending'") ?: 0);
            $overdue[] = floatval($wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE {$base_where} AND status = 'overdue'") ?: 0);
        }
        
        return [
            'months' => $months,
            'collected' => $collected,
            'due' => $due,
            'overdue' => $overdue,
        ];
    }
    
    /**
     * Get maintenance request trends
     * 
     * @return array Monthly maintenance data
     */
    public function get_maintenance_trends() {
        global $wpdb;
        $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$maint_table}'") !== $maint_table) {
            return array_merge($this->get_empty_monthly_data(), ['maintenance_requests' => array_fill(0, 12, 0), 'maintenance_completed' => array_fill(0, 12, 0)]);
        }
        
        $months = [];
        $requests = [];
        $completed = [];
        $high_priority = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $month_name = date_i18n('F', mktime(0, 0, 0, $i, 1));
            $months[] = $this->get_arabic_month($month_name);
            
            $base_where = $this->building_id
                ? $wpdb->prepare("building_id = %d AND YEAR(created_at) = %d AND MONTH(created_at) = %d", $this->building_id, $this->year, $i)
                : $wpdb->prepare("YEAR(created_at) = %d AND MONTH(created_at) = %d", $this->year, $i);
            
            $requests[] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$maint_table} WHERE {$base_where}") ?: 0);
            $completed[] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$maint_table} WHERE {$base_where} AND status = 'completed'") ?: 0);
            $high_priority[] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$maint_table} WHERE {$base_where} AND priority = 'high'") ?: 0);
        }
        
        return [
            'months' => $months,
            'maintenance_requests' => $requests,
            'maintenance_completed' => $completed,
            'high_priority' => $high_priority,
        ];
    }
    
    /**
     * Get summary statistics for dashboard cards
     * 
     * @return array Summary data
     */
    public function get_summary_stats() {
        global $wpdb;
        
        $stats = [
            'total_units' => 0,
            'occupied' => 0,
            'vacant' => 0,
            'total_buildings' => 0,
            'monthly_revenue' => 0,
            'pending_payments' => 0,
            'open_tickets' => 0,
            'occupancy_rate' => 0,
        ];
        
        $units_table = $wpdb->prefix . 'mostager_units';
        $buildings_table = $wpdb->prefix . 'mostager_buildings';
        $payments_table = $wpdb->prefix . 'mostager_payments';
        $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
        
        // Units count
        if ($wpdb->get_var("SHOW TABLES LIKE '{$units_table}'") === $units_table) {
            $where = $this->building_id ? $wpdb->prepare("WHERE building_id = %d", $this->building_id) : '';
            $stats['total_units'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where}") ?: 0);
            $stats['occupied'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'occupied'") ?: 0);
            $stats['vacant'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$units_table} {$where} " . ($where ? 'AND' : 'WHERE') . " status = 'vacant'") ?: 0);
        }
        
        // Buildings count
        if ($wpdb->get_var("SHOW TABLES LIKE '{$buildings_table}'") === $buildings_table) {
            $stats['total_buildings'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$buildings_table}") ?: 0);
        }
        
        // Monthly revenue
        if ($wpdb->get_var("SHOW TABLES LIKE '{$payments_table}'") === $payments_table) {
            $where = $this->building_id 
                ? $wpdb->prepare("WHERE building_id = %d AND YEAR(payment_date) = %d AND MONTH(payment_date) = %d AND status = 'paid'", $this->building_id, $this->year, intval(date('n')))
                : $wpdb->prepare("WHERE YEAR(payment_date) = %d AND MONTH(payment_date) = %d AND status = 'paid'", $this->year, intval(date('n')));
            $stats['monthly_revenue'] = floatval($wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} {$where}") ?: 0);
            
            // Pending payments
            $where_pending = $this->building_id
                ? $wpdb->prepare("WHERE building_id = %d AND status IN ('pending', 'overdue')", $this->building_id)
                : "WHERE status IN ('pending', 'overdue')";
            $stats['pending_payments'] = floatval($wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} {$where_pending}") ?: 0);
        }
        
        // Open maintenance tickets
        if ($wpdb->get_var("SHOW TABLES LIKE '{$maint_table}'") === $maint_table) {
            $where = $this->building_id ? $wpdb->prepare("WHERE building_id = %d AND status NOT IN ('completed', 'cancelled')", $this->building_id) : "WHERE status NOT IN ('completed', 'cancelled')";
            $stats['open_tickets'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$maint_table} {$where}") ?: 0);
        }
        
        // Occupancy rate
        if ($stats['total_units'] > 0) {
            $stats['occupancy_rate'] = round(($stats['occupied'] / $stats['total_units']) * 100);
        }
        
        return $stats;
    }
    
    /**
     * Get all chart data combined for JavaScript
     * 
     * @return array All chart datasets
     */
    public function get_all_chart_data() {
        $expenses = $this->get_monthly_expenses();
        $occupancy = $this->get_occupancy_data();
        $payments = $this->get_payment_data();
        $maintenance = $this->get_maintenance_trends();
        $summary = $this->get_summary_stats();
        
        // Merge all data
        $all_data = array_merge($expenses, $occupancy, $payments, $maintenance, $summary);
        
        return $all_data;
    }
    
    /**
     * Enqueue Chart.js and localize data
     */
    public function enqueue_chart_assets() {
        // Enqueue Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Enqueue dashboard charts script
        wp_enqueue_script(
            'mostager-dashboard-charts',
            MOSTAGER_PLUGIN_URL . 'admin/js/dashboard-charts.js',
            ['jquery', 'chartjs'],
            '2.0.0',
            true
        );
        
        // Localize data
        wp_localize_script('mostager-dashboard-charts', 'mostager_dashboard', $this->get_all_chart_data());
        
        // Enqueue dashboard styles
        wp_enqueue_style(
            'mostager-dashboard',
            MOSTAGER_PLUGIN_URL . 'admin/css/dashboard-enhanced.css',
            [],
            '2.0.0'
        );
    }
    
    /**
     * Get empty monthly data structure
     */
    private function get_empty_monthly_data() {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = $this->get_arabic_month(date_i18n('F', mktime(0, 0, 0, $i, 1)));
        }
        return ['months' => $months];
    }
    
    /**
     * Convert English month to Arabic
     * 
     * @param string $month English month name
     * @return string Arabic month name
     */
    private function get_arabic_month($month) {
        $months = [
            'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
            'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
            'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
            'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر',
        ];
        
        // Try localized month first
        $ar_months = [
            'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
        ];
        
        // If already Arabic, return as-is
        if (in_array($month, $ar_months)) {
            return $month;
        }
        
        return $months[$month] ?? $month;
    }
}
