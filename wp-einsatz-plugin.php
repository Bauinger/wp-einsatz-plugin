<?php
/**
 * Plugin Name: WP Einsatz Plugin
 * Plugin URI:  https://github.com/bauinger/wp-einsatz-plugin
 * Description: Einsatzberichte für Feuerwehr – mit benutzerdefinierten Feldern, Statistik, Archiv und NORA API Integration.
 * Version:     1.0.0
 * Author:      Bauinger
 * Text Domain: wp-einsatz
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('EINSATZ_VERSION',    '1.0.0');
define('EINSATZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EINSATZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EINSATZ_PLUGIN_FILE', __FILE__);

require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-helpers.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-post-type.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-taxonomy.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-meta.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-fahrzeuge.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-shortcodes.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-api.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-settings.php';
require_once EINSATZ_PLUGIN_DIR . 'includes/class-einsatz-widgets.php';

final class WP_Einsatz_Plugin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init',           [$this, 'load_textdomain']);
    }

    public function init() {
        new Einsatz_Post_Type();
        new Einsatz_Taxonomy();
        new Einsatz_Meta();
        new Einsatz_Fahrzeuge();
        new Einsatz_Shortcodes();
        new Einsatz_API();
        new Einsatz_Settings();
        new Einsatz_Widgets();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-einsatz',
            false,
            dirname(plugin_basename(EINSATZ_PLUGIN_FILE)) . '/languages'
        );
    }

    public static function activate() {
        Einsatz_Post_Type::do_register();
        Einsatz_Taxonomy::do_register();
        Einsatz_Taxonomy::insert_default_categories();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

register_activation_hook(EINSATZ_PLUGIN_FILE,   ['WP_Einsatz_Plugin', 'activate']);
register_deactivation_hook(EINSATZ_PLUGIN_FILE, ['WP_Einsatz_Plugin', 'deactivate']);

WP_Einsatz_Plugin::instance();
