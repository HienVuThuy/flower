/* GÓC CÂY — PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. */

const TONG_TOI_DA_MB = 38;

function token() {
    const o = document.querySelector('input[name="_token"]');

    return o ? o.value : '';
}

function doiIcon(nut, thuocTinh, ten) {
    const use = nut.querySelector(`[${thuocTinh}] use`);

    if (use) {
        use.setAttribute('href', `#i-${ten}`);
    }
}

const CAM_XUC = {
    thich: { icon: 'hand-thumbs-up-fill', nhan: 'Thích' },
    yeu: { icon: 'heart-fill', nhan: 'Yêu thích' },
    haha: { icon: 'emoji-laughing-fill', nhan: 'Haha' },
    wow: { icon: 'emoji-surprise-fill', nhan: 'Wow' },
    buon: { icon: 'emoji-frown-fill', nhan: 'Buồn' },
};

function nhanThich(form, data) {
    const khoi = form.closest('.cam-xuc');
    const nut = khoi?.querySelector('[data-thich]') || form.querySelector('[data-thich]');

    const khung = form.closest('[data-binh-luan]') || form.closest('[data-bai]');

    if (!nut) return;

    const loai = data.thich && CAM_XUC[data.loai] ? data.loai : null;

    nut.classList.toggle('is-on', !!loai);
    Object.keys(CAM_XUC).forEach((k) => nut.classList.remove(`cam-xuc--${k}`));
    if (loai) nut.classList.add(`cam-xuc--${loai}`);
    nut.setAttribute('aria-pressed', loai ? 'true' : 'false');
    doiIcon(nut, 'data-icon-thich', loai ? CAM_XUC[loai].icon : 'hand-thumbs-up');

    const nhan = nut.querySelector('[data-nhan-thich]');

    if (nhan) nhan.textContent = loai ? CAM_XUC[loai].nhan : 'Thích';

    const so = nut.querySelector('[data-so-thich]');

    if (so) so.textContent = data.so;

    const oHienTai = khoi?.querySelector('[data-cam-xuc-hien-tai]');

    if (oHienTai) oHienTai.value = loai || 'thich';

    khoi?.querySelectorAll('[data-chon-cam-xuc]').forEach((n) => {
        n.classList.toggle('is-on', n.dataset.chonCamXuc === loai);
    });

    const bangChon = khoi?.querySelector('details.cam-xuc-chon');

    if (bangChon) bangChon.open = false;

    const tomTat = khung?.querySelector('[data-tom-tat-thich]');

    if (tomTat) {
        tomTat.hidden = data.so < 1;
        const icons = (data.tom_tat || [])
            .filter((x) => CAM_XUC[x.loai])
            .slice(0, 3)
            .map((x) => `<span class="cam-xuc-tomtat__icon cam-xuc--${x.loai}"><svg class="icon" width="1em" height="1em" fill="currentColor" aria-hidden="true"><use href="#i-${CAM_XUC[x.loai].icon}"></use></svg></span>`)
            .join('');

        tomTat.innerHTML = `${icons} <span data-so-cam-xuc>${data.so}</span> cảm xúc`;
    }
}

function nhanLuu(form, data) {
    const id = form.querySelector('[data-luu]')?.dataset.luu;

    if (!id) return;

    document.querySelectorAll(`[data-luu="${id}"]`).forEach((nut) => {
        nut.classList.toggle('is-on', data.luu);
        nut.setAttribute('aria-pressed', data.luu ? 'true' : 'false');
        doiIcon(nut, 'data-icon-luu', data.luu ? 'bookmark-fill' : 'bookmark');

        const nhan = nut.querySelector('[data-nhan-luu]');

        if (nhan) {
            nhan.textContent = nhan.closest('.post-menu__item')
                ? (data.luu ? 'Bỏ lưu bài' : 'Lưu bài')
                : (data.luu ? 'Đã lưu' : 'Lưu');
        }
    });
}

async function guiToggle(form) {
    const res = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() },
        body: new FormData(form),
    });

    if (!res.ok) throw new Error('loi');

    const data = await res.json();

    if (form.dataset.loai === 'thich') nhanThich(form, data);
    else nhanLuu(form, data);
}

function chenEmoji(o, chu) {
    const dau = o.selectionStart ?? o.value.length;
    const cuoi = o.selectionEnd ?? o.value.length;

    o.value = o.value.slice(0, dau) + chu + o.value.slice(cuoi);
    o.selectionStart = o.selectionEnd = dau + chu.length;
    o.focus();
}

function xemTruocMedia(input) {
    const khung = input.closest('.mb-3')?.querySelector('[data-media-preview]');
    const canhBao = input.closest('.mb-3')?.querySelector('[data-media-canhbao]');

    if (!khung) return;

    khung.replaceChildren();
    const tep = Array.from(input.files || []);
    khung.hidden = tep.length === 0;

    let tong = 0;

    tep.forEach((f) => {
        tong += f.size;

        const o = document.createElement('div');
        o.className = 'composer-media__item';

        const laVideo = f.type.startsWith('video/');
        const el = document.createElement(laVideo ? 'video' : 'img');
        el.className = 'composer-media__thumb';
        el.src = URL.createObjectURL(f);
        if (laVideo) el.muted = true;

        const ten = document.createElement('span');
        ten.className = 'composer-media__xoa';
        ten.textContent = `${(f.size / 1048576).toFixed(1)}MB`;

        o.append(el, ten);
        khung.append(o);
    });

    if (canhBao) {
        const mb = tong / 1048576;
        canhBao.hidden = mb <= TONG_TOI_DA_MB;
        canhBao.textContent = `Tổng ${mb.toFixed(1)}MB — máy chủ chỉ nhận ${TONG_TOI_DA_MB}MB mỗi lần gửi. Hãy bớt bớt tệp hoặc đăng làm hai bài.`;
    }
}

export function initCommunity() {
    const goc = document.querySelector('[data-cong-dong]') ? document : null;

    if (document.body.dataset.congDongBound) return;

    document.body.dataset.congDongBound = '1';

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-toggle-json]');

        if (!form) return;

        e.preventDefault();

        try {
            await guiToggle(form);
        } catch {
            form.removeAttribute('data-toggle-json');
            form.submit();
        }
    });

    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-emoji]');

        if (!nut) return;

        const bang = nut.closest('[data-emoji-picker]');
        const o = document.querySelector(bang?.dataset.target || '');

        if (o) chenEmoji(o, nut.dataset.emoji);

        if (bang) bang.open = false;
    });

    document.addEventListener('click', (e) => {
        document.querySelectorAll('.emoji-picker[open], .post-menu[open]').forEach((d) => {
            if (!d.contains(e.target)) d.open = false;
        });
    });

    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-bao-cao]');

        if (!nut) return;

        const hop = document.querySelector('#hop-bao-cao');

        if (!hop) return;

        hop.querySelector('[data-bao-cao-loai]').value = nut.dataset.loai;
        hop.querySelector('[data-bao-cao-id]').value = nut.dataset.id;
    });

    document.addEventListener('change', (e) => {
        const input = e.target.closest('[data-media-input]');

        if (input) xemTruocMedia(input);
    });

    if (window.matchMedia?.('(hover: hover)').matches) {
        let hen = null;

        document.addEventListener('mouseover', (e) => {
            const khoi = e.target.closest?.('.cam-xuc');

            if (!khoi) return;

            clearTimeout(hen);
            hen = setTimeout(() => {
                const bang = khoi.querySelector('details.cam-xuc-chon');

                if (bang) bang.open = true;
            }, 320);
        });

        document.addEventListener('mouseout', (e) => {
            const khoi = e.target.closest?.('.cam-xuc');

            if (!khoi || khoi.contains(e.relatedTarget)) return;

            clearTimeout(hen);
            hen = setTimeout(() => {
                khoi.querySelectorAll('details.cam-xuc-chon[open]').forEach((d) => {
                    d.open = false;
                });
            }, 260);
        });
    }

    const moLai = document.querySelector('[data-mo-lai]');

    if (moLai && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(moLai).show();
    }

    return goc;
}
