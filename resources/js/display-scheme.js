/** TRẠNG THÁI NỀN SÁNG / TỐI — nơi duy nhất đọc và ghi `data-scheme`. */
export const DisplaySchemeStore = {
    dangHien() {
        return document.documentElement.dataset.scheme === 'toi' ? 'toi' : 'sang';
    },

    dangTuDong() {
        return document.documentElement.dataset.schemeAuto === '1';
    },

    doiSang() {
        return this.dangHien() === 'toi' ? 'sang' : 'toi';
    },

    apDung(cheDo, { giuTuDong = false } = {}) {
        const el = document.documentElement;

        el.dataset.scheme = cheDo === 'toi' ? 'toi' : 'sang';

        if (el.hasAttribute('data-admin')) {
            el.setAttribute('data-bs-theme', el.dataset.scheme === 'toi' ? 'dark' : 'light');
        }

        if (giuTuDong) {
            el.dataset.schemeAuto = '1';
        } else {
            delete el.dataset.schemeAuto;
        }
    },
};
