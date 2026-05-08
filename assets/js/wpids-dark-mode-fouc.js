// FOUC Prevention & Logic for WPIDS Utility
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
    
    // Event Delegation with CAPTURE PHASE (true)
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.wpids-dark-mode-toggle');
        if (!toggle) return;
        e.preventDefault();
        e.stopPropagation();
        
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
        
        // Update all toggles on screen
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

    // Initial Sync when DOM is ready
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
