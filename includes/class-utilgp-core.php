<?php
/**
 * Core Class of the UTILGP Utility Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILGP_Core {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_dependencies();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-color-math.php';
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-color-module.php';
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-gradient-module.php';
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-typography.php';
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-dark-mode.php';
		require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-settings.php';
	}

	/**
	 * Run the plugin logic.
	 */
	public function run() {
		$settings = new UTILGP_Settings();
		$settings->init();

		$options = get_option( 'utilgp_options' );
		// 1. Color Management (import, expansion, harmony variants)
		$is_color_management = isset( $options['enable_color_manager'] ) ? $options['enable_color_manager'] : true;
		if ( $is_color_management ) {
			$color_module = new UTILGP_Color_Module();
			$color_module->init();
		}

		// 2. Gradient Palette (independent — theme.json native + utility classes)
		$is_gradient_palette = isset( $options['enable_gradient_palette'] ) ? $options['enable_gradient_palette'] : true;
		if ( $is_gradient_palette ) {
			$gradient_module = new UTILGP_Gradient_Module();
			$gradient_module->init();
		}

		// 2. Fluid Typography
		$is_typography = isset( $options['enable_typography'] ) ? $options['enable_typography'] : true;
		if ( $is_typography ) {
			$typography = new UTILGP_Typography();
			$typography->init();
		}

		// 3. Dark Mode
		$is_dark_mode = isset( $options['enable_dark_mode'] ) ? $options['enable_dark_mode'] : true;
		if ( $is_dark_mode ) {
			$dark_mode = new UTILGP_Dark_Mode();
			$dark_mode->init();
		}

		// 4. Editor CSS Sync is handled via enqueue_editor_assets() below

		// Enqueue global assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// Always register the Utility panel in Customizer (independent of modules)
		add_action( 'customize_register', array( $this, 'register_customizer_panel' ), 990 );

		// Add Settings link in Plugins list
		add_filter( 'plugin_action_links_' . plugin_basename( UTILGP_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Enqueue Admin & Customizer Assets.
	 */
	public function enqueue_admin_assets( $hook = '' ) {
		// Only load on our settings page to prevent breaking other GP dashboards
		if ( 'appearance_page_utilgp-utility' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'utilgp-admin-common',
			UTILGP_PLUGIN_URL . 'assets/css/utilgp-admin-common.css',
			array(),
			UTILGP_VERSION
		);
	}

	/**
	 * Always register the Utility Panel in Customizer.
	 * This ensures the panel appears regardless of which modules are active.
	 */
	public function register_customizer_panel( $wp_customize ) {
		$wp_customize->add_panel(
			'utilgp_utility_panel',
			array(
				'title'    => __( 'Utility', 'utility-for-generatepress' ),
				'priority' => 30,
			)
		);
	}

	/**
	 * Add Configure link to the Plugins list page.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'themes.php?page=utilgp-utility' ) ) . '">' . esc_html__( 'Configure', 'utility-for-generatepress' ) . '</a>';
		$links[] = $settings_link; // Append to the right of Deactivate
		return $links;
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'utilgp-utility-frontend',
			UTILGP_PLUGIN_URL . 'assets/css/utilgp-frontend.css',
			array(),
			UTILGP_VERSION,
			'all'
		);

		wp_enqueue_script(
			'utilgp-utility-frontend-js',
			UTILGP_PLUGIN_URL . 'assets/js/utilgp-frontend.js',
			array(),
			UTILGP_VERSION,
			true // in footer
		);
	}

	/**
	 * Enqueue Editor Assets.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_style(
			'utilgp-utility-editor',
			UTILGP_PLUGIN_URL . 'assets/css/utilgp-editor.css',
			array(),
			UTILGP_VERSION,
			'all'
		);

		$options = get_option( 'utilgp_options' );
		$is_editor_sync = isset( $options['enable_editor_sync'] ) ? $options['enable_editor_sync'] : true;

		if ( $is_editor_sync ) {
			// 1. Sync Child Theme / Active Theme Stylesheet to Editor
			$theme = wp_get_theme();
			wp_enqueue_style(
				'utilgp-theme-style-sync',
				get_stylesheet_uri(),
				array(),
				$theme->get('Version')
			);

			// 2. Sync Customizer "Additional CSS" to Editor
			$custom_css = wp_get_custom_css();
			if ( ! empty( $custom_css ) ) {
				// Escape CSS late.
				wp_add_inline_style( 'utilgp-utility-editor', wp_strip_all_tags( $custom_css ) );
			}
		}

		// 3. Always inject CSS variables to editor (regardless of editor-sync toggle)
		// This makes var(--slug) work in GenerateBlocks for gradients and variant colors
		$css_vars = $this->build_css_variables_string();
		if ( ! empty( $css_vars ) ) {
			// Escape CSS late.
			wp_add_inline_style( 'utilgp-utility-editor', wp_strip_all_tags( $css_vars ) );
		}

		// 4. Inject gradient utility classes to editor (enables .has-[slug]-gradient-text live preview)
		$gradient_utils = UTILGP_Gradient_Module::build_utility_css( 'editor' );
		if ( ! empty( $gradient_utils ) ) {
			// Escape CSS late.
			wp_add_inline_style( 'utilgp-utility-editor', wp_strip_all_tags( $gradient_utils ) );
		}
	}

	/**
	 * Build the full :root CSS variable string for expanded colors and gradients.
	 * Used by both the block editor and frontend head injection.
	 */
	public function build_css_variables_string() {
		$lines = array();

		// Expanded color variants (--slug-10, --slug-50, etc.)
		$expanded = get_option( 'utilgp_expanded_colors', array() );
		if ( is_array( $expanded ) ) {
			foreach ( $expanded as $set ) {
				if ( empty( $set['variables'] ) ) continue;
				foreach ( $set['variables'] as $var => $hex ) {
					$lines[] = "\t" . esc_html( $var ) . ': ' . esc_attr( $hex ) . ';';
				}
			}
		}

		// Gradient variables
		$gradients = get_option( 'utilgp_gradient_variables', array() );
		if ( is_array( $gradients ) ) {
			foreach ( $gradients as $g ) {
				$slug = $g['slug'] ?? '';
				if ( empty( $slug ) || empty( $g['stops'] ) ) continue;
				$css_val = UTILGP_Gradient_Module::build_gradient_css( $g );
				if ( $css_val ) {
					$lines[] = "\t--" . esc_html( $slug ) . ": " . esc_attr( $css_val ) . ';';
				}
			}
		}

		if ( empty( $lines ) ) return '';

		return ":root {\n" . implode( "\n", $lines ) . "\n}";
	}
}
