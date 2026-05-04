<?php
/**
 * Dark Mode Class.
 * Handles the logic for Dark/Light mode toggling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Dark_Mode {

	public function init() {
		// Customizer integration
		add_action( 'customize_register', array( $this, 'register_customizer' ), 999 );
		
		// Setup frontend logic (hanya jika opsi Dark Mode aktif)
		add_action( 'wp', array( $this, 'setup_frontend_hooks' ) );
		
		// Injeksi CSS Dark Mode langsung dari sini — priority 9999 agar menang dari GP
		// Tidak bergantung pada Color Manager module
		add_action( 'wp_head', array( $this, 'inject_dark_mode_css' ), 9999 );
		
		// Customizer UI Restrictions (Lock GP React Control)
		add_action( 'customize_controls_print_styles', array( $this, 'customizer_ui_restrictions' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customizer_ui_js_restrictions' ) );

		// Sinkronisasi otomatis struktur data array
		add_filter( 'theme_mod_wpids_dark_global_colors', array( $this, 'sync_dark_colors_with_gp' ) );
	}


	public function sync_dark_colors_with_gp( $dark_colors ) {
		// Warna gelap default untuk variabel standar GP
		$dark_defaults = array(
			'contrast'   => '#f9fafb',
			'contrast-2' => '#e5e7eb',
			'contrast-3' => '#9ca3af',
			'base'       => '#374151',
			'base-2'     => '#1f2937',
			'base-3'     => '#111827',
			'accent'     => '#60a5fa',
		);

		// Mengambil daftar warna asli GP
		$gp_settings = get_option( 'generate_settings', array() );
		$gp_colors = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();
		
		if ( empty( $gp_colors ) ) {
			return $dark_colors;
		}

		if ( ! is_array( $dark_colors ) ) {
			$dark_colors = array();
		}

		$synced_colors = array();
		foreach ( $gp_colors as $gp_color ) {
			if ( empty( $gp_color['slug'] ) ) continue;
			
			$slug = $gp_color['slug'];
			
			// Cari warna dark yang sudah disimpan user
			$existing_dark = null;
			foreach ( $dark_colors as $dc ) {
				if ( isset( $dc['slug'] ) && $dc['slug'] === $slug ) {
					$existing_dark = $dc;
					break;
				}
			}

			if ( $existing_dark ) {
				// Paksa nama dan slug agar SELALU sama persis dengan GP
				$existing_dark['name'] = $gp_color['name'];
				$existing_dark['slug'] = $slug;
				$synced_colors[] = $existing_dark;
			} else {
				// Warna baru dari GP yang belum ada di dark mode:
				// Gunakan dark default jika ada, BUKAN warna light dari GP
				$fallback_color = isset( $dark_defaults[ $slug ] ) ? $dark_defaults[ $slug ] : $gp_color['color'];
				$synced_colors[] = array(
					'name'  => $gp_color['name'],
					'slug'  => $slug,
					'color' => $fallback_color,
				);
			}
		}

		return $synced_colors;
	}

	/**
	 * Injeksi CSS Dark Mode dengan dua lapisan:
	 * Layer 1: Override CSS Variables (untuk elemen yang pakai var())
	 * Layer 2: Override Hardcoded Hex (untuk elemen GP yang cetak warna statis)
	 */
	public function inject_dark_mode_css() {
		// Hanya jalankan jika Dark Mode aktif di Customizer
		if ( 'on' !== get_theme_mod( 'wpids_dark_mode_enable', 'off' ) ) {
			return;
		}

		$dark_colors = array();

		// ── Priority 1: Math-derived dark counterparts (Color Module active) ──
		// These are computed mathematically and always most accurate.
		// Only applied when both Color Management module is active.
		if ( class_exists( 'WPIDS_Color_Module' ) ) {
			$expanded = get_option( 'wpids_expanded_colors', array() );
			if ( ! empty( $expanded ) && is_array( $expanded ) ) {
				foreach ( $expanded as $set ) {
					if ( empty( $set['dark_counterparts'] ) ) continue;
					foreach ( $set['dark_counterparts'] as $slug => $hex ) {
						// dark_counterparts keyed by slug (e.g. 'accent' not '--accent')
						$dark_colors[ $slug ] = array(
							'slug'  => $slug,
							'color' => $hex,
						);
					}
				}
			}
		}

		// ── Priority 2: User-saved dark colors from Customizer (manual overrides) ──
		// Any slug manually saved by user overrides the math-derived value.
		$theme_mods_raw = get_option( 'theme_mods_' . get_option( 'stylesheet' ), array() );
		$db_saved       = isset( $theme_mods_raw['wpids_dark_global_colors'] ) ? $theme_mods_raw['wpids_dark_global_colors'] : array();
		if ( is_array( $db_saved ) && ! empty( $db_saved ) ) {
			foreach ( $db_saved as $dc ) {
				if ( empty( $dc['slug'] ) || empty( $dc['color'] ) ) continue;
				// Mark as manually overridden — won't be touched by auto-sync
				if ( ! isset( $dark_colors[ $dc['slug'] ] ) ) {
					$dark_colors[ $dc['slug'] ] = $dc;
				}
			}
		}

		// ── Priority 3: Hardcoded defaults for the 7 standard GP colors ──
		// Only applied if Color Module is NOT active or a slug has no math value.
		if ( ! class_exists( 'WPIDS_Color_Module' ) || empty( $dark_colors ) ) {
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

		// Build final flat color map from priority stack
		$map = array();
		foreach ( $dark_colors as $slug => $entry ) {
			if ( ! empty( $entry['color'] ) ) {
				$map[ $slug ] = $entry['color'];
			}
		}

		// Extract structural variables from the final priority-resolved map
		$base3     = isset( $map['base-3'] )     ? $map['base-3']     : '#111827';
		$base2     = isset( $map['base-2'] )     ? $map['base-2']     : '#1f2937';
		$base      = isset( $map['base'] )       ? $map['base']       : '#374151';
		$contrast  = isset( $map['contrast'] )   ? $map['contrast']   : '#f9fafb';
		$contrast2 = isset( $map['contrast-2'] ) ? $map['contrast-2'] : '#e5e7eb';
		$contrast3 = isset( $map['contrast-3'] ) ? $map['contrast-3'] : '#9ca3af';
		$accent    = isset( $map['accent'] )     ? $map['accent']     : '#60a5fa';

		// Build variable override string (Layer 1) — all colors in map get injected
		$var_css = '';
		foreach ( $map as $slug => $hex ) {
			$var_css .= "\t\t\t--" . esc_attr( $slug ) . ": " . esc_attr( $hex ) . " !important;\n";
		}

		?>
		<style id="wpids-dark-mode-css">
			/* === LAYER 1: CSS Variable Override === */
			/* Untuk elemen yang menggunakan var(--nama-warna) */
			body.dark {
<?php echo wp_kses_post( $var_css ); ?>
			}

			/* === LAYER 2: Structural Override dengan Hex Statis === */
			/* Untuk elemen GP yang TIDAK menggunakan var() */
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
				background-color: <?php echo esc_attr( $base3 ); ?> !important;
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			body.dark .site-content,
			body.dark #content {
				background-color: <?php echo esc_attr( $base2 ); ?> !important;
			}

			body.dark h1, body.dark h2, body.dark h3,
			body.dark h4, body.dark h5, body.dark h6,
			body.dark .widget-title, body.dark .entry-title,
			body.dark .entry-title a {
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			body.dark .main-navigation .main-nav ul li a {
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			body.dark a { color: <?php echo esc_attr( $accent ); ?> !important; }
			body.dark a:hover { color: <?php echo esc_attr( $contrast2 ); ?> !important; }

			body.dark .entry-meta, body.dark .entry-meta a,
			body.dark .cat-links, body.dark .tag-links {
				color: <?php echo esc_attr( $contrast3 ); ?> !important;
			}

			body.dark input, body.dark textarea, body.dark select {
				background-color: <?php echo esc_attr( $base2 ); ?> !important;
				color: <?php echo esc_attr( $contrast ); ?> !important;
				border-color: <?php echo esc_attr( $contrast3 ); ?> !important;
			}

			body.dark hr, body.dark .inside-article, body.dark .sidebar .widget {
				border-color: <?php echo esc_attr( $base ); ?> !important;
			}
		</style>
		<?php
	}

	public function customizer_ui_restrictions() {
		// CSS untuk menyembunyikan tombol Add Color
		?>
		<style>
			#customize-control-wpids_dark_global_colors .generate-color-manager--add-color,
			#customize-control-wpids_dark_global_colors .generate-color-manager--remove,
			#customize-control-wpids_dark_global_colors button[aria-label*="Lock"],
			#customize-control-wpids_dark_global_colors button[aria-label*="Unlock"],
			#customize-control-wpids_dark_global_colors .generate-color-input--css-var-name-wrapper button {
				display: none !important;
			}
		</style>
		<?php
	}

	public function customizer_ui_js_restrictions() {
		// JS untuk mematikan field input slug dan tombol kunci/gembok
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Sinkronisasi data real-time (Live Sync) dari GP ke Dark Mode di dalam browser!
				if (typeof wp !== 'undefined' && wp.customize) {
					wp.customize.bind('ready', function() {
						var gpColorsSetting = wp.customize('generate_settings[global_colors]');
						var darkColorsSetting = wp.customize('wpids_dark_global_colors');
						
						if (gpColorsSetting && darkColorsSetting) {
							var syncColors = function() {
								var gpColors = gpColorsSetting.get() || [];
								var darkColors = darkColorsSetting.get() || [];
								var updated = false;
								
								if (!Array.isArray(darkColors)) darkColors = [];
								if (!Array.isArray(gpColors)) gpColors = [];
								
								var newDarkColors = [];
								
								// Cocokkan warna Dark dengan GP
								gpColors.forEach(function(gpColor) {
									if (!gpColor.slug) return;
									
									var existingDark = darkColors.find(function(dc) { return dc.slug === gpColor.slug; });
									
									if (existingDark) {
										// Paksa nama dan slug agar selalu identik
										if (existingDark.name !== gpColor.name) {
											existingDark.name = gpColor.name;
											updated = true;
										}
										newDarkColors.push(existingDark);
									} else {
										// Ada warna baru di GP, otomatis tambahkan ke Dark
										newDarkColors.push({
											name: gpColor.name,
											slug: gpColor.slug,
											color: gpColor.color // Fallback ke nilai light
										});
										updated = true;
									}
								});
								
								// Jika jumlah warna berbeda (misal ada yang dihapus di GP), update juga
								if (darkColors.length !== newDarkColors.length) {
									updated = true;
								}
								
								if (updated) {
									darkColorsSetting.set(newDarkColors);
								}
							};
							
							// Trigger sync setiap kali user menambah/menghapus/mengedit warna di GP Global Colors!
							gpColorsSetting.bind(syncColors);
							// Jalankan sekali di awal untuk memastikan sinkronisasi langsung
							setTimeout(syncColors, 500); 
						}
					});
				}

				// Gunakan setInterval agar elemen React yang di-load asinkron tetap bisa ditangkap untuk UI Restrictions
				setInterval(function() {
					var container = document.getElementById('customize-control-wpids_dark_global_colors');
					if (!container) return;

					// Disable field "CSS Variable Name"
					var inputs = container.querySelectorAll('.generate-color-input--css-var-name-wrapper');
					inputs.forEach(function(input) {
						if (!input.disabled) {
							input.disabled = true;
							input.style.opacity = '0.6';
						}
					});

					// Sembunyikan tombol gembok (Lock/Unlock) dan tombol Delete secara paksa lewat JS
					var buttons = container.querySelectorAll('.components-button, .generate-color-manager--delete-color');
					buttons.forEach(function(btn) {
						var svg = btn.querySelector('svg');
						
						// Jika ini tombol gembok (punya icon lock/unlock)
						if (svg && btn.getAttribute('aria-label') && (btn.getAttribute('aria-label').indexOf('Lock') !== -1 || btn.getAttribute('aria-label').indexOf('Unlock') !== -1)) {
							btn.style.display = 'none';
						}
						
						// Jika ini tombol Delete (biasanya class .generate-color-manager--delete-color)
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
		// Jika Dark Mode "Off" di Customizer, hentikan eksekusi frontend (tema tetap default)
		if ( 'on' !== get_theme_mod( 'wpids_dark_mode_enable', 'off' ) ) {
			return;
		}

		// Inject script FOUC
		add_action( 'wp_head', array( $this, 'inject_fouc_script' ), 1 );

		// Eksekusi hook posisi toggle sesuai pilihan Customizer
		$position = get_theme_mod( 'wpids_dark_mode_position', 'after_nav' );
		if ( 'inside_nav' === $position ) {
			add_action( 'generate_inside_navigation', array( __CLASS__, 'render_toggle_hook' ) );
		} elseif ( 'floating' === $position ) {
			add_action( 'wp_footer', array( __CLASS__, 'render_toggle_hook' ) );
		} else {
			add_action( 'generate_after_navigation', array( __CLASS__, 'render_toggle_hook' ) );
		}
	}

	public static function render_toggle_hook() {
		echo wp_kses_post( WPIDS_Dark_Mode::render_toggle() );
	}

	public function register_customizer( $wp_customize ) {
		// Note: 'wpids_utility_panel' is registered by WPIDS_Utility_Core::register_customizer_panel()

		// Add Section inside 'Utility' panel
		$wp_customize->add_section(
			'wpids_dark_mode_section',
			array(
				'title'    => 'Dark Mode',
				'panel'    => 'wpids_utility_panel',
				'priority' => 40,
			)
		);

		// 2. Add 'Dark Mode' Enable Setting
		$wp_customize->add_setting(
			'wpids_dark_mode_enable',
			array(
				'default'   => 'off',
				'transport' => 'refresh', // Refresh preview otomatis
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'wpids_dark_mode_enable_control',
			array(
				'label'   => __( 'Dark Mode', 'generatepress-utility' ),
				'section' => 'wpids_dark_mode_section',
				'settings'=> 'wpids_dark_mode_enable',
				'type'    => 'select',
				'choices' => array(
					'off' => __( 'Off', 'generatepress-utility' ),
					'on'  => __( 'On', 'generatepress-utility' ),
				),
			)
		);

		// 3. Add 'Toggle Position' Setting
		$wp_customize->add_setting(
			'wpids_dark_mode_position',
			array(
				'default'   => 'after_nav',
				'transport' => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'wpids_dark_mode_position_control',
			array(
				'label'   => __( 'Toggle Position', 'generatepress-utility' ),
				'section' => 'wpids_dark_mode_section',
				'settings'=> 'wpids_dark_mode_position',
				'type'    => 'select',
				'choices' => array(
					'after_nav'  => __( 'After Navigation', 'generatepress-utility' ),
					'inside_nav' => __( 'Inside Navigation', 'generatepress-utility' ),
					'floating'   => __( 'Floating (Stand-alone)', 'generatepress-utility' ),
				),
				// Ajax/Dynamic show/hide berdasarkan opsi pertama
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		// 4. Add 'Toggle Icon' Setting
		$wp_customize->add_setting(
			'wpids_dark_mode_icon',
			array(
				'default'   => 'outlined',
				'transport' => 'refresh',
				'sanitize_callback' => 'sanitize_key',
			)
		);

		$wp_customize->add_control(
			'wpids_dark_mode_icon_control',
			array(
				'label'   => __( 'Toggle Icon', 'generatepress-utility' ),
				'section' => 'wpids_dark_mode_section',
				'settings'=> 'wpids_dark_mode_icon',
				'type'    => 'select',
				'choices' => array(
					'outlined'   => __( 'Outlined', 'generatepress-utility' ),
					'monocolor'  => __( 'Monocolor', 'generatepress-utility' ),
					'multicolor' => __( 'Multicolor', 'generatepress-utility' ),
				),
				// Ajax/Dynamic show/hide
				'active_callback' => array( $this, 'is_dark_mode_enabled' ),
			)
		);

		// Meniru persis arsitektur Global Colors dari GeneratePress 
		// menggunakan React Control bawaan GP
		if ( class_exists( 'GeneratePress_Customize_Field' ) ) {
			GeneratePress_Customize_Field::add_title(
				'wpids_dark_global_colors_title',
				array(
					'section' => 'wpids_dark_mode_section',
					'title' => __( 'Dark Global Colors', 'generatepress-utility' ),
				)
			);

			$default_dark_colors = array();
			$gp_settings = get_option( 'generate_settings', array() );
			$gp_colors = isset( $gp_settings['global_colors'] ) ? $gp_settings['global_colors'] : array();
			
			// Hardcoded fallbacks untuk warna bawaan agar langsung terlihat bagus
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

			// Data di bawah ini akan diisi secara dinamis melalui filter `theme_mod_wpids_dark_global_colors`
			GeneratePress_Customize_Field::add_field(
				'wpids_dark_global_colors',
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
					'label' => __( 'Choose Color', 'generatepress-utility' ),
					'section' => 'wpids_dark_mode_section',
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
		return 'on' === $control->manager->get_setting( 'wpids_dark_mode_enable' )->value();
	}

	public function inject_fouc_script() {
		?>
		<script>
			// FOUC Prevention & Logic
			(function() {
				var isDark = false;
				try {
					isDark = localStorage.getItem('wpids-dark-mode') === 'true' || (!('wpids-dark-mode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
				} catch(e) {}

				if (typeof window.wpidsIsDark === 'undefined') {
					window.wpidsIsDark = isDark;
				}

				if (window.wpidsIsDark) {
					document.documentElement.classList.add('dark');
					if (document.body) document.body.classList.add('dark');
				} else {
					document.documentElement.classList.remove('dark');
					if (document.body) document.body.classList.remove('dark');
				}
				
				// Event Delegation dengan CAPTURE PHASE (true)
				document.addEventListener('click', function(e) {
					var toggle = e.target.closest('.wpids-dark-mode-toggle');
					if (!toggle) return;
					e.preventDefault();
					e.stopPropagation(); // Hentikan event agar tidak memicu script lain
					
					var isCurrentlyDark = window.wpidsIsDark;
					var willBeDark = !isCurrentlyDark;
					
					// Update State
					window.wpidsIsDark = willBeDark;
					
					if (willBeDark) {
						document.documentElement.classList.add('dark');
						if (document.body) document.body.classList.add('dark');
						try { localStorage.setItem('wpids-dark-mode', 'true'); } catch(err){}
					} else {
						document.documentElement.classList.remove('dark');
						if (document.body) document.body.classList.remove('dark');
						try { localStorage.setItem('wpids-dark-mode', 'false'); } catch(err){}
					}
					
					// Update semua toggle di layar
					document.querySelectorAll('.wpids-dark-mode-toggle').forEach(function(t) {
						var sun = t.querySelector('.wpids-icon-sun');
						var moon = t.querySelector('.wpids-icon-moon');
						if (willBeDark) {
							if(sun) sun.style.display = 'none';
							if(moon) moon.style.display = 'block';
						} else {
							if(sun) sun.style.display = 'block';
							if(moon) moon.style.display = 'none';
						}
					});
				}, true);

				// Initial Sync saat DOM siap
				var initDarkToggle = function() {
					if (isDark && document.body && !document.body.classList.contains('dark')) {
						document.body.classList.add('dark');
					}
					
					var isDarkActive = document.documentElement.classList.contains('dark');
					document.querySelectorAll('.wpids-dark-mode-toggle').forEach(function(t) {
						var sun = t.querySelector('.wpids-icon-sun');
						var moon = t.querySelector('.wpids-icon-moon');
						if (isDarkActive) {
							if(sun) sun.style.display = 'none';
							if(moon) moon.style.display = 'block';
						} else {
							if(sun) sun.style.display = 'block';
							if(moon) moon.style.display = 'none';
						}
					});
				};

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', initDarkToggle);
				} else {
					initDarkToggle();
				}
			})();
		</script>
		<style>
			/* ===== WPIDS Dark Mode Toggle ===== */

			/* Base: tombol transparan, ikon pakai warna gelap eksplisit di light mode */
			.wpids-dark-mode-toggle {
				background: transparent !important;
				border: none !important;
				box-shadow: none !important;
				cursor: pointer;
				padding: 6px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				outline: none;
				/* Warna eksplisit — TIDAK pakai inherit agar tidak hilang di background apapun */
				color: #1f2937 !important;
				transition: color 0.3s ease;
			}
			.wpids-dark-mode-toggle svg {
				width: 22px;
				height: 22px;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
				pointer-events: none;
			}
			.wpids-dark-mode-toggle svg.wpids-icon-moon {
				transform: scale(1.15);
				transform-origin: center;
			}

			/* Saat Dark Mode aktif: ikon harus tetap kontras terhadap background gelap */
			body.dark .wpids-dark-mode-toggle {
				color: #f9fafb !important;
			}

			/* Outlined Style */
			.wpids-dark-mode-toggle.style-outlined svg {
				fill: transparent;
				stroke: currentColor;
			}
			
			/* Monocolor Style */
			.wpids-dark-mode-toggle.style-monocolor svg {
				fill: currentColor;
				stroke: currentColor;
			}
			
			/* Multicolor Style — warna tetap selalu, tidak bergantung pada mode */
			.wpids-dark-mode-toggle.style-multicolor svg.wpids-icon-sun {
				fill: #fbbf24;
				stroke: #f59e0b;
			}
			.wpids-dark-mode-toggle.style-multicolor svg.wpids-icon-moon {
				fill: #818cf8;
				stroke: #6366f1;
			}

			/* Keyframes Animations */
			@keyframes wpidsSpinSun { 0% { transform: rotate(0deg) scale(1); } 100% { transform: rotate(45deg) scale(1.1); } }
			@keyframes wpidsSwingMoon { 0% { transform: rotate(0deg) scale(1.15); } 100% { transform: rotate(-15deg) scale(1.25); } }
			
			/* Interactive Hover Animations */
			.wpids-dark-mode-toggle:hover svg.wpids-icon-sun { animation: wpidsSpinSun 0.4s ease forwards; }
			.wpids-dark-mode-toggle:hover svg.wpids-icon-moon { animation: wpidsSwingMoon 0.4s ease forwards; }

			/* Floating Position */
			.wpids-dark-mode-toggle.is-floating {
				position: fixed;
				bottom: 25px;
				right: 25px;
				z-index: 99999;
				background-color: #ffffff !important;
				color: #1f2937 !important;
				border-radius: 50% !important;
				width: 50px;
				height: 50px;
				box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
				transition: transform 0.3s ease, background-color 0.3s ease, color 0.3s ease;
			}
			.wpids-dark-mode-toggle.is-floating:hover {
				transform: translateY(-3px);
				box-shadow: 0 6px 20px rgba(0,0,0,0.25) !important;
			}
			body.dark .wpids-dark-mode-toggle.is-floating {
				background-color: #1f2937 !important;
				color: #f9fafb !important;
			}
		</style>
		<?php
	}

	/**
	 * Render a simple toggle button (can be used via shortcode or hook)
	 * For now, this is just a helper method.
	 */
	public static function render_toggle() {
		$icon_style = get_theme_mod( 'wpids_dark_mode_icon', 'outlined' );
		$position   = get_theme_mod( 'wpids_dark_mode_position', 'after_nav' );
		
		$classes = 'wpids-dark-mode-toggle style-' . esc_attr( $icon_style );
		if ( 'floating' === $position ) {
			$classes .= ' is-floating';
		}

		ob_start();
		?>
		<button type="button" class="<?php echo esc_attr( $classes ); ?>" aria-label="Toggle Dark Mode">
			<svg class="wpids-icon-sun" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
			<svg class="wpids-icon-moon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="display:none;"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
		</button>
		<?php
		return ob_get_clean();
	}
}

// Register shortcode for the toggle
add_shortcode( 'wpids_dark_mode_toggle', array( 'WPIDS_Dark_Mode', 'render_toggle' ) );
