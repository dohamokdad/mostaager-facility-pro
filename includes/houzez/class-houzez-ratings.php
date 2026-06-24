<?php
if (!defined('ABSPATH')) {
    exit;
}

class MFP_Houzez_Ratings
{
    public static function init()
    {
        add_action('houzez_single_property_after_description', array(__CLASS__, 'render_property_ratings'));
        add_filter('houzez_property_rating', array(__CLASS__, 'get_property_average_rating'), 10, 2);
    }

    public static function render_property_ratings($post_id)
    {
        if (!is_singular('property')) {
            return;
        }

        $building_id = get_post_meta($post_id, '_ms_building_id', true);
        $building_id = $building_id ? absint($building_id) : get_post_meta($post_id, 'ms_building_id', true);
        $building_id = absint($building_id);

        if (!$building_id) {
            return;
        }

        global $wpdb;
        $request_table = $wpdb->prefix . 'ms_maintenance_requests';
        $rating_table = $wpdb->prefix . 'ms_technician_ratings';
        $company = ms_get_company_clause();

        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $request_table WHERE building_id = %d AND status = 'completed' ORDER BY created_at DESC LIMIT 100",
            $building_id
        ));

        if (empty($requests)) {
            return;
        }

        $total_rating = 0;
        $rating_count = 0;

        foreach ($requests as $request) {
            $ratings = function_exists('ms_get_technician_ratings_by_request')
                ? ms_get_technician_ratings_by_request($request->id, 50)
                : $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $rating_table WHERE request_id = %d AND {$company['clause']} ORDER BY rating_date DESC LIMIT 50",
                    $request->id,
                    $company['value']
                ));

            foreach ($ratings as $rating) {
                $value = intval($rating->rating ?? 0);
                if ($value > 0) {
                    $total_rating += $value;
                    $rating_count++;
                }
            }
        }

        if ($rating_count === 0) {
            return;
        }

        $average = round($total_rating / $rating_count, 1);
        $full_stars = intval($average);
        $half_star = ($average - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

        ob_start();
        ?>
        <div class="ms-property-ratings-section" style="margin-top:30px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
            <h3>تقييمات المستأجرين للمبنى</h3>
            <div style="display:flex;align-items:center;gap:10px;margin-top:12px;">
                <div class="ms-rating-stars" style="font-size:24px;color:#f59e0b;">
                    <?php echo str_repeat('★', $full_stars); ?>
                    <?php echo $half_star ? '½' : ''; ?>
                    <?php echo str_repeat('☆', $empty_stars); ?>
                </div>
                <span style="font-size:18px;font-weight:600;"><?php echo esc_html(number_format_i18n($average, 1)); ?>/5</span>
                <span style="color:#6b7280;">(<?php echo intval($rating_count); ?> تقييم)</span>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }

    public static function get_property_average_rating($rating, $post_id)
    {
        $building_id = get_post_meta($post_id, '_ms_building_id', true);
        $building_id = absint($building_id);

        if (!$building_id) {
            return $rating;
        }

        global $wpdb;
        $rating_table = $wpdb->prefix . 'ms_technician_ratings';
        $company = ms_get_company_clause();

        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM $rating_table WHERE building_id = %d AND {$company['clause']}",
            $building_id,
            $company['value']
        ));

        if ($avg !== null) {
            return round(floatval($avg), 1);
        }

        return $rating;
    }
}