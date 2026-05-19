<?php
defined('ABSPATH') || exit;

class Einsatz_Taxonomy {

    public function __construct() {
        add_action('init', [__CLASS__, 'do_register']);
    }

    public static function do_register() {
        register_taxonomy('einsatz_kategorie', 'einsatz', [
            'labels' => [
                'name'              => __('Einsatzkategorien',   'wp-einsatz'),
                'singular_name'     => __('Einsatzkategorie',    'wp-einsatz'),
                'menu_name'         => __('Kategorien',          'wp-einsatz'),
                'all_items'         => __('Alle Kategorien',     'wp-einsatz'),
                'edit_item'         => __('Kategorie bearbeiten','wp-einsatz'),
                'add_new_item'      => __('Neue Kategorie',      'wp-einsatz'),
                'search_items'      => __('Kategorien suchen',   'wp-einsatz'),
                'not_found'         => __('Keine Kategorien gefunden', 'wp-einsatz'),
            ],
            'hierarchical'  => true,
            'public'        => true,
            'show_in_rest'  => true,
            'rewrite'       => ['slug' => 'einsatz-kategorie'],
            'show_ui'       => true,
            'show_in_menu'  => true,
        ]);
    }

    public static function insert_default_categories() {
        $categories = [
            ['name' => 'Brand',                    'slug' => 'brand'],
            ['name' => 'Technische Hilfeleistung', 'slug' => 'technisch'],
            ['name' => 'Schadstoff / ABC',         'slug' => 'schadstoff'],
        ];

        foreach ($categories as $cat) {
            if (!term_exists($cat['name'], 'einsatz_kategorie')) {
                wp_insert_term($cat['name'], 'einsatz_kategorie', ['slug' => $cat['slug']]);
            }
        }
    }
}
