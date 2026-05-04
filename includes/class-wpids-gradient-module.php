<?php
/**
 * Gradient Module Class.
 *
 * Registers gradient presets as WordPress-native theme.json gradients.
 * This makes var(--wp--preset--gradient--[slug]) available everywhere:
 *   - Block Editor gradient palette (second tab in color picker)
 *   - Frontend via theme.json auto-generated CSS variables
 *   - Customizer preview (via postMessage + preview JS)
 *
 * Also auto-generates utility CSS classes:
 *   .has-[slug]-gradient-text   → gradient text effect
 *   .has-[slug]-gradient-border → gradient border effect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Gradient_Module {

	public function init() {
		// Customizer panel/control
		add_action( 'customize_register', array( $this, 'register_customizer' ), 997 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_assets' ) );

		// Customizer preview iframe — live CSS updates
		add_action( 'customize_preview_init', array( $this, 'enqueue_preview_assets' ) );

		// WordPress native: inject into theme.json
		add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_theme_json_gradients' ) );

		// Frontend utility classes
		add_action( 'wp_head', array( $this, 'inject_gradient_css' ), 9998 );

		// AJAX
		add_action( 'wp_ajax_wpids_save_gradients', array( $this, 'ajax_save_gradients' ) );
		add_action( 'wp_ajax_wpids_dark_gradient', array( $this, 'ajax_dark_gradient' ) );
		add_action( 'wp_ajax_wpids_save_border_settings', array( $this, 'ajax_save_border_settings' ) );
	}

	// ─────────────────────────────────────────
	// WORDPRESS NATIVE INTEGRATION
	// ─────────────────────────────────────────

	/**
	 * Inject saved gradients into WordPress theme.json.
	 *
	 * WordPress auto-generates --wp--preset--gradient--[slug] CSS variables
	 * from this, and shows them natively in the block editor gradient palette.
	 */
	public function inject_theme_json_gradients( $theme_json ) {
		$gradients = get_option( 'wpids_gradient_variables', array() );
		if ( empty( $gradients ) || ! is_array( $gradients ) ) {
			return $theme_json;
		}

		$wp_gradients = array();
		foreach ( $gradients as $g ) {
			$slug = $g['slug'] ?? '';
			$name = $g['name'] ?? $slug;
			if ( empty( $slug ) || empty( $g['stops'] ) ) continue;
			$css_val = self::build_gradient_css( $g );
			if ( ! $css_val ) continue;

			$wp_gradients[] = array(
				'slug'     => $slug,
				'name'     => $name,
				'gradient' => $css_val,
			);
		}

		if ( empty( $wp_gradients ) ) return $theme_json;

		return $theme_json->update_with( array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'gradients' => $wp_gradients,
				),
			),
		) );
	}

	// ─────────────────────────────────────────
	// CSS INJECTION
	// ─────────────────────────────────────────

	/**
	 * Build the full utility CSS string for all saved gradients.
	 * Called for both frontend and block editor injection.
	 */
	public static function build_utility_css( $context = 'frontend' ) {
		$gradients = get_option( 'wpids_gradient_variables', array() );
		if ( empty( $gradients ) || ! is_array( $gradients ) ) return '';

		$css = '';
		foreach ( $gradients as $g ) {
			$slug = sanitize_key( $g['slug'] ?? '' );
			if ( empty( $slug ) || empty( $g['stops'] ) ) continue;

			$grad = self::build_gradient_css( $g );
			if ( ! $grad ) continue;

			// Text gradient (background-clip technique)
			$css .= ".has-{$slug}-gradient-text{background:{$grad};-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:inline;}\n";

			// Border gradient — technique depends on radius setting
			$radius = self::get_border_radius_css();
			if ( '0' === $radius ) {
				// Sharp: border-image (simpler, no border-radius needed)
				$css .= ".has-{$slug}-gradient-border{border-style:solid!important;border-image:{$grad} 1;}\n";
			} else {
				// Rounded/Pill/Custom: ::before pseudo-element (supports border-radius)
				$css .= ".has-{$slug}-gradient-border{position:relative;border:var(--wpids-gb-width,2px) solid transparent!important;}\n";
				$css .= ".has-{$slug}-gradient-border::before{content:'';position:absolute;inset:0;border-radius:{$radius};padding:var(--wpids-gb-width,2px);background:{$grad};-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none;}\n";
			}

			// Dark mode variants
			if ( ! empty( $g['dark_stops'] ) ) {
				$dark_grad = self::build_gradient_css( array_merge( $g, array( 'stops' => $g['dark_stops'] ) ) );
				if ( $dark_grad ) {
					$css .= "body.dark .has-{$slug}-gradient-text{background:{$dark_grad};-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}\n";
					if ( '0' === $radius ) {
						$css .= "body.dark .has-{$slug}-gradient-border{border-image:{$dark_grad} 1;}\n";
					} else {
						$css .= "body.dark .has-{$slug}-gradient-border::before{background:{$dark_grad};}\n";
					}
				}
			}
		}

		return $css;
	}

	/** Output utility CSS to wp_head (frontend). */
	public function inject_gradient_css() {
		$css = self::build_utility_css( 'frontend' );
		if ( empty( $css ) ) return;
		echo "<style id=\"wpids-gradient-utilities\">\n";
		echo wp_kses_post( wp_strip_all_tags( $css ) );
		echo "</style>\n";
	}

	// ─────────────────────────────────────────
	// CUSTOMIZER
	// ─────────────────────────────────────────

	public function register_customizer( $wp_customize ) {
		$wp_customize->add_section(
			'wpids_gradient_variables',
			array(
				'title'    => __( 'Gradient Variables', 'generatepress-utility' ),
				'panel'    => 'wpids_utility_panel',
				'priority' => 20,
			)
		);

		$wp_customize->add_setting(
			'wpids_gradient_variables',
			array(
				'default'           => array(),
				'type'              => 'option',
				'sanitize_callback' => array( $this, 'sanitize_gradients' ),
				'transport'         => 'postMessage',
			)
		);

		// Border settings — saves via Publish button (no separate AJAX needed)
		$wp_customize->add_setting(
			'wpids_gradient_border_settings',
			array(
				'default'           => wp_json_encode( array(
					'radius_preset' => 'sharp',
					'radius'        => array( 'tl' => 0, 'tr' => 0, 'bl' => 0, 'br' => 0 ),
					'radius_unit'   => 'px',
					'linked'        => true,
				) ),
				'type'              => 'option',
				'sanitize_callback' => array( $this, 'sanitize_border_settings' ),
				'transport'         => 'postMessage',
			)
		);

		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-gradient-control.php';
		$wp_customize->add_control(
			new WPIDS_Gradient_Control(
				$wp_customize,
				'wpids_gradient_variables',
				array(
					'label'   => __( 'Gradient Palette', 'generatepress-utility' ),
					'section' => 'wpids_gradient_variables',
				)
			)
		);
	}

	public function enqueue_customizer_assets() {
		wp_enqueue_style(
			'wpids-gradient-module',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-gradient-module.css',
			array(),
			WPIDS_UTILITY_VERSION
		);

		wp_enqueue_script(
			'wpids-gradient-module',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/js/wpids-gradient-module.js',
			array( 'jquery', 'customize-controls', 'wp-color-picker' ),
			WPIDS_UTILITY_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );

		$saved    = get_option( 'wpids_gradient_variables', array() );
		$gp_colors = array();
		$gp_settings = get_option( 'generate_settings', array() );
		if ( ! empty( $gp_settings['global_colors'] ) ) {
			foreach ( $gp_settings['global_colors'] as $c ) {
				if ( ! empty( $c['slug'] ) && ! empty( $c['color'] ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $c['color'] ) ) {
					$gp_colors[] = array( 'slug' => $c['slug'], 'name' => $c['name'] ?? $c['slug'], 'color' => $c['color'] );
				}
			}
		}

		$border_raw = get_option( 'wpids_gradient_border_settings', '' );
		$border     = is_string( $border_raw ) && ! empty( $border_raw ) ? json_decode( $border_raw, true ) : $border_raw;
		if ( ! is_array( $border ) ) {
			$border = array(
				'radius_preset' => 'sharp',
				'radius'        => array( 'tl' => '0', 'tr' => '0', 'bl' => '0', 'br' => '0' ),
				'radius_unit'   => 'px',
				'linked'        => true,
			);
		}

		wp_localize_script(
			'wpids-gradient-module',
			'wpidsGradientModule',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wpids_gradient_module' ),
				'saved'         => is_array( $saved ) ? $saved : array(),
				'borderSettings' => $border,
				'gpColors'      => $gp_colors,
				'i18n'     => array(
					'addGradient'  => __( 'Add Gradient', 'generatepress-utility' ),
					'editGradient' => __( 'Edit Gradient', 'generatepress-utility' ),
					'name'         => __( 'Name', 'generatepress-utility' ),
					'type'         => __( 'Type', 'generatepress-utility' ),
					'angle'        => __( 'Angle', 'generatepress-utility' ),
					'addColor'     => __( '+ Add Color Stop', 'generatepress-utility' ),
					'save'         => __( 'Save', 'generatepress-utility' ),
					'saved'        => __( 'Saved!', 'generatepress-utility' ),
					'delete'       => __( 'Delete Gradient', 'generatepress-utility' ),
					'linear'       => __( 'Linear', 'generatepress-utility' ),
					'radial'       => __( 'Radial', 'generatepress-utility' ),
					'conic'        => __( 'Conic', 'generatepress-utility' ),
					'noGradients'  => __( 'No gradients yet. Click + to add.', 'generatepress-utility' ),
					'utilityInfo'  => __( 'Use class: has-[name]-gradient-text or has-[name]-gradient-border', 'generatepress-utility' ),
				),
			)
		);
	}

	/** Enqueue preview JS for live Customizer preview. */
	public function enqueue_preview_assets() {
		wp_enqueue_script(
			'wpids-gradient-preview',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/js/wpids-gradient-preview.js',
			array( 'customize-preview' ),
			WPIDS_UTILITY_VERSION,
			true
		);
	}

	// ─────────────────────────────────────────
	// AJAX
	// ─────────────────────────────────────────

	public function ajax_save_gradients() {
		check_ajax_referer( 'wpids_gradient_module', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$raw = isset( $_POST['gradients'] ) ? wp_unslash( $_POST['gradients'] ) : array();
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$sanitized = $this->sanitize_gradients( $raw );
		update_option( 'wpids_gradient_variables', $sanitized );

		wp_send_json_success( array(
			'saved'   => count( $sanitized ),
			'message' => count( $sanitized ) . ' gradient(s) saved.',
		) );
	}

	public function ajax_dark_gradient() {
		check_ajax_referer( 'wpids_gradient_module', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$stops = isset( $_POST['stops'] ) ? wp_unslash( $_POST['stops'] ) : array();
		if ( ! is_array( $stops ) ) {
			wp_send_json_error( 'Invalid stops' );
		}

		$result = array();
		foreach ( $stops as $stop ) {
			$hex = sanitize_text_field( $stop['color'] ?? '' );
			$pos = intval( $stop['position'] ?? 0 );
			if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
				$result[] = array(
					'color'    => WPIDS_Color_Math::dark_counterpart( $hex ),
					'position' => $pos,
				);
			}
		}

		wp_send_json_success( array( 'dark_stops' => $result ) );
	}

	/** Sanitize border settings (stored as JSON string via Customizer). */
	public function sanitize_border_settings( $input ) {
		$data = is_string( $input ) ? json_decode( $input, true ) : $input;
		if ( ! is_array( $data ) ) return $input;

		$preset = sanitize_key( $data['radius_preset'] ?? 'sharp' );
		$unit   = in_array( $data['radius_unit'] ?? 'px', array( 'px', 'rem' ) ) ? $data['radius_unit'] : 'px';
		$r      = $data['radius'] ?? array();

		return wp_json_encode( array(
			'radius_preset' => $preset,
			'radius_unit'   => $unit,
			'linked'        => (bool) ( $data['linked'] ?? true ),
			'radius'        => array(
				'tl' => max( 0, intval( $r['tl'] ?? 0 ) ),
				'tr' => max( 0, intval( $r['tr'] ?? 0 ) ),
				'bl' => max( 0, intval( $r['bl'] ?? 0 ) ),
				'br' => max( 0, intval( $r['br'] ?? 0 ) ),
			),
		) );
	}

	/** Return the border-radius CSS value based on saved border settings. */
	public static function get_border_radius_css() {
		$raw  = get_option( 'wpids_gradient_border_settings', '' );
		$s    = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $s ) ) return '0';
		$preset = $s['radius_preset'] ?? 'sharp';

		switch ( $preset ) {
			case 'rounded': return '8px';
			case 'pill':    return '9999px';
			case 'custom':
				$u  = $s['radius_unit'] ?? 'px';
				$r  = $s['radius'] ?? array();
				$tl = intval( $r['tl'] ?? 0 );
				$tr = intval( $r['tr'] ?? 0 );
				$bl = intval( $r['bl'] ?? 0 );
				$br = intval( $r['br'] ?? 0 );
				return "{$tl}{$u} {$tr}{$u} {$br}{$u} {$bl}{$u}";
			default: return '0';
		}
	}

	// ─────────────────────────────────────────
	// BUILD GRADIENT CSS VALUE
	// ─────────────────────────────────────────

	public static function build_gradient_css( $gradient ) {
		if ( empty( $gradient['stops'] ) ) return '';

		$parts = array();
		foreach ( $gradient['stops'] as $stop ) {
			$hex = $stop['color'] ?? '#000000';
			$pos = intval( $stop['position'] ?? 0 );
			$parts[] = esc_attr( $hex ) . ' ' . $pos . '%';
		}
		$stops_str = implode( ', ', $parts );

		switch ( $gradient['type'] ?? 'linear' ) {
			case 'radial':
				$shape = $gradient['shape'] ?? 'ellipse';
				$at    = $gradient['at'] ?? 'center';
				return "radial-gradient({$shape} at {$at}, {$stops_str})";
			case 'conic':
				$angle = intval( $gradient['angle'] ?? 0 );
				return "conic-gradient(from {$angle}deg, {$stops_str})";
			default:
				$angle = intval( $gradient['angle'] ?? 135 );
				return "linear-gradient({$angle}deg, {$stops_str})";
		}
	}

	// ─────────────────────────────────────────
	// SANITIZATION
	// ─────────────────────────────────────────

	public function sanitize_gradients( $input ) {
		if ( ! is_array( $input ) ) return array();

		$clean = array();
		foreach ( $input as $g ) {
			$slug = sanitize_key( $g['slug'] ?? '' );
			$name = sanitize_text_field( $g['name'] ?? $slug );
			$type = in_array( $g['type'] ?? 'linear', array( 'linear', 'radial', 'conic' ) ) ? $g['type'] : 'linear';
			if ( empty( $slug ) ) continue;

			$stops = array();
			foreach ( (array) ( $g['stops'] ?? array() ) as $stop ) {
				$hex = sanitize_text_field( $stop['color'] ?? '' );
				$pos = max( 0, min( 100, intval( $stop['position'] ?? 0 ) ) );
				if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$stops[] = array( 'color' => $hex, 'position' => $pos );
				}
			}
			if ( count( $stops ) < 2 ) continue;

			$dark_stops = array();
			foreach ( (array) ( $g['dark_stops'] ?? array() ) as $stop ) {
				$hex = sanitize_text_field( $stop['color'] ?? '' );
				$pos = max( 0, min( 100, intval( $stop['position'] ?? 0 ) ) );
				if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$dark_stops[] = array( 'color' => $hex, 'position' => $pos );
				}
			}

			$clean[] = array(
				'slug'       => $slug,
				'name'       => $name,
				'type'       => $type,
				'angle'      => intval( $g['angle'] ?? 135 ),
				'shape'      => sanitize_text_field( $g['shape'] ?? 'ellipse' ),
				'at'         => sanitize_text_field( $g['at'] ?? 'center' ),
				'stops'      => $stops,
				'dark_stops' => $dark_stops,
			);
		}

		return $clean;
	}
}
