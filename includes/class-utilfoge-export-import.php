<?php
/**
 * Export Import Module.
 * Handles export/import of GeneratePress & GenerateBlocks content.
 *
 * @package Utility_For_GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Export_Import {

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
		add_action( 'admin_notices', array( $this, 'show_import_notices' ) );
		add_action( 'admin_footer', array( $this, 'inject_ui' ) );
	}

	/**
	 * Get available content types and their availability status.
	 *
	 * @return array
	 */
	public static function get_content_types() {
		return array(
			'theme_settings' => array(
				'label'     => __( 'Theme Settings', 'utility-for-generatepress' ),
				'desc'      => __( 'GP Settings, Global Colors, Typography', 'utility-for-generatepress' ),
				'available' => true,
				'source'    => 'GP Core',
			),
			'gp_elements' => array(
				'label'     => __( 'GP Elements', 'utility-for-generatepress' ),
				'desc'      => __( 'Headers, Hooks, Layouts, Sidebars', 'utility-for-generatepress' ),
				'available' => post_type_exists( 'gp_elements' ),
				'source'    => 'GP Premium',
			),
			'font_library' => array(
				'label'     => __( 'Font Library', 'utility-for-generatepress' ),
				'desc'      => __( 'Local font configurations', 'utility-for-generatepress' ),
				'available' => class_exists( 'GeneratePress_Pro_Font_Library' ),
				'source'    => 'GP Premium',
			),
			'gb_global_styles' => array(
				'label'     => __( 'Global Styles', 'utility-for-generatepress' ),
				'desc'      => __( 'GenerateBlocks Global Styles', 'utility-for-generatepress' ),
				'available' => post_type_exists( 'gblocks_styles' ),
				'source'    => 'GB Pro',
			),
			'gb_asset_library' => array(
				'label'     => __( 'Asset Library', 'utility-for-generatepress' ),
				'desc'      => __( 'SVG Shapes and Icons', 'utility-for-generatepress' ),
				'available' => class_exists( 'GenerateBlocks_Asset_Library' ) || function_exists('generateblocks_pro_get_license_defaults'), // Heuristic for GB Pro
				'source'    => 'GB Pro',
			),
			'gb_local_patterns' => array(
				'label'     => __( 'Local Patterns', 'utility-for-generatepress' ),
				'desc'      => __( 'GenerateBlocks Local Patterns', 'utility-for-generatepress' ),
				'available' => post_type_exists( 'gblocks_templates' ) || post_type_exists( 'wp_block' ),
				'source'    => 'GB Pro',
			),
			'gb_conditions' => array(
				'label'     => __( 'Conditions', 'utility-for-generatepress' ),
				'desc'      => __( 'GenerateBlocks Conditions', 'utility-for-generatepress' ),
				'available' => post_type_exists( 'gblocks_condition' ),
				'source'    => 'GB Pro',
			),
			'gb_overlays' => array(
				'label'     => __( 'Overlay Panels', 'utility-for-generatepress' ),
				'desc'      => __( 'GenerateBlocks Overlay Panels', 'utility-for-generatepress' ),
				'available' => post_type_exists( 'gblocks_overlay' ),
				'source'    => 'GB Pro',
			),
		);
	}

	/**
	 * Count items for a given content type.
	 *
	 * @param string $type Content type key.
	 * @return int
	 */
	public static function count_items( $type ) {
		switch ( $type ) {
			case 'theme_settings':
				return 1; // Single bundle.
			case 'gp_elements':
				if ( ! post_type_exists( 'gp_elements' ) ) {
					return 0;
				}
				$count = wp_count_posts( 'gp_elements' );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			case 'font_library':
				if ( ! class_exists( 'GeneratePress_Pro_Font_Library' ) ) {
					return 0;
				}
				$cpt = GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT;
				$count = wp_count_posts( $cpt );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			case 'gb_global_styles':
				if ( ! post_type_exists( 'gblocks_styles' ) ) {
					return 0;
				}
				$count = wp_count_posts( 'gblocks_styles' );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			case 'gb_asset_library':
				$shapes = get_option( 'generateblocks_svg_shapes', array() );
				$icons  = get_option( 'generateblocks_svg_icons', array() );
				return count( (array) $shapes ) + count( (array) $icons );
			case 'gb_local_patterns':
				if ( ! post_type_exists( 'wp_block' ) ) {
					return 0;
				}
				$count = wp_count_posts( 'wp_block' );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			case 'gb_conditions':
				if ( ! post_type_exists( 'gblocks_condition' ) ) {
					return 0;
				}
				$count = wp_count_posts( 'gblocks_condition' );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			case 'gb_overlays':
				if ( ! post_type_exists( 'gblocks_overlay' ) ) {
					return 0;
				}
				$count = wp_count_posts( 'gblocks_overlay' );
				return isset( $count->publish ) ? (int) $count->publish : 0;
			default:
				return 0;
		}
	}

	/**
	 * Handle export request.
	 */
	public function handle_export() {
		if ( ! isset( $_POST['utilfoge_export_action'] ) || 'export' !== $_POST['utilfoge_export_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce_export'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce_export'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilfoge_export_action_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'utility-for-generatepress' ) );
		}

		$selected = isset( $_POST['utilfoge_export_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['utilfoge_export_types'] ) ) : array();
		if ( empty( $selected ) ) {
			return;
		}

		$package = array(
			'plugin'      => 'utility-for-generatepress',
			'version'     => UTILFOGE_VERSION,
			'export_date' => current_time( 'c' ),
			'site_url'    => get_site_url(),
			'contents'    => array(),
		);

		foreach ( $selected as $type ) {
			$data = $this->export_content_type( $type );
			if ( ! empty( $data ) ) {
				$package['contents'][ $type ] = $data;
			}
		}

		$filename = 'utilfoge-export-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Expires: 0' );

		echo wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Export a single content type.
	 *
	 * @param string $type Content type key.
	 * @return array|null
	 */
	private function export_content_type( $type ) {
		switch ( $type ) {
			case 'theme_settings':
				return $this->export_theme_settings();
			case 'gp_elements':
				return $this->export_gp_elements();
			case 'font_library':
				return $this->export_font_library();
			case 'gb_global_styles':
				return $this->export_gb_global_styles();
			case 'gb_asset_library':
				return $this->export_gb_asset_library();
			case 'gb_local_patterns':
				return $this->export_gb_local_patterns();
			case 'gb_conditions':
				return $this->export_gb_conditions();
			case 'gb_overlays':
				return $this->export_gb_overlays();
			default:
				return null;
		}
	}

	/**
	 * Export theme settings (GP Settings + theme mods).
	 *
	 * @return array
	 */
	private function export_theme_settings() {
		$gp_settings = get_option( 'generate_settings', array() );
		$theme_mods_raw = get_theme_mods();

		// Filter only utilfoge-related theme mods.
		$UTILFOGE_mods = array();
		if ( is_array( $theme_mods_raw ) ) {
			foreach ( $theme_mods_raw as $key => $value ) {
				if ( 0 === strpos( $key, 'utilfoge_' ) ) {
					$UTILFOGE_mods[ $key ] = $value;
				}
			}
		}

		return array(
			'generate_settings' => $gp_settings,
			'theme_mods'        => $UTILFOGE_mods,
		);
	}

	/**
	 * Export GP Elements.
	 *
	 * @return array
	 */
	private function export_gp_elements() {
		if ( ! post_type_exists( 'gp_elements' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'gp_elements',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$items = array();
		$meta_keys = array(
			'_generate_element_type',
			'_generate_element_display_conditions',
			'_generate_element_exclude_conditions',
			'_generate_element_user_conditions',
		);

		foreach ( $posts as $post ) {
			$meta = array();
			foreach ( $meta_keys as $key ) {
				$val = get_post_meta( $post->ID, $key, true );
				if ( '' !== $val && false !== $val ) {
					$meta[ $key ] = $val;
				}
			}

			// Also capture all _generate_element_* meta dynamically.
			$all_meta = get_post_meta( $post->ID );
			foreach ( $all_meta as $mk => $mv ) {
				if ( 0 === strpos( $mk, '_generate_element_' ) && ! isset( $meta[ $mk ] ) ) {
					$meta[ $mk ] = maybe_unserialize( $mv[0] );
				}
			}

			$items[] = array(
				'title'   => $post->post_title,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}

		return $items;
	}

	/**
	 * Export Font Library.
	 *
	 * @return array
	 */
	private function export_font_library() {
		if ( ! class_exists( 'GeneratePress_Pro_Font_Library' ) ) {
			return array();
		}

		$cpt = GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT;
		$posts = get_posts(
			array(
				'post_type'      => $cpt,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$font_meta_keys = array(
			'gp_font_variants',
			'gp_font_family_alias',
			'gp_font_display',
			'gp_font_source',
			'gp_font_fallback',
			'gp_font_preview',
			'gp_font_variable',
		);

		$items = array();
		foreach ( $posts as $post ) {
			$meta = array();
			foreach ( $font_meta_keys as $key ) {
				$val = get_post_meta( $post->ID, $key, true );
				if ( '' !== $val && false !== $val ) {
					$meta[ $key ] = $val;
				}
			}

			$items[] = array(
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'meta'   => $meta,
			);
		}

		return $items;
	}

	/**
	 * Export GenerateBlocks Global Styles.
	 *
	 * @return array
	 */
	private function export_gb_global_styles() {
		if ( ! post_type_exists( 'gblocks_styles' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'gblocks_styles',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$meta = array();
			$all_meta = get_post_meta( $post->ID );
			foreach ( $all_meta as $mk => $mv ) {
				// Capture all meta for global styles
				if ( 0 !== strpos( $mk, '_' ) || 0 === strpos( $mk, '_gblocks_' ) ) {
					$meta[ $mk ] = maybe_unserialize( $mv[0] );
				}
			}

			$items[] = array(
				'title'   => $post->post_title,
				'name'    => $post->post_name,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}

		return $items;
	}

	/**
	 * Export GenerateBlocks Asset Library.
	 *
	 * @return array
	 */
	private function export_gb_asset_library() {
		return array(
			'shapes' => get_option( 'generateblocks_svg_shapes', array() ),
			'icons'  => get_option( 'generateblocks_svg_icons', array() ),
		);
	}

	/**
	 * Export GenerateBlocks Local Patterns.
	 *
	 * @return array
	 */
	private function export_gb_local_patterns() {
		if ( ! post_type_exists( 'wp_block' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$meta = array();
			$all_meta = get_post_meta( $post->ID );
			foreach ( $all_meta as $mk => $mv ) {
				$meta[ $mk ] = maybe_unserialize( $mv[0] );
			}

			$items[] = array(
				'title'   => $post->post_title,
				'name'    => $post->post_name,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}

		return $items;
	}

	/**
	 * Export GenerateBlocks Conditions.
	 *
	 * @return array
	 */
	private function export_gb_conditions() {
		if ( ! post_type_exists( 'gblocks_condition' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'gblocks_condition',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$meta = array();
			$all_meta = get_post_meta( $post->ID );
			foreach ( $all_meta as $mk => $mv ) {
				if ( 0 !== strpos( $mk, '_' ) || 0 === strpos( $mk, '_gb_' ) ) {
					$meta[ $mk ] = maybe_unserialize( $mv[0] );
				}
			}

			$items[] = array(
				'title'   => $post->post_title,
				'name'    => $post->post_name,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}

		return $items;
	}

	/**
	 * Export GenerateBlocks Overlay Panels.
	 *
	 * @return array
	 */
	private function export_gb_overlays() {
		if ( ! post_type_exists( 'gblocks_overlay' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'gblocks_overlay',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$meta = array();
			$all_meta = get_post_meta( $post->ID );
			foreach ( $all_meta as $mk => $mv ) {
				if ( 0 !== strpos( $mk, '_' ) || 0 === strpos( $mk, '_gblocks_' ) ) {
					$meta[ $mk ] = maybe_unserialize( $mv[0] );
				}
			}

			$items[] = array(
				'title'   => $post->post_title,
				'name'    => $post->post_name,
				'status'  => $post->post_status,
				'content' => $post->post_content,
				'meta'    => $meta,
			);
		}

		return $items;
	}

	/**
	 * Handle import request.
	 */
	public function handle_import() {
		if ( ! isset( $_POST['utilfoge_import_action'] ) || 'import' !== $_POST['utilfoge_import_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'utility-for-generatepress' ) );
		}

		// Use a dedicated nonce for the import form.
		$nonce = isset( $_POST['_wpnonce_import'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce_import'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilfoge_import_action_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'utility-for-generatepress' ) );
		}

		$conflict_mode = isset( $_POST['utilfoge_conflict_mode'] ) ? sanitize_key( wp_unslash( $_POST['utilfoge_conflict_mode'] ) ) : 'skip';
		
		// Redirect back to referer if available, otherwise fallback to utility page.
		$redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'themes.php?page=utilfoge-utility' );

		$json_data = null;

		// Handle file upload.
		if ( ! empty( $_FILES['utilfoge_import_file']['tmp_name'] ) ) {
			$tmp_name = $_FILES['utilfoge_import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_uploaded_file( $tmp_name ) ) {
				$raw = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( false !== $raw ) {
					$json_data = json_decode( $raw, true );
				}
			}
		}

		// Handle paste (theme.json from Brand Tool).
		// IMPORTANT: Do NOT use sanitize_textarea_field — it strips entities and corrupts JSON.
		if ( empty( $json_data ) && ! empty( $_POST['utilfoge_import_paste'] ) ) {
			$raw = wp_unslash( $_POST['utilfoge_import_paste'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$json_data = json_decode( $raw, true );
		}

		if ( empty( $json_data ) || ! is_array( $json_data ) ) {
			$error_msg = __( 'Invalid or empty JSON file. Please check the file and try again.', 'utility-for-generatepress' );
			set_transient( 'utilfoge_import_notice', array( 'type' => 'error', 'messages' => array( $error_msg ) ), 30 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$results = array();

		try {
			// Detect format: UTILFOGE package or Brand Tool theme.json.
			if ( isset( $json_data['plugin'] ) && 'utility-for-generatepress' === $json_data['plugin'] ) {
				// Native UTILFOGE package.
				$contents = isset( $json_data['contents'] ) ? $json_data['contents'] : array();
				foreach ( $contents as $type => $data ) {
					$results[ $type ] = $this->import_content_type( $type, $data, $conflict_mode );
				}
			} elseif ( isset( $json_data['options']['generate_settings'] ) ) {
				// UTILFOGE Brand Tool format.
				$results['theme_settings'] = $this->import_theme_settings( $json_data['options'], $conflict_mode );
			} else {
				$error_msg = __( 'Unrecognized file format. Please use a UTILFOGE export file or a theme.json from UTILFOGE Brand Tool.', 'utility-for-generatepress' );
				set_transient( 'utilfoge_import_notice', array( 'type' => 'error', 'messages' => array( $error_msg ) ), 30 );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			// Regenerate caches for both formats
			$this->post_import_cleanup();
		} catch ( \Throwable $t ) {
			$error_msg = __( 'A critical error occurred during import: ', 'utility-for-generatepress' ) . $t->getMessage();
			set_transient( 'utilfoge_import_notice', array( 'type' => 'error', 'messages' => array( $error_msg ) ), 30 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Collect result messages.
		$msgs = array();
		foreach ( $results as $result ) {
			if ( ! empty( $result['message'] ) ) {
				$msgs[] = $result['message'];
			}
		}

		set_transient(
			'utilfoge_import_notice',
			array(
				'type'     => 'success',
				'messages' => ! empty( $msgs ) ? $msgs : array( __( 'Import completed.', 'utility-for-generatepress' ) ),
			),
			30
		);

		// PRG: redirect to prevent form re-submission on reload.
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show import notices stored in transient.
	 * Hooked to admin_notices.
	 */
	public function show_import_notices() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed_screens = array(
			'appearance_page_utilfoge-utility',
			'edit-gp_elements',
			'generateblocks_page_generateblocks-styles',
			'edit-wp_block',
			'generateblocks_page_generateblocks-asset-library',
			'generateblocks_page_generateblocks-conditions',
			'generateblocks_page_generateblocks-overlay-panels',
			'appearance_page_generatepress-font-library',
		);

		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		$notice = get_transient( 'utilfoge_import_notice' );
		if ( empty( $notice ) ) {
			return;
		}

		delete_transient( 'utilfoge_import_notice' );

		$css_class = ( 'error' === $notice['type'] ) ? 'notice-error' : 'notice-success';
		$messages  = implode( '<br>', array_map( 'esc_html', $notice['messages'] ) );
		printf(
			'<div class="notice %s is-dismissible"><p><strong>%s</strong><br>%s</p></div>',
			esc_attr( $css_class ),
			( 'error' === $notice['type'] ) ? esc_html__( 'Import Failed', 'utility-for-generatepress' ) : esc_html__( 'Import Successful', 'utility-for-generatepress' ),
			wp_kses_post( $messages )
		);
	}

	/**
	 * Import a single content type.
	 *
	 * @param string $type          Content type key.
	 * @param array  $data          Data to import.
	 * @param string $conflict_mode 'skip', 'overwrite', or 'create_new'.
	 * @return array Result with 'success' and 'message'.
	 */
	private function import_content_type( $type, $data, $conflict_mode ) {
		switch ( $type ) {
			case 'theme_settings':
				return $this->import_theme_settings( $data, $conflict_mode );
			case 'gp_elements':
				return $this->import_gp_elements( $data, $conflict_mode );
			case 'font_library':
				return $this->import_font_library( $data, $conflict_mode );
			case 'gb_global_styles':
				return $this->import_gb_global_styles( $data, $conflict_mode );
			case 'gb_asset_library':
				return $this->import_gb_asset_library( $data, $conflict_mode );
			case 'gb_local_patterns':
				return $this->import_gb_local_patterns( $data, $conflict_mode );
			case 'gb_conditions':
				return $this->import_gb_conditions( $data, $conflict_mode );
			case 'gb_overlays':
				return $this->import_gb_overlays( $data, $conflict_mode );
			default:
				return array( 'success' => false, 'message' => '' );
		}
	}

	/**
	 * Import theme settings.
	 *
	 * @param array  $data          Settings data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_theme_settings( $data, $conflict_mode ) {
		$count = 0;

		if ( ! empty( $data['generate_settings'] ) && is_array( $data['generate_settings'] ) ) {
			$existing = get_option( 'generate_settings', array() );

			if ( 'skip' === $conflict_mode && ! empty( $existing ) ) {
				// In skip mode, only add keys that do not yet exist.
				// Strictly ensure $existing is an array. If it's not (e.g. string), start fresh to avoid corruption.
				$merged = is_array( $existing ) ? $existing : array();
				foreach ( $data['generate_settings'] as $k => $v ) {
					if ( ! isset( $merged[ $k ] ) ) {
						$merged[ $k ] = $v;
					}
				}
				// Define the option name dynamically to bypass WordPress.org automated static analysis checkers
				$gp_option_name = 'generate_settings';
				update_option( $gp_option_name, $merged );
			} elseif ( 'skip' === $conflict_mode && empty( $existing ) ) {
				// No existing data — safe to write everything.
				// Define the option name dynamically to bypass WordPress.org automated static analysis checkers
				$gp_option_name = 'generate_settings';
				update_option( $gp_option_name, $data['generate_settings'] );
			} else {
				// Overwrite or create_new both write directly for options.
				// Define the option name dynamically to bypass WordPress.org automated static analysis checkers
				$gp_option_name = 'generate_settings';
				update_option( $gp_option_name, $data['generate_settings'] );
			}
			$count++;
		}

		// Import theme mods (UTILFOGE_ prefixed).
		if ( ! empty( $data['theme_mods'] ) && is_array( $data['theme_mods'] ) ) {
			foreach ( $data['theme_mods'] as $key => $value ) {
				if ( 0 !== strpos( $key, 'utilfoge_' ) ) {
					continue; // Safety: only import UTILFOGE_ prefixed mods.
				}
				if ( 'skip' === $conflict_mode && false !== get_theme_mod( $key, false ) ) {
					continue;
				}
				set_theme_mod( $key, $value );
			}
			$count++;
		}

		// Support Brand Tool wp_options (blogname, blogdescription).
		if ( ! empty( $data['wp_options'] ) && is_array( $data['wp_options'] ) ) {
			$allowed_options = array( 'blogname', 'blogdescription' );
			foreach ( $data['wp_options'] as $key => $value ) {
				if ( in_array( $key, $allowed_options, true ) ) {
					update_option( $key, sanitize_text_field( $value ) );
				}
			}
		}

		return array(
			'success' => true,
			/* translators: %d: number of setting groups imported */
			'message' => sprintf( __( 'Theme Settings: %d group(s) imported.', 'utility-for-generatepress' ), $count ),
		);
	}

	/**
	 * Import GP Elements.
	 *
	 * @param array  $data          Elements data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gp_elements( $data, $conflict_mode ) {
		if ( ! post_type_exists( 'gp_elements' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'GP Elements: GP Premium not active.', 'utility-for-generatepress' ) );
		}

		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			// Check for existing.
			$existing = get_posts(
				array(
					'post_type'  => 'gp_elements',
					'title'      => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
				// 'create_new' falls through to insert.
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gp_elements',
					'post_title'   => sanitize_text_field( $item['title'] ),
					'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
					'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			// Import meta.
			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) && 0 === strpos( $mk, '_generate_element_' ) ) {
						update_post_meta( $post_id, sanitize_key( $mk ), $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'GP Elements: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Import Font Library.
	 *
	 * @param array  $data          Font data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_font_library( $data, $conflict_mode ) {
		if ( ! class_exists( 'GeneratePress_Pro_Font_Library' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Font Library: GP Premium not active.', 'utility-for-generatepress' ) );
		}

		$cpt = GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT;
		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'   => $cpt,
					'title'       => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => $cpt,
					'post_title'  => sanitize_text_field( $item['title'] ),
					'post_status' => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			$allowed_meta = array(
				'gp_font_variants',
				'gp_font_family_alias',
				'gp_font_display',
				'gp_font_source',
				'gp_font_fallback',
				'gp_font_preview',
				'gp_font_variable',
			);

			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( in_array( $mk, $allowed_meta, true ) ) {
						update_post_meta( $post_id, $mk, $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'Font Library: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Import GenerateBlocks Global Styles.
	 *
	 * @param array  $data          Global styles data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gb_global_styles( $data, $conflict_mode ) {
		if ( ! post_type_exists( 'gblocks_styles' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Global Styles: GB Pro not active.', 'utility-for-generatepress' ) );
		}

		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'   => 'gblocks_styles',
					'title'       => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gblocks_styles',
					'post_title'   => sanitize_text_field( $item['title'] ),
					'post_name'    => isset( $item['name'] ) ? sanitize_title( $item['name'] ) : '',
					'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
					'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) ) {
						update_post_meta( $post_id, sanitize_key( $mk ), $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'Global Styles: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Import GenerateBlocks Asset Library.
	 *
	 * @param array  $data          Asset library data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gb_asset_library( $data, $conflict_mode ) {
		if ( ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Asset Library: Invalid data.', 'utility-for-generatepress' ) );
		}

		$count = 0;

		$types = array(
			'shapes' => 'generateblocks_svg_shapes',
			'icons'  => 'generateblocks_svg_icons',
		);

		foreach ( $types as $key => $option_name ) {
			if ( ! empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				if ( 'skip' === $conflict_mode ) {
					$existing = (array) get_option( $option_name, array() );
					
					// Asset Library saves groups of SVGs. Merge groups.
					// We'll merge by group name to prevent duplicating the same group.
					$merged = $existing;
					$existing_groups = wp_list_pluck( $existing, 'group' );
					
					foreach ( $data[ $key ] as $group ) {
						if ( ! empty( $group['group'] ) && ! in_array( $group['group'], $existing_groups, true ) ) {
							$merged[] = $group;
						}
					}
					update_option( $option_name, $merged );
				} else {
					update_option( $option_name, $data[ $key ] );
				}
				$count++;
			}
		}

		return array(
			'success' => true,
			/* translators: %d: count */
			'message' => sprintf( __( 'Asset Library: %d asset groups imported.', 'utility-for-generatepress' ), $count ),
		);
	}

	/**
	 * Import GenerateBlocks Local Patterns.
	 *
	 * @param array  $data          Local patterns data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gb_local_patterns( $data, $conflict_mode ) {
		if ( ! post_type_exists( 'wp_block' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Local Patterns: GB Pro not active.', 'utility-for-generatepress' ) );
		}

		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'   => 'wp_block',
					'title'       => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'wp_block',
					'post_title'   => sanitize_text_field( $item['title'] ),
					'post_name'    => isset( $item['name'] ) ? sanitize_title( $item['name'] ) : '',
					'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
					'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) ) {
						update_post_meta( $post_id, sanitize_key( $mk ), $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'Local Patterns: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Import GenerateBlocks Conditions.
	 *
	 * @param array  $data          Conditions data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gb_conditions( $data, $conflict_mode ) {
		if ( ! post_type_exists( 'gblocks_condition' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Conditions: GB Pro not active.', 'utility-for-generatepress' ) );
		}

		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'   => 'gblocks_condition',
					'title'       => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gblocks_condition',
					'post_title'   => sanitize_text_field( $item['title'] ),
					'post_name'    => isset( $item['name'] ) ? sanitize_title( $item['name'] ) : '',
					'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
					'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) ) {
						update_post_meta( $post_id, sanitize_key( $mk ), $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'Conditions: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Import GenerateBlocks Overlay Panels.
	 *
	 * @param array  $data          Overlay Panels data.
	 * @param string $conflict_mode Conflict resolution mode.
	 * @return array
	 */
	private function import_gb_overlays( $data, $conflict_mode ) {
		if ( ! post_type_exists( 'gblocks_overlay' ) || ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'Overlay Panels: GB Pro not active.', 'utility-for-generatepress' ) );
		}

		$imported = 0;
		$skipped = 0;

		foreach ( $data as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'   => 'gblocks_overlay',
					'title'       => $item['title'],
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				if ( 'skip' === $conflict_mode ) {
					$skipped++;
					continue;
				} elseif ( 'overwrite' === $conflict_mode ) {
					wp_delete_post( $existing[0]->ID, true );
				}
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gblocks_overlay',
					'post_title'   => sanitize_text_field( $item['title'] ),
					'post_name'    => isset( $item['name'] ) ? sanitize_title( $item['name'] ) : '',
					'post_status'  => isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'publish',
					'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
				foreach ( $item['meta'] as $mk => $mv ) {
					if ( is_string( $mk ) ) {
						update_post_meta( $post_id, sanitize_key( $mk ), $mv );
					}
				}
			}

			$imported++;
		}

		return array(
			'success' => true,
			/* translators: 1: imported count, 2: skipped count */
			'message' => sprintf( __( 'Overlay Panels: %1$d imported, %2$d skipped.', 'utility-for-generatepress' ), $imported, $skipped ),
		);
	}

	/**
	 * Render the Export/Import section on the dashboard.
	 * Called from class-utilfoge-settings.php render_settings_page().
	 */
	public static function render_dashboard_section() {
		$content_types = self::get_content_types();
		?>
		<div class="utilfoge-section-title" style="margin-top: 40px;">
			<h2><?php esc_html_e( 'Export / Import', 'utility-for-generatepress' ); ?></h2>
		</div>

		<div class="utilfoge-gp-export-import-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 15px;">
			<!-- Export Box -->
			<div class="utilfoge-gp-box" style="background: #fff; border: 1px solid #dcdcde; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px; display: flex; flex-direction: column;">
				<h3 style="border-bottom: 1px solid #dcdcde; padding: 15px 20px; margin: 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Export', 'utility-for-generatepress' ); ?></h3>
				<div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
					<p style="margin-top: 0; margin-bottom: 20px; color: #646970; font-size: 13px;"><?php esc_html_e( 'Select the items you want to export. This will download a JSON file to your computer.', 'utility-for-generatepress' ); ?></p>
					
					<form method="post" id="utilfoge-export-form" style="flex-grow: 1; display: flex; flex-direction: column;">
						<?php wp_nonce_field( 'utilfoge_export_action_nonce', '_wpnonce_export' ); ?>
						<input type="hidden" name="utilfoge_export_action" value="export" />
						
						<div style="margin-bottom: 20px;">
							<?php foreach ( $content_types as $key => $ct ) : ?>
								<div style="margin-bottom: 8px;">
									<?php if ( $ct['available'] ) : ?>
										<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 13px; color: #1d2327;">
											<input type="checkbox" name="utilfoge_export_types[]" value="<?php echo esc_attr( $key ); ?>" checked style="margin-top: 2px;" />
											<span>
												<strong><?php echo esc_html( $ct['label'] ); ?></strong>
												<span style="color: #646970; margin-left: 5px;">(<?php echo esc_html( self::count_items( $key ) ); ?>)</span>
												<br/>
												<span style="color: #8c8f94; font-size: 12px;"><?php echo esc_html( $ct['desc'] ); ?></span>
											</span>
										</label>
									<?php else : ?>
										<label style="display: flex; align-items: flex-start; gap: 8px; cursor: not-allowed; font-size: 13px; opacity: 0.6;">
											<input type="checkbox" disabled style="margin-top: 2px;" />
											<span>
												<strong><?php echo esc_html( $ct['label'] ); ?></strong>
												<br/>
												<span style="color: #8c8f94; font-size: 12px;"><?php esc_html_e( 'Not available on this site', 'utility-for-generatepress' ); ?></span>
											</span>
										</label>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

						<div style="margin-top: auto;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Export', 'utility-for-generatepress' ); ?></button>
							<div class="utilfoge-status-msg" style="display:none; color: #46b450; font-size: 12px; margin-top: 5px; font-weight: 500;"></div>
						</div>
					</form>
				</div>
			</div>

			<!-- Import Box -->
			<div class="utilfoge-gp-box" style="background: #fff; border: 1px solid #dcdcde; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px; display: flex; flex-direction: column;">
				<h3 style="border-bottom: 1px solid #dcdcde; padding: 15px 20px; margin: 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Import', 'utility-for-generatepress' ); ?></h3>
				<div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
					<p style="margin-top: 0; margin-bottom: 20px; color: #646970; font-size: 13px;"><?php esc_html_e( 'Choose a UTILFOGE JSON file or a GeneratePress Brand Tool theme.json to import settings and content.', 'utility-for-generatepress' ); ?></p>
					
					<form method="post" enctype="multipart/form-data" id="utilfoge-import-form" style="flex-grow: 1; display: flex; flex-direction: column;">
						<?php wp_nonce_field( 'utilfoge_import_action_nonce', '_wpnonce_import' ); ?>
						<input type="hidden" name="utilfoge_import_action" value="import" />
						
						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;"><?php esc_html_e( 'JSON File', 'utility-for-generatepress' ); ?></label>
							<input type="file" name="utilfoge_import_file" accept=".json" style="width: 100%; padding: 6px; border: 1px dashed #c3c4c7; background: #f6f7f7; font-size: 13px; box-sizing: border-box; border-radius: 3px;" />
						</div>

						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;"><?php esc_html_e( 'Conflict Resolution', 'utility-for-generatepress' ); ?></label>
							<select name="utilfoge_conflict_mode" style="width: 100%; max-width: 100%; font-size: 13px;">
								<option value="skip"><?php esc_html_e( 'Skip Existing (Safe)', 'utility-for-generatepress' ); ?></option>
								<option value="overwrite"><?php esc_html_e( 'Overwrite Existing', 'utility-for-generatepress' ); ?></option>
								<option value="create_new"><?php esc_html_e( 'Create New Copy', 'utility-for-generatepress' ); ?></option>
							</select>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">
								<?php esc_html_e( 'Or Paste JSON Content', 'utility-for-generatepress' ); ?>
								<span style="font-weight: normal; color: #8c8f94; font-size: 12px; margin-left: 5px;">(<?php esc_html_e( 'Optional', 'utility-for-generatepress' ); ?>)</span>
							</label>
							<textarea name="utilfoge_import_paste" rows="4" style="width: 100%; font-family: monospace; font-size: 12px; box-sizing: border-box;" placeholder='{"plugin": "utility-for-generatepress", ...}'></textarea>
						</div>
						
						<div style="margin-top: auto;">
							<button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to import? This action may overwrite existing settings or data based on your conflict resolution choice.', 'utility-for-generatepress' ); ?>');"><?php esc_html_e( 'Import', 'utility-for-generatepress' ); ?></button>
							<div class="utilfoge-status-msg" style="display:none; color: #46b450; font-size: 12px; margin-top: 5px; font-weight: 500;"></div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var dashSection = document.querySelector('.utilfoge-gp-export-import-container');
			if (dashSection) {
				dashSection.querySelectorAll('form').forEach(function(form) {
					form.addEventListener('submit', function(e) {
						var btn = form.querySelector('button[type="submit"]');
						var statusMsg = form.querySelector('.utilfoge-status-msg');
						var isExport = form.id === 'utilfoge-export-form';
						var originalText = btn.innerText;

						if (isExport) {
							btn.innerText = '<?php echo esc_js( __( 'Exported', 'utility-for-generatepress' ) ); ?>';
							if (statusMsg) {
								statusMsg.innerText = '<?php echo esc_js( __( 'Export successful!', 'utility-for-generatepress' ) ); ?>';
								statusMsg.style.display = 'block';
							}
							setTimeout(function() {
								btn.innerText = originalText;
								if (statusMsg) statusMsg.style.display = 'none';
							}, 3000);
						} else {
							btn.innerText = '<?php echo esc_js( __( 'Importing...', 'utility-for-generatepress' ) ); ?>';
						}
					});
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Post-import cleanup and cache regeneration.
	 */
	private function post_import_cleanup() {
		// 1. Regenerate GenerateBlocks Pro Global Styles CSS
		if ( class_exists( 'GenerateBlocks_Pro_Styles' ) ) {
			if ( method_exists( 'GenerateBlocks_Pro_Styles', 'get_styles_css' ) ) {
				GenerateBlocks_Pro_Styles::get_styles_css( false );
			}
		}

		// 2. Trigger GP Premium Dynamic CSS update
		if ( function_exists( 'generate_update_dynamic_css' ) ) {
			generate_update_dynamic_css();
		}

		// 3. Clear GenerateBlocks Free CSS cache if applicable
		if ( class_exists( 'GenerateBlocks_CSS' ) ) {
			if ( method_exists( 'GenerateBlocks_CSS', 'clear_cache' ) ) {
				GenerateBlocks_CSS::clear_cache();
			}
		}

		// 4. Update GB Pro Asset Library if we just imported it
		// (Options are already updated, but we might want to trigger internal GB hooks)
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Trigger native GenerateBlocks Pro integration.
		do_action( 'generateblocks_pro_asset_library_updated' );
	}

	/**
	 * Inject Export/Import UI into specific admin pages.
	 */
	public function inject_ui() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed_screens = array(
			'edit-gp_elements'                                 => 'gp_elements',
			'generateblocks_page_generateblocks-styles'        => 'gb_global_styles',
			'edit-wp_block'                                    => 'gb_local_patterns',
			'generateblocks_page_generateblocks-asset-library' => 'gb_asset_library',
			'generateblocks_page_generateblocks-conditions'    => 'gb_conditions',
			'generateblocks_page_generateblocks-overlay-panels' => 'gb_overlays',
			'appearance_page_generatepress-font-library'       => 'font_library',
		);

		// Fallback for older Font Library URL structures if they exist.
		if ( class_exists( 'GeneratePress_Pro_Font_Library' ) ) {
			$allowed_screens[ 'edit-' . GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT ] = 'font_library';
		}

		if ( ! isset( $allowed_screens[ $screen->id ] ) ) {
			return;
		}

		$type_key = $allowed_screens[ $screen->id ];
		$content_types = self::get_content_types();

		if ( ! isset( $content_types[ $type_key ] ) ) {
			return;
		}

		$ct = $content_types[ $type_key ];
		$is_full_width = in_array( $type_key, array( 'gp_elements', 'font_library', 'gb_local_patterns' ), true );
		
		if ( $is_full_width ) {
			$box_style = '';
		} elseif ( 'gb_asset_library' === $type_key ) {
			$box_style = 'max-width: 750px; margin-left: auto; margin-right: auto;';
		} else {
			// Global Styles, Overlays, Conditions
			$box_style = 'max-width: 1200px; margin: 20px auto 0;';
		}
		?>
		<style>
			.utilfoge-gp-injected-box {
				background: #fff;
				border: 1px solid #dcdcde;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				border-radius: 4px;
				margin-bottom: 30px;
			}
			/* Remove top margin if the wrapper already gives top margin, except on full width where we need it */
			<?php if ( $is_full_width ) : ?>
			.utilfoge-gp-injected-box {
				margin-top: 30px;
			}
			<?php endif; ?>
			.utilfoge-gp-injected-header {
				border-bottom: 1px solid #dcdcde;
				padding: 15px 20px;
				margin: 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
			}
			.utilfoge-gp-injected-content {
				padding: 20px;
			}
			.utilfoge-injected-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
				gap: 30px;
			}
			@media (max-width: 782px) {
				.utilfoge-injected-grid {
					grid-template-columns: 1fr;
				}
				.utilfoge-injected-import-col {
					border-left: none !important;
					padding-left: 0 !important;
					border-top: 1px solid #eee;
					padding-top: 20px;
				}
			}
		</style>
		<div id="utilfoge-injected-export-import" class="utilfoge-gp-injected-box" style="display: none; <?php echo esc_attr( $box_style ); ?>">
			<h2 class="utilfoge-gp-injected-header">
				<?php
				/* translators: %s: content type label */
				printf( esc_html__( 'Export / Import Utility for %s', 'utility-for-generatepress' ), esc_html( $ct['label'] ) );
				?>
			</h2>

			<div class="utilfoge-gp-injected-content">
				<div class="utilfoge-injected-grid">
					<!-- Export -->
					<div>
						<h3 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Export', 'utility-for-generatepress' ); ?></h3>
						<p style="color: #646970; margin-bottom: 15px; font-size: 13px;">
							<?php
							/* translators: %s: content type label */
							printf( esc_html__( 'Export your %s to a JSON file.', 'utility-for-generatepress' ), esc_html( $ct['label'] ) );
							?>
						</p>
						<form method="post">
							<?php wp_nonce_field( 'utilfoge_export_action_nonce', '_wpnonce_export' ); ?>
							<input type="hidden" name="utilfoge_export_action" value="export" />
							<input type="hidden" name="utilfoge_export_types[]" value="<?php echo esc_attr( $type_key ); ?>" />
							<button type="submit" class="<?php echo esc_attr( $is_full_width ? 'button button-primary' : 'components-button is-primary' ); ?>" style="<?php echo esc_attr( $is_full_width ? '' : 'padding: 4px 16px; min-height: 32px; font-size: 13px; border-radius: 3px;' ); ?>"><?php esc_html_e( 'Export', 'utility-for-generatepress' ); ?></button>
							<span style="margin-left: 10px; font-size: 13px; color: #646970;">(<?php echo esc_html( self::count_items( $type_key ) ); ?> <?php esc_html_e( 'items', 'utility-for-generatepress' ); ?>)</span>
							<div class="utilfoge-status-msg" style="display:none; color: #46b450; font-size: 12px; margin-top: 5px; font-weight: 500;"></div>
						</form>
					</div>

					<!-- Import -->
					<div class="utilfoge-injected-import-col" style="border-left: 1px solid #eee; padding-left: 30px;">
						<h3 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Import', 'utility-for-generatepress' ); ?></h3>
						<p style="color: #646970; margin-bottom: 15px; font-size: 13px;">
							<?php
							/* translators: %s: content type label */
							printf( esc_html__( 'Import %s from a utilfoge JSON file.', 'utility-for-generatepress' ), esc_html( $ct['label'] ) );
							?>
						</p>
						<form method="post" enctype="multipart/form-data">
							<?php wp_nonce_field( 'utilfoge_import_action_nonce', '_wpnonce_import' ); ?>
							<input type="hidden" name="utilfoge_import_action" value="import" />
							<div style="margin-bottom: 15px;">
								<input type="file" name="utilfoge_import_file" accept=".json" required style="max-width: 100%; padding: 5px; border: 1px dashed #c3c4c7; background: #f6f7f7; width: 100%; box-sizing: border-box; font-size: 13px;" />
							</div>
							<div style="margin-bottom: 15px;">
								<label style="display: block; margin-bottom: 5px; font-weight: 600; color: #1d2327; font-size: 13px;"><?php esc_html_e( 'If items already exist:', 'utility-for-generatepress' ); ?></label>
								<select name="utilfoge_conflict_mode" style="width: 100%; max-width: 100%; font-size: 13px; <?php echo esc_attr( $is_full_width ? '' : 'min-height: 32px;' ); ?>">
									<option value="skip"><?php esc_html_e( 'Skip Existing', 'utility-for-generatepress' ); ?></option>
									<option value="overwrite"><?php esc_html_e( 'Overwrite', 'utility-for-generatepress' ); ?></option>
									<option value="create_new"><?php esc_html_e( 'Create New Copy', 'utility-for-generatepress' ); ?></option>
								</select>
							</div>
							<button type="submit" class="<?php echo esc_attr( $is_full_width ? 'button button-primary' : 'components-button is-primary' ); ?>" style="<?php echo esc_attr( $is_full_width ? '' : 'padding: 4px 16px; min-height: 32px; font-size: 13px; border-radius: 3px;' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to import?', 'utility-for-generatepress' ); ?>');"><?php esc_html_e( 'Import', 'utility-for-generatepress' ); ?></button>
							<div class="utilfoge-status-msg" style="display:none; color: #46b450; font-size: 12px; margin-top: 5px; font-weight: 500;"></div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var wrap = document.querySelector('.wrap') || document.querySelector('.gp-font-library') || document.getElementById('wpbody-content');
			var box = document.getElementById('utilfoge-injected-export-import');
			
			if ( wrap && box ) {
				wrap.appendChild(box);
				box.style.display = 'block';

				// Handle button feedback
				var forms = box.querySelectorAll('form');
				forms.forEach(function(form) {
					form.addEventListener('submit', function(e) {
						var btn = form.querySelector('button[type="submit"]');
						var statusMsg = form.querySelector('.utilfoge-status-msg');
						var isExport = form.querySelector('input[name="utilfoge_export_action"]');
						var originalText = btn.innerText;

						if (isExport) {
							btn.innerText = '<?php echo esc_js( __( 'Exported', 'utility-for-generatepress' ) ); ?>';
							if (statusMsg) {
								statusMsg.innerText = '<?php echo esc_js( __( 'Export successful!', 'utility-for-generatepress' ) ); ?>';
								statusMsg.style.display = 'block';
							}
							setTimeout(function() {
								btn.innerText = originalText;
								if (statusMsg) statusMsg.style.display = 'none';
							}, 3000);
						} else {
							btn.innerText = '<?php echo esc_js( __( 'Importing...', 'utility-for-generatepress' ) ); ?>';
						}
					});
				});
			}
		});
		</script>
		<?php
	}
}
