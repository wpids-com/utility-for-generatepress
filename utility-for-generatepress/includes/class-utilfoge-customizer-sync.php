<?php
/**
 * Customizer Sync Class.
 *
 * Syncs GeneratePress Customizer values and Utility variables with
 * GenerateBlocks Pro Design Tokens (GB Pro 2.8.0+).
 *
 * - GeneratePress Global Colors  <-> Design Tokens (two-way)
 * - Utility color variants       ->  Design Tokens (one-way)
 * - Utility fluid typography     ->  Design Tokens (one-way)
 *
 * Tokens created by this module are tracked in the `utilfoge_customizer_sync_map`
 * option. Tokens that already exist and were not created by this module are never touched.
 * Nothing is ever deleted from GenerateBlocks: a source that disappears is simply
 * released from the sync map.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Customizer_Sync {

	const MAP_OPTION    = 'utilfoge_customizer_sync_map';
	const STATUS_OPTION = 'utilfoge_customizer_sync_status';
	const MIN_GB_PRO    = '2.8.0';

	const CAT_GP     = 'GeneratePress';
	const CAT_COLORS = 'Variants';
	const CAT_FLUID  = 'Fluid Typography';

	/**
	 * Re-entrancy guard (prevents Customizer <-> GB Pro loops).
	 *
	 * @var bool
	 */
	private static $syncing = false;

	public function init() {
		add_action( 'customize_save_after', array( $this, 'on_customizer_save' ), 999 );
		add_action( 'rest_after_insert_gblocks_styles', array( $this, 'on_tokens_saved' ), 20, 3 );
		add_action( 'admin_post_utilfoge_customizer_sync_now', array( $this, 'handle_sync_now' ) );
	}

	/**
	 * Whether GenerateBlocks Pro with Design Tokens is available.
	 */
	public static function is_gb_pro_available() {
		if ( ! class_exists( 'GenerateBlocks_Pro_Styles_Root' ) || ! class_exists( 'GenerateBlocks_Pro_Design_Tokens' ) ) {
			return false;
		}

		if ( defined( 'GENERATEBLOCKS_PRO_VERSION' ) && version_compare( GENERATEBLOCKS_PRO_VERSION, self::MIN_GB_PRO, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Last sync status for the dashboard.
	 *
	 * @return array
	 */
	public static function get_status() {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	// ─────────────────────────────────────────
	// Triggers
	// ─────────────────────────────────────────

	public function on_customizer_save() {
		if ( self::$syncing || ! self::is_gb_pro_available() ) {
			return;
		}
		$this->sync_to_tokens();
	}

	public function handle_sync_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized action.', 'utility-for-generatepress' ) );
		}
		check_admin_referer( 'utilfoge_customizer_sync_now' );

		if ( self::is_gb_pro_available() ) {
			$this->sync_to_tokens();
		}

		wp_safe_redirect( admin_url( 'themes.php?page=utilfoge-utility' ) );
		exit;
	}

	/**
	 * GenerateBlocks Pro saved the :root style: push token color changes back to the Customizer.
	 *
	 * @param WP_Post         $post     Saved post.
	 * @param WP_REST_Request $request  Request.
	 * @param bool            $creating Whether the post was created.
	 */
	public function on_tokens_saved( $post, $request, $creating ) {
		if ( $creating || self::$syncing || ! self::is_gb_pro_available() || ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! GenerateBlocks_Pro_Styles_Root::is_root_post( (int) $post->ID ) ) {
			return;
		}

		$map = get_option( self::MAP_OPTION, array() );
		if ( empty( $map['gp'] ) || ! is_array( $map['gp'] ) ) {
			return;
		}

		self::$syncing = true;

		$state  = GenerateBlocks_Pro_Styles_Root::get_state( $post );
		$styles = is_array( $state['styles'] ) ? $state['styles'] : array();

		$gp_option = 'generate_settings';
		$settings  = $this->get_raw_gp_settings( $gp_option );
		$colors    = $this->get_gp_colors( $settings );
		$changed   = 0;

		foreach ( $colors as $i => $color ) {
			$slug = isset( $color['slug'] ) ? $color['slug'] : '';
			$name = '--' . $slug;

			if ( ! $slug || ! in_array( $name, $map['gp'], true ) || ! isset( $styles[ $name ] ) || ! is_string( $styles[ $name ] ) ) {
				continue;
			}

			$value = trim( $styles[ $name ] );
			if ( $value !== $color['color'] && GenerateBlocks_Pro_Design_Tokens::is_valid_value_for_type( $value, 'color' ) ) {
				$colors[ $i ]['color'] = $value;
				$changed++;
			}
		}

		if ( $changed ) {
			$settings['global_colors'] = $colors;
			update_option( $gp_option, $settings );
			delete_option( 'generate_dynamic_css_output' );
			delete_option( 'generate_dynamic_css_cached_version' );
			$this->set_status( 'ok', sprintf(
				/* translators: %d: number of colors */
				_n( '%d color updated from Design Tokens.', '%d colors updated from Design Tokens.', $changed, 'utility-for-generatepress' ),
				$changed
			) );
		}

		self::$syncing = false;
	}

	// ─────────────────────────────────────────
	// Sync: Customizer / Utility -> Design Tokens
	// ─────────────────────────────────────────

	/**
	 * Push all sources to Design Tokens.
	 *
	 * @return bool True when the sync completed (even if nothing changed).
	 */
	public function sync_to_tokens() {
		if ( self::$syncing || ! self::is_gb_pro_available() ) {
			return false;
		}

		if ( ! GenerateBlocks_Pro_Design_Tokens::can_manage_tokens() ) {
			$this->set_status( 'error', __( 'Your account cannot manage Design Tokens.', 'utility-for-generatepress' ) );
			return false;
		}

		self::$syncing = true;
		$result        = $this->run_sync();
		self::$syncing = false;

		return $result;
	}

	private function run_sync() {
		$resolved = GenerateBlocks_Pro_Styles_Root::resolve( true );

		if ( 'conflict' === $resolved['status'] ) {
			$this->set_status( 'error', __( 'GenerateBlocks has more than one :root style. Resolve it in the GenerateBlocks dashboard first.', 'utility-for-generatepress' ) );
			return false;
		}

		if ( 'absent' === $resolved['status'] ) {
			$created = GenerateBlocks_Pro_Styles_Root::create_for_dashboard();
			if ( is_wp_error( $created ) ) {
				$this->set_status( 'error', $created->get_error_message() );
				return false;
			}
		}

		$state = GenerateBlocks_Pro_Styles_Root::read_for_dashboard();
		if ( is_wp_error( $state ) ) {
			$this->set_status( 'error', $state->get_error_message() );
			return false;
		}

		$sources = $this->collect_sources();
		$styles  = is_array( $state['styles'] ) ? $state['styles'] : array();
		$tokens  = is_array( $state['tokens'] ) ? $state['tokens'] : array();
		$map     = get_option( self::MAP_OPTION, array() );
		$map     = is_array( $map ) ? $map : array();

		$by_name = array();
		foreach ( $tokens as $i => $token ) {
			$by_name[ $token['name'] ] = $i;
		}

		$old_managed = isset( $map['all'] ) && is_array( $map['all'] ) ? $map['all'] : array();
		$managed     = array();
		$gp_names    = array();
		$created      = 0;
		$updated      = 0;
		$skipped      = 0;
		$meta_changed = false;
		$new_values   = array();

		foreach ( $sources as $name => $src ) {
			if ( ! GenerateBlocks_Pro_Design_Tokens::is_valid_token_name( $name )
				|| ! GenerateBlocks_Pro_Design_Tokens::is_valid_value_for_type( $src['value'], $src['type'] ) ) {
				continue;
			}

			if ( isset( $by_name[ $name ] ) ) {
				// Never touch a token we did not create.
				if ( ! in_array( $name, $old_managed, true ) ) {
					$skipped++;
					continue;
				}
				$token_idx   = $by_name[ $name ];
				$val_changed = ! isset( $styles[ $name ] ) || $styles[ $name ] !== $src['value'];
				$cat_changed   = isset( $tokens[ $token_idx ]['category'] ) && $tokens[ $token_idx ]['category'] !== $src['category'];
				$scope_changed = isset( $tokens[ $token_idx ]['scope'] ) && $tokens[ $token_idx ]['scope'] === array( 'color', 'backgroundColor', 'borderColor' );
				if ( $cat_changed ) {
					$tokens[ $token_idx ]['category'] = $src['category'];
					$meta_changed                      = true;
				}
				if ( $scope_changed ) {
					$tokens[ $token_idx ]['scope'] = $src['scope'];
					$meta_changed                   = true;
				}
				if ( $val_changed || $cat_changed ) {
					$updated++;
				}
			} else {
				$tokens[]           = $this->build_token( $name, $src );
				$by_name[ $name ]   = count( $tokens ) - 1;
				$created++;
			}

			$managed[] = $name;
			if ( self::CAT_GP === $src['category'] ) {
				$gp_names[] = $name;
			}

			if ( ! isset( $styles[ $name ] ) || $styles[ $name ] !== $src['value'] ) {
				$new_values[ $name ] = $src['value'];
			}
			$styles[ $name ] = $src['value'];
		}

		$map = array(
			'all' => $managed,
			'gp'  => $gp_names,
		);

		if ( empty( $new_values ) && 0 === $created && ! $meta_changed ) {
			update_option( self::MAP_OPTION, $map, false );
			$this->set_status( 'ok', __( 'Everything is already in sync.', 'utility-for-generatepress' ), $skipped );
			return true;
		}

		$css   = $this->patch_root_css( (string) $state['css'], $new_values );
		$saved = GenerateBlocks_Pro_Styles_Root::save( $styles, $css, $state['checksum'], array_values( $tokens ) );

		if ( is_wp_error( $saved ) ) {
			$this->set_status( 'error', $saved->get_error_message() );
			return false;
		}

		update_option( self::MAP_OPTION, $map, false );

		$this->set_status(
			'ok',
			sprintf(
				/* translators: 1: created tokens, 2: updated tokens */
				__( 'Synced to Design Tokens: %1$d created, %2$d updated.', 'utility-for-generatepress' ),
				$created,
				$updated
			),
			$skipped
		);

		return true;
	}

	/**
	 * Build a new token row for GenerateBlocks Pro.
	 */
	private function build_token( $name, $src ) {
		$token = array(
			'id'       => wp_generate_uuid4(),
			'name'     => $name,
			'type'     => $src['type'],
			'label'    => $src['label'],
			'scope'    => $src['scope'],
			'category' => $src['category'],
		);

		if ( 'color' === $src['type'] ) {
			$token['display'] = array( 'control' => 'colorPicker' );
		} elseif ( 'unit' === $src['type'] ) {
			$token['display'] = array( 'control' => 'unitControl' );
		}

		return $token;
	}

	/**
	 * Update or insert declarations inside the existing compiled :root CSS.
	 *
	 * GenerateBlocks Pro compiles this CSS in the browser, so we only patch the
	 * declarations we own and leave everything else untouched.
	 */
	private function patch_root_css( $css, $values ) {
		$insert = '';

		foreach ( $values as $name => $value ) {
			$pattern = '/(' . preg_quote( $name, '/' ) . '\s*:\s*)[^;}]*/';
			if ( '' !== $css && preg_match( $pattern, $css ) ) {
				$css = preg_replace_callback(
					$pattern,
					static function ( $m ) use ( $value ) {
						return $m[1] . $value;
					},
					$css,
					1
				);
			} else {
				$insert .= $name . ':' . $value . ';';
			}
		}

		if ( '' === $insert ) {
			return $css;
		}

		if ( preg_match( '/(:where\(:root\)|:root)\s*\{/', $css, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos = $m[0][1] + strlen( $m[0][0] );
			return substr( $css, 0, $pos ) . $insert . substr( $css, $pos );
		}

		return ':root{' . $insert . '}' . $css;
	}

	// ─────────────────────────────────────────
	// Sources
	// ─────────────────────────────────────────

	/**
	 * Collect every variable this module manages.
	 *
	 * @return array<string, array{value:string,type:string,label:string,scope:array,category:string}>
	 */
	private function collect_sources() {
		$options     = get_option( 'utilfoge_utility_options', array() );
		$color_scope = array( 'color', 'backgroundColor', 'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor' );
		$out         = array();

		// 1. GeneratePress Global Colors.
		$gp_option = 'generate_settings';
		$settings  = $this->get_raw_gp_settings( $gp_option );
		$gp_colors = $this->get_gp_colors( $settings );
		if ( ! empty( $gp_colors ) ) {
			foreach ( $gp_colors as $color ) {
				if ( empty( $color['slug'] ) || empty( $color['color'] ) ) {
					continue;
				}
				$out[ '--' . $color['slug'] ] = array(
					'value'    => trim( (string) $color['color'] ),
					'type'     => 'color',
					'label'    => isset( $color['name'] ) ? (string) $color['name'] : $color['slug'],
					'scope'    => $color_scope,
					'category' => self::CAT_GP,
				);
			}
		}

		// 2. Utility color variants (shades, tints, harmony).
		$color_active = isset( $options['enable_color_manager'] ) ? $options['enable_color_manager'] : true;
		$expanded     = get_option( 'utilfoge_expanded_colors', array() );
		if ( $color_active && is_array( $expanded ) ) {
			foreach ( $expanded as $set ) {
				if ( empty( $set['variables'] ) || ! is_array( $set['variables'] ) ) {
					continue;
				}
				foreach ( $set['variables'] as $var => $hex ) {
					if ( ! is_string( $var ) || ! is_string( $hex ) || isset( $out[ $var ] ) || 0 === strpos( $var, '__dark__' ) ) {
						continue;
					}
					$out[ $var ] = array(
						'value'    => trim( $hex ),
						'type'     => 'color',
						'label'    => ucwords( str_replace( '-', ' ', ltrim( $var, '-' ) ) ),
						'scope'    => $color_scope,
						'category' => self::CAT_COLORS,
					);
				}
			}
		}

		// 3. Fluid typography scale.
		$typo_active = isset( $options['enable_typography'] ) ? $options['enable_typography'] : true;
		if ( $typo_active && class_exists( 'UTILFOGE_Typography' ) && 'on' === get_option( 'utilfoge_fluid_typo_enabled', 'off' ) ) {
			$base   = get_option( 'utilfoge_typo_base_size', 16 );
			$unit   = get_option( 'utilfoge_typo_base_unit', 'px' );
			$min_vw = get_option( 'utilfoge_typo_min_vw', 320 );
			$max_vw = get_option( 'utilfoge_typo_max_vw', 1280 );
			$ratio  = get_option( 'utilfoge_typo_scale_ratio', '1.250' );
			$ratio  = ( 'custom' === $ratio ) ? floatval( get_option( 'utilfoge_typo_custom_ratio', '1.2' ) ) : floatval( $ratio );

			for ( $i = -1; $i <= 8; $i++ ) {
				$out[ '--utilfoge-step-' . $i ] = array(
					'value'    => UTILFOGE_Typography::calculate_fluid( $base, $ratio, $i, $min_vw, $max_vw, $unit ),
					'type'     => 'unit',
					'label'    => sprintf(
						/* translators: %d: step number */
						__( 'Fluid Step %d', 'utility-for-generatepress' ),
						$i
					),
					'scope'    => array( 'fontSize' ),
					'category' => self::CAT_FLUID,
				);
			}
		}

		return $out;
	}

	/**
	 * Read generate_settings without this plugin's merge filters.
	 */
	private function get_raw_gp_settings( $gp_option ) {
		$filters = array();
		global $wp_filter;
		$hook = 'option_' . $gp_option;

		if ( isset( $wp_filter[ $hook ] ) ) {
			$filters = $wp_filter[ $hook ];
			unset( $wp_filter[ $hook ] );
		}

		$settings = get_option( $gp_option, array() );

		if ( $filters ) {
			$wp_filter[ $hook ] = $filters;
		}

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Global colors from the saved option, or GeneratePress defaults when none are saved yet.
	 */
	private function get_gp_colors( $settings ) {
		if ( ! empty( $settings['global_colors'] ) && is_array( $settings['global_colors'] ) ) {
			return $settings['global_colors'];
		}

		if ( function_exists( 'generate_get_option' ) ) {
			$defaults = generate_get_option( 'global_colors' );
			return is_array( $defaults ) ? $defaults : array();
		}

		return array();
	}

	private function set_status( $state, $message, $skipped = 0 ) {
		update_option(
			self::STATUS_OPTION,
			array(
				'state'   => $state,
				'message' => $message,
				'skipped' => (int) $skipped,
				'time'    => time(),
			),
			false
		);
	}
}
