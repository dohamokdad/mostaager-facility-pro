<?php
/**
 * Mostaager User Experience Enhancement Module
 * PWA Support, Amenities Booking, E-Signature Integration
 * 
 * @package Mostaager_Facility_Pro
 * @version 15.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register PWA manifest
 */
add_action('wp_head', function () {
    $manifest_url = MOSTAGER_PLUGIN_URL . 'assets/manifest.json';
    echo '<link rel="manifest" href="' . esc_url($manifest_url) . '">' . PHP_EOL;
    echo '<meta name="theme-color" content="#2563eb">' . PHP_EOL;
    echo '<meta name="mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . PHP_EOL;
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . PHP_EOL;
});

/**
 * Register service worker
 */
add_action('wp_footer', function () {
    if (is_user_logged_in()) {
        $sw_url = MOSTAGER_PLUGIN_URL . 'assets/js/service-worker.js';
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo esc_url($sw_url); ?>').then(function(reg) {
                console.log('Service Worker registered successfully:', reg);
            }).catch(function(err) {
                console.log('Service Worker registration failed:', err);
            });
        }
        </script>
        <?php
    }
});

/**
 * Create PWA manifest file
 */
function msfp_create_pwa_manifest()
{
    $manifest = [
        'name' => 'Mostaager - إدارة العقارات',
        'short_name' => 'Mostaager',
        'description' => 'منصة متكاملة لإدارة العقارات والمستأجرين',
        'start_url' => home_url('/rent-dashboard/'),
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#2563eb',
        'orientation' => 'portrait-primary',
        'icons' => [
            [
                'src' => MOSTAGER_PLUGIN_URL . 'assets/images/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => MOSTAGER_PLUGIN_URL . 'assets/images/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ],
        'screenshots' => [
            [
                'src' => MOSTAGER_PLUGIN_URL . 'assets/images/screenshot-1.png',
                'sizes' => '540x720',
                'type' => 'image/png',
                'form_factor' => 'narrow',
            ],
        ],
    ];
    
    return $manifest;
}

/**
 * Render Amenities Booking System
 */
function msfp_render_amenities_booking($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    
    if (!$building_id || !is_user_logged_in()) {
        return '<div class="ms-card"><p>يرجى اختيار مبنى وتسجيل الدخول لحجز المرافق.</p></div>';
    }
    
    global $wpdb;
    
    // Get available amenities for this building
    $amenities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_amenities WHERE building_id = %d AND status = 'active'",
        $building_id
    ));
    
    // Get user's bookings
    $user_id = get_current_user_id();
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_amenity_bookings 
         WHERE building_id = %d AND user_id = %d 
         ORDER BY booking_date DESC LIMIT 20",
        $building_id,
        $user_id
    ));
    
    ob_start();
    ?>
    <div class="ms-card msfp-amenities-booking">
        <h3>حجز المرافق - Amenities Booking</h3>
        
        <?php if (empty($amenities)) : ?>
            <p style="margin-top:16px;">لا توجد مرافق متاحة للحجز في هذا المبنى.</p>
        <?php else : ?>
            <form id="msfp-amenity-booking-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;align-items:end;">
                <input type="hidden" name="action" value="msfp_book_amenity">
                <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
                <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_amenity_booking')); ?>">
                
                <label>المرفق
                    <select name="amenity_id" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                        <option value="">اختر مرفقاً</option>
                        <?php foreach ($amenities as $amenity) : ?>
                            <option value="<?php echo esc_attr($amenity->id); ?>">
                                <?php echo esc_html($amenity->name); ?> (<?php echo esc_html($amenity->capacity); ?> أشخاص)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                
                <label>التاريخ
                    <input type="date" name="booking_date" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                </label>
                
                <label>الوقت من
                    <input type="time" name="start_time" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                </label>
                
                <label>الوقت إلى
                    <input type="time" name="end_time" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                </label>
                
                <label>عدد الأشخاص
                    <input type="number" name="guest_count" min="1" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                </label>
                
                <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">حجز المرفق</button>
                <div id="msfp-booking-message" style="display:none;font-weight:600;"></div>
            </form>
            
            <h4 style="margin-top:24px;">حجوزاتك</h4>
            <?php if (empty($bookings)) : ?>
                <p>لم تقم بأي حجوزات بعد.</p>
            <?php else : ?>
                <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                    <thead>
                        <tr style="background:#f8fafc;text-align:right;">
                            <th style="padding:12px;">المرفق</th>
                            <th style="padding:12px;">التاريخ</th>
                            <th style="padding:12px;">الوقت</th>
                            <th style="padding:12px;">الحالة</th>
                            <th style="padding:12px;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking) : ?>
                            <tr style="border-top:1px solid #e5e7eb;">
                                <td style="padding:12px;"><?php echo esc_html($booking->amenity_name); ?></td>
                                <td style="padding:12px;"><?php echo esc_html($booking->booking_date); ?></td>
                                <td style="padding:12px;"><?php echo esc_html($booking->start_time); ?> - <?php echo esc_html($booking->end_time); ?></td>
                                <td style="padding:12px;">
                                    <span style="padding:4px 8px;background:#dcfce7;color:#15803d;border-radius:4px;font-size:12px;">
                                        <?php echo esc_html($booking->status); ?>
                                    </span>
                                </td>
                                <td style="padding:12px;">
                                    <?php if ($booking->status === 'pending') : ?>
                                        <button class="msfp-cancel-booking" data-id="<?php echo esc_attr($booking->id); ?>" style="background:#ef4444;color:#fff;border:0;padding:4px 8px;border-radius:4px;cursor:pointer;">إلغاء</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
    (function(){
        var form = document.getElementById('msfp-amenity-booking-form');
        if (form && form.dataset.bound !== '1') {
            form.dataset.bound = '1';
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var msg = document.getElementById('msfp-booking-message');
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new FormData(form)
                }).then(function(r){ return r.json(); }).then(function(res){
                    msg.style.display = 'block';
                    msg.style.color = res.success ? '#059669' : '#dc2626';
                    msg.textContent = (res.data && res.data.message) ? res.data.message : 'تمت العملية.';
                    if(res.success) setTimeout(function(){ location.reload(); }, 900);
                });
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_amenities_booking', function ($atts) {
    $atts = shortcode_atts(['building_id' => 0], $atts, 'mostaager_amenities_booking');
    return msfp_render_amenities_booking(absint($atts['building_id']));
});

/**
 * AJAX handler for amenity booking
 */
add_action('wp_ajax_msfp_book_amenity', function () {
    check_ajax_referer('msfp_amenity_booking', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'يرجى تسجيل الدخول.'], 403);
    }
    
    global $wpdb;
    
    $building_id = absint($_POST['building_id'] ?? 0);
    $amenity_id = absint($_POST['amenity_id'] ?? 0);
    $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
    $start_time = sanitize_text_field($_POST['start_time'] ?? '');
    $end_time = sanitize_text_field($_POST['end_time'] ?? '');
    $guest_count = absint($_POST['guest_count'] ?? 0);
    
    if (!$building_id || !$amenity_id || !$booking_date || !$start_time || !$end_time || !$guest_count) {
        wp_send_json_error(['message' => 'يرجى ملء جميع الحقول.'], 422);
    }
    
    // Check if amenity is available
    $amenity = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_amenities WHERE id = %d AND building_id = %d",
        $amenity_id,
        $building_id
    ));
    
    if (!$amenity) {
        wp_send_json_error(['message' => 'المرفق غير متاح.'], 404);
    }
    
    // Check for conflicts
    $conflict = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_amenity_bookings 
         WHERE amenity_id = %d AND booking_date = %s 
         AND ((start_time < %s AND end_time > %s) OR (start_time < %s AND end_time > %s))
         AND status IN ('confirmed', 'pending')",
        $amenity_id,
        $booking_date,
        $end_time,
        $start_time,
        $end_time,
        $start_time
    ));
    
    if ($conflict > 0) {
        wp_send_json_error(['message' => 'المرفق محجوز في هذا الوقت.'], 409);
    }
    
    // Create booking
    $wpdb->insert($wpdb->prefix . 'ms_amenity_bookings', [
        'building_id' => $building_id,
        'amenity_id' => $amenity_id,
        'user_id' => get_current_user_id(),
        'booking_date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'guest_count' => $guest_count,
        'amenity_name' => $amenity->name,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
    ], ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
    
    wp_send_json_success(['message' => 'تم الحجز بنجاح. سيتم تأكيده قريباً.']);
});

/**
 * Create amenities tables
 */
function msfp_create_amenities_tables()
{
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_amenities (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        name VARCHAR(191) NOT NULL,
        description TEXT NULL,
        capacity INT UNSIGNED DEFAULT 0,
        price_per_hour DECIMAL(10,2) DEFAULT 0.00,
        status VARCHAR(40) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id)
    ) $charset_collate";
    
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_amenity_bookings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        amenity_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        amenity_name VARCHAR(191) NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        guest_count INT UNSIGNED DEFAULT 0,
        status VARCHAR(40) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY amenity_id (amenity_id),
        KEY user_id (user_id),
        KEY booking_date (booking_date)
    ) $charset_collate";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
}
add_action('plugins_loaded', 'msfp_create_amenities_tables');

/**
 * E-Signature Integration (DocuSign API)
 */
function msfp_init_esignature()
{
    $docusign_key = get_option('msfp_docusign_api_key', '');
    $docusign_account = get_option('msfp_docusign_account_id', '');
    
    if (empty($docusign_key) || empty($docusign_account)) {
        return false;
    }
    
    return true;
}

/**
 * Send document for e-signature via DocuSign
 */
function msfp_send_for_esignature($document_id, $recipient_email, $recipient_name)
{
    $docusign_key = get_option('msfp_docusign_api_key', '');
    $docusign_account = get_option('msfp_docusign_account_id', '');
    $docusign_base_url = get_option('msfp_docusign_base_url', 'https://demo.docusign.net');
    
    if (empty($docusign_key) || empty($docusign_account)) {
        return new WP_Error('not_configured', 'DocuSign is not configured');
    }
    
    $document = get_post($document_id);
    if (!$document) {
        return new WP_Error('not_found', 'Document not found');
    }
    
    // Prepare envelope for DocuSign
    $envelope = [
        'emailSubject' => 'توقيع مطلوب: ' . $document->post_title,
        'documents' => [
            [
                'documentBase64' => base64_encode(file_get_contents(get_attached_file($document_id))),
                'name' => $document->post_title,
                'documentId' => $document_id,
            ],
        ],
        'recipients' => [
            'signers' => [
                [
                    'email' => $recipient_email,
                    'name' => $recipient_name,
                    'recipientId' => '1',
                    'tabs' => [
                        'signHereTabs' => [
                            [
                                'documentId' => $document_id,
                                'pageNumber' => '1',
                                'xPosition' => '100',
                                'yPosition' => '100',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'status' => 'sent',
    ];
    
    $response = wp_remote_post(
        $docusign_base_url . '/restapi/v2.1/accounts/' . $docusign_account . '/envelopes',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $docusign_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($envelope),
            'timeout' => 30,
        ]
    );
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['envelopeId'])) {
        update_post_meta($document_id, '_docusign_envelope_id', $body['envelopeId']);
        return ['envelope_id' => $body['envelopeId'], 'status' => 'sent'];
    }
    
    return new WP_Error('api_error', $body['message'] ?? 'Failed to send for signature');
}

/**
 * Admin settings for e-signature
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'mostaager-admin',
        'التوقيع الإلكتروني',
        'التوقيع الإلكتروني',
        'manage_options',
        'msfp-esignature',
        'msfp_render_esignature_settings'
    );
}, 36);

function msfp_render_esignature_settings()
{
    if (isset($_POST['msfp_save_esignature'])) {
        check_admin_referer('msfp_esignature_settings');
        
        update_option('msfp_docusign_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
        update_option('msfp_docusign_account_id', sanitize_text_field($_POST['account_id'] ?? ''));
        update_option('msfp_docusign_base_url', esc_url_raw($_POST['base_url'] ?? 'https://demo.docusign.net'));
        
        echo '<div class="notice notice-success"><p>تم حفظ الإعدادات بنجاح</p></div>';
    }
    
    $api_key = get_option('msfp_docusign_api_key', '');
    $account_id = get_option('msfp_docusign_account_id', '');
    $base_url = get_option('msfp_docusign_base_url', 'https://demo.docusign.net');
    ?>
    <div class="wrap">
        <h1>إعدادات التوقيع الإلكتروني (DocuSign)</h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>تكوين DocuSign API</h2>
            <form method="post">
                <?php wp_nonce_field('msfp_esignature_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="api_key">API Key</label></th>
                        <td>
                            <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" style="width:100%;max-width:400px;padding:8px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="account_id">Account ID</label></th>
                        <td>
                            <input type="text" id="account_id" name="account_id" value="<?php echo esc_attr($account_id); ?>" style="width:100%;max-width:400px;padding:8px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="base_url">Base URL</label></th>
                        <td>
                            <input type="url" id="base_url" name="base_url" value="<?php echo esc_attr($base_url); ?>" style="width:100%;max-width:400px;padding:8px;">
                            <p class="description">استخدم https://demo.docusign.net للاختبار أو https://na3.docusign.net للإنتاج</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="msfp_save_esignature" class="button button-primary">حفظ الإعدادات</button>
                </p>
            </form>
        </div>
    </div>
    <?php
}
