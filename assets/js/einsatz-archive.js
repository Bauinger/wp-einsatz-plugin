/**
 * WP Einsatz Plugin – Archive JS
 * Enhances checkbox interactions in the admin meta box.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Toggle .checked class on fahrzeug labels when checkbox changes
        document.querySelectorAll('.einsatz-fahrzeug-label input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var label = this.closest('.einsatz-fahrzeug-label');
                if (label) {
                    label.classList.toggle('checked', this.checked);
                }
            });
        });

        // Auto-update badge preview when Alarmstufe changes (admin meta box)
        var stufeSelect = document.getElementById('einsatz_alarmstufe');
        if (stufeSelect) {
            stufeSelect.addEventListener('change', function () {
                var preview = document.querySelector('.einsatz-badge-preview');
                if (!preview) return;

                var val = this.value;
                if (!val) {
                    preview.style.display = 'none';
                    return;
                }

                var colors = {
                    B1: { bg: '#fca5a5', txt: '#7f1d1d' },
                    B2: { bg: '#ef4444', txt: '#ffffff' },
                    B3: { bg: '#991b1b', txt: '#ffffff' },
                    T1: { bg: '#fde68a', txt: '#78350f' },
                    T2: { bg: '#f59e0b', txt: '#ffffff' },
                    T3: { bg: '#92400e', txt: '#ffffff' },
                    S1: { bg: '#86efac', txt: '#14532d' },
                    S2: { bg: '#22c55e', txt: '#ffffff' },
                    S3: { bg: '#14532d', txt: '#ffffff' }
                };

                var labels = {
                    B: 'Brand',
                    T: 'Technische Hilfeleistung',
                    S: 'Schadstoff / ABC'
                };

                var c = colors[val] || { bg: '#94a3b8', txt: '#fff' };
                var prefix = val.charAt(0);
                var typLabel = labels[prefix] || '';

                preview.style.background = c.bg;
                preview.style.color = c.txt;
                preview.textContent = val + (typLabel ? '  ' + typLabel : '');
                preview.style.display = 'inline-block';
            });
        }
    });
})();
