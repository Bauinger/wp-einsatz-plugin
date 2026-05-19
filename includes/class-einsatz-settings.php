<?php
defined('ABSPATH') || exit;

class Einsatz_Settings {

    const OPTION_GROUP = 'einsatz_settings';
    const PAGE_SLUG    = 'einsatz-einstellungen';

    public function __construct() {
        add_action('admin_menu',    [$this, 'add_settings_page']);
        add_action('admin_init',    [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('plugin_action_links_' . plugin_basename(EINSATZ_PLUGIN_FILE), [$this, 'add_plugin_links']);
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=einsatz',
            __('Einsatz-Plugin Einstellungen', 'wp-einsatz'),
            __('Einstellungen', 'wp-einsatz'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_settings() {
        register_setting(self::OPTION_GROUP, 'einsatz_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting(self::OPTION_GROUP, 'einsatz_api_enabled', [
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);
        register_setting(self::OPTION_GROUP, 'einsatz_default_status', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'publish',
        ]);

        add_settings_section(
            'einsatz_api_section',
            __('NORA API Konfiguration', 'wp-einsatz'),
            [$this, 'render_api_section'],
            self::PAGE_SLUG
        );

        add_settings_field('einsatz_api_enabled', __('API aktivieren', 'wp-einsatz'),
            [$this, 'field_api_enabled'], self::PAGE_SLUG, 'einsatz_api_section');

        add_settings_field('einsatz_api_key', __('API-Schlüssel', 'wp-einsatz'),
            [$this, 'field_api_key'], self::PAGE_SLUG, 'einsatz_api_section');

        add_settings_section(
            'einsatz_general_section',
            __('Allgemeine Einstellungen', 'wp-einsatz'),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field('einsatz_default_status', __('Standard-Status neuer Einsätze', 'wp-einsatz'),
            [$this, 'field_default_status'], self::PAGE_SLUG, 'einsatz_general_section');
    }

    public function render_api_section() {
        echo '<p>' . esc_html__('Konfiguriere die REST API für die NORA App Integration.', 'wp-einsatz') . '</p>';
        $url = esc_url(rest_url('einsatz/v1/einsatz'));
        echo '<p><strong>' . esc_html__('API-Endpunkt:', 'wp-einsatz') . '</strong> ';
        echo '<code>' . $url . '</code></p>';
    }

    public function field_api_enabled() {
        $val = get_option('einsatz_api_enabled', 1);
        ?>
        <label>
            <input type="checkbox" name="einsatz_api_enabled" value="1" <?php checked($val, 1); ?>>
            <?php esc_html_e('NORA REST API aktivieren', 'wp-einsatz'); ?>
        </label>
        <?php
    }

    public function field_api_key() {
        $key = get_option('einsatz_api_key', '');
        ?>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" name="einsatz_api_key" id="einsatz_api_key"
                   value="<?php echo esc_attr($key); ?>" class="regular-text"
                   autocomplete="off" style="font-family:monospace">
            <button type="button" class="button" id="einsatz-generate-key">
                <?php esc_html_e('Neuen Key generieren', 'wp-einsatz'); ?>
            </button>
            <button type="button" class="button" id="einsatz-copy-key" <?php echo empty($key) ? 'disabled' : ''; ?>>
                <?php esc_html_e('Kopieren', 'wp-einsatz'); ?>
            </button>
        </div>
        <p class="description">
            <?php esc_html_e('Sende diesen Key im HTTP-Header: ', 'wp-einsatz'); ?>
            <code>X-Einsatz-API-Key: &lt;key&gt;</code>
        </p>
        <script>
        document.getElementById('einsatz-generate-key').addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let key = '';
            for (let i = 0; i < 48; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
            const field = document.getElementById('einsatz_api_key');
            field.value = key;
            document.getElementById('einsatz-copy-key').disabled = false;
        });
        document.getElementById('einsatz-copy-key').addEventListener('click', function() {
            const field = document.getElementById('einsatz_api_key');
            navigator.clipboard.writeText(field.value).then(() => {
                this.textContent = '✓ Kopiert!';
                setTimeout(() => this.textContent = '<?php esc_html_e('Kopieren', 'wp-einsatz'); ?>', 2000);
            });
        });
        </script>
        <?php
    }

    public function field_default_status() {
        $val = get_option('einsatz_default_status', 'publish');
        ?>
        <select name="einsatz_default_status">
            <option value="publish" <?php selected($val, 'publish'); ?>><?php esc_html_e('Sofort veröffentlichen', 'wp-einsatz'); ?></option>
            <option value="draft"   <?php selected($val, 'draft'); ?>>  <?php esc_html_e('Als Entwurf speichern', 'wp-einsatz'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Gilt für über die NORA API erstellte Einsätze (sofern nicht im API-Request überschrieben).', 'wp-einsatz'); ?></p>
        <?php
    }

    public function render_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap einsatz-settings-wrap">
            <h1><?php esc_html_e('Einsatz-Plugin Einstellungen', 'wp-einsatz'); ?></h1>

            <?php settings_errors(); ?>

            <div class="einsatz-settings-layout">
                <div class="einsatz-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(self::OPTION_GROUP);
                        do_settings_sections(self::PAGE_SLUG);
                        submit_button(__('Einstellungen speichern', 'wp-einsatz'));
                        ?>
                    </form>
                </div>

                <div class="einsatz-settings-sidebar">
                    <div class="einsatz-info-box">
                        <h3><?php esc_html_e('API Dokumentation', 'wp-einsatz'); ?></h3>
                        <p><?php esc_html_e('Verfügbare Endpunkte:', 'wp-einsatz'); ?></p>
                        <ul>
                            <li><code>POST <?php echo esc_html(rest_url('einsatz/v1/einsatz')); ?></code><br>
                                <small><?php esc_html_e('Neuen Einsatz anlegen', 'wp-einsatz'); ?></small></li>
                            <li><code>GET <?php echo esc_html(rest_url('einsatz/v1/einsatz')); ?></code><br>
                                <small><?php esc_html_e('Einsätze abrufen', 'wp-einsatz'); ?></small></li>
                            <li><code>GET <?php echo esc_html(rest_url('einsatz/v1/einsatz/{id}')); ?></code><br>
                                <small><?php esc_html_e('Einzelnen Einsatz abrufen', 'wp-einsatz'); ?></small></li>
                        </ul>
                        <p><strong><?php esc_html_e('Authentication Header:', 'wp-einsatz'); ?></strong><br>
                        <code>X-Einsatz-API-Key: &lt;key&gt;</code></p>
                    </div>

                    <div class="einsatz-info-box">
                        <h3><?php esc_html_e('Shortcodes', 'wp-einsatz'); ?></h3>
                        <ul>
                            <li><code>[einsatz_statistik]</code><br>
                                <small><?php esc_html_e('Jahresstatistik', 'wp-einsatz'); ?></small></li>
                            <li><code>[einsatz_archiv]</code><br>
                                <small><?php esc_html_e('Filterbares Archiv', 'wp-einsatz'); ?></small></li>
                            <li><code>[einsatz_liste anzahl="5"]</code><br>
                                <small><?php esc_html_e('Letzte X Einsätze', 'wp-einsatz'); ?></small></li>
                        </ul>
                    </div>

                    <div class="einsatz-info-box">
                        <h3><?php esc_html_e('Plugin-Version', 'wp-einsatz'); ?></h3>
                        <p><?php echo esc_html(EINSATZ_VERSION); ?></p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=einsatz')); ?>" class="button button-primary">
                                <?php esc_html_e('+ Neuer Einsatz', 'wp-einsatz'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if (str_contains($hook, self::PAGE_SLUG) || get_current_screen()?->post_type === 'einsatz') {
            wp_enqueue_style(
                'einsatz-admin',
                EINSATZ_PLUGIN_URL . 'assets/css/einsatz-admin.css',
                [],
                EINSATZ_VERSION
            );
        }
    }

    public function add_plugin_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('edit.php?post_type=einsatz&page=' . self::PAGE_SLUG)),
            esc_html__('Einstellungen', 'wp-einsatz')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
}
