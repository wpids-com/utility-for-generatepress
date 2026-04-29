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
	 * Convert hex color to HSL array.
	 *
	 * @param string $hex e.g. '#238b65' or '238b65'
	 * @return array|null [h(0-360), s(0-100), l(0-100)] or null on invalid input
	 */
	public static function hex_to_hsl( $hex ) {
		$hex = ltrim( $hex, '#' );

		// Support 3-char shorthand
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
			return null;
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$max  = max( $r, $g, $b );
		$min  = min( $r, $g, $b );
		$delta = $max - $min;

		// Lightness
		$l = ( $max + $min ) / 2;

		// Saturation
		if ( $delta == 0 ) {
			$h = 0;
			$s = 0;
		} else {
			$s = $delta / ( 1 - abs( 2 * $l - 1 ) );

			// Hue
			if ( $max === $r ) {
				$h = 60 * fmod( ( $g - $b ) / $delta, 6 );
			} elseif ( $max === $g ) {
				$h = 60 * ( ( $b - $r ) / $delta + 2 );
			} else {
				$h = 60 * ( ( $r - $g ) / $delta + 4 );
			}
		}

		if ( $h < 0 ) {
			$h += 360;
		}

		return array(
			'h' => round( $h, 2 ),
			's' => round( $s * 100, 2 ),
			'l' => round( $l * 100, 2 ),
		);
	}

	/**
	 * Convert HSL values to hex string.
	 *
	 * @param float $h 0-360
	 * @param float $s 0-100
	 * @param float $l 0-100
	 * @return string e.g. '#238b65'
	 */
	public static function hsl_to_hex( $h, $s, $l ) {
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
	 * Uses ITU-R BT.601 formula.
	 *
	 * @param string $hex
	 * @return float
	 */
	public static function brightness( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		}
		if ( strlen( $hex ) !== 6 ) return 128;

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return ( $r * 299 + $g * 587 + $b * 114 ) / 1000;
	}

	/**
	 * Is a color considered "light"?
	 *
	 * @param string $hex
	 * @return bool
	 */
	public static function is_light( $hex ) {
		return self::brightness( $hex ) > 128;
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
	public static function lightness_scale( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return array();

		$scale = array();
		for ( $step = 10; $step <= 90; $step += 10 ) {
			$scale[ (string) $step ] = self::hsl_to_hex( $hsl['h'], $hsl['s'], $step );
		}

		return $scale;
	}

	// ─────────────────────────────────────────────
	// SECTION 3: Color Theory Variants
	// ─────────────────────────────────────────────

	/**
	 * Complementary color (hue + 180°).
	 *
	 * @param string $hex
	 * @return string hex
	 */
	public static function complementary( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return $hex;

		return self::hsl_to_hex( $hsl['h'] + 180, $hsl['s'], $hsl['l'] );
	}

	/**
	 * Triadic colors (hue ±120°).
	 *
	 * @param string $hex
	 * @return array ['a' => '#hex', 'b' => '#hex']
	 */
	public static function triadic( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return array( 'a' => $hex, 'b' => $hex );

		return array(
			'a' => self::hsl_to_hex( $hsl['h'] + 120, $hsl['s'], $hsl['l'] ),
			'b' => self::hsl_to_hex( $hsl['h'] + 240, $hsl['s'], $hsl['l'] ),
		);
	}

	/**
	 * Analogous colors (hue ±30°).
	 *
	 * @param string $hex
	 * @return array ['a' => '#hex', 'b' => '#hex']
	 */
	public static function analogous( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return array( 'a' => $hex, 'b' => $hex );

		return array(
			'a' => self::hsl_to_hex( $hsl['h'] + 30, $hsl['s'], $hsl['l'] ),
			'b' => self::hsl_to_hex( $hsl['h'] - 30, $hsl['s'], $hsl['l'] ),
		);
	}

	/**
	 * Split-complementary colors (hue +150° and hue +210°).
	 *
	 * @param string $hex
	 * @return array ['a' => '#hex', 'b' => '#hex']
	 */
	public static function split_complementary( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return array( 'a' => $hex, 'b' => $hex );

		return array(
			'a' => self::hsl_to_hex( $hsl['h'] + 150, $hsl['s'], $hsl['l'] ),
			'b' => self::hsl_to_hex( $hsl['h'] + 210, $hsl['s'], $hsl['l'] ),
		);
	}

	// ─────────────────────────────────────────────
	// SECTION 4: Dark Counterpart
	// ─────────────────────────────────────────────

	/**
	 * Compute the dark mode counterpart of a light color.
	 *
	 * Algorithm:
	 *   L_dark = clamp(15, 95 - L_light, 85)
	 *   S_dark = max(0, S - 5)   ← slightly desaturate to reduce harshness
	 *
	 * This ensures:
	 *   - Very dark colors (L<15) stay at min 15% lightness
	 *   - Very light colors (L>80) don't go above 85%
	 *   - Mid-range colors are naturally inverted
	 *
	 * @param string $hex Light mode hex color
	 * @return string Dark mode hex color
	 */
	public static function dark_counterpart( $hex ) {
		$hsl = self::hex_to_hsl( $hex );
		if ( ! $hsl ) return $hex;

		$l_dark = max( 15, min( 85, 95 - $hsl['l'] ) );
		$s_dark = max( 0, $hsl['s'] - 5 );

		return self::hsl_to_hex( $hsl['h'], $s_dark, $l_dark );
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

		// 1. Try CSS custom properties: --slug: #hex;
		if ( preg_match_all( '/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,6})/i', $input, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$colors[ sanitize_key( $m[1] ) ] = strtolower( $m[2] );
			}
			return $colors;
		}

		// 2. Try JSON { "name": "#hex" }
		$decoded = json_decode( $input, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $key => $value ) {
				if ( is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', trim( $value ) ) ) {
					$colors[ sanitize_key( $key ) ] = strtolower( trim( $value ) );
				}
			}
			if ( ! empty( $colors ) ) return $colors;
		}

		// 3. Try plain hex list (comma or newline separated)
		preg_match_all( '/#([0-9a-fA-F]{3,6})\b/', $input, $hex_matches );
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
