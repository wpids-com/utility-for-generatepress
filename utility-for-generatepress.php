<?php
/**
 * Plugin Name:       Utility for GeneratePress
 * Plugin URI:        https://wpids.com/utility-for-generatepress
 * Description:       Color Management, Gradient Variables, Advanced Fluid Typography, Dark Mode support, and Editor Sync for GeneratePress.
 * Version:           1.0.16
 * Author:            WPIDS
 * Author URI:        https://wpids.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       utility-for-generatepress
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP:      7.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UTILGP_VERSION', '1.0.16' );
define( 'UTILGP_FILE', __FILE__ );
define( 'UTILGP_PLUGIN_DIR', plugin_dir_path( UTILGP_FILE ) );
define( 'UTILGP_PLUGIN_URL', plugin_dir_url( UTILGP_FILE ) );

/**
 * Load Core Class
 */
require_once UTILGP_PLUGIN_DIR . 'includes/class-utilgp-core.php';

/**
 * Initialize the plugin.
 */
function utilgp_init() {
	// Check if GeneratePress theme is active
	$theme = wp_get_theme();
	$is_gp = ( 'GeneratePress' === $theme->name || 'generatepress' === $theme->template );

	if ( ! $is_gp ) {
		add_action( 'admin_notices', 'utilgp_gp_missing_notice' );
		return;
	}

	$plugin = new UTILGP_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'utilgp_init' );

/**
 * Show notice if GeneratePress is missing
 */
function utilgp_gp_missing_notice() {
	$theme_slug = 'generatepress';
	$theme = wp_get_theme( $theme_slug );
	
	if ( $theme->exists() ) {
		$url = wp_nonce_url( admin_url( 'themes.php?action=activate&stylesheet=' . $theme_slug ), 'switch-theme_' . $theme_slug );
		$label = esc_html__( 'Activate now.', 'utility-for-generatepress' );
	} else {
		$url = admin_url( 'theme-install.php?theme=' . $theme_slug );
		$label = esc_html__( 'Install now.', 'utility-for-generatepress' );
	}
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php 
			$message = sprintf( 
				/* translators: 1: Activation URL, 2: Link Label */
				esc_html__( 'Utility for GeneratePress requires GeneratePress to be your active theme. <a href="%1$s">%2$s</a>', 'utility-for-generatepress' ),
				esc_url( $url ),
				$label
			);
			echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ); 
			?>
		</p>
	</div>
	<?php
}
