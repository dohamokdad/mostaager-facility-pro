<?php
if (!defined('ABSPATH')) exit;

/**
 * Agent: update property_status taxonomy and upload contracts.
 *
 * Note: This file is intended to be included from core/ajax.php
 * to keep core/ajax.php manageable.
 */

add_action('wp_ajax_ms_agent_update_property_status', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $prop_id = isset($_POST['prop_id']) ? absint($_POST['prop_id']) : 0;
    $term_slug = isset($_POST['term_slug']) ? sanitize_title($_POST['term_slug']) : '';
    if (empty($term_slug) && isset($_POST['status'])) {
        $term_slug = sanitize_title($_POST['status']);
    }

    if (!$prop_id || empty($term_slug)) {
        wp_send_json_error('invalid_data', 400);
    }

    // Ownership check (best-effort, align with ms_delete_agent_property).
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $listing_ids = [];
    if (!empty($listings)) {
        foreach ((array)$listings as $listing) {
            if (is_object($listing) && isset($listing->ID)) {
                $listing_ids[] = absint($listing->ID);
            } elseif (is_array($listing) && isset($listing['ID'])) {
                $listing_ids[] = absint($listing['ID']);
            } elseif (is_numeric($listing)) {
                $listing_ids[] = absint($listing);
            }
        }
    }

    if (empty($listing_ids)) {
        // fallback to meta-based mapping
        $author_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ]);
        $listing_ids = array_map('absint', (array)$author_listings);

        $agent_meta_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'fave_agents',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
                [
                    'key' => 'fave_property_agency',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'ids',
        ]);

        $listing_ids = array_merge($listing_ids, array_map('absint', (array)$agent_meta_listings));
    }

    $listing_ids = array_values(array_unique(array_filter($listing_ids)));
    if (!in_array($prop_id, $listing_ids, true)) {
        wp_send_json_error('permission_denied', 403);
    }

    // Validate term exists under property_status
    $term = get_term_by('slug', $term_slug, 'property_status');
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('invalid_term', 400);
    }

    $updated = wp_set_post_terms($prop_id, [$term_slug], 'property_status');
    if (is_wp_error($updated)) {
        wp_send_json_error('update_failed', 500);
    }

    // Notify agent (best-effort). Admin notification depends on your existing admin workflow.
    if (function_exists('ms_add_notification')) {
        $current_slug = $term_slug;
        ms_add_notification(
            intval($user->ID),
            'property_status_updated',
            sprintf('تم تحديث حالة العقار إلى: %s', esc_html($current_slug)),
            0,
            $prop_id
        );
    }

    wp_send_json_success(['updated' => true]);
});

add_action('wp_ajax_ms_agent_upload_property_contract', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $prop_id = isset($_POST['prop_id']) ? absint($_POST['prop_id']) : 0;
    $contract_type = isset($_POST['contract_type']) ? sanitize_text_field($_POST['contract_type']) : '';

    if (!$prop_id || !in_array($contract_type, ['rent','sale'], true)) {
        wp_send_json_error('invalid_data', 400);
    }

    if (empty($_FILES) || empty($_FILES['contract_file']) || empty($_FILES['contract_file']['name'])) {
        wp_send_json_error('file_missing', 400);
    }

    // Ownership check (best-effort, align with ms_delete_agent_property)
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $listing_ids = [];
    if (!empty($listings)) {
        foreach ((array)$listings as $listing) {
            if (is_object($listing) && isset($listing->ID)) {
                $listing_ids[] = absint($listing->ID);
            } elseif (is_array($listing) && isset($listing['ID'])) {
                $listing_ids[] = absint($listing['ID']);
            } elseif (is_numeric($listing)) {
                $listing_ids[] = absint($listing);
            }
        }
    }

    if (empty($listing_ids)) {
        $author_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ]);
        $listing_ids = array_map('absint', (array)$author_listings);
    }

    $listing_ids = array_values(array_unique(array_filter($listing_ids)));
    if (!in_array($prop_id, $listing_ids, true)) {
        wp_send_json_error('permission_denied', 403);
    }

    $file = $_FILES['contract_file'];

    // Basic validation
    $allowed_ext = ['pdf','jpg','jpeg','png','doc','docx'];
    $filename = isset($file['name']) ? (string)$file['name'] : '';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        wp_send_json_error('invalid_file_type', 400);
    }

    if (!empty($file['size']) && intval($file['size']) > 10 * 1024 * 1024) {
        wp_send_json_error('file_too_large', 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [
        'test_form' => false,
        'mimes' => [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    $movefile = wp_handle_upload($file, $overrides);
    if (isset($movefile['error'])) {
        wp_send_json_error('upload_failed', 500);
    }

    $url = isset($movefile['url']) ? esc_url_raw($movefile['url']) : '';
    if (empty($url)) {
        wp_send_json_error('upload_url_missing', 500);
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';

    $filetype = wp_check_filetype($movefile['file'], null);
    $attachment = [
        'post_mime_type' => $filetype['type'] ?? 'application/octet-stream',
        'post_title' => 'Contract for property #' . $prop_id . ' (' . $contract_type . ')',
        'post_content' => 'Contract uploaded on ' . current_time('mysql'),
        'post_status' => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $movefile['file'], $prop_id);
    if (is_wp_error($attach_id) || empty($attach_id)) {
        // still store url as best-effort
        update_post_meta($prop_id, 'ms_property_contract_url', $url);
        update_post_meta($prop_id, 'ms_property_contract_type', $contract_type);
    } else {
        update_post_meta($prop_id, 'ms_property_contract_url', $url);
        update_post_meta($prop_id, 'ms_property_contract_type', $contract_type);
        update_post_meta($prop_id, 'ms_property_contract_id', $attach_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        if (!is_wp_error($attach_data) && $attach_data) {
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
    }

    if (function_exists('ms_add_notification')) {
        ms_add_notification(
            intval($user->ID),
            'property_contract_uploaded',
            'تم رفع عقد العقار بنجاح.',
            0,
            $prop_id
        );
    }

    wp_send_json_success([
        'url' => $url,
        'attachment_id' => $attach_id,
        'contract_type' => $contract_type,
        'message' => 'تم رفع العقد بنجاح'
    ]);
});

