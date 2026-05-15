// FOUC Prevention & Logic for UTILGP Utility
(function() {
    var isDark = false;
    try {
        isDark = localStorage.getItem('utilgp-dark-mode') === 'true' || (!('utilgp-dark-mode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    } catch(e) {}

    if (typeof window.utilgpIsDark === 'undefined') {
        window.utilgpIsDark = isDark;
    }

    if (window.utilgpIsDark) {
        document.documentElement.classList.add('dark');
        if (document.body) document.body.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
        if (document.body) document.body.classList.remove('dark');
    }
    
    // Event Delegation with CAPTURE PHASE (true)
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.utilgp-dark-mode-toggle');
        if (!toggle) return;
        e.preventDefault();
        e.stopPropagation();
        
        var isCurrentlyDark = window.utilgpIsDark;
        var willBeDark = !isCurrentlyDark;
        
        // Update State
        window.utilgpIsDark = willBeDark;
        
        if (willBeDark) {
            document.documentElement.classList.add('dark');
            if (document.body) document.body.classList.add('dark');
            try { localStorage.setItem('utilgp-dark-mode', 'true'); } catch(err){}
        } else {
            document.documentElement.classList.remove('dark');
            if (document.body) document.body.classList.remove('dark');
            try { localStorage.setItem('utilgp-dark-mode', 'false'); } catch(err){}
        }
        
        // Update all toggles on screen
        document.querySelectorAll('.utilgp-dark-mode-toggle').forEach(function(t) {
            var sun = t.querySelector('.utilgp-icon-sun');
            var moon = t.querySelector('.utilgp-icon-moon');
            if (willBeDark) {
                if(sun) sun.style.display = 'none';
                if(moon) moon.style.display = 'block';
            } else {
                if(sun) sun.style.display = 'block';
                if(moon) moon.style.display = 'none';
            }
        });
    }, true);

    // Initial Sync when DOM is ready
    var initDarkToggle = function() {
        if (isDark && document.body && !document.body.classList.contains('dark')) {
            document.body.classList.add('dark');
        }
        
        var isDarkActive = document.documentElement.classList.contains('dark');
        document.querySelectorAll('.utilgp-dark-mode-toggle').forEach(function(t) {
            var sun = t.querySelector('.utilgp-icon-sun');
            var moon = t.querySelector('.utilgp-icon-moon');
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
