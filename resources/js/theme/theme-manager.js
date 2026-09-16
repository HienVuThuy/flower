/** ThemeManager — cầu nối giữa `data-theme` trên <html> và hiệu ứng theo mùa (Layer 3). */

const EFFECT_LOADERS = {
    noel: () => import('./effects/noel-effect.js'),
    tet: () => import('./effects/tet-effect.js'),
    valentine: () => import('./effects/valentine-effect.js'),
};

class ThemeManager {
    constructor() {
        this.currentEffect = null;
        this.currentTheme = null;

        this.token = 0;
    }

    async applyTheme(theme, effectName = undefined) {
        if (theme === this.currentTheme) return;

        const myToken = ++this.token;

        if (this.currentEffect) {
            this.currentEffect.destroy();
            this.currentEffect = null;
        }

        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;

        const key = effectName === undefined ? theme : effectName;
        const loader = key ? EFFECT_LOADERS[key] : null;

        if (!loader) return;

        try {
            const module = await loader();

            if (myToken !== this.token) return;

            const EffectClass = Object.values(module)[0];
            this.currentEffect = new EffectClass();
            this.currentEffect.init();
            this.currentEffect.enable();
        } catch (error) {
            console.warn('Không tải được hiệu ứng theme:', key, error);
        }
    }

    boot() {
        const root = document.documentElement;

        if (root.hasAttribute('data-admin')) {
            return;
        }

        const theme = root.getAttribute('data-theme') || 'default';
        const effect = root.getAttribute('data-theme-effect') || null;

        this.currentTheme = null;
        this.applyTheme(theme, effect);
    }
}

window.ThemeManager = new ThemeManager();
window.ThemeManager.boot();
