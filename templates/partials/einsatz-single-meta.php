<?php
defined('ABSPATH') || exit;
// Var: $post_id

$meta           = Einsatz_Helpers::get_einsatz_meta($post_id);
$stufe          = $meta['alarmstufe'];
$color          = $stufe ? Einsatz_Helpers::get_alarmstufe_color($stufe)      : '#94a3b8';
$txtcol         = $stufe ? Einsatz_Helpers::get_alarmstufe_text_color($stufe) : '#fff';
$icon           = $stufe ? Einsatz_Helpers::get_type_icon($stufe)             : '';
$typ_label      = $stufe ? Einsatz_Helpers::get_type_label($stufe)            : '';
$datum          = $meta['datum']       ? Einsatz_Helpers::format_date($meta['datum'])      : '';
$dauer          = Einsatz_Helpers::get_duration_string($meta['datum'], $meta['einsatzende']);
$fahrzeug_names = Einsatz_Helpers::get_fahrzeug_names($meta['fahrzeuge']);
$text_fz        = get_post_meta($post_id, '_einsatz_fahrzeuge_text', true);
if ($text_fz) {
    $fahrzeug_names = array_merge($fahrzeug_names, explode(', ', $text_fz));
}
?>
<div class="einsatz-single-header">

    <!-- Alarmstufe-Banner -->
    <div class="einsatz-header-banner" style="background:<?php echo esc_attr($color); ?>">
        <div class="einsatz-header-banner-inner">
            <?php if ($stufe) : ?>
                <div class="einsatz-header-stufe">
                    <span class="einsatz-header-stufe-code"><?php echo esc_html($stufe); ?></span>
                    <?php if ($icon) : ?><span class="einsatz-header-icon"><?php echo $icon; ?></span><?php endif; ?>
                    <span class="einsatz-header-typ" style="color:<?php echo esc_attr($txtcol); ?>"><?php echo esc_html($typ_label); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($datum) : ?>
                <div class="einsatz-header-datum" style="color:<?php echo esc_attr($txtcol); ?>">
                    <span>&#128197;</span>
                    <?php echo esc_html($datum); ?>
                    <?php if ($dauer) : ?><span class="einsatz-header-dauer">&#8987; <?php echo esc_html($dauer); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($meta['nummer']) : ?>
                <div class="einsatz-header-nummer" style="color:<?php echo esc_attr($txtcol); ?>">
                    #<?php echo esc_html($meta['nummer']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Einsatzdetails -->
    <div class="einsatz-detail-bar">
        <?php if ($meta['stichwort']) : ?>
        <div class="einsatz-detail-item einsatz-detail-item--stichwort">
            <span class="einsatz-detail-icon">&#128276;</span>
            <div>
                <span class="einsatz-detail-label"><?php esc_html_e('Stichwort', 'wp-einsatz'); ?></span>
                <span class="einsatz-detail-value"><?php echo esc_html($meta['stichwort']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($meta['ort']) : ?>
        <div class="einsatz-detail-item">
            <span class="einsatz-detail-icon">&#128205;</span>
            <div>
                <span class="einsatz-detail-label"><?php esc_html_e('Einsatzort', 'wp-einsatz'); ?></span>
                <span class="einsatz-detail-value">
                    <a href="https://maps.google.com/?q=<?php echo urlencode($meta['ort']); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($meta['ort']); ?>
                    </a>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($meta['einsatzleiter']) : ?>
        <div class="einsatz-detail-item">
            <span class="einsatz-detail-icon">&#128100;</span>
            <div>
                <span class="einsatz-detail-label"><?php esc_html_e('Einsatzleiter', 'wp-einsatz'); ?></span>
                <span class="einsatz-detail-value"><?php echo esc_html($meta['einsatzleiter']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($meta['kraefte']) : ?>
        <div class="einsatz-detail-item">
            <span class="einsatz-detail-icon">&#128101;</span>
            <div>
                <span class="einsatz-detail-label"><?php esc_html_e('Kräfte', 'wp-einsatz'); ?></span>
                <span class="einsatz-detail-value">
                    <?php printf(
                        esc_html(_n('%d Person', '%d Personen', (int) $meta['kraefte'], 'wp-einsatz')),
                        (int) $meta['kraefte']
                    ); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Fahrzeuge -->
    <?php if (!empty($fahrzeug_names)) : ?>
    <div class="einsatz-fahrzeuge-bar">
        <span class="einsatz-fahrzeuge-label">&#128665; <?php esc_html_e('Eingesetzte Fahrzeuge:', 'wp-einsatz'); ?></span>
        <div class="einsatz-fahrzeuge-tags">
            <?php foreach ($fahrzeug_names as $name) : ?>
                <span class="einsatz-fahrzeug-tag"><?php echo esc_html($name); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .einsatz-single-header -->
