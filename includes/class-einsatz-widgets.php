<?php
defined('ABSPATH') || exit;

class Einsatz_Widgets {

    public function __construct() {
        add_action('widgets_init', [$this, 'register_widgets']);
    }

    public function register_widgets() {
        register_widget('Einsatz_Widget_Letzte');
        register_widget('Einsatz_Widget_Statistik');
        register_widget('Einsatz_Widget_Aktuell');
    }
}

// ── Widget 1: Letzte Einsätze ─────────────────────────────────────────────────

class Einsatz_Widget_Letzte extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'einsatz_widget_letzte',
            __('Einsätze – Letzte Einsätze', 'wp-einsatz'),
            [
                'description' => __('Zeigt die letzten X Einsätze als Liste', 'wp-einsatz'),
                'classname'   => 'widget-einsatz-letzte',
            ]
        );
    }

    public function widget($args, $instance) {
        $title   = apply_filters('widget_title', $instance['title'] ?? '');
        $anzahl  = (int) ($instance['anzahl'] ?? 5);
        $mit_bild= !empty($instance['mit_bild']);

        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $query = new WP_Query([
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => $anzahl,
            'orderby'        => 'meta_value',
            'meta_key'       => '_einsatz_datum',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        if ($query->have_posts()) :
            echo '<ul class="einsatz-widget-list">';
            while ($query->have_posts()) : $query->the_post();
                $post_id = get_the_ID();
                $meta    = Einsatz_Helpers::get_einsatz_meta($post_id);
                $stufe   = $meta['alarmstufe'];
                $color   = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe)      : '#94a3b8';
                $txtcol  = $stufe ? Einsatz_Helpers::get_alarmstufe_text_color($stufe) : '#fff';
                $datum   = $meta['datum']
                    ? Einsatz_Helpers::format_date_short($meta['datum'])
                    : get_the_date('d.m.Y');
                ?>
                <li class="einsatz-widget-item">
                    <?php if ($mit_bild && has_post_thumbnail()) : ?>
                        <a href="<?php the_permalink(); ?>" class="einsatz-widget-thumb">
                            <?php the_post_thumbnail('thumbnail', ['loading' => 'lazy', 'alt' => get_the_title()]); ?>
                        </a>
                    <?php endif; ?>
                    <div class="einsatz-widget-info">
                        <?php if ($stufe) : ?>
                            <span class="einsatz-badge einsatz-badge--sm"
                                  style="background:<?php echo esc_attr($color); ?>;color:<?php echo esc_attr($txtcol); ?>">
                                <?php echo esc_html($stufe); ?>
                            </span>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="einsatz-widget-title">
                            <?php the_title(); ?>
                        </a>
                        <span class="einsatz-widget-date"><?php echo esc_html($datum); ?></span>
                        <?php if ($meta['stichwort']) : ?>
                            <span class="einsatz-widget-stichwort"><?php echo esc_html($meta['stichwort']); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php
            endwhile;
            wp_reset_postdata();
            echo '</ul>';

            $archive_url = get_post_type_archive_link('einsatz');
            if ($archive_url) {
                printf(
                    '<a href="%s" class="einsatz-widget-more">%s &rarr;</a>',
                    esc_url($archive_url),
                    esc_html__('Alle Einsätze', 'wp-einsatz')
                );
            }
        else :
            echo '<p class="einsatz-widget-empty">' . esc_html__('Keine Einsätze vorhanden.', 'wp-einsatz') . '</p>';
        endif;

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title    = $instance['title']    ?? __('Letzte Einsätze', 'wp-einsatz');
        $anzahl   = $instance['anzahl']   ?? 5;
        $mit_bild = $instance['mit_bild'] ?? 0;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Titel:', 'wp-einsatz'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('anzahl')); ?>">
                <?php esc_html_e('Anzahl anzeigen:', 'wp-einsatz'); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('anzahl')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('anzahl')); ?>"
                   type="number" value="<?php echo absint($anzahl); ?>" min="1" max="20" size="3">
        </p>
        <p>
            <input id="<?php echo esc_attr($this->get_field_id('mit_bild')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('mit_bild')); ?>"
                   type="checkbox" value="1" <?php checked($mit_bild, 1); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('mit_bild')); ?>">
                <?php esc_html_e('Beitragsbild anzeigen', 'wp-einsatz'); ?>
            </label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return [
            'title'    => sanitize_text_field($new_instance['title']),
            'anzahl'   => absint($new_instance['anzahl']),
            'mit_bild' => isset($new_instance['mit_bild']) ? 1 : 0,
        ];
    }
}

// ── Widget 2: Einsatzstatistik ────────────────────────────────────────────────

class Einsatz_Widget_Statistik extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'einsatz_widget_statistik',
            __('Einsätze – Statistik', 'wp-einsatz'),
            [
                'description' => __('Zeigt Einsatzzahlen als Übersicht', 'wp-einsatz'),
                'classname'   => 'widget-einsatz-statistik',
            ]
        );
    }

    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title'] ?? '');
        $year  = (int) ($instance['jahr'] ?? date('Y'));
        $show_types = !empty($instance['show_types']);

        $total      = $this->count($year);
        $total_all  = $this->count();
        $by_type    = $show_types ? $this->count_by_type($year) : [];

        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        ?>
        <div class="einsatz-stat-widget">
            <div class="einsatz-stat-widget-numbers">
                <div class="einsatz-stat-widget-item">
                    <strong><?php echo (int) $total; ?></strong>
                    <span><?php printf(esc_html__('Einsätze %d', 'wp-einsatz'), $year); ?></span>
                </div>
                <div class="einsatz-stat-widget-item">
                    <strong><?php echo (int) $total_all; ?></strong>
                    <span><?php esc_html_e('Einsätze gesamt', 'wp-einsatz'); ?></span>
                </div>
            </div>
            <?php if ($show_types && !empty($by_type)) : ?>
            <ul class="einsatz-stat-widget-types">
                <?php
                $type_colors = ['Brand' => '#ef4444', 'Technisch' => '#f59e0b', 'Schadstoff' => '#22c55e'];
                foreach ($by_type as $typ => $cnt) :
                    $pct   = $total > 0 ? round(($cnt / $total) * 100) : 0;
                    $color = $type_colors[$typ] ?? '#94a3b8';
                ?>
                <li>
                    <span class="einsatz-stat-widget-dot" style="background:<?php echo esc_attr($color); ?>"></span>
                    <span><?php echo esc_html($typ); ?></span>
                    <span class="einsatz-stat-widget-cnt"><?php echo (int) $cnt; ?></span>
                    <div class="einsatz-stat-widget-bar">
                        <div style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr($color); ?>"></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <a href="<?php echo esc_url(get_post_type_archive_link('einsatz')); ?>" class="einsatz-widget-more">
                <?php esc_html_e('Alle Einsätze', 'wp-einsatz'); ?> &rarr;
            </a>
        </div>
        <?php
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title      = $instance['title']      ?? __('Einsatzstatistik', 'wp-einsatz');
        $jahr       = $instance['jahr']       ?? date('Y');
        $show_types = $instance['show_types'] ?? 1;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titel:', 'wp-einsatz'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('jahr')); ?>"><?php esc_html_e('Jahr:', 'wp-einsatz'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('jahr')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('jahr')); ?>"
                   type="number" value="<?php echo absint($jahr); ?>" min="2000" size="6">
        </p>
        <p>
            <input id="<?php echo esc_attr($this->get_field_id('show_types')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_types')); ?>"
                   type="checkbox" value="1" <?php checked($show_types, 1); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_types')); ?>">
                <?php esc_html_e('Aufschlüsselung nach Typ anzeigen', 'wp-einsatz'); ?>
            </label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return [
            'title'      => sanitize_text_field($new_instance['title']),
            'jahr'       => absint($new_instance['jahr']),
            'show_types' => isset($new_instance['show_types']) ? 1 : 0,
        ];
    }

    private function count($year = null) {
        $args = ['post_type' => 'einsatz', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => false];
        if ($year) $args['date_query'] = [['year' => $year]];
        return (int) (new WP_Query($args))->found_posts;
    }

    private function count_by_type($year = null) {
        $result = [];
        $map    = ['Brand' => 'brand', 'Technisch' => 'technisch', 'Schadstoff' => 'schadstoff'];
        foreach ($map as $label => $slug) {
            $args = [
                'post_type'      => 'einsatz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [['taxonomy' => 'einsatz_kategorie', 'field' => 'slug', 'terms' => $slug]],
            ];
            if ($year) $args['date_query'] = [['year' => $year]];
            $cnt = (int) (new WP_Query($args))->found_posts;
            if ($cnt > 0) $result[$label] = $cnt;
        }
        return $result;
    }
}

// ── Widget 3: Aktueller Einsatz (Featured) ────────────────────────────────────

class Einsatz_Widget_Aktuell extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'einsatz_widget_aktuell',
            __('Einsätze – Aktueller Einsatz', 'wp-einsatz'),
            [
                'description' => __('Zeigt den neuesten Einsatz als Karte', 'wp-einsatz'),
                'classname'   => 'widget-einsatz-aktuell',
            ]
        );
    }

    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title'] ?? '');

        $query = new WP_Query([
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_einsatz_datum',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        if (!$query->have_posts()) return;
        $query->the_post();

        $post_id = get_the_ID();
        $meta    = Einsatz_Helpers::get_einsatz_meta($post_id);
        $stufe   = $meta['alarmstufe'];
        $color   = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe)      : '#475569';
        $txtcol  = $stufe ? Einsatz_Helpers::get_alarmstufe_text_color($stufe) : '#fff';
        $datum   = $meta['datum']
            ? Einsatz_Helpers::format_date($meta['datum'])
            : get_the_date('d.m.Y H:i') . ' Uhr';

        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        ?>
        <div class="einsatz-aktuell-widget">
            <a href="<?php the_permalink(); ?>" class="einsatz-aktuell-link">
                <div class="einsatz-aktuell-media" style="background:<?php echo esc_attr($this->get_gradient($stufe)); ?>">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium', ['class' => 'einsatz-aktuell-img', 'loading' => 'lazy']); ?>
                        <div class="einsatz-aktuell-overlay"></div>
                    <?php endif; ?>
                    <?php if ($stufe) : ?>
                        <span class="einsatz-badge einsatz-badge--lg einsatz-aktuell-badge"
                              style="background:rgba(0,0,0,.35);color:#fff;backdrop-filter:blur(4px)">
                            <?php echo esc_html($stufe); ?>
                        </span>
                    <?php endif; ?>
                    <span class="einsatz-aktuell-date"><?php echo esc_html($datum); ?></span>
                </div>
                <div class="einsatz-aktuell-body">
                    <?php if ($stufe) : ?>
                        <span class="einsatz-card-typ-badge"><?php echo esc_html(Einsatz_Helpers::get_type_label($stufe)); ?></span>
                    <?php endif; ?>
                    <h3 class="einsatz-aktuell-title"><?php the_title(); ?></h3>
                    <?php if ($meta['stichwort']) : ?>
                        <p class="einsatz-aktuell-stichwort"><?php echo esc_html($meta['stichwort']); ?></p>
                    <?php endif; ?>
                    <?php if ($meta['ort']) : ?>
                        <p class="einsatz-aktuell-ort">&#128205; <?php echo esc_html($meta['ort']); ?></p>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php
        wp_reset_postdata();
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = $instance['title'] ?? __('Aktueller Einsatz', 'wp-einsatz');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titel:', 'wp-einsatz'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return ['title' => sanitize_text_field($new_instance['title'])];
    }

    private function get_gradient($stufe) {
        $prefix = $stufe ? Einsatz_Helpers::get_alarmstufe_prefix($stufe) : '';
        $gradients = [
            'B' => 'linear-gradient(135deg,#991b1b,#ef4444)',
            'T' => 'linear-gradient(135deg,#92400e,#f59e0b)',
            'S' => 'linear-gradient(135deg,#14532d,#22c55e)',
        ];
        return $gradients[$prefix] ?? 'linear-gradient(135deg,#334155,#64748b)';
    }
}
