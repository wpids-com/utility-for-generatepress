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
		// 1. Color Management (includes: Color Manager + Color Import/Expansion + Gradient Variables)
		$is_color_management = isset( $options['enable_color_manager'] ) ? $options['enable_color_manager'] : true;
		if ( $is_color_management ) {
			// CSS variable injection (legacy, also holds CSS Houdini @property for future)
			$color_manager = new WPIDS_Color_Manager();
			$color_manager->init();

			// Color Import, Expansion & Mapping
			$color_module = new WPIDS_Color_Module();
			$color_module->init();

			// Gradient Variables (sub-feature of Color Management)
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

	public function inject_raw_css() {
		$raw_css = get_option( 'wpids_utility_raw_css', '' );
		if ( ! empty( $raw_css ) ) {
			echo "<style id='wpids-utility-raw-css'>\n";
			echo wp_strip_all_tags( $raw_css );
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
				wp_add_inline_style( 'wpids-utility-editor', strip_tags( $raw_css ) );
			}
		}
	}
}
