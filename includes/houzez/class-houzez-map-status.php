<?php
if (!defined('ABSPATH')) {
    exit;
}

class MFP_Houzez_Map_Status
{
    public static function init()
    {
        add_filter('houzez_map_marker_data', array(__CLASS__, 'add_building_status_to_marker'), 10, 2);
        add_action('wp_footer', array(__CLASS__, 'inject_map_status_script'));
    }

    public static function add_building_status_to_marker($marker_data, $post_id)
    {
        $building_id = get_post_meta($post_id, '_ms_building_id', true);
        $building_id = absint($building_id);

        if (!$building_id) {
            return $marker_data;
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        $company = ms_get_company_clause();

        $total = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $units_table WHERE building_id = %d AND {$company['clause']}",
            $building_id,
            $company['value']
        )));

        $vacant = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $units_table WHERE building_id = %d AND status = %s AND {$company['clause']}",
            $building_id,
            'vacant',
            $company['value']
        )));

        $occupied = max(0, $total - $vacant);
        $occupancy_rate = $total > 0 ? intval(round(($occupied / $total) * 100, 0)) : 0;

        if ($occupancy_rate >= 80) {
            $status_color = '#10b981';
            $status_label = 'مستقر';
        } elseif ($occupancy_rate >= 50) {
            $status_color = '#f59e0b';
            $status_label = 'تنبيه صيانة';
        } else {
            $status_color = '#ef4444';
            $status_label = 'حرج';
        }

        $marker_data['ms_occupancy_rate'] = $occupancy_rate;
        $marker_data['ms_status_color'] = $status_color;
        $marker_data['ms_status_label'] = $status_label;
        $marker_data['ms_total_units'] = $total;
        $marker_data['ms_vacant_units'] = $vacant;
        $marker_data['ms_occupied_units'] = $occupied;

        return $marker_data;
    }

    public static function inject_map_status_script()
    {
        if (!is_singular('property') && !is_post_type_archive('property')) {
            return;
        }
        ?>
        <script type="text/javascript">
            (function() {
                function applyBuildingStatusColors() {
                    if (typeof jQuery === 'undefined') {
                        return;
                    }
                    var markers = window.houzez_map_markers || [];
                    if (!markers.length) {
                        setTimeout(applyBuildingStatusColors, 500);
                        return;
                    }
                    jQuery.each(markers, function(i, marker) {
                        if (marker.options && marker.options.ms_status_color) {
                            var color = marker.options.ms_status_color;
                            if (marker.setIcon) {
                                var svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40"><path fill="' + color + '" d="M16 0C7.16 0 0 7.16 0 16c0 14.32 16 24 16 24s16-9.68 16-24C32 7.16 24.84 0 16 0zm0 22c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/></svg>';
                                marker.setIcon(svgIcon);
                            }
                        }
                    });
                }
                if (document.readyState === 'complete') {
                    applyBuildingStatusColors();
                } else {
                    jQuery(document).ready(function() {
                        setTimeout(applyBuildingStatusColors, 1000);
                    });
                }
            })();
        </script>
        <?php
    }
}