<?php
/**
 * Gradient Module Class.
 *
 * Adds a "Gradient Variables" section in Customizer (Utility → Gradient Variables).
 * Also injects an entry-point button near the GP Global Colors section via JS.
 *
 * Gradients are stored as theme_mod 'wpids_gradient_variables' and output
 * as CSS custom properties to :root at wp_head priority 9998.
 *
 * Supports: linear-gradient, radial-gradient, conic-gradient
 * Dark Mode: Auto-computes dark gradient (per-stop dark_counterpart)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Gradient_Module {

	public function init() {
		// Customizer section
		add_action( 'customize_register', array( $this, 'register_customizer' ), 997 );

		// Customizer JS + CSS
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_assets' ) );

		// AJAX: Save gradient set
		add_action( 'wp_ajax_wpids_save_gradients', array( $this, 'ajax_save_gradients' ) );

		// AJAX: Compute dark gradient stops
		add_action( 'wp_ajax_wpids_dark_gradient', array( $this, 'ajax_dark_gradient' ) );

		// CSS injection — priority 9998
		add_action( 'wp_head', array( $this, 'inject_gradient_css' ), 9998 );
	}

	// ─────────────────────────────────────────
	// CUSTOMIZER
	// ─────────────────────────────────────────

	public function register_customizer( $wp_customize ) {
		// Section: Gradient Variables (below Color Management, priority 30)
		$wp_customize->add_section(
			'wpids_gradient_variables',
			array(
				'title'    => 'Gradient Variables',
				'panel'    => 'wpids_utility_panel',
				'priority' => 30,
			)
		);

		// Setting: stores the gradient array
		$wp_customize->add_setting(
			'wpids_gradient_variables',
			array(
				'default'           => array(),
				'type'              => 'option',
				'sanitize_callback' => array( $this, 'sanitize_gradients' ),
				'transport'         => 'postMessage',
			)
		);

		// Custom control
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-gradient-control.php';
		$wp_customize->add_control(
			new WPIDS_Gradient_Control(
				$wp_customize,
				'wpids_gradient_variables',
				array(
					'label'   => 'Gradient Variables',
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

		$saved = get_option( 'wpids_gradient_variables', array() );

		// Get GP colors for the stop color palette
		$gp_colors = array();
		$gp_settings = get_option( 'generate_settings', array() );
		if ( ! empty( $gp_settings['global_colors'] ) ) {
			foreach ( $gp_settings['global_colors'] as $c ) {
				if ( ! empty( $c['slug'] ) && ! empty( $c['color'] ) ) {
					// Only include solid hex colors (not gradient references)
					if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $c['color'] ) ) {
						$gp_colors[] = array(
							'slug'  => $c['slug'],
							'name'  => $c['name'] ?? $c['slug'],
							'color' => $c['color'],
						);
					}
				}
			}
		}

		// Also include solid expanded colors from color module
		$expanded = get_option( 'wpids_expanded_colors', array() );
		$solid_expanded = array();
		if ( is_array( $expanded ) ) {
			foreach ( $expanded as $set ) {
				if ( ! empty( $set['slug'] ) && ! empty( $set['hex'] ) ) {
					$solid_expanded[] = array(
						'slug'  => $set['slug'],
						'name'  => $set['slug'],
						'color' => $set['hex'],
					);
				}
			}
		}

		wp_localize_script(
			'wpids-gradient-module',
			'wpidsGradientModule',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wpids_gradient_module' ),
				'saved'         => is_array( $saved ) ? $saved : array(),
				'gpColors'      => $gp_colors,        // for stop color palette
				'expandedColors' => $solid_expanded,  // from color import module
			)
		);
	}

	// ─────────────────────────────────────────
	// AJAX
	// ─────────────────────────────────────────

	/**
	 * AJAX: Save the full gradient array to DB.
	 * Also registers each gradient in generate_settings[global_colors]
	 * using the first stop color as a visual representative.
	 * This makes gradient variables appear in GP's React Color picker.
	 */
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

		// Sync gradient entries to GP Global Colors palette
		$gp_added = $this->sync_gradients_to_gp_colors( $sanitized );

		if ( ! empty( $_POST['sync_dark'] ) ) {
			$this->sync_dark_gradients( $sanitized );
		}

		// Return updated GP colors for JS to confirm
		$gp_settings = get_option( 'generate_settings', array() );
		$updated_gp_colors = $gp_settings['global_colors'] ?? array();

		$msg = count( $sanitized ) . ' gradient(s) saved.';
		if ( $gp_added > 0 ) {
			$msg .= ' ' . $gp_added . ' added to GP Color palette.';
		}

		wp_send_json_success( array(
			'saved'            => count( $sanitized ),
			'gp_added'         => $gp_added,
			'message'          => $msg,
			'updated_gp_colors' => $updated_gp_colors,
		) );
	}

	/**
	 * AJAX: Compute dark counterparts for each color stop.
	 * Input: array of hex colors (stops)
	 * Output: array of darkened hex colors
	 */
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

	// ─────────────────────────────────────────
	// CSS INJECTION
	// ─────────────────────────────────────────

	/**
	 * Build the CSS value string for a gradient.
	 *
	 * @param array $gradient
	 * @return string e.g. 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
	 */
	public static function build_gradient_css( $gradient ) {
		if ( empty( $gradient['stops'] ) ) return '';

		$stops_parts = array();
		foreach ( $gradient['stops'] as $stop ) {
			$hex = isset( $stop['color'] ) ? $stop['color'] : '#000000';
			$pos = isset( $stop['position'] ) ? intval( $stop['position'] ) : 0;
			$stops_parts[] = esc_attr( $hex ) . ' ' . $pos . '%';
		}
		$stops_str = implode( ', ', $stops_parts );

		switch ( $gradient['type'] ?? 'linear' ) {
			case 'radial':
				$shape = $gradient['shape'] ?? 'ellipse';
				$at    = $gradient['at'] ?? 'center';
				return "radial-gradient({$shape} at {$at}, {$stops_str})";

			case 'conic':
				$angle = intval( $gradient['angle'] ?? 0 );
				return "conic-gradient(from {$angle}deg, {$stops_str})";

			default: // linear
				$angle = intval( $gradient['angle'] ?? 135 );
				return "linear-gradient({$angle}deg, {$stops_str})";
		}
	}

	/**
	 * Inject gradient CSS variables to :root and body.dark.
	 * Priority 9998 — after expanded colors (9997), before Dark Mode (9999).
	 */
	public function inject_gradient_css() {
		$gradients = get_option( 'wpids_gradient_variables', array() );

		if ( empty( $gradients ) || ! is_array( $gradients ) ) {
			return;
		}

		$light_lines = array();
		$dark_lines  = array();

		foreach ( $gradients as $g ) {
			$slug = $g['slug'] ?? '';
			if ( empty( $slug ) ) continue;

			$css_val = self::build_gradient_css( $g );
			if ( $css_val ) {
				$light_lines[] = "\t--{$slug}: {$css_val};";
			}

			// Dark gradient (if stored)
			if ( ! empty( $g['dark_stops'] ) ) {
				$dark_g = array_merge( $g, array( 'stops' => $g['dark_stops'] ) );
				$dark_val = self::build_gradient_css( $dark_g );
				if ( $dark_val ) {
					$dark_lines[] = "\t--{$slug}: {$dark_val};";
				}
			}
		}

		if ( empty( $light_lines ) ) return;

		echo "<style id=\"wpids-gradient-vars\">\n";
		echo ":root {\n" . implode( "\n", $light_lines ) . "\n}\n";

		if ( ! empty( $dark_lines ) ) {
			echo "body.dark {\n" . implode( "\n", $dark_lines ) . "\n}\n";
		}

		echo "</style>\n";
	}

	// ─────────────────────────────────────────
	// HELPERS
	// ─────────────────────────────────────────

	/**
	 * Sync gradient variables to generate_settings[global_colors].
	 *
	 * Each gradient is registered with:
	 *   - slug: the gradient slug (same as CSS variable name)
	 *   - name: human-readable name with a gradient suffix
	 *   - color: the FIRST stop hex color (visual representative only)
	 *
	 * The actual CSS variable is overridden at priority 9998 with the real gradient.
	 * GP's own CSS will inject --slug: #firstStopColor, but our injection wins.
	 *
	 * This allows users to select gradient variables from GP's color picker
	 * and reference them via var(--slug) in GenerateBlocks and GP fields.
	 *
	 * @param  array $gradients  Sanitized gradient array
	 * @return int               Number of entries added/updated in GP colors
	 */
	private function sync_gradients_to_gp_colors( $gradients ) {
		$gp_settings = get_option( 'generate_settings', array() );
		$gp_colors   = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();
		$synced      = 0;

		foreach ( $gradients as $g ) {
			$slug  = $g['slug'] ?? '';
			$name  = $g['name'] ?? $slug;
			$type  = ucfirst( $g['type'] ?? 'linear' );
			if ( empty( $slug ) || empty( $g['stops'] ) ) continue;

			// Use first stop hex as visual representative color
			$first_stop_hex = $g['stops'][0]['color'] ?? '#000000';

			// Update if exists, insert if not
			$found = false;
			foreach ( $gp_colors as &$gpc ) {
				if ( isset( $gpc['slug'] ) && $gpc['slug'] === $slug ) {
					$gpc['name']  = $name . ' (' . $type . ' Gradient)';
					$gpc['color'] = $first_stop_hex;
					$found = true;
					$synced++;
					break;
				}
			}
			unset( $gpc );

			if ( ! $found ) {
				$gp_colors[] = array(
					'name'  => $name . ' (' . $type . ' Gradient)',
					'slug'  => $slug,
					'color' => $first_stop_hex,
				);
				$synced++;
			}
		}

		if ( $synced > 0 ) {
			$gp_settings['global_colors'] = $gp_colors;
			update_option( 'generate_settings', $gp_settings );
		}

		return $synced;
	}

	/**
	 * Sync dark gradient stops to wpids_dark_global_colors.
	 * Currently a no-op — dark gradients are handled via body.dark CSS injection.
	 */
	private function sync_dark_gradients( $gradients ) {
		// Handled via inject_gradient_css() body.dark block
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
			$type = in_array( $g['type'] ?? 'linear', array( 'linear', 'radial', 'conic' ) )
				? $g['type']
				: 'linear';

			if ( empty( $slug ) ) continue;

			// Sanitize stops
			$stops = array();
			foreach ( (array) ( $g['stops'] ?? array() ) as $stop ) {
				$hex = sanitize_text_field( $stop['color'] ?? '' );
				$pos = max( 0, min( 100, intval( $stop['position'] ?? 0 ) ) );
				if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$stops[] = array( 'color' => $hex, 'position' => $pos );
				}
			}

			if ( count( $stops ) < 2 ) continue; // need at least 2 stops

			// Sanitize dark stops
			$dark_stops = array();
			foreach ( (array) ( $g['dark_stops'] ?? array() ) as $stop ) {
				$hex = sanitize_text_field( $stop['color'] ?? '' );
				$pos = max( 0, min( 100, intval( $stop['position'] ?? 0 ) ) );
				if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $hex ) ) {
					$dark_stops[] = array( 'color' => $hex, 'position' => $pos );
				}
			}

			$entry = array(
				'slug'      => $slug,
				'name'      => $name,
				'type'      => $type,
				'angle'     => intval( $g['angle'] ?? 135 ),
				'shape'     => sanitize_text_field( $g['shape'] ?? 'ellipse' ),
				'at'        => sanitize_text_field( $g['at'] ?? 'center' ),
				'stops'     => $stops,
				'dark_stops' => $dark_stops,
			);

			$clean[] = $entry;
		}

		return $clean;
	}
}
