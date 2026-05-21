/**
 * UTILFOGE Gradient Preview JS
 * Runs inside the Customizer preview iframe.
 * Listens for gradient changes from the control sidebar
 * and injects CSS variables + utility classes in real-time.
 */
( function( api ) {
	'use strict';

	api.bind( 'preview-ready', function() {

		api.preview.bind( 'utilfoge-gradient-preview', function( data ) {
			var gradients = data.gradients || [];
			var css = buildPreviewCSS( gradients );

			var $el = document.getElementById( 'utilfoge-gradient-preview-live' );
			if ( ! $el ) {
				$el = document.createElement( 'style' );
				$el.id = 'utilfoge-gradient-preview-live';
				document.head.appendChild( $el );
			}
			$el.textContent = css;
		} );

	} );

	function buildPreviewCSS( gradients ) {
		var css = ':root {\n';
		var utilities = '';

		gradients.forEach( function( g ) {
			var slug = g.slug;
			if ( ! slug || ! g.stops || g.stops.length < 2 ) return;

			var grad = buildGradientCSS( g );
			if ( ! grad ) return;

			// WP-compatible variable (matches what theme.json generates)
			css += '\t--wp--preset--gradient--' + slug + ': ' + grad + ';\n';

			// Utility classes
			utilities += '.has-' + slug + '-gradient-text{background:' + grad + ';-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:inline;}\n';
			utilities += '.has-' + slug + '-gradient-border{border-style:solid!important;border-image:' + grad + ' 1;}\n';
		} );

		css += '}\n' + utilities;
		return css;
	}

	function buildGradientCSS( g ) {
		if ( ! g.stops || g.stops.length < 2 ) return '';
		var stops = g.stops.map( function( s ) {
			return s.color + ' ' + s.position + '%';
		} ).join( ', ' );

		if ( g.type === 'radial' ) {
			return 'radial-gradient(' + ( g.shape || 'ellipse' ) + ' at ' + ( g.at || 'center' ) + ', ' + stops + ')';
		}
		if ( g.type === 'conic' ) {
			return 'conic-gradient(from ' + ( parseInt( g.angle ) || 0 ) + 'deg, ' + stops + ')';
		}
		return 'linear-gradient(' + ( parseInt( g.angle ) || 135 ) + 'deg, ' + stops + ')';
	}

} )( wp.customize );
