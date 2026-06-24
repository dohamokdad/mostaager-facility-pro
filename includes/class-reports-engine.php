<?php
if (!defined('ABSPATH')) exit;

class MS_Reports_Engine
{
    public static function init()
    {
        // lightweight init
    }

    public static function get_building_report($building_id, $force = false)
    {
        global $wpdb;
        $building_id = intval($building_id);
        if (!$building_id) return array();
        $cache_table = $wpdb->prefix . 'ms_report_cache';
        $period = 'default';

        if (!$force) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $cache_table WHERE report_type = %s AND entity_id = %d AND period = %s AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
                'building', $building_id, $period, current_time('mysql')
            ));
            if ($row && !empty($row->data)) {
                $res = maybe_unserialize($row->data);
                if (is_array($res) || is_object($res)) return $res;
            }
        }

        $inv = $wpdb->prefix . 'ms_invoices';
        $total = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv WHERE building_id = %d", $building_id)));
        $collected = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv WHERE building_id = %d AND status = %s", $building_id, 'paid')));
        $count_total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $inv WHERE building_id = %d", $building_id)));
        $count_paid = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $inv WHERE building_id = %d AND status = %s", $building_id, 'paid')));

        $report = array(
            'building_id' => $building_id,
            'total_amount' => $total,
            'collected' => $collected,
            'count_total' => $count_total,
            'count_paid' => $count_paid,
            'generated_at' => current_time('mysql'),
        );

        // cache for 1 hour
        $expires = date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $cache_table WHERE report_type = %s AND entity_id = %d AND period = %s LIMIT 1", 'building', $building_id, $period));
        if ($existing) {
            $wpdb->update($cache_table, ['data' => maybe_serialize($report), 'generated_at' => current_time('mysql'), 'expires_at' => $expires], ['id' => $existing], ['%s','%s','%s'], ['%d']);
        } else {
            $wpdb->insert($cache_table, ['report_type' => 'building', 'entity_id' => $building_id, 'period' => $period, 'data' => maybe_serialize($report), 'generated_at' => current_time('mysql'), 'expires_at' => $expires], ['%s','%d','%s','%s','%s','%s']);
        }

        return $report;
    }

    public static function export_csv($data)
    {
        if (empty($data) || !is_array($data)) return '';
        $fh = fopen('php://temp', 'r+');
        // header from keys of first row
        $first = reset($data);
        if (is_array($first)) {
            fputcsv($fh, array_keys($first));
            foreach ($data as $row) {
                fputcsv($fh, $row);
            }
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    public static function generate_invoice_pdf($invoice_id)
    {
        if (!class_exists('Mostager_Invoice_PDF')) return false;
        $pdf = new Mostager_Invoice_PDF();
        return $pdf->generate_invoice(intval($invoice_id), 'S');
    }
}

MS_Reports_Engine::init();
