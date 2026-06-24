<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ms_register_houzez_lead_conversion_metabox');
add_action('admin_enqueue_scripts', 'ms_enqueue_houzez_lead_convert_assets');
add_action('wp_ajax_ms_convert_lead_to_tenant', 'ms_ajax_convert_lead_to_tenant');

function ms_register_houzez_lead_conversion_metabox()
{
    $post_types = array('houzez_crm', 'fave_property_inquiry');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'ms_houzez_lead_conversion',
            'تحويل Lead إلى مستأجر',
            'ms_render_houzez_lead_conversion_metabox',
            $post_type,
            'side',
            'high'
        );
    }
}

function ms_render_houzez_lead_conversion_metabox($post)
{
    $converted_tenant_id = intval(get_post_meta($post->ID, 'ms_converted_tenant_id', true));
    $status = sanitize_text_field(get_post_meta($post->ID, 'ms_lead_status', true));
    $lead_name = sanitize_text_field(get_post_meta($post->ID, 'lead_name', true));
    $lead_email = sanitize_email(get_post_meta($post->ID, 'lead_email', true));
    $lead_phone = sanitize_text_field(get_post_meta($post->ID, 'lead_phone', true));
    $property_id = intval(get_post_meta($post->ID, 'property_id', true)) ?: intval(get_post_meta($post->ID, 'fave_property_id', true));

    if (empty($lead_name)) {
        $lead_name = $post->post_title ?: 'Lead';
    }

    wp_nonce_field('ms_houzez_lead_convert', 'ms_houzez_lead_convert_nonce');

    if ($converted_tenant_id) {
        $tenant_link = get_edit_user_link($converted_tenant_id);
        echo '<p>تم التحويل بالفعل إلى مستأجر.</p>';
        if ($tenant_link) {
            echo '<p><a href="' . esc_url($tenant_link) . '" target="_blank" class="button button-primary">عرض ملف المستأجر</a></p>';
        }
        echo '<p><span class="dashicons dashicons-yes" style="color:#10b981;margin-right:6px;"></span> تم التحويل</p>';
        return;
    }

    echo '<p>الاسم: <strong>' . esc_html($lead_name) . '</strong></p>';
    echo '<p>البريد الإلكتروني: <strong>' . esc_html($lead_email) . '</strong></p>';
    echo '<p>الهاتف: <strong>' . esc_html($lead_phone) . '</strong></p>';
    echo '<p>العقار المرتبط: <strong>' . esc_html($property_id ? $property_id : 'غير محدد') . '</strong></p>';
    echo '<p><button type="button" class="button button-primary ms-convert-lead-button" data-lead-id="' . esc_attr($post->ID) . '" data-property-id="' . esc_attr($property_id) . '" data-security="' . esc_attr(wp_create_nonce('ms_houzez_lead_convert')) . '">تحويل إلى مستأجر في Mostaager</button></p>';
}

function ms_enqueue_houzez_lead_convert_assets($hook)
{
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, array('houzez_crm', 'fave_property_inquiry'), true)) {
        return;
    }

    $js_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/lead-convert.js';
    if (!file_exists($js_path)) {
        return;
    }

    wp_enqueue_script(
        'ms-lead-convert',
        MS_PLUGIN_URL . 'assets/js/lead-convert.js',
        array('jquery'),
        filemtime($js_path),
        true
    );

    wp_localize_script('ms-lead-convert', 'msLeadConvert', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'dashboard_url' => home_url('/'),
        'messages' => array(
            'confirm' => 'هل أنت متأكد من تحويل هذا الـ Lead إلى مستأجر؟',
            'sending' => 'جاري التحويل...',
            'success' => 'تم تحويل الـ Lead بنجاح.',
            'error' => 'حدث خطأ أثناء التحويل.',
        ),
    ));
}

function ms_ajax_convert_lead_to_tenant()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'غير مصرح'), 401);
    }

    if (empty($_POST['security']) || !wp_verify_nonce(sanitize_text_field($_POST['security']), 'ms_houzez_lead_convert')) {
        wp_send_json_error(array('message' => 'فشل التحقق'), 403);
    }

    $user_id = get_current_user_id();
    $lead_id = intval($_POST['lead_id'] ?? 0);
    if (!$lead_id) {
        wp_send_json_error(array('message' => 'معرف Lead غير صالح'), 400);
    }

    if (!current_user_can('manage_options') && !(function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'agent'))) {
        wp_send_json_error(array('message' => 'غير مصرح'), 403);
    }

    $lead_post = get_post($lead_id);
    if (!$lead_post) {
        wp_send_json_error(array('message' => 'Lead غير موجود'), 404);
    }

    $lead_name = sanitize_text_field(get_post_meta($lead_id, 'lead_name', true));
    $lead_email = sanitize_email(get_post_meta($lead_id, 'lead_email', true));
    $lead_phone = sanitize_text_field(get_post_meta($lead_id, 'lead_phone', true));
    $property_id = intval(get_post_meta($lead_id, 'property_id', true)) ?: intval(get_post_meta($lead_id, 'fave_property_id', true));

    if (empty($lead_name)) {
        $lead_name = $lead_post->post_title ?: 'Lead';
    }

    if (empty($lead_email)) {
        wp_send_json_error(array('message' => 'البريد الإلكتروني مطلوب'), 400);
    }

    $existing_user = get_user_by('email', $lead_email);
    if ($existing_user) {
        $tenant_id = intval($existing_user->ID);
    } else {
        $username = sanitize_user(current(explode('@', $lead_email)), true);
        if (empty($username)) {
            $username = 'tenant_' . time();
        }
        $password = wp_generate_password(12, false);
        $tenant_id = wp_create_user($username, $password, $lead_email);
        if (is_wp_error($tenant_id)) {
            wp_send_json_error(array('message' => 'فشل إنشاء مستخدم المستأجر'), 500);
        }
    }

    wp_update_user(array(
        'ID' => $tenant_id,
        'role' => 'tenant',
    ));
    if (!empty($lead_phone)) {
        update_user_meta($tenant_id, 'billing_phone', $lead_phone);
        update_user_meta($tenant_id, 'phone', $lead_phone);
    }

    $building_id = 0;
    $unit_id = 0;
    if ($property_id) {
        $building_id = intval(get_post_meta($property_id, 'building_id', true));
        $unit_meta = intval(get_post_meta($property_id, 'unit_id', true));
        if ($unit_meta) {
            $unit_id = $unit_meta;
        } elseif ($building_id && function_exists('ms_get_units_by_building')) {
            $units = ms_get_units_by_building($building_id);
            if (!empty($units)) {
                $unit_id = intval($units[0]->id ?? $units[0]->ID ?? 0);
            }
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_unit_tenants';
    $inserted = $wpdb->insert(
        $table,
        array(
            'company_id' => 1,
            'unit_id' => $unit_id,
            'tenant_id' => $tenant_id,
            'building_id' => $building_id,
            'start_date' => current_time('Y-m-d'),
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%d', '%d', '%s', '%s', '%s')
    );

    if ($inserted === false) {
        wp_send_json_error(array('message' => 'تعذر حفظ الربط في ms_unit_tenants'), 500);
    }

    update_post_meta($lead_id, 'ms_converted_tenant_id', $tenant_id);
    update_post_meta($lead_id, 'ms_lead_status', 'converted');

    if (class_exists('Mostager_WhatsApp_Integration')) {
        $whatsapp = new Mostager_WhatsApp_Integration();
        if (method_exists($whatsapp, 'send_welcome') && !empty($lead_phone)) {
            $whatsapp->send_welcome($lead_phone, array(
                'name' => $lead_name,
                'email' => $lead_email,
                'property_id' => $property_id,
                'tenant_id' => $tenant_id,
            ));
        }
    }

    wp_send_json_success(array(
        'tenant_id' => $tenant_id,
        'lead_id' => $lead_id,
        'unit_id' => $unit_id,
    ));
}
