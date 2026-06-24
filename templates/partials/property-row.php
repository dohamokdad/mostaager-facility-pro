<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post, $delete_properties_nonce, $ms_dashboard_context;

$post_id = get_the_ID();
$thumbnail = get_the_post_thumbnail($post_id, [60, 60], array(
    'loading' => 'lazy',
    'decoding' => 'async',
    'class' => 'ms-property-thumbnail'
));
if (!$thumbnail) {
    $thumbnail = '<span class="no-thumbnail" aria-hidden="true"></span>';
}

$status = get_post_status($post_id);
$status_labels = array(
    'publish' => 'منشور',
    'pending' => 'قيد المراجعة',
    'draft' => 'مسودة',
    'expired' => 'منتهي',
    'houzez_sold' => 'مباع',
    'disapproved' => 'مرفوض',
    'on_hold' => 'معلق',
    'private' => 'خاص',
);
$status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'غير معروف';

$property_status_terms = taxonomy_exists('property_status') ? get_terms(array('taxonomy' => 'property_status', 'hide_empty' => false)) : array();
$current_property_status_terms = wp_get_post_terms($post_id, 'property_status', array('fields' => 'all'));
$current_property_status_slug = '';
$current_property_status_name = '';
if (is_array($current_property_status_terms) && !empty($current_property_status_terms)) {
    $current_property_status_slug = $current_property_status_terms[0]->slug;
    $current_property_status_name = $current_property_status_terms[0]->name;
}
$fave_property_status = get_post_meta($post_id, 'fave_property_status', true);

$contract_url = get_post_meta($post_id, 'ms_property_contract_url', true);
$contract_type = get_post_meta($post_id, 'ms_property_contract_type', true);

$price = get_post_meta($post_id, 'fave_property_price', true);
$type = get_post_meta($post_id, 'fave_property_type', true);
$date = get_the_date();

$edit_url = get_edit_post_link($post_id);
$edit_url = $edit_url ? esc_url($edit_url) : '';
?>
<tr>
    <td>
        <label class="houzez-checkbox">
            <input type="checkbox" class="listing-bulk-delete" value="<?php echo intval($post_id); ?>">
            <span class="houzez-checkmark"></span>
        </label>
    </td>
    <td><?php echo $thumbnail; ?></td>
    <td><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title()); ?></a></td>
    <td>
        <?php if (!empty($current_property_status_name)): ?>
            <span class="houzez-badge houzez-badge-success"><?php echo esc_html($current_property_status_name); ?></span>
        <?php elseif (!empty($fave_property_status)): ?>
            <span class="houzez-badge houzez-badge-secondary"><?php echo esc_html($fave_property_status); ?></span>
        <?php else: ?>
            <span class="houzez-badge houzez-badge-secondary"><?php echo esc_html($status_label); ?></span>
        <?php endif; ?>
        <?php if (!empty($property_status_terms) && !is_wp_error($property_status_terms)): ?>
            <div style="margin-top:8px;">
                <select class="agent-property-status" data-prop-id="<?php echo intval($post_id); ?>" style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;background:#fff;min-width:180px;">
                    <option value=""><?php echo esc_html__('تغيير الحالة', 'mostaager-facility-pro'); ?></option>
                    <?php foreach ($property_status_terms as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($current_property_status_slug, $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </td>
    <?php
    $is_agent_dashboard = isset($ms_dashboard_context) && $ms_dashboard_context === 'agent';
    $show_edit_action = empty($ms_dashboard_context) || $ms_dashboard_context !== 'agent';
    ?>

    <?php if ($is_agent_dashboard): ?>
        <td><?php echo esc_html($price); ?></td>
        <td>
            <?php if ($contract_url): ?>
                <div style="margin-bottom:8px;">
                    <a href="<?php echo esc_url($contract_url); ?>" target="_blank" style="color:#2563eb;text-decoration:underline;">
                        <?php echo esc_html($contract_type === 'sale' ? 'عرض عقد البيع' : ($contract_type === 'rent' ? 'عرض عقد الإيجار' : 'عرض العقد')); ?>
                    </a>
                </div>
            <?php endif; ?>
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="margin-right:6px;padding:6px 10px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;">رفع عقد إيجار</button>
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="padding:6px 10px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer;">رفع عقد بيع</button>
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
            <?php if ($edit_url): ?>
                <a href="<?php echo $edit_url; ?>"><?php echo esc_html__('Edit', 'mostaager-facility-pro'); ?></a>
            <?php endif; ?>
            <a href="#" class="delete-property" data-prop-id="<?php echo intval($post_id); ?>" data-security="<?php echo esc_attr($delete_properties_nonce); ?>"><?php echo esc_html__('Delete', 'mostaager-facility-pro'); ?></a>
        </td>
    <?php else: ?>
        <td><?php echo esc_html($price); ?></td>
        <td>
            <?php if ($contract_url): ?>
                <div style="margin-bottom:8px;">
                    <a href="<?php echo esc_url($contract_url); ?>" target="_blank" style="color:#2563eb;text-decoration:underline;">
                        <?php echo esc_html($contract_type === 'sale' ? 'عرض عقد البيع' : ($contract_type === 'rent' ? 'عرض عقد الإيجار' : 'عرض العقد')); ?>
                    </a>
                </div>
            <?php endif; ?>
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="margin-right:6px;padding:6px 10px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;">رفع عقد إيجار</button>
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="padding:6px 10px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer;">رفع عقد بيع</button>
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
            <div style="margin-top:10px;color:#64748b;font-size:0.95rem;line-height:1.4;">
                <div><?php echo esc_html__('رقم العقار:', 'mostaager-facility-pro'); ?> <?php echo intval($post_id); ?></div>
                <div><?php echo esc_html($type); ?></div>
                <div><?php echo esc_html($date); ?></div>
            </div>
            <?php if ($edit_url): ?>
                <div style="margin-top:8px;"><a href="<?php echo $edit_url; ?>"><?php echo esc_html__('Edit', 'mostaager-facility-pro'); ?></a></div>
            <?php endif; ?>
            <div><a href="#" class="delete-property" data-prop-id="<?php echo intval($post_id); ?>" data-security="<?php echo esc_attr($delete_properties_nonce); ?>"><?php echo esc_html__('Delete', 'mostaager-facility-pro'); ?></a></div>
        </td>
    <?php endif; ?>
</tr>
