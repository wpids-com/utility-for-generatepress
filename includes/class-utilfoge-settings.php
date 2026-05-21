<?php
/**
 * Settings Class.
 * Handles WP-Admin options page for modular toggles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Settings {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'handle_toggles' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
	}

	public function enqueue_dashboard_assets( $hook ) {
		if ( 'appearance_page_utilfoge-utility' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'utilfoge-admin-common',
			UTILFOGE_PLUGIN_URL . 'assets/css/utilfoge-admin-common.css',
			array(),
			UTILFOGE_VERSION
		);

		// Register a dummy script to attach inline JS
		wp_register_script( 'utilfoge-dashboard-js', false, array(), UTILFOGE_VERSION, true );
		wp_enqueue_script( 'utilfoge-dashboard-js' );

		$inline_js = "
		(function() {
			// Hide flash messages after 3 seconds
			var flashMessages = document.querySelectorAll('.utilfoge-module-message__show');
			if ( flashMessages.length > 0 ) {
				setTimeout(function() {
					flashMessages.forEach(function(msg) {
						msg.style.opacity = '0';
						msg.style.transition = 'opacity 0.5s ease';
						setTimeout(function() { msg.style.display = 'none'; }, 500);
					});
					var url = new URL(window.location.href);
					url.searchParams.delete('message');
					url.searchParams.delete('module_id');
					url.searchParams.delete('msg_nonce');
					window.history.replaceState({}, '', url);
				}, 3000);
			}
		})();
		";
		wp_add_inline_script( 'utilfoge-dashboard-js', $inline_js );
	}

	/**
	 * Get all module definitions
	 */
	public function get_modules() {
		$modules = array(
			'enable_color_manager' => array(
				'name' => __( 'Color Management', 'utility-for-generatepress' ),
				'desc' => __( 'Import colors, generate shades, tints, and harmony variants.', 'utility-for-generatepress' ),
				'action_link' => admin_url( 'customize.php' ),
			),
			'enable_gradient_palette' => array(
				'name' => __( 'Gradient Palette', 'utility-for-generatepress' ),
				'desc' => __( 'Create gradient presets as native WordPress variables (--wp--preset--gradient--*).', 'utility-for-generatepress' ),
				'action_link' => admin_url( 'customize.php' ),
			),
			'enable_typography' => array(
				'name' => __( 'Fluid Typography', 'utility-for-generatepress' ),
				'desc' => __( 'Responsive text sizing using clamp().', 'utility-for-generatepress' ),
				'action_link' => admin_url( 'customize.php' ),
			),
			'enable_dark_mode' => array(
				'name' => __( 'Dark Mode', 'utility-for-generatepress' ),
				'desc' => __( 'Native Dark/Light Mode toggle.', 'utility-for-generatepress' ),
				'action_link' => admin_url( 'customize.php' ),
			),
			'enable_editor_sync' => array(
				'name' => __( 'Editor CSS Sync', 'utility-for-generatepress' ),
				'desc' => __( 'Sync Customizer & Theme CSS to Gutenberg.', 'utility-for-generatepress' )
			),
		);

		return apply_filters( 'utilfoge_utility_modules', $modules );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'themes.php',
			esc_html__( 'Utility for GeneratePress Settings', 'utility-for-generatepress' ),
			esc_html__( 'Utility', 'utility-for-generatepress' ),
			'manage_options',
			'utilfoge-utility',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		// No custom CSS registration anymore
	}

	public function handle_toggles() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'utilfoge-utility' && isset( $_GET['action'] ) && $_GET['action'] === 'toggle_module' && isset( $_GET['module'] ) && isset( $_GET['_wpnonce'] ) ) {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			
			if ( wp_verify_nonce( $nonce, 'utilfoge_toggle_module' ) ) {
				$module_id = sanitize_text_field( wp_unslash( $_GET['module'] ) );
				$modules = $this->get_modules();
				
				if ( ! isset( $modules[ $module_id ] ) ) {
					return;
				}

				$options = get_option( 'utilfoge_utility_options', array() );
				$default_state = isset( $modules[ $module_id ]['default'] ) ? $modules[ $module_id ]['default'] : true;
				$current_state = isset( $options[ $module_id ] ) ? $options[ $module_id ] : $default_state;
				
				$options[ $module_id ] = ! $current_state;
				update_option( 'utilfoge_utility_options', $options );
				
				$message = $options[ $module_id ] ? 'activated' : 'deactivated';
				$redirect_url = add_query_arg(
					array(
						'message'   => $message,
						'module_id' => $module_id,
						'msg_nonce' => wp_create_nonce( 'utilfoge_show_message' ),
					),
					admin_url( 'themes.php?page=utilfoge-utility' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	public function render_settings_page() {
		$options = get_option( 'utilfoge_utility_options', array() );
		$modules = $this->get_modules();
		?>
		<div class="utilfoge-dashboard-header">
			<div class="utilfoge-dashboard-header__title">
				<h1>
					<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600"><path d="M485.2 427.8l-99.1-46.2 15.8-34c5.6-11.9 8.8-24.3 10-36.7 3.3-33.7-9-67.3-33.2-91.1-8.9-8.7-19.3-16.1-31.3-21.7-11.9-5.6-24.3-8.8-36.7-10-33.7-3.3-67.4 9-91.1 33.2-8.7 8.9-16.1 19.3-21.7 31.3l-15.8 34-30.4 65.2c-.7 1.5-.1 3.3 1.5 4l65.2 30.4 34 15.8 34 15.8 68 31.7 74.7 34.8c-65 45.4-152.1 55.2-228.7 17.4C90.2 447.4 44.1 313.3 97.3 202.6c53.3-110.8 186-158.5 297.8-106.3 88.1 41.1 137.1 131.9 129.1 223.4-.1 1.3.6 2.4 1.7 3l65.6 30.6c1.8.8 3.9-.3 4.2-2.2 22.6-130.7-44-265.4-170.5-323.5-150.3-69-327-4.1-396.9 145.8-70 150.1-5.1 328.5 145.1 398.5 114.1 53.2 244.5 28.4 331.3-52.3 17.9-16.6 33.9-35.6 47.5-56.8 1-1.5.4-3.6-1.3-4.3l-65.7-30.7zm-235-109.6l15.8-34c8.8-18.8 31.1-26.9 49.8-18.1s26.9 31 18.1 49.8l-15.8 34-34-15.8-33.9-15.9z" fill="currentColor" /></svg>
					<?php esc_html_e( 'Utility for GeneratePress', 'utility-for-generatepress' ); ?>
				</h1>
			</div>
			<div class="utilfoge-dashboard-header__nav">
				<a href="#" class="utilfoge-active"><?php esc_html_e( 'Utilities', 'utility-for-generatepress' ); ?></a>
				<a href="https://wpids.com/elite-utility-for-gp" target="_blank"><?php esc_html_e( 'Elite', 'utility-for-generatepress' ); ?></a>
				<a href="https://wpids.com/documentation" target="_blank"><?php esc_html_e( 'Documentation', 'utility-for-generatepress' ); ?></a>
				<a href="https://wpids.com/help-support" target="_blank"><?php esc_html_e( 'Support', 'utility-for-generatepress' ); ?></a>
			</div>
		</div>

		<div class="wrap" style="margin-top: 0;">
			<div class="utilfoge-dashboard-content">
				
				<?php 
				/**
				 * Hook for Elite License Box
				 */
				do_action( 'utilfoge_utility_dashboard_before_modules' ); 
				?>

				<div class="utilfoge-section-title">
					<h2><?php esc_html_e( 'Utilities', 'utility-for-generatepress' ); ?></h2>
				</div>

				<div class="utilfoge-module-list">
					<?php 
					foreach ( $modules as $id => $module ) : 
						$default_state = isset( $module['default'] ) ? $module['default'] : true;
						$is_active = isset( $options[ $id ] ) ? $options[ $id ] : $default_state;
						$toggle_url = wp_nonce_url( admin_url( 'themes.php?page=utilfoge-utility&action=toggle_module&module=' . $id ), 'utilfoge_toggle_module' );
						
						$shadow_color = $is_active ? '#007cba' : 'rgb(221, 221, 221)';
						$row_style = "box-shadow: {$shadow_color} -5px 0px 0px;";
					?>
					<div class="utilfoge-module-row <?php echo $is_active ? 'utilfoge-active' : 'utilfoge-inactive'; ?>" style="<?php echo esc_attr( $row_style ); ?>">
						<div class="utilfoge-module-info">
							<h3>
								<?php echo esc_html( $module['name'] ); ?>
								<?php if ( $is_active && ! empty( $module['is_page'] ) && ! empty( $module['action_link'] ) ) : ?>
									<a class="utilfoge-module-action" href="<?php echo esc_url( $module['action_link'] ); ?>">
										<?php esc_html_e( 'Open Tool  →', 'utility-for-generatepress' ); ?>
									</a>
								<?php endif; ?>
							</h3>
							<p><?php echo esc_html( $module['desc'] ); ?></p>
						</div>
						<div class="utilfoge-module-actions">
							<?php 
							$msg_nonce = isset( $_GET['msg_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['msg_nonce'] ) ) : '';
							$msg_type  = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
							$msg_id    = isset( $_GET['module_id'] ) ? sanitize_text_field( wp_unslash( $_GET['module_id'] ) ) : '';

							$show_message = ( wp_verify_nonce( $msg_nonce, 'utilfoge_show_message' ) && $msg_id === $id );
							if ( $show_message ) :
								$msg_text = ( $msg_type === 'activated' ) ? __( 'Module activated.', 'utility-for-generatepress' ) : __( 'Module deactivated.', 'utility-for-generatepress' );
							?>
								<span class="utilfoge-module-message utilfoge-module-message__show"><?php echo esc_html( $msg_text ); ?></span>
							<?php endif; ?>
							
							<?php if ( $is_active && ! empty( $module['action_link'] ) && empty( $module['is_page'] ) ) : ?>
								<div class="utilfoge-module-settings">
									<a href="<?php echo esc_url( $module['action_link'] ); ?>" class="components-button is-tertiary has-icon" title="<?php esc_attr_e( 'Settings', 'utility-for-generatepress' ); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"></path></svg>
									</a>
								</div>
							<?php endif; ?>

							<?php if ( $is_active ) : ?>
								<a href="<?php echo esc_url( $toggle_url ); ?>" class="components-button is-secondary"><?php esc_html_e( 'Deactivate', 'utility-for-generatepress' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( $toggle_url ); ?>" class="components-button is-primary"><?php esc_html_e( 'Activate', 'utility-for-generatepress' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

			</div> <!-- .utilfoge-dashboard-content -->
		</div> <!-- .wrap -->
		<?php
	}
}
