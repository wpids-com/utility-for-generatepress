<?php
/**
 * Color Math Engine.
 * Pure mathematical functions for HSL color manipulation.
 * Used by: Color Management module, Dark Mode auto-counterpart.
 *
 * All math operates in HSL space for perceptual accuracy.
 * H: 0-360, S: 0-100, L: 0-100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Color_Math {

	// ─────────────────────────────────────────────
	// SECTION 1: Color Space Conversion
	// ─────────────────────────────────────────────

	/**
	 * Parse color string (hex, hex8, or rgba) into HSL array + alpha.
	 *
	 * @param string $color
	 * @return array|null [h(0-360), s(0-100), l(0-100), a(0-1)] or null on invalid input
	 */
	public static function parse_color( $color ) {
		$color = strtolower( trim( $color ) );
		$alpha = 1.0;
		$hex   = '';

		// 1. rgba(r,g,b,a)
		if ( preg_match( '/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?\s*\)$/i', $color, $m ) ) {
			$r = max( 0, min( 255, (int) $m[1] ) );
			$g = max( 0, min( 255, (int) $m[2] ) );
			$b = max( 0, min( 255, (int) $m[3] ) );
			if ( isset( $m[4] ) ) {
				$alpha = max( 0, min( 1, (float) $m[4] ) );
			}
			$hex = sprintf( "%02x%02x%02x", $r, $g, $b );
		}
		// 2. Hex formats (#RGB, #RGBA, #RRGGBB, #RRGGBBAA)
		else {
			$raw = ltrim( $color, '#' );
			if ( strlen( $raw ) === 3 ) {
				$hex = $raw[0].$raw[0].$raw[1].$raw[1].$raw[2].$raw[2];
			} elseif ( strlen( $raw ) === 4 ) {
				$hex = $raw[0].$raw[0].$raw[1].$raw[1].$raw[2].$raw[2];
				$alpha = round( hexdec( $raw[3].$raw[3] ) / 255, 2 );
			} elseif ( strlen( $raw ) === 6 ) {
				$hex = $raw;
			} elseif ( strlen( $raw ) === 8 ) {
				$hex = substr( $raw, 0, 6 );
				$alpha = round( hexdec( substr( $raw, 6, 2 ) ) / 255, 2 );
			} else {
				return null;
			}
		}

		if ( ! ctype_xdigit( $hex ) ) {
			return null;
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$max  = max( $r, $g, $b );
		$min  = min( $r, $g, $b );
		$delta = $max - $min;

		$l = ( $max + $min ) / 2;

		if ( $delta == 0 ) {
			$h = 0;
			$s = 0;
		} else {
			$s = $delta / ( 1 - abs( 2 * $l - 1 ) );
			if ( $max === $r ) {
				$h = 60 * fmod( ( $g - $b ) / $delta, 6 );
			} elseif ( $max === $g ) {
				$h = 60 * ( ( $b - $r ) / $delta + 2 );
			} else {
				$h = 60 * ( ( $r - $g ) / $delta + 4 );
			}
		}

		if ( $h < 0 ) $h += 360;

		return array(
			'h' => round( $h, 2 ),
			's' => round( $s * 100, 2 ),
			'l' => round( $l * 100, 2 ),
			'a' => $alpha,
		);
	}

	/**
	 * Format HSL + Alpha back to CSS string (#RRGGBB or rgba).
	 *
	 * @param float $h 0-360
	 * @param float $s 0-100
	 * @param float $l 0-100
	 * @param float $a 0-1.0
	 * @return string
	 */
	public static function format_color( $h, $s, $l, $a = 1.0 ) {
		$h = fmod( $h, 360 );
		if ( $h < 0 ) $h += 360;

		$s = max( 0, min( 100, $s ) ) / 100;
		$l = max( 0, min( 100, $l ) ) / 100;

		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		if ( $h < 60 )       { $r = $c; $g = $x; $b = 0; }
		elseif ( $h < 120 )  { $r = $x; $g = $c; $b = 0; }
		elseif ( $h < 180 )  { $r = 0;  $g = $c; $b = $x; }
		elseif ( $h < 240 )  { $r = 0;  $g = $x; $b = $c; }
		elseif ( $h < 300 )  { $r = $x; $g = 0;  $b = $c; }
		else                  { $r = $c; $g = 0;  $b = $x; }

		$r = round( ( $r + $m ) * 255 );
		$g = round( ( $g + $m ) * 255 );
		$b = round( ( $b + $m ) * 255 );

		if ( $a < 1.0 ) {
			return "rgba({$r}, {$g}, {$b}, " . round( $a, 3 ) . ")";
		}

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Convert hex to rgba string.
	 *
	 * @param string $hex
	 * @param float  $alpha 0.0-1.0
	 * @return string e.g. 'rgba(35, 139, 101, 0.5)'
	 */
	public static function hex_to_rgba( $hex, $alpha = 1.0 ) {
		$hex   = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		}
		if ( strlen( $hex ) !== 6 ) return $hex;

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$a = round( max( 0, min( 1, $alpha ) ), 2 );

		return "rgba({$r}, {$g}, {$b}, {$a})";
	}

	/**
	 * Calculate perceived brightness (0-255).
	 *
	 * @param string $color
	 * @return float
	 */
	public static function brightness( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return 128;
		
		// Approximate brightness from Lightness for fallback,
		// or ideally convert back to RGB.
		// For simplicity, we just use L (0-100) scaled to (0-255)
		return ( $hsl['l'] / 100 ) * 255;
	}

	/**
	 * Is a color considered "light"?
	 *
	 * @param string $color
	 * @return bool
	 */
	public static function is_light( $color ) {
		return self::brightness( $color ) > 128;
	}

	// ─────────────────────────────────────────────
	// SECTION 2: Lightness Scale
	// ─────────────────────────────────────────────

	/**
	 * Generate the lightness scale for a given color.
	 * Produces steps 10, 20, 30, 40, 50, 60, 70, 80, 90.
	 * Each step uses the SAME hue and saturation as the base,
	 * only lightness is substituted with the step value.
	 *
	 * e.g. '--contrast-2' => '#238b65' (L=34%)
	 * returns: ['10' => '#0b1f14', '20' => '#...',  ... ]
	 *
	 * @param string $hex Base hex color
	 * @return array  [ '10' => '#hex', '20' => '#hex', ... '90' => '#hex' ]
	 */
	public static function lightness_scale( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return array();

		$scale = array();
		for ( $step = 10; $step <= 90; $step += 10 ) {
			$scale[ (string) $step ] = self::format_color( $hsl['h'], $hsl['s'], $step, $hsl['a'] );
		}

		return $scale;
	}

	// ─────────────────────────────────────────────
	// SECTION 3: Color Theory Variants
	// ─────────────────────────────────────────────

	public static function complementary( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return $color;
		return self::format_color( $hsl['h'] + 180, $hsl['s'], $hsl['l'], $hsl['a'] );
	}

	public static function triadic( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return array( 'a' => $color, 'b' => $color );

		return array(
			'a' => self::format_color( $hsl['h'] + 120, $hsl['s'], $hsl['l'], $hsl['a'] ),
			'b' => self::format_color( $hsl['h'] + 240, $hsl['s'], $hsl['l'], $hsl['a'] ),
		);
	}

	public static function analogous( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return array( 'a' => $color, 'b' => $color );

		return array(
			'a' => self::format_color( $hsl['h'] + 30, $hsl['s'], $hsl['l'], $hsl['a'] ),
			'b' => self::format_color( $hsl['h'] - 30, $hsl['s'], $hsl['l'], $hsl['a'] ),
		);
	}

	public static function split_complementary( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return array( 'a' => $color, 'b' => $color );

		return array(
			'a' => self::format_color( $hsl['h'] + 150, $hsl['s'], $hsl['l'], $hsl['a'] ),
			'b' => self::format_color( $hsl['h'] + 210, $hsl['s'], $hsl['l'], $hsl['a'] ),
		);
	}

	// ─────────────────────────────────────────────
	// SECTION 4: Dark Counterpart
	// ─────────────────────────────────────────────

	/**
	 * Compute the dark mode counterpart of a light color.
	 *
	 * Algorithm:
	 *   1. Invert lightness (100 - L)
	 *   2. Contrast Boosting: Shift colors +/- 15% away from 50%
	 *   3. S_dark = max(0, S - 10) ← desaturate for comfort
	 *
	 * This ensures:
	 *   - High contrast in dark mode (black -> white-ish, white -> black-ish)
	 *   - Mid-range colors are pushed away from the "muddy" 50% range
	 *   - Perceptual comfort via desaturation
	 *
	 * @param string $hex Light mode hex color
	 * @return string Dark mode hex color
	 */
	public static function dark_counterpart( $color ) {
		$hsl = self::parse_color( $color );
		if ( ! $hsl ) return $color;

		// 1. Invert lightness
		$l_inv = 100 - $hsl['l'];

		// 2. Push for higher contrast (avoid the 'muddy middle')
		if ( $l_inv < 50 ) {
			// If it would be dark-ish, make it darker
			$l_dark = max( 12, $l_inv - 15 );
		} else {
			// If it would be light-ish, make it lighter
			$l_dark = min( 92, $l_inv + 15 );
		}

		// 3. Slightly desaturate to avoid 'neon' look in dark mode
		$s_dark = max( 0, $hsl['s'] - 10 );

		return self::format_color( $hsl['h'], $s_dark, $l_dark, $hsl['a'] );
	}

	// ─────────────────────────────────────────────
	// SECTION 5: Master Expansion Function
	// ─────────────────────────────────────────────

	/**
	 * Expand a single color into its full derivative palette.
	 *
	 * @param string $slug     CSS variable slug, e.g. 'contrast-2'
	 * @param string $hex      Base hex color
	 * @param array  $options  Which derivatives to generate:
	 *   [
	 *     'scale'             => true,  // 10-90 lightness steps
	 *     'complementary'     => false,
	 *     'triadic'           => false,
	 *     'analogous'         => false,
	 *     'split_comp'        => false,
	 *     'dark_counterpart'  => true,
	 *   ]
	 * @return array  CSS variable map: [ '--slug-10' => '#hex', '--slug-comp' => '#hex', ... ]
	 */
	public static function expand_color( $slug, $hex, $options = array() ) {
		$defaults = array(
			'scale'            => true,
			'complementary'    => false,
			'triadic'          => false,
			'analogous'        => false,
			'split_comp'       => false,
			'dark_counterpart' => true,
		);
		$options = array_merge( $defaults, $options );

		$result = array(
			"--{$slug}" => $hex, // Base color always included
		);

		// Lightness scale
		if ( $options['scale'] ) {
			$scale = self::lightness_scale( $hex );
			foreach ( $scale as $step => $step_hex ) {
				$result[ "--{$slug}-{$step}" ] = $step_hex;
			}
		}

		// Color theory variants
		if ( $options['complementary'] ) {
			$result[ "--{$slug}-comp" ] = self::complementary( $hex );
		}

		if ( $options['triadic'] ) {
			$tri = self::triadic( $hex );
			$result[ "--{$slug}-tri-a" ] = $tri['a'];
			$result[ "--{$slug}-tri-b" ] = $tri['b'];
		}

		if ( $options['analogous'] ) {
			$ana = self::analogous( $hex );
			$result[ "--{$slug}-ana-a" ] = $ana['a'];
			$result[ "--{$slug}-ana-b" ] = $ana['b'];
		}

		if ( $options['split_comp'] ) {
			$sc = self::split_complementary( $hex );
			$result[ "--{$slug}-sc-a" ] = $sc['a'];
			$result[ "--{$slug}-sc-b" ] = $sc['b'];
		}

		// Dark counterpart stored separately (for Dark Mode integration)
		if ( $options['dark_counterpart'] ) {
			$result[ "__dark__{$slug}" ] = self::dark_counterpart( $hex );
		}

		return $result;
	}

	// ─────────────────────────────────────────────
	// SECTION 6: Import Format Parser
	// ─────────────────────────────────────────────

	/**
	 * Auto-detect and parse various color import formats.
	 * Returns a flat array of [ 'name_or_slug' => '#hex' ].
	 *
	 * Supported formats:
	 *   - Hex list:       #ff0000, #00ff00
	 *   - CSS vars:       --red: #ff0000; --green: #00ff00;
	 *   - JSON object:    {"red": "#ff0000", "green": "#00ff00"}
	 *   - Coolors URL export (json)
	 *   - Simple hex list (no names)
	 *
	 * @param string $input Raw paste from user
	 * @return array [ 'slug' => '#hex', ... ] (slugs auto-generated if no names)
	 */
	public static function parse_import( $input ) {
		$input   = trim( $input );
		$colors  = array();

		// 1. Try CSS custom properties: --slug: #hex or rgba(...)
		// Match `#...` or `rgba(...)`
		if ( preg_match_all( '/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/i', $input, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$colors[ sanitize_key( $m[1] ) ] = strtolower( $m[2] );
			}
			return $colors;
		}

		// 2. Try JSON { "name": "#hex" }
		$decoded = json_decode( $input, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $key => $value ) {
				if ( is_string( $value ) && preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', trim( $value ) ) ) {
					$colors[ sanitize_key( $key ) ] = strtolower( trim( $value ) );
				}
			}
			if ( ! empty( $colors ) ) return $colors;
		}

		// 3. Try plain hex list (comma or newline separated)
		preg_match_all( '/#([0-9a-fA-F]{3,8})\b/', $input, $hex_matches );
		if ( ! empty( $hex_matches[0] ) ) {
			$i = 1;
			foreach ( $hex_matches[0] as $hex ) {
				$colors[ 'color-' . $i ] = strtolower( $hex );
				$i++;
			}
		}

		return $colors;
	}

	/**
	 * Build full CSS variable string from an expanded color map.
	 * Dark counterparts (keys starting with '__dark__') are excluded.
	 *
	 * @param array $expanded Result of expand_color()
	 * @return string CSS property declarations
	 */
	public static function to_css_vars( $expanded ) {
		$lines = array();
		foreach ( $expanded as $var => $hex ) {
			if ( strpos( $var, '__dark__' ) === 0 ) continue;
			$lines[] = "\t" . esc_attr( $var ) . ': ' . esc_attr( $hex ) . ';';
		}
		return implode( "\n", $lines );
	}

	/**
	 * Extract dark counterparts from an expanded map.
	 * Returns [ 'slug' => '#hex' ] ready for Dark Mode injection.
	 *
	 * @param array $expanded Result of expand_color()
	 * @return array
	 */
	public static function extract_dark_counterparts( $expanded ) {
		$dark = array();
		foreach ( $expanded as $var => $hex ) {
			if ( strpos( $var, '__dark__' ) === 0 ) {
				$slug         = substr( $var, strlen( '__dark__' ) );
				$dark[ $slug ] = $hex;
			}
		}
		return $dark;
	}
}
