/**
 * Mostager Facilities Pro - Dashboard Charts
 * Chart.js 3.x+ visualizations for admin dashboard
 * 
 * @package Mostager_Facilities_Pro
 * @version 2.0.0
 */

jQuery(document).ready(function($) {
    
    // Check if Chart.js loaded
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded - dashboard charts disabled');
        return;
    }
    
    // Check if data is available
    if (typeof mostager_dashboard === 'undefined') {
        console.warn('Dashboard data not available');
        return;
    }
    
    var data = mostager_dashboard;
    
    // Common chart defaults
    Chart.defaults.font.family = 'Cairo, "Segoe UI", Tahoma, sans-serif';
    Chart.defaults.color = '#666';
    Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';
    
    // ===== 1. MONTHLY EXPENSES STACKED BAR CHART =====
    var expensesCtx = document.getElementById('expenses-chart');
    if (expensesCtx && data.months) {
        new Chart(expensesCtx, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'كهرباء',
                        data: data.electricity || [],
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: '#FFC107',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'مياه',
                        data: data.water || [],
                        backgroundColor: 'rgba(33, 150, 243, 0.8)',
                        borderColor: '#2196F3',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'نظافة',
                        data: data.cleaning || [],
                        backgroundColor: 'rgba(76, 175, 80, 0.8)',
                        borderColor: '#4CAF50',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'أمن',
                        data: data.security || [],
                        backgroundColor: 'rgba(244, 67, 54, 0.8)',
                        borderColor: '#F44336',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'مصعد',
                        data: data.elevator || [],
                        backgroundColor: 'rgba(156, 39, 176, 0.8)',
                        borderColor: '#9C27B0',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'صيانة',
                        data: data.maintenance_exp || [],
                        backgroundColor: 'rgba(96, 125, 139, 0.8)',
                        borderColor: '#607D8B',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'إدارة',
                        data: data.management || [],
                        backgroundColor: 'rgba(255, 152, 0, 0.8)',
                        borderColor: '#FF9800',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(10, 42, 74, 0.95)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                       context.parsed.y.toLocaleString('ar-EG') + ' ج.م';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            font: { size: 11 },
                            callback: function(value) {
                                return value.toLocaleString('ar-EG');
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // ===== 2. OCCUPANCY RATE DOUGHNUT CHART =====
    var occupancyCtx = document.getElementById('occupancy-chart');
    if (occupancyCtx) {
        var occupied = parseInt(data.occupied) || 0;
        var vacant = parseInt(data.vacant) || 0;
        var maintenance = parseInt(data.maintenance) || 0;
        var reserved = parseInt(data.reserved) || 0;
        var total = occupied + vacant + maintenance + reserved;
        var rate = total > 0 ? Math.round((occupied / total) * 100) : 0;
        
        new Chart(occupancyCtx, {
            type: 'doughnut',
            data: {
                labels: ['مؤجرة', 'شاغرة', 'صيانة', 'محجوزة'],
                datasets: [{
                    data: [occupied, vacant, maintenance, reserved],
                    backgroundColor: ['#4CAF50', '#FF9800', '#F44336', '#2196F3'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var val = context.parsed;
                                var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    var ctx = chart.ctx;
                    var centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                    var centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                    
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    
                    ctx.font = 'bold 28px Cairo, sans-serif';
                    ctx.fillStyle = '#0a2a4a';
                    ctx.fillText(rate + '%', centerX, centerY - 8);
                    
                    ctx.font = '12px Cairo, sans-serif';
                    ctx.fillStyle = '#999';
                    ctx.fillText('نسبة الإشغال', centerX, centerY + 14);
                    
                    ctx.restore();
                }
            }]
        });
    }

    // ===== 3. PAYMENT COLLECTION LINE CHART =====
    var paymentCtx = document.getElementById('payment-chart');
    if (paymentCtx && data.months) {
        new Chart(paymentCtx, {
            type: 'line',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'المحصل',
                        data: data.collected || [],
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#4CAF50',
                    },
                    {
                        label: 'المستحق',
                        data: data.due || [],
                        borderColor: '#FF9800',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#FF9800',
                    },
                    {
                        label: 'المتأخر',
                        data: data.overdue || [],
                        borderColor: '#F44336',
                        backgroundColor: 'rgba(244, 67, 54, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#F44336',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { padding: 15, usePointStyle: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                       context.parsed.y.toLocaleString('ar-EG') + ' ج.م';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return (value / 1000) + 'k';
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // ===== 4. MAINTENANCE TRENDS BAR CHART =====
    var maintenanceCtx = document.getElementById('maintenance-chart');
    if (maintenanceCtx && data.months) {
        new Chart(maintenanceCtx, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'طلبات الصيانة',
                        data: data.maintenance_requests || [],
                        backgroundColor: function(context) {
                            var value = context.raw;
                            return value > 10 ? 'rgba(244, 67, 54, 0.8)' : 'rgba(33, 150, 243, 0.8)';
                        },
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'المكتملة',
                        data: data.maintenance_completed || [],
                        backgroundColor: 'rgba(76, 175, 80, 0.8)',
                        borderRadius: 4,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { padding: 15, usePointStyle: true }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }

    // ===== 5. YEAR SELECTOR HANDLER =====
    $('#mostager-year-select').on('change', function() {
        var year = $(this).val();
        var buildingId = $('#mostager-building-select').val() || '';
        
        // Reload page with new year parameter
        var url = new URL(window.location.href);
        url.searchParams.set('year', year);
        if (buildingId) {
            url.searchParams.set('building_id', buildingId);
        }
        window.location.href = url.toString();
    });

    // ===== 6. BUILDING SELECTOR HANDLER =====
    $('#mostager-building-select').on('change', function() {
        var buildingId = $(this).val();
        var year = $('#mostager-year-select').val() || new Date().getFullYear();
        
        var url = new URL(window.location.href);
        if (buildingId) {
            url.searchParams.set('building_id', buildingId);
        } else {
            url.searchParams.delete('building_id');
        }
        url.searchParams.set('year', year);
        window.location.href = url.toString();
    });

    // ===== 7. EXPORT CHARTS BUTTON =====
    $('.export-chart-btn').on('click', function() {
        var chartId = $(this).data('chart');
        var canvas = document.getElementById(chartId);
        if (canvas) {
            var link = document.createElement('a');
            link.download = chartId + '_' + new Date().toISOString().split('T')[0] + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
    });
});
