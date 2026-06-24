<?php
if (!defined('ABSPATH')) {
    exit;
}

class MS_Lead_Converter {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('wp_ajax_ms_convert_lead_to_tenant', array($this, 'handle_ajax_convert'));
    }

    public function register_meta_box() {
        add_meta_box(
            'ms_lead_converter',
            'تحويل Lead إلى مستأجر',
            array($this, 'render_meta_box'),
            'houzez_crm_lead',
            'side',
            'default'
        );
    }

    public function render_meta_box($post) {
        global $wpdb;

        $lead_name = get_post_meta($post->ID, 'fave_lead_name', true);
        $lead_phone = get_post_meta($post->ID, 'fave_lead_phone', true);
        $lead_email = get_post_meta($post->ID, 'fave_lead_email', true);
        $property_id = intval(get_post_meta($post->ID, 'fave_property_id', true));
        $converted_tenant_id = intval(get_post_meta($post->ID, '_ms_converted_tenant_id', true));
        $building_id = 0;
        $unit_id = 0;

        if ($property_id) {
            $building_id = intval(get_post_meta($property_id, '_ms_building_id', true));
            if ($building_id) {
                $unit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_units WHERE building_id = %d LIMIT 1", $building_id));
                if ($unit) {
                    $unit_id = intval($unit->id ?? $unit->ID ?? 0);
                }
            }
        }

        if ($converted_tenant_id) {
            $tenant_url = admin_url('admin.php?page=mostaager-admin&user_id=' . intval($converted_tenant_id));
            echo '<p style="margin-bottom:10px;">تم التحويل بالفعل إلى مستأجر.</p>';
            echo '<p><a href="' . esc_url($tenant_url) . '" target="_blank">عرض ملف المستأجر في Mostaager</a></p>';
            return;
        }

        if (!$property_id) {
            echo '<p>لا يوجد عقار مرتبط بهذا الـ Lead.</p>';
            return;
        }

        if (!$unit_id) {
            echo '<p>تعذر تحديد وحدة مرتبطة بالمبنى.</p>';
            return;
        }

        wp_nonce_field('ms_convert_lead_nonce', 'ms_convert_lead_nonce');
        ?>
        <table class="form-table" style="width:100%;">
            <tr>
                <th scope="row"><label>الاسم</label></th>
                <td><?php echo esc_html($lead_name ?: '-'); ?></td>
            </tr>
            <tr>
                <th scope="row"><label>الجوال</label></th>
                <td><?php echo esc_html($lead_phone ?: '-'); ?></td>
            </tr>
            <tr>
                <th scope="row"><label>البريد</label></th>
                <td><?php echo esc_html($lead_email ?: '-'); ?></td>
            </tr>
            <tr>
                <th scope="row"><label>العقار المهتم به</label></th>
                <td><a href="<?php echo esc_url(get_edit_post_link($property_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($property_id) ?: $property_id); ?></a></td>
            </tr>
        </table>
        <input type="hidden" name="lead_id" value="<?php echo esc_attr($post->ID); ?>" />
        <input type="hidden" name="unit_id" value="<?php echo esc_attr($unit_id); ?>" />
        <p>
            <button type="button" id="ms-convert-lead-button" class="button button-primary" style="width:100%;">تحويل إلى مستأجر</button>
        </p>
        <div id="ms-convert-lead-response" style="margin-top:10px;"></div>
        <script type="text/javascript">
            (function () {
                var button = document.getElementById('ms-convert-lead-button');
                if (!button) {
                    return;
                }
                button.addEventListener('click', function () {
                    var leadId = '<?php echo esc_js($post->ID); ?>';
                    var unitId = '<?php echo esc_js($unit_id); ?>';
                    var responseContainer = document.getElementById('ms-convert-lead-response');

                    if (!leadId || !unitId) {
                        responseContainer.textContent = 'تعذر قراءة بيانات Lead أو الوحدة.';
                        return;
                    }

                    var data = new FormData();
                    data.append('action', 'ms_convert_lead_to_tenant');
                    data.append('lead_id', leadId);
                    data.append('unit_id', unitId);
                    data.append('security', '<?php echo esc_js(wp_create_nonce('ms_convert_lead_nonce')); ?>');

                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: data,
                    }).then(function (res) {
                        return res.json();
                    }).then(function (result) {
                        if (result.success) {
                            responseContainer.innerHTML = '<span style="color:#0a0;">تم التحويل بنجاح. سيتم إعادة تحميل الصفحة...</span>';
                            setTimeout(function () {
                                window.location.reload();
                            }, 1200);
                            return;
                        }
                        responseContainer.innerHTML = '<span style="color:#a00;">' + (result.data || 'فشل التحويل') + '</span>';
                    }).catch(function () {
                        responseContainer.innerHTML = '<span style="color:#a00;">حدث خطأ أثناء الاتصال.</span>';
                    });
                });
            })();
        </script>
        <?php
    }

    public function handle_ajax_convert() {
        if (empty($_POST['security']) || !wp_verify_nonce(sanitize_text_field($_POST['security']), 'ms_convert_lead_nonce')) {
            wp_send_json_error('فشل التحقق', 403);
        }

        $current_user_id = get_current_user_id();
        if (!current_user_can('manage_options') && !current_user_can('ms_view_dashboard')) {
            wp_send_json_error('غير مصرح', 403);
        }

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $unit_id = isset($_POST['unit_id']) ? absint($_POST['unit_id']) : 0;
        if (!$lead_id || !$unit_id) {
            wp_send_json_error('معرف Lead أو الوحدة غير صالح', 400);
        }

        $lead_name = sanitize_text_field(get_post_meta($lead_id, 'fave_lead_name', true));
        $lead_phone = sanitize_text_field(get_post_meta($lead_id, 'fave_lead_phone', true));
        $lead_email = sanitize_email(get_post_meta($lead_id, 'fave_lead_email', true));
        $property_id = intval(get_post_meta($lead_id, 'fave_property_id', true));

        if (empty($lead_email)) {
            wp_send_json_error('البريد الإلكتروني مطلوب', 400);
        }

        $user_id = email_exists($lead_email);
        if (!$user_id) {
            $username = sanitize_user(current(explode('@', $lead_email)), true);
            if (empty($username)) {
                $username = 'tenant_' . time();
            }
            $password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $password, $lead_email);
            if (is_wp_error($user_id)) {
                wp_send_json_error('فشل إنشاء مستخدم المستأجر', 500);
            }
            wp_update_user(array(
                'ID' => $user_id,
                'role' => 'tenant',
                'display_name' => $lead_name ?: $username,
            ));
        }

        if (!empty($lead_phone)) {
            update_user_meta($user_id, 'phone', $lead_phone);
        }

        global $wpdb;
        $unit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_units WHERE id = %d LIMIT 1", $unit_id));
        if (!$unit) {
            wp_send_json_error('الوحدة غير موجودة', 404);
        }

        $building_id = intval($unit->building_id ?? 0);
        if (!$building_id) {
            wp_send_json_error('تعذر تحديد مبنى الوحدة', 400);
        }

        if (!function_exists('ms_link_tenant_to_unit')) {
            wp_send_json_error('الدالة ms_link_tenant_to_unit غير متاحة', 500);
        }

        $linked = ms_link_tenant_to_unit($user_id, $unit_id, $building_id, current_time('Y-m-d'));
        if ($linked === false) {
            wp_send_json_error('فشل ربط المستأجر بالوحدة', 500);
        }

        $updated = $wpdb->update(
            "{$wpdb->prefix}ms_units",
            array(
                'tenant_id' => intval($user_id),
                'status' => 'occupied',
            ),
            array('id' => $unit_id),
            array('%d', '%s'),
            array('%d')
        );

        update_post_meta($lead_id, '_ms_converted_tenant_id', $user_id);
        update_post_meta($lead_id, '_ms_converted_unit_id', $unit_id);
        update_post_meta($lead_id, '_ms_converted_date', current_time('mysql'));

        $building_name = get_post_meta($property_id, 'building_title', true) ?: get_post_meta($property_id, 'building_name', true) ?: '';
        if (class_exists('Mostager_WhatsApp_Integration') && !empty($lead_phone)) {
            $whatsapp = new Mostager_WhatsApp_Integration();
            if (method_exists($whatsapp, 'send_welcome')) {
                $whatsapp->send_welcome($lead_phone, array(
                    'tenant_name' => $lead_name,
                    'building_name' => $building_name,
                ));
            }
        }

        $tenant_url = admin_url('admin.php?page=mostaager-admin&user_id=' . intval($user_id));
        if (function_exists('ms_add_notification')) {
            ms_add_notification($current_user_id, 'lead_converted', 'تم تحويل Lead إلى مستأجر بنجاح.', $building_id, $lead_id);
        }

        wp_send_json_success(array('tenant_url' => esc_url_raw($tenant_url)));
    }
}
