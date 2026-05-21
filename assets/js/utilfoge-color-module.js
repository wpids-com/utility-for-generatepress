/**
 * UTILFOGE Color Module — Customizer JS
 * Handles: Parse colors, Mapping wizard modal, Preview modal, Save/Delete sets.
 */
(function ($, wp) {
    'use strict';

    var cfg = window.utilfogeColorModule || {};
    var gpColors = cfg.gpColors || [];      // existing GP global colors
    var savedSets = cfg.savedExpanded || []; // already saved color sets

    // ─── Utilities ───────────────────────────────────────────────

    function showStatus(msg, isError) {
        var $s = $('#utilfoge-ci-status');
        $s.text(msg)
          .css('color', isError ? '#dc2626' : '#16a34a')
          .show();
        setTimeout(function () { $s.fadeOut(); }, 4000);
    }

    function showEditorStatus(msg, isError) {
        var $s = $('#utilfoge-editor-status');
        $s.text(msg)
          .css('color', isError ? '#dc2626' : '#00a32a')
          .show();
        setTimeout(function () { $s.fadeOut(); }, 4000);
    }

    function showError(msg) {
        $('#utilfoge-parse-error').text(msg).show();
    }

    function clearError() {
        $('#utilfoge-parse-error').hide().text('');
    }

    // ─── Step 1: Parse Colors ────────────────────────────────────

    $(document).on('click', '#utilfoge-parse-btn', function () {
        clearError();
        var raw = $('#utilfoge-color-raw-input').val().trim();
        if (!raw) {
            showError('Please paste some colors first.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Parsing...');

        $.post(cfg.ajaxUrl, {
            action: 'utilfoge_parse_colors',
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
        var $rows = $('#utilfoge-mapping-rows').empty();

        $.each(parsedColors, function (detectedSlug, hex) {
            var row = buildMappingRow(detectedSlug, hex);
            $rows.append(row);
        });

        $('#utilfoge-mapping-modal').fadeIn(200);
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
            '<div class="utilfoge-map-row" data-hex="' + escHtml(hex) + '">',
                '<div class="utilfoge-map-swatch" style="background:' + escHtml(hex) + ';"></div>',
                '<div class="utilfoge-map-detected">',
                    '<code>' + escHtml(hex) + '</code>',
                    '<small>' + escHtml(detectedSlug) + '</small>',
                '</div>',
                '<div class="utilfoge-map-arrow">→</div>',
                '<div class="utilfoge-map-assign">',
                    '<select class="utilfoge-map-select">' + options + '</select>',
                    '<input type="text" class="utilfoge-map-newname" placeholder="new-variable-name" value="' + escHtml(newNameVal) + '" style="' + (isNew ? '' : 'display:none;') + '"/>',
                '</div>',
                '<button type="button" class="utilfoge-map-skip" title="Skip this color">Skip</button>',
            '</div>'
        ].join(''));
    }

    // Toggle new name field when select changes
    $(document).on('change', '.utilfoge-map-select', function () {
        var $input = $(this).closest('.utilfoge-map-assign').find('.utilfoge-map-newname');
        if ($(this).val() === '__new__') {
            $input.show().focus();
        } else {
            $input.hide();
        }
    });

    // Skip row
    $(document).on('click', '.utilfoge-map-skip', function () {
        $(this).closest('.utilfoge-map-row').addClass('utilfoge-map-skipped').css('opacity', 0.4);
        $(this).text('Undo').removeClass('utilfoge-map-skip').addClass('utilfoge-map-undo');
    });
    $(document).on('click', '.utilfoge-map-undo', function () {
        $(this).closest('.utilfoge-map-row').removeClass('utilfoge-map-skipped').css('opacity', 1);
        $(this).text('Skip').removeClass('utilfoge-map-undo').addClass('utilfoge-map-skip');
    });

    // Close modal
    $(document).on('click', '#utilfoge-modal-close, #utilfoge-modal-cancel', function () {
        $('#utilfoge-mapping-modal').fadeOut(200);
    });

    // ─── Apply Import ─────────────────────────────────────────────

    $(document).on('click', '#utilfoge-modal-apply', function () {
        var colors = [];

        $('#utilfoge-mapping-rows .utilfoge-map-row').each(function () {
            if ($(this).hasClass('utilfoge-map-skipped')) return;

            var hex     = $(this).data('hex');
            var select  = $(this).find('.utilfoge-map-select').val();
            var isNew   = (select === '__new__');
            var slug    = isNew
                ? $(this).find('.utilfoge-map-newname').val().trim().replace(/[^a-z0-9-]/gi, '-').toLowerCase()
                : select;

            if (!slug || !hex) return;

            // gp_replace: true when user maps to an existing GP slug (not creating new)
            var isGpReplace = !isNew && gpColors.some(function (c) { return c.slug === slug; });

            colors.push({
                slug:       slug,
                hex:        hex,
                gp_replace: isGpReplace ? 1 : 0,
                options: {
                    scale:            $('#utilfoge-opt-scale').is(':checked') ? 1 : 0,
                    complementary:    $('#utilfoge-opt-complementary').is(':checked') ? 1 : 0,
                    triadic:          $('#utilfoge-opt-triadic').is(':checked') ? 1 : 0,
                    analogous:        $('#utilfoge-opt-analogous').is(':checked') ? 1 : 0,
                    split_comp:       $('#utilfoge-opt-split-comp').is(':checked') ? 1 : 0,
                    dark_counterpart: $('#utilfoge-opt-dark').is(':checked') ? 1 : 0,
                }
            });
        });

        if (!colors.length) {
            alert('No colors to import. Please map at least one color.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Processing...');
        var syncDark = $('#utilfoge-opt-sync-dark').is(':checked') ? 1 : 0;

        $.post(cfg.ajaxUrl, {
            action: 'utilfoge_expand_colors',
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
                action:    'utilfoge_save_expanded',
                nonce:     cfg.nonce,
                expanded:  savedSets,
                sync_dark: syncDark
            })
            .done(function (saveRes) {
                if (!saveRes.success) {
                    alert(saveRes.data || 'Save failed.');
                    return;
                }

                $('#utilfoge-mapping-modal').fadeOut(200);
                $('#utilfoge-color-raw-input').val('');
                renderPaletteGrid();
                showStatus(saveRes.data.message);

                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }

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

    // ─── GP Customizer Polling Sync (GP -> Module) ────────────────
    
    if (wp && wp.customize) {
        wp.customize.bind('ready', function() {
            var setting = wp.customize('generate_settings');
            if (setting) {
                // Initial load
                var val = setting.get();
                if (val && val.global_colors) {
                    gpColors = val.global_colors;
                    renderPaletteGrid();
                }
                
                // GP React component mutates the array without changing the object reference,
                // so setting.bind() doesn't fire reliably. We use polling to detect changes.
                setInterval(function() {
                    var currentVal = setting.get();
                    if (currentVal && currentVal.global_colors) {
                        // Simple JSON comparison to detect changes
                        var currentStr = JSON.stringify(currentVal.global_colors);
                        var oldStr = JSON.stringify(gpColors);
                        if (currentStr !== oldStr) {
                            gpColors = JSON.parse(currentStr); // Deep copy
                            renderPaletteGrid();
                        }
                    }
                }, 1000);
            }
        });
    }

    function renderPaletteGrid() {
        var $container = $('#utilfoge-color-palette');
        if (!$container.length) return;

        $container.empty();

        if (!gpColors || !gpColors.length) {
            $container.append('<span class="utilfoge-ci-empty">No GP global colors found.</span>');
            return;
        }

        $.each(gpColors, function (i, c) {
            var $swatch = $('<button type="button" class="utilfoge-gc-swatch"></button>');
            $swatch.attr('title', c.name + ' (' + c.slug + ')')
                   .css('background-color', c.color)
                   .on('click', function () { openMassiveMenu(c.slug); });
            $container.append($swatch);
        });

        var $add = $('<button type="button" class="utilfoge-gc-swatch-add" title="Add New Color">+</button>');
        $add.on('click', function () { openMassiveMenu('__new__'); });
        $container.append($add);
    }

    // Fallback render for initial PHP localized data
    $(document).ready(function() {
        renderPaletteGrid();
    });

    // ─── Massive Menu Editor ──────────────────────────────────────

    var activeSlug = '';

    function openMassiveMenu(slug) {
        activeSlug = slug;
        var gpColor = { name: '', slug: '', color: '#000000' };
        
        if (slug !== '__new__') {
            $.each(gpColors, function(_, c) {
                if (c.slug === slug) gpColor = c;
            });
        }
        
        var setOpt = { scale: 1, dark_counterpart: 1, complementary: 0, triadic: 0, analogous: 0, split_comp: 0 };
        var existingSet = null;
        $.each(savedSets, function(_, s) {
            if (s.slug === slug) {
                existingSet = s;
                if (s.options) setOpt = s.options;
            }
        });

        $('#utilfoge-edit-name').val(gpColor.name || '');
        var $hexInput = $('#utilfoge-edit-hex');
        var colorVal = gpColor.color || '#000000';
        $hexInput.val(colorVal);

        if (wp && wp.element && wp.components && wp.components.ColorPicker) {
            var el = wp.element.createElement;
            var ColorPicker = wp.components.ColorPicker;

            // Render React Color Picker
            wp.element.render(
                el(ColorPicker, {
                    color: colorVal,
                    enableAlpha: true,
                    onChange: function(val) {
                        var newColor = typeof val === 'string' ? val : 
                            (val.rgb ? 'rgba(' + val.rgb.r + ',' + val.rgb.g + ',' + val.rgb.b + ',' + (val.rgb.a !== undefined ? val.rgb.a : 1) + ')' : 
                            (val.hex || '#000000'));
                        $hexInput.val(newColor);
                    }
                }),
                document.getElementById('utilfoge-react-color-picker-root')
            );
        } else {
            // Fallback to plain text input if React is somehow missing
            document.getElementById('utilfoge-react-color-picker-root').innerHTML = 
                '<input type="text" id="utilfoge-fallback-hex" class="utilfoge-ci-input" value="' + escHtml(colorVal) + '" />';
            $('#utilfoge-fallback-hex').on('input', function() {
                $hexInput.val($(this).val());
            });
        }

        $('#utilfoge-edit-opt-scale').prop('checked', !!setOpt.scale);
        $('#utilfoge-edit-opt-complementary').prop('checked', !!setOpt.complementary);
        $('#utilfoge-edit-opt-triadic').prop('checked', !!setOpt.triadic);
        $('#utilfoge-edit-opt-analogous').prop('checked', !!setOpt.analogous);
        $('#utilfoge-edit-opt-split-comp').prop('checked', !!setOpt.split_comp);
        $('#utilfoge-edit-opt-dark').prop('checked', !!setOpt.dark_counterpart);

        renderPreviewList(existingSet);

        $('#utilfoge-editor-status').hide();
        $('#utilfoge-editor-modal').fadeIn(200);
    }

    function renderPreviewList(setObj) {
        var $list = $('#utilfoge-editor-preview-list').empty();
        var hasVars = setObj && setObj.variables && Object.keys(setObj.variables).length > 0;
        var hasDark = setObj && setObj.dark_counterparts && Object.keys(setObj.dark_counterparts).length > 0;

        if (!hasVars && !hasDark) {
            $list.append('<span class="utilfoge-ci-empty" style="grid-column: 1 / -1;">No derivatives generated yet. Click Apply & Save.</span>');
            return;
        }

        if (hasVars) {
            $.each(setObj.variables, function (varName, hex) {
                var $item = $([
                    '<div class="utilfoge-ep-item">',
                        '<div class="utilfoge-ep-swatch" style="background-color:' + escHtml(hex) + ';"></div>',
                        '<code>' + escHtml(varName) + '</code>',
                        '<button type="button" class="utilfoge-ep-copy-btn" data-clipboard="' + escHtml(varName) + '" title="Copy CSS variable"><span class="dashicons dashicons-admin-page"></span></button>',
                    '</div>'
                ].join(''));
                $list.append($item);
            });
        }

        if (hasDark) {
            $.each(setObj.dark_counterparts, function (slug, hex) {
                var varName = '--' + slug;
                var $item = $([
                    '<div class="utilfoge-ep-item" style="border-color: #374151; background: #f8fafc;">',
                        '<div class="utilfoge-ep-swatch" style="background-color:' + escHtml(hex) + ';"></div>',
                        '<code>' + escHtml(varName) + ' <small style="color:#64748b;">(Dark)</small></code>',
                        '<button type="button" class="utilfoge-ep-copy-btn" data-clipboard="' + escHtml(varName) + '" title="Copy CSS variable"><span class="dashicons dashicons-admin-page"></span></button>',
                    '</div>'
                ].join(''));
                $list.append($item);
            });
        }
    }

    // Handle copy button in Massive Menu
    $(document).on('click', '.utilfoge-ep-copy-btn', function() {
        var $btn = $(this);
        var text = 'var(' + $btn.attr('data-clipboard') + ')';
        navigator.clipboard.writeText(text).then(function() {
            var $icon = $btn.find('.dashicons');
            $icon.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt');
            $btn.addClass('is-copied');
            setTimeout(function() {
                $icon.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page');
                $btn.removeClass('is-copied');
            }, 1500);
        });
    });

    // ─── Massive Menu Actions ─────────────────────────────────────

    $(document).on('click', '#utilfoge-editor-apply', function() {
        var name = $('#utilfoge-edit-name').val().trim();
        var hex = $('#utilfoge-edit-hex').val().trim();
        if (!name || !hex) {
            alert('Name and Hex color are required.');
            return;
        }

        var slug = activeSlug === '__new__' ? name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') : activeSlug;

        var colorObj = {
            slug: slug,
            hex: hex,
            gp_replace: 1, // We always force update GP from this editor
            options: {
                scale: $('#utilfoge-edit-opt-scale').is(':checked') ? 1 : 0,
                complementary: $('#utilfoge-edit-opt-complementary').is(':checked') ? 1 : 0,
                triadic: $('#utilfoge-edit-opt-triadic').is(':checked') ? 1 : 0,
                analogous: $('#utilfoge-edit-opt-analogous').is(':checked') ? 1 : 0,
                split_comp: $('#utilfoge-edit-opt-split-comp').is(':checked') ? 1 : 0,
                dark_counterpart: $('#utilfoge-edit-opt-dark').is(':checked') ? 1 : 0,
            }
        };

        var $btn = $(this).prop('disabled', true).text('Saving...');

        // Step 1: Expand math
        $.post(cfg.ajaxUrl, {
            action: 'utilfoge_expand_colors',
            nonce: cfg.nonce,
            colors: [colorObj]
        })
        .done(function (res) {
            if (!res.success) {
                alert(res.data || 'Expansion failed.');
                $btn.prop('disabled', false).text('Apply & Save');
                return;
            }

            var newSet = res.data.expanded[0];
            var exists = false;
            $.each(savedSets, function (i, existing) {
                if (existing.slug === newSet.slug) {
                    savedSets[i] = newSet;
                    exists = true;
                    return false;
                }
            });
            if (!exists) savedSets.push(newSet);

            // Step 2: Save to DB
            $.post(cfg.ajaxUrl, {
                action: 'utilfoge_save_expanded',
                nonce: cfg.nonce,
                expanded: savedSets,
                sync_dark: 1
            })
            .done(function (saveRes) {
                $btn.prop('disabled', false).text('Apply & Save');
                if (!saveRes.success) {
                    alert(saveRes.data || 'Save failed.');
                    return;
                }

                activeSlug = newSet.slug; // If it was __new__, update active to new slug
                renderPreviewList(newSet);
                showEditorStatus('Saved successfully!');

                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }

                // If gp_replace actually updated DB natively, we might need a reload.
                // But the customizer setting itself might not update on JS side instantly unless we trigger it.
                // Let's trigger a native Customizer update so the React picker knows.
                if (wp && wp.customize && wp.customize('generate_settings')) {
                    var current = wp.customize('generate_settings').get();
                    if (current && current.global_colors) {
                        var gpFound = false;
                        $.each(current.global_colors, function(i, c) {
                            if (c.slug === newSet.slug) {
                                current.global_colors[i].color = newSet.hex;
                                current.global_colors[i].name = name;
                                gpFound = true;
                            }
                        });
                        if (!gpFound) {
                            current.global_colors.push({ slug: newSet.slug, name: name, color: newSet.hex });
                        }
                        wp.customize('generate_settings').set($.extend({}, current));
                    }
                }

                // Sync Dark Mode Customizer UI
                if (saveRes.data && saveRes.data.updated_dark_colors && wp && wp.customize && wp.customize('utilfoge_dark_global_colors')) {
                    wp.customize('utilfoge_dark_global_colors').set($.extend([], saveRes.data.updated_dark_colors));
                }
            });
        })
        .fail(function() {
            alert('Server error.');
            $btn.prop('disabled', false).text('Apply & Save');
        });
    });

    $(document).on('click', '#utilfoge-resync-dark-btn', function() {
        var $btn = $(this);
        var oldText = $btn.text();
        $btn.prop('disabled', true).text('Syncing...');

        $.post(cfg.ajaxUrl, {
            action: 'utilfoge_sync_dark_auto',
            nonce: cfg.nonce
        })
        .done(function (res) {
            $btn.prop('disabled', false).text(oldText);
            if (res.success) {
                showStatus(res.data.message);
                
                if (res.data && res.data.updated_dark_colors && wp && wp.customize && wp.customize('utilfoge_dark_global_colors')) {
                    wp.customize('utilfoge_dark_global_colors').set($.extend([], res.data.updated_dark_colors));
                }

                if (wp && wp.customize && wp.customize.previewer) {
                    wp.customize.previewer.refresh();
                }
            } else {
                alert(res.data || 'Sync failed.');
            }
        })
        .fail(function() {
            $btn.prop('disabled', false).text(oldText);
            alert('Server error during dark sync.');
        });
    });

    $(document).on('click', '#utilfoge-edit-delete', function() {
        if (activeSlug === '__new__') {
            $('#utilfoge-editor-modal').fadeOut(200);
            return;
        }

        if (!confirm('Delete this color from GeneratePress Global Colors completely?')) return;

        // 1. Remove from savedSets
        var newSaved = [];
        $.each(savedSets, function(_, s) {
            if (s.slug !== activeSlug) newSaved.push(s);
        });
        savedSets = newSaved;

        // 2. Remove from GP customizer
        if (wp && wp.customize && wp.customize('generate_settings')) {
            var current = wp.customize('generate_settings').get();
            if (current && current.global_colors) {
                var newGp = [];
                $.each(current.global_colors, function(_, c) {
                    if (c.slug !== activeSlug) newGp.push(c);
                });
                current.global_colors = newGp;
                wp.customize('generate_settings').set($.extend({}, current));
            }
        }

        // 3. Save expanded to DB
        $.post(cfg.ajaxUrl, {
            action: 'utilfoge_save_expanded',
            nonce: cfg.nonce,
            expanded: savedSets,
            sync_dark: 1
        });

        $('#utilfoge-editor-modal').fadeOut(200);
        showStatus('Color deleted.');
    });

    $(document).on('click', '#utilfoge-editor-cancel, #utilfoge-editor-close', function () {
        if (wp && wp.element && wp.element.unmountComponentAtNode) {
            wp.element.unmountComponentAtNode(document.getElementById('utilfoge-react-color-picker-root'));
        }
        $('#utilfoge-editor-modal').fadeOut(200);
    });

    // ─── ESC key closes modals ────────────────────────────────────

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#utilfoge-mapping-modal, #utilfoge-editor-modal').fadeOut(200);
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
        if ($('#utilfoge-reload-notice').length) return;

        var $notice = $([
            '<div id="utilfoge-reload-notice" style="',
                'background:#1d2327;color:#f0f0f1;padding:10px 14px;border-radius:6px;',
                'margin-top:10px;font-size:12px;display:flex;align-items:center;gap:10px;">',
                '<span>&#9432; Reload Customizer to see updated GP Color palette.</span>',
                '<button id="utilfoge-reload-btn" style="',
                    'background:#2271b1;color:#fff;border:none;padding:5px 12px;',
                    'border-radius:4px;cursor:pointer;font-size:11px;white-space:nowrap;">',
                    'Reload Now',
                '</button>',
            '</div>'
        ].join(''));

        $('#utilfoge-color-import-wrap').append($notice);

        $('#utilfoge-reload-btn').on('click', function () {
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
