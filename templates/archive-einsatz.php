<?php
/**
 * Archive template for 'einsatz' post type.
 * Falls back to theme's archive.php if this template is not suitable.
 */
defined('ABSPATH') || exit;

get_header();

$all_years    = Einsatz_Helpers::get_available_years();
$current_year = isset($_GET['ea_jahr'])  ? (int) $_GET['ea_jahr']  : '';
$current_type = isset($_GET['ea_typ'])   ? sanitize_text_field($_GET['ea_typ'])   : '';
$current_stufe= isset($_GET['ea_stufe']) ? sanitize_text_field($_GET['ea_stufe']) : '';
$paged        = get_query_var('paged', 1);

// Re-query with filters
$query_args = [
    'post_type'      => 'einsatz',
    'post_status'    => 'publish',
    'posts_per_page' => (int) get_option('posts_per_page', 10),
    'paged'          => $paged,
    'orderby'        => 'meta_value',
    'meta_key'       => '_einsatz_datum',
    'order'          => 'DESC',
];
if ($current_year)  $query_args['date_query']  = [['year' => $current_year]];
if ($current_type)  $query_args['tax_query']   = [['taxonomy' => 'einsatz_kategorie', 'field' => 'slug', 'terms' => $current_type]];
if ($current_stufe) $query_args['meta_query']  = [['key' => '_einsatz_alarmstufe', 'value' => $current_stufe]];

$einsatz_query = new WP_Query($query_args);
?>

<div class="einsatz-archive-page">
    <div class="einsatz-archive-container">

        <header class="einsatz-archive-header">
            <h1 class="einsatz-archive-title">
                <?php esc_html_e('Einsatzarchiv', 'wp-einsatz'); ?>
            </h1>
            <?php if ($einsatz_query->found_posts) : ?>
            <p class="einsatz-archive-subtitle">
                <?php printf(
                    esc_html(_n('%d Einsatz', '%d Einsätze', $einsatz_query->found_posts, 'wp-einsatz')),
                    $einsatz_query->found_posts
                ); ?>
            </p>
            <?php endif; ?>
        </header>

        <!-- Filter Bar -->
        <div class="einsatz-filter-bar" id="einsatz-archiv">
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
                        <option value="brand"      <?php selected($current_type, 'brand'); ?>>&#128293; Brand</option>
                        <option value="technisch"  <?php selected($current_type, 'technisch'); ?>>&#9881; Technisch</option>
                        <option value="schadstoff" <?php selected($current_type, 'schadstoff'); ?>>&#9763; Schadstoff</option>
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
                <?php if ($current_year || $current_type || $current_stufe) : ?>
                    <a href="<?php echo esc_url(get_post_type_archive_link('einsatz')); ?>" class="einsatz-filter-reset">
                        &#10005; <?php esc_html_e('Filter zurücksetzen', 'wp-einsatz'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Einsatz List -->
        <?php if ($einsatz_query->have_posts()) : ?>
            <div class="einsatz-list">
                <?php while ($einsatz_query->have_posts()) :
                    $einsatz_query->the_post();
                    $post_id  = get_the_ID();
                    $meta     = Einsatz_Helpers::get_einsatz_meta($post_id);
                    $stufe    = $meta['alarmstufe'];
                    $color    = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe)      : '#94a3b8';
                    $txtcol   = $stufe ? Einsatz_Helpers::get_alarmstufe_text_color($stufe) : '#fff';
                    $datum    = $meta['datum'] ? Einsatz_Helpers::format_date($meta['datum']) : get_the_date('d.m.Y H:i') . ' Uhr';
                    $dauer    = Einsatz_Helpers::get_duration_string($meta['datum'], $meta['einsatzende']);
                ?>
                <article class="einsatz-item" id="einsatz-<?php echo $post_id; ?>">
                    <div class="einsatz-item-left">
                        <?php if ($stufe) : ?>
                            <span class="einsatz-badge einsatz-badge--lg"
                                  style="background:<?php echo esc_attr($color); ?>;color:<?php echo esc_attr($txtcol); ?>">
                                <?php echo esc_html($stufe); ?>
                            </span>
                        <?php endif; ?>
                        <div class="einsatz-item-date-block">
                            <span class="einsatz-item-date"><?php echo esc_html($datum); ?></span>
                            <?php if ($dauer) : ?>
                                <span class="einsatz-item-dauer">&#8987; <?php echo esc_html($dauer); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="einsatz-item-body">
                        <?php if ($stufe) : ?>
                            <span class="einsatz-item-typ-badge"><?php echo esc_html(Einsatz_Helpers::get_type_label($stufe)); ?></span>
                        <?php endif; ?>
                        <h2 class="einsatz-item-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        <?php if ($meta['stichwort']) : ?>
                            <p class="einsatz-item-stichwort"><?php echo esc_html($meta['stichwort']); ?></p>
                        <?php endif; ?>
                        <?php if ($meta['ort']) : ?>
                            <p class="einsatz-item-ort">&#128205; <?php echo esc_html($meta['ort']); ?></p>
                        <?php endif; ?>
                        <div class="einsatz-item-stats">
                            <?php if ($meta['kraefte']) : ?>
                                <span>&#128101; <?php echo (int) $meta['kraefte']; ?> <?php esc_html_e('Kräfte', 'wp-einsatz'); ?></span>
                            <?php endif; ?>
                            <?php
                            $fz_count = count((array) $meta['fahrzeuge']);
                            if ($fz_count) :
                            ?>
                                <span>&#128665; <?php printf(
                                    esc_html(_n('%d Fahrzeug', '%d Fahrzeuge', $fz_count, 'wp-einsatz')),
                                    $fz_count
                                ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="einsatz-item-right">
                        <a href="<?php the_permalink(); ?>" class="einsatz-read-more">
                            <?php esc_html_e('Einsatzbericht', 'wp-einsatz'); ?> &rarr;
                        </a>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <!-- Pagination -->
            <?php if ($einsatz_query->max_num_pages > 1) : ?>
            <nav class="einsatz-pagination">
                <?php
                echo paginate_links([
                    'total'   => $einsatz_query->max_num_pages,
                    'current' => $paged,
                    'format'  => '?paged=%#%',
                    'prev_text' => '&larr; ' . esc_html__('Neuere', 'wp-einsatz'),
                    'next_text' => esc_html__('Ältere', 'wp-einsatz') . ' &rarr;',
                ]);
                ?>
            </nav>
            <?php endif; ?>

        <?php else : ?>
            <div class="einsatz-no-results">
                <p><?php esc_html_e('Keine Einsätze für die gewählten Filter gefunden.', 'wp-einsatz'); ?></p>
                <?php if ($current_year || $current_type || $current_stufe) : ?>
                    <a href="<?php echo esc_url(get_post_type_archive_link('einsatz')); ?>" class="button">
                        <?php esc_html_e('Alle Einsätze anzeigen', 'wp-einsatz'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- .einsatz-archive-container -->
</div><!-- .einsatz-archive-page -->

<?php get_footer(); ?>
