<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ms_register_houzez_property_building_metabox');
add_action('save_post_property', 'ms_save_property_building_link', 10, 2);
add_action('admin_notices', 'ms_show_houzez_building_link_notice');

function ms_register_houzez_property_building_metabox()
{
    add_meta_box(
        'ms_houzez_property_building_link',
        '🔗 ربط بـ Mostaager',
        'ms_render_houzez_property_building_metabox',
        'property',
        'side',
        'high'
    );
}

function ms_render_houzez_property_building_metabox($post)
{
    global $wpdb;

    $selected_building_id = intval(get_post_meta($post->ID, 'building_id', true));
    $pending_supervisor_name = sanitize_text_field(get_post_meta($post->ID, 'ms_pending_supervisor_name', true));
    $pending_supervisor_phone = sanitize_text_field(get_post_meta($post->ID, 'ms_pending_supervisor_phone', true));

    $buildings = array();
    if (function_exists('ms_get_buildings_by_manager')) {
        $buildings = ms_get_buildings_by_manager(get_current_user_id());
    }

    if (!is_array($buildings) || empty($buildings)) {
        $buildings = $wpdb->get_results(
            "SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC"
        );
    }

    wp_nonce_field('ms_building_link_nonce', 'ms_building_link_nonce');

    echo '<p>حدد مبنى موجوداً أو أنشئ مبنى جديداً مرتبطاً بهذا العقار.</p>';
    echo '<select id="ms_building_id_select" name="ms_building_id" style="width:100%;padding:8px;border:1px solid #ccd0d4;border-radius:5px;">';
    echo '<option value="">-- اختر مبنى --</option>';
    foreach ((array) $buildings as $building) {
        $building_id = intval($building->id ?? $building->ID ?? 0);
        $building_title = sanitize_text_field($building->title ?? $building->name ?? '');
        if (!$building_id) {
            continue;
        }
        printf(
            '<option value="%d" %s>%s</option>',
            $building_id,
            selected($selected_building_id, $building_id, false),
            esc_html($building_title)
        );
    }
    echo '<option value="new_building" ' . selected($selected_building_id, 'new_building', false) . '>مبنى جديد</option>';
    echo '</select>';

    echo '<div id="ms_new_building_fields" style="margin-top:12px;display:' . ($selected_building_id === 0 || $selected_building_id === 'new_building' ? 'block' : 'none') . ';">';
    echo '<p><label for="ms_new_building_title">اسم المبنى</label><br><input type="text" id="ms_new_building_title" name="ms_new_building_title" value="' . esc_attr(get_post_meta($post->ID, 'ms_new_building_title', true)) . '" style="width:100%;padding:8px;border:1px solid #ccd0d4;border-radius:5px;"></p>';
    echo '<p><label for="ms_new_building_supervisor_name">اسم مشرف المبنى</label><br><input type="text" id="ms_new_building_supervisor_name" name="ms_new_building_supervisor_name" value="' . esc_attr($pending_supervisor_name) . '" style="width:100%;padding:8px;border:1px solid #ccd0d4;border-radius:5px;"></p>';
    echo '<p><label for="ms_new_building_supervisor_phone">رقم جوال المشرف</label><br><input type="text" id="ms_new_building_supervisor_phone" name="ms_new_building_supervisor_phone" value="' . esc_attr($pending_supervisor_phone) . '" style="width:100%;padding:8px;border:1px solid #ccd0d4;border-radius:5px;"></p>';
    echo '</div>';

    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var select = document.getElementById("ms_building_id_select");
            var fields = document.getElementById("ms_new_building_fields");
            if (!select || !fields) {
                return;
            }
            select.addEventListener("change", function () {
                if (select.value === "new_building") {
                    fields.style.display = "block";
                } else {
                    fields.style.display = "none";
                }
            });
        });
    </script>';
}

function ms_save_property_building_link($post_id, $post)
{
    if (!isset($_POST['ms_building_link_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['ms_building_link_nonce']), 'ms_building_link_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $building_id = sanitize_text_field($_POST['ms_building_id'] ?? '');
    if ($building_id === 'new_building') {
        global $wpdb;

        $new_building_title = sanitize_text_field($_POST['ms_new_building_title'] ?? '');
        $supervisor_name = sanitize_text_field($_POST['ms_new_building_supervisor_name'] ?? '');
        $supervisor_phone = sanitize_text_field($_POST['ms_new_building_supervisor_phone'] ?? '');
        if (empty($new_building_title)) {
            $new_building_title = sanitize_text_field(get_the_title($post_id) ?: 'مبنى جديد');
        }

        $table = $wpdb->prefix . 'ms_buildings';
        $inserted = $wpdb->insert(
            $table,
            array(
                'company_id' => 1,
                'title' => $new_building_title,
                'manager_id' => 0,
                'wp_post_id' => $post_id,
                'status' => 'incomplete',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%d', '%s', '%s')
        );

        if ($inserted !== false) {
            $building_id = intval($wpdb->insert_id);
            update_post_meta($post_id, 'building_id', $building_id);
            update_post_meta($post_id, 'ms_pending_supervisor_name', $supervisor_name);
            update_post_meta($post_id, 'ms_pending_supervisor_phone', $supervisor_phone);
            update_post_meta($post_id, 'ms_new_building_title', $new_building_title);

            if (function_exists('ms_add_notification')) {
                $admins = get_users(array('role__in' => array('administrator'), 'number' => -1));
                $message = sprintf('تم إنشاء مبنى جديد غير مكتمل بواسطة %s لخاصية %s', wp_get_current_user()->display_name, get_the_title($post_id));
                foreach ($admins as $admin) {
                    ms_add_notification(intval($admin->ID), 'new_building_incomplete', $message, $building_id, $post_id);
                }
            }

            if (class_exists('Mostager_WhatsApp_Integration')) {
                $whatsapp = new Mostager_WhatsApp_Integration();
                if (method_exists($whatsapp, 'send_template') && !empty($supervisor_phone)) {
                    $registration_link = home_url('/register/');
                    $whatsapp->send_template($supervisor_phone, 'supervisor_building_invite', array(
                        $supervisor_name ?: esc_html__('مشرف المبنى', 'mostaager-facility-pro'),
                        $new_building_title,
                        $registration_link,
                    ));
                }
            }

            set_transient('ms_houzez_building_link_notice_' . get_current_user_id(), __('تم إنشاء مبنى جديد في Mostaager بنجاح. سيتابع المسئولون إكمال البيانات.', 'mostaager-facility-pro'), 30);
        }
    } else {
        $building_id = intval($building_id);
        if ($building_id > 0) {
            update_post_meta($post_id, 'building_id', $building_id);
        }
    }
}

function ms_show_houzez_building_link_notice()
{
    if (!is_admin()) {
        return;
    }

    $notice_key = 'ms_houzez_building_link_notice_' . get_current_user_id();
    $message = get_transient($notice_key);
    if (!$message) {
        return;
    }

    delete_transient($notice_key);
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
}
