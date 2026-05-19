<?php
defined('ABSPATH') || exit;

class Einsatz_Meta {

    public function __construct() {
        add_action('init',              [$this, 'register_meta']);
        add_action('add_meta_boxes',    [$this, 'add_meta_boxes']);
        add_action('save_post_einsatz', [$this, 'save_meta'], 10, 2);
        add_action('wp_head',           [$this, 'output_schema_org']);
    }

    public function register_meta() {
        $string_fields = [
            '_einsatz_alarmstufe',
            '_einsatz_datum',
            '_einsatz_einsatzende',
            '_einsatz_ort',
            '_einsatz_stichwort',
            '_einsatz_einsatzleiter',
            '_einsatz_nummer',
        ];

        foreach ($string_fields as $key) {
            register_post_meta('einsatz', $key, [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ]);
        }

        register_post_meta('einsatz', '_einsatz_kraefte', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'einsatz_details',
            __('Einsatzdetails', 'wp-einsatz'),
            [$this, 'render_meta_box'],
            'einsatz',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('einsatz_meta_save', 'einsatz_meta_nonce');

        $meta = Einsatz_Helpers::get_einsatz_meta($post->ID);

        $available_fahrzeuge = get_posts([
            'post_type'      => 'einsatz_fahrzeug',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);

        wp_enqueue_style(
            'einsatz-admin',
            EINSATZ_PLUGIN_URL . 'assets/css/einsatz-admin.css',
            [],
            EINSATZ_VERSION
        );

        include EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box.php';
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['einsatz_meta_nonce'])) return;
        if (!wp_verify_nonce($_POST['einsatz_meta_nonce'], 'einsatz_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $text_fields = [
            'einsatz_alarmstufe'    => '_einsatz_alarmstufe',
            'einsatz_datum'         => '_einsatz_datum',
            'einsatz_einsatzende'   => '_einsatz_einsatzende',
            'einsatz_ort'           => '_einsatz_ort',
            'einsatz_stichwort'     => '_einsatz_stichwort',
            'einsatz_einsatzleiter' => '_einsatz_einsatzleiter',
            'einsatz_nummer'        => '_einsatz_nummer',
        ];

        foreach ($text_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }

        if (isset($_POST['einsatz_kraefte'])) {
            update_post_meta($post_id, '_einsatz_kraefte', absint($_POST['einsatz_kraefte']));
        }

        if (isset($_POST['einsatz_fahrzeuge']) && is_array($_POST['einsatz_fahrzeuge'])) {
            $ids = array_map('absint', $_POST['einsatz_fahrzeuge']);
            $ids = array_filter($ids);
            update_post_meta($post_id, '_einsatz_fahrzeuge', $ids);
        } else {
            update_post_meta($post_id, '_einsatz_fahrzeuge', []);
        }

        // Auto-set taxonomy category based on Alarmstufe prefix
        if (!empty($_POST['einsatz_alarmstufe'])) {
            $stufe = sanitize_text_field($_POST['einsatz_alarmstufe']);
            self::auto_set_category($post_id, $stufe);
        }
    }

    public static function auto_set_category($post_id, $alarmstufe) {
        $slug = Einsatz_Helpers::get_alarmstufe_slug($alarmstufe);
        if (!$slug) return;

        $term = get_term_by('slug', $slug, 'einsatz_kategorie');
        if ($term) {
            wp_set_object_terms($post_id, (int) $term->term_id, 'einsatz_kategorie', false);
        }
    }

    public function output_schema_org() {
        if (!is_singular('einsatz')) return;
        $post_id = get_the_ID();
        $meta    = Einsatz_Helpers::get_einsatz_meta($post_id);

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'NewsArticle',
            'headline'    => get_the_title($post_id),
            'datePublished' => $meta['datum'] ?: get_the_date('c', $post_id),
            'description' => $meta['stichwort'] ?: get_the_excerpt($post_id),
            'author'      => ['@type' => 'Organization', 'name' => get_bloginfo('name')],
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
