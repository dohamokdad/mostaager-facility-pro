<?php
if (!defined('ABSPATH')) {
    exit;
}

class MS_Property_Sync {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post_property', array($this, 'handle_save'), 10, 2);
        add_action('admin_notices', array($this, 'show_admin_notice'));
    }

    public function register_meta_box() {
        add_meta_box(
            'ms_property_sync',
            '🔗 ربط بـ Mostaager',
            array($this, 'render_meta_box'),
            'property',
            'side',
            'default'
        );
    }

    public function render_meta_box($post) {
        global $wpdb;

        $building_id = intval(get_post_meta($post->ID, '_ms_building_id', true));
        $buildings_table = $wpdb->prefix . 'ms_buildings';
        $buildings = $wpdb->get_results("SELECT id, title FROM {$buildings_table} ORDER BY title ASC");

        wp_nonce_field('ms_property_sync_nonce_action', 'ms_property_sync_nonce');
        ?>
        <p>
            <label for="ms_building_select"><?php esc_html_e('اختر مبنى موجود أو أنشئ مبنى جديد', 'mostaager-facility-pro'); ?></label>
            <select id="ms_building_select" name="ms_building_select" style="width:100%;">
                <option value=""><?php esc_html_e('-- اختر مبنى --', 'mostaager-facility-pro'); ?></option>
                <?php foreach ((array) $buildings as $building) : ?>
                    <option value="<?php echo esc_attr($building->id); ?>" <?php selected($building_id, $building->id); ?>><?php echo esc_html($building->title); ?></option>
                <?php endforeach; ?>
                <option value="new" <?php selected($building_id, 0); ?>><?php esc_html_e('➕ مبنى جديد', 'mostaager-facility-pro'); ?></option>
            </select>
        </p>
        <div id="ms-new-building-fields" style="display: <?php echo $building_id === 0 ? 'block' : 'none'; ?>; margin-top: 12px;">
            <p>
                <label for="ms_supervisor_name"><?php esc_html_e('اسم المشرف', 'mostaager-facility-pro'); ?></label>
                <input type="text" id="ms_supervisor_name" name="ms_supervisor_name" value="" style="width:100%;" />
            </p>
            <p>
                <label for="ms_supervisor_phone"><?php esc_html_e('هاتف المشرف', 'mostaager-facility-pro'); ?></label>
                <input type="text" id="ms_supervisor_phone" name="ms_supervisor_phone" value="" style="width:100%;" />
            </p>
        </div>
        <script type="text/javascript">
            (function () {
                var select = document.getElementById('ms_building_select');
                var fields = document.getElementById('ms-new-building-fields');

                if (!select || !fields) {
                    return;
                }

                select.addEventListener('change', function () {
                    fields.style.display = this.value === 'new' ? 'block' : 'none';
                });
            })();
        </script>
        <?php
    }

    public function handle_save($post_id, $post) {
        global $wpdb;

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (empty($_POST['ms_property_sync_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['ms_property_sync_nonce']), 'ms_property_sync_nonce_action')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (empty($_POST['ms_building_select'])) {
            return;
        }

        $selected = sanitize_text_field($_POST['ms_building_select']);
        $buildings_table = $wpdb->prefix . 'ms_buildings';

        if ($selected === 'new') {
            $supervisor_name = isset($_POST['ms_supervisor_name']) ? sanitize_text_field($_POST['ms_supervisor_name']) : '';
            $supervisor_phone = isset($_POST['ms_supervisor_phone']) ? sanitize_text_field($_POST['ms_supervisor_phone']) : '';

            if (empty($supervisor_name) || empty($supervisor_phone)) {
                return;
            }

            $existing_building_id = intval(get_post_meta($post_id, '_ms_building_id', true));
            if ($existing_building_id) {
                return;
            }

            $digits = preg_replace('/\D+/', '', $supervisor_phone);
            $username = 'bm_' . substr($digits, -8);
            if (empty($username) || $username === 'bm_') {
                $username = 'bm_' . time();
            }

            $password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $password, $username . '@mostaager.local');
            if (is_wp_error($user_id)) {
                return;
            }

            wp_update_user(array(
                'ID' => $user_id,
                'role' => 'building_manager',
                'display_name' => $supervisor_name,
            ));

            $wpdb->insert($buildings_table, array(
                'title' => $post->post_title,
                'manager_id' => intval($user_id),
                'wp_post_id' => intval($post_id),
                'supervisor_phone' => $supervisor_phone,
                'company_id' => 1,
            ));

            $building_id = intval($wpdb->insert_id);
            if (!$building_id) {
                return;
            }

            update_post_meta($post_id, '_ms_building_id', $building_id);
            update_post_meta($post_id, '_ms_sync_status', 'new_building');
            update_post_meta($post_id, '_ms_sync_date', current_time('mysql'));

            if (class_exists('Mostager_WhatsApp_Integration')) {
                $whatsapp = new Mostager_WhatsApp_Integration();
                $whatsapp->send_template($supervisor_phone, 'building_manager_credentials', array(
                    $supervisor_name,
                    $username,
                    wp_login_url(),
                ));
            }

            $admins = get_users(array(
                'role' => 'administrator',
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
            ));
            $admin_user_id = isset($admins[0]->ID) ? intval($admins[0]->ID) : 0;
            if ($admin_user_id && function_exists('ms_add_notification')) {
                ms_add_notification($admin_user_id, 'new_building', 'مبنى جديد يتطلب إكمال البيانات: ' . $post->post_title, $building_id, $post_id);
            }

            set_transient('ms_sync_notice_' . $post_id, 'new_building', 30);
            return;
        }

        $building_id = intval($selected);
        if ($building_id <= 0) {
            return;
        }

        update_post_meta($post_id, '_ms_building_id', $building_id);
        update_post_meta($post_id, '_ms_sync_status', 'linked');
        update_post_meta($post_id, '_ms_sync_date', current_time('mysql'));
        $wpdb->update($buildings_table, array('wp_post_id' => intval($post_id)), array('id' => $building_id));
        set_transient('ms_sync_notice_' . $post_id, 'linked', 30);
    }

    public function show_admin_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'property') {
            return;
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) {
            return;
        }

        $notice = get_transient('ms_sync_notice_' . $post_id);
        if (empty($notice)) {
            return;
        }

        delete_transient('ms_sync_notice_' . $post_id);

        if ($notice === 'linked') {
            $message = 'تم ربط العقار بمبنى موجود بنجاح.';
        } elseif ($notice === 'new_building') {
            $message = 'تم إنشاء مبنى جديد وربطه بالعقار. تم إرسال بيانات الدخول للمشرف.';
        } else {
            return;
        }

        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
    }
}
