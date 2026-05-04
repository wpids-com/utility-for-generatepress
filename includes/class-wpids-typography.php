<?php
/**
 * Typography Class.
 * Handles fluid typography clamp calculations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Typography {

	public function init() {
		// Register Customizer settings
		add_action( 'customize_register', array( $this, 'register_customizer' ), 999 );

		// Customizer Assets
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_assets' ) );

		// Inject CSS variables
		add_action( 'wp_head', array( $this, 'inject_typography_css' ), 10 );

		// Inject CSS variables into the editor
		add_action( 'enqueue_block_editor_assets', array( $this, 'inject_typography_editor_css' ) );

		// Filter GP settings to apply fluid sizes
		add_filter( 'option_generate_settings', array( $this, 'filter_gp_typography' ), 25 );
	}

	public function enqueue_customizer_assets() {
		// Global Plugin Admin CSS (Buttons, etc)
		wp_enqueue_style(
			'wpids-admin-common',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-admin-common.css',
			array(),
			WPIDS_UTILITY_VERSION
		);

		wp_enqueue_style(
			'wpids-typography-admin',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-typography-admin.css',
			array(),
			WPIDS_UTILITY_VERSION
		);

		wp_enqueue_script(
			'wpids-typography',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/js/wpids-typography.js',
			array( 'jquery', 'customize-controls' ),
			WPIDS_UTILITY_VERSION,
			true
		);
	}

	/**
	 * Define the available type scales
	 */
	public static function get_scales() {
		return array(
			'1.067' => 'Minor Second (1.067)',
			'1.125' => 'Major Second (1.125)',
			'1.200' => 'Minor Third (1.200)',
			'1.250' => 'Major Third (1.250)',
			'1.333' => 'Perfect Fourth (1.333)',
			'1.414' => 'Augmented Fourth (1.414)',
			'1.500' => 'Perfect Fifth (1.500)',
			'1.618' => 'Golden Ratio (1.618)',
			'custom' => 'Custom / User Preference',
		);
	}

	public function register_customizer( $wp_customize ) {
		// 0. Register the Section (Fix for missing menu)
		$wp_customize->add_section(
			'wpids_typography_section',
			array(
				'title'    => 'Fluid Typography',
				'panel'    => 'wpids_utility_panel',
				'priority' => 30,
			)
		);

		// 1. Enable (Priority 10)
		$wp_customize->add_setting(
			'wpids_fluid_typo_enabled',
			array(
				'default'           => false,
				'type'              => 'option',
				'sanitize_callback' => 'wp_validate_boolean',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'wpids_fluid_typo_enabled',
			array(
				'label'    => __( 'Enable Fluid Typography', 'generatepress-utility' ),
				'section'  => 'wpids_typography_section',
				'type'     => 'checkbox',
				'priority' => 10,
			)
		);

		// 2. Grid Group (Base Size, Unit, Viewport Limits)
		$wp_customize->add_setting( 'wpids_typo_grid_ui', array( 'type' => 'hidden' ) );
		$wp_customize->add_control(
			new WPIDS_Fluid_Typography_Control(
				$wp_customize,
				'wpids_typo_grid_ui',
				array(
					'label'    => __( 'Typography Scale Settings', 'generatepress-utility' ),
					'section'  => 'wpids_typography_section',
					'priority' => 20,
				)
			)
		);

		// Keep the settings for internal use (math calculations), but remove their individual controls
		$wp_customize->add_setting( 'wpids_typo_base_size', array( 'default' => 16, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'wpids_typo_base_unit', array( 'default' => 'px', 'type' => 'option', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'wpids_typo_min_vw', array( 'default' => 320, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'wpids_typo_max_vw', array( 'default' => 1280, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );

		// 3. Scale Ratio (Priority 30)
		$wp_customize->add_setting(
			'wpids_typo_scale_ratio',
			array(
				'default'           => '1.250',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'wpids_typo_scale_ratio',
			array(
				'label'    => __( 'Type Scale Ratio', 'generatepress-utility' ),
				'section'  => 'wpids_typography_section',
				'type'     => 'select',
				'choices'  => self::get_scales(),
				'priority' => 30,
			)
		);

		$wp_customize->add_setting(
			'wpids_typo_custom_ratio',
			array(
				'default'           => '1.2',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'wpids_typo_custom_ratio',
			array(
				'label'           => __( 'Custom Ratio', 'generatepress-utility' ),
				'section'         => 'wpids_typography_section',
				'type'            => 'text',
				'priority'        => 31,
				'active_callback' => function() {
					return get_option( 'wpids_typo_scale_ratio' ) === 'custom';
				},
			)
		);

		// 5. Preview Text (Priority 50)
		$wp_customize->add_setting(
			'wpids_typo_preview_text',
			array(
				'default'           => 'The quick brown fox jumps over the lazy dog',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'postMessage',
			)
		);

		$wp_customize->add_control(
			'wpids_typo_preview_text',
			array(
				'label'    => __( 'Preview Text', 'generatepress-utility' ),
				'section'  => 'wpids_typography_section',
				'type'     => 'text',
				'priority' => 50,
			)
		);

		// 6. Launch Wizard (Priority 60)
		$wp_customize->add_setting( 'wpids_typo_wizard_trigger', array( 'type' => 'hidden' ) );
		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpids_typo_wizard_trigger',
				array(
					'label'       => '', 
					'section'     => 'wpids_typography_section',
					'type'        => 'hidden',
					'priority'    => 60,
				)
			)
		);
	}

	/**
	 * Calculate a fluid font size using clamp()
	 */
	public static function calculate_fluid( $base_val, $ratio, $step, $min_vw = 320, $max_vw = 1280, $unit = 'px' ) {
		// If base is rem/em, convert to px for slope math
		$base_px = ( $unit === 'px' ) ? $base_val : $base_val * 16;

		// Calculate min and max sizes based on the step
		$max_size = $base_px * pow( $ratio, $step );
		$min_size = $max_size / 1.2; // Scaling factor for mobile

		// Convert back to rem for clamp output (standard accessibility practice)
		$min_rem = round( $min_size / 16, 3 );
		$max_rem = round( $max_size / 16, 3 );

		// Formula: clamp(min, val, max)
		$slope = ( $max_size - $min_size ) / ( $max_vw - $min_vw );
		$intersection = ( -1 * $min_vw ) * $slope + $min_size;
		
		$v_unit = round( $slope * 100, 3 );
		$r_unit = round( $intersection / 16, 3 );

		return "clamp({$min_rem}rem, {$r_unit}rem + {$v_unit}vw, {$max_rem}rem)";
	}

	/**
	 * Injects the fluid scales as CSS variables
	 */
	public function inject_typography_css() {
		if ( ! get_option( 'wpids_fluid_typo_enabled' ) ) return;

		$base   = get_option( 'wpids_typo_base_size', 16 );
		$unit   = get_option( 'wpids_typo_base_unit', 'px' );
		$min_vw = get_option( 'wpids_typo_min_vw', 320 );
		$max_vw = get_option( 'wpids_typo_max_vw', 1280 );
		$ratio  = get_option( 'wpids_typo_scale_ratio', '1.250' );
		if ( $ratio === 'custom' ) {
			$ratio = floatval( get_option( 'wpids_typo_custom_ratio', '1.2' ) );
		} else {
			$ratio = floatval( $ratio );
		}

		echo "<style id='wpids-fluid-typography-css'>\n:root {\n";
		// Generate steps
		for ( $i = -1; $i <= 8; $i++ ) {
			$var_name = "--wpids-step-{$i}";
			$fluid_val = self::calculate_fluid( $base, $ratio, $i, $min_vw, $max_vw, $unit );
			echo "\t" . esc_html( $var_name ) . ": " . esc_html( $fluid_val ) . ";\n";
		}
		echo "}\n";

		// Direct Overrides
		echo "body { font-size: var(--wpids-step-0) !important; }\n";
		echo "h6, .h6 { font-size: var(--wpids-step-1) !important; }\n";
		echo "h5, .h5 { font-size: var(--wpids-step-2) !important; }\n";
		echo "h4, .h4 { font-size: var(--wpids-step-3) !important; }\n";
		echo "h3, .h3 { font-size: var(--wpids-step-4) !important; }\n";
		echo "h2, .h2 { font-size: var(--wpids-step-5) !important; }\n";
		echo "h1, .h1 { font-size: var(--wpids-step-6) !important; }\n";
		
		echo "</style>";
	}

	/**
	 * Injects the fluid scales as CSS variables into the Block Editor
	 */
	public function inject_typography_editor_css() {
		if ( ! get_option( 'wpids_fluid_typo_enabled' ) ) return;

		$base   = get_option( 'wpids_typo_base_size', 16 );
		$unit   = get_option( 'wpids_typo_base_unit', 'px' );
		$min_vw = get_option( 'wpids_typo_min_vw', 320 );
		$max_vw = get_option( 'wpids_typo_max_vw', 1280 );
		$ratio  = get_option( 'wpids_typo_scale_ratio', '1.250' );
		if ( $ratio === 'custom' ) {
			$ratio = floatval( get_option( 'wpids_typo_custom_ratio', '1.2' ) );
		} else {
			$ratio = floatval( $ratio );
		}

		$css = ":root {\n";
		for ( $i = -1; $i <= 8; $i++ ) {
			$var_name = "--wpids-step-{$i}";
			$css .= "\t{$var_name}: " . self::calculate_fluid( $base, $ratio, $i, $min_vw, $max_vw, $unit ) . ";\n";
		}
		$css .= "}\n";

		// Apply to editor canvas
		$css .= ".editor-styles-wrapper { font-size: var(--wpids-step-0); }\n";
		$css .= ".editor-styles-wrapper h1 { font-size: var(--wpids-step-6) !important; }\n";
		$css .= ".editor-styles-wrapper h2 { font-size: var(--wpids-step-5) !important; }\n";
		$css .= ".editor-styles-wrapper h3 { font-size: var(--wpids-step-4) !important; }\n";
		$css .= ".editor-styles-wrapper h4 { font-size: var(--wpids-step-3) !important; }\n";
		$css .= ".editor-styles-wrapper h5 { font-size: var(--wpids-step-2) !important; }\n";
		$css .= ".editor-styles-wrapper h6 { font-size: var(--wpids-step-1) !important; }\n";

		wp_add_inline_style( 'wpids-utility-editor', $css );
	}

	/**
	 * Filter GP settings to automatically apply fluid sizes to Dynamic Typography
	 */
	public function filter_gp_typography( $settings ) {
		if ( ! get_option( 'wpids_fluid_typo_enabled' ) ) return $settings;
		if ( ! isset( $settings['typography'] ) || ! is_array( $settings['typography'] ) ) return $settings;

		$base   = get_option( 'wpids_typo_base_size', 16 );
		$unit   = get_option( 'wpids_typo_base_unit', 'px' );
		$ratio  = get_option( 'wpids_typo_scale_ratio', '1.250' );
		if ( $ratio === 'custom' ) {
			$ratio = floatval( get_option( 'wpids_typo_custom_ratio', '1.2' ) );
		} else {
			$ratio = floatval( $ratio );
		}

		/**
		 * Advanced Mapping:
		 * Maps GP selectors to Scale Steps
		 */
		$mapping = array(
			'site-title' => 6, // Step 6
			'h1'         => 6,
			'h2'         => 5,
			'h3'         => 4,
			'h4'         => 3,
			'h5'         => 2,
			'h6'         => 1,
			'navigation' => 0, // Step 0
			'button'     => 0,
			'body'       => 0,
			'site-description' => -1, // Step -1
		);

		$min_vw = get_option( 'wpids_typo_min_vw', 320 );
		$max_vw = get_option( 'wpids_typo_max_vw', 1280 );

		foreach ( $settings['typography'] as &$typo ) {
			$selector = strtolower( $typo['selector'] ?? '' );
			
			foreach ( $mapping as $key => $step ) {
				if ( strpos( $selector, $key ) !== false ) {
					$typo['fontSize'] = self::calculate_fluid( $base, $ratio, $step, $min_vw, $max_vw );
					$typo['fontSizeUnit'] = 'number';
					break;
				}
			}
		}

		return $settings;
	}
}

/**
 * Custom Control for Fluid Typography Grid
 */
if ( class_exists( 'WP_Customize_Control' ) ) {
	class WPIDS_Fluid_Typography_Control extends WP_Customize_Control {
		public $type = 'wpids_fluid_grid';

		public function render_content() {
			$base_size = get_option( 'wpids_typo_base_size', 16 );
			$base_unit = get_option( 'wpids_typo_base_unit', 'px' );
			$min_vw    = get_option( 'wpids_typo_min_vw', 320 );
			$max_vw    = get_option( 'wpids_typo_max_vw', 1280 );
			?>
			<div class="wpids-fluid-grid-container">
				<div class="wpids-grid-layout">
					<!-- Base Size Row -->
					<div class="wpids-grid-row">
						<div class="wpids-grid-item">
							<label>Base Size</label>
							<div class="wpids-input-group">
								<input type="number" class="wpids-grid-input" data-setting="wpids_typo_base_size" value="<?php echo esc_attr( $base_size ); ?>">
								<select class="wpids-grid-select" data-setting="wpids_typo_base_unit">
									<option value="px" <?php selected( $base_unit, 'px' ); ?>>px</option>
									<option value="rem" <?php selected( $base_unit, 'rem' ); ?>>rem</option>
									<option value="em" <?php selected( $base_unit, 'em' ); ?>>em</option>
								</select>
							</div>
						</div>
					</div>

					<!-- Viewport Row -->
					<div class="wpids-grid-row">
						<div class="wpids-grid-item">
							<label><?php esc_html_e( 'Min Viewport', 'generatepress-utility' ); ?></label>
							<input type="number" class="wpids-grid-input" data-setting="wpids_typo_min_vw" value="<?php echo esc_attr( $min_vw ); ?>">
						</div>
						<div class="wpids-grid-item">
							<label><?php esc_html_e( 'Max Viewport', 'generatepress-utility' ); ?></label>
							<input type="number" class="wpids-grid-input" data-setting="wpids_typo_max_vw" value="<?php echo esc_attr( $max_vw ); ?>">
						</div>
					</div>

					<div class="wpids-grid-description">
						<?php 
						echo wp_kses( 
							__( 'Uses the CSS <code>clamp()</code> property to dynamically control font growth between viewports.', 'generatepress-utility' ), 
							array( 'code' => array() ) 
						); 
						?>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
