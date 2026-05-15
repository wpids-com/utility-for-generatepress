/**
 * UTILGP Gradient Module — Customizer Control JS
 */
( function( $ ) {
	'use strict';

	if ( typeof utilgpGradientModule === 'undefined' ) return;

	var data        = utilgpGradientModule;
	var gradients   = data.saved ? JSON.parse( JSON.stringify( data.saved ) ) : [];
	var editIndex   = -1;
	var pickers     = {};
	var initialized = false;

	// ─────────────────────────────────────────
	// HELPERS
	// ─────────────────────────────────────────

	function buildGradientCSS( g ) {
		if ( ! g.stops || g.stops.length < 2 ) return '';
		var stops = g.stops.map( function( s ) { return s.color + ' ' + s.position + '%'; } ).join( ', ' );
		if ( g.type === 'radial' ) return 'radial-gradient(' + ( g.shape || 'ellipse' ) + ' at ' + ( g.at || 'center' ) + ', ' + stops + ')';
		if ( g.type === 'conic' ) return 'conic-gradient(from ' + ( parseInt( g.angle ) || 0 ) + 'deg, ' + stops + ')';
		return 'linear-gradient(' + ( parseInt( g.angle ) || 135 ) + 'deg, ' + stops + ')';
	}

	function sendPreview() {
		if ( typeof wp !== 'undefined' && typeof wp.customize !== 'undefined' && wp.customize.previewer ) {
			wp.customize.previewer.send( 'utilgp-gradient-preview', { gradients: gradients } );
		}
	}

	function slugify( name ) {
		return name.toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /(^-|-$)/g, '' );
	}

	// ─────────────────────────────────────────
	// PALETTE
	// ─────────────────────────────────────────

	function renderPalette() {
		var $palette = $( '#utilgp-gc-palette' );
		if ( ! $palette.length ) return;
		$palette.empty();

		if ( gradients.length === 0 ) {
			$palette.append( '<span class="utilgp-gc-empty">' + data.i18n.noGradients + '</span>' );
		}

		gradients.forEach( function( g, i ) {
			var css     = buildGradientCSS( g );
			var $swatch = $( '<button type="button" class="utilgp-gc-swatch"></button>' );
			$swatch.attr( 'title', g.name ).attr( 'data-index', i ).css( 'background', css || '#e0e0e0' );
			$swatch.on( 'click', function( e ) { openEditor( i, e.currentTarget ); } );
			if ( i === editIndex && $( '#utilgp-gc-editor' ).is( ':visible' ) ) {
				$swatch.addClass( 'is-active' );
			}
			$palette.append( $swatch );
		} );

		var $add = $( '<button type="button" class="utilgp-gc-swatch-add">+</button>' );
		$add.attr( 'title', data.i18n.addGradient ).on( 'click', function( e ) { openEditor( -1, e.currentTarget ); } );
		if ( editIndex === -1 && $( '#utilgp-gc-editor' ).is( ':visible' ) ) {
			$add.addClass( 'is-active' );
		}
		$palette.append( $add );

		renderClassList();
	}

	// ─────────────────────────────────────────
	// EDITOR
	// ─────────────────────────────────────────

	function openEditor( index, targetEl ) {
		// Toggle if clicking the same active swatch
		if ( editIndex === index && $( '#utilgp-gc-editor' ).is( ':visible' ) ) {
			closeEditor();
			return;
		}

		editIndex = index;
		destroyPickers();

		// Update active state on swatches
		$( '.utilgp-gc-swatch, .utilgp-gc-swatch-add' ).removeClass( 'is-active' );
		if ( targetEl ) {
			$( targetEl ).addClass( 'is-active' );
			
			// Calculate triangle position
			var $target = $( targetEl );
			var $palette = $( '#utilgp-gc-palette' );
			var targetCenter = $target.position().left + ( $target.outerWidth() / 2 );
			// Create or update triangle
			var $caret = $( '#utilgp-gc-editor-caret' );
			if ( ! $caret.length ) {
				$caret = $( '<div id="utilgp-gc-editor-caret" class="utilgp-gc-editor-caret"></div>' );
				$( '#utilgp-gc-editor' ).prepend( $caret );
			}
			$caret.css( 'left', targetCenter + 'px' );
		}

		var g = ( index === -1 ) ? {
			slug: '', name: '', type: 'linear', angle: 135, shape: 'ellipse', at: 'center',
			stops: [ { color: '#667eea', position: 0 }, { color: '#764ba2', position: 100 } ],
			dark_stops: []
		} : JSON.parse( JSON.stringify( gradients[ index ] ) );

		$( '#utilgp-gc-name' ).val( g.name );
		$( '#utilgp-gc-type' ).val( g.type || 'linear' );
		$( '#utilgp-gc-angle' ).val( g.angle || 135 );
		toggleAngleField( g.type || 'linear' );
		renderStops( g.stops );

		if ( index !== -1 && g.slug ) {
			$( '#utilgp-gc-hint-text' ).text( '.has-' + g.slug + '-gradient-text | .has-' + g.slug + '-gradient-border' );
			$( '#utilgp-gc-utility-hint' ).show();
		} else {
			$( '#utilgp-gc-utility-hint' ).hide();
		}

		$( '#utilgp-gb-settings, #utilgp-gt-settings' ).slideUp( 200 );
		$( '#utilgp-gc-editor' ).slideDown( 250 );
	}

	function closeEditor() {
		destroyPickers();
		editIndex = -2; // Reset active
		$( '.utilgp-gc-swatch, .utilgp-gc-swatch-add' ).removeClass( 'is-active' );
		$( '#utilgp-gc-editor' ).slideUp( 200 );
		$( '#utilgp-gb-settings, #utilgp-gt-settings' ).slideDown( 250 );
		$( '#utilgp-gc-status' ).text( '' );
	}

	function renderStops( stops ) {
		var $wrap = $( '#utilgp-gc-stops' );
		$wrap.empty();
		destroyPickers();
		stops.forEach( function( stop, i ) { $wrap.append( buildStopRow( stop, i ) ); } );
		stops.forEach( function( stop, i ) { initStopPicker( i, stop.color ); } );
		updatePreviewBar();
	}

	function buildStopRow( stop, i ) {
		var $row    = $( '<div class="utilgp-gc-stop-row" data-stop="' + i + '">' );
		var $input  = $( '<input type="text" class="utilgp-gc-stop-color" data-stop="' + i + '">' ).val( stop.color );
		var $slider = $( '<input type="range" class="utilgp-gc-stop-slider" min="0" max="100" data-stop="' + i + '">' ).val( stop.position );
		var $pos    = $( '<input type="number" class="utilgp-gc-stop-pos" min="0" max="100" data-stop="' + i + '">' ).val( stop.position );
		var $rm     = $( '<button type="button" class="utilgp-gc-stop-remove">&times;</button>' );

		$slider.on( 'input', function() {
			$pos.val( this.value );
			getCurrentStops()[ i ].position = parseInt( this.value );
			updatePreviewBar();
		} );
		$pos.on( 'input', function() {
			var v = Math.min( 100, Math.max( 0, parseInt( this.value ) || 0 ) );
			$slider.val( v );
			getCurrentStops()[ i ].position = v;
			updatePreviewBar();
		} );
		$rm.on( 'click', function() {
			var s = getCurrentStops();
			if ( s.length <= 2 ) return;
			s.splice( i, 1 );
			renderStops( s );
		} );

		return $row.append( $input, $slider, $pos, $rm );
	}

	function initStopPicker( i, color ) {
		var $input = $( '#utilgp-gc-stops .utilgp-gc-stop-color[data-stop="' + i + '"]' );
		if ( ! $input.length || ! $.fn.wpColorPicker ) return;
		$input.wpColorPicker( {
			defaultColor: color,
			change: function( e, ui ) { getCurrentStops()[ i ].color = ui.color.toString(); updatePreviewBar(); },
			clear: function() { getCurrentStops()[ i ].color = '#000000'; updatePreviewBar(); }
		} );
		pickers[ i ] = $input;
	}

	function destroyPickers() {
		$.each( pickers, function( i, $el ) { try { $el.iris( 'destroy' ); } catch( e ) {} } );
		pickers = {};
	}

	function getCurrentStops() {
		var stops = [];
		$( '#utilgp-gc-stops .utilgp-gc-stop-row' ).each( function() {
			stops.push( {
				color:    $( this ).find( '.utilgp-gc-stop-color' ).val() || '#000000',
				position: parseInt( $( this ).find( '.utilgp-gc-stop-pos' ).val() ) || 0
			} );
		} );
		return stops;
	}

	function getEditorGradient() {
		var name = $( '#utilgp-gc-name' ).val().trim() || 'gradient';
		return {
			slug: slugify( name ), name: name,
			type: $( '#utilgp-gc-type' ).val(),
			angle: parseInt( $( '#utilgp-gc-angle' ).val() ) || 135,
			shape: 'ellipse', at: 'center',
			stops: getCurrentStops(), dark_stops: []
		};
	}

	function updatePreviewBar() {
		var css = buildGradientCSS( getEditorGradient() );
		$( '#utilgp-gc-preview-bar' ).css( 'background', css || 'linear-gradient(135deg,#e0e0e0,#fff)' );
		sendPreview();
	}

	function toggleAngleField( type ) {
		$( '#utilgp-gc-angle-wrap' )[ type === 'radial' ? 'hide' : 'show' ]();
	}

	function saveGradients( callback ) {
		$.post( data.ajaxUrl, { action: 'utilgp_save_gradients', nonce: data.nonce, gradients: gradients },
			function( r ) { if ( r.success && typeof callback === 'function' ) callback(); }
		);
	}

	// ─────────────────────────────────────────
	// BORDER SETTINGS — uses WP Customizer setting (saves via Publish)
	// ─────────────────────────────────────────

	function getBorderData() {
		var preset = $( '#utilgp-gb-radius-preset' ).val() || 'sharp';
		var unit   = $( '#utilgp-gb-radius-unit' ).val() || 'px';
		var linked = $( '#utilgp-gb-link-sides' ).hasClass( 'is-linked' );
		return {
			radius_preset: preset,
			radius_unit:   unit,
			linked:        linked,
			radius: {
				tl: parseInt( $( '#utilgp-gb-r-tl' ).val() ) || 0,
				tr: parseInt( $( '#utilgp-gb-r-tr' ).val() ) || 0,
				bl: parseInt( $( '#utilgp-gb-r-bl' ).val() ) || 0,
				br: parseInt( $( '#utilgp-gb-r-br' ).val() ) || 0,
			}
		};
	}

	function updateBorderSetting() {
		if ( typeof wp === 'undefined' || typeof wp.customize === 'undefined' ) return;
		var setting = wp.customize( 'utilgp_gradient_border_settings' );
		if ( setting ) {
			setting.set( JSON.stringify( getBorderData() ) );
		}
	}

	function initBorderSettings() {
		var bs     = data.borderSettings || {};
		var preset = bs.radius_preset || 'sharp';

		$( '#utilgp-gb-radius-preset' ).val( preset );
		$( '#utilgp-gb-radius-unit' ).val( bs.radius_unit || 'px' );

		// Show/hide custom fields on load
		if ( preset === 'custom' ) {
			$( '#utilgp-gb-custom-radius' ).show();
		}

		// Load saved radius values
		var r = bs.radius || {};
		$( '#utilgp-gb-r-tl' ).val( r.tl || 0 );
		$( '#utilgp-gb-r-tr' ).val( r.tr || 0 );
		$( '#utilgp-gb-r-bl' ).val( r.bl || 0 );
		$( '#utilgp-gb-r-br' ).val( r.br || 0 );

		if ( ! bs.linked ) {
			$( '#utilgp-gb-link-sides' ).removeClass( 'is-linked' );
		}

		// Preset change → show/hide custom panel + update setting
		$( document ).on( 'change', '#utilgp-gb-radius-preset', function() {
			if ( $( this ).val() === 'custom' ) {
				$( '#utilgp-gb-custom-radius' ).slideDown( 200 );
			} else {
				$( '#utilgp-gb-custom-radius' ).slideUp( 200 );
			}
			updateBorderSetting();
		} );

		// Link-all-sides toggle
		$( document ).on( 'click', '#utilgp-gb-link-sides', function() {
			$( this ).toggleClass( 'is-linked' );
			updateBorderSetting();
		} );

		// Sync linked inputs
		$( document ).on( 'input', '.utilgp-gb-r-input', function() {
			if ( $( '#utilgp-gb-link-sides' ).hasClass( 'is-linked' ) ) {
				var v = $( this ).val();
				$( '.utilgp-gb-r-input' ).not( this ).val( v );
			}
			updateBorderSetting();
		} );

		// Unit change
		$( document ).on( 'change', '#utilgp-gb-radius-unit', updateBorderSetting );
	}

	// ─────────────────────────────────────────
	// CLASS LIST
	// ─────────────────────────────────────────

	function renderClassList() {
		var $listBorder = $( '#utilgp-gb-class-list-border' );
		var $listText   = $( '#utilgp-gb-class-list-text' );

		if ( ! $listBorder.length || ! $listText.length ) return;

		if ( gradients.length === 0 ) {
			$listBorder.html( '<span class="utilgp-gc-empty">' + data.i18n.noGradients + '</span>' );
			$listText.html( '<span class="utilgp-gc-empty">' + data.i18n.noGradients + '</span>' );
			return;
		}

		var htmlBorder = '';
		var htmlText   = '';

		gradients.forEach( function( g ) {
			var bClass = 'has-' + g.slug + '-gradient-border';
			htmlBorder += '<div class="utilgp-gb-class-item">';
			htmlBorder += '<code>' + bClass + '</code>';
			htmlBorder += '<button type="button" class="utilgp-gb-copy-btn" data-clipboard="' + bClass + '" title="Copy class"><span class="dashicons dashicons-admin-page"></span></button>';
			htmlBorder += '</div>';

			var tClass = 'has-' + g.slug + '-gradient-text';
			htmlText += '<div class="utilgp-gb-class-item">';
			htmlText += '<code>' + tClass + '</code>';
			htmlText += '<button type="button" class="utilgp-gb-copy-btn" data-clipboard="' + tClass + '" title="Copy class"><span class="dashicons dashicons-admin-page"></span></button>';
			htmlText += '</div>';
		} );

		$listBorder.html( htmlBorder );
		$listText.html( htmlText );
	}

	// Handle copy to clipboard
	$( document ).on( 'click', '.utilgp-gb-copy-btn', function() {
		var $btn = $( this );
		var text = $btn.attr( 'data-clipboard' );
		navigator.clipboard.writeText( text ).then( function() {
			var $icon = $btn.find( '.dashicons' );
			$icon.removeClass( 'dashicons-admin-page' ).addClass( 'dashicons-yes-alt' );
			$btn.addClass( 'is-copied' );
			setTimeout( function() {
				$icon.removeClass( 'dashicons-yes-alt' ).addClass( 'dashicons-admin-page' );
				$btn.removeClass( 'is-copied' );
			}, 1500 );
		} );
	} );

	// ─────────────────────────────────────────
	// INIT
	// ─────────────────────────────────────────

	function initControl() {
		if ( initialized ) { renderPalette(); return; }
		initialized = true;
		renderPalette();
		initBorderSettings();

		$( document )
			.on( 'click', '#utilgp-gc-back', closeEditor )
			.on( 'click', '#utilgp-gc-delete', function() {
				if ( editIndex === -1 ) { closeEditor(); return; }
				if ( ! confirm( data.i18n.delete ) ) return;
				gradients.splice( editIndex, 1 );
				saveGradients( function() { closeEditor(); sendPreview(); } );
			} )
			.on( 'change', '#utilgp-gc-type', function() { toggleAngleField( $( this ).val() ); updatePreviewBar(); } )
			.on( 'input', '#utilgp-gc-angle, #utilgp-gc-name', updatePreviewBar )
			.on( 'click', '#utilgp-gc-add-stop', function() {
				var s = getCurrentStops(); s.push( { color: '#ffffff', position: 100 } ); renderStops( s );
			} )
			.on( 'click', '#utilgp-gc-save', function() {
				var g = getEditorGradient();
				if ( g.stops.length < 2 ) { alert( data.i18n.needStops || 'Need at least 2 colour stops.' ); return; }
				if ( editIndex === -1 ) { gradients.push( g ); } else { gradients[ editIndex ] = g; }
				$( '#utilgp-gc-hint-text' ).text( '.has-' + g.slug + '-gradient-text | .has-' + g.slug + '-gradient-border' );
				$( '#utilgp-gc-utility-hint' ).show();
				var $btn = $( this ).prop( 'disabled', true );
				saveGradients( function() {
					sendPreview(); $btn.prop( 'disabled', false );
					$( '#utilgp-gc-status' ).text( data.i18n.saved );
					setTimeout( function() { $( '#utilgp-gc-status' ).text( '' ); }, 2000 );
					renderClassList();
				} );
			} );
	}

	// ── Polling: run initControl when element is in DOM
	var pollCount = 0;
	var initPoll = setInterval( function() {
		pollCount++;
		if ( $( '#utilgp-gc-palette' ).length ) {
			clearInterval( initPoll );
			initControl();
		}
		if ( pollCount > 150 ) { clearInterval( initPoll ); }
	}, 200 );

	// ─────────────────────────────────────────
	// INJECT READ-ONLY PALETTE IN GP GLOBAL COLORS
	// ─────────────────────────────────────────
	setInterval( function() {
		var $gpControl = $( '#customize-control-generate_settings-global_colors' );
		if ( $gpControl.length && ! $( '#utilgp-gp-readonly-palette' ).length ) {
			var html = '<div id="utilgp-gp-readonly-palette" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #dcdcde;">';
			html += '<span class="customize-control-title" style="font-size:11px;font-weight:600;text-transform:uppercase;color:#646970;">Gradient Palette</span>';
			html += '<div class="utilgp-gc-palette">';
			
			gradients.forEach( function( g ) {
				var css = buildGradientCSS( g );
				html += '<button type="button" class="utilgp-gc-swatch" title="' + g.name + '" style="background: ' + css + '; cursor: default; transform: none; box-shadow: none;"></button>';
			} );
			
			html += '<button type="button" class="utilgp-gc-swatch-add" title="Edit Gradients" id="utilgp-goto-gradient-settings" style="cursor: pointer; border-color: #2271b1; color: #2271b1;"><span class="dashicons dashicons-admin-settings" style="font-size:16px;width:16px;height:16px;"></span></button>';
			html += '</div></div>';
			
			// Append inside the React container if possible so it survives less aggressively
			// Actually just append to the wrapper, React will wipe it when it re-renders,
			// but our setInterval will put it right back.
			$gpControl.append( html );
		}
	}, 1000 );

	$( document ).on( 'click', '#utilgp-goto-gradient-settings', function() {
		if ( wp && wp.customize && wp.customize.section ) {
			wp.customize.section( 'utilgp_gradient_variables' ).focus();
		}
	} );

} )( jQuery );
