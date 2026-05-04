<?php
/**
 * Settings Class.
 * Handles WP-Admin options page for modular toggles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Settings {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'handle_toggles' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'themes.php',
			esc_html__( 'GeneratePress Utility Settings', 'generatepress-utility' ),
			esc_html__( 'Utility', 'generatepress-utility' ),
			'manage_options',
			'wpids-utility',
			array( $this, 'render_settings_page' )
		);

		global $submenu;
		if ( isset( $submenu['themes.php'] ) ) {
			$themes_menu = $submenu['themes.php'];
			$utility_menu_key = null;
			$editor_menu_key  = null;

			foreach ( $themes_menu as $key => $item ) {
				if ( isset( $item[2] ) && $item[2] === 'wpids-utility' ) {
					$utility_menu_key = $key;
				}
				if ( isset( $item[2] ) && $item[2] === 'theme-editor.php' ) {
					$editor_menu_key = $key;
				}
			}

			if ( $utility_menu_key !== null && $editor_menu_key !== null ) {
				$utility_item = $themes_menu[ $utility_menu_key ];
				unset( $themes_menu[ $utility_menu_key ] );

				$new_themes_menu = array();
				foreach ( $themes_menu as $key => $item ) {
					if ( $key < $editor_menu_key ) {
						$new_themes_menu[ $key ] = $item;
					}
				}
				$new_themes_menu[ $editor_menu_key ] = $utility_item;
				foreach ( $themes_menu as $key => $item ) {
					if ( $key >= $editor_menu_key ) {
						$next_key = $key + 1;
						while ( isset( $new_themes_menu[ $next_key ] ) ) {
							$next_key++;
						}
						$new_themes_menu[ $next_key ] = $item;
					}
				}
				ksort( $new_themes_menu );
				$submenu['themes.php'] = $new_themes_menu;
			}
		}
	}

	public function register_settings() {
		register_setting( 
			'wpids_utility_settings_group', 
			'wpids_utility_raw_css',
			array(
				'sanitize_callback' => 'wp_strip_all_tags', // Wajib untuk keamanan
			)
		);
	}

	private function get_admin_primary_color() {
		global $_wp_admin_css_colors;
		$color_scheme = get_user_option( 'admin_color' );
		if ( empty( $color_scheme ) || ! isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
			$color_scheme = 'fresh';
		}
		
		if ( isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
			$colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
			if ( isset( $colors[2] ) ) {
				return $colors[2];
			}
		}
		return '#2271b1';
	}

	public function handle_toggles() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wpids-utility' && isset( $_GET['action'] ) && $_GET['action'] === 'toggle_module' && isset( $_GET['module'] ) && isset( $_GET['_wpnonce'] ) ) {
			// Unslash & Sanitize Nonce
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			
			if ( wp_verify_nonce( $nonce, 'wpids_toggle_module' ) ) {
				$module = sanitize_text_field( wp_unslash( $_GET['module'] ) );
				$options = get_option( 'wpids_utility_options', array() );
				$current_state = isset( $options[ $module ] ) ? $options[ $module ] : true;
				$options[ $module ] = ! $current_state;
				update_option( 'wpids_utility_options', $options );
				
				wp_safe_redirect( admin_url( 'themes.php?page=wpids-utility' ) );
				exit;
			}
		}
	}

	public function render_settings_page() {
		$options = get_option( 'wpids_utility_options', array() );
		
		$modules = array(
			'enable_color_manager' => array(
				'name' => __( 'Color Management', 'generatepress-utility' ),
				'desc' => __( 'Import colors, generate shades, tints, and harmony variants.', 'generatepress-utility' )
			),
			'enable_gradient_palette' => array(
				'name' => __( 'Gradient Palette', 'generatepress-utility' ),
				'desc' => __( 'Create gradient presets as native WordPress variables (--wp--preset--gradient--*).', 'generatepress-utility' )
			),
			'enable_typography' => array(
				'name' => __( 'Fluid Typography', 'generatepress-utility' ),
				'desc' => __( 'Responsive text sizing using clamp().', 'generatepress-utility' )
			),
			'enable_dark_mode' => array(
				'name' => __( 'Dark Mode', 'generatepress-utility' ),
				'desc' => __( 'Native Dark/Light Mode toggle.', 'generatepress-utility' )
			),
			'enable_editor_sync' => array(
				'name' => __( 'Editor CSS Sync', 'generatepress-utility' ),
				'desc' => __( 'Sync Customizer & Theme CSS to Gutenberg.', 'generatepress-utility' )
			),
		);
		?>
		<div class="wpids-dashboard-header">
			<div class="wpids-header-inner">
				<div class="wpids-header-logo">
					<h2><?php esc_html_e( 'GeneratePress Utility', 'generatepress-utility' ); ?></h2>
				</div>
				<div class="wpids-header-nav">
					<a href="#" class="active"><?php esc_html_e( 'Dashboard', 'generatepress-utility' ); ?></a>
					<a href="https://wpids.com/gp-utility-elite" target="_blank"><?php esc_html_e( 'Elite', 'generatepress-utility' ); ?></a>
					<a href="https://wpids.com/help-support" target="_blank"><?php esc_html_e( 'Support', 'generatepress-utility' ); ?></a>
					<a href="https://wpids.com/documentation" target="_blank"><?php esc_html_e( 'Documentation', 'generatepress-utility' ); ?></a>
				</div>
			</div>
		</div>

		<div class="wpids-dashboard-container">
			<div class="wpids-modules-container">
				<h2><?php esc_html_e( 'Utilities', 'generatepress-utility' ); ?></h2>
				<table class="form-table" style="width: 100%; border-collapse: collapse;">
					<tbody>
						<?php 
						$admin_primary_color = $this->get_admin_primary_color();
						foreach ( $modules as $id => $module ) : 
							$is_active = isset( $options[ $id ] ) ? $options[ $id ] : true;
							$toggle_url = wp_nonce_url( admin_url( 'themes.php?page=wpids-utility&action=toggle_module&module=' . $id ), 'wpids_toggle_module' );
							$shadow_color = $is_active ? $admin_primary_color : 'rgb(221, 221, 221)';
						?>
						<tr style="background: #fff; border-bottom: 1px solid #eee; box-shadow: <?php echo esc_attr( $shadow_color ); ?> -5px 0px 0px;">
							<td style="padding: 15px;">
								<strong style="font-size:15px;"><?php echo esc_html( $module['name'] ); ?></strong><br>
								<span style="color: #80879a; font-size: 13px;"><?php echo esc_html( $module['desc'] ); ?></span>
							</td>
							<td style="text-align: right; padding: 15px; vertical-align: middle;">
								<?php if ( $is_active ) : ?>
									<a href="<?php echo esc_url( $toggle_url ); ?>" class="wpids-btn-outline" style="height:32px; font-size:12px;"><?php esc_html_e( 'Deactivate', 'generatepress-utility' ); ?></a>
								<?php else : ?>
									<a href="<?php echo esc_url( $toggle_url ); ?>" class="wpids-btn-primary" style="height:32px; font-size:12px;"><?php esc_html_e( 'Activate', 'generatepress-utility' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php 
			$is_editor_sync = isset( $options['enable_editor_sync'] ) ? $options['enable_editor_sync'] : true;
			if ( $is_editor_sync ) : 
			?>
			<div class="wpids-advanced-container" style="margin-top: 50px;">
				<h2><?php esc_html_e( 'Custom CSS', 'generatepress-utility' ); ?></h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wpids_utility_settings_group' );
					$raw_css = get_option( 'wpids_utility_raw_css', '' );
					?>
					<p style="color: #646970; font-size: 13px; margin-bottom:15px;">
						<strong><?php esc_html_e( 'Advanced Raw CSS (No Linter)', 'generatepress-utility' ); ?></strong><br>
						<?php 
						printf( 
							/* translators: %s: code tag with @property */
							esc_html__( 'Write modern CSS like %s here. This CSS will be loaded in both the frontend and backend editor, bypassing the Customizer linter.', 'generatepress-utility' ),
							'<code>@property</code>'
						); 
						?>
					</p>
					
					<textarea name="wpids_utility_raw_css" rows="10" style="width: 100%; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; padding: 15px; background: #fff; color: #1d2327; border: 1px solid #c3c4c7; border-radius: 2px; box-shadow: inset 0 1px 2px rgba(0,0,0,.07);"><?php echo esc_textarea( $raw_css ); ?></textarea>
					
					<p style="margin-top: 20px;">
						<input type="submit" name="submit" id="wpids-save-css-btn" class="wpids-btn-primary" value="<?php esc_attr_e( 'Save CSS Settings', 'generatepress-utility' ); ?>">
					</p>
				</form>
				<script>
				(function() {
					var form = document.querySelector('.wpids-advanced-container form');
					if ( ! form ) return;
					form.addEventListener( 'submit', function() {
						var btn = document.getElementById('wpids-save-css-btn');
						if ( ! btn ) return;
						setTimeout( function() {
							btn.value = '<?php echo esc_js( __( 'Saved!', 'generatepress-utility' ) ); ?>';
							btn.style.background = '#00a32a';
							btn.style.borderColor = '#00a32a';
							setTimeout( function() {
								btn.value = '<?php echo esc_js( __( 'Save CSS Settings', 'generatepress-utility' ) ); ?>';
								btn.style.background = '';
								btn.style.borderColor = '';
							}, 3000 );
						}, 100 );
					});
				})();
				</script>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
