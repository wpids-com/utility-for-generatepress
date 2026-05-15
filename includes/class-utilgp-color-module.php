<?php
/**
 * Color Module Class.
 *
 * Registers the "Color Management" Customizer section.
 * Features:
 *   - Color import with mapping wizard modal
 *   - Lightness scale generation (--slug-10 to --slug-90)
 *   - Color theory variants (complementary, triadic, analogous)
 *   - Auto dark counterpart sync to Dark Mode panel
 *   - CSS variable injection to :root (priority 9997)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILGP_Color_Module {

	public function init() {
		// Customizer section
		add_action( 'customize_register', array( $this, 'register_customizer' ), 998 );

		// Customizer assets (JS + CSS for the custom control)
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_assets' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_utilgp_parse_colors', array( $this, 'ajax_parse_colors' ) );
		add_action( 'wp_ajax_utilgp_expand_colors', array( $this, 'ajax_expand_colors' ) );
		add_action( 'wp_ajax_utilgp_save_expanded', array( $this, 'ajax_save_expanded' ) );
		add_action( 'wp_ajax_utilgp_sync_dark_auto', array( $this, 'ajax_sync_all_dark' ) );

		// CSS injection
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ), 15 );

		// ── Core filter: merge UTILGP colors into GP Global Colors on every read ──
		// This ensures GP React Color picker, Customizer, and GB editor ALWAYS see
		// our expanded + gradient colors without needing a page reload or DB patch.
		// Priority 20 = after GP's own filters (default priority 10).
		add_filter( 'option_generate_settings', array( $this, 'filter_gp_global_colors' ), 20 );
	}

	public function enqueue_frontend_styles() {
		$css = $this->get_expanded_css();
		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'utilgp-utility-frontend', wp_strip_all_tags( $css ) );
		}
	}

	/**
	 * Build expanded color variables CSS.
	 */
	private function get_expanded_css() {
		$expanded = get_option( 'utilgp_expanded_colors', array() );

		if ( empty( $expanded ) || ! is_array( $expanded ) ) {
			return '';
		}

		$lines = array();
		foreach ( $expanded as $set ) {
			if ( empty( $set['variables'] ) ) continue;

			$is_gp = ! empty( $set['gp_replace'] );
			$lines[] = "/* " . esc_html( $set['slug'] ) . ( $is_gp ? ' (GP override)' : ' (custom)' ) . ' */';

			foreach ( $set['variables'] as $var => $hex ) {
				if ( $is_gp && $var === "--{$set['slug']}" ) continue;

				$important = $is_gp ? ' !important' : '';
				$lines[]   = esc_html( $var ) . ': ' . esc_html( $hex ) . $important . ';';
			}
		}

		if ( empty( $lines ) ) return '';

		return ":root {\n" . implode( "\n", $lines ) . "\n}";
	}

	// ─────────────────────────────────────────
	// CORE FILTER: Merge UTILGP → GP Global Colors
	// ─────────────────────────────────────────

	/**
	 * Filter generate_settings option to inject our expanded colors and
	 * gradient variables into GP's global_colors array.
	 *
	 * Called every time WordPress reads option_generate_settings.
	 * This makes our colors visible in:
	 *   1. GP React Color Manager (Customizer sidebar)
	 *   2. GenerateBlocks color palette (block editor)
	 *   3. Any GP/GB component that reads global_colors
	 *
	 * Rules:
	 *   - Original GP colors that we haven't touched → unchanged
	 *   - Colors we mapped/imported → hex updated to our value
	 *   - New colors not in GP → appended at the end
	 *   - Gradient variables → appended with first-stop hex as representative
	 *     (the actual gradient is injected via wp_head priority 9998)
	 *
	 * @param mixed $settings  Value of generate_settings option
	 * @return mixed           Modified settings
	 */
	public function filter_gp_global_colors( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		// Prevent recursive calls (filter running during our own get_option calls)
		static $is_filtering = false;
		if ( $is_filtering ) {
			return $settings;
		}
		$is_filtering = true;

		$gp_colors = isset( $settings['global_colors'] ) ? $settings['global_colors'] : array();

		// Build a slug-indexed map from existing GP colors (preserves original order)
		$color_map    = array();
		$slug_order   = array(); // track original order by slug
		foreach ( $gp_colors as $c ) {
			$slug = $c['slug'] ?? '';
			if ( $slug ) {
				$color_map[ $slug ] = $c;
				$slug_order[]       = $slug;
			}
		}

		// ── Merge 1: Expanded/imported solid colors ──
		$expanded = get_option( 'utilgp_expanded_colors', array() );
		if ( is_array( $expanded ) ) {
			foreach ( $expanded as $set ) {
				$slug = $set['slug'] ?? '';
				$hex  = $set['hex']  ?? '';
				$name = $set['name'] ?? $slug;
				if ( ! $slug || ! preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $hex ) ) continue;

				if ( isset( $color_map[ $slug ] ) ) {
					// Update hex but preserve the original GP color name
					$color_map[ $slug ]['color'] = $hex;
				} else {
					// New color — append
					$color_map[ $slug ] = array(
						'name'  => $name,
						'slug'  => $slug,
						'color' => $hex,
					);
					$slug_order[] = $slug;
				}
			}
		}

		// ── Rebuild: solid colors first (original order) ──
		$merged = array();
		foreach ( $slug_order as $slug ) {
			if ( isset( $color_map[ $slug ] ) ) {
				$merged[] = $color_map[ $slug ];
			}
		}

		$settings['global_colors'] = $merged;

		$is_filtering = false;
		return $settings;
	}

	// ─────────────────────────────────────────
	// CUSTOMIZER REGISTRATION
	// ─────────────────────────────────────────

	public function register_customizer( $wp_customize ) {
		// Section: Color Management (below Dark Mode, priority 20)
		$wp_customize->add_section(
			'utilgp_color_management',
			array(
				'title'    => 'Color Management',
				'panel'    => 'utilgp_utility_panel',
				'priority' => 10,
			)
		);

		// Setting: stores the array of expanded color sets
		$wp_customize->add_setting(
			'utilgp_expanded_colors',
			array(
				'default'           => array(),
				'type'              => 'option',
				'sanitize_callback' => array( $this, 'sanitize_expanded_colors' ),
				'transport'         => 'postMessage',
			)
		);

		// Custom control
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-color-import-control.php';
		$wp_customize->add_control(
			new UTILGP_Color_Import_Control(
				$wp_customize,
				'utilgp_expanded_colors',
				array(
					'label'   => 'Color Import & Expansion',
					'section' => 'utilgp_color_management',
				)
			)
		);
	}

	public function enqueue_customizer_assets() {
		wp_enqueue_style(
			'utilgp-color-module',
			UTILGP_PLUGIN_URL . 'assets/css/utilgp-color-module.css',
			array(),
			UTILGP_VERSION
		);

		wp_enqueue_script(
			'utilgp-color-module',
			UTILGP_PLUGIN_URL . 'assets/js/utilgp-color-module.js',
			array( 'jquery', 'customize-controls', 'wp-element', 'wp-components' ),
			UTILGP_VERSION,
			true
		);

		// Pass PHP data to JS
		$existing_gp_colors = array();
		$gp_settings = get_option( 'generate_settings', array() );
		if ( ! empty( $gp_settings['global_colors'] ) ) {
			foreach ( $gp_settings['global_colors'] as $c ) {
				if ( ! empty( $c['slug'] ) ) {
					$existing_gp_colors[] = array(
						'slug'  => $c['slug'],
						'name'  => $c['name'],
						'color' => $c['color'],
					);
				}
			}
		}

		$saved = get_option( 'utilgp_expanded_colors', array() );

		wp_localize_script(
			'utilgp-color-module',
			'utilgpColorModule',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'utilgp_color_module' ),
				'gpColors'      => $existing_gp_colors,
				'savedExpanded' => is_array( $saved ) ? $saved : array(),
			)
		);
	}

	// ─────────────────────────────────────────
	// AJAX HANDLERS
	// ─────────────────────────────────────────

	/**
	 * AJAX: Parse raw color input, return detected colors.
	 * Used to populate the mapping wizard modal.
	 */
	public function ajax_parse_colors() {
		check_ajax_referer( 'utilgp_color_module', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$raw = isset( $_POST['raw'] ) ? sanitize_textarea_field( wp_unslash( $_POST['raw'] ) ) : '';
		if ( empty( $raw ) ) {
			wp_send_json_error( 'Empty input' );
		}

		$parsed = UTILGP_Color_Math::parse_import( $raw );

		if ( empty( $parsed ) ) {
			wp_send_json_error( 'No valid colors found. Supported formats: hex list, CSS variables (--slug: #hex), or JSON.' );
		}

		wp_send_json_success( array( 'colors' => $parsed ) );
	}

	/**
	 * AJAX: Expand a set of mapped colors using the Math Engine.
	 * Returns the full CSS variable map + dark counterparts.
	 * Now also accepts 'gp_replace' flag — true means this slug exists in GP Global Colors.
	 */
	public function ajax_expand_colors() {
		check_ajax_referer( 'utilgp_color_module', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$colors = isset( $_POST['colors'] ) ? map_deep( wp_unslash( $_POST['colors'] ), 'sanitize_text_field' ) : array();
		
		if ( empty( $colors ) ) {
			wp_send_json_error( 'Invalid colors data' );
		}

		$result = array();

		foreach ( $colors as $item ) {
			$slug       = sanitize_key( $item['slug'] ?? '' );
			$hex        = sanitize_text_field( $item['hex'] ?? '' );
			$gp_replace = ! empty( $item['gp_replace'] ); // true = replaces existing GP slug
			$options    = array(
				'scale'            => ! empty( $item['options']['scale'] ),
				'complementary'    => ! empty( $item['options']['complementary'] ),
				'triadic'          => ! empty( $item['options']['triadic'] ),
				'analogous'        => ! empty( $item['options']['analogous'] ),
				'split_comp'       => ! empty( $item['options']['split_comp'] ),
				'dark_counterpart' => ! empty( $item['options']['dark_counterpart'] ),
			);

			if ( empty( $slug ) || ! preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $hex ) ) {
				continue;
			}

			$expanded = UTILGP_Color_Math::expand_color( $slug, $hex, $options );

			$result[] = array(
				'slug'              => $slug,
				'hex'               => $hex,
				'gp_replace'        => $gp_replace,
				'options'           => $options,
				'variables'         => array_filter( $expanded, fn( $k ) => strpos( $k, '__dark__' ) !== 0, ARRAY_FILTER_USE_KEY ),
				'dark_counterparts' => UTILGP_Color_Math::extract_dark_counterparts( $expanded ),
			);
		}

		wp_send_json_success( array( 'expanded' => $result ) );
	}

	/**
	 * AJAX: Save expanded colors to DB.
	 * For sets flagged as 'gp_replace', update generate_settings[global_colors] directly.
	 * This ensures the GP color variable is truly replaced at the source.
	 */
	public function ajax_save_expanded() {
		check_ajax_referer( 'utilgp_color_module', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$expanded = isset( $_POST['expanded'] ) ? map_deep( wp_unslash( $_POST['expanded'] ), 'sanitize_text_field' ) : array();
		
		if ( empty( $expanded ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		// Sanitize and save
		$sanitized = $this->sanitize_expanded_colors( $expanded );
		update_option( 'utilgp_expanded_colors', $sanitized );

		// Update GP Global Colors for items flagged as 'gp_replace'
		$gp_replaced = $this->sync_gp_colors( $sanitized );

		// Auto-sync dark counterparts:
		// Always run if Dark Mode module is active (both modules active = auto-managed).
		// Falls back to manual flag check otherwise.
		$dark_mode_active = ( class_exists( 'UTILGP_Dark_Mode' ) );
		$updated_dark_colors = array();
		if ( $dark_mode_active || ! empty( $_POST['sync_dark'] ) ) {
			$updated_dark_colors = $this->sync_dark_counterparts( $sanitized );
		}

		$msg = count( $sanitized ) . ' color set(s) saved.';
		if ( $gp_replaced > 0 ) {
			$msg .= ' ' . $gp_replaced . ' GP variable(s) updated at source.';
		}

		// Return the updated GP global_colors so JS can sync the Customizer React picker
		$gp_settings = get_option( 'generate_settings', array() );
		$updated_gp_colors = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();

		wp_send_json_success( array(
			'saved'               => count( $sanitized ),
			'gp_replaced'         => $gp_replaced,
			'message'             => $msg,
			'updated_gp_colors'   => $updated_gp_colors, // for Customizer live sync
			'updated_dark_colors' => $updated_dark_colors,
		) );
	}

	/**
	 * Update generate_settings[global_colors] for any color set flagged as gp_replace.
	 * This makes the import truly replace the GP color at the source —
	 * visible in GP's own color picker and output to :root natively by GP.
	 *
	 * @param array $expanded Sanitized expanded color sets
	 * @return int Number of GP colors updated
	 */
	private function sync_gp_colors( $expanded ) {
		$gp_settings = get_option( 'generate_settings', array() );
		$gp_colors   = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();
		$updated     = 0;

		foreach ( $expanded as $set ) {
			if ( empty( $set['gp_replace'] ) || empty( $set['slug'] ) || empty( $set['hex'] ) ) {
				continue;
			}

			$target_slug = $set['slug'];

			// Find and update the matching GP color
			$found = false;
			foreach ( $gp_colors as &$gpc ) {
				if ( isset( $gpc['slug'] ) && $gpc['slug'] === $target_slug ) {
					$gpc['color'] = $set['hex'];
					$found = true;
					$updated++;
					break;
				}
			}
			unset( $gpc );

			// If not found (shouldn't happen if gp_replace is correct), add it
			if ( ! $found ) {
				$gp_colors[] = array(
					'name'  => $set['slug'],
					'slug'  => $set['slug'],
					'color' => $set['hex'],
				);
				$updated++;
			}
		}

		if ( $updated > 0 ) {
			$gp_settings['global_colors'] = $gp_colors;
			update_option( 'generate_settings', $gp_settings );

			// Also update theme_mod for Customizer live preview
			$current_mods = get_theme_mod( 'generate_settings', array() );
			if ( is_array( $current_mods ) ) {
				$current_mods['global_colors'] = $gp_colors;
				set_theme_mod( 'generate_settings', $current_mods );
			}
		}

		return $updated;
	}

	/**
	 * AJAX: Bulk re-sync ALL existing dark counterparts from saved expanded colors.
	 * Called by JS when user clicks "Re-sync All Dark Colors" button.
	 * Only available when both Color + Dark Mode modules are active.
	 */
	public function ajax_sync_all_dark() {
		check_ajax_referer( 'utilgp_color_module', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$expanded = get_option( 'utilgp_expanded_colors', array() );
		if ( empty( $expanded ) ) {
			wp_send_json_error( 'No expanded colors found.' );
		}

		$updated_dark_colors = $this->sync_dark_counterparts( $expanded );

		wp_send_json_success( array(
			'message'             => count( $expanded ) . ' color set(s) dark counterparts synced.',
			'updated_dark_colors' => $updated_dark_colors,
		) );
	}

	/**
	 * Push all dark counterparts from expanded colors into
	 * utilgp_dark_global_colors theme_mod (used by Dark Mode CSS injection).
	 */
	private function sync_dark_counterparts( $expanded ) {
		$existing_dark = get_theme_mod( 'utilgp_dark_global_colors', array() );
		if ( ! is_array( $existing_dark ) ) {
			$existing_dark = array();
		}

		// Build a lookup map of existing dark colors by slug
		$dark_map = array();
		foreach ( $existing_dark as $dc ) {
			if ( isset( $dc['slug'] ) ) {
				$dark_map[ $dc['slug'] ] = $dc;
			}
		}

		// Merge new dark counterparts
		foreach ( $expanded as $set ) {
			if ( empty( $set['dark_counterparts'] ) ) continue;

			foreach ( $set['dark_counterparts'] as $slug => $hex ) {
				$dark_map[ $slug ] = array(
					'name'  => $slug,
					'slug'  => $slug,
					'color' => $hex,
				);
			}
		}

		// Re-save as indexed array
		$final_dark_colors = array_values( $dark_map );
		set_theme_mod( 'utilgp_dark_global_colors', $final_dark_colors );
		
		return $final_dark_colors;
	}

	// ─────────────────────────────────────────
	// SANITIZATION
	// ─────────────────────────────────────────

	public function sanitize_expanded_colors( $input ) {
		if ( ! is_array( $input ) ) return array();

		$clean = array();
		foreach ( $input as $set ) {
			if ( empty( $set['slug'] ) || empty( $set['hex'] ) ) continue;

			$slug       = sanitize_key( $set['slug'] );
			$hex        = sanitize_text_field( $set['hex'] );
			$gp_replace = ! empty( $set['gp_replace'] );
			if ( ! preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $hex ) ) continue;

			// Sanitize variables map
			$vars = array();
			if ( ! empty( $set['variables'] ) && is_array( $set['variables'] ) ) {
				foreach ( $set['variables'] as $var => $val ) {
					$var = sanitize_text_field( $var );
					$val = sanitize_text_field( $val );
					if ( preg_match( '/^--[a-z0-9-]+$/', $var ) && preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $val ) ) {
						$vars[ $var ] = $val;
					}
				}
			}

			// Sanitize dark counterparts
			$dark = array();
			if ( ! empty( $set['dark_counterparts'] ) && is_array( $set['dark_counterparts'] ) ) {
				foreach ( $set['dark_counterparts'] as $dslug => $dhex ) {
					$dslug = sanitize_key( $dslug );
					$dhex  = sanitize_text_field( $dhex );
					if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/i', $dhex ) ) {
						$dark[ $dslug ] = $dhex;
					}
				}
			}

			$clean[] = array(
				'slug'              => $slug,
				'hex'               => $hex,
				'gp_replace'        => $gp_replace,
				'options'           => $set['options'] ?? array(),
				'variables'         => $vars,
				'dark_counterparts' => $dark,
			);
		}

		return $clean;
	}
}
