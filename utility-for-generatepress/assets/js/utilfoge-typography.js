(function($, wp) {
    'use strict';

    wp.customize.bind('ready', function() {
        var $section = $('#sub-section-utilfoge_typography_section');

        // 2. Create Modal Structure
        var $modal = $([
            '<div id="utilfoge-typo-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999999; display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">',
                '<div style="background:#fff; width:90%; max-width:1000px; height:85vh; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 50px rgba(0,0,0,0.3);">',
                    // Header
                    '<div style="padding:20px 30px; background:#f8f9fa; border-bottom:1px solid #e5e5e5; display:flex; justify-content:space-between; align-items:center;">',
                        '<div>',
                            '<h2 style="margin:0; font-size:20px; font-weight:700; color:#1d2327;">Typography Scale Wizard</h2>',
                            '<p style="margin:5px 0 0 0; font-size:13px; color:#646970;">Visualize and simulate your responsive scale.</p>',
                        '</div>',
                        '<button id="utilfoge-typo-modal-close" style="background:none; border:none; font-size:28px; cursor:pointer; color:#646970;">&times;</button>',
                    '</div>',
                    
                    // Toolbar (Simulator)
                    '<div style="padding:15px 30px; background:#fff; border-bottom:1px solid #e5e5e5; display:flex; align-items:center; gap:20px;">',
                        '<div style="flex:1; display:flex; align-items:center; gap:15px;">',
                            '<label style="font-size:12px; font-weight:600; color:#3c434a; white-space:nowrap;">Viewport Width:</label>',
                            '<input type="range" id="utilfoge-viewport-slider" min="320" max="1440" value="1280" style="flex:1; cursor:pointer;">',
                            '<span id="utilfoge-viewport-val" style="background:#2271b1; color:#fff; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:600; min-width:50px; text-align:center;">1280px</span>',
                        '</div>',
                        '<div style="width:1px; height:24px; background:#e5e5e5;"></div>',
                        '<button id="utilfoge-viewport-reset" class="utilfoge-btn-outline" style="height:28px; font-size:11px;">Reset</button>',
                    '</div>',

                    // Content Area (Scrollable)
                    '<div id="utilfoge-modal-canvas-wrap" style="flex:1; overflow:auto; background:#dcdcde; padding:40px; display:flex; justify-content:center; align-items: flex-start;">',
                        '<div id="utilfoge-modal-canvas" style="background:#fff; width:1280px; max-width:100%; transition: width 0.2s ease; padding:60px; box-shadow:0 10px 30px rgba(0,0,0,0.15); min-height:100%; border-radius:4px; box-sizing: border-box;">',
                            '<div id="utilfoge-modal-list"></div>',
                        '</div>',
                    '</div>',
                '</div>',
            '</div>'
        ].join(''));

        $('body').append($modal.hide());

        // 3. Logic to update Modal Content
        // 3. Logic to update Modal Content
        function renderModalContent() {
            var getVal = function(id, fallback) {
                if (wp.customize.has(id)) {
                    return wp.customize(id).get();
                }
                return fallback;
            };

            var base = parseFloat(getVal('utilfoge_typo_base_size', 16));
            var unit = getVal('utilfoge_typo_base_unit', 'px');
            var text = getVal('utilfoge_typo_preview_text', 'The quick brown fox');
            var ratio = getVal('utilfoge_typo_scale_ratio', '1.250');
            var minVW = parseInt(getVal('utilfoge_typo_min_vw', 320));
            var maxVW = parseInt(getVal('utilfoge_typo_max_vw', 1280));

            if (ratio === 'custom') {
                ratio = parseFloat(getVal('utilfoge_typo_custom_ratio', 1.2));
            } else {
                ratio = parseFloat(ratio);
            }

            // If unit is rem/em, we assume 16px root for calculation
            var basePx = (unit === 'px') ? base : base * 16;

            var $list = $('#utilfoge-modal-list').empty();
            var labels = {
                6: 'Heading 1 (Step 6)',
                5: 'Heading 2 (Step 5)',
                4: 'Heading 3 (Step 4)',
                3: 'Heading 4 (Step 3)',
                2: 'Heading 5 (Step 2)',
                1: 'Heading 6 (Step 1)',
                0: 'Body / Paragraph (Step 0)',
                '-1': 'Small Text (Step -1)'
            };

            for (var i = 6; i >= -1; i--) {
                var sizeAtMax = basePx * Math.pow(ratio, i);
                var sizeAtMin = sizeAtMax / 1.2; // Matching the PHP logic factor

                // Calculate current size based on simulator width
                var currentWidth = parseInt($('#utilfoge-viewport-slider').val());
                var currentSize;
                
                if (currentWidth <= minVW) {
                    currentSize = sizeAtMin;
                } else if (currentWidth >= maxVW) {
                    currentSize = sizeAtMax;
                } else {
                    // Fluid interpolation
                    var slope = (sizeAtMax - sizeAtMin) / (maxVW - minVW);
                    currentSize = (currentWidth - minVW) * slope + sizeAtMin;
                }

                var $item = $('<div style="margin-bottom:35px; border-bottom:1px solid #f0f0f1; padding-bottom:20px;"></div>');
                
                var meta = '<div style="display:flex; justify-content:space-between; margin-bottom:10px;">';
                meta += '<span style="font-weight:700; color:#2271b1; font-size:12px; text-transform:uppercase;">' + labels[i] + '</span>';
                meta += '<span style="font-family:monospace; font-size:11px; color:#888;">' + currentSize.toFixed(1) + 'px</span>';
                meta += '</div>';

                $item.append(meta);
                $item.append('<div class="typo-text" style="font-size:' + currentSize + 'px; color:#1d2327; line-height:1.2;">' + text + '</div>');
                
                $list.append($item);
            }
        }

        // 4. Modal Interactions
        $(document).on('click', '#utilfoge-launch-wizard-btn', function() {
            $modal.fadeIn(200);
            renderModalContent();
        });

        $('#utilfoge-typo-modal-close').on('click', function() {
            $modal.fadeOut(200);
        });

        // Simulator Interaction
        $('#utilfoge-viewport-slider').on('input', function() {
            var val = $(this).val();
            $('#utilfoge-viewport-val').text(val + 'px');
            $('#utilfoge-modal-canvas').css('width', val + 'px');
            renderModalContent();
        });

        $('#utilfoge-viewport-reset').on('click', function() {
            $('#utilfoge-viewport-slider').val(1280).trigger('input');
        });

        // Close on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') $modal.fadeOut(200);
        });

        // 6. Sync Custom Grid Inputs to Customizer Settings
        // Use delegated listener to ensure it works even if controls re-render
        $(document).on('change input', '.utilfoge-fluid-grid-container input, .utilfoge-fluid-grid-container select', function() {
            var $el = $(this);
            var settingId = $el.data('setting');
            var val = $el.val();
            
            if (settingId && wp.customize.has(settingId)) {
                wp.customize(settingId).set(val);
                
                // If it's a "refresh" transport, the set() above triggers it.
                // For immediate visual feedback in the wizard modal:
                if ($('#utilfoge-typo-modal').is(':visible')) {
                    renderModalContent();
                }
            }
        });

        // Listen for changes from the sidebar (standard controls) to update the wizard
        wp.customize.bind('change', function(setting) {
            if (setting.id.indexOf('utilfoge_typo_') !== -1 && $('#utilfoge-typo-modal').is(':visible')) {
                renderModalContent();
            }
        });
    });

})(jQuery, wp);
