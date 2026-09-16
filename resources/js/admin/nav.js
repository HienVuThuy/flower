/* Điều hướng quản trị KHÔNG TẢI LẠI CẢ TRANG. */

const KHUNG = '[data-admin-content]';

let khoiTaoLai = () => {};

export function initAdminNav(bootLaiUi) {
    const khung = document.querySelector(KHUNG);

    if (!khung) {
        return;
    }

    if (typeof bootLaiUi === 'function') {
        khoiTaoLai = bootLaiUi;
    }

    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-admin-link]');

        if (!link || !nhanDuoc(e, link)) {
            return;
        }

        e.preventDefault();
        diToi(link.href, true);
    });

    window.addEventListener('popstate', () => {
        diToi(window.location.href, false);
    });
}

function nhanDuoc(e, link) {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.classList.contains('is-disabled')) {
        return false;
    }

    return new URL(link.href, window.location.origin).origin === window.location.origin;
}

async function diToi(url, ghiLichSu) {
    const khung = document.querySelector(KHUNG);

    if (!khung) {
        window.location.href = url;

        return;
    }

    khung.setAttribute('aria-busy', 'true');
    khung.classList.add('is-loading');

    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
        });

        if (!res.ok || res.redirected) {
            window.location.href = res.redirected ? res.url : url;

            return;
        }

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const moi = doc.querySelector(KHUNG);

        if (!moi) {
            window.location.href = url;

            return;
        }

        khung.innerHTML = moi.innerHTML;
        document.title = doc.title;

        danhDauTheoMayChu(doc, url);

        if (ghiLichSu) {
            window.history.pushState({}, '', url);
        }

        window.scrollTo({ top: 0, behavior: 'instant' });

        khoiTaoLai();
    } catch (err) {
        window.location.href = url;
    } finally {
        khung.removeAttribute('aria-busy');
        khung.classList.remove('is-loading');
    }
}

function danhDauTheoMayChu(doc, url) {
    const sangTrenMayChu = doc.querySelectorAll('.admin-sidebar [data-admin-link].is-active');

    if (sangTrenMayChu.length === 0 && !doc.querySelector('.admin-sidebar')) {
        danhDauDangXem(url);

        return;
    }

    const duongSang = new Set(
        [...sangTrenMayChu].map((a) => new URL(a.href, window.location.origin).pathname),
    );

    document.querySelectorAll('.admin-sidebar [data-admin-link]').forEach((link) => {
        const dangXem = duongSang.has(new URL(link.href, window.location.origin).pathname);

        link.classList.toggle('is-active', dangXem);
        link.toggleAttribute('aria-current', dangXem);
    });
}

function danhDauDangXem(url) {
    const duong = new URL(url, window.location.origin).pathname;

    document.querySelectorAll('[data-admin-link]').forEach((link) => {
        const cua = new URL(link.href, window.location.origin).pathname;

        const dangXem = cua === duong
            || (cua !== '/admin' && duong.startsWith(cua + '/'));

        link.classList.toggle('is-active', dangXem);
        link.toggleAttribute('aria-current', dangXem);
    });
}
