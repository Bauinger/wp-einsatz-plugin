<?php
defined('ABSPATH') || exit;
// Available vars: $post, $meta, $available_fahrzeuge
?>
<div class="einsatz-meta-box">
    <div class="einsatz-meta-grid">

        <!-- Alarmstufe -->
        <div class="einsatz-field einsatz-field--alarmstufe">
            <label for="einsatz_alarmstufe"><strong><?php esc_html_e('Alarmstufe', 'wp-einsatz'); ?></strong> <span class="required">*</span></label>
            <select id="einsatz_alarmstufe" name="einsatz_alarmstufe" class="einsatz-select-alarmstufe">
                <option value=""><?php esc_html_e('— Bitte wählen —', 'wp-einsatz'); ?></option>
                <?php
                $groups = [
                    'Brand'                    => ['B1', 'B2', 'B3'],
                    'Technische Hilfeleistung' => ['T1', 'T2', 'T3'],
                    'Schadstoff / ABC'         => ['S1', 'S2', 'S3'],
                ];
                foreach ($groups as $group => $stufen) :
                ?>
                    <optgroup label="<?php echo esc_attr($group); ?>">
                        <?php foreach ($stufen as $stufe) : ?>
                            <option value="<?php echo esc_attr($stufe); ?>" <?php selected($meta['alarmstufe'], $stufe); ?>>
                                <?php echo esc_html($stufe); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?php if ($meta['alarmstufe']) : ?>
                <span class="einsatz-badge-preview einsatz-badge"
                      style="background:<?php echo esc_attr(Einsatz_Helpers::get_alarmstufe_color($meta['alarmstufe'])); ?>;
                             color:<?php echo esc_attr(Einsatz_Helpers::get_alarmstufe_text_color($meta['alarmstufe'])); ?>">
                    <?php echo esc_html($meta['alarmstufe']); ?>
                    &nbsp;<?php echo esc_html(Einsatz_Helpers::get_type_label($meta['alarmstufe'])); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Einsatznummer -->
        <div class="einsatz-field">
            <label for="einsatz_nummer"><strong><?php esc_html_e('Einsatznummer', 'wp-einsatz'); ?></strong></label>
            <input type="text" id="einsatz_nummer" name="einsatz_nummer"
                   value="<?php echo esc_attr($meta['nummer']); ?>" class="widefat"
                   placeholder="z.B. E-2024-0042">
        </div>

        <!-- Stichwort -->
        <div class="einsatz-field einsatz-field--wide">
            <label for="einsatz_stichwort"><strong><?php esc_html_e('Stichwort / Meldebild', 'wp-einsatz'); ?></strong></label>
            <input type="text" id="einsatz_stichwort" name="einsatz_stichwort"
                   value="<?php echo esc_attr($meta['stichwort']); ?>" class="widefat"
                   placeholder="z.B. Wohnhausbrand mit Menschenrettung">
        </div>

        <!-- Alarmzeit -->
        <div class="einsatz-field">
            <label for="einsatz_datum"><strong><?php esc_html_e('Alarmzeit', 'wp-einsatz'); ?></strong></label>
            <input type="datetime-local" id="einsatz_datum" name="einsatz_datum"
                   value="<?php echo esc_attr($meta['datum']); ?>" class="widefat">
        </div>

        <!-- Einsatzende -->
        <div class="einsatz-field">
            <label for="einsatz_einsatzende"><strong><?php esc_html_e('Einsatzende', 'wp-einsatz'); ?></strong></label>
            <input type="datetime-local" id="einsatz_einsatzende" name="einsatz_einsatzende"
                   value="<?php echo esc_attr($meta['einsatzende']); ?>" class="widefat">
            <?php
            $dauer = Einsatz_Helpers::get_duration_string($meta['datum'], $meta['einsatzende']);
            if ($dauer) :
            ?>
                <span class="einsatz-dauer-hint">&#8987; <?php echo esc_html($dauer); ?></span>
            <?php endif; ?>
        </div>

        <!-- Einsatzort -->
        <div class="einsatz-field einsatz-field--wide">
            <label for="einsatz_ort"><strong><?php esc_html_e('Einsatzort', 'wp-einsatz'); ?></strong></label>
            <input type="text" id="einsatz_ort" name="einsatz_ort"
                   value="<?php echo esc_attr($meta['ort']); ?>" class="widefat"
                   placeholder="z.B. Musterstraße 1, 12345 Musterstadt">
        </div>

        <!-- Einsatzleiter -->
        <div class="einsatz-field">
            <label for="einsatz_einsatzleiter"><strong><?php esc_html_e('Einsatzleiter', 'wp-einsatz'); ?></strong></label>
            <input type="text" id="einsatz_einsatzleiter" name="einsatz_einsatzleiter"
                   value="<?php echo esc_attr($meta['einsatzleiter']); ?>" class="widefat"
                   placeholder="z.B. GF Mustermann">
        </div>

        <!-- Kräfte -->
        <div class="einsatz-field">
            <label for="einsatz_kraefte"><strong><?php esc_html_e('Eingesetzte Kräfte', 'wp-einsatz'); ?></strong></label>
            <div style="display:flex;align-items:center;gap:8px">
                <input type="number" id="einsatz_kraefte" name="einsatz_kraefte"
                       value="<?php echo esc_attr($meta['kraefte']); ?>"
                       min="0" max="9999" style="width:100px">
                <span><?php esc_html_e('Personen', 'wp-einsatz'); ?></span>
            </div>
        </div>

    </div><!-- .einsatz-meta-grid -->

    <!-- Fahrzeuge -->
    <?php if (!empty($available_fahrzeuge)) : ?>
    <div class="einsatz-field einsatz-field--fahrzeuge">
        <label><strong><?php esc_html_e('Beteiligte Fahrzeuge', 'wp-einsatz'); ?></strong></label>
        <div class="einsatz-fahrzeuge-grid">
            <?php foreach ($available_fahrzeuge as $fahrzeug) :
                $funkruf = get_post_meta($fahrzeug->ID, '_fahrzeug_funkrufname', true) ?: $fahrzeug->post_title;
                $typ     = get_post_meta($fahrzeug->ID, '_fahrzeug_typ', true);
                $checked = in_array($fahrzeug->ID, (array) $meta['fahrzeuge'], false);
            ?>
                <label class="einsatz-fahrzeug-label <?php echo $checked ? 'checked' : ''; ?>">
                    <input type="checkbox" name="einsatz_fahrzeuge[]"
                           value="<?php echo esc_attr($fahrzeug->ID); ?>"
                           <?php checked($checked, true); ?>>
                    <span class="einsatz-fahrzeug-name"><?php echo esc_html($funkruf); ?></span>
                    <?php if ($typ) : ?>
                        <span class="einsatz-fahrzeug-typ"><?php echo esc_html($typ); ?></span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=einsatz_fahrzeug')); ?>">
                <?php esc_html_e('+ Neues Fahrzeug anlegen', 'wp-einsatz'); ?>
            </a>
        </p>
    </div>
    <?php else : ?>
    <p class="description">
        <?php esc_html_e('Noch keine Fahrzeuge angelegt.', 'wp-einsatz'); ?>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=einsatz_fahrzeug')); ?>">
            <?php esc_html_e('Fahrzeug anlegen', 'wp-einsatz'); ?>
        </a>
    </p>
    <?php endif; ?>

</div><!-- .einsatz-meta-box -->
