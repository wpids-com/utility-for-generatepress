<?php
/**
 * Dark Mode Class.
 * Handles the logic for Dark/Light mode toggling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Dark_Mode {

		public function init() {
		// Customizer integration
		add_action( 'customize_register', array( $this, 'register_customizer' ), 999 );
		
		// Setup frontend logic (only if Dark Mode option is active)
		add_action( 'wp', array( $this, 'setup_frontend_hooks' ) );
		
		// Enqueue Dark Mode CSS and JS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
		
		// Customizer UI Restrictions (Lock GP React Control)
		add_action( 'customize_controls_print_styles', array( $this, 'customizer_ui_restrictions' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customizer_ui_js_restrictions' ) );

		// Auto-sync data structural array
		add_filter( 'theme_mod_utilfoge_dark_global_colors', array( $this, 'sync_dark_colors_with_gp' ) );

		// Logo Swapping Filters
		add_filter( 'generate_logo_output', array( $this, 'filter_logo_output' ), 10, 3 );
		add_filter( 'generate_mobile_header_logo', array( $this, 'filter_mobile_header_logo' ), 10, 1 );
	}

	public function enqueue_frontend_assets() {
		// Only run if Dark Mode is enabled in Customizer
		if ( 'on' !== get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) ) {
			return;
		}

		// Pass PHP settings to the FOUC script via inline script (before FOUC JS loads)
		$default_mode = get_theme_mod( 'utilfoge_dark_mode_default', 'light' );
		$inline_config = 'window.utilfogeDarkConfig = ' . wp_json_encode(
			array(
				'defaultMode' => $default_mode, // 'user_choice' | 'dark' | 'light' | 'system'
			)
		) . ';';
		wp_register_script( 'utilfoge-dark-mode-config', '', array(), UTILFOGE_VERSION, false );
		wp_enqueue_script( 'utilfoge-dark-mode-config' );
		wp_add_inline_script( 'utilfoge-dark-mode-config', $inline_config );

		// Enqueue FOUC script at the top (after config)
		wp_enqueue_script(
			'utilfoge-dark-mode-fouc',
			UTILFOGE_PLUGIN_URL . 'assets/js/utilfoge-dark-mode-fouc.js',
			array( 'utilfoge-dark-mode-config' ),
			UTILFOGE_VERSION,
			false // In head
		);

		// Inject Dark Mode CSS
		$css = $this->get_dark_mode_css();
		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'utilfoge-utility-frontend', $css );
		}

		// Inject Toggle styles
		$toggle_css = $this->get_toggle_css();
		if ( ! empty( $toggle_css ) ) {
			wp_add_inline_style( 'utilfoge-utility-frontend', $toggle_css );
		}
	}

	/**
	 * Get Dark Mode CSS logic
	 */
	private function get_dark_mode_css() {
		$dark_colors = array();

		if ( class_exists( 'utilfoge_Color_Module' ) ) {
			$expanded = get_option( 'utilfoge_expanded_colors', array() );
			if ( ! empty( $expanded ) && is_array( $expanded ) ) {
				foreach ( $expanded as $set ) {
					if ( empty( $set['dark_counterparts'] ) ) continue;
					foreach ( $set['dark_counterparts'] as $slug => $hex ) {
						$dark_colors[ $slug ] = array(
							'slug'  => $slug,
							'color' => $hex,
						);
					}
				}
			}
		}

		$theme_mods_raw = get_option( 'theme_mods_' . get_option( 'stylesheet' ), array() );
		$db_saved       = isset( $theme_mods_raw['utilfoge_dark_global_colors'] ) ? $theme_mods_raw['utilfoge_dark_global_colors'] : array();
		if ( is_array( $db_saved ) && ! empty( $db_saved ) ) {
			foreach ( $db_saved as $dc ) {
				if ( empty( $dc['slug'] ) || empty( $dc['color'] ) ) continue;
				if ( ! isset( $dark_colors[ $dc['slug'] ] ) ) {
					$dark_colors[ $dc['slug'] ] = $dc;
				}
			}
		}

		if ( ! class_exists( 'utilfoge_Color_Module' ) || empty( $dark_colors ) ) {
			$defaults = array(
				'contrast'   => '#f9fafb',
				'contrast-2' => '#e5e7eb',
				'contrast-3' => '#9ca3af',
				'base'       => '#374151',
				'base-2'     => '#1f2937',
				'base-3'     => '#111827',
				'accent'     => '#60a5fa',
			);
			foreach ( $defaults as $slug => $hex ) {
				if ( ! isset( $dark_colors[ $slug ] ) ) {
					$dark_colors[ $slug ] = array( 'slug' => $slug, 'color' => $hex );
				}
			}
		}

		$map = array();
		foreach ( $dark_colors as $slug => $entry ) {
			if ( ! empty( $entry['color'] ) ) {
				$map[ $slug ] = $entry['color'];
			}
		}

		$base3     = isset( $map['base-3'] )     ? $map['base-3']     : '#111827';
		$base2     = isset( $map['base-2'] )     ? $map['base-2']     : '#1f2937';
		$base      = isset( $map['base'] )       ? $map['base']       : '#374151';
		$contrast  = isset( $map['contrast'] )   ? $map['contrast']   : '#f9fafb';
		$contrast2 = isset( $map['contrast-2'] ) ? $map['contrast-2'] : '#e5e7eb';
		$contrast3 = isset( $map['contrast-3'] ) ? $map['contrast-3'] : '#9ca3af';
		$accent    = isset( $map['accent'] )     ? $map['accent']     : '#60a5fa';

		$var_css = "";
		foreach ( $map as $slug => $hex ) {
			$var_css .= "\t\t\t--" . esc_attr( $slug ) . ": " . esc_attr( $hex ) . " !important;\n";
		}

		$css = "
			/* === LAYER 1: CSS Variable Override === */
			body.dark {
				$var_css
			}

			/* === LAYER 2: Structural Override with Static Hex === */
			body.dark,
			body.dark #page,
			body.dark .site-header,
			body.dark .inside-header,
			body.dark .main-navigation,
			body.dark .site-footer,
			body.dark .site-info,
			body.dark .inside-article,
			body.dark .page-hero,
			body.dark .sidebar .widget,
			body.dark .comments-area,
			body.dark .comment-body,
			body.dark .separate-containers .inside-article,
			body.dark .separate-containers .sidebar .widget,
			body.dark .separate-containers .page-header {
				background-color: " . esc_attr( $base3 ) . " !important;
				color: " . esc_attr( $contrast ) . " !important;
			}

			body.dark .site-content,
			body.dark #content {
				background-color: " . esc_attr( $base2 ) . " !important;
			}

			body.dark h1, body.dark h2, body.dark h3,
			body.dark h4, body.dark h5, body.dark h6,
			body.dark .widget-title, body.dark .entry-title,
			body.dark .entry-title a {
				color: " . esc_attr( $contrast ) . " !important;
			}

			body.dark .main-navigation .main-nav ul li a {
				color: " . esc_attr( $contrast ) . " !important;
			}

			body.dark a:not(#wpadminbar *):not(#wpadminbar) { color: " . esc_attr( $accent ) . " !important; }
			body.dark a:not(#wpadminbar *):not(#wpadminbar):hover { color: " . esc_attr( $contrast2 ) . " !important; }

			body.dark .entry-meta, body.dark .entry-meta a:not(#wpadminbar *):not(#wpadminbar),
			body.dark .cat-links, body.dark .tag-links {
				color: " . esc_attr( $contrast3 ) . " !important;
			}

			body.dark input:not(#wpadminbar *), body.dark textarea:not(#wpadminbar *), body.dark select:not(#wpadminbar *) {
				background-color: " . esc_attr( $base2 ) . " !important;
				color: " . esc_attr( $contrast ) . " !important;
				border-color: " . esc_attr( $contrast3 ) . " !important;
			}

			body.dark hr, body.dark .inside-article, body.dark .sidebar .widget {
				border-color: " . esc_attr( $base ) . " !important;
			}
		";

		return $css;
	}

	private function get_toggle_css() {
		return "
			/* === UTILFOGE Dark Mode Toggle === */
			.utilfoge-dark-mode-toggle {
				background: transparent !important;
				border: none !important;
				box-shadow: none !important;
				cursor: pointer;
				padding: 6px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				outline: none;
				color: #1f2937 !important;
				transition: color 0.3s ease;
				line-height: 1;
			}
			.utilfoge-dark-mode-toggle svg {
				width: 22px;
				height: 22px;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
				pointer-events: none;
			}
			.utilfoge-dark-mode-toggle svg.utilfoge-icon-moon {
				transform: scale(1.15);
				transform-origin: center;
			}
			body.dark .utilfoge-dark-mode-toggle {
				color: #f9fafb !important;
			}
			.utilfoge-dark-mode-toggle.style-outlined svg {
				fill: transparent;
				stroke: currentColor;
			}
			.utilfoge-dark-mode-toggle.style-monocolor svg {
				fill: currentColor;
				stroke: currentColor;
			}
			.utilfoge-dark-mode-toggle.style-multicolor svg.utilfoge-icon-sun {
				fill: #fbbf24;
				stroke: #f59e0b;
			}
			.utilfoge-dark-mode-toggle.style-multicolor svg.utilfoge-icon-moon {
				fill: #818cf8;
				stroke: #6366f1;
			}
			@keyframes UTILFOGESpinSun { 0% { transform: rotate(0deg) scale(1); } 100% { transform: rotate(45deg) scale(1.1); } }
			@keyframes UTILFOGESwingMoon { 0% { transform: rotate(0deg) scale(1.15); } 100% { transform: rotate(-15deg) scale(1.25); } }
			.utilfoge-dark-mode-toggle:hover svg.utilfoge-icon-sun { animation: UTILFOGESpinSun 0.4s ease forwards; }
			.utilfoge-dark-mode-toggle:hover svg.utilfoge-icon-moon { animation: UTILFOGESwingMoon 0.4s ease forwards; }

			/* Floating style base */
			.utilfoge-dark-mode-toggle.is-floating {
				position: fixed;
				z-index: 99999;
				background-color: #ffffff !important;
				color: #1f2937 !important;
				border-radius: 50% !important;
				width: 50px;
				height: 50px;
				box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
				transition: transform 0.3s ease, background-color 0.3s ease, color 0.3s ease;
			}
			.utilfoge-dark-mode-toggle.is-floating-right {
				bottom: 25px;
				right: 25px;
			}
			.utilfoge-dark-mode-toggle.is-floating-left {
				bottom: 25px;
				left: 25px;
			}
			.utilfoge-dark-mode-toggle.is-floating:hover {
				transform: translateY(-3px);
				box-shadow: 0 6px 20px rgba(0,0,0,0.25) !important;
			}
			body.dark .utilfoge-dark-mode-toggle.is-floating {
				background-color: #1f2937 !important;
				color: #f9fafb !important;
			}

			@media (max-width: 768px) {
				.utilfoge-dark-mode-toggle.is-floating-right {
					bottom: 15px;
					right: 15px;
				}
				.utilfoge-dark-mode-toggle.is-floating-left {
					bottom: 15px;
					left: 15px;
				}
			}

			/* --- Mobile Menu Integration (Non-Floating) --- */
			.utilfoge-dark-toggle-nav-wrapper {
				display: inline-flex;
				align-items: center;
			}
			/* GP menu-bar-items will natively handle the placement left of the burger icon on mobile */

			/* --- Dark Mode Logo Swaps --- */
			img.dark-mode-logo {
				display: none !important;
			}
			body.dark img.dark-mode-logo {
				display: inline-block !important;
			}
			body.dark img.header-image:not(.dark-mode-logo),
			body.dark img.mobile-logo:not(.dark-mode-logo),
			body.dark .site-logo img:not(.dark-mode-logo),
			body.dark .mobile-header-logo img:not(.dark-mode-logo) {
				display: none !important;
			}
		";
	}

	public function customizer_ui_restrictions() {
		$css = "
			#customize-control-utilfoge_dark_global_colors .generate-color-manager--add-color,
			#customize-control-utilfoge_dark_global_colors .generate-color-manager--remove,
			#customize-control-utilfoge_dark_global_colors button[aria-label*='Lock'],
			#customize-control-utilfoge_dark_global_colors button[aria-label*='Unlock'],
			#customize-control-utilfoge_dark_global_colors .generate-color-input--css-var-name-wrapper button {
				display: none !important;
			}
		";
		?>
		<style id="utilfoge-dark-mode-customizer-css">
			<?php echo esc_html( $css ); ?>
		</style>
		<?php
	}

	public function customizer_ui_js_restrictions() {
		?>
		<script id="utilfoge-dark-mode-customizer-js">
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof wp !== 'undefined' && wp.customize) {
					wp.customize.bind('ready', function() {
						var gpColorsSetting = wp.customize('generate_settings[global_colors]');
						var darkColorsSetting = wp.customize('utilfoge_dark_global_colors');
						
						if (gpColorsSetting && darkColorsSetting) {
							var syncColors = function() {
								var gpColors = gpColorsSetting.get() || [];
								var darkColors = darkColorsSetting.get() || [];
								var updated = false;
								
								if (!Array.isArray(darkColors)) darkColors = [];
								if (!Array.isArray(gpColors)) gpColors = [];
								
								var newDarkColors = [];
								
								gpColors.forEach(function(gpColor) {
									if (!gpColor.slug) return;
									
									var existingDark = darkColors.find(function(dc) { return dc.slug === gpColor.slug; });
									
									if (existingDark) {
										if (existingDark.name !== gpColor.name) {
											existingDark.name = gpColor.name;
											updated = true;
										}
										newDarkColors.push(existingDark);
									} else {
										newDarkColors.push({
											name: gpColor.name,
											slug: gpColor.slug,
											color: gpColor.color
										});
										updated = true;
									}
								});
								
								if (darkColors.length !== newDarkColors.length) {
									updated = true;
								}
								
								if (updated) {
									darkColorsSetting.set(newDarkColors);
								}
							};
							
							gpColorsSetting.bind(syncColors);
							setTimeout(syncColors, 500); 
						}
					});
				}

				setInterval(function() {
					var container = document.getElementById('customize-control-utilfoge_dark_global_colors');
					if (!container) return;

					var inputs = container.querySelectorAll('.generate-color-input--css-var-name-wrapper');
					inputs.forEach(function(input) {
						if (!input.disabled) {
							input.disabled = true;
							input.style.opacity = '0.6';
						}
					});

					var buttons = container.querySelectorAll('.components-button, .generate-color-manager--delete-color');
					buttons.forEach(function(btn) {
						var svg = btn.querySelector('svg');
						if (svg && btn.getAttribute('aria-label') && (btn.getAttribute('aria-label').indexOf('Lock') !== -1 || btn.getAttribute('aria-label').indexOf('Unlock') !== -1)) {
							btn.style.display = 'none';
						}
						if (btn.classList.contains('generate-color-manager--delete-color')) {
							btn.style.display = 'none';
						}
					});
				}, 1000);
			});
		</script>
		<?php
	}

	public function setup_frontend_hooks() {
		if ( 'on' !== get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) ) {
			return;
		}

		$position = get_theme_mod( 'utilfoge_dark_mode_position', 'after_nav' );
		
		if ( 'custom' === $position ) {
			return; // Do nothing. Let the user use the shortcode manually.
		} elseif ( 'floating' === $position ) {
			add_action( 'wp_footer', array( __CLASS__, 'render_toggle_hook' ) );
		} else {
			// Native GP Flexbox integration: menu_bar_items perfectly handles desktop alignment 
			// and automatically places the item left of the burger icon on mobile.
			if ( function_exists( 'generate_is_using_flexbox' ) && generate_is_using_flexbox() ) {
				add_action( 'generate_menu_bar_items', array( __CLASS__, 'render_toggle_hook_menu_bar_item' ) );
			} else {
				// Fallback for legacy float structure
				if ( 'inside_nav' === $position ) {
					add_action( 'generate_inside_navigation', array( __CLASS__, 'render_toggle_hook' ) );
				} else {
					add_action( 'generate_after_navigation', array( __CLASS__, 'render_toggle_hook' ) );
				}
			}
		}
	}

	public static function render_toggle_hook_menu_bar_item() {
		echo '<span class="menu-bar-item utilfoge-dark-toggle-menu-item">';
		self::render_toggle_hook();
		echo '</span>';
	}

	public static function render_toggle_hook() {
		echo wp_kses( 
			self::render_toggle(), 
			array(
				'span'   => array(
					'class' => array(),
				),
				'div'    => array(
					'class' => array(),
				),
				'button' => array(
					'type'       => array(),
					'class'      => array(),
					'aria-label' => array(),
				),
				'svg'    => array(
					'class'   => array(),
					'viewbox' => array(),
					'xmlns'   => array(),
					'style'   => array(),
				),
				'path'   => array(
					'd' => array(),
				),
			)
		);
	}

	public function register_customizer( $wp_customize ) {
		$wp_customize->add_section(
			'utilfoge_dark_mode_section',
			array(
				'title'    => 'Dark Mode',
				'panel'    => 'utilfoge_utility_panel',
				'priority' => 40,
			)
		);

		$wp_customize->add_setting(
			'utilfoge_dark_mode_enable',
			array(
				'default'   => 'off',
				'transport' => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_enable_control',
			array(
				'label'   => __( 'Dark Mode', 'utility-for-generatepress' ),
				'section' => 'utilfoge_dark_mode_section',
				'settings'=> 'utilfoge_dark_mode_enable',
				'type'    => 'select',
				'choices' => array(
					'off' => __( 'Off', 'utility-for-generatepress' ),
					'on'  => __( 'On', 'utility-for-generatepress' ),
				),
			)
		);

		$wp_customize->add_setting(
			'utilfoge_dark_mode_position',
			array(
				'default'   => 'after_nav',
				'transport' => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_position_control',
			array(
				'label'       => __( 'Toggle Position', 'utility-for-generatepress' ),
				'section'     => 'utilfoge_dark_mode_section',
				'settings'    => 'utilfoge_dark_mode_position',
				'type'        => 'select',
				'choices'     => array(
					'after_nav'     => __( 'After Navigation', 'utility-for-generatepress' ),
					'inside_nav'    => __( 'Inside Navigation', 'utility-for-generatepress' ),
					'floating'      => __( 'Floating (Stand-alone)', 'utility-for-generatepress' ),
					'custom'        => __( 'Custom (Shortcode)', 'utility-for-generatepress' ),
				),
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		// --- Custom Shortcode Notice Control ---
		$wp_customize->add_setting( 'utilfoge_dark_mode_custom_notice_setting', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'utilfoge_dark_mode_custom_notice_control',
			array(
				'label'       => '',
				'section'     => 'utilfoge_dark_mode_section',
				'settings'    => 'utilfoge_dark_mode_custom_notice_setting',
				'type'        => 'text',
				'description' => '<div style="padding: 12px; background: #e0f2fe; border-left: 4px solid #0284c7; color: #0c4a6e; font-size: 13px; margin-top: -10px; border-radius: 2px;">' . __( 'Use the shortcode <strong>[utilfoge_dark_mode_toggle]</strong> anywhere on your site, including GeneratePress Elements (GP Element) or widgets.', 'utility-for-generatepress' ) . '</div>',
				'input_attrs' => array(
					'style' => 'display:none;',
				),
				'active_callback' => function() {
					return get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) === 'on' && get_theme_mod( 'utilfoge_dark_mode_position', 'after_nav' ) === 'custom';
				},
			)
		);

		// --- Floating Alignment ---
		$wp_customize->add_setting(
			'utilfoge_dark_mode_floating_align',
			array(
				'default'           => 'left',
				'transport'         => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_floating_align_control',
			array(
				'label'       => __( 'Floating Alignment', 'utility-for-generatepress' ),
				'section'     => 'utilfoge_dark_mode_section',
				'settings'    => 'utilfoge_dark_mode_floating_align',
				'type'        => 'select',
				'choices'     => array(
					'left'  => __( 'Left', 'utility-for-generatepress' ),
					'right' => __( 'Right', 'utility-for-generatepress' ),
				),
				'active_callback' => function() {
					return get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) === 'on' && get_theme_mod( 'utilfoge_dark_mode_position', 'after_nav' ) === 'floating';
				},
			)
		);

		// --- Setting: Default Mode ---
		$wp_customize->add_setting(
			'utilfoge_dark_mode_default',
			array(
				'default'           => 'light',
				'transport'         => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_default_control',
			array(
				'label'       => __( 'Default Mode', 'utility-for-generatepress' ),
				/* translators: Explanation for Default Mode setting choices. */
				'description' => __( 'Set the initial mode when a visitor first opens the site.', 'utility-for-generatepress' ),
				'section'     => 'utilfoge_dark_mode_section',
				'settings'    => 'utilfoge_dark_mode_default',
				'type'        => 'select',
				'choices'     => array(
					'light'       => __( 'Default (Light Mode)', 'utility-for-generatepress' ),
					'dark'        => __( 'Dark Mode', 'utility-for-generatepress' ),
					'system'      => __( 'OS System Preference', 'utility-for-generatepress' ),
				),
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		$wp_customize->add_setting(
			'utilfoge_dark_mode_icon',
			array(
				'default'   => 'outlined',
				'transport' => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_icon_control',
			array(
				'label'   => __( 'Toggle Icon', 'utility-for-generatepress' ),
				'section' => 'utilfoge_dark_mode_section',
				'settings'=> 'utilfoge_dark_mode_icon',
				'type'    => 'select',
				'choices' => array(
					'outlined'   => __( 'Outlined', 'utility-for-generatepress' ),
					'monocolor'  => __( 'Monocolor', 'utility-for-generatepress' ),
					'multicolor' => __( 'Multicolor', 'utility-for-generatepress' ),
				),
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		// --- Dark Mode Logo Enable ---
		$wp_customize->add_setting(
			'utilfoge_dark_mode_logo_enable',
			array(
				'default'           => 'off',
				'transport'         => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'utilfoge_dark_mode_logo_enable_control',
			array(
				'label'    => __( 'Dark Mode Logo', 'utility-for-generatepress' ),
				'section'  => 'utilfoge_dark_mode_section',
				'settings' => 'utilfoge_dark_mode_logo_enable',
				'type'     => 'select',
				'choices'  => array(
					'off' => __( 'Off', 'utility-for-generatepress' ),
					'on'  => __( 'On', 'utility-for-generatepress' ),
				),
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		// --- Dark Mode Logo Upload ---
		$wp_customize->add_setting(
			'utilfoge_dark_mode_logo',
			array(
				'default'           => '',
				'transport'         => 'refresh',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'utilfoge_dark_mode_logo_control',
				array(
					'label'      => __( 'Dark Mode Logo (Desktop)', 'utility-for-generatepress' ),
					'section'    => 'utilfoge_dark_mode_section',
					'settings'   => 'utilfoge_dark_mode_logo',
					'active_callback' => function() {
						return get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) === 'on' && get_theme_mod( 'utilfoge_dark_mode_logo_enable', 'off' ) === 'on';
					},
				)
			)
		);

		// --- Dark Mode Logo for Mobile Upload ---
		$wp_customize->add_setting(
			'utilfoge_dark_mode_mobile_logo',
			array(
				'default'           => '',
				'transport'         => 'refresh',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'utilfoge_dark_mode_mobile_logo_control',
				array(
					'label'      => __( 'Dark Mode Logo for Mobile', 'utility-for-generatepress' ),
					'section'    => 'utilfoge_dark_mode_section',
					'settings'   => 'utilfoge_dark_mode_mobile_logo',
					'active_callback' => function() {
						return get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) === 'on' && get_theme_mod( 'utilfoge_dark_mode_logo_enable', 'off' ) === 'on' && $this->is_mobile_header_enabled();
					},
				)
			)
		);

		if ( class_exists( 'GeneratePress_Customize_Field' ) ) {
			GeneratePress_Customize_Field::add_title(
				'utilfoge_dark_global_colors_title',
				array(
					'section' => 'utilfoge_dark_mode_section',
					'title' => __( 'Dark Global Colors', 'utility-for-generatepress' ),
				)
			);

			$default_dark_colors = array();
			$gp_settings = get_option( 'generate_settings', array() );
			$gp_colors = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();
			
			$dark_fallbacks = array(
				'contrast'   => '#f9fafb',
				'contrast-2' => '#e5e7eb',
				'contrast-3' => '#9ca3af',
				'base'       => '#374151',
				'base-2'     => '#1f2937',
				'base-3'     => '#111827',
				'accent'     => '#60a5fa',
			);

			if ( is_array( $gp_colors ) ) {
				foreach ( $gp_colors as $gpc ) {
					if ( empty( $gpc['slug'] ) ) continue;
					$slug = $gpc['slug'];
					$default_dark_colors[] = array(
						'name'  => $gpc['name'],
						'slug'  => $slug,
						'color' => isset( $dark_fallbacks[ $slug ] ) ? $dark_fallbacks[ $slug ] : $gpc['color'],
					);
				}
			}

			GeneratePress_Customize_Field::add_field(
				'utilfoge_dark_global_colors',
				'GeneratePress_Customize_React_Control',
				array(
					'default' => $default_dark_colors,
					'sanitize_callback' => function( $colors ) {
						if ( ! is_array( $colors ) ) { return; }
						$new_settings = array();
						foreach ( (array) $colors as $key => $data ) {
							if ( empty( $data['slug'] ) || empty( $data['color'] ) ) { continue; }
							$slug = preg_replace( '/[^a-z0-9-\s]+/i', '', $data['slug'] );
							$slug = strtolower( $slug );
							$new_settings[ $key ]['name'] = sanitize_text_field( $slug );
							$new_settings[ $key ]['slug'] = sanitize_text_field( $slug );
							$new_settings[ $key ]['color'] = function_exists('generate_sanitize_rgba_color') ? generate_sanitize_rgba_color( $data['color'] ) : sanitize_text_field( $data['color'] );
						}
						return array_values( $new_settings );
					},
					'transport' => 'postMessage',
				),
				array(
					'type' => 'generate-color-manager-control',
					'label' => __( 'Choose Color', 'utility-for-generatepress' ),
					'section' => 'utilfoge_dark_mode_section',
					'choices' => array(
						'alpha' => true,
						'showPalette' => false,
						'showReset' => false,
						'showVarName' => true,
					),
				)
			);
		}
	}

	public function is_dark_mode_enabled( $control ) {
		return 'on' === $control->manager->get_setting( 'utilfoge_dark_mode_enable' )->value();
	}

	public static function render_toggle() {
		$icon_style = get_theme_mod( 'utilfoge_dark_mode_icon', 'outlined' );
		$position   = get_theme_mod( 'utilfoge_dark_mode_position', 'after_nav' );

		$btn_classes = 'utilfoge-dark-mode-toggle style-' . esc_attr( $icon_style );
		if ( 'floating' === $position ) {
			$align = get_theme_mod( 'utilfoge_dark_mode_floating_align', 'left' );
			$btn_classes .= ' is-floating is-floating-' . esc_attr( $align );
		}

		// Untuk non-floating, bungkus dalam wrapper agar posisi flex bisa diatur
		$use_wrapper    = ( 'floating' !== $position );
		$wrapper_class  = 'utilfoge-dark-toggle-nav-wrapper';

		ob_start();
		if ( $use_wrapper ) : ?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
		<?php endif; ?>
		<button type="button" class="<?php echo esc_attr( $btn_classes ); ?>" aria-label="<?php esc_attr_e( 'Toggle Dark Mode', 'utility-for-generatepress' ); ?>">
			<svg class="utilfoge-icon-sun" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
			<svg class="utilfoge-icon-moon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="display:none;"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
		</button>
		<?php if ( $use_wrapper ) : ?>
		</div>
		<?php endif;
		return ob_get_clean();
	}
	/**
	 * Sync Dark Mode global colors with GeneratePress Global Colors.
	 * 
	 * This ensures that if a color is added/removed in GP, 
	 * it is reflected in the Dark Mode settings.
	 *
	 * @param mixed $value The theme_mod value.
	 * @return array
	 */
	public function sync_dark_colors_with_gp( $value ) {
		$gp_settings = get_option( 'generate_settings', array() );
		$gp_colors   = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();

		if ( ! is_array( $value ) ) {
			$value = array();
		}

		if ( empty( $gp_colors ) ) {
			return $value;
		}

		$current_dark_map = array();
		foreach ( $value as $index => $color_data ) {
			if ( isset( $color_data['slug'] ) ) {
				$current_dark_map[ $color_data['slug'] ] = $index;
			}
		}

		$new_value = array();
		foreach ( $gp_colors as $gp_color ) {
			if ( empty( $gp_color['slug'] ) ) {
				continue;
			}

			$slug = $gp_color['slug'];

			if ( isset( $current_dark_map[ $slug ] ) ) {
				// Keep existing dark color
				$new_value[] = $value[ $current_dark_map[ $slug ] ];
			} else {
				// Add new color from GP with its default color as dark counterpart
				$new_value[] = array(
					'name'  => isset( $gp_color['name'] ) ? $gp_color['name'] : $slug,
					'slug'  => $slug,
					'color' => isset( $gp_color['color'] ) ? $gp_color['color'] : '',
				);
			}
		}

		return $new_value;
	}

	/**
	 * Check if GP Premium mobile header is enabled.
	 *
	 * @return bool
	 */
	public function is_mobile_header_enabled() {
		if ( ! function_exists( 'generate_menu_plus_get_defaults' ) ) {
			return false;
		}
		$menu_plus_settings = get_option( 'generate_menu_plus_settings', array() );
		return isset( $menu_plus_settings['mobile_header'] ) && 'enable' === $menu_plus_settings['mobile_header'];
	}

	/**
	 * Append dark mode logo to main theme logo HTML.
	 *
	 * @param string $logo_html The default logo output HTML.
	 * @param string $logo_url The logo link URL.
	 * @param string $html_attr Logo image tag HTML attributes.
	 * @return string
	 */
	public function filter_logo_output( $logo_html, $logo_url, $html_attr ) {
		if ( 'on' !== get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) || 'on' !== get_theme_mod( 'utilfoge_dark_mode_logo_enable', 'off' ) ) {
			return $logo_html;
		}
		$dark_logo = get_theme_mod( 'utilfoge_dark_mode_logo', '' );
		if ( empty( $dark_logo ) ) {
			return $logo_html;
		}

		$dark_img = sprintf(
			'<img class="header-image is-logo-image dark-mode-logo" src="%1$s" alt="%2$s" />',
			esc_url( $dark_logo ),
			esc_attr( get_bloginfo( 'name', 'display' ) )
		);

		if ( strpos( $logo_html, '</a>' ) !== false ) {
			$logo_html = str_replace( '</a>', $dark_img . '</a>', $logo_html );
		} else {
			$logo_html .= $dark_img;
		}

		return $logo_html;
	}

	/**
	 * Append dark mode mobile logo to mobile header logo HTML.
	 *
	 * @param string $logo_html The default mobile logo HTML.
	 * @return string
	 */
	public function filter_mobile_header_logo( $logo_html ) {
		if ( 'on' !== get_theme_mod( 'utilfoge_dark_mode_enable', 'off' ) || 'on' !== get_theme_mod( 'utilfoge_dark_mode_logo_enable', 'off' ) ) {
			return $logo_html;
		}
		$dark_logo = get_theme_mod( 'utilfoge_dark_mode_mobile_logo', '' );
		if ( empty( $dark_logo ) ) {
			return $logo_html;
		}

		$dark_img = sprintf(
			'<img class="mobile-logo dark-mode-logo" src="%1$s" alt="%2$s" />',
			esc_url( $dark_logo ),
			esc_attr( get_bloginfo( 'name', 'display' ) )
		);

		if ( strpos( $logo_html, '</a>' ) !== false ) {
			$logo_html = str_replace( '</a>', $dark_img . '</a>', $logo_html );
		} else {
			$logo_html .= $dark_img;
		}

		return $logo_html;
	}
}

// Register shortcode for the toggle
add_shortcode( 'utilfoge_dark_mode_toggle', array( 'utilfoge_Dark_Mode', 'render_toggle' ) );

