<?php
defined('ABSPATH') || exit;

class Einsatz_Fahrzeuge {

    public function __construct() {
        add_action('init',               [$this, 'register_post_type']);
        add_action('add_meta_boxes',     [$this, 'add_meta_boxes']);
        add_action('save_post_einsatz_fahrzeug', [$this, 'save_meta'], 10, 2);
        add_filter('manage_einsatz_fahrzeug_posts_columns',       [$this, 'admin_columns']);
        add_action('manage_einsatz_fahrzeug_posts_custom_column', [$this, 'admin_column_content'], 10, 2);
    }

    public function register_post_type() {
        $labels = [
            'name'               => __('Fahrzeuge',                      'wp-einsatz'),
            'singular_name'      => __('Fahrzeug',                       'wp-einsatz'),
            'menu_name'          => __('Fahrzeuge',                      'wp-einsatz'),
            'add_new'            => __('Neues Fahrzeug',                 'wp-einsatz'),
            'add_new_item'       => __('Neues Fahrzeug hinzufügen',      'wp-einsatz'),
            'edit_item'          => __('Fahrzeug bearbeiten',            'wp-einsatz'),
            'not_found'          => __('Keine Fahrzeuge gefunden',       'wp-einsatz'),
            'not_found_in_trash' => __('Keine Fahrzeuge im Papierkorb', 'wp-einsatz'),
        ];

        register_post_type('einsatz_fahrzeug', [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'edit.php?post_type=einsatz',
            'supports'      => ['title'],
            'capability_type' => 'post',
            'show_in_rest'  => true,
        ]);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'fahrzeug_details',
            __('Fahrzeugdetails', 'wp-einsatz'),
            [$this, 'render_meta_box'],
            'einsatz_fahrzeug',
            'normal',
            'default'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('fahrzeug_meta_save', 'fahrzeug_meta_nonce');

        $funkrufname = get_post_meta($post->ID, '_fahrzeug_funkrufname', true);
        $typ         = get_post_meta($post->ID, '_fahrzeug_typ',         true);
        $besatzung   = get_post_meta($post->ID, '_fahrzeug_besatzung',   true);
        $aktiv       = get_post_meta($post->ID, '_fahrzeug_aktiv',       true);
        if ($aktiv === '') $aktiv = '1';

        $typen = [
            'HLF'  => 'HLF – Hilfeleistungslöschfahrzeug',
            'LF'   => 'LF – Löschfahrzeug',
            'TLF'  => 'TLF – Tanklöschfahrzeug',
            'DLK'  => 'DLK – Drehleiter',
            'RW'   => 'RW – Rüstwagen',
            'GW-G' => 'GW-G – Gefahrgut',
            'ELW'  => 'ELW – Einsatzleitwagen',
            'MTW'  => 'MTW – Mannschaftstransportwagen',
            'KLF'  => 'KLF – Kleinlöschfahrzeug',
            'GRTW' => 'GRTW – Gerätewagen Atemschutz/Strahlenschutz',
            'FWK'  => 'Feuerwehrkran',
            'Boot' => 'Boot',
            'Sonst'=> 'Sonstiges',
        ];
        ?>
        <div class="einsatz-admin-grid">
            <p>
                <label for="fahrzeug_funkrufname"><strong><?php esc_html_e('Funkrufname / Kennung', 'wp-einsatz'); ?></strong></label><br>
                <input type="text" id="fahrzeug_funkrufname" name="fahrzeug_funkrufname"
                       value="<?php echo esc_attr($funkrufname); ?>" class="widefat"
                       placeholder="z.B. Florian Muster 1/44/1">
                <span class="description"><?php esc_html_e('Offizieller Funkrufname, wird in Einsatzberichten angezeigt.', 'wp-einsatz'); ?></span>
            </p>
            <p>
                <label for="fahrzeug_typ"><strong><?php esc_html_e('Fahrzeugtyp', 'wp-einsatz'); ?></strong></label><br>
                <select id="fahrzeug_typ" name="fahrzeug_typ" class="widefat">
                    <option value=""><?php esc_html_e('— Bitte wählen —', 'wp-einsatz'); ?></option>
                    <?php foreach ($typen as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($typ, $val); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="fahrzeug_besatzung"><strong><?php esc_html_e('Regelbesatzung', 'wp-einsatz'); ?></strong></label><br>
                <input type="number" id="fahrzeug_besatzung" name="fahrzeug_besatzung"
                       value="<?php echo esc_attr($besatzung); ?>" min="0" max="20" style="width:80px">
                <?php esc_html_e('Personen', 'wp-einsatz'); ?>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="fahrzeug_aktiv" value="1" <?php checked($aktiv, '1'); ?>>
                    <strong><?php esc_html_e('Fahrzeug ist aktiv / einsatzbereit', 'wp-einsatz'); ?></strong>
                </label>
            </p>
        </div>
        <?php
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['fahrzeug_meta_nonce'])) return;
        if (!wp_verify_nonce($_POST['fahrzeug_meta_nonce'], 'fahrzeug_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = ['fahrzeug_funkrufname', 'fahrzeug_typ'];
        foreach ($fields as $field) {
            $meta_key = '_' . $field;
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }

        update_post_meta($post_id, '_fahrzeug_besatzung', absint($_POST['fahrzeug_besatzung'] ?? 0));
        update_post_meta($post_id, '_fahrzeug_aktiv', isset($_POST['fahrzeug_aktiv']) ? '1' : '0');
    }

    public function admin_columns($columns) {
        return [
            'cb'                => $columns['cb'],
            'title'             => __('Bezeichnung', 'wp-einsatz'),
            'fahrzeug_funkruf'  => __('Funkrufname', 'wp-einsatz'),
            'fahrzeug_typ'      => __('Typ', 'wp-einsatz'),
            'fahrzeug_besatz'   => __('Besatzung', 'wp-einsatz'),
            'fahrzeug_aktiv'    => __('Status', 'wp-einsatz'),
        ];
    }

    public function admin_column_content($column, $post_id) {
        switch ($column) {
            case 'fahrzeug_funkruf':
                echo esc_html(get_post_meta($post_id, '_fahrzeug_funkrufname', true) ?: '—');
                break;
            case 'fahrzeug_typ':
                echo esc_html(get_post_meta($post_id, '_fahrzeug_typ', true) ?: '—');
                break;
            case 'fahrzeug_besatz':
                $b = get_post_meta($post_id, '_fahrzeug_besatzung', true);
                echo $b ? esc_html($b) . ' Pers.' : '—';
                break;
            case 'fahrzeug_aktiv':
                $aktiv = get_post_meta($post_id, '_fahrzeug_aktiv', true);
                if ($aktiv === '' || $aktiv === '1') {
                    echo '<span style="color:#22c55e;font-weight:600">&#10003; Aktiv</span>';
                } else {
                    echo '<span style="color:#94a3b8">&#10005; Inaktiv</span>';
                }
                break;
        }
    }
}
