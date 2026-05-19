<?php
defined('ABSPATH') || exit;

class Einsatz_Helpers {

    public static function get_alarmstufen() {
        return ['B1', 'B2', 'B3', 'T1', 'T2', 'T3', 'S1', 'S2', 'S3'];
    }

    public static function get_alarmstufe_prefix($stufe) {
        return strtoupper(substr(trim($stufe), 0, 1));
    }

    public static function get_alarmstufe_type($stufe) {
        $map = ['B' => 'Brand', 'T' => 'Technisch', 'S' => 'Schadstoff'];
        return $map[self::get_alarmstufe_prefix($stufe)] ?? 'Unbekannt';
    }

    public static function get_alarmstufe_slug($stufe) {
        $map = ['B' => 'brand', 'T' => 'technisch', 'S' => 'schadstoff'];
        return $map[self::get_alarmstufe_prefix($stufe)] ?? '';
    }

    public static function get_alarmstufe_color($stufe) {
        $prefix = self::get_alarmstufe_prefix($stufe);
        $level  = (int) substr(trim($stufe), 1);

        $colors = [
            'B' => ['#fca5a5', '#ef4444', '#991b1b'],
            'T' => ['#fde68a', '#f59e0b', '#92400e'],
            'S' => ['#86efac', '#22c55e', '#14532d'],
        ];

        if (isset($colors[$prefix]) && $level >= 1 && $level <= 3) {
            return $colors[$prefix][$level - 1];
        }
        return '#94a3b8';
    }

    public static function get_alarmstufe_text_color($stufe) {
        $prefix = self::get_alarmstufe_prefix($stufe);
        $level  = (int) substr(trim($stufe), 1);
        // Darker shades (level 2+) need white text
        if ($level >= 2) {
            return '#ffffff';
        }
        $dark_text = ['B' => '#7f1d1d', 'T' => '#78350f', 'S' => '#14532d'];
        return $dark_text[$prefix] ?? '#1e293b';
    }

    public static function get_type_icon($stufe) {
        $map = ['B' => '&#128293;', 'T' => '&#9881;', 'S' => '&#9763;'];
        return $map[self::get_alarmstufe_prefix($stufe)] ?? '&#128658;';
    }

    public static function get_type_label($stufe) {
        $prefix = self::get_alarmstufe_prefix($stufe);
        $labels = [
            'B' => 'Brand',
            'T' => 'Technische Hilfeleistung',
            'S' => 'Schadstoff / ABC',
        ];
        return $labels[$prefix] ?? 'Sonstiger Einsatz';
    }

    public static function format_date($date) {
        if (empty($date)) return '';
        $ts = strtotime($date);
        return $ts ? date_i18n('d.m.Y H:i', $ts) . ' Uhr' : esc_html($date);
    }

    public static function format_date_short($date) {
        if (empty($date)) return '';
        $ts = strtotime($date);
        return $ts ? date_i18n('d.m.Y', $ts) : esc_html($date);
    }

    public static function get_duration_string($start, $end) {
        if (empty($start) || empty($end)) return '';
        $diff = strtotime($end) - strtotime($start);
        if ($diff <= 0) return '';
        $hours   = (int) floor($diff / 3600);
        $minutes = (int) floor(($diff % 3600) / 60);
        if ($hours > 0) {
            return sprintf('%d Std. %d Min.', $hours, $minutes);
        }
        return sprintf('%d Min.', $minutes);
    }

    public static function get_einsatz_meta($post_id) {
        return [
            'alarmstufe'    => get_post_meta($post_id, '_einsatz_alarmstufe',    true),
            'datum'         => get_post_meta($post_id, '_einsatz_datum',         true),
            'einsatzende'   => get_post_meta($post_id, '_einsatz_einsatzende',   true),
            'ort'           => get_post_meta($post_id, '_einsatz_ort',           true),
            'stichwort'     => get_post_meta($post_id, '_einsatz_stichwort',     true),
            'einsatzleiter' => get_post_meta($post_id, '_einsatz_einsatzleiter', true),
            'kraefte'       => get_post_meta($post_id, '_einsatz_kraefte',       true),
            'fahrzeuge'     => get_post_meta($post_id, '_einsatz_fahrzeuge',     true) ?: [],
            'nummer'        => get_post_meta($post_id, '_einsatz_nummer',        true),
        ];
    }

    public static function get_fahrzeug_names($fahrzeug_ids) {
        if (empty($fahrzeug_ids) || !is_array($fahrzeug_ids)) return [];
        $names = [];
        foreach ($fahrzeug_ids as $id) {
            $post = get_post((int) $id);
            if ($post && $post->post_type === 'einsatz_fahrzeug') {
                $funkruf = get_post_meta($post->ID, '_fahrzeug_funkrufname', true);
                $names[] = $funkruf ? esc_html($funkruf) : esc_html($post->post_title);
            }
        }
        return $names;
    }

    public static function get_available_years() {
        global $wpdb;
        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(meta_value)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_einsatz_datum'
               AND meta_value != ''
             ORDER BY 1 DESC"
        );
        if (empty($years)) {
            $years = $wpdb->get_col(
                "SELECT DISTINCT YEAR(post_date)
                 FROM {$wpdb->posts}
                 WHERE post_type = 'einsatz'
                   AND post_status = 'publish'
                 ORDER BY 1 DESC"
            );
        }
        return array_filter($years);
    }
}
