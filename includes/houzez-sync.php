<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ms_register_houzez_property_sync_metabox');
add_action('save_post_property', 'ms_save_houzez_property_sync_metabox', 10, 3);
add_action('admin_notices', 'ms_houzez_sync_admin_notice');

function ms_register_houzez_property_sync_metabox()
{
    add_meta_box(
        'ms_houzez_property_sync',
        'ربط العقار بـ Mostaager',
        'ms_render_houzez_property_sync_metabox',
        'property',
        'side',
        'high'
    );
}

function ms_render_houzez_property_sync_metabox($post)
{
    global $wpdb;

    $selected_building_id = intval(get_post_meta($post->ID, 'ms_building_id', true));
    $supervisor_name = sanitize_text_field(get_post_meta($post->ID, 'ms_sync_supervisor_name', true));
    $supervisor_phone = sanitize_text_field(get_post_meta($post->ID, 'ms_sync_supervisor_phone', true));

    $rows = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");

    wp_nonce_field('ms_houzez_property_sync', 'ms_houzez_property_sync_nonce');
    ?>
    <p>اختر مبنى موجوداً من قاعدة Mostaager أو أنشئ مبنى جديد مرتبطاً بهذا العقار.</p>
    <select name="ms_building_id" id="ms_houzez_building_id" style="width:100%;padding:8px;border:1px solid #ccd0d4;border-radius:4px;">
        <option value="0">-- اختر مبنى --</option>
        <?php foreach ((array)$rows as $row): ?>
            <option value="<?php echo intval($row->id); ?>" <?php selected($selected_building_id, intval($row->id)); ?>><?php echo esc_html($row->title); ?></option>
        <?php endforeach; ?>
        <option value="new" <?php selected($selected_building_id, 'new'); ?>>أنشئ مبنى جديد</option>
    </select>

    <div id="ms_houzez_new_building_fields" style="margin-top:12px;display:<?php echo $selected_building_id === 0 || $selected_building_id !== 'new' ? 'none' : 'block'; ?>;">
        <p><strong>إنشاء مبنى جديد</strong></p>
        <label for="ms_sync_supervisor_name">اسم المشرف</label>
        <input type="text" id="ms_sync_supervisor_name" name="ms_sync_supervisor_name" value="<?php echo esc_attr($supervisor_name); ?>" style="width:100%;padding:8px;margin-top:4px;border:1px solid #ccd0d4;border-radius:4px;">
        <label for="ms_sync_supervisor_phone" style="margin-top:10px;display:block;">رقم الجوال</label>
        <input type="text" id="ms_sync_supervisor_phone" name="ms_sync_supervisor_phone" value="<?php echo esc_attr($supervisor_phone); ?>" style="width:100%;padding:8px;margin-top:4px;border:1px solid #ccd0d4;border-radius:4px;">
    </div>

    <script>
    (function () {
        var selector = document.getElementById('ms_houzez_building_id');
        var fields = document.getElementById('ms_houzez_new_building_fields');
        if (!selector || !fields) {
            return;
        }
        function toggleFields() {
            fields.style.display = selector.value === 'new' ? 'block' : 'none';
        }
        selector.addEventListener('change', toggleFields);
        toggleFields();
    })();
    </script>
    <?php
}

function ms_save_houzez_property_sync_metabox($post_id, $post, $update)
{
    if (!isset($_POST['ms_houzez_property_sync_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['ms_houzez_property_sync_nonce']), 'ms_houzez_property_sync')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ($post->post_type !== 'property') {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $building_id = isset($_POST['ms_building_id']) ? sanitize_text_field($_POST['ms_building_id']) : '';
    if ($building_id === 'new') {
        $supervisor_name = isset($_POST['ms_sync_supervisor_name']) ? sanitize_text_field($_POST['ms_sync_supervisor_name']) : '';
        $supervisor_phone = isset($_POST['ms_sync_supervisor_phone']) ? sanitize_text_field($_POST['ms_sync_supervisor_phone']) : '';

        update_post_meta($post_id, 'ms_sync_supervisor_name', $supervisor_name);
        update_post_meta($post_id, 'ms_sync_supervisor_phone', $supervisor_phone);

        global $wpdb;
        $table = $wpdb->prefix . 'ms_buildings';
        $inserted = $wpdb->insert(
            $table,
            array(
                'company_id' => 1,
                'title' => get_the_title($post_id),
                'wp_post_id' => $post_id,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s')
        );

        if ($inserted !== false) {
            $new_building_id = intval($wpdb->insert_id);
            update_post_meta($post_id, 'ms_building_id', $new_building_id);
            set_transient('ms_sync_notice_' . $post_id, 'تم ربط العقار بمبنى جديد في Mostaager بنجاح.', 30);

            if (function_exists('ms_add_notification')) {
                ms_add_notification(get_current_user_id(), 'houzez_property_sync', sprintf('تم إنشاء مبنى جديد من العقار %s.', get_the_title($post_id)), $new_building_id, 0);
            }

            if (!empty($supervisor_phone) && class_exists('Mostager_WhatsApp_Integration')) {
                $whatsapp = new Mostager_WhatsApp_Integration();
                if (method_exists($whatsapp, 'send_template')) {
                    $registration_url = function_exists('wp_registration_url') ? wp_registration_url() : home_url('/wp-login.php?action=register');
                    $whatsapp->send_template($supervisor_phone, 'houzez_new_building', array(
                        'supervisor_name' => $supervisor_name,
                        'property_title' => get_the_title($post_id),
                        'register_url' => $registration_url,
                    ));
                }
            }
        }

        return;
    }

    $building_id = intval($building_id);
    if ($building_id > 0) {
        update_post_meta($post_id, 'ms_building_id', $building_id);
        global $wpdb;
        $table = $wpdb->prefix . 'ms_buildings';
        $wpdb->update($table, array('wp_post_id' => $post_id), array('id' => $building_id), array('%d'), array('%d'));
        set_transient('ms_sync_notice_' . $post_id, 'تم ربط العقار بمبنى موجود في Mostaager بنجاح.', 30);
    }
}

function ms_houzez_sync_admin_notice()
{
    if (!is_admin()) {
        return;
    }

    $screen = get_current_screen();
    if (!is_object($screen) || $screen->id !== 'property') {
        return;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return;
    }

    $notice = get_transient('ms_sync_notice_' . $post_id);
    if (!$notice) {
        return;
    }

    delete_transient('ms_sync_notice_' . $post_id);
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
}
