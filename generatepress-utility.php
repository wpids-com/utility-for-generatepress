<?php
/**
 * Plugin Name:       GeneratePress Utility
 * Plugin URI:        https://wpids.com/generatepress-utility
 * Description:       Color Management, Gradient Variables, Advanced Fluid Typography, Dark Mode support, and Editor Sync for GeneratePress.
 * Version:           1.0.15
 * Author:            WPIDS
 * Author URI:        https://wpids.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       generatepress-utility
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP:      7.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPIDS_UTILITY_VERSION', '1.0.15' );
define( 'WPIDS_UTILITY_FILE', __FILE__ );
define( 'WPIDS_UTILITY_PLUGIN_DIR', plugin_dir_path( WPIDS_UTILITY_FILE ) );
define( 'WPIDS_UTILITY_PLUGIN_URL', plugin_dir_url( WPIDS_UTILITY_FILE ) );

/**
 * Load Core Class
 */
require_once WPIDS_UTILITY_PLUGIN_DIR . 'includes/class-wpids-utility-core.php';

/**
 * Initialize the plugin.
 */
function wpids_utility_init() {
	// Load text domain
	load_plugin_textdomain( 'generatepress-utility', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Check if GeneratePress theme is active
	$theme = wp_get_theme();
	$is_gp = ( 'GeneratePress' === $theme->name || 'generatepress' === $theme->template );

	if ( ! $is_gp ) {
		add_action( 'admin_notices', 'wpids_utility_gp_missing_notice' );
		return;
	}

	$plugin = new WPIDS_Utility_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wpids_utility_init' );

/**
 * Show notice if GeneratePress is missing
 */
function wpids_utility_gp_missing_notice() {
	$theme_slug = 'generatepress';
	$theme = wp_get_theme( $theme_slug );
	
	if ( $theme->exists() ) {
		$url = wp_nonce_url( admin_url( 'themes.php?action=activate&stylesheet=' . $theme_slug ), 'switch-theme_' . $theme_slug );
		$label = esc_html__( 'Activate now.', 'generatepress-utility' );
	} else {
		$url = admin_url( 'theme-install.php?theme=' . $theme_slug );
		$label = esc_html__( 'Install now.', 'generatepress-utility' );
	}
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php 
			$message = sprintf( 
				/* translators: 1: Activation URL, 2: Link Label */
				esc_html__( 'GeneratePress Utility requires GeneratePress to be your active theme. <a href="%1$s">%2$s</a>', 'generatepress-utility' ),
				esc_url( $url ),
				$label
			);
			echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ); 
			?>
		</p>
	</div>
	<?php
}
