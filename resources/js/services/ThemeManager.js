const THEMES = [
    { id: 'default', name: 'Dark Tech (Default)' },
    { id: 'cyberpunk', name: 'Cyberpunk Neon' },
    { id: 'hacker', name: 'Matrix Terminal' },
    { id: 'light', name: 'Light Mode (Clean)' }
];

class ThemeManager {
    constructor() {
        this.theme = localStorage.getItem('server_tycoon_theme') || 'default';
        this.applyTheme(this.theme);
    }

    getAvailableThemes() {
        return THEMES;
    }

    getCurrentTheme() {
        return this.theme;
    }

    setTheme(themeId) {
        if (!THEMES.find(t => t.id === themeId)) return;

        this.theme = themeId;
        localStorage.setItem('server_tycoon_theme', themeId);
        this.applyTheme(themeId);
    }

    applyTheme(themeId) {
        if (themeId === 'default') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', themeId);
        }
    }
}

export default new ThemeManager();
