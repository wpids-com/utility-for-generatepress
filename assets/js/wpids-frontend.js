/**
 * WPIDS Utility Frontend Script
 */

document.addEventListener('DOMContentLoaded', () => {
    
    const toggleButtons = document.querySelectorAll('.wpids-dark-mode-toggle');

    // Function to set theme
    const setTheme = (isDark) => {
        if (isDark) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('wpids-dark-mode', 'true');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('wpids-dark-mode', 'false');
        }
    };

    // Attach click event to all toggles on the page
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const isCurrentlyDark = document.documentElement.classList.contains('dark');
            setTheme(!isCurrentlyDark);
        });
    });

});
