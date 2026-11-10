/**
 * UTILFOGE Dark Mode - FOUC Prevention & Toggle Logic
 *
 * Priority Order untuk menentukan mode awal:
 * 1. localStorage (pilihan tersimpan pengunjung) - selalu dihormati jika ada
 * 2. defaultMode dari Customizer:
 *    - 'dark'   : Paksa dark mode sebagai default
 *    - 'light'  : Paksa light mode sebagai default
 *    - 'system' : Ikuti preferensi OS (prefers-color-scheme)
 *    - 'user_choice' (default): Biarkan sistem (sebelumnya ikut OS juga)
 */
(function () {
	var config       = window.UTILFOGEDarkConfig || {};
	var defaultMode  = config.defaultMode || 'user_choice';
	var storageKey   = 'utilfoge-dark-mode';
	var isDark       = false;

	try {
		var stored = localStorage.getItem( storageKey );

		if ( stored !== null ) {
			// Pengunjung sudah pernah memilih → hormati pilihannya
			isDark = stored === 'true';
		} else {
			// Belum ada pilihan tersimpan → gunakan defaultMode dari Customizer
			if ( 'dark' === defaultMode ) {
				isDark = true;
			} else if ( 'system' === defaultMode ) {
				isDark = window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
			} else {
				// default to light
				isDark = false;
			}
		}
	} catch ( e ) {}

	// Simpan di global agar bisa diakses script lain
	window.UTILFOGEIsDark = isDark;

	// Terapkan ke <html> langsung (mencegah FOUC)
	if ( isDark ) {
		document.documentElement.classList.add( 'dark' );
		if ( document.body ) document.body.classList.add( 'dark' );
	} else {
		document.documentElement.classList.remove( 'dark' );
		if ( document.body ) document.body.classList.remove( 'dark' );
	}

	/**
	 * Sinkronkan tampilan ikon semua toggle di halaman.
	 *
	 * @param {boolean} darkActive
	 */
	function syncToggleIcons( darkActive ) {
		document.querySelectorAll( '.utilfoge-dark-mode-toggle' ).forEach( function ( t ) {
			var sun  = t.querySelector( '.utilfoge-icon-sun' );
			var moon = t.querySelector( '.utilfoge-icon-moon' );
			if ( darkActive ) {
				if ( sun )  sun.style.display  = 'none';
				if ( moon ) moon.style.display = 'block';
			} else {
				if ( sun )  sun.style.display  = 'block';
				if ( moon ) moon.style.display = 'none';
			}
		} );
	}

	// Event Delegation - capture phase agar lebih cepat dari handler lain
	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '.utilfoge-dark-mode-toggle' );
		if ( ! toggle ) return;
		e.preventDefault();
		e.stopPropagation();

		var willBeDark  = ! window.UTILFOGEIsDark;
		window.UTILFOGEIsDark = willBeDark;

		if ( willBeDark ) {
			document.documentElement.classList.add( 'dark' );
			if ( document.body ) document.body.classList.add( 'dark' );
			try { localStorage.setItem( storageKey, 'true' ); } catch ( err ) {}
		} else {
			document.documentElement.classList.remove( 'dark' );
			if ( document.body ) document.body.classList.remove( 'dark' );
			try { localStorage.setItem( storageKey, 'false' ); } catch ( err ) {}
		}

		syncToggleIcons( willBeDark );
	}, true );

	// Initial sync setelah DOM siap
	function initDarkToggle() {
		if ( isDark && document.body && ! document.body.classList.contains( 'dark' ) ) {
			document.body.classList.add( 'dark' );
		}
		syncToggleIcons( document.documentElement.classList.contains( 'dark' ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDarkToggle );
	} else {
		initDarkToggle();
	}

	// Jika mode 'system', pantau perubahan OS preference secara real-time
	// (hanya berlaku jika belum ada pilihan tersimpan dari pengunjung)
	if ( 'system' === defaultMode ) {
		try {
			window.matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', function ( e ) {
				// Hanya ikut sistem jika pengunjung belum pernah memilih secara manual
				var stored = localStorage.getItem( storageKey );
				if ( stored !== null ) return; // sudah ada pilihan manual → abaikan

				window.UTILFOGEIsDark = e.matches;
				if ( e.matches ) {
					document.documentElement.classList.add( 'dark' );
					if ( document.body ) document.body.classList.add( 'dark' );
				} else {
					document.documentElement.classList.remove( 'dark' );
					if ( document.body ) document.body.classList.remove( 'dark' );
				}
				syncToggleIcons( e.matches );
			} );
		} catch ( e ) {}
	}
})();
