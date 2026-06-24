<?php
/**
 * Mostager Facilities Pro - Enhanced Dashboard View
 * Modern dashboard with Chart.js visualizations
 * 
 * Include this in your plugin's admin page:
 * require_once MOSTAGER_PLUGIN_DIR . 'admin/views/dashboard.php';
 */

if (!defined('ABSPATH')) exit;

// Get filters
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$selected_building = isset($_GET['building_id']) ? intval($_GET['building_id']) : null;

// Initialize charts class
$charts = new Mostager_Dashboard_Charts($selected_building, $selected_year);
$stats = $charts->get_summary_stats();

// Get buildings for filter
$buildings = $wpdb->get_results("SELECT id, building_name FROM {$wpdb->prefix}mostager_buildings ORDER BY building_name ASC");

// Year options
$current_year = intval(date('Y'));
$year_options = range($current_year - 2, $current_year + 1);
?>

<div class="wrap mostager-dashboard">
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1><i class="fas fa-chart-line" style="color: #d4af37;"></i> لوحة التحكم</h1>
        
        <div class="dashboard-filters">
            <span class="filter-label">السنة:</span>
            <select id="mostager-year-select">
                <?php foreach ($year_options as $year): ?>
                <option value="<?php echo $year; ?>" <?php selected($selected_year, $year); ?>><?php echo $year; ?></option>
                <?php endforeach; ?>
            </select>
            
            <span class="filter-label">المبنى:</span>
            <select id="mostager-building-select">
                <option value="">جميع المباني</option>
                <?php foreach ($buildings as $building): ?>
                <option value="<?php echo $building->id; ?>" <?php selected($selected_building, $building->id); ?>><?php echo esc_html($building->building_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="<?php echo admin_url('admin.php?page=mostager-add-invoice'); ?>" class="quick-action-btn">
            <i class="fas fa-file-invoice"></i>
            <span>فاتورة جديدة</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=mostager-maintenance&action=new'); ?>" class="quick-action-btn">
            <i class="fas fa-wrench"></i>
            <span>طلب صيانة</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=mostager-payments'); ?>" class="quick-action-btn">
            <i class="fas fa-money-bill-wave"></i>
            <span>تسجيل دفعة</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=mostager-units&action=add'); ?>" class="quick-action-btn">
            <i class="fas fa-plus-circle"></i>
            <span>وحدة جديدة</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=mostager-reports'); ?>" class="quick-action-btn">
            <i class="fas fa-file-export"></i>
            <span>تقرير</span>
        </a>
    </div>
    
    <!-- Stats Cards Row -->
    <div class="dashboard-stats-row">
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-building"></i></div>
            <div class="stat-value"><?php echo number_format_i18n($stats['total_buildings']); ?></div>
            <div class="stat-label">المباني</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-door-open"></i></div>
            <div class="stat-value"><?php echo number_format_i18n($stats['total_units']); ?></div>
            <div class="stat-label">إجمالي الوحدات</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="color: #4CAF50;"><?php echo number_format_i18n($stats['occupied']); ?></div>
            <div class="stat-label">وحدات مؤجرة</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percentage"></i></div>
            <div class="stat-value" style="color: #d4af37;"><?php echo $stats['occupancy_rate']; ?>%</div>
            <div class="stat-label">نسبة الإشغال</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value" style="color: #4CAF50;"><?php echo number_format_i18n($stats['monthly_revenue']); ?> ج.م</div>
            <div class="stat-label">إيرادات الشهر</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value" style="color: <?php echo $stats['pending_payments'] > 0 ? '#F44336' : '#4CAF50'; ?>;">
                <?php echo number_format_i18n($stats['pending_payments']); ?> ج.م
            </div>
            <div class="stat-label">مبالغ مستحقة</div>
        </div>
        
    </div>
    
    <!-- Charts Row 1 -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i> المصاريف التشغيلية الشهرية
                </div>
                <div class="chart-actions">
                    <button class="export-chart-btn" data-chart="expenses-chart" title="تصدير"><i class="fas fa-download"></i></button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="expenses-chart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i> نسبة الإشغال
                </div>
                <div class="chart-actions">
                    <button class="export-chart-btn" data-chart="occupancy-chart" title="تصدير"><i class="fas fa-download"></i></button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="occupancy-chart"></canvas>
            </div>
        </div>
        
    </div>
    
    <!-- Charts Row 2 -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-money-bill-wave"></i> حالة التحصيل
                </div>
                <div class="chart-actions">
                    <button class="export-chart-btn" data-chart="payment-chart" title="تصدير"><i class="fas fa-download"></i></button>
                </div>
            </div>
            <div class="chart-container sm">
                <canvas id="payment-chart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-wrench"></i> طلبات الصيانة
                </div>
                <div class="chart-actions">
                    <button class="export-chart-btn" data-chart="maintenance-chart" title="تصدير"><i class="fas fa-download"></i></button>
                </div>
            </div>
            <div class="chart-container sm">
                <canvas id="maintenance-chart"></canvas>
            </div>
        </div>
        
    </div>
    
    <!-- Bottom Row: Recent Activity & Alerts -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        
        <div class="recent-activity">
            <div class="activity-title">
                <i class="fas fa-clock"></i> آخر النشاطات
            </div>
            <ul class="activity-list">
                <?php
                // Get recent activity (this is a simplified version - customize based on your DB)
                $recent_items = [];
                
                // Recent invoices
                $recent_invoices = $wpdb->get_results(
                    "SELECT i.*, b.building_name 
                    FROM {$wpdb->prefix}mostager_invoices i
                    LEFT JOIN {$wpdb->prefix}mostager_buildings b ON i.building_id = b.id
                    ORDER BY i.created_at DESC LIMIT 3"
                );
                
                foreach ($recent_invoices as $inv) {
                    $recent_items[] = [
                        'type' => 'invoice',
                        'icon' => 'file-invoice',
                        'text' => 'فاتورة جديدة #' . $inv->invoice_number . ' - ' . $inv->building_name,
                        'time' => human_time_diff(strtotime($inv->created_at), current_time('timestamp')) . ' ago',
                    ];
                }
                
                // Show items
                if (empty($recent_items)) {
                    echo '<li class="activity-item"><div class="activity-content"><div class="activity-text">لا توجد نشاطات حديثة</div></div></li>';
                } else {
                    foreach ($recent_items as $item):
                    ?>
                    <li class="activity-item">
                        <div class="activity-icon <?php echo $item['type']; ?>">
                            <i class="fas fa-<?php echo $item['icon']; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text"><?php echo esc_html($item['text']); ?></div>
                            <div class="activity-time"><?php echo esc_html($item['time']); ?></div>
                        </div>
                    </li>
                    <?php 
                    endforeach;
                }
                ?>
            </ul>
        </div>
        
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-bell" style="color: #F44336;"></i> تنبيهات
            </div>
            <div style="padding: 15px 0;">
                <?php if ($stats['open_tickets'] > 0): ?>
                <div style="padding: 12px; background: rgba(244, 67, 54, 0.05); border-radius: 8px; margin-bottom: 10px; border-right: 3px solid #F44336;">
                    <div style="font-weight: 700; color: #F44336; font-size: 14px; font-family: 'Cairo', sans-serif;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $stats['open_tickets']; ?> طلب صيانة مفتوح
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=mostager-maintenance'); ?>" style="font-size: 12px; color: #666;">عرض الكل &rarr;</a>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['pending_payments'] > 0): ?>
                <div style="padding: 12px; background: rgba(255, 152, 0, 0.05); border-radius: 8px; margin-bottom: 10px; border-right: 3px solid #FF9800;">
                    <div style="font-weight: 700; color: #FF9800; font-size: 14px; font-family: 'Cairo', sans-serif;">
                        <i class="fas fa-clock"></i> مبالغ مستحقة: <?php echo number_format_i18n($stats['pending_payments']); ?> ج.م
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=mostager-payments&status=pending'); ?>" style="font-size: 12px; color: #666;">عرض الكل &rarr;</a>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['vacant'] > 0): ?>
                <div style="padding: 12px; background: rgba(33, 150, 243, 0.05); border-radius: 8px; border-right: 3px solid #2196F3;">
                    <div style="font-weight: 700; color: #2196F3; font-size: 14px; font-family: 'Cairo', sans-serif;">
                        <i class="fas fa-home"></i> <?php echo number_format_i18n($stats['vacant']); ?> وحدة شاغرة
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=mostager-units&status=vacant'); ?>" style="font-size: 12px; color: #666;">عرض الكل &rarr;</a>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['open_tickets'] == 0 && $stats['pending_payments'] == 0 && $stats['vacant'] == 0): ?>
                <div style="text-align: center; padding: 30px; color: #4CAF50;">
                    <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <div style="font-family: 'Cairo', sans-serif; font-weight: 600;">كل شيء على ما يرام!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
</div>

<?php
// Enqueue all chart assets
$charts->enqueue_chart_assets();
