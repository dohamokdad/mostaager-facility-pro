<?php
if (!defined('ABSPATH')) {
    exit;
}

class MFP_Houzez_Search_Filter
{
    public static function init()
    {
        add_action('houzez_search_form_fields', array(__CLASS__, 'add_vacant_units_field'));
        add_filter('houzez_search_query_args', array(__CLASS__, 'filter_by_vacancy'), 10, 2);
        add_filter('pre_get_posts', array(__CLASS__, 'apply_vacancy_filter'));
    }

    public static function add_vacant_units_field()
    {
        if (!isset($_GET['ms_vacant_only']) || $_GET['ms_vacant_only'] != '1') {
            $checked = '';
        } else {
            $checked = 'checked';
        }
        ?>
        <div class="ms-vacant-units-filter" style="margin:10px 0;padding:12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="ms_vacant_only" value="1" <?php echo $checked; ?> style="width:18px;height:18px;" />
                <span>وحدات شاغرة الآن فقط</span>
            </label>
        </div>
        <?php
    }

    public static function filter_by_vacancy($args, $request)
    {
        if (!isset($_GET['ms_vacant_only']) || $_GET['ms_vacant_only'] != '1') {
            return $args;
        }

        if (!isset($args['post_type']) || $args['post_type'] !== 'property') {
            return $args;
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        $buildings_table = $wpdb->prefix . 'ms_buildings';
        $company = ms_get_company_clause();

        $building_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT building_id FROM $units_table WHERE status = %s AND {$company['clause']}",
            'vacant',
            $company['value']
        ));

        if (empty($building_ids)) {
            $args['post__in'] = array(0);
            return $args;
        }

        $placeholders = implode(',', array_fill(0, count($building_ids), '%d'));
        $property_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT wp_post_id FROM $buildings_table WHERE id IN ($placeholders) AND wp_post_id > 0 AND {$company['clause']}",
            array_merge($building_ids, array($company['value']))
        ));

        if (empty($property_ids)) {
            $args['post__in'] = array(0);
            return $args;
        }

        $args['post__in'] = $property_ids;
        return $args;
    }

    public static function apply_vacancy_filter($query)
    {
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('property')) {
            if (isset($_GET['ms_vacant_only']) && $_GET['ms_vacant_only'] == '1') {
                global $wpdb;
                $units_table = $wpdb->prefix . 'ms_units';
                $buildings_table = $wpdb->prefix . 'ms_buildings';
                $company = ms_get_company_clause();

                $building_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT building_id FROM $units_table WHERE status = %s AND {$company['clause']}",
                    'vacant',
                    $company['value']
                ));

                if (!empty($building_ids)) {
                    $placeholders = implode(',', array_fill(0, count($building_ids), '%d'));
                    $property_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT wp_post_id FROM $buildings_table WHERE id IN ($placeholders) AND wp_post_id > 0 AND {$company['clause']}",
                        array_merge($building_ids, array($company['value']))
                    ));

                    if (!empty($property_ids)) {
                        $query->set('post__in', $property_ids);
                    } else {
                        $query->set('post__in', array(0));
                    }
                } else {
                    $query->set('post__in', array(0));
                }
            }
        }
        return $query;
    }
}