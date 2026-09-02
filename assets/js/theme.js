// Theme Switcher Controller for LOCK & ROOM (L n' R)
// Default theme: 'light'

(function() {
    // 1. Immediately apply saved theme before DOM renders (avoids flash)
    const savedTheme = localStorage.getItem('lockroom_theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();

function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('lockroom_theme', isDark ? 'dark' : 'light');
    updateThemeUI(isDark);
}

function updateThemeUI(isDark) {
    if (typeof isDark === 'undefined') {
        isDark = document.documentElement.classList.contains('dark');
    }

    // Update icons
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
        if (isDark) {
            icon.className = 'fa-solid fa-sun text-amber-400 text-sm theme-toggle-icon';
        } else {
            icon.className = 'fa-solid fa-moon text-slate-700 text-sm theme-toggle-icon';
        }
    });

    // Update tooltips or text labels if any
    document.querySelectorAll('.theme-toggle-label').forEach(label => {
        label.innerText = isDark ? 'Mode Terang' : 'Mode Gelap';
    });

    // Update toggle switch indicator positions if switch style used
    document.querySelectorAll('.theme-switch-indicator').forEach(indicator => {
        if (isDark) {
            indicator.style.transform = 'translateX(20px)';
            indicator.parentElement.classList.add('bg-indigo-600');
            indicator.parentElement.classList.remove('bg-slate-300');
        } else {
            indicator.style.transform = 'translateX(0px)';
            indicator.parentElement.classList.remove('bg-indigo-600');
            indicator.parentElement.classList.add('bg-slate-300');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateThemeUI();
});
