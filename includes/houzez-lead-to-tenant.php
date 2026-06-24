<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ms_add_lead_convert_metabox');
add_action('admin_enqueue_scripts', 'ms_enqueue_lead_to_tenant_assets');

function ms_add_lead_convert_metabox()
{
    $post_types = array('houzez_crm', 'houzez_lead');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'ms_houzez_lead_to_tenant',
            'تحويل Lead إلى مستأجر',
            'ms_render_lead_to_tenant_metabox',
            $post_type,
            'side',
            'high'
        );
    }
}

function ms_render_lead_to_tenant_metabox($post)
{
    $converted = get_post_meta($post->ID, 'ms_lead_converted', true);
    $tenant_user_id = intval(get_post_meta($post->ID, 'ms_tenant_user_id', true));
    $lead_name = sanitize_text_field(get_post_meta($post->ID, 'lead_name', true));
    $lead_email = sanitize_email(get_post_meta($post->ID, 'lead_email', true));
    $lead_phone = sanitize_text_field(get_post_meta($post->ID, 'lead_phone', true));
    $property_id = intval(get_post_meta($post->ID, 'fave_property_id', true)) ?: intval(get_post_meta($post->ID, 'property_id', true));

    if (empty($lead_name)) {
        $lead_name = sanitize_text_field($post->post_title ?: 'Lead');
    }

    echo '<p><strong>الاسم:</strong> ' . esc_html($lead_name) . '</p>';
    echo '<p><strong>البريد الإلكتروني:</strong> ' . esc_html($lead_email) . '</p>';
    echo '<p><strong>الهاتف:</strong> ' . esc_html($lead_phone) . '</p>';
    echo '<p><strong>العقار:</strong> ' . esc_html($property_id ? $property_id : 'غير محدد') . '</p>';

    if ($converted && $tenant_user_id) {
        $tenant_link = get_edit_user_link($tenant_user_id);
        echo '<p style="margin-top:12px;color:#0f172a;">تم تحويل هذا الـ Lead إلى مستأجر.</p>';
        if ($tenant_link) {
            echo '<p><a class="button button-primary" href="' . esc_url($tenant_link) . '" target="_blank">عرض ملف المستأجر</a></p>';
        }
        return;
    }

    echo '<p style="margin-top:12px;"><button type="button" class="button button-primary ms-convert-lead-button" data-lead-id="' . esc_attr($post->ID) . '" data-property-id="' . esc_attr($property_id) . '" data-security="' . esc_attr(wp_create_nonce('mostaager-ajax-nonce')) . '">تحويل إلى مستأجر في Mostaager</button></p>';
}

function ms_enqueue_lead_to_tenant_assets($hook)
{
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, array('houzez_crm', 'houzez_lead'), true)) {
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
        'nonce' => wp_create_nonce('mostaager-ajax-nonce'),
        'messages' => array(
            'confirm' => 'هل أنت متأكد من تحويل هذا الـ Lead إلى مستأجر؟',
            'sending' => 'جاري التحويل...',
            'success' => 'تم تحويل الـ Lead بنجاح.',
            'error' => 'حدث خطأ أثناء التحويل.',
        ),
    ));
}
