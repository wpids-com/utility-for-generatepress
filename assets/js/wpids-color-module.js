/**
 * WPIDS Color Module — Customizer JS
 * Handles: Parse colors, Mapping wizard modal, Preview modal, Save/Delete sets.
 */
(function ($, wp) {
    'use strict';

    var cfg = window.wpidsColorModule || {};
    var gpColors = cfg.gpColors || [];      // existing GP global colors
    var savedSets = cfg.savedExpanded || []; // already saved color sets

    // ─── Utilities ───────────────────────────────────────────────

    function showStatus(msg, isError) {
        var $s = $('#wpids-ci-status');
        $s.text(msg)
          .css('color', isError ? '#dc2626' : '#16a34a')
          .show();
        setTimeout(function () { $s.fadeOut(); }, 4000);
    }

    function showError(msg) {
        $('#wpids-parse-error').text(msg).show();
    }

    function clearError() {
        $('#wpids-parse-error').hide().text('');
    }

    // ─── Step 1: Parse Colors ────────────────────────────────────

    $(document).on('click', '#wpids-parse-btn', function () {
        clearError();
        var raw = $('#wpids-color-raw-input').val().trim();
        if (!raw) {
            showError('Please paste some colors first.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Parsing...');

        $.post(cfg.ajaxUrl, {
            action: 'wpids_parse_colors',
            nonce: cfg.nonce,
            raw: raw
        })
        .done(function (res) {
            if (res.success) {
                openMappingModal(res.data.colors);
            } else {
                showError(res.data || 'Could not parse colors.');
            }
        })
        .fail(function () {
            showError('Server error. Please try again.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Parse Colors');
        });
    });

    // ─── Mapping Modal ───────────────────────────────────────────

    function openMappingModal(parsedColors) {
        var $rows = $('#wpids-mapping-rows').empty();

        $.each(parsedColors, function (detectedSlug, hex) {
            var row = buildMappingRow(detectedSlug, hex);
            $rows.append(row);
        });

        $('#wpids-mapping-modal').fadeIn(200);
    }

    function buildMappingRow(detectedSlug, hex) {
        // Build dropdown options: existing GP slugs + "Create New"
        var options = '<option value="__new__">— Create New Variable —</option>';
        $.each(gpColors, function (_, c) {
            var selected = (c.slug === detectedSlug) ? 'selected' : '';
            options += '<option value="' + escHtml(c.slug) + '" ' + selected + '>'
                     + escHtml('--' + c.slug) + ' (' + escHtml(c.color) + ')</option>';
        });

        var isNew = !gpColors.some(function (c) { return c.slug === detectedSlug; });
        var newNameVal = isNew ? detectedSlug : '';

        return $([
            '<div class="wpids-map-row" data-hex="' + escHtml(hex) + '">',
                '<div class="wpids-map-swatch" style="background:' + escHtml(hex) + ';"></div>',
                '<div class="wpids-map-detected">',
                    '<code>' + escHtml(hex) + '</code>',
                    '<small>' + escHtml(detectedSlug) + '</small>',
                '</div>',
                '<div class="wpids-map-arrow">→</div>',
                '<div class="wpids-map-assign">',
                    '<select class="wpids-map-select">' + options + '</select>',
                    '<input type="text" class="wpids-map-newname" placeholder="new-variable-name" value="' + escHtml(newNameVal) + '" style="' + (isNew ? '' : 'display:none;') + '"/>',
                '</div>',
                '<button type="button" class="wpids-map-skip" title="Skip this color">Skip</button>',
            '</div>'
        ].join(''));
    }

    // Toggle new name field when select changes
    $(document).on('change', '.wpids-map-select', function () {
        var $input = $(this).closest('.wpids-map-assign').find('.wpids-map-newname');
        if ($(this).val() === '__new__') {
            $input.show().focus();
        } else {
            $input.hide();
        }
    });

    // Skip row
    $(document).on('click', '.wpids-map-skip', function () {
        $(this).closest('.wpids-map-row').addClass('wpids-map-skipped').css('opacity', 0.4);
        $(this).text('Undo').removeClass('wpids-map-skip').addClass('wpids-map-undo');
    });
    $(document).on('click', '.wpids-map-undo', function () {
        $(this).closest('.wpids-map-row').removeClass('wpids-map-skipped').css('opacity', 1);
        $(this).text('Skip').removeClass('wpids-map-undo').addClass('wpids-map-skip');
    });

    // Close modal
    $(document).on('click', '#wpids-modal-close, #wpids-modal-cancel', function () {
        $('#wpids-mapping-modal').fadeOut(200);
    });

    // ─── Apply Import ─────────────────────────────────────────────

    $(document).on('click', '#wpids-modal-apply', function () {
        var colors = [];

        $('#wpids-mapping-rows .wpids-map-row').each(function () {
            if ($(this).hasClass('wpids-map-skipped')) return;

            var hex     = $(this).data('hex');
            var select  = $(this).find('.wpids-map-select').val();
            var isNew   = (select === '__new__');
            var slug    = isNew
                ? $(this).find('.wpids-map-newname').val().trim().replace(/[^a-z0-9-]/gi, '-').toLowerCase()
                : select;

            if (!slug || !hex) return;

            // gp_replace: true when user maps to an existing GP slug (not creating new)
            var isGpReplace = !isNew && gpColors.some(function (c) { return c.slug === slug; });

            colors.push({
                slug:       slug,
                hex:        hex,
                gp_replace: isGpReplace ? 1 : 0,
                options: {
                    scale:            $('#wpids-opt-scale').is(':checked') ? 1 : 0,
                    complementary:    $('#wpids-opt-complementary').is(':checked') ? 1 : 0,
                    triadic:          $('#wpids-opt-triadic').is(':checked') ? 1 : 0,
                    analogous:        $('#wpids-opt-analogous').is(':checked') ? 1 : 0,
                    split_comp:       $('#wpids-opt-split-comp').is(':checked') ? 1 : 0,
                    dark_counterpart: $('#wpids-opt-dark').is(':checked') ? 1 : 0,
                }
            });
        });

        if (!colors.length) {
            alert('No colors to import. Please map at least one color.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Processing...');
        var syncDark = $('#wpids-opt-sync-dark').is(':checked') ? 1 : 0;

        $.post(cfg.ajaxUrl, {
            action: 'wpids_expand_colors',
            nonce:  cfg.nonce,
            colors: colors
        })
        .done(function (res) {
            if (!res.success) {
                alert(res.data || 'Expansion failed.');
                return;
            }

            // Merge with existing saved sets
            var newSets = res.data.expanded;
            $.each(newSets, function (_, newSet) {
                var exists = false;
                $.each(savedSets, function (i, existing) {
                    if (existing.slug === newSet.slug) {
                        savedSets[i] = newSet;
                        exists = true;
                        return false;
                    }
                });
                if (!exists) savedSets.push(newSet);
            });

            // Save to DB
            $.post(cfg.ajaxUrl, {
                action:    'wpids_save_expanded',
                nonce:     cfg.nonce,
                expanded:  savedSets,
                sync_dark: syncDark
            })
            .done(function (saveRes) {
                if (!saveRes.success) {
                    alert(saveRes.data || 'Save failed.');
                    return;
                }

                $('#wpids-mapping-modal').fadeOut(200);
                $('#wpids-color-raw-input').val('');
                renderSavedSets();
                showStatus(saveRes.data.message);

                // Refresh preview iframe immediately
                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }

                // GP React Color reads palette from DB only at page load.
                // Show a one-click reload notice if any GP colors were updated.
                if (saveRes.data.gp_replaced > 0) {
                    showReloadNotice();
                }
            });
        })
        .fail(function () {
            alert('Server error during expansion.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Import & Apply');
        });
    });

    // ─── Saved Sets Rendering ─────────────────────────────────────

    function renderSavedSets() {
        var $container = $('#wpids-saved-sets').empty();

        if (!savedSets.length) {
            $container.append('<div class="wpids-ci-empty">No color sets yet. Import colors above to get started.</div>');
            return;
        }

        $.each(savedSets, function (i, set) {
            var varCount = Object.keys(set.variables || {}).length;
            $container.append([
                '<div class="wpids-saved-set" data-index="' + i + '">',
                    '<div class="wpids-set-swatch" style="background-color:' + escHtml(set.hex) + ';"></div>',
                    '<div class="wpids-set-info">',
                        '<span class="wpids-set-slug">--' + escHtml(set.slug) + '</span>',
                        '<span class="wpids-set-count">' + varCount + ' variables</span>',
                    '</div>',
                    '<div class="wpids-set-actions">',
                        '<button type="button" class="wpids-set-preview" data-index="' + i + '" title="Preview">&#128065;</button>',
                        '<button type="button" class="wpids-set-delete" data-index="' + i + '" title="Remove">&times;</button>',
                    '</div>',
                '</div>'
            ].join(''));
        });
    }

    // ─── Preview ──────────────────────────────────────────────────

    $(document).on('click', '.wpids-set-preview', function () {
        var idx = parseInt($(this).data('index'));
        var set = savedSets[idx];
        if (!set) return;

        var lines = [];
        $.each(set.variables || {}, function (varName, hex) {
            lines.push(
                '<div class="wpids-preview-row">' +
                    '<div class="wpids-preview-swatch" style="background:' + escHtml(hex) + ';"></div>' +
                    '<code class="wpids-preview-var">' + escHtml(varName) + '</code>' +
                    '<code class="wpids-preview-hex">' + escHtml(hex) + '</code>' +
                '</div>'
            );
        });

        $('#wpids-preview-content').html(lines.join(''));
        $('#wpids-preview-modal').fadeIn(200);
    });

    $(document).on('click', '#wpids-preview-close, #wpids-preview-close-btn', function () {
        $('#wpids-preview-modal').fadeOut(200);
    });

    $(document).on('click', '#wpids-preview-copy', function () {
        var idx = parseInt($('.wpids-set-preview[data-index]').data('index'));
        var cssLines = [':root {'];
        var set = savedSets[idx];
        if (set) {
            $.each(set.variables || {}, function (v, h) {
                cssLines.push('  ' + v + ': ' + h + ';');
            });
        }
        cssLines.push('}');
        navigator.clipboard.writeText(cssLines.join('\n')).then(function () {
            showStatus('CSS copied to clipboard!');
        });
    });

    // ─── Delete ───────────────────────────────────────────────────

    $(document).on('click', '.wpids-set-delete', function () {
        var idx = parseInt($(this).data('index'));
        if (!confirm('Remove this color set? This cannot be undone.')) return;

        savedSets.splice(idx, 1);

        $.post(cfg.ajaxUrl, {
            action:   'wpids_save_expanded',
            nonce:    cfg.nonce,
            expanded: savedSets,
            sync_dark: 0
        })
        .done(function (res) {
            if (res.success) {
                renderSavedSets();
                showStatus('Color set removed.');
                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }
            }
        });
    });

    // ─── ESC key closes modals ────────────────────────────────────

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#wpids-mapping-modal, #wpids-preview-modal').fadeOut(200);
        }
    });

    // ─── Reload Notice ────────────────────────────────────────────
    /**
     * GP React Color Manager reads from generate_settings[global_colors] in DB
     * only at Customizer page load. After we update the DB via AJAX, the palette
     * can only be refreshed by reloading the Customizer page.
     * This notice gives the user a one-click way to do that.
     */
    function showReloadNotice() {
        if ($('#wpids-reload-notice').length) return;

        var $notice = $([
            '<div id="wpids-reload-notice" style="',
                'background:#1d2327;color:#f0f0f1;padding:10px 14px;border-radius:6px;',
                'margin-top:10px;font-size:12px;display:flex;align-items:center;gap:10px;">',
                '<span>&#9432; Reload Customizer to see updated GP Color palette.</span>',
                '<button id="wpids-reload-btn" style="',
                    'background:#2271b1;color:#fff;border:none;padding:5px 12px;',
                    'border-radius:4px;cursor:pointer;font-size:11px;white-space:nowrap;">',
                    'Reload Now',
                '</button>',
            '</div>'
        ].join(''));

        $('#wpids-color-import-wrap').append($notice);

        $('#wpids-reload-btn').on('click', function () {
            window.location.reload();
        });
    }

    // ─── Helper ───────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery, window.wp);
