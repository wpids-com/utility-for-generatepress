<?php
/**
 * Color Manager Class.
 * Injeksi CSS variabel untuk Dark Mode.
 * 
 * PENTING: Hook priority 9999 agar output CSS ini SELALU
 * muncul SETELAH GeneratePress dan GenerateBlocks selesai
 * mencetak semua CSS mereka, sehingga override kita menang.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Color_Manager {

	public function init() {
		// Inject into Frontend — priority 9999 agar tampil SETELAH GP
		add_action( 'wp_head', array( $this, 'inject_dark_mode_css' ), 9999 );
		// Inject into Backend Editor
		add_action( 'admin_head', array( $this, 'inject_dark_mode_css' ), 9999 );
	}

	/**
	 * Ambil warna dark yang sudah di-set user,
	 * atau gunakan fallback default yang sudah terbukti bagus.
	 */
	private function get_dark_colors() {
		$dark_colors = get_theme_mod( 'wpids_dark_global_colors' );

		if ( is_array( $dark_colors ) && ! empty( $dark_colors ) ) {
			return $dark_colors;
		}

		// Fallback default — digunakan saat belum pernah Publish dari Customizer
		return array(
			array( 'slug' => 'contrast',   'color' => '#f9fafb' ),
			array( 'slug' => 'contrast-2', 'color' => '#e5e7eb' ),
			array( 'slug' => 'contrast-3', 'color' => '#9ca3af' ),
			array( 'slug' => 'base',       'color' => '#374151' ),
			array( 'slug' => 'base-2',     'color' => '#1f2937' ),
			array( 'slug' => 'base-3',     'color' => '#111827' ),
			array( 'slug' => 'accent',     'color' => '#60a5fa' ),
		);
	}

	/**
	 * Bangun map slug => color untuk akses cepat.
	 */
	private function build_color_map( $colors ) {
		$map = array();
		foreach ( $colors as $c ) {
			if ( ! empty( $c['slug'] ) && ! empty( $c['color'] ) ) {
				$map[ $c['slug'] ] = $c['color'];
			}
		}
		return $map;
	}

	public function inject_dark_mode_css() {
		$dark_colors = $this->get_dark_colors();
		$color_map   = $this->build_color_map( $dark_colors );
		$color_count = count( $color_map );

		// Bangun CSS variable overrides
		$var_css = '';
		foreach ( $color_map as $slug => $hex ) {
			$var_css .= "\t\t\t\t--" . esc_attr( $slug ) . ": " . esc_attr( $hex ) . " !important;\n";
		}

		// Ambil nilai spesifik untuk structural CSS (dengan hardcoded fallback)
		$base3    = isset( $color_map['base-3'] )     ? $color_map['base-3']     : '#111827';
		$base2    = isset( $color_map['base-2'] )     ? $color_map['base-2']     : '#1f2937';
		$base     = isset( $color_map['base'] )       ? $color_map['base']       : '#374151';
		$contrast = isset( $color_map['contrast'] )   ? $color_map['contrast']   : '#f9fafb';
		$contrast2= isset( $color_map['contrast-2'] ) ? $color_map['contrast-2'] : '#e5e7eb';
		$contrast3= isset( $color_map['contrast-3'] ) ? $color_map['contrast-3'] : '#9ca3af';
		$accent   = isset( $color_map['accent'] )     ? $color_map['accent']     : '#60a5fa';

		?>
		<!-- WPIDS Dark Mode CSS | <?php echo (int) $color_count; ?> colors loaded | priority 9999 -->
		<style id="wpids-dark-mode-vars">
			/*
			 * Layer 1: CSS Variable Override
			 * Menimpa variabel :root GP agar semua elemen yang menggunakan var() ikut berubah.
			 */
			body.dark {
<?php echo wp_strip_all_tags( $var_css ); ?>
			}

			/*
			 * Layer 2: Structural Override (Hardcoded Hex Fallback)
			 * Untuk elemen GP yang TIDAK menggunakan var() tapi langsung pakai hex statis.
			 * Menggunakan hex langsung agar tidak bergantung pada cascade variabel.
			 */
			body.dark,
			body.dark #page,
			body.dark .site-header,
			body.dark .inside-header,
			body.dark .main-navigation,
			body.dark .main-navigation .main-nav ul li a,
			body.dark .navigation-search,
			body.dark .menu-toggle,
			body.dark .site-footer,
			body.dark .site-info,
			body.dark .inside-article,
			body.dark .page-hero,
			body.dark .sidebar .widget,
			body.dark .comments-area,
			body.dark .comment-body,
			body.dark .separate-containers .inside-article,
			body.dark .separate-containers .sidebar .widget,
			body.dark .separate-containers .page-header,
			body.dark .separate-containers .comment-respond {
				background-color: <?php echo esc_attr( $base3 ); ?> !important;
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			/* Body dan content area — sedikit lebih terang (base-2) */
			body.dark .site-content,
			body.dark #content,
			body.dark .one-container .site-content {
				background-color: <?php echo esc_attr( $base2 ); ?> !important;
			}

			/* Nav link colors */
			body.dark .main-navigation .main-nav ul li a,
			body.dark .main-navigation .menu-toggle {
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			/* Headings */
			body.dark h1, body.dark h2, body.dark h3, 
			body.dark h4, body.dark h5, body.dark h6,
			body.dark .widget-title,
			body.dark .entry-title,
			body.dark .entry-title a,
			body.dark .page-title,
			body.dark .comments-title,
			body.dark .comment-reply-title {
				color: <?php echo esc_attr( $contrast ); ?> !important;
			}

			/* Links */
			body.dark a {
				color: <?php echo esc_attr( $accent ); ?> !important;
			}
			body.dark a:hover {
				color: <?php echo esc_attr( $contrast2 ); ?> !important;
			}

			/* Muted text */
			body.dark .entry-meta,
			body.dark .entry-meta a,
			body.dark .post-navigation .nav-previous a,
			body.dark .post-navigation .nav-next a,
			body.dark .cat-links, 
			body.dark .tag-links {
				color: <?php echo esc_attr( $contrast3 ); ?> !important;
			}

			/* Input & Form */
			body.dark input,
			body.dark textarea,
			body.dark select {
				background-color: <?php echo esc_attr( $base2 ); ?> !important;
				color: <?php echo esc_attr( $contrast ); ?> !important;
				border-color: <?php echo esc_attr( $contrast3 ); ?> !important;
			}

			/* Buttons */
			body.dark button:not(.wpids-dark-mode-toggle),
			body.dark .button,
			body.dark input[type="submit"],
			body.dark .wp-block-button__link {
				background-color: <?php echo esc_attr( $accent ); ?> !important;
				color: <?php echo esc_attr( $base3 ); ?> !important;
			}

			/* Borders & Separators */
			body.dark hr,
			body.dark .sidebar .widget,
			body.dark .inside-article {
				border-color: <?php echo esc_attr( $base ); ?> !important;
			}

			/* Footer specifics */
			body.dark .site-info {
				background-color: <?php echo esc_attr( $base3 ); ?> !important;
				color: <?php echo esc_attr( $contrast3 ); ?> !important;
			}
			body.dark .site-info a {
				color: <?php echo esc_attr( $contrast2 ); ?> !important;
			}
		</style>
		<?php
	}
}
