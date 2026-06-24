<?php
if (!defined('ABSPATH')) exit;

add_shortcode('owner_dashboard_v4', function () {

    ob_start();

    $user = wp_get_current_user();

    $properties = function_exists('ms_get_properties_by_owner') ? ms_get_properties_by_owner($user->ID) : [];
    $invoices = function_exists('ms_get_owner_invoices') ? ms_get_owner_invoices($user->ID) : [];
    $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;
    $owner_summary = function_exists('ms_get_owner_revenue_summary') ? ms_get_owner_revenue_summary($user->ID) : array('total_paid' => 0, 'total_due' => 0, 'next_due' => null);
    $owner_wallet_transactions = function_exists('ms_get_wallet_transactions_for_user') ? ms_get_wallet_transactions_for_user($user->ID, 20) : array();
    $wallet_topup_url = '';

    // Owner notifications & unread count
    $owner_notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();
    $owner_unread_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;

    // Gather maintenance requests for buildings linked to owner's properties
    global $wpdb;
    $owner_maintenance_requests = array();
    $building_ids = array();
    if (!empty($properties)) {
        foreach ($properties as $prop) {
            $prop_id = isset($prop->id) ? intval($prop->id) : (isset($prop->ID) ? intval($prop->ID) : 0);
            $b = 0;
            if ($prop_id) {
                $b = intval(get_post_meta($prop_id, 'building_id', true));
            }
            if (!$b && isset($prop->building_id)) {
                $b = intval($prop->building_id);
            }
            if (!$b && isset($prop->unit) && is_object($prop->unit) && isset($prop->unit->building_id)) {
                $b = intval($prop->unit->building_id);
            }
            if ($b) {
                $building_ids[] = $b;
            }
        }
    }
    $building_ids = array_values(array_unique(array_filter($building_ids, 'absint')));
    if (!empty($building_ids)) {
        $ids_csv = implode(',', array_map('intval', $building_ids));
        $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
        // Important: $building_ids contains building IDs, not unit/property IDs.
        // Do not pass it to ms_get_maintenance_by_property_ids(), which filters unit_id.
        $owner_maintenance_requests = $wpdb->get_results("SELECT * FROM {$maint_table} WHERE building_id IN ({$ids_csv}) ORDER BY created_at DESC LIMIT 50");
    }

    ?>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '🏠'),
            array('label' => 'العقارات', 'data_tab' => 'properties', 'icon' => '🏘️', 'badge' => intval(count($properties))),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾', 'badge' => intval(count($invoices))),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'المحفظة', 'data_tab' => 'wallet', 'icon' => '💰'),
            array('label' => 'تقارير المالك', 'data_tab' => 'owner-reports', 'icon' => '📄'),
            array('label' => 'الإشعارات', 'data_tab' => 'notifications', 'icon' => '🔔', 'badge' => intval($owner_unread_count)),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">

            <div class="ms-owner-top">
                <h1>مرحبا، <?php echo esc_html($user->display_name); ?></h1>
            </div>

            <div class="ms-tab-content active" id="overview">
                <div class="ms-owner-grid">
                    <div class="ms-card"><h3>عدد العقارات</h3><div id="ms-props-count" class="ms-amount"><?php echo intval(count($properties)); ?></div></div>
                    <div class="ms-card"><h3>الفواتير</h3><div id="ms-invoices-count" class="ms-amount"><?php echo intval(count($invoices)); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">مدفوعة: <span id="ms-invoices-paid"><?php echo function_exists('ms_get_owner_invoices_count_by_status') ? ms_get_owner_invoices_count_by_status($user->ID,'paid') : 0; ?></span> — معلقة: <span id="ms-invoices-pending"><?php echo function_exists('ms_get_owner_invoices_count_by_status') ? ms_get_owner_invoices_count_by_status($user->ID,'pending') : 0; ?></span></div>
                    </div>
<div class="ms-card"><h3>رصيد المحفظة</h3><div id="ms-wallet-balance" class="ms-amount"><?php echo 'ج.م ' . number_format_i18n($wallet,2); ?></div></div>
<div class="ms-card"><h3>إجمالي الإيرادات</h3><div class="ms-amount">ج.م <span id="ms-owner-total-paid"><?php echo number_format_i18n($owner_summary['total_paid'],2); ?></span></div>
<div style="margin-top:8px;font-size:13px;color:#666">المستحق: ج.م <span id="ms-owner-total-due"><?php echo number_format_i18n($owner_summary['total_due'],2); ?></span> — متأخرة: <span id="ms-owner-overdue"><?php echo function_exists('ms_get_owner_overdue_count') ? ms_get_owner_overdue_count($user->ID) : 0; ?></span></div>
                        <div style="margin-top:6px;font-size:13px;color:#444">القادم: <span id="ms-owner-next-due"><?php echo !empty($owner_summary['next_due']->due_date) ? esc_html($owner_summary['next_due']->due_date) : '—'; ?></span></div>
                    </div>
                </div>

                <div class="ms-card" style="margin-top: 20px;">
                    <h3>الإشعارات الأخيرة</h3>
                    <?php if (!empty($owner_notifications)) : ?>
                        <ul style="list-style:none;padding:0;margin:0;">
                            <?php foreach ($owner_notifications as $note) : ?>
                                <li style="padding:12px 0;border-bottom:1px solid #e5e7eb;">
                                    <div style="font-size:14px;color:#111;"><?php echo esc_html($note->message ?? ''); ?></div>
                                    <div style="margin-top:8px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p style="padding:12px;margin:0;">لا توجد إشعارات جديدة.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Owner properties tab (Houzez-style listing)
            global $properties_query, $delete_properties_nonce;
            $delete_properties_nonce = wp_create_nonce('delete_properties_nonce');

            $paged = get_query_var('paged') ?: get_query_var('page') ?: 1;
            $property_ids = array();
            if (function_exists('ms_get_properties_by_owner')) {
                $owned_properties = ms_get_properties_by_owner($user->ID);
                foreach ($owned_properties as $owned_property) {
                    // If property is Houzez post-based, use its post ID
                    if (isset($owned_property->type) && $owned_property->type === 'houzez') {
                        if (isset($owned_property->id)) {
                            $property_ids[] = intval($owned_property->id);
                            continue;
                        } elseif (isset($owned_property->ID)) {
                            $property_ids[] = intval($owned_property->ID);
                            continue;
                        }
                    }

                    // For unit/other types, attempt to use linked WP post id if present
                    if (!empty($owned_property->wp_post_id)) {
                        $property_ids[] = intval($owned_property->wp_post_id);
                        continue;
                    }
                    if (!empty($owned_property->post_id)) {
                        $property_ids[] = intval($owned_property->post_id);
                        continue;
                    }

                    // If the property has a nested unit object, try its wp_post_id
                    if (!empty($owned_property->unit) && is_object($owned_property->unit) && !empty($owned_property->unit->wp_post_id)) {
                        $property_ids[] = intval($owned_property->unit->wp_post_id);
                        continue;
                    }
                }
            }
            $property_ids = array_values(array_unique(array_filter($property_ids, 'absint')));

            $args = array(
                'post_type' => 'property',
                'paged' => $paged,
                'posts_per_page' => 10,
                'suppress_filters' => false,
                'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            );

            if (!empty($property_ids)) {
                $args['post__in'] = $property_ids;
                $args['orderby'] = 'post__in';
            } else {
                $args['author'] = $user->ID;
            }

            $properties_query = new WP_Query($args);
            $properties_count = intval($properties_query->found_posts);

            $rent_sale_types = array('rent', 'sale');
            $invoice_groups = array(
                'rent_sale' => array(),
                'facility_management' => array(),
            );
            foreach ($invoices as $invoice_item) {
                $invoice_type = isset($invoice_item->invoice_type) ? strtolower(trim($invoice_item->invoice_type)) : '';
                if (in_array($invoice_type, $rent_sale_types, true)) {
                    $invoice_groups['rent_sale'][] = $invoice_item;
                } else {
                    $invoice_groups['facility_management'][] = $invoice_item;
                }
            }
            ?>

            <div class="ms-tab-content" id="properties">
                <?php if ( isset($properties_query) && $properties_query->have_posts() ): ?>
                    <?php get_template_part('template-parts/dashboard/property/tabs'); ?>
                    <div class="houzez-data-content">
                        <?php get_template_part('template-parts/dashboard/property/filters'); ?>

                        <div class="houzez-data-table">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Thumbnail</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php global $ms_dashboard_context; $ms_dashboard_context = 'owner'; ?>
                                        <?php while ( $properties_query->have_posts() ): $properties_query->the_post(); ?>
                                            <?php include ms_get_dashboard_template_path('templates/partials/property-row.php'); ?>
                                        <?php endwhile; wp_reset_postdata(); unset($ms_dashboard_context); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php get_template_part('template-parts/dashboard/property/pagination'); ?>
                    </div>
                <?php else: ?>
                    <div class="ms-card"><p style="padding:12px;">لا توجد عقارات</p></div>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card"><h3>الفواتير</h3>
                <?php if(!empty($invoices)): ?>
                    <div style="margin-top:18px;">
                        <h4 style="margin:0 0 10px;font-size:1rem;color:#0f172a;">فواتير الإيجار والبيع</h4>
                        <?php if (!empty($invoice_groups['rent_sale'])): ?>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                <thead>
                                    <tr style="background: #f3f4f6;">
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">#</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">الوصف</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">المبنى</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">المبلغ</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">الحالة</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">تاريخ الاستحقاق</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $invoice_index = 1; foreach($invoice_groups['rent_sale'] as $inv): ?>
                                        <?php
                                            $description = isset($inv->description) ? $inv->description : (isset($inv->invoice_type) ? $inv->invoice_type : 'فاتورة');
                                            $building_name = '—';
                                            if (isset($inv->building_id) && $inv->building_id) {
                                                $building_post = get_post($inv->building_id);
                                                if ($building_post) {
                                                    $building_name = $building_post->post_title;
                                                }
                                            }
                                            $amount = isset($inv->amount) ? number_format_i18n(floatval($inv->amount), 2) : '0.00';
                                            $status = isset($inv->status) ? $inv->status : 'unknown';
                                            $due_date = isset($inv->due_date) ? $inv->due_date : '—';
                                            $is_paid = $status === 'paid';
                                        ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; text-align: center;"><?php echo intval($invoice_index++); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($description); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($building_name); ?></td>
                                            <td style="padding: 12px;">ج.م <?php echo esc_html($amount); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if ($is_paid): ?>
                                                    <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">مدفوعة</span>
                                                <?php else: ?>
                                                    <?php 
                                                        $status_label = $status;
                                                        if ($status === 'pending') {
                                                            $status_label = 'معلقة';
                                                        } elseif ($status === 'overdue') {
                                                            $status_label = 'متأخرة';
                                                        }
                                                        echo esc_html($status_label);
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px;"><?php echo esc_html($due_date); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if (!$is_paid && (!isset($inv->source) || $inv->source !== 'legacy')): ?>
                                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($inv->id); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . $inv->id); ?>" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">ادفع الآن</button>
                                                <?php elseif ($is_paid): ?>
                                                    <span style="color: #10b981; font-size: 12px;">✓ تم الدفع</span>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">غير متاح للمدفوعات القديمة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 12px; margin:0;">لا توجد فواتير إيجار أو بيع.</p>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:24px;">
                        <h4 style="margin:0 0 10px;font-size:1rem;color:#0f172a;">فواتير البناء وإدارة المرافق</h4>
                        <?php if (!empty($invoice_groups['facility_management'])): ?>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                <thead>
                                    <tr style="background: #f3f4f6;">
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">#</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">الوصف</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">المبنى</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">المبلغ</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">الحالة</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">تاريخ الاستحقاق</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $invoice_index = 1; foreach($invoice_groups['facility_management'] as $inv): ?>
                                        <?php
                                            $description = isset($inv->description) ? $inv->description : (isset($inv->invoice_type) ? $inv->invoice_type : 'فاتورة');
                                            $building_name = '—';
                                            if (isset($inv->building_id) && $inv->building_id) {
                                                $building_post = get_post($inv->building_id);
                                                if ($building_post) {
                                                    $building_name = $building_post->post_title;
                                                }
                                            }
                                            $amount = isset($inv->amount) ? number_format_i18n(floatval($inv->amount), 2) : '0.00';
                                            $status = isset($inv->status) ? $inv->status : 'unknown';
                                            $due_date = isset($inv->due_date) ? $inv->due_date : '—';
                                            $is_paid = $status === 'paid';
                                        ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; text-align: center;"><?php echo intval($invoice_index++); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($description); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($building_name); ?></td>
                                            <td style="padding: 12px;">ج.م <?php echo esc_html($amount); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if ($is_paid): ?>
                                                    <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">مدفوعة</span>
                                                <?php else: ?>
                                                    <?php 
                                                        $status_label = $status;
                                                        if ($status === 'pending') {
                                                            $status_label = 'معلقة';
                                                        } elseif ($status === 'overdue') {
                                                            $status_label = 'متأخرة';
                                                        }
                                                        echo esc_html($status_label);
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px;"><?php echo esc_html($due_date); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if (!$is_paid && (!isset($inv->source) || $inv->source !== 'legacy')): ?>
                                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($inv->id); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . $inv->id); ?>" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">ادفع الآن</button>
                                                <?php elseif ($is_paid): ?>
                                                    <span style="color: #10b981; font-size: 12px;">✓ تم الدفع</span>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">غير متاح للمدفوعات القديمة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 12px; margin:0;">لا توجد فواتير بناء أو إدارة مرافق.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="padding: 12px;">لا توجد فواتير</p>
                <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <div class="ms-card"><h3>الصيانة</h3>
                    <?php if (!empty($owner_maintenance_requests)) : ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">العنوان</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">النوع</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">التكلفة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الحالة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">تاريخ الإنشاء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($owner_maintenance_requests as $req) : ?>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <td style="padding:12px;"><?php echo esc_html($req->title ?? 'بدون عنوان'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->maintenance_type ?? ($req->type ?? '—')); ?></td>
                                        <td style="padding:12px;">ج.م <?php echo isset($req->cost) ? number_format_i18n(floatval($req->cost),2) : '0.00'; ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->status ?? '—'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->created_at ?? $req->created_on ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="padding:12px;">لا توجد طلبات صيانة مرتبطة بعقاراتك.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="wallet">
                <div class="ms-card">
                    <h3>المحفظة</h3>
                    <p>الرصيد الحالي: <?php echo 'ج.م ' . number_format_i18n($wallet, 2); ?></p>
                    <div style="margin-top:12px;max-width:320px;">
                        <label for="ms-owner-wallet-topup-amount" style="display:block;margin-bottom:6px;font-weight:600;">المبلغ المطلوب شحنه</label>
                        <input type="number" id="ms-owner-wallet-topup-amount" name="wallet_topup_amount" min="1" step="0.01" value="100.00" class="ms-input" style="width:100%;max-width:180px;" />
                    </div>
                    <p style="margin-top:12px;"><button type="button" id="ms-owner-wallet-topup-btn" class="button button-primary" data-checkout-url="<?php echo esc_attr($wallet_topup_url); ?>">شحن المحفظة</button></p>
                    <?php if (empty($wallet_topup_url)) : ?>
                        <p style="margin-top:8px;color:#6b7280;font-size:13px;">يرجى تفعيل WooCommerce أو إعداد طريقة شحن المحفظة.</p>
                    <?php endif; ?>
                    <?php if (!empty($owner_wallet_transactions)) : ?>
                        <div class="ms-table-wrap" style="margin-top:16px;">
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
                                    <?php foreach ($owner_wallet_transactions as $tx) : ?>
                                        <tr>
                                            <td><?php echo esc_html($tx->description ?? $tx->note ?? 'معاملة'); ?></td>
                                            <td>ج.م <?php echo isset($tx->amount) ? number_format_i18n(floatval($tx->amount), 2) : '0.00'; ?></td>
                                            <td><?php echo esc_html($tx->type ?? '—'); ?></td>
                                            <td><?php echo esc_html($tx->created_at ?? $tx->date ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p style="margin-top:12px;">لا توجد معاملات محفظة حديثة.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="owner-reports">
                <?php echo function_exists('msfp_render_owner_reports') ? msfp_render_owner_reports($user->ID) : '<div class="ms-card"><h3>تقارير المالك</h3><p>وحدة التقارير غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="notifications">
                <div class="ms-card"><h3>الإشعارات</h3>
                    <?php if (!empty($owner_notifications)) : ?>
                        <ul class="ms-notifications-list" style="list-style:none;padding:0;margin:0;">
                            <?php foreach ($owner_notifications as $note) : ?>
                                <li style="padding:12px 0;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;" data-notification-id="<?php echo intval($note->id ?? $note->notification_id ?? 0); ?>" class="<?php echo !empty($note->is_read) ? 'ms-notification-read' : 'ms-notification-unread'; ?>">
                                    <div>
                                        <div style="font-size:14px;color:#111;"><?php echo esc_html($note->message ?? ''); ?></div>
                                        <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                    </div>
                                    <?php if (empty($note->is_read)) : ?>
                                        <span style="color:#2563eb;font-size:18px;line-height:1;">●</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="padding:12px;">لا توجد إشعارات جديدة.</p>
                    <?php endif; ?>
                </div>
            </div>

        </main>

    </div>

    <?php

    return ob_get_clean();

});
