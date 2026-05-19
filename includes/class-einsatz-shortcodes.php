<?php
defined('ABSPATH') || exit;

class Einsatz_Shortcodes {

    public function __construct() {
        add_shortcode('einsatz_statistik', [$this, 'statistik']);
        add_shortcode('einsatz_archiv',    [$this, 'archiv']);
        add_shortcode('einsatz_liste',     [$this, 'liste']);
        add_action('wp_ajax_nopriv_einsatz_filter', [$this, 'ajax_filter']);
        add_action('wp_ajax_einsatz_filter',        [$this, 'ajax_filter']);
        add_action('wp_enqueue_scripts',            [$this, 'enqueue_shortcode_assets']);
    }

    public function enqueue_shortcode_assets() {
        wp_enqueue_style(
            'einsatz-frontend',
            EINSATZ_PLUGIN_URL . 'assets/css/einsatz-frontend.css',
            [],
            EINSATZ_VERSION
        );
    }

    // ── [einsatz_statistik jahr="2024"] ──────────────────────────────────────

    public function statistik($atts) {
        $atts = shortcode_atts(['jahr' => date('Y')], $atts, 'einsatz_statistik');
        $year = (int) $atts['jahr'];

        $all_years  = Einsatz_Helpers::get_available_years();
        $total      = $this->count_einsaetze();
        $year_total = $this->count_einsaetze($year);
        $by_type    = $this->count_by_type($year);
        $by_stufe   = $this->count_by_alarmstufe($year);
        $monthly    = $this->count_monthly($year);

        ob_start();
        ?>
        <div class="einsatz-statistik">
            <div class="einsatz-stat-header">
                <h3><?php printf(esc_html__('Einsatzstatistik %d', 'wp-einsatz'), $year); ?></h3>
                <?php if (count($all_years) > 1) : ?>
                <div class="einsatz-year-tabs">
                    <?php foreach ($all_years as $y) : ?>
                        <a href="?stat_jahr=<?php echo (int) $y; ?>#einsatz-statistik"
                           class="einsatz-year-tab <?php echo ($y == $year) ? 'active' : ''; ?>">
                            <?php echo (int) $y; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="einsatz-stat-overview">
                <div class="einsatz-stat-card">
                    <span class="einsatz-stat-number"><?php echo (int) $total; ?></span>
                    <span class="einsatz-stat-label"><?php esc_html_e('Einsätze gesamt', 'wp-einsatz'); ?></span>
                </div>
                <div class="einsatz-stat-card">
                    <span class="einsatz-stat-number"><?php echo (int) $year_total; ?></span>
                    <span class="einsatz-stat-label"><?php printf(esc_html__('Einsätze %d', 'wp-einsatz'), $year); ?></span>
                </div>
            </div>

            <?php if (!empty($by_type)) : ?>
            <div class="einsatz-stat-section">
                <h4><?php esc_html_e('Nach Einsatztyp', 'wp-einsatz'); ?></h4>
                <div class="einsatz-bar-chart">
                    <?php foreach ($by_type as $typ => $count) :
                        $pct = $year_total > 0 ? round(($count / $year_total) * 100) : 0;
                        $color = $this->get_type_color($typ);
                    ?>
                    <div class="einsatz-bar-row">
                        <span class="einsatz-bar-label"><?php echo esc_html($typ); ?></span>
                        <div class="einsatz-bar-track">
                            <div class="einsatz-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr($color); ?>"></div>
                        </div>
                        <span class="einsatz-bar-value"><?php echo (int) $count; ?> <small>(<?php echo $pct; ?>%)</small></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($by_stufe)) : ?>
            <div class="einsatz-stat-section">
                <h4><?php esc_html_e('Nach Alarmstufe', 'wp-einsatz'); ?></h4>
                <div class="einsatz-stufen-grid">
                    <?php foreach (Einsatz_Helpers::get_alarmstufen() as $stufe) : ?>
                    <div class="einsatz-stufe-cell">
                        <span class="einsatz-badge" style="background:<?php echo esc_attr(Einsatz_Helpers::get_alarmstufe_color($stufe)); ?>;color:<?php echo esc_attr(Einsatz_Helpers::get_alarmstufe_text_color($stufe)); ?>">
                            <?php echo esc_html($stufe); ?>
                        </span>
                        <span class="einsatz-stufe-count"><?php echo (int) ($by_stufe[$stufe] ?? 0); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($monthly)) : ?>
            <div class="einsatz-stat-section">
                <h4><?php printf(esc_html__('Monatliche Übersicht %d', 'wp-einsatz'), $year); ?></h4>
                <div class="einsatz-monthly-chart">
                    <?php
                    $month_names = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
                    $max_month   = max($monthly) ?: 1;
                    for ($m = 1; $m <= 12; $m++) :
                        $count = (int) ($monthly[$m] ?? 0);
                        $height = round(($count / $max_month) * 100);
                    ?>
                    <div class="einsatz-month-col">
                        <span class="einsatz-month-num"><?php echo $count ?: ''; ?></span>
                        <div class="einsatz-month-bar" style="height:<?php echo $height; ?>%"></div>
                        <span class="einsatz-month-label"><?php echo esc_html($month_names[$m - 1]); ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        // Handle year change via URL param
        if (isset($_GET['stat_jahr'])) {
            // Will reload with correct year on next request
        }
        return ob_get_clean();
    }

    // ── [einsatz_archiv] ─────────────────────────────────────────────────────

    public function archiv($atts) {
        $atts = shortcode_atts([
            'pro_seite' => 10,
            'modus'     => 'karten',   // 'karten' oder 'liste'
        ], $atts, 'einsatz_archiv');

        $per_page   = (int) $atts['pro_seite'];
        $modus      = in_array($atts['modus'], ['karten','liste'], true) ? $atts['modus'] : 'karten';
        $all_years  = Einsatz_Helpers::get_available_years();
        $current_year = isset($_GET['ea_jahr'])  ? (int) $_GET['ea_jahr']  : '';
        $current_type = isset($_GET['ea_typ'])   ? sanitize_text_field($_GET['ea_typ'])   : '';
        $current_stufe= isset($_GET['ea_stufe']) ? sanitize_text_field($_GET['ea_stufe']) : '';
        $paged        = isset($_GET['ea_seite']) ? (int) $_GET['ea_seite'] : 1;

        $query_args = [
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'meta_value',
            'meta_key'       => '_einsatz_datum',
            'order'          => 'DESC',
        ];

        if ($current_year) {
            $query_args['date_query'] = [
                ['year' => $current_year],
            ];
        }

        if ($current_type) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'einsatz_kategorie',
                    'field'    => 'slug',
                    'terms'    => $current_type,
                ],
            ];
        }

        if ($current_stufe) {
            $query_args['meta_query'][] = [
                'key'   => '_einsatz_alarmstufe',
                'value' => $current_stufe,
            ];
        }

        $query = new WP_Query($query_args);

        ob_start();
        ?>
        <div class="einsatz-archiv" id="einsatz-archiv">
            <div class="einsatz-filter-bar">
                <form method="get" action="" class="einsatz-filter-form">
                    <div class="einsatz-filter-group">
                        <label><?php esc_html_e('Jahr', 'wp-einsatz'); ?></label>
                        <select name="ea_jahr" onchange="this.form.submit()">
                            <option value=""><?php esc_html_e('Alle Jahre', 'wp-einsatz'); ?></option>
                            <?php foreach ($all_years as $y) : ?>
                                <option value="<?php echo (int) $y; ?>" <?php selected($current_year, $y); ?>>
                                    <?php echo (int) $y; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="einsatz-filter-group">
                        <label><?php esc_html_e('Typ', 'wp-einsatz'); ?></label>
                        <select name="ea_typ" onchange="this.form.submit()">
                            <option value=""><?php esc_html_e('Alle Typen', 'wp-einsatz'); ?></option>
                            <option value="brand"      <?php selected($current_type, 'brand'); ?>>🔥 Brand</option>
                            <option value="technisch"  <?php selected($current_type, 'technisch'); ?>>⚙️ Technisch</option>
                            <option value="schadstoff" <?php selected($current_type, 'schadstoff'); ?>>☣️ Schadstoff</option>
                        </select>
                    </div>
                    <div class="einsatz-filter-group">
                        <label><?php esc_html_e('Alarmstufe', 'wp-einsatz'); ?></label>
                        <select name="ea_stufe" onchange="this.form.submit()">
                            <option value=""><?php esc_html_e('Alle Stufen', 'wp-einsatz'); ?></option>
                            <?php foreach (Einsatz_Helpers::get_alarmstufen() as $stufe) : ?>
                                <option value="<?php echo esc_attr($stufe); ?>" <?php selected($current_stufe, $stufe); ?>>
                                    <?php echo esc_html($stufe); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="ea_seite" value="1">
                    <?php
                    // Preserve other GET params (e.g., page slug)
                    foreach ($_GET as $k => $v) {
                        if (!in_array($k, ['ea_jahr','ea_typ','ea_stufe','ea_seite'], true)) {
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr(sanitize_text_field($v)) . '">';
                        }
                    }
                    ?>
                    <button type="submit" class="einsatz-filter-btn"><?php esc_html_e('Filtern', 'wp-einsatz'); ?></button>
                    <?php if ($current_year || $current_type || $current_stufe) : ?>
                        <a href="?" class="einsatz-filter-reset"><?php esc_html_e('Zurücksetzen', 'wp-einsatz'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($query->have_posts()) : ?>
                <p class="einsatz-result-count">
                    <?php printf(
                        esc_html(_n('%d Einsatz gefunden', '%d Einsätze gefunden', $query->found_posts, 'wp-einsatz')),
                        $query->found_posts
                    ); ?>
                </p>
                <div class="<?php echo $modus === 'karten' ? 'einsatz-card-grid' : 'einsatz-list'; ?>">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <?php if ($modus === 'karten') : ?>
                            <?php $this->render_card(get_the_ID()); ?>
                        <?php else : ?>
                            <?php $this->render_archive_item(get_the_ID()); ?>
                        <?php endif; ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

                <?php if ($query->max_num_pages > 1) : ?>
                <div class="einsatz-pagination">
                    <?php for ($p = 1; $p <= $query->max_num_pages; $p++) : ?>
                        <?php
                        $params = array_merge($_GET, ['ea_seite' => $p]);
                        $url    = '?' . http_build_query($params) . '#einsatz-archiv';
                        ?>
                        <a href="<?php echo esc_url($url); ?>"
                           class="einsatz-page-btn <?php echo ($p === $paged) ? 'active' : ''; ?>">
                            <?php echo (int) $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

            <?php else : ?>
                <p class="einsatz-no-results"><?php esc_html_e('Keine Einsätze für die gewählten Filter gefunden.', 'wp-einsatz'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── [einsatz_liste anzahl="5"] ────────────────────────────────────────────

    public function liste($atts) {
        $atts = shortcode_atts([
            'anzahl' => 5,
            'typ'    => '',
        ], $atts, 'einsatz_liste');

        $args = [
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['anzahl'],
            'orderby'        => 'meta_value',
            'meta_key'       => '_einsatz_datum',
            'order'          => 'DESC',
        ];

        if ($atts['typ']) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'einsatz_kategorie',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($atts['typ']),
                ],
            ];
        }

        $query = new WP_Query($args);

        ob_start();
        if ($query->have_posts()) :
            echo '<div class="einsatz-list einsatz-list--compact">';
            while ($query->have_posts()) : $query->the_post();
                $this->render_archive_item(get_the_ID(), true);
            endwhile;
            wp_reset_postdata();
            echo '</div>';
        else :
            echo '<p>' . esc_html__('Keine Einsätze vorhanden.', 'wp-einsatz') . '</p>';
        endif;
        return ob_get_clean();
    }

    // ── Shared render helpers ────────────────────────────────────────────────

    private function render_card($post_id) {
        $meta       = Einsatz_Helpers::get_einsatz_meta($post_id);
        $stufe      = $meta['alarmstufe'];
        $color      = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe) : '#94a3b8';
        $dark       = $stufe ? Einsatz_Helpers::get_gradient_dark($stufe)    : '#334155';
        $gradient   = "linear-gradient(135deg,{$dark},{$color})";
        $datum_short= $meta['datum'] ? Einsatz_Helpers::format_date_short($meta['datum']) : get_the_date('d.m.Y');
        $dauer      = Einsatz_Helpers::get_duration_string($meta['datum'], $meta['einsatzende']);
        $has_img    = has_post_thumbnail($post_id);
        $fz_count   = count((array) $meta['fahrzeuge']);
        ?>
        <article class="einsatz-card" id="einsatz-sc-<?php echo $post_id; ?>">
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="einsatz-card-link">
                <div class="einsatz-card-media" style="background:<?php echo esc_attr($gradient); ?>">
                    <?php if ($has_img) : ?>
                        <?php echo get_the_post_thumbnail($post_id, 'medium_large', ['class' => 'einsatz-card-img', 'loading' => 'lazy']); ?>
                        <div class="einsatz-card-overlay"></div>
                    <?php endif; ?>
                    <div class="einsatz-card-media-top">
                        <?php if ($stufe) : ?>
                            <span class="einsatz-badge einsatz-card-badge"
                                  style="background:rgba(0,0,0,.4);color:#fff;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)">
                                <?php echo esc_html($stufe); ?>
                            </span>
                        <?php endif; ?>
                        <span class="einsatz-card-date-overlay"><?php echo esc_html($datum_short); ?></span>
                    </div>
                    <?php if ($dauer) : ?>
                        <span class="einsatz-card-dauer-overlay">&#8987; <?php echo esc_html($dauer); ?></span>
                    <?php endif; ?>
                </div>
                <div class="einsatz-card-body">
                    <?php if ($stufe) : ?>
                        <span class="einsatz-card-typ-badge"><?php echo esc_html(Einsatz_Helpers::get_type_label($stufe)); ?></span>
                    <?php endif; ?>
                    <h3 class="einsatz-card-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
                    <?php if ($meta['stichwort']) : ?>
                        <p class="einsatz-card-stichwort"><?php echo esc_html($meta['stichwort']); ?></p>
                    <?php endif; ?>
                    <?php if ($meta['ort']) : ?>
                        <p class="einsatz-card-ort">&#128205; <?php echo esc_html($meta['ort']); ?></p>
                    <?php endif; ?>
                    <div class="einsatz-card-stats">
                        <?php if ($meta['kraefte']) : ?>
                            <span>&#128101; <?php echo (int) $meta['kraefte']; ?> <?php esc_html_e('Kräfte', 'wp-einsatz'); ?></span>
                        <?php endif; ?>
                        <?php if ($fz_count) : ?>
                            <span>&#128665; <?php printf(esc_html(_n('%d Fahrzeug','%d Fahrzeuge',$fz_count,'wp-einsatz')),$fz_count); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </article>
        <?php
    }

    private function render_archive_item($post_id, $compact = false) {
        $meta    = Einsatz_Helpers::get_einsatz_meta($post_id);
        $stufe   = $meta['alarmstufe'];
        $color   = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe)      : '#94a3b8';
        $txtcol  = $stufe ? Einsatz_Helpers::get_alarmstufe_text_color($stufe) : '#1e293b';
        $datum   = $meta['datum'] ? Einsatz_Helpers::format_date($meta['datum']) : get_the_date('d.m.Y H:i') . ' Uhr';
        $dauer   = Einsatz_Helpers::get_duration_string($meta['datum'], $meta['einsatzende']);
        ?>
        <div class="einsatz-item <?php echo $compact ? 'einsatz-item--compact' : ''; ?>">
            <div class="einsatz-item-left">
                <?php if ($stufe) : ?>
                    <span class="einsatz-badge einsatz-badge--lg"
                          style="background:<?php echo esc_attr($color); ?>;color:<?php echo esc_attr($txtcol); ?>">
                        <?php echo esc_html($stufe); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="einsatz-item-body">
                <div class="einsatz-item-meta-row">
                    <span class="einsatz-item-date"><?php echo esc_html($datum); ?></span>
                    <?php if ($dauer) : ?>
                        <span class="einsatz-item-dauer">&#8987; <?php echo esc_html($dauer); ?></span>
                    <?php endif; ?>
                    <?php if ($stufe) : ?>
                        <span class="einsatz-item-typ"><?php echo esc_html(Einsatz_Helpers::get_type_label($stufe)); ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="einsatz-item-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <?php if ($meta['stichwort']) : ?>
                    <p class="einsatz-item-stichwort"><?php echo esc_html($meta['stichwort']); ?></p>
                <?php endif; ?>
                <?php if ($meta['ort'] && !$compact) : ?>
                    <p class="einsatz-item-ort">&#128205; <?php echo esc_html($meta['ort']); ?></p>
                <?php endif; ?>
            </div>
            <?php if (!$compact) : ?>
            <div class="einsatz-item-right">
                <a href="<?php the_permalink(); ?>" class="einsatz-read-more">
                    <?php esc_html_e('Details', 'wp-einsatz'); ?> &rarr;
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Statistics helpers ────────────────────────────────────────────────────

    private function count_einsaetze($year = null) {
        $args = [
            'post_type'      => 'einsatz',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        if ($year) {
            $args['date_query'] = [['year' => $year]];
        }
        return (int) (new WP_Query($args))->found_posts;
    }

    private function count_by_type($year = null) {
        $types = ['Brand' => 'brand', 'Technisch' => 'technisch', 'Schadstoff' => 'schadstoff'];
        $result = [];
        foreach ($types as $label => $slug) {
            $args = [
                'post_type'      => 'einsatz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [
                    ['taxonomy' => 'einsatz_kategorie', 'field' => 'slug', 'terms' => $slug],
                ],
            ];
            if ($year) $args['date_query'] = [['year' => $year]];
            $count = (int) (new WP_Query($args))->found_posts;
            if ($count > 0) $result[$label] = $count;
        }
        return $result;
    }

    private function count_by_alarmstufe($year = null) {
        $result = [];
        foreach (Einsatz_Helpers::get_alarmstufen() as $stufe) {
            $args = [
                'post_type'      => 'einsatz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    ['key' => '_einsatz_alarmstufe', 'value' => $stufe],
                ],
            ];
            if ($year) $args['date_query'] = [['year' => $year]];
            $result[$stufe] = (int) (new WP_Query($args))->found_posts;
        }
        return $result;
    }

    private function count_monthly($year) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT MONTH(meta_value) AS m, COUNT(*) AS cnt
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_einsatz_datum'
               AND p.post_type = 'einsatz'
               AND p.post_status = 'publish'
               AND YEAR(pm.meta_value) = %d
             GROUP BY m",
            $year
        ));
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->m] = (int) $row->cnt;
        }
        return $result;
    }

    private function get_type_color($typ) {
        $map = ['Brand' => '#ef4444', 'Technisch' => '#f59e0b', 'Schadstoff' => '#22c55e'];
        return $map[$typ] ?? '#94a3b8';
    }
}
