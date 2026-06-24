<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Mostaager_DB
{
    protected $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public static function get_instance(): Mostaager_DB
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Mostaager_DB();
        }
        return $instance;
    }

    public function get_facilities_by_manager(int $user_id): array
    {
        if ($user_id && user_can($user_id, 'manage_options')) {
            return (array) $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ms_buildings");
        }

        if (!$user_id) {
            return [];
        }

        return (array) $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}ms_buildings WHERE manager_id = %d", $user_id)
        );
    }

    public function get_user_invoices(int $user_id, int $limit = 100): array
    {
        if ($user_id && function_exists('ms_get_user_invoices')) {
            return array_slice(ms_get_user_invoices($user_id), 0, $limit);
        }

        return [];
    }

    public function get_manager_invoices(int $user_id, int $limit = 100, int $building_id = 0): array
    {
        if (!function_exists('ms_get_manager_invoices')) {
            return [];
        }

        if ($building_id && function_exists('ms_current_user_manages_building') && !ms_current_user_manages_building($user_id, $building_id)) {
            return [];
        }

        return ms_get_manager_invoices($user_id, $limit, '', $building_id);
    }

    public function current_user_manages_building(int $user_id, int $building_id): bool
    {
        if (!function_exists('ms_current_user_manages_building')) {
            return false;
        }

        return ms_current_user_manages_building($user_id, $building_id);
    }

    public function get_property_operational_status(int $building_id): string
    {
        $building_id = absint($building_id);
        if (!$building_id) {
            return 'stable';
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        $requests_table = $wpdb->prefix . 'ms_maintenance_requests';

        $total_units = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$units_table} WHERE building_id = %d", $building_id)));
        $vacant_units = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$units_table} WHERE building_id = %d AND status IN (%s, %s)", $building_id, 'vacant', 'available')));
        $maintenance_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$requests_table} WHERE building_id = %d AND status IN (%s, %s, %s)", $building_id, 'open', 'in_progress', 'waiting_parts')));

        if ($total_units <= 0) {
            return 'stable';
        }

        $occupied = max(0, $total_units - $vacant_units);
        $occupancy_rate = $total_units > 0 ? ($occupied / $total_units) * 100 : 0;

        if ($maintenance_count > 0 || $occupancy_rate < 50) {
            return 'critical';
        }

        if ($occupancy_rate < 80) {
            return 'maintenance_alert';
        }

        return 'stable';
    }

    public function get_avg_rating(int $property_id): float
    {
        $property_id = absint($property_id);
        if (!$property_id) {
            return 0.0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ms_property_reviews';
        $avg = $wpdb->get_var($wpdb->prepare("SELECT AVG(rating) FROM {$table} WHERE property_id = %d AND is_approved = %d", $property_id, 1));
        if ($avg === null) {
            return 0.0;
        }

        return round(floatval($avg), 2);
    }

    public function get_occupancy_stats(int $building_id): array
    {
        $building_id = absint($building_id);
        if (!$building_id) {
            return ['total' => 0, 'occupied' => 0, 'vacant' => 0, 'rate' => 0];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ms_units';

        $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE building_id = %d", $building_id)));
        $vacant = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE building_id = %d AND status IN (%s, %s)", $building_id, 'vacant', 'available')));
        $occupied = max(0, $total - $vacant);
        $rate = $total > 0 ? round(($occupied / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'rate' => $rate,
        ];
    }

    public function get_buildings_map_data(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ms_buildings';
        $units_table = $wpdb->prefix . 'ms_units';

        $buildings = $wpdb->get_results("SELECT id, title, manager_id FROM {$table}");
        if (empty($buildings)) {
            return [];
        }

        $result = [];
        foreach ($buildings as $building) {
            $stats = $this->get_occupancy_stats(intval($building->id));
            $status = 'stable';
            $color = '#10b981';
            $label = 'مستقر';

            if ($stats['rate'] < 50) {
                $status = 'critical';
                $color = '#ef4444';
                $label = 'حرج';
            } elseif ($stats['rate'] < 80) {
                $status = 'maintenance_alert';
                $color = '#f59e0b';
                $label = 'تنبيه صيانة';
            }

            $result[intval($building->id)] = [
                'status' => $status,
                'color' => $color,
                'label' => $label,
                'title' => $building->title,
                'manager_id' => intval($building->manager_id),
                'occupancy' => $stats,
            ];
        }

        return $result;
    }
}
