/* Thanh khuyến mại đóng được, và nhớ là đã đóng. */

const STORAGE_KEY = 'announcement.dismissed';

function read() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function write(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
    }
}

export function initAnnouncement() {
    const bar = document.querySelector('[data-announcement]');

    if (!bar) {
        return;
    }

    const key = bar.dataset.announcement;

    if (read() === key) {
        return;
    }

    bar.hidden = false;

    bar.querySelector('[data-announcement-close]')?.addEventListener('click', () => {
        bar.hidden = true;
        write(key);
    });
}
