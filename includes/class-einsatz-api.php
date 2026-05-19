<?php
defined('ABSPATH') || exit;

/**
 * REST API endpoint für NORA App Integration.
 *
 * Basis-URL: /wp-json/einsatz/v1/
 *
 * Endpunkte:
 *   POST /einsatz       – Neuen Einsatz anlegen (API-Key erforderlich)
 *   GET  /einsatz       – Letzte Einsätze abrufen (API-Key erforderlich)
 *   GET  /einsatz/{id}  – Einzelnen Einsatz abrufen (API-Key erforderlich)
 *
 * Authentifizierung:
 *   Header: X-Einsatz-API-Key: <key>
 *   (Der Key wird unter Einstellungen → Einsatz-Plugin konfiguriert)
 *
 * POST-Body (JSON):
 * {
 *   "titel":          "Wohnhausbrand",          // Pflicht
 *   "alarmstufe":     "B2",                     // B1-B3, T1-T3, S1-S3
 *   "stichwort":      "Brand in einer Wohnung",
 *   "ort":            "Musterstraße 1, 12345 Stadt",
 *   "alarmzeit":      "2024-01-15T14:30:00",    // ISO 8601
 *   "einsatzende":    "2024-01-15T17:45:00",
 *   "bericht":        "Berichtstext...",
 *   "einsatzleiter":  "GF Mustermann",
 *   "kraefte":        24,
 *   "fahrzeuge":      ["Florian Muster 1/44/1", "Florian Muster 1/23/1"],
 *   "nummer":         "E-2024-0042",
 *   "veroeffentlichen": true                    // Standard: true
 * }
 */
class Einsatz_API {

    const NAMESPACE = 'einsatz/v1';
    const ROUTE     = '/einsatz';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_einsaetze'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => [
                    'anzahl' => [
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ],
                    'seite' => [
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'jahr' => [
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_einsatz'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => $this->get_create_args(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, self::ROUTE . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_einsatz'],
                'permission_callback' => [$this, 'check_api_key'],
                'args'                => [
                    'id' => [
                        'validate_callback' => function($v) { return is_numeric($v); },
                    ],
                ],
            ],
        ]);
    }

    public function check_api_key(WP_REST_Request $request) {
        $stored_key = get_option('einsatz_api_key', '');

        if (empty($stored_key)) {
            return new WP_Error(
                'api_key_not_configured',
                __('API-Key ist nicht konfiguriert. Bitte unter Einstellungen → Einsatz-Plugin einrichten.', 'wp-einsatz'),
                ['status' => 503]
            );
        }

        $provided_key = $request->get_header('X-Einsatz-API-Key');
        if (empty($provided_key)) {
            // Try Authorization: Bearer <key> as fallback
            $auth = $request->get_header('Authorization');
            if ($auth && str_starts_with($auth, 'Bearer ')) {
                $provided_key = substr($auth, 7);
            }
        }

        if (empty($provided_key) || !hash_equals($stored_key, $provided_key)) {
            return new WP_Error(
                'invalid_api_key',
                __('Ungültiger oder fehlender API-Key.', 'wp-einsatz'),
                ['status' => 401]
            );
        }

        return true;
    }

    public function create_einsatz(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $titel = sanitize_text_field($params['titel'] ?? '');
        if (empty($titel)) {
            return new WP_Error('missing_title', __('Titel ist erforderlich.', 'wp-einsatz'), ['status' => 400]);
        }

        $alarmstufe = isset($params['alarmstufe'])
            ? strtoupper(sanitize_text_field($params['alarmstufe']))
            : '';

        if ($alarmstufe && !in_array($alarmstufe, Einsatz_Helpers::get_alarmstufen(), true)) {
            return new WP_Error(
                'invalid_alarmstufe',
                sprintf(
                    __('Ungültige Alarmstufe "%s". Erlaubt: %s', 'wp-einsatz'),
                    $alarmstufe,
                    implode(', ', Einsatz_Helpers::get_alarmstufen())
                ),
                ['status' => 400]
            );
        }

        $publish = isset($params['veroeffentlichen']) ? (bool) $params['veroeffentlichen'] : true;
        $status  = $publish ? 'publish' : 'draft';

        $bericht = isset($params['bericht'])
            ? wp_kses_post($params['bericht'])
            : '';

        $post_id = wp_insert_post([
            'post_title'   => $titel,
            'post_content' => $bericht,
            'post_status'  => $status,
            'post_type'    => 'einsatz',
        ], true);

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'create_failed',
                $post_id->get_error_message(),
                ['status' => 500]
            );
        }

        // Save meta fields
        $meta_map = [
            '_einsatz_alarmstufe'    => $alarmstufe,
            '_einsatz_datum'         => sanitize_text_field($params['alarmzeit']     ?? ''),
            '_einsatz_einsatzende'   => sanitize_text_field($params['einsatzende']   ?? ''),
            '_einsatz_ort'           => sanitize_text_field($params['ort']           ?? ''),
            '_einsatz_stichwort'     => sanitize_text_field($params['stichwort']     ?? ''),
            '_einsatz_einsatzleiter' => sanitize_text_field($params['einsatzleiter'] ?? ''),
            '_einsatz_kraefte'       => absint($params['kraefte'] ?? 0),
            '_einsatz_nummer'        => sanitize_text_field($params['nummer']        ?? ''),
        ];

        foreach ($meta_map as $key => $value) {
            if ($value !== '' && $value !== 0) {
                update_post_meta($post_id, $key, $value);
            }
        }

        // Resolve Fahrzeuge by Funkrufname
        if (!empty($params['fahrzeuge']) && is_array($params['fahrzeuge'])) {
            $fahrzeug_ids   = [];
            $unmatched_names = [];

            foreach ($params['fahrzeuge'] as $name) {
                $name = sanitize_text_field($name);
                $found = $this->find_fahrzeug_by_name($name);
                if ($found) {
                    $fahrzeug_ids[] = $found;
                } else {
                    $unmatched_names[] = $name;
                }
            }

            if (!empty($fahrzeug_ids)) {
                update_post_meta($post_id, '_einsatz_fahrzeuge', $fahrzeug_ids);
            }
            if (!empty($unmatched_names)) {
                update_post_meta($post_id, '_einsatz_fahrzeuge_text', implode(', ', $unmatched_names));
            }
        }

        // Auto-assign taxonomy category
        if ($alarmstufe) {
            Einsatz_Meta::auto_set_category($post_id, $alarmstufe);
        }

        return new WP_REST_Response([
            'success' => true,
            'id'      => $post_id,
            'url'     => get_permalink($post_id),
            'status'  => $status,
            'message' => __('Einsatz wurde erfolgreich angelegt.', 'wp-einsatz'),
        ], 201);
    }

    public function get_einsaetze(WP_REST_Request $request) {
        $args = [
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => min((int) $request->get_param('anzahl'), 100),
            'paged'          => (int) $request->get_param('seite'),
            'orderby'        => 'meta_value',
            'meta_key'       => '_einsatz_datum',
            'order'          => 'DESC',
        ];

        $jahr = $request->get_param('jahr');
        if ($jahr) {
            $args['date_query'] = [['year' => $jahr]];
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = $this->format_einsatz($post->ID);
        }

        return new WP_REST_Response([
            'gesamt'  => $query->found_posts,
            'seiten'  => $query->max_num_pages,
            'einsaetze' => $items,
        ]);
    }

    public function get_einsatz(WP_REST_Request $request) {
        $post_id = (int) $request->get_param('id');
        $post    = get_post($post_id);

        if (!$post || $post->post_type !== 'einsatz') {
            return new WP_Error('not_found', __('Einsatz nicht gefunden.', 'wp-einsatz'), ['status' => 404]);
        }

        return new WP_REST_Response($this->format_einsatz($post_id));
    }

    private function format_einsatz($post_id) {
        $post = get_post($post_id);
        $meta = Einsatz_Helpers::get_einsatz_meta($post_id);

        $fahrzeuge_names = Einsatz_Helpers::get_fahrzeug_names($meta['fahrzeuge']);
        $text_fahrzeuge  = get_post_meta($post_id, '_einsatz_fahrzeuge_text', true);
        if ($text_fahrzeuge) {
            $fahrzeuge_names = array_merge($fahrzeuge_names, explode(', ', $text_fahrzeuge));
        }

        return [
            'id'            => $post_id,
            'titel'         => $post->post_title,
            'url'           => get_permalink($post_id),
            'bericht'       => $post->post_content,
            'alarmstufe'    => $meta['alarmstufe'],
            'typ'           => $meta['alarmstufe'] ? Einsatz_Helpers::get_type_label($meta['alarmstufe']) : '',
            'stichwort'     => $meta['stichwort'],
            'ort'           => $meta['ort'],
            'alarmzeit'     => $meta['datum'],
            'einsatzende'   => $meta['einsatzende'],
            'einsatzleiter' => $meta['einsatzleiter'],
            'kraefte'       => (int) $meta['kraefte'],
            'fahrzeuge'     => $fahrzeuge_names,
            'nummer'        => $meta['nummer'],
            'veroeffentlicht' => get_post_status($post_id) === 'publish',
        ];
    }

    private function find_fahrzeug_by_name($name) {
        // Try exact title match
        $posts = get_posts([
            'post_type'      => 'einsatz_fahrzeug',
            'title'          => $name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]);
        if (!empty($posts)) return $posts[0];

        // Try Funkrufname meta
        $posts = get_posts([
            'post_type'      => 'einsatz_fahrzeug',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'meta_query'     => [
                ['key' => '_fahrzeug_funkrufname', 'value' => $name, 'compare' => '='],
            ],
        ]);
        if (!empty($posts)) return $posts[0];

        return null;
    }

    private function get_create_args() {
        return [
            'titel' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'alarmstufe' => [
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($v) {
                    return empty($v) || in_array(strtoupper($v), Einsatz_Helpers::get_alarmstufen(), true);
                },
            ],
            'stichwort'     => ['sanitize_callback' => 'sanitize_text_field'],
            'ort'           => ['sanitize_callback' => 'sanitize_text_field'],
            'alarmzeit'     => ['sanitize_callback' => 'sanitize_text_field'],
            'einsatzende'   => ['sanitize_callback' => 'sanitize_text_field'],
            'bericht'       => ['sanitize_callback' => 'wp_kses_post'],
            'einsatzleiter' => ['sanitize_callback' => 'sanitize_text_field'],
            'nummer'        => ['sanitize_callback' => 'sanitize_text_field'],
        ];
    }
}
