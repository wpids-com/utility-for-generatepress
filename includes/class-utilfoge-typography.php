<?php
/**
 * Typography Class.
 * Handles fluid typography clamp calculations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Typography {

	public function init() {
		// Register Customizer settings
		add_action( 'customize_register', array( $this, 'register_customizer' ), 999 );

		// Customizer Assets
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customizer_assets' ) );

		// CSS injection
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ), 20 );

		// Inject CSS variables into the editor
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_styles' ), 20 );

		// Filter GP settings to apply fluid sizes
		add_filter( 'option_generate_settings', array( $this, 'filter_gp_typography' ), 25 );
	}

	/**
	 * Helper to get settings correctly in Customizer and Frontend
	 */
	public function get_setting( $id, $default = '' ) {
		global $wp_customize;
		if ( is_customize_preview() && isset( $wp_customize ) ) {
			$setting = $wp_customize->get_setting( $id );
			if ( $setting ) {
				return $setting->value();
			}
		}
		return get_option( $id, $default );
	}

	public function enqueue_frontend_styles() {
		$css = $this->get_typography_css();
		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'utilfoge-utility-frontend', wp_strip_all_tags( $css ) );
		}
	}

	public function enqueue_editor_styles() {
		$css = $this->get_typography_css();
		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'utilfoge-utility-editor', wp_strip_all_tags( $css ) );
		}
	}

	/**
	 * Build the typography CSS string.
	 */
	private function get_typography_css() {
		if ( ! $this->get_setting( 'utilfoge_fluid_typo_enabled', false ) ) {
			return '';
		}

		$base   = $this->get_setting( 'utilfoge_typo_base_size', 16 );
		$unit   = $this->get_setting( 'utilfoge_typo_base_unit', 'px' );
		$min_vw = $this->get_setting( 'utilfoge_typo_min_vw', 320 );
		$max_vw = $this->get_setting( 'utilfoge_typo_max_vw', 1280 );
		$ratio  = $this->get_setting( 'utilfoge_typo_scale_ratio', '1.250' );
		
		if ( $ratio === 'custom' ) {
			$ratio = floatval( $this->get_setting( 'utilfoge_typo_custom_ratio', '1.2' ) );
		} else {
			$ratio = floatval( $ratio );
		}

		$css = ":root {\n";
		for ( $i = -1; $i <= 8; $i++ ) {
			$var_name = "--utilfoge-step-{$i}";
			$fluid_val = self::calculate_fluid( $base, $ratio, $i, $min_vw, $max_vw, $unit );
			$css .= "\t" . esc_html( $var_name ) . ": " . esc_html( $fluid_val ) . ";\n";
		}
		$css .= "}\n";

		// Global Overrides - Scoped to frontend only by excluding admin/customizer body classes
		$css .= "body:not(.wp-admin):not(.wp-customizer) { font-size: var(--utilfoge-step-0) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h6, body:not(.wp-admin):not(.wp-customizer) .h6 { font-size: var(--utilfoge-step-1) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h5, body:not(.wp-admin):not(.wp-customizer) .h5 { font-size: var(--utilfoge-step-2) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h4, body:not(.wp-admin):not(.wp-customizer) .h4 { font-size: var(--utilfoge-step-3) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h3, body:not(.wp-admin):not(.wp-customizer) .h3 { font-size: var(--utilfoge-step-4) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h2, body:not(.wp-admin):not(.wp-customizer) .h2 { font-size: var(--utilfoge-step-5) !important; }\n";
		$css .= "body:not(.wp-admin):not(.wp-customizer) h1, body:not(.wp-admin):not(.wp-customizer) .h1 { font-size: var(--utilfoge-step-6) !important; }\n";

		return $css;
	}

	public function enqueue_customizer_assets() {
		// Global Plugin Admin CSS
		wp_enqueue_style(
			'utilfoge-admin-common',
			UTILFOGE_PLUGIN_URL . 'assets/css/utilfoge-admin-common.css',
			array(),
			UTILFOGE_VERSION
		);

		wp_enqueue_style(
			'utilfoge-typography-admin',
			UTILFOGE_PLUGIN_URL . 'assets/css/utilfoge-typography-admin.css',
			array(),
			UTILFOGE_VERSION
		);

		wp_enqueue_script(
			'utilfoge-typography',
			UTILFOGE_PLUGIN_URL . 'assets/js/utilfoge-typography.js',
			array( 'jquery', 'customize-controls' ),
			UTILFOGE_VERSION,
			true
		);
	}

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
		$wp_customize->add_section(
			'utilfoge_typography_section',
			array(
				'title'    => 'Fluid Typography',
				'panel'    => 'utilfoge_utility_panel',
				'priority' => 30,
			)
		);

		$wp_customize->add_setting(
			'utilfoge_fluid_typo_enabled',
			array(
				'default'           => false,
				'type'              => 'option',
				'sanitize_callback' => 'wp_validate_boolean',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'utilfoge_fluid_typo_enabled',
			array(
				'label'    => __( 'Enable Fluid Typography', 'utility-for-generatepress' ),
				'section'  => 'utilfoge_typography_section',
				'type'     => 'checkbox',
				'priority' => 10,
			)
		);

		$wp_customize->add_setting( 'utilfoge_typo_grid_ui', array( 'type' => 'hidden' ) );
		$wp_customize->add_control(
			new UTILFOGE_Fluid_Typography_Control(
				$wp_customize,
				'utilfoge_typo_grid_ui',
				array(
					'label'    => __( 'Typography Scale Settings', 'utility-for-generatepress' ),
					'section'  => 'utilfoge_typography_section',
					'priority' => 20,
				)
			)
		);

		$wp_customize->add_setting( 'utilfoge_typo_base_size', array( 'default' => 16, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'utilfoge_typo_base_unit', array( 'default' => 'px', 'type' => 'option', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'utilfoge_typo_min_vw', array( 'default' => 320, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );
		$wp_customize->add_setting( 'utilfoge_typo_max_vw', array( 'default' => 1280, 'type' => 'option', 'sanitize_callback' => 'absint', 'transport' => 'refresh' ) );

		$wp_customize->add_setting(
			'utilfoge_typo_scale_ratio',
			array(
				'default'           => '1.250',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'utilfoge_typo_scale_ratio',
			array(
				'label'    => __( 'Type Scale Ratio', 'utility-for-generatepress' ),
				'section'  => 'utilfoge_typography_section',
				'type'     => 'select',
				'choices'  => self::get_scales(),
				'priority' => 30,
			)
		);

		$wp_customize->add_setting(
			'utilfoge_typo_custom_ratio',
			array(
				'default'           => '1.2',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'utilfoge_typo_custom_ratio',
			array(
				'label'           => __( 'Custom Ratio', 'utility-for-generatepress' ),
				'section'         => 'utilfoge_typography_section',
				'type'            => 'text',
				'priority'        => 31,
				'active_callback' => function( $control ) {
					return $control->manager->get_setting( 'utilfoge_typo_scale_ratio' )->value() === 'custom';
				},
			)
		);

		$wp_customize->add_setting(
			'utilfoge_typo_preview_text',
			array(
				'default'           => 'The quick brown fox jumps over the lazy dog',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'postMessage',
			)
		);

		$wp_customize->add_control(
			'utilfoge_typo_preview_text',
			array(
				'label'    => __( 'Preview Text', 'utility-for-generatepress' ),
				'section'  => 'utilfoge_typography_section',
				'type'     => 'text',
				'priority' => 50,
			)
		);

		$wp_customize->add_setting( 'utilfoge_typo_wizard_trigger', array( 'type' => 'hidden' ) );
		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'utilfoge_typo_wizard_trigger',
				array(
					'label'       => '', 
					'section'     => 'utilfoge_typography_section',
					'type'        => 'hidden',
					'priority'    => 60,
				)
			)
		);
	}

	public static function calculate_fluid( $base_val, $ratio, $step, $min_vw = 320, $max_vw = 1280, $unit = 'px' ) {
		$base_px = ( $unit === 'px' ) ? $base_val : $base_val * 16;
		$max_size = $base_px * pow( $ratio, $step );
		$min_size = $max_size / 1.2; 
		$min_rem = round( $min_size / 16, 3 );
		$max_rem = round( $max_size / 16, 3 );
		$slope = ( $max_size - $min_size ) / ( $max_vw - $min_vw );
		$intersection = ( -1 * $min_vw ) * $slope + $min_size;
		$v_unit = round( $slope * 100, 3 );
		$r_unit = round( $intersection / 16, 3 );

		return "clamp({$min_rem}rem, {$r_unit}rem + {$v_unit}vw, {$max_rem}rem)";
	}

	public function filter_gp_typography( $settings ) {
		if ( ! $this->get_setting( 'utilfoge_fluid_typo_enabled', false ) ) {
			return $settings;
		}

		if ( ! isset( $settings['typography'] ) || ! is_array( $settings['typography'] ) ) {
			return $settings;
		}

		$base   = $this->get_setting( 'utilfoge_typo_base_size', 16 );
		$ratio  = $this->get_setting( 'utilfoge_typo_scale_ratio', '1.250' );
		if ( $ratio === 'custom' ) {
			$ratio = floatval( $this->get_setting( 'utilfoge_typo_custom_ratio', '1.2' ) );
		} else {
			$ratio = floatval( $ratio );
		}

		$mapping = array(
			'site-title' => 6,
			'h1'         => 6,
			'h2'         => 5,
			'h3'         => 4,
			'h4'         => 3,
			'h5'         => 2,
			'h6'         => 1,
			'navigation' => 0,
			'button'     => 0,
			'body'       => 0,
			'site-description' => -1,
		);

		$min_vw = get_option( 'utilfoge_typo_min_vw', 320 );
		$max_vw = get_option( 'utilfoge_typo_max_vw', 1280 );

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
	class UTILFOGE_Fluid_Typography_Control extends WP_Customize_Control {
		public $type = 'utilfoge_fluid_grid';

		public function render_content() {
			$base_size = get_option( 'utilfoge_typo_base_size', 16 );
			$base_unit = get_option( 'utilfoge_typo_base_unit', 'px' );
			$min_vw    = get_option( 'utilfoge_typo_min_vw', 320 );
			$max_vw    = get_option( 'utilfoge_typo_max_vw', 1280 );
			?>
			<div class="utilfoge-fluid-grid-container">
				<div class="utilfoge-grid-layout">
					<div class="utilfoge-grid-row">
						<div class="utilfoge-grid-item">
							<label><?php esc_html_e( 'Base Size', 'utility-for-generatepress' ); ?></label>
							<div class="utilfoge-input-group">
								<input type="number" class="utilfoge-grid-input" data-setting="utilfoge_typo_base_size" value="<?php echo esc_attr( $base_size ); ?>">
								<select class="utilfoge-grid-select" data-setting="utilfoge_typo_base_unit">
									<option value="px" <?php selected( $base_unit, 'px' ); ?>>px</option>
									<option value="rem" <?php selected( $base_unit, 'rem' ); ?>>rem</option>
									<option value="em" <?php selected( $base_unit, 'em' ); ?>>em</option>
								</select>
							</div>
						</div>
					</div>

					<div class="utilfoge-grid-row">
						<div class="utilfoge-grid-item">
							<label><?php esc_html_e( 'Min Viewport', 'utility-for-generatepress' ); ?></label>
							<input type="number" class="utilfoge-grid-input" data-setting="utilfoge_typo_min_vw" value="<?php echo esc_attr( $min_vw ); ?>">
						</div>
						<div class="utilfoge-grid-item">
							<label><?php esc_html_e( 'Max Viewport', 'utility-for-generatepress' ); ?></label>
							<input type="number" class="utilfoge-grid-input" data-setting="utilfoge_typo_max_vw" value="<?php echo esc_attr( $max_vw ); ?>">
						</div>
					</div>

					<div class="utilfoge-grid-description">
						<?php 
						echo wp_kses( 
							__( 'Uses the CSS <code>clamp()</code> property to dynamically control font growth between viewports.', 'utility-for-generatepress' ), 
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
