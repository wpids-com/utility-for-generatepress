<?php
/**
 * Core Class of the WPIDS Utility Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Utility_Core {

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
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-color-math.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-color-module.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-gradient-module.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-color-manager.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-typography.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-dark-mode.php';
		require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-settings.php';
	}

	/**
	 * Run the plugin logic.
	 */
	public function run() {
		$settings = new WPIDS_Settings();
		$settings->init();

		$options = get_option( 'wpids_utility_options' );
		// ── 4 Core Modules ──────────────────────────────────────────
		// 1. Color Management (import, expansion, harmony variants)
		$is_color_management = isset( $options['enable_color_manager'] ) ? $options['enable_color_manager'] : true;
		if ( $is_color_management ) {
			$color_manager = new WPIDS_Color_Manager();
			$color_manager->init();

			$color_module = new WPIDS_Color_Module();
			$color_module->init();
		}

		// 2. Gradient Palette (independent — theme.json native + utility classes)
		$is_gradient_palette = isset( $options['enable_gradient_palette'] ) ? $options['enable_gradient_palette'] : true;
		if ( $is_gradient_palette ) {
			$gradient_module = new WPIDS_Gradient_Module();
			$gradient_module->init();
		}

		// 2. Fluid Typography
		$is_typography = isset( $options['enable_typography'] ) ? $options['enable_typography'] : true;
		if ( $is_typography ) {
			$typography = new WPIDS_Typography();
			$typography->init();
		}

		// 3. Dark Mode
		$is_dark_mode = isset( $options['enable_dark_mode'] ) ? $options['enable_dark_mode'] : true;
		if ( $is_dark_mode ) {
			$dark_mode = new WPIDS_Dark_Mode();
			$dark_mode->init();
		}

		// 4. Editor CSS Sync is handled via enqueue_editor_assets() below

		// Enqueue global assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// Inject advanced raw CSS (Frontend Only)
		add_action( 'wp_head', array( $this, 'inject_raw_css' ), 99 );

		// Always register the Utility panel in Customizer (independent of modules)
		add_action( 'customize_register', array( $this, 'register_customizer_panel' ), 990 );

		// Add Settings link in Plugins list
		add_filter( 'plugin_action_links_' . plugin_basename( WPIDS_UTILITY_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Enqueue Admin & Customizer Assets.
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'wpids-admin-common',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-admin-common.css',
			array(),
			WPIDS_UTILITY_VERSION
		);
	}

	/**
	 * Always register the Utility Panel in Customizer.
	 * This ensures the panel appears regardless of which modules are active.
	 */
	public function register_customizer_panel( $wp_customize ) {
		$wp_customize->add_panel(
			'wpids_utility_panel',
			array(
				'title'    => __( 'Utility', 'generatepress-utility' ),
				'priority' => 30,
			)
		);
	}

	/**
	 * Add Configure link to the Plugins list page.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'themes.php?page=wpids-utility' ) ) . '">' . esc_html__( 'Configure', 'generatepress-utility' ) . '</a>';
		$links[] = $settings_link; // Append to the right of Deactivate
		return $links;
	}

	public function inject_raw_css() {
		$options = get_option( 'wpids_utility_options', array() );
		$is_editor_sync = isset( $options['enable_editor_sync'] ) ? $options['enable_editor_sync'] : true;

		if ( ! $is_editor_sync ) {
			return;
		}

		$raw_css = get_option( 'wpids_utility_raw_css', '' );
		if ( ! empty( $raw_css ) ) {
			echo "<style id='wpids-utility-raw-css'>\n";
			echo wp_kses_post( wp_strip_all_tags( $raw_css ) );
			echo "\n</style>";
		}
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'wpids-utility-frontend',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-frontend.css',
			array(),
			WPIDS_UTILITY_VERSION,
			'all'
		);

		wp_enqueue_script(
			'wpids-utility-frontend-js',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/js/wpids-frontend.js',
			array(),
			WPIDS_UTILITY_VERSION,
			true // in footer
		);
	}

	/**
	 * Enqueue Editor Assets.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_style(
			'wpids-utility-editor',
			WPIDS_UTILITY_PLUGIN_URL . 'assets/css/wpids-editor.css',
			array(),
			WPIDS_UTILITY_VERSION,
			'all'
		);

		$options = get_option( 'wpids_utility_options' );
		$is_editor_sync = isset( $options['enable_editor_sync'] ) ? $options['enable_editor_sync'] : true;

		if ( $is_editor_sync ) {
			// 1. Sync Child Theme / Active Theme Stylesheet to Editor
			$theme = wp_get_theme();
			wp_enqueue_style(
				'wpids-theme-style-sync',
				get_stylesheet_uri(),
				array(),
				$theme->get('Version')
			);

			// 2. Sync Customizer "Additional CSS" to Editor
			$custom_css = wp_get_custom_css();
			if ( ! empty( $custom_css ) ) {
				wp_add_inline_style( 'wpids-utility-editor', $custom_css );
			}

			// 3. Sync "Custom CSS No Linter" to Editor (Lockdown to Editor Only)
			$raw_css = get_option( 'wpids_utility_raw_css', '' );
			if ( ! empty( $raw_css ) ) {
				wp_add_inline_style( 'wpids-utility-editor', wp_strip_all_tags( $raw_css ) );
			}
		}

		// 4. Always inject CSS variables to editor (regardless of editor-sync toggle)
		// This makes var(--slug) work in GenerateBlocks for gradients and variant colors
		$css_vars = $this->build_css_variables_string();
		if ( ! empty( $css_vars ) ) {
			wp_add_inline_style( 'wpids-utility-editor', $css_vars );
		}

		// 5. Inject gradient utility classes to editor (enables .has-[slug]-gradient-text live preview)
		$gradient_utils = WPIDS_Gradient_Module::build_utility_css( 'editor' );
		if ( ! empty( $gradient_utils ) ) {
			wp_add_inline_style( 'wpids-utility-editor', $gradient_utils );
		}
	}

	/**
	 * Build the full :root CSS variable string for expanded colors and gradients.
	 * Used by both the block editor and frontend head injection.
	 */
	public function build_css_variables_string() {
		$lines = array();

		// Expanded color variants (--slug-10, --slug-50, etc.)
		$expanded = get_option( 'wpids_expanded_colors', array() );
		if ( is_array( $expanded ) ) {
			foreach ( $expanded as $set ) {
				if ( empty( $set['variables'] ) ) continue;
				foreach ( $set['variables'] as $var => $hex ) {
					$lines[] = "\t" . esc_html( $var ) . ': ' . esc_html( $hex ) . ';';
				}
			}
		}

		// Gradient variables
		$gradients = get_option( 'wpids_gradient_variables', array() );
		if ( is_array( $gradients ) ) {
			foreach ( $gradients as $g ) {
				$slug = $g['slug'] ?? '';
				if ( empty( $slug ) || empty( $g['stops'] ) ) continue;
				$css_val = WPIDS_Gradient_Module::build_gradient_css( $g );
				if ( $css_val ) {
					$lines[] = "\t--" . esc_html( $slug ) . ": " . $css_val . ';';
				}
			}
		}

		if ( empty( $lines ) ) return '';

		return ":root {\n" . implode( "\n", $lines ) . "\n}";
	}
}
