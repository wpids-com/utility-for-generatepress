/**
 * WPIDS Gradient Module — Customizer JS
 * Handles: Gradient builder, live preview, stop management, dark auto-compute, save/edit/delete.
 * Also injects an "Add Gradient" shortcut near GP Global Colors panel via MutationObserver.
 */
(function ($, wp) {
    'use strict';

    var cfg       = window.wpidsGradientModule || {};
    var savedList = cfg.saved || [];      // persisted from DB
    var editIndex = -1;                   // -1 = new, N = editing index N

    // Current builder state
    var state = {
        name:  '',
        slug:  '',
        type:  'linear',
        angle: 135,
        shape: 'ellipse',
        at:    'center',
        stops: [
            { color: '#667eea', position: 0 },
            { color: '#764ba2', position: 100 }
        ],
        darkStops: []
    };

    // ─── Bootstrap ────────────────────────────────────────────────
    $(document).ready(function () {
        injectGPShortcut();
        renderSavedList();
    });

    // ─── GP Shortcut Injection (MutationObserver) ─────────────────
    /**
     * Watch for the GP Global Colors customizer section to appear,
     * then inject a shortcut button below the color list.
     */
    function injectGPShortcut() {
        var observer = new MutationObserver(function () {
            // GP's Global Color section wrapper — adjust selector if GP changes
            var $target = $('#customize-control-generate_settings\\[global_colors\\]');
            if ($target.length && !$target.find('.wpids-grad-shortcut').length) {
                var $btn = $('<button>', {
                    type:  'button',
                    class: 'wpids-ci-btn wpids-ci-btn-ghost wpids-grad-shortcut',
                    html:  '&#127752; Gradient Variables &rarr;',
                    css:   { marginTop: '10px', width: '100%', justifyContent: 'center' }
                });
                $btn.on('click', function () {
                    // Navigate to Gradient Variables section in Customizer
                    if (wp && wp.customize) {
                        wp.customize.section('wpids_gradient_variables').expand();
                    }
                });
                $target.append($btn);
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ─── Open builder (new) ───────────────────────────────────────
    $(document).on('click', '#wpids-grad-add-btn', function () {
        editIndex = -1;
        resetState();
        openBuilder();
    });

    // ─── Open builder (edit) ──────────────────────────────────────
    $(document).on('click', '.wpids-grad-edit', function () {
        var idx = parseInt($(this).data('index'));
        var g   = savedList[idx];
        if (!g) return;

        editIndex      = idx;
        state.name     = g.name     || '';
        state.slug     = g.slug     || '';
        state.type     = g.type     || 'linear';
        state.angle    = g.angle    || 135;
        state.shape    = g.shape    || 'ellipse';
        state.at       = g.at       || 'center';
        state.stops    = JSON.parse(JSON.stringify(g.stops || []));
        state.darkStops = JSON.parse(JSON.stringify(g.dark_stops || []));

        openBuilder();
    });

    function openBuilder() {
        syncStateToUI();
        renderStops();
        updatePreview();
        $('#wpids-grad-modal').fadeIn(200);
    }

    function resetState() {
        state = {
            name:  '',
            slug:  '',
            type:  'linear',
            angle: 135,
            shape: 'ellipse',
            at:    'center',
            stops: [
                { color: '#667eea', position: 0 },
                { color: '#764ba2', position: 100 }
            ],
            darkStops: []
        };
    }

    function syncStateToUI() {
        $('#wpids-grad-name').val(state.name);
        $('#wpids-grad-slug').val(state.slug);
        $('#wpids-grad-type').val(state.type);
        $('#wpids-grad-angle-range').val(state.angle);
        $('#wpids-grad-angle-num').val(state.angle);
        $('#wpids-grad-shape').val(state.shape);
        $('#wpids-grad-at').val(state.at);
        toggleTypeControls(state.type);
    }

    // ─── Close ────────────────────────────────────────────────────
    $(document).on('click', '#wpids-grad-modal-close, #wpids-grad-cancel', function () {
        $('#wpids-grad-modal').fadeOut(200);
    });

    // ─── Type change ──────────────────────────────────────────────
    $(document).on('change', '#wpids-grad-type', function () {
        state.type = $(this).val();
        toggleTypeControls(state.type);
        updatePreview();
    });

    function toggleTypeControls(type) {
        $('#wpids-grad-angle-wrap').toggle(type !== 'radial');
        $('#wpids-grad-radial-wrap, #wpids-grad-at-wrap').toggle(type === 'radial');
    }

    // ─── Angle ────────────────────────────────────────────────────
    $(document).on('input', '#wpids-grad-angle-range', function () {
        state.angle = parseInt($(this).val());
        $('#wpids-grad-angle-num').val(state.angle);
        updatePreview();
    });
    $(document).on('input', '#wpids-grad-angle-num', function () {
        state.angle = Math.max(0, Math.min(360, parseInt($(this).val()) || 0));
        $('#wpids-grad-angle-range').val(state.angle);
        updatePreview();
    });

    // ─── Shape / At ───────────────────────────────────────────────
    $(document).on('change', '#wpids-grad-shape', function () {
        state.shape = $(this).val();
        updatePreview();
    });
    $(document).on('change', '#wpids-grad-at', function () {
        state.at = $(this).val();
        updatePreview();
    });

    // ─── Auto slug from name ───────────────────────────────────────
    $(document).on('input', '#wpids-grad-name', function () {
        state.name = $(this).val();
        if (editIndex === -1) {
            var auto = state.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            $('#wpids-grad-slug').val(auto);
            state.slug = auto;
        }
    });
    $(document).on('input', '#wpids-grad-slug', function () {
        state.slug = $(this).val().toLowerCase().replace(/[^a-z0-9-]/g, '');
        $(this).val(state.slug);
    });

    // ─── Stop Management ──────────────────────────────────────────

    // Combined palette: GP colors + expanded solid colors (no gradients)
    var palette = [].concat(cfg.gpColors || [], cfg.expandedColors || []);

    function renderStops() {
        var $list = $('#wpids-grad-stops').empty();

        state.stops.forEach(function (stop, i) {
            var $row = $([
                '<div class="wpids-stop-row" data-index="' + i + '">',
                    '<div class="wpids-stop-top">',
                        '<input type="color" class="wpids-stop-color" value="' + escHtml(stop.color) + '" />',
                        '<div class="wpids-stop-pos-wrap">',
                            '<input type="range" class="wpids-stop-range" min="0" max="100" value="' + stop.position + '" />',
                            '<input type="number" class="wpids-stop-num" min="0" max="100" value="' + stop.position + '" />',
                            '<span class="wpids-stop-pct">%</span>',
                        '</div>',
                        (state.stops.length > 2 ? '<button type="button" class="wpids-stop-del">&times;</button>' : ''),
                    '</div>',
                    buildPaletteRow(i),
                '</div>'
            ].join(''));
            $list.append($row);
        });
    }

    function buildPaletteRow(stopIndex) {
        if (!palette.length) return '';
        var swatches = palette.map(function (c) {
            return '<span class="wpids-pal-swatch" title="' + escHtml('--' + c.slug + ' ' + c.color) + '" '
                 + 'style="background:' + escHtml(c.color) + ';" '
                 + 'data-color="' + escHtml(c.color) + '" '
                 + 'data-stop="' + stopIndex + '"></span>';
        }).join('');
        return '<div class="wpids-pal-row">' + swatches + '</div>';
    }

    // Palette swatch click → set stop color
    $(document).on('click', '.wpids-pal-swatch', function () {
        var color = $(this).data('color');
        var idx   = parseInt($(this).data('stop'));
        state.stops[idx].color = color;
        $(this).closest('.wpids-stop-row').find('.wpids-stop-color').val(color);
        updatePreview();
    });

    // Stop color change
    $(document).on('input change', '.wpids-stop-color', function () {
        var idx = parseInt($(this).closest('.wpids-stop-row').data('index'));
        state.stops[idx].color = $(this).val();
        updatePreview();
    });

    // Stop position via range
    $(document).on('input', '.wpids-stop-range', function () {
        var idx = parseInt($(this).closest('.wpids-stop-row').data('index'));
        var val = parseInt($(this).val());
        state.stops[idx].position = val;
        $(this).closest('.wpids-stop-row').find('.wpids-stop-num').val(val);
        updatePreview();
    });

    // Stop position via number
    $(document).on('input', '.wpids-stop-num', function () {
        var idx = parseInt($(this).closest('.wpids-stop-row').data('index'));
        var val = Math.max(0, Math.min(100, parseInt($(this).val()) || 0));
        state.stops[idx].position = val;
        $(this).closest('.wpids-stop-row').find('.wpids-stop-range').val(val);
        updatePreview();
    });

    // Delete stop
    $(document).on('click', '.wpids-stop-del', function () {
        var idx = parseInt($(this).closest('.wpids-stop-row').data('index'));
        state.stops.splice(idx, 1);
        renderStops();
        updatePreview();
    });

    // Add stop
    $(document).on('click', '#wpids-grad-add-stop', function () {
        state.stops.push({ color: '#ffffff', position: 50 });
        // Sort stops by position
        state.stops.sort(function (a, b) { return a.position - b.position; });
        renderStops();
        updatePreview();
    });

    // ─── Dark Mode ────────────────────────────────────────────────
    $(document).on('change', '#wpids-grad-dark-auto', function () {
        if ($(this).is(':checked')) {
            computeDarkStops();
        } else {
            state.darkStops = [];
            $('#wpids-grad-dark-preview-wrap').hide();
        }
    });

    function computeDarkStops() {
        if (!state.stops.length) return;

        $.post(cfg.ajaxUrl, {
            action: 'wpids_dark_gradient',
            nonce:  cfg.nonce,
            stops:  state.stops
        })
        .done(function (res) {
            if (res.success) {
                state.darkStops = res.data.dark_stops;
                updateDarkPreview();
            }
        });
    }

    function updateDarkPreview() {
        if (!state.darkStops.length) {
            $('#wpids-grad-dark-preview-wrap').hide();
            return;
        }

        var css = buildGradientCSS(state.type, state.angle, state.shape, state.at, state.darkStops);
        $('#wpids-grad-dark-preview').css('background', css);
        $('#wpids-grad-dark-preview-wrap').show();
    }

    // ─── Live Preview ─────────────────────────────────────────────
    function updatePreview() {
        var css = buildGradientCSS(state.type, state.angle, state.shape, state.at, state.stops);
        $('#wpids-grad-preview').css('background', css);
        $('#wpids-grad-css-output').text('--' + (state.slug || 'your-gradient') + ': ' + css + ';');

        // Recompute dark stops when preview updates
        if ($('#wpids-grad-dark-auto').is(':checked')) {
            computeDarkStops();
        }
    }

    /**
     * Build gradient CSS value string (mirrors PHP build_gradient_css).
     */
    function buildGradientCSS(type, angle, shape, at, stops) {
        var stopsStr = stops.map(function (s) {
            return s.color + ' ' + s.position + '%';
        }).join(', ');

        switch (type) {
            case 'radial':
                return 'radial-gradient(' + shape + ' at ' + at + ', ' + stopsStr + ')';
            case 'conic':
                return 'conic-gradient(from ' + angle + 'deg, ' + stopsStr + ')';
            default:
                return 'linear-gradient(' + angle + 'deg, ' + stopsStr + ')';
        }
    }

    // ─── Save ─────────────────────────────────────────────────────
    $(document).on('click', '#wpids-grad-save', function () {
        state.name  = $('#wpids-grad-name').val().trim();
        state.slug  = $('#wpids-grad-slug').val().trim();
        state.angle = parseInt($('#wpids-grad-angle-num').val()) || 135;

        if (!state.slug) {
            alert('Please enter a CSS variable name.');
            return;
        }
        if (state.stops.length < 2) {
            alert('Please add at least 2 color stops.');
            return;
        }

        var entry = {
            slug:       state.slug,
            name:       state.name || state.slug,
            type:       state.type,
            angle:      state.angle,
            shape:      state.shape,
            at:         state.at,
            stops:      JSON.parse(JSON.stringify(state.stops)),
            dark_stops: JSON.parse(JSON.stringify(state.darkStops))
        };

        if (editIndex >= 0) {
            savedList[editIndex] = entry;
        } else {
            // Check for slug collision
            var collision = savedList.findIndex(function (g) { return g.slug === entry.slug; });
            if (collision >= 0) {
                if (!confirm('A gradient with slug "' + entry.slug + '" already exists. Replace it?')) return;
                savedList[collision] = entry;
            } else {
                savedList.push(entry);
            }
        }

        var $btn = $(this).prop('disabled', true).text('Saving...');

        $.post(cfg.ajaxUrl, {
            action:    'wpids_save_gradients',
            nonce:     cfg.nonce,
            gradients: savedList,
            sync_dark: 0
        })
        .done(function (res) {
            if (res.success) {
                $('#wpids-grad-modal').fadeOut(200);
                renderSavedList();

                // Show reload notice — GP React Color reads from DB on page load
                showGradStatus(res.data.message + ' Reload Customizer to see palette.');
                showReloadNotice();

                // Refresh preview iframe so :root variables update immediately
                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }
            } else {
                alert(res.data || 'Save failed.');
            }
        })
        .fail(function () {
            alert('Server error.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Save Gradient');
        });
    });

    // ─── Delete ───────────────────────────────────────────────────
    $(document).on('click', '.wpids-grad-delete', function () {
        var idx = parseInt($(this).data('index'));
        if (!confirm('Delete this gradient variable?')) return;

        savedList.splice(idx, 1);

        $.post(cfg.ajaxUrl, {
            action:    'wpids_save_gradients',
            nonce:     cfg.nonce,
            gradients: savedList
        })
        .done(function (res) {
            if (res.success) {
                renderSavedList();
                showGradStatus('Gradient deleted.');
            }
        });
    });

    // ─── Render saved list ────────────────────────────────────────
    function renderSavedList() {
        var $list = $('#wpids-grad-list').empty();

        if (!savedList.length) {
            $list.append('<div class="wpids-ci-empty">No gradient variables yet. Click the button below to create one.</div>');
            return;
        }

        savedList.forEach(function (g, i) {
            var css  = buildGradientCSS(g.type, g.angle, g.shape, g.at, g.stops);
            var type = (g.type || 'linear').charAt(0).toUpperCase() + (g.type || 'linear').slice(1);
            $list.append([
                '<div class="wpids-grad-saved-row" data-index="' + i + '">',
                    '<div class="wpids-grad-swatch" style="background:' + css + ';"></div>',
                    '<div class="wpids-set-info">',
                        '<span class="wpids-set-slug">--' + escHtml(g.slug) + '</span>',
                        '<span class="wpids-set-count">' + escHtml(g.name || g.slug) + ' &middot; ' + type + '</span>',
                    '</div>',
                    '<div class="wpids-set-actions">',
                        '<button type="button" class="wpids-grad-edit" data-index="' + i + '" title="Edit">&#9998;</button>',
                        '<button type="button" class="wpids-grad-delete" data-index="' + i + '" title="Delete">&times;</button>',
                    '</div>',
                '</div>'
            ].join(''));
        });
    }

    // ─── ESC key ──────────────────────────────────────────────────
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('#wpids-grad-modal').fadeOut(200);
    });

    // ─── Reload Notice ────────────────────────────────────────────
    /**
     * Show a persistent reload banner.
     * GP React Color reads from DB only at page load,
     * so the palette only updates after Customizer reload.
     */
    function showReloadNotice() {
        if ($('#wpids-grad-reload-notice').length) return; // already shown

        var $notice = $([
            '<div id="wpids-grad-reload-notice" style="',
                'background:#1d2327;color:#f0f0f1;padding:10px 14px;border-radius:6px;',
                'margin-top:10px;font-size:12px;display:flex;align-items:center;gap:10px;">',
                '<span>&#9432; Reload Customizer to see gradient in GP Color palette.</span>',
                '<button id="wpids-grad-reload-btn" style="',
                    'background:#2271b1;color:#fff;border:none;padding:5px 12px;',
                    'border-radius:4px;cursor:pointer;font-size:11px;white-space:nowrap;">',
                    'Reload Now',
                '</button>',
            '</div>'
        ].join(''));

        $('#wpids-grad-wrap').append($notice);

        $('#wpids-grad-reload-btn').on('click', function () {
            window.location.reload();
        });
    }

    // ─── Status ───────────────────────────────────────────────────
    function showGradStatus(msg) {
        $('#wpids-grad-status').text(msg).css('color', '#16a34a').show();
        setTimeout(function () { $('#wpids-grad-status').fadeOut(); }, 4000);
    }

    // ─── Helper ───────────────────────────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})(jQuery, window.wp);
