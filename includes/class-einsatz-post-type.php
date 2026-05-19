<?php
defined('ABSPATH') || exit;

class Einsatz_Post_Type {

    public function __construct() {
        add_action('init',               [__CLASS__, 'do_register']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('the_content',        [$this, 'prepend_meta_header']);
        add_filter('archive_template',   [$this, 'load_archive_template']);
        add_filter('manage_einsatz_posts_columns',       [$this, 'admin_columns']);
        add_action('manage_einsatz_posts_custom_column', [$this, 'admin_column_content'], 10, 2);
        add_filter('manage_edit-einsatz_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts', [$this, 'sort_by_einsatz_date']);
    }

    public static function do_register() {
        $labels = [
            'name'               => __('Einsätze',                   'wp-einsatz'),
            'singular_name'      => __('Einsatz',                    'wp-einsatz'),
            'menu_name'          => __('Einsätze',                   'wp-einsatz'),
            'add_new'            => __('Neuer Einsatz',              'wp-einsatz'),
            'add_new_item'       => __('Neuen Einsatz hinzufügen',   'wp-einsatz'),
            'edit_item'          => __('Einsatz bearbeiten',         'wp-einsatz'),
            'new_item'           => __('Neuer Einsatz',              'wp-einsatz'),
            'view_item'          => __('Einsatz ansehen',            'wp-einsatz'),
            'search_items'       => __('Einsätze suchen',            'wp-einsatz'),
            'not_found'          => __('Keine Einsätze gefunden',    'wp-einsatz'),
            'not_found_in_trash' => __('Keine Einsätze im Papierkorb', 'wp-einsatz'),
            'all_items'          => __('Alle Einsätze',              'wp-einsatz'),
        ];

        register_post_type('einsatz', [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'comments'],
            'rewrite'      => ['slug' => 'einsatz', 'with_front' => false],
            'menu_icon'    => 'dashicons-sos',
            'show_in_rest' => true,
            'menu_position'=> 5,
            'taxonomies'   => ['einsatz_kategorie'],
        ]);
    }

    public function enqueue_scripts() {
        if (is_singular('einsatz') || is_post_type_archive('einsatz')) {
            wp_enqueue_style(
                'einsatz-frontend',
                EINSATZ_PLUGIN_URL . 'assets/css/einsatz-frontend.css',
                [],
                EINSATZ_VERSION
            );
        }
        if (is_post_type_archive('einsatz')) {
            wp_enqueue_script(
                'einsatz-archive',
                EINSATZ_PLUGIN_URL . 'assets/js/einsatz-archive.js',
                ['jquery'],
                EINSATZ_VERSION,
                true
            );
            wp_localize_script('einsatz-archive', 'einsatzAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('einsatz_filter'),
            ]);
        }
    }

    public function prepend_meta_header($content) {
        if (!is_singular('einsatz') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $post_id = get_the_ID();
        ob_start();
        include EINSATZ_PLUGIN_DIR . 'templates/partials/einsatz-single-meta.php';
        return ob_get_clean() . $content;
    }

    public function load_archive_template($template) {
        if (is_post_type_archive('einsatz')) {
            $plugin_tpl = EINSATZ_PLUGIN_DIR . 'templates/archive-einsatz.php';
            if (file_exists($plugin_tpl)) {
                return $plugin_tpl;
            }
        }
        return $template;
    }

    public function admin_columns($columns) {
        $new = [];
        $new['cb']          = $columns['cb'];
        $new['einsatz_nr']  = __('Nr.', 'wp-einsatz');
        $new['title']       = $columns['title'];
        $new['alarmstufe']  = __('Alarmstufe', 'wp-einsatz');
        $new['einsatz_typ'] = __('Typ', 'wp-einsatz');
        $new['einsatz_dat'] = __('Alarmzeit', 'wp-einsatz');
        $new['einsatz_ort'] = __('Einsatzort', 'wp-einsatz');
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        $meta = Einsatz_Helpers::get_einsatz_meta($post_id);

        switch ($column) {
            case 'einsatz_nr':
                echo esc_html($meta['nummer'] ?: '—');
                break;
            case 'alarmstufe':
                if ($meta['alarmstufe']) {
                    $color   = Einsatz_Helpers::get_alarmstufe_color($meta['alarmstufe']);
                    $txtcol  = Einsatz_Helpers::get_alarmstufe_text_color($meta['alarmstufe']);
                    printf(
                        '<span class="einsatz-badge" style="background:%s;color:%s;padding:2px 8px;border-radius:3px;font-weight:700">%s</span>',
                        esc_attr($color),
                        esc_attr($txtcol),
                        esc_html($meta['alarmstufe'])
                    );
                } else {
                    echo '—';
                }
                break;
            case 'einsatz_typ':
                echo $meta['alarmstufe']
                    ? esc_html(Einsatz_Helpers::get_type_label($meta['alarmstufe']))
                    : '—';
                break;
            case 'einsatz_dat':
                echo $meta['datum']
                    ? esc_html(Einsatz_Helpers::format_date($meta['datum']))
                    : esc_html(get_the_date('d.m.Y H:i'));
                break;
            case 'einsatz_ort':
                echo esc_html($meta['ort'] ?: '—');
                break;
        }
    }

    public function sortable_columns($columns) {
        $columns['einsatz_dat'] = 'einsatz_datum';
        return $columns;
    }

    public function sort_by_einsatz_date($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('orderby') === 'einsatz_datum') {
            $query->set('meta_key', '_einsatz_datum');
            $query->set('orderby',  'meta_value');
        }
    }
}
