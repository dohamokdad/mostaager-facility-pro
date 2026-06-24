<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    $cap = 'manage_options';
    add_menu_page('Mostaager', 'Mostaager', $cap, 'mostaager-admin', function () {
        echo '<div class="wrap"><h1>Mostaager</h1><p>Manage Mostaager data tables.</p></div>';
    }, 'dashicons-building', 58);

    add_submenu_page('mostaager-admin', 'Buildings', 'Buildings', $cap, 'mostaager-buildings', 'ms_admin_buildings_page');
    add_submenu_page('mostaager-admin', 'Units', 'Units', $cap, 'mostaager-units', 'ms_admin_units_page');
    add_submenu_page('mostaager-admin', 'Invoices', 'Invoices', $cap, 'mostaager-invoices', 'ms_admin_invoices_page');
    add_submenu_page('mostaager-admin', 'Maintenance', 'Maintenance', $cap, 'mostaager-maintenance', 'ms_admin_maintenance_page');
    add_submenu_page('mostaager-admin', 'Migrations', 'Migrations', $cap, 'mostaager-migrations', 'ms_admin_migrations_page');

    // New grouped pages per plan (separate handlers to avoid conflict with legacy pages)
    add_submenu_page('mostaager-admin', 'المستأجرون', 'المستأجرون', $cap, 'mostaager-tenants', 'ms_admin_tenants_page');
    add_submenu_page('mostaager-admin', 'الفواتير (MS)', 'الفواتير', $cap, 'mostaager-ms-invoices', 'ms_admin_ms_invoices_page');
    add_submenu_page('mostaager-admin', 'التحويلات (MS)', 'التحويلات', $cap, 'mostaager-ms-transfers', 'ms_admin_ms_transfers_page');

    add_menu_page('فواتير المرافق', 'فواتير المرافق', $cap, 'mostaager-all-invoices', 'ms_admin_all_invoices_page', 'dashicons-media-spreadsheet', 30);
    add_submenu_page('mostaager-all-invoices', 'طلبات التحويل', 'طلبات التحويل', $cap, 'mostaager-transfers', 'ms_admin_transfers_page');

    // Wallet report page (plan A5)
    add_menu_page('تقرير المحفظة', 'تقرير المحفظة', $cap, 'mostaager-wallet-report', 'ms_admin_wallet_report_page', 'dashicons-chart-pie', 31);
});


function ms_table_list_output($rows, $cols)
{
    echo '<table class="widefat fixed striped"><thead><tr>';
    foreach ($cols as $c) {
        echo '<th>' . esc_html($c) . '</th>';
    }
    echo '<th>Actions</th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="' . (count($cols) + 1) . '">No records found.</td></tr>';
    } else {
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($cols as $key => $label) {
                // allow numeric keys for direct property access
                $val = is_int($key) ? (isset($r->$label) ? $r->$label : '') : (isset($r->$key) ? $r->$key : '');
                echo '<td>' . esc_html($val) . '</td>';
            }

            $id = intval($r->id ?? $r->ID ?? 0);
            $type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            // map page to short type
            $map = array(
                'mostaager-buildings' => 'building',
                'mostaager-units' => 'unit',
                'mostaager-invoices' => 'invoice',
                'mostaager-maintenance' => 'maintenance',
                'mostaager-transfers' => 'transfer',
            );
            $short = isset($map[$type]) ? $map[$type] : '';
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=' . $short . '&id=' . $id), 'ms_delete_item');

            echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete record?\')">Delete</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
}

function ms_admin_get_building_label($building_id)
{
    global $wpdb;
    $building_id = intval($building_id);
    if (!$building_id) {
        return '—';
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $row = $wpdb->get_row($wpdb->prepare("SELECT title, wp_post_id FROM {$table} WHERE id = %d LIMIT 1", $building_id));
    if ($row) {
        if (!empty($row->title)) {
            return $row->title;
        }
        if (!empty($row->wp_post_id)) {
            $linked_title = get_the_title(intval($row->wp_post_id));
            if ($linked_title) {
                return $linked_title;
            }
        }
    }

    if (function_exists('ms_get_linked_wp_post_id_for_building')) {
        $linked_id = ms_get_linked_wp_post_id_for_building($building_id);
        if ($linked_id) {
            $linked_title = get_the_title(intval($linked_id));
            if ($linked_title) {
                return $linked_title;
            }
        }
    }

    $legacy_title = get_the_title($building_id);
    if ($legacy_title) {
        return $legacy_title;
    }

    return 'Building #' . $building_id;
}

function ms_admin_buildings_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $rows = $wpdb->get_results("SELECT b.*, COUNT(u.id) AS unit_count FROM {$tbl} b LEFT JOIN {$unit_tbl} u ON u.building_id = b.id GROUP BY b.id ORDER BY b.id DESC LIMIT 200");
    // fetch WP posts that represent buildings for linking
    $wp_buildings = array();
    $posts = get_posts(array('post_type' => array('building', 'ms_building'), 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids'));
    if (!empty($posts)) {
        foreach ($posts as $pid) {
            $p = get_post($pid);
            if ($p) {
                $wp_buildings[$p->ID] = $p->post_title;
            }
        }
    }

    $message = isset($_GET['ms_building_message']) ? sanitize_text_field($_GET['ms_building_message']) : '';

    echo '<div class="wrap"><h1>Buildings</h1>';
    if ($message === 'linked') {
        echo '<div class="notice notice-success"><p>Building linked to WordPress post successfully.</p></div>';
    } elseif ($message === 'conflict') {
        $conflict_title = isset($_GET['conflict_title']) ? esc_html(urldecode(sanitize_text_field($_GET['conflict_title']))) : '';
        echo '<div class="notice notice-warning"><p>Selected WordPress post is already linked to another building' . ($conflict_title ? ': ' . $conflict_title : '') . '.</p></div>';
    }

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>Title</th><th>Manager</th><th>Units</th><th>WordPress Post</th><th>Created</th><th>إجراء</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="7">No records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $manager = get_userdata(intval($row->manager_id));
            $manager_name = $manager ? $manager->display_name : ($row->manager_id ? 'User #' . intval($row->manager_id) : '--');
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=building&id=' . intval($row->id)), 'ms_delete_item');
            echo '<tr>';
            echo '<td>' . intval($row->id) . '</td>';
            echo '<td>' . esc_html($row->title) . '</td>';
            echo '<td>' . esc_html($manager_name) . '</td>';
            echo '<td>' . intval($row->unit_count) . '</td>';

            // WordPress post column
            $linked_wp_id = intval($row->wp_post_id ?? 0);
            if ($linked_wp_id && isset($wp_buildings[$linked_wp_id])) {
                $post_title = $wp_buildings[$linked_wp_id];
                $post_edit_url = admin_url('post.php?post=' . $linked_wp_id . '&action=edit');
                echo '<td><a href="' . esc_url($post_edit_url) . '">' . esc_html($post_title) . '</a></td>';
            } else {
                echo '<td><span style="color:#6b7280">غير مرتبط</span></td>';
            }

            echo '<td>' . esc_html($row->created_at ?? '') . '</td>';

            // Action: if not linked show select to link, else show change form
            echo '<td>';
            if (empty($wp_buildings)) {
                echo '<em>لا توجد مباني في WordPress</em>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
                wp_nonce_field('ms_link_building_post');
                echo '<input type="hidden" name="action" value="ms_link_building_post">';
                echo '<input type="hidden" name="building_id" value="' . intval($row->id) . '">';
                echo '<select name="wp_post_id" style="min-width:180px;">';
                echo '<option value="0">-- اختر منشور WordPress --</option>';
                foreach ($wp_buildings as $pid => $title) {
                    $selected = ($pid === $linked_wp_id) ? ' selected' : '';
                    echo '<option value="' . intval($pid) . '"' . $selected . '>' . esc_html($title) . ' (#' . intval($pid) . ')</option>';
                }
                echo '</select> ';
                echo '<button type="submit" class="button">حفظ</button>';
                echo ' <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete building?\')" class="button button-link-delete">Delete</a>';
                echo '</form>';
            }
            echo '</td>';

            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Handler for linking a Mostaager building row to a WordPress post
add_action('admin_post_ms_link_building_post', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'ms_link_building_post')) {
        wp_die('Invalid nonce', '400');
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $wp_post_id = isset($_POST['wp_post_id']) ? intval($_POST['wp_post_id']) : 0;
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_buildings';

    if (!$building_id) {
        wp_die('Invalid building id', '400');
    }

    if ($wp_post_id > 0) {
        $conflict = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tbl} WHERE wp_post_id = %d AND id != %d LIMIT 1", $wp_post_id, $building_id));
        if ($conflict) {
            $post = get_post($wp_post_id);
            $title = $post ? rawurlencode($post->post_title) : '';
            $redirect = add_query_arg(array('ms_building_message' => 'conflict', 'conflict_title' => $title), wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-buildings'));
            wp_safe_redirect($redirect);
            exit;
        }
    }

    $updated = $wpdb->update($tbl, array('wp_post_id' => $wp_post_id), array('id' => $building_id), array('%d'), array('%d'));
    $redirect = add_query_arg('ms_building_message', 'linked', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-buildings'));
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_ms_cancel_invoice', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
    if (!$invoice_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ms_cancel_invoice_' . $invoice_id)) {
        wp_die('Invalid request', '400');
    }

    if (!function_exists('ms_cancel_invoice') || !function_exists('ms_get_invoice_by_id')) {
        wp_die('Function not available', '500');
    }

    $inv = ms_get_invoice_by_id($invoice_id);
    if (!$inv) {
        wp_die('Invoice not found', '404');
    }
    if (strtolower(trim($inv->status ?? '')) !== 'pending') {
        wp_die('Invalid invoice state', '400');
    }

    ms_cancel_invoice($invoice_id);
    $redirect = add_query_arg('ms_message', 'invoice_canceled', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-invoices'));
    wp_safe_redirect($redirect);
    exit;
});

function ms_admin_units_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_units';
    $rows = $wpdb->get_results("SELECT * FROM {$tbl} ORDER BY id DESC LIMIT 200");
    echo '<div class="wrap"><h1>Units</h1>';
    ms_table_list_output($rows, array('id' => 'ID', 'building_id' => 'Building ID', 'owner_id' => 'Owner', 'tenant_id' => 'Tenant', 'status' => 'Status', 'created_at' => 'Created'));
    echo '</div>';
}

function ms_admin_all_invoices_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;

    $args = array(
        'post_type' => 'invoices',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $invoices_query = new WP_Query($args);
    $cpt_rows = array();
    if ($invoices_query->have_posts()) {
        while ($invoices_query->have_posts()) {
            $invoices_query->the_post();
            $invoice_id = get_the_ID();
            $building_id = get_post_meta($invoice_id, 'building_id', true);
            $property_id = get_post_meta($invoice_id, 'property_id', true);
            $amount = get_post_meta($invoice_id, 'amount_due', true);
            $status = get_post_meta($invoice_id, 'status', true);
            $created_at = get_post_meta($invoice_id, 'created_at', true) ?: get_the_date('Y-m-d H:i:s');
            $cpt_rows[] = (object) array(
                'id' => $invoice_id,
                'building_id' => intval($building_id),
                'property_id' => intval($property_id),
                'amount' => floatval($amount),
                'status' => $status,
                'created_at' => $created_at,
                'source' => 'legacy_cpt',
            );
        }
        wp_reset_postdata();
    }

    $legacy_rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_invoices ORDER BY created_at DESC");
    $rows = array_merge($cpt_rows, $legacy_rows);
    usort($rows, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? ($a->post_date ?? ''));
        $b_date = strtotime($b->created_at ?? ($b->post_date ?? ''));
        return $b_date <=> $a_date;
    });

    echo '<div class="wrap">';
    echo '<h1>جميع فواتير المرافق في الموقع</h1>';
    echo '<p>إجمالي السجلات: ' . intval(count($rows)) . '</p>';

    if (empty($rows)) {
        echo '<p>لا توجد فواتير.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>رقم الفاتورة</th>';
        echo '<th>المبنى</th>';
        echo '<th>الشقة</th>';
        echo '<th>المبلغ</th>';
        echo '<th>الحالة</th>';
        echo '<th>تاريخ الإنشاء</th>';
        echo '<th>المصدر</th>';
        echo '<th>إجراءات</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $invoice_id = intval($row->id ?? $row->ID ?? 0);
            $building_title = $row->building_id ? get_the_title(intval($row->building_id)) : '—';
            $property_title = $row->property_id ? get_the_title(intval($row->property_id)) : '—';
            $amount_label = isset($row->amount) ? number_format_i18n(floatval($row->amount), 2) . ' جنيه' : '—';
            $status_label = isset($row->status) ? esc_html($row->status) : '—';
            $created_at = esc_html($row->created_at ?? '—');
            $source = esc_html($row->source ?? '—');
            $edit_url = $invoice_id ? get_edit_post_link($invoice_id) : '#';

            echo '<tr>';
            echo '<td>' . $invoice_id . '</td>';
            echo '<td>' . esc_html($building_title) . '</td>';
            echo '<td>' . esc_html($property_title) . '</td>';
            echo '<td>' . esc_html($amount_label) . '</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . $created_at . '</td>';
            echo '<td>' . $source . '</td>';
            echo '<td>' . ($edit_url ? '<a href="' . esc_url($edit_url) . '" target="_blank">عرض</a>' : '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}


function ms_admin_invoices_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_invoices';
    $status_filter = isset($_GET['ms_status']) ? sanitize_text_field($_GET['ms_status']) : '';
    $sql = "SELECT * FROM {$tbl}";
    if (!empty($status_filter)) {
        $sql = $wpdb->prepare("SELECT * FROM {$tbl} WHERE status = %s", $status_filter);
    }
    $sql .= " ORDER BY id DESC LIMIT 200";
    $rows = $wpdb->get_results($sql);

    $message = isset($_GET['ms_message']) ? sanitize_text_field($_GET['ms_message']) : '';
    echo '<div class="wrap">';
    echo '<h1>Invoices</h1>';
    if ($message === 'invoice_paid') {
        echo '<div class="notice notice-success"><p>تم وضع الفاتورة كمدفوعة بنجاح.</p></div>';
    } elseif ($message === 'invoice_canceled') {
        echo '<div class="notice notice-success"><p>تم إلغاء الفاتورة بنجاح.</p></div>';
    }

    // Filter form
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-invoices">';
    echo '<label style="margin-right:8px">الحالة: </label>';
    echo '<select name="ms_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلقة', 'paid' => 'مدفوع', 'canceled' => 'ملغي');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> ';
    echo '<button class="button">تصفية</button>';
    echo '</form>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>نوع الدافع</th><th>اسم الشخص</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الاستحقاق</th><th>أنشئت بتاريخ</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="8">No invoices found.</td></tr>';
    } else {
        foreach ($rows as $invoice) {
            $payer_type_map = array(
                'owner' => 'مالك',
                'tenant' => 'مستأجر',
                'agent' => 'وسيط',
                'owner_or_tenant' => 'مالك/مستأجر',
            );
            $payer_type = strtolower(trim($invoice->payer_type ?? ''));
            $payer_type_label = isset($payer_type_map[$payer_type]) ? $payer_type_map[$payer_type] : 'غير معروف';

            $user = get_userdata(intval($invoice->user_id));
            $payer_name = $user ? $user->display_name : esc_html($invoice->payer_name ?? '--');

            $status_norm = strtolower(trim($invoice->status ?? ''));
            $paid_label = ($status_norm === 'paid') ? '<span style="color:#10b981;font-weight:600">مدفوع</span>' : ( $status_norm === 'canceled' ? '<span style="color:#ef4444;font-weight:600">ملغي</span>' : esc_html($invoice->status) );
            $action = '';
            if ($status_norm === 'pending') {
                $action .= '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_mark_invoice_paid&invoice_id=' . intval($invoice->id)), 'ms_mark_invoice_paid_' . intval($invoice->id))) . '" class="button button-secondary">تحديد كمدفوع</a>';
                $action .= ' ' . '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_cancel_invoice&invoice_id=' . intval($invoice->id)), 'ms_cancel_invoice_' . intval($invoice->id))) . '" class="button button-link-delete" onclick="return confirm(\'Are you sure you want to cancel this invoice?\')">إلغاء</a>';
            }

            echo '<tr>';
            echo '<td>' . intval($invoice->id) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($payer_name) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($invoice->amount), 2) . '</td>';
            echo '<td>' . $paid_label . '</td>';
            echo '<td>' . esc_html($invoice->due_date) . '</td>';
            echo '<td>' . esc_html($invoice->created_at ?? '') . '</td>';
            echo '<td>' . $action . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

add_action('admin_post_ms_mark_invoice_paid', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
    if (!$invoice_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ms_mark_invoice_paid_' . $invoice_id)) {
        wp_die('Invalid request', '400');
    }

    if (!function_exists('ms_mark_invoice_paid') || !function_exists('ms_get_invoice_by_id')) {
        wp_die('Function not available', '500');
    }

    $inv = ms_get_invoice_by_id($invoice_id);
    if (!$inv) {
        wp_die('Invoice not found', '404');
    }
    if (strtolower(trim($inv->status ?? '')) !== 'pending') {
        wp_die('Invalid invoice state', '400');
    }

    ms_mark_invoice_paid($invoice_id);
    $redirect = add_query_arg('ms_message', 'invoice_paid', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-invoices'));
    wp_safe_redirect($redirect);
    exit;
});

function ms_admin_maintenance_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_maintenance_requests';
    $rows = $wpdb->get_results("SELECT m.*, b.title AS building_title FROM {$tbl} m LEFT JOIN {$wpdb->prefix}ms_buildings b ON b.id = m.building_id ORDER BY m.id DESC LIMIT 200");
    echo '<div class="wrap"><h1>Maintenance / Expenses</h1>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>Building</th><th>Unit</th><th>Title</th><th>Cost</th><th>نوع الدافع</th><th>Manager</th><th>Type</th><th>Status</th><th>Due Date</th><th>Created</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="12">No records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $manager = get_userdata(intval($row->manager_id));
            $manager_name = $manager ? $manager->display_name : ($row->manager_id ? 'User #' . intval($row->manager_id) : '--');
            $payer_type_map = array(
                'owner' => 'مالك',
                'tenant' => 'مستأجر',
                'agent' => 'وسيط',
                'owner_or_tenant' => 'مالك/مستأجر',
            );
            $payer_type = strtolower(trim($row->payer_type ?? 'owner'));
            $payer_type_label = isset($payer_type_map[$payer_type]) ? $payer_type_map[$payer_type] : esc_html($payer_type);
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=maintenance&id=' . intval($row->id)), 'ms_delete_item');
            echo '<tr>';
            echo '<td>' . intval($row->id) . '</td>';
            echo '<td>' . esc_html($row->building_title ?: 'Building #' . intval($row->building_id)) . '</td>';
            echo '<td>' . ($row->unit_id ? 'Unit #' . intval($row->unit_id) : '--') . '</td>';
            echo '<td>' . esc_html($row->title) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($row->cost), 2) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($manager_name) . '</td>';
            echo '<td>' . esc_html($row->maintenance_type) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html($row->due_date ?? '') . '</td>';
            echo '<td>' . esc_html($row->created_at ?? '') . '</td>';
            echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete maintenance record?\')">Delete</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

function ms_admin_transfers_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    if (isset($_POST['approve_transfer']) && isset($_POST['transfer_action_nonce']) && wp_verify_nonce($_POST['transfer_action_nonce'], 'transfer_action')) {
        $transfer_id = intval($_POST['transfer_id']);
        update_post_meta($transfer_id, 'status', 'approved');
        update_post_meta($transfer_id, 'approved_by', get_current_user_id());
        update_post_meta($transfer_id, 'approved_date', current_time('mysql'));

        $building_id = get_post_meta($transfer_id, 'building_id', true);
        $manager_id = $building_id ? get_post_meta($building_id, 'manager_id', true) : 0;
        if ($manager_id) {
            if (function_exists('mostaager_add_internal_notification')) {
                mostaager_add_internal_notification($manager_id, 'تمت الموافقة على طلب التحويل', 'تمت الموافقة على طلب التحويل المالي الخاص بك.', $transfer_id);
            }
        }
    }

    if (isset($_POST['reject_transfer']) && isset($_POST['transfer_action_nonce']) && wp_verify_nonce($_POST['transfer_action_nonce'], 'transfer_action')) {
        $transfer_id = intval($_POST['transfer_id']);
        update_post_meta($transfer_id, 'status', 'rejected');
        update_post_meta($transfer_id, 'rejected_by', get_current_user_id());
        update_post_meta($transfer_id, 'rejected_date', current_time('mysql'));

        $building_id = get_post_meta($transfer_id, 'building_id', true);
        $manager_id = $building_id ? get_post_meta($building_id, 'manager_id', true) : 0;
        if ($manager_id) {
            if (function_exists('mostaager_add_internal_notification')) {
                mostaager_add_internal_notification($manager_id, 'تم رفض طلب التحويل', 'تم رفض طلب التحويل المالي الخاص بك.', $transfer_id);
            }
        }
    }

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $args = array(
        'post_type' => 'transfers',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $transfers_query = new WP_Query($args);
    $total_transfers = $transfers_query->found_posts;
    $total_pages = ceil($total_transfers / $per_page);

    echo '<div class="wrap">';
    echo '<h1>طلبات التحويل المالي</h1>';
    echo '<p>إجمالي الطلبات: ' . intval($total_transfers) . '</p>';

    if ($transfers_query->have_posts()) {
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>رقم الطلب</th>';
        echo '<th>المبنى</th>';
        echo '<th>المبلغ</th>';
        echo '<th>الحالة</th>';
        echo '<th>تاريخ الطلب</th>';
        echo '<th>الإجراءات</th>';
        echo '</tr></thead><tbody>';

        while ($transfers_query->have_posts()) {
            $transfers_query->the_post();
            $transfer_id = get_the_ID();
            $building_id = get_post_meta($transfer_id, 'building_id', true);
            $amount = get_post_meta($transfer_id, 'amount', true);
            $status = get_post_meta($transfer_id, 'status', true);
            $status_label = '';
            switch ($status) {
                case 'pending': $status_label = 'في الانتظار'; break;
                case 'approved': $status_label = 'معتمد'; break;
                case 'rejected': $status_label = 'مرفوض'; break;
                default: $status_label = 'غير محدد'; break;
            }

            echo '<tr>';
            echo '<td>' . $transfer_id . '</td>';
            echo '<td>' . esc_html($building_id ? get_the_title(intval($building_id)) : '—') . '</td>';
            echo '<td>' . ($amount ? number_format(floatval($amount), 2) . ' جنيه' : '—') . '</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>';
            if ($status === 'pending') {
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field('transfer_action', 'transfer_action_nonce');
                echo '<input type="hidden" name="transfer_id" value="' . $transfer_id . '">';
                echo '<button type="submit" name="approve_transfer" class="button button-primary">موافقة</button> ';
                echo '<button type="submit" name="reject_transfer" class="button button-secondary">رفض</button>';
                echo '</form>';
            }
            echo ' <a href="' . esc_url(get_edit_post_link($transfer_id)) . '" target="_blank">عرض</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($total_pages > 1) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $paged
            ));
            echo '</div></div>';
        }
    } else {
        echo '<p>لا توجد طلبات تحويل.</p>';
    }

    wp_reset_postdata();
    echo '</div>';
}

/**
 * Admin page: manage tenants bindings
 */
function ms_admin_tenants_page()
{
    if (!current_user_can('manage_options') && !(function_exists('ms_user_has_role') && ms_user_has_role(get_current_user_id(), 'building_manager'))) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $ten_table = $wpdb->prefix . 'ms_unit_tenants';

    // Handle saving new binding
    if (isset($_POST['ms_save_tenant_binding']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ms_save_tenant_binding')) {
        $unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;
        $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
        $tenant_user_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';

        if (!$unit_id || !$building_id || !$tenant_user_id || empty($start_date)) {
            echo '<div class="notice notice-error"><p>الرجاء ملء جميع الحقول المطلوبة.</p></div>';
        } else {
            $new_id = false;
            if (function_exists('ms_link_tenant_to_unit')) {
                $new_id = ms_link_tenant_to_unit($tenant_user_id, $unit_id, $building_id, $start_date);
            } else {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ten_table} WHERE unit_id = %d AND (end_date IS NULL OR end_date = '') LIMIT 1", $unit_id));
                if ($existing) {
                    $wpdb->update($ten_table, array('end_date' => current_time('Y-m-d')), array('id' => intval($existing->id)), array('%s'), array('%d'));
                }
                $inserted = $wpdb->insert($ten_table, array(
                    'building_id' => $building_id,
                    'unit_id' => $unit_id,
                    'tenant_id' => $tenant_user_id,
                    'start_date' => $start_date,
                    'end_date' => null,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                ), array('%d','%d','%d','%s','%s','%s','%s'));
                if ($inserted !== false) {
                    $new_id = intval($wpdb->insert_id);
                }
            }

            if ($new_id) {
                if (function_exists('ms_add_notification')) {
                    ms_add_notification($tenant_user_id, 'tenant_linked', "تم ربطك بالوحدة #{$unit_id} في المبنى.", $building_id, $new_id);
                }
                echo '<div class="notice notice-success"><p>تم حفظ الربط بنجاح.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>فشل حفظ الربط.</p></div>';
            }
        }
    }

    // Handle ending binding
    if (isset($_POST['ms_end_tenant_binding']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ms_end_tenant_binding')) {
        $binding_id = isset($_POST['binding_id']) ? intval($_POST['binding_id']) : 0;
        if ($binding_id) {
            $updated = $wpdb->update($ten_table, array('end_date' => current_time('Y-m-d')), array('id' => $binding_id), array('%s'), array('%d'));
            if ($updated !== false) {
                echo '<div class="notice notice-success"><p>تم إنهاء الربط بنجاح.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>فشل إنهاء الربط.</p></div>';
            }
        }
    }

    // Display page
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;

    // get buildings list
    if ($is_admin) {
        $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager(0) : array();
    } else {
        $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($current_user->ID) : array();
    }

    echo '<div class="wrap"><h1>إدارة المستأجرين</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-tenants">';
    echo '<label style="margin-right:8px">اختر المبنى: </label>';
    echo '<select name="building_id">';
    echo '<option value="0">-- اختر مبنى --</option>';
    foreach ($buildings as $b) {
        $bid = intval($b->id ?? $b->ID ?? 0);
        $title = esc_html($b->title ?? $b->post_title ?? 'Building #' . $bid);
        $sel = ($bid === $building_id) ? ' selected' : '';
        echo '<option value="' . $bid . '"' . $sel . '>' . $title . ' (#' . $bid . ')</option>';
    }
    echo '</select> <button class="button">تصفية</button>';
    echo '</form>';

    if (!$building_id) {
        echo '<p>اختر مبنى لعرض الوحدات.</p>';
        echo '</div>';
        return;
    }

    // units
    $units = function_exists('ms_get_units_by_building') ? ms_get_units_by_building($building_id) : array();
    echo '<table class="widefat fixed striped"><thead><tr><th>الوحدة</th><th>المستأجر الحالي</th><th>تاريخ البداية</th><th>إجراء</th></tr></thead><tbody>';
    $tenant_users = get_users(array(
        'role' => 'tenant',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => array('ID', 'display_name'),
    ));

    if (empty($units)) {
        echo '<tr><td colspan="4">لا توجد وحدات لهذا المبنى.</td></tr>';
    } else {
        foreach ($units as $unit) {
            $unit_id = intval($unit->id ?? $unit->ID ?? 0);
            $binding = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ten_table} WHERE unit_id = %d AND (end_date IS NULL OR end_date = '') LIMIT 1", $unit_id));
            echo '<tr>';
            echo '<td>الوحدة #' . $unit_id . '</td>';
            if ($binding) {
                $tenant_user = get_userdata(intval($binding->tenant_id ?? $binding->tenant_user_id));
                $tenant_name = $tenant_user ? $tenant_user->display_name : ('User #' . intval($binding->tenant_id ?? $binding->tenant_user_id));
                echo '<td>' . esc_html($tenant_name) . '</td>';
                echo '<td>' . esc_html($binding->start_date ?? '') . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('ms_end_tenant_binding');
                echo '<input type="hidden" name="binding_id" value="' . intval($binding->id) . '">';
                echo '<button type="submit" name="ms_end_tenant_binding" class="button button-secondary">إنهاء الربط</button>';
                echo '</form>';
                echo '</td>';
            } else {
                echo '<td>—</td>';
                echo '<td>—</td>';
                echo '<td>';
                echo '<form method="post" style="display:flex;gap:8px;align-items:center">';
                wp_nonce_field('ms_save_tenant_binding');
                echo '<input type="hidden" name="building_id" value="' . intval($building_id) . '">';
                echo '<input type="hidden" name="unit_id" value="' . $unit_id . '">';
                echo '<select name="tenant_id" style="width:180px;padding:6px">';
                echo '<option value="0">-- اختر مستأجراً --</option>';
                foreach ($tenant_users as $tenant_user) {
                    echo '<option value="' . intval($tenant_user->ID) . '">' . esc_html($tenant_user->display_name) . ' (#' . intval($tenant_user->ID) . ')</option>';
                }
                echo '</select>';
                echo '<input type="date" name="start_date" style="padding:6px">';
                echo '<button type="submit" name="ms_save_tenant_binding" class="button button-primary">حفظ الربط</button>';
                echo '</form>';
                echo '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Admin page: improved MS invoices listing with building/unit/date filters
 */
function ms_admin_ms_invoices_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_invoices';
    $building_filter = isset($_GET['ms_building_id']) ? intval($_GET['ms_building_id']) : 0;
    $date_from = isset($_GET['ms_date_from']) ? sanitize_text_field($_GET['ms_date_from']) : '';
    $date_to = isset($_GET['ms_date_to']) ? sanitize_text_field($_GET['ms_date_to']) : '';
    $status_filter = isset($_GET['ms_status']) ? sanitize_text_field($_GET['ms_status']) : '';

    $where = array();
    $params = array();
    if ($building_filter) {
        $where[] = 'building_id = %d';
        $params[] = $building_filter;
    }
    if ($status_filter) {
        $where[] = 'status = %s';
        $params[] = $status_filter;
    }
    if ($date_from && $date_to) {
        $where[] = 'due_date BETWEEN %s AND %s';
        $params[] = $date_from;
        $params[] = $date_to;
    } elseif ($date_from) {
        $where[] = 'due_date >= %s';
        $params[] = $date_from;
    } elseif ($date_to) {
        $where[] = 'due_date <= %s';
        $params[] = $date_to;
    }

    $sql = "SELECT * FROM {$tbl}";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT 500';

    if (!empty($params)) {
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $rows = $wpdb->get_results($sql);
    }

    // fetch buildings list for filter
    $buildings = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");

    echo '<div class="wrap"><h1>الفواتير (MS)</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-ms-invoices">';
    echo '<label style="margin-right:8px">المبنى: </label>';
    echo '<select name="ms_building_id">';
    echo '<option value="0">الكل</option>';
    foreach ($buildings as $b) {
        $sel = ($building_filter && intval($b->id) === $building_filter) ? ' selected' : '';
        echo '<option value="' . intval($b->id) . '"' . $sel . '>' . esc_html($b->title) . ' (#' . intval($b->id) . ')</option>';
    }
    echo '</select> ';
    echo '<label style="margin-left:12px;margin-right:8px">من: </label><input type="date" name="ms_date_from" value="' . esc_attr($date_from) . '">';
    echo '<label style="margin-left:8px;margin-right:8px">إلى: </label><input type="date" name="ms_date_to" value="' . esc_attr($date_to) . '">';
    echo '<label style="margin-left:12px;margin-right:8px">الحالة: </label>';
    echo '<select name="ms_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلقة', 'paid' => 'مدفوع', 'canceled' => 'ملغي');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> ';
    echo '<button class="button">تصفية</button>';
    echo '</form>';

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>المبنى</th><th>الوحدة</th><th>نوع الدافع</th><th>اسم الشخص</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الاستحقاق</th><th>أنشئت بتاريخ</th><th>إجراءات</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="10">لا توجد فواتير.</td></tr>';
    } else {
        foreach ($rows as $invoice) {
            $invoice_id = intval($invoice->id ?? $invoice->ID ?? 0);
            $building_title = $invoice->building_id ? ms_admin_get_building_label(intval($invoice->building_id)) : '—';
            $unit_label = $invoice->unit_id ? 'Unit #' . intval($invoice->unit_id) : '—';
            $payer_type_map = array('owner' => 'مالك','tenant' => 'مستأجر','agent' => 'وسيط','owner_or_tenant' => 'مالك/مستأجر');
            $payer_type = strtolower(trim($invoice->payer_type ?? ''));
            $payer_type_label = $payer_type_map[$payer_type] ?? 'غير معروف';
            $user = get_userdata(intval($invoice->user_id));
            $payer_name = $user ? $user->display_name : esc_html($invoice->payer_name ?? '--');
            $status_norm = strtolower(trim($invoice->status ?? ''));
            $paid_label = ($status_norm === 'paid') ? '<span style="color:#10b981;font-weight:600">مدفوع</span>' : ($status_norm === 'canceled' ? '<span style="color:#ef4444;font-weight:600">ملغي</span>' : esc_html($invoice->status));
            $action = '';
            if ($status_norm === 'pending') {
                $action .= '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_mark_invoice_paid&invoice_id=' . $invoice_id), 'ms_mark_invoice_paid_' . $invoice_id)) . '" class="button button-secondary">تحديد كمدفوع</a>';
                $action .= ' ' . '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_cancel_invoice&invoice_id=' . $invoice_id), 'ms_cancel_invoice_' . $invoice_id)) . '" class="button button-link-delete" onclick="return confirm(\'Are you sure you want to cancel this invoice?\')">إلغاء</a>';
            }

            echo '<tr>';
            echo '<td>' . $invoice_id . '</td>';
            echo '<td>' . esc_html($building_title) . '</td>';
            echo '<td>' . esc_html($unit_label) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($payer_name) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($invoice->amount), 2) . '</td>';
            echo '<td>' . $paid_label . '</td>';
            echo '<td>' . esc_html($invoice->due_date) . '</td>';
            echo '<td>' . esc_html($invoice->created_at ?? '') . '</td>';
            echo '<td>' . $action . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';
}

/**
 * Admin page: improved MS transfers with manager name, document link and rejection note
 */
function ms_admin_ms_transfers_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;

    // handle approve/reject with rejection note and notifications
    if (isset($_POST['ms_transfer_action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ms_ms_transfers_action')) {
        $action = sanitize_text_field($_POST['ms_transfer_action']);
        $transfer_id = isset($_POST['transfer_id']) ? intval($_POST['transfer_id']) : 0;
        if ($transfer_id) {
            if ($action === 'approve') {
                update_post_meta($transfer_id, 'status', 'approved');
                update_post_meta($transfer_id, 'approved_by', get_current_user_id());
                update_post_meta($transfer_id, 'approved_date', current_time('mysql'));
                // notify manager
                $building_id = get_post_meta($transfer_id, 'building_id', true);
                $manager_id = 0;
                if ($building_id) {
                    $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                    if (!$manager_id) {
                        $manager_id = get_post_meta($building_id, 'manager_id', true);
                    }
                }
                if ($manager_id && function_exists('ms_add_notification')) {
                    ms_add_notification(intval($manager_id), 'transfer_approved', 'تمت الموافقة على طلب التحويل المالي الخاص بك.', $building_id, $transfer_id);
                }
            } elseif ($action === 'reject') {
                $note = isset($_POST['rejection_note']) ? sanitize_textarea_field($_POST['rejection_note']) : '';
                update_post_meta($transfer_id, 'status', 'rejected');
                update_post_meta($transfer_id, 'rejected_by', get_current_user_id());
                update_post_meta($transfer_id, 'rejected_date', current_time('mysql'));
                update_post_meta($transfer_id, 'rejection_note', $note);
                // notify manager with note
                $building_id = get_post_meta($transfer_id, 'building_id', true);
                $manager_id = 0;
                if ($building_id) {
                    $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                    if (!$manager_id) {
                        $manager_id = get_post_meta($building_id, 'manager_id', true);
                    }
                }
                if ($manager_id && function_exists('ms_add_notification')) {
                    $msg = 'تم رفض طلب التحويل المالي.' . ($note ? " ملاحظة: {$note}" : '');
                    ms_add_notification(intval($manager_id), 'transfer_rejected', $msg, $building_id, $transfer_id);
                }
            }
        }
    }

    // filters
    $status_filter = isset($_GET['ms_transfer_status']) ? sanitize_text_field($_GET['ms_transfer_status']) : '';
    $args = array('post_type' => 'transfers', 'posts_per_page' => 50, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC');
    if ($status_filter) {
        $args['meta_query'] = array(array('key' => 'status', 'value' => $status_filter, 'compare' => '='));
    }

    $transfers_query = new WP_Query($args);

    echo '<div class="wrap"><h1>التحويلات (MS)</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-ms-transfers">';
    echo '<label style="margin-right:8px">الحالة: </label>';
    echo '<select name="ms_transfer_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلق', 'approved' => 'معتمد', 'rejected' => 'مرفوض');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> <button class="button">تصفية</button>';
    echo '</form>';

    if ($transfers_query->have_posts()) {
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>رقم الطلب</th><th>المبنى</th><th>المدير</th><th>المبلغ</th><th>الحالة</th><th>المستند</th><th>تاريخ الطلب</th><th>الإجراءات</th>';
        echo '</tr></thead><tbody>';

        while ($transfers_query->have_posts()) {
            $transfers_query->the_post();
            $transfer_id = get_the_ID();
            $building_id = get_post_meta($transfer_id, 'building_id', true);
            $amount = get_post_meta($transfer_id, 'amount', true);
            $status = get_post_meta($transfer_id, 'status', true) ?: 'pending';
            // resolve manager id from ms_buildings then post_meta fallback
            $manager_id = 0;
            if ($building_id) {
                $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                if (!$manager_id) {
                    $manager_id = get_post_meta($building_id, 'manager_id', true);
                }
            }
            $manager_name = $manager_id ? (get_userdata(intval($manager_id))->display_name ?? 'User #' . intval($manager_id)) : '—';

            // document link
            $doc_url = '';
            $invoice_file_id = get_post_meta($transfer_id, 'invoice_file', true);
            if ($invoice_file_id) {
                $doc_url = wp_get_attachment_url(intval($invoice_file_id));
            }
            if (!$doc_url) {
                $doc_url = get_post_meta($transfer_id, 'document_url', true) ?: '';
            }
            if (!$doc_url) {
                $att = get_post_meta($transfer_id, 'attachment_id', true);
                if ($att) {
                    $doc_url = wp_get_attachment_url(intval($att));
                }
            }

            $building_label = $building_id ? ms_admin_get_building_label(intval($building_id)) : '—';

            echo '<tr>';
            echo '<td>' . $transfer_id . '</td>';
            echo '<td>' . esc_html($building_label) . '</td>';
            echo '<td>' . esc_html($manager_name) . '</td>';
            echo '<td>' . ($amount ? number_format(floatval($amount), 2) . ' جنيه' : '—') . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . ($doc_url ? '<a href="' . esc_url($doc_url) . '" target="_blank">عرض المستند</a>' : '—') . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>';
            if ($status === 'pending') {
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('ms_ms_transfers_action');
                echo '<input type="hidden" name="transfer_id" value="' . intval($transfer_id) . '">';
                echo '<button type="submit" name="ms_transfer_action" value="approve" class="button button-primary">موافقة</button> ';
                echo '<button type="button" class="button button-secondary" onclick="document.getElementById(\'reject-wrap-' . $transfer_id . '\').style.display=\'block\'">رفض</button>';
                echo '<div id="reject-wrap-' . $transfer_id . '" style="display:none;margin-top:8px">';
                echo '<textarea name="rejection_note" placeholder="ملاحظة الرفض" style="width:100%;min-height:80px"></textarea><br/>';
                echo '<button type="submit" name="ms_transfer_action" value="reject" class="button button-secondary" style="margin-top:6px">تأكيد الرفض</button>';
                echo '</div>';
                echo '</form>';
            }
            echo ' <a href="' . esc_url(get_edit_post_link($transfer_id)) . '" target="_blank">عرض</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>لا توجد طلبات تحويل.</p>';
    }

    wp_reset_postdata();
    echo '</div>';
}

function ms_units_table_exists()
{
    global $wpdb;
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($unit_tbl)));
}

function ms_admin_ensure_ms_units_table()
{
    if (!ms_units_table_exists() && function_exists('ms_create_tables')) {
        ms_create_tables();
    }
    return ms_units_table_exists();
}

function ms_get_property_sync_candidates()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return array();
    }
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return $wpdb->get_results("SELECT * FROM {$unit_tbl} WHERE building_id > 0 ORDER BY id DESC");
}

function ms_get_total_units_count()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return 0;
    }
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return intval($wpdb->get_var("SELECT COUNT(*) FROM {$unit_tbl}"));
}

function ms_get_property_posts_with_building_id()
{
    $args = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'compare' => 'EXISTS',
            ),
        ),
        'fields' => 'all',
    );

    return get_posts($args);
}

function ms_extract_first_meta_id($value)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            $id = ms_extract_first_meta_id($item);
            if ($id) {
                return $id;
            }
        }
        return 0;
    }

    if (is_string($value)) {
        if ($value === '') {
            return 0;
        }

        if (is_serialized($value)) {
            return ms_extract_first_meta_id(maybe_unserialize($value));
        }

        $delimiters = array(',', '|');
        foreach ($delimiters as $delimiter) {
            if (strpos($value, $delimiter) !== false) {
                foreach (explode($delimiter, $value) as $token) {
                    $id = intval(trim($token));
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }

        return intval($value);
    }

    return intval($value);
}

function ms_generate_units_from_properties()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return array(
            'created' => 0,
            'skipped' => 0,
            'report' => array('Cannot generate units because the ms_units table is missing and could not be created.'),
        );
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $properties = ms_get_property_posts_with_building_id();
    $created = 0;
    $skipped = 0;
    $report = array();

    foreach ($properties as $property) {
        $building_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'building_id', true));
        if (!$building_id) {
            $report[] = "Property {$property->ID} skipped: missing building_id.";
            $skipped++;
            continue;
        }

        $owner_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'owner_id', true));
        if (!$owner_id) {
            $owner_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'property_owner', true));
        }
        if (!$owner_id) {
            $owner_id = intval($property->post_author);
        }

        $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'fave_agents', true));
        if (!$agent_id) {
            $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'fave_property_agent', true));
        }
        if (!$agent_id) {
            $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'property_agent', true));
        }

        $tenant_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'tenant_id', true));
        $status = $tenant_id ? 'occupied' : 'available';

        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$unit_tbl} WHERE building_id=%d AND owner_id=%d AND agent_id=%d AND tenant_id=%d", $building_id, $owner_id, $agent_id, $tenant_id));
        if ($existing) {
            $report[] = "Property {$property->ID} skipped: matching unit already exists.";
            $skipped++;
            continue;
        }

        $inserted = $wpdb->insert($unit_tbl, array(
            'building_id' => $building_id,
            'owner_id' => $owner_id,
            'tenant_id' => $tenant_id,
            'agent_id' => $agent_id,
            'status' => $status,
            'created_at' => current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%s'));

        if ($inserted !== false) {
            $created++;
            $report[] = "Created unit from property {$property->ID} (building {$building_id}).";
        } else {
            $error_message = trim($wpdb->last_error);
            $query = trim($wpdb->last_query);
            $report[] = "Property {$property->ID} failed to create unit." . ($error_message ? " DB error: {$error_message}." : '');
            if ($query) {
                $report[] = "Last query: {$query}";
            }
            $skipped++;
        }
    }

    return array('created' => $created, 'skipped' => $skipped, 'report' => $report);
}

function ms_sync_units_to_houzez_properties()
{
    $report = array();
    $updated = 0;
    $skipped = 0;

    if (!ms_admin_ensure_ms_units_table()) {
        return array(
            'updated' => 0,
            'skipped' => 0,
            'report' => array('Cannot sync units because the ms_units table is missing and could not be created.'),
        );
    }

    $units = ms_get_property_sync_candidates();

    if (empty($units)) {
        return array('updated' => 0, 'skipped' => 0, 'report' => array('No unit rows found for sync.'));
    }

    foreach ($units as $unit) {
        $building_id = intval($unit->building_id);
        if (!$building_id) {
            $report[] = "Unit {$unit->id} skipped: missing building_id.";
            $skipped++;
            continue;
        }

        $props = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'building_id',
                    'value' => $building_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
            'fields' => 'all',
        ));

        if (empty($props)) {
            $report[] = "Unit {$unit->id} skipped: no property posts found for building_id {$building_id}.";
            $skipped++;
            continue;
        }

        $property = null;
        if (count($props) === 1) {
            $property = $props[0];
        } else {
            foreach ($props as $p) {
                if ($unit->owner_id && intval($p->post_author) === intval($unit->owner_id)) {
                    $property = $p;
                    break;
                }
                $owner_meta = get_post_meta($p->ID, 'owner_id', true);
                if ($unit->owner_id && intval($owner_meta) === intval($unit->owner_id)) {
                    $property = $p;
                    break;
                }
            }
            if (!$property) {
                $property = $props[0];
            }
        }

        $needs_update = false;
        $property_id = $property->ID;
        $current_owner = get_post_meta($property_id, 'owner_id', true);
        $current_owner_alt = get_post_meta($property_id, 'property_owner', true);
        $current_agent = get_post_meta($property_id, 'fave_agents', true);
        $current_agent_alt = get_post_meta($property_id, 'fave_property_agent', true);
        $current_tenant = get_post_meta($property_id, 'tenant_id', true);

        if ($unit->owner_id && (!$current_owner || intval($current_owner) !== intval($unit->owner_id))) {
            update_post_meta($property_id, 'owner_id', intval($unit->owner_id));
            update_post_meta($property_id, 'property_owner', intval($unit->owner_id));
            $needs_update = true;
        }

        if ($unit->agent_id && (!$current_agent || intval($current_agent) !== intval($unit->agent_id))) {
            update_post_meta($property_id, 'fave_agents', intval($unit->agent_id));
            update_post_meta($property_id, 'fave_property_agent', intval($unit->agent_id));
            $needs_update = true;
        } elseif ($unit->agent_id && !$current_agent_alt) {
            update_post_meta($property_id, 'fave_property_agent', intval($unit->agent_id));
            $needs_update = true;
        }

        if ($unit->tenant_id && (!$current_tenant || intval($current_tenant) !== intval($unit->tenant_id))) {
            update_post_meta($property_id, 'tenant_id', intval($unit->tenant_id));
            $needs_update = true;
        }

        if ($needs_update) {
            $updated++;
            $report[] = "Unit {$unit->id} synced to property {$property_id} (building {$building_id}).";
        } else {
            $skipped++;
            $report[] = "Unit {$unit->id} already in sync for property {$property_id}.";
        }
    }

    return array('updated' => $updated, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrations_page()
{
    $status = '';
    $report = array();

    if (isset($_POST['ms_migrate_legacy_buildings']) && check_admin_referer('ms_migrate_legacy_buildings')) {
        $result = ms_admin_migrate_legacy_buildings();
        $status = sprintf('Migrated %d legacy buildings, skipped %d existing.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_expenses']) && check_admin_referer('ms_migrate_legacy_expenses')) {
        $result = ms_admin_migrate_legacy_expenses();
        $status = sprintf('Migrated %d legacy expenses, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_invoices']) && check_admin_referer('ms_migrate_legacy_invoices')) {
        $result = ms_admin_migrate_legacy_invoices();
        $status = sprintf('Migrated %d legacy invoices, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_transfers']) && check_admin_referer('ms_migrate_legacy_transfers')) {
        $result = ms_admin_migrate_legacy_transfers();
        $status = sprintf('Migrated %d legacy transfers, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_all']) && check_admin_referer('ms_migrate_legacy_all')) {
        $result = ms_admin_migrate_legacy_all();
        $status = sprintf('Migrated %d records across legacy CPTs, skipped %d existing items.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_run_unit_generation']) && check_admin_referer('ms_unit_generation')) {
        $result = ms_generate_units_from_properties();
        $status = sprintf('Created %d units, skipped %d properties.', intval($result['created']), intval($result['skipped']));
        $report = $result['report'];
    }

    $legacy_counts = array(
        'building' => ms_get_legacy_cpt_count('building'),
        'expenses' => ms_get_legacy_cpt_count('expenses'),
        'invoices' => ms_get_legacy_cpt_count('invoices'),
        'transfers' => ms_get_legacy_cpt_count('transfers'),
    );
    $legacy_total = array_sum($legacy_counts);

    $units = ms_get_property_sync_candidates();
    $units_count = count($units);
    $total_units = ms_get_total_units_count();
    $properties_with_building = ms_get_property_posts_with_building_id();
    $properties_with_building_count = count($properties_with_building);
    $properties_count = 0;
    if (post_type_exists('property')) {
        $counts = wp_count_posts('property');
        $properties_count = intval($counts->publish) + intval($counts->pending) + intval($counts->draft);
    }
    $can_generate_units = ($properties_with_building_count > 0);

    echo '<div class="wrap"><h1>Mostaager Migrations</h1>';
    echo '<p>Use this page to migrate legacy Mostager CPT content into the current plugin tables.</p>';

    echo '<div style="margin:16px 0;padding:12px;border:1px solid #ccd0d4;background:#f9f9f9;">';
    echo '<strong>Legacy buildings:</strong> ' . intval($legacy_counts['building']) . '<br>';
    echo '<strong>Legacy expenses:</strong> ' . intval($legacy_counts['expenses']) . '<br>';
    echo '<strong>Legacy invoices:</strong> ' . intval($legacy_counts['invoices']) . '<br>';
    echo '<strong>Legacy transfers:</strong> ' . intval($legacy_counts['transfers']) . '<br>';
    echo '<strong>Current ms_units rows:</strong> ' . intval($total_units) . '<br>';
    echo '<strong>Houzez property posts:</strong> ' . intval($properties_count) . '<br>';
    echo '<strong>Property posts with building_id:</strong> ' . intval($properties_with_building_count) . '</div>';

    if ($legacy_total === 0) {
        echo '<div class="notice notice-info inline"><p>No legacy Mostager CPT records were detected. Use the Houzez property generator below to seed <code>ms_units</code> if needed.</p></div>';
    }

    if ($status) {
        echo '<div class="notice notice-success inline"><p>' . esc_html($status) . '</p></div>';
        if (!empty($report)) {
            echo '<div style="margin:16px 0;padding:12px;border:1px solid #e6e6e6;background:#fff;max-height:320px;overflow:auto;"><strong>Migration log:</strong><ul>';
            foreach ($report as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    echo '<h2>Legacy CPT migration</h2>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>Legacy CPT</th><th>Count</th><th>Action</th>';
    echo '</tr></thead><tbody>';

    $legacy_types = array(
        'building' => 'Buildings',
        'expenses' => 'Expenses',
        'invoices' => 'Invoices',
        'transfers' => 'Transfers',
    );

    foreach ($legacy_types as $type => $label) {
        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td>' . intval($legacy_counts[$type]) . '</td>';
        echo '<td>';
        if ($legacy_counts[$type] > 0) {
            echo '<form method="post" style="display:inline-block;margin:0;">';
            wp_nonce_field('ms_migrate_legacy_' . $type);
            echo '<input type="hidden" name="ms_migrate_legacy_' . esc_attr($type) . '" value="1">';
            echo '<button type="submit" class="button button-secondary">Migrate ' . esc_html($label) . '</button>';
            echo '</form>';
        } else {
            echo 'No legacy records';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '<tr><td colspan="3">';
    echo '<form method="post">';
    wp_nonce_field('ms_migrate_legacy_all');
    echo '<input type="hidden" name="ms_migrate_legacy_all" value="1">';
    echo '<button type="submit" class="button button-primary"' . ($legacy_total === 0 ? ' disabled' : '') . '>Migrate All Legacy CPTs</button>';
    echo '</form>';
    echo '</td></tr>';
    echo '</tbody></table>';

    echo '<h2>Houzez property unit generator</h2>';
    if ($can_generate_units) {
        if ($total_units > 0) {
            echo '<div class="notice notice-warning inline"><p>Existing <code>ms_units</code> rows were detected. This generator will only add missing units for Houzez properties that do not already map to an existing unit.</p></div>';
        }
        $button_label = $total_units === 0 ? 'Seed ms_units from Houzez properties' : 'Generate missing ms_units rows from Houzez properties';
        echo '<form method="post">';
        wp_nonce_field('ms_unit_generation');
        echo '<input type="hidden" name="ms_run_unit_generation" value="1">';
        echo '<p><button type="submit" class="button button-secondary" onclick="return confirm(\'Confirm generation of ms_units rows from Houzez properties?\')">' . esc_html($button_label) . '</button></p>';
        echo '</form>';
    } else {
        echo '<div class="notice notice-warning inline"><p>Generation is unavailable because no Houzez properties are tagged with <code>building_id</code>.</p></div>';
    }

    echo '<h2>Notes</h2>';
    echo '<p>The legacy migration imports <strong>building</strong>, <strong>expenses</strong>, <strong>invoices</strong>, and <strong>transfers</strong> CPTs into the current Mostaager tables.</p>';
    echo '<p>If no legacy CPT records are available, use the Houzez property generator to seed <code>ms_units</code> from existing properties.</p>';
    echo '</div>';
}

function ms_get_legacy_cpt_count($post_type)
{
    global $wpdb;
    return intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s", $post_type, 'auto-draft')));
}

function ms_get_legacy_cpt_posts($post_type)
{
    return get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'all',
    ));
}

function ms_admin_get_legacy_meta_value($post_id, $keys, $default = '')
{
    foreach ((array) $keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if ($value !== '' && $value !== null) {
            return $value;
        }
    }
    return $default;
}

function ms_admin_get_legacy_meta_int($post_id, $keys, $default = 0)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return intval($value);
}

function ms_admin_get_legacy_meta_float($post_id, $keys, $default = 0.0)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return floatval($value);
}

function ms_admin_get_legacy_meta_bool($post_id, $keys, $default = false)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function ms_admin_sanitize_legacy_payer_type($value)
{
    $allowed = array('owner', 'tenant', 'agent', 'owner_or_tenant');
    $value = strtolower(trim($value));
    return in_array($value, $allowed, true) ? $value : 'owner_or_tenant';
}

function ms_admin_get_legacy_invoice_user_id($property_id, $building_id)
{
    if ($property_id) {
        $contacts = ms_get_property_contacts($property_id);
        if (!empty($contacts['tenant'])) {
            return intval($contacts['tenant']->ID);
        }
        if (!empty($contacts['owner'])) {
            return intval($contacts['owner']->ID);
        }
    }

    if ($building_id) {
        $properties = ms_get_properties_by_building($building_id);
        if (!empty($properties)) {
            $property = reset($properties);
            $contacts = ms_get_property_contacts($property->ID);
            if (!empty($contacts['tenant'])) {
                return intval($contacts['tenant']->ID);
            }
            if (!empty($contacts['owner'])) {
                return intval($contacts['owner']->ID);
            }
        }
    }

    return 0;
}

function ms_admin_migrate_legacy_buildings()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('building');
    $tbl = $wpdb->prefix . 'ms_buildings';

    foreach ($posts as $post) {
        $manager_id = ms_admin_get_legacy_meta_int($post->ID, array('manager_id', 'building_manager', 'manager'));
        if (!$manager_id) {
            $manager_id = intval($post->post_author);
        }
        $title = sanitize_text_field($post->post_title ?: 'Building #' . $post->ID);
        $exists = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE title = %s AND manager_id = %d", $title, $manager_id)));
        if ($exists) {
            $skipped++;
            $report[] = "Skipped legacy building {$post->ID}: already migrated.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'title' => $title,
            'manager_id' => $manager_id,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%s', '%d', '%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy building {$post->ID} as building row.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy building {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_expenses()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('expenses');
    $tbl = $wpdb->prefix . 'ms_maintenance_requests';

    foreach ($posts as $post) {
        $building_id = ms_admin_get_legacy_meta_int($post->ID, array('building_id', 'property_id', 'building'));
        if (!$building_id) {
            $report[] = "Skipped legacy expense {$post->ID}: missing building_id.";
            $skipped++;
            continue;
        }

        $title = sanitize_text_field($post->post_title ?: 'Expense #' . $post->ID);
        $description = $post->post_content ?: ms_admin_get_legacy_meta_value($post->ID, array('description', 'desc', 'details'));
        $cost = ms_admin_get_legacy_meta_float($post->ID, array('total_amount', 'amount_due', 'amount', 'cost'), 0);
        if ($cost <= 0) {
            $report[] = "Skipped legacy expense {$post->ID}: invalid cost.";
            $skipped++;
            continue;
        }

        $maintenance_type = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('maintenance_type', 'expense_type', 'type'), 'emergency'));
        $allowed_types = array('monthly', 'emergency', 'capital');
        if (!in_array($maintenance_type, $allowed_types, true)) {
            $maintenance_type = 'emergency';
        }

        $status = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('status'), 'open'));
        if ($status !== 'open' && $status !== 'closed' && $status !== 'completed') {
            $status = 'open';
        }
        if ($status === 'completed') {
            $status = 'closed';
        }

        $is_recurring = ms_admin_get_legacy_meta_bool($post->ID, array('is_recurring', 'recurring', 'repeat'), false) ? 1 : 0;
        $recurrence_day = ms_admin_get_legacy_meta_int($post->ID, array('recurrence_day'), 1);
        if ($recurrence_day < 1 || $recurrence_day > 28) {
            $recurrence_day = 1;
        }

        $manager_id = ms_admin_get_legacy_meta_int($post->ID, array('manager_id', 'created_by', 'author'));
        if (!$manager_id) {
            $manager_id = intval($post->post_author);
        }

        $payer_type = ms_admin_sanitize_legacy_payer_type(ms_admin_get_legacy_meta_value($post->ID, array('payer_type', 'responsible', 'payer', 'pay_type'), 'owner_or_tenant'));
        $start_date = ms_admin_get_legacy_meta_value($post->ID, array('start_date', 'date_created'), null);
        $due_date = ms_admin_get_legacy_meta_value($post->ID, array('due_date', 'date_due', 'due_date'), null);

        $existing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tbl WHERE building_id = %d AND title = %s AND cost = %f AND due_date <=> %s",
            $building_id,
            $title,
            $cost,
            $due_date
        )));

        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy expense {$post->ID}: matching maintenance request already exists.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'building_id' => $building_id,
            'unit_id' => ms_admin_get_legacy_meta_int($post->ID, array('unit_id'), 0),
            'title' => $title,
            'description' => $description,
            'cost' => $cost,
            'status' => $status,
            'priority' => ms_admin_get_legacy_meta_value($post->ID, array('priority'), 'medium'),
            'maintenance_type' => $maintenance_type,
            'is_recurring' => $is_recurring,
            'recurrence_day' => $recurrence_day,
            'manager_id' => $manager_id,
            'payer_type' => $payer_type,
            'start_date' => $start_date ?: date('Y-m-d', strtotime($post->post_date)),
            'due_date' => $due_date,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%d','%d','%s','%s','%f','%s','%s','%d','%d','%d','%s','%s','%s','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy expense {$post->ID} as maintenance request.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy expense {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_invoices()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('invoices');
    $tbl = $wpdb->prefix . 'ms_invoices';

    foreach ($posts as $post) {
        $property_id = ms_admin_get_legacy_meta_int($post->ID, array('property_id'));
        $building_id = ms_admin_get_legacy_meta_int($post->ID, array('building_id'));
        $amount = ms_admin_get_legacy_meta_float($post->ID, array('amount_due', 'total_amount', 'amount', 'due_amount'), 0);
        if ($amount <= 0) {
            $report[] = "Skipped legacy invoice {$post->ID}: invalid amount.";
            $skipped++;
            continue;
        }

        $status = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('status'), 'pending'));
        if ($status === 'completed') {
            $status = 'paid';
        }
        if (!in_array($status, array('pending', 'paid', 'overdue', 'cancelled', 'failed'), true)) {
            $status = 'pending';
        }

        $payer_type = ms_admin_sanitize_legacy_payer_type(ms_admin_get_legacy_meta_value($post->ID, array('payer_type', 'pay_type', 'responsible'), 'owner_or_tenant'));
        $invoice_type = ms_admin_get_legacy_meta_value($post->ID, array('invoice_type', 'type'), 'legacy');
        $description = $post->post_content ?: ms_admin_get_legacy_meta_value($post->ID, array('description', 'desc', 'details'), $post->post_title ?: 'Invoice #' . $post->ID);
        $due_date = ms_admin_get_legacy_meta_value($post->ID, array('due_date', 'date_due', 'paid_date'), null);
        $user_id = ms_admin_get_legacy_invoice_user_id($property_id, $building_id);

        $existing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tbl WHERE building_id = %d AND amount = %f AND due_date <=> %s AND invoice_type = %s",
            $building_id,
            $amount,
            $due_date,
            $invoice_type
        )));

        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy invoice {$post->ID}: matching invoice already exists.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'user_id' => max(0, $user_id),
            'building_id' => $building_id,
            'unit_id' => ms_admin_get_legacy_meta_int($post->ID, array('unit_id'), 0),
            'expense_id' => 0,
            'description' => $description,
            'amount' => $amount,
            'status' => $status,
            'due_date' => $due_date,
            'invoice_type' => $invoice_type,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
            'payer_type' => $payer_type,
        ), array('%d','%d','%d','%d','%s','%f','%s','%s','%s','%s','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy invoice {$post->ID} as ms_invoices record.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy invoice {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_transfers()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('transfers');
    $tbl = $wpdb->prefix . 'ms_wallet_transactions';

    foreach ($posts as $post) {
        $user_id = ms_admin_get_legacy_meta_int($post->ID, array('user_id', 'participant_id', 'from_user', 'to_user'));
        $amount = ms_admin_get_legacy_meta_float($post->ID, array('amount', 'transfer_amount', 'total_amount'), 0);
        if ($amount <= 0) {
            $report[] = "Skipped legacy transfer {$post->ID}: invalid amount.";
            $skipped++;
            continue;
        }

        $type = ms_admin_get_legacy_meta_value($post->ID, array('type', 'transfer_type'), 'transfer');
        $meta = array(
            'legacy_post_id' => $post->ID,
            'legacy_post_type' => 'transfers',
        );

        $existing = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE reference_id = %d", $post->ID)));
        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy transfer {$post->ID}: already migrated.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'user_id' => max(0, $user_id),
            'type' => $type,
            'amount' => $amount,
            'meta' => maybe_serialize($meta),
            'reference_id' => $post->ID,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%d','%s','%f','%s','%d','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy transfer {$post->ID} as wallet transaction.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy transfer {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_all()
{
    $report = array();
    $migrated = 0;
    $skipped = 0;

    $building_result = ms_admin_migrate_legacy_buildings();
    $expense_result = ms_admin_migrate_legacy_expenses();
    $invoice_result = ms_admin_migrate_legacy_invoices();
    $transfer_result = ms_admin_migrate_legacy_transfers();

    $report = array_merge($building_result['report'], $expense_result['report'], $invoice_result['report'], $transfer_result['report']);
    $migrated = intval($building_result['migrated']) + intval($expense_result['migrated']) + intval($invoice_result['migrated']) + intval($transfer_result['migrated']);
    $skipped = intval($building_result['skipped']) + intval($expense_result['skipped']) + intval($invoice_result['skipped']) + intval($transfer_result['skipped']);

    return array('migrated' => $migrated, 'skipped' => $skipped, 'report' => $report);
}

// Add property owner meta box to Houzez property edit screen
add_action('add_meta_boxes', function() {
    add_meta_box('ms_property_owner_box', 'Mostaager Property Owner', function($post) {
        wp_nonce_field('ms_property_owner_meta', 'ms_property_owner_meta_nonce');
        $current = get_post_meta($post->ID, 'ms_property_owner_id', true);
        $users = get_users(array('role__in' => array('owner', 'administrator', 'editor'), 'orderby' => 'display_name'));
        echo '<p><label for="ms_property_owner_id">تعيين مالك العقار (Mostaager)</label></p>';
        echo '<select name="ms_property_owner_id" id="ms_property_owner_id" style="width:100%;padding:8px;">';
        echo '<option value="">-- لا شيء --</option>';
        foreach ($users as $u) {
            printf('<option value="%d" %s>%s</option>', $u->ID, selected($u->ID, $current, false), esc_html($u->display_name));
        }
        echo '</select>';
    }, 'property', 'side', 'low');
});

// Save ms_property_owner_id when property saved
add_action('save_post_property', function($post_id, $post, $update) {
    if (!isset($_POST['ms_property_owner_meta_nonce']) || !wp_verify_nonce($_POST['ms_property_owner_meta_nonce'], 'ms_property_owner_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['ms_property_owner_id'])) {
        $val = intval($_POST['ms_property_owner_id']);
        if ($val > 0) {
            update_post_meta($post_id, 'ms_property_owner_id', $val);
        } else {
            delete_post_meta($post_id, 'ms_property_owner_id');
        }
    }
}, 10, 3);

add_action('admin_post_ms_delete_item', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('ms_delete_item');

    $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if (!$id || !$type) wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=mostaager-admin'));

    global $wpdb;
    $mapping = array(
        'building' => $wpdb->prefix . 'ms_buildings',
        'unit' => $wpdb->prefix . 'ms_units',
        'invoice' => $wpdb->prefix . 'ms_invoices',
        'maintenance' => $wpdb->prefix . 'ms_maintenance_requests',
        'transfer' => $wpdb->prefix . 'ms_wallet_transactions',
    );

    if (!isset($mapping[$type])) wp_die('Invalid type');

    $tbl = $mapping[$type];
    $wpdb->delete($tbl, array('id' => $id), array('%d'));

    wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=mostaager-admin'));
    exit;
});

