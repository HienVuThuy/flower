/*
 * GÓC CÂY — PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH.
 * ============================================================
 * Không có tệp này thì mọi thứ vẫn chạy: thích / lưu là biểu mẫu thật, menu và
 * bảng emoji là <details>, hộp thoại đăng bài là modal của Bootstrap. Tệp này
 * chỉ bỏ đi những chỗ khó chịu:
 *
 *   1. Thích / lưu gửi bằng fetch và đổi tại chỗ — bấm thích giữa bảng tin
 *      không còn tải lại trang rồi nhảy về đầu danh sách.
 *   2. Bảng emoji chèn vào ĐÚNG vị trí con trỏ trong ô đang gõ.
 *   3. Hộp báo cáo biết nó đang báo bài nào / bình luận nào.
 *   4. Chọn ảnh xong thấy ngay ảnh thu nhỏ và tổng dung lượng — không phải gửi
 *      lên rồi mới biết quá nặng.
 *   5. Gửi bài lỗi thì mở lại hộp soạn để thấy lỗi ngay chỗ vừa nhập.
 */

/** Giới hạn máy chủ nhận mỗi lần gửi (post_max_size 40MB) trừ hao cho phần chữ. */
const TONG_TOI_DA_MB = 38;

function token() {
    const o = document.querySelector('input[name="_token"]');

    return o ? o.value : '';
}

/** Đổi <use href="#i-cu"> sang icon khác mà không dựng lại cả nút. */
function doiIcon(nut, thuocTinh, ten) {
    const use = nut.querySelector(`[${thuocTinh}] use`);

    if (use) {
        use.setAttribute('href', `#i-${ten}`);
    }
}

function nhanThich(form, data) {
    const nut = form.querySelector('[data-thich]');

    if (!nut) return;

    nut.classList.toggle('is-on', data.thich);
    nut.setAttribute('aria-pressed', data.thich ? 'true' : 'false');
    doiIcon(nut, 'data-icon-thich', data.thich ? 'heart-fill' : 'heart');

    const so = nut.querySelector('[data-so-thich]');

    if (so) so.textContent = data.so;

    // Dòng tóm tắt phía trên nút (bảng tin) cũng phải khớp.
    const bai = form.closest('[data-bai]');
    const tomTat = bai?.querySelector('[data-tom-tat-thich]');

    if (tomTat) tomTat.textContent = `${data.so} lượt thích`;
}

function nhanLuu(form, data) {
    /*
     * Một bài có thể có HAI nút lưu (thanh hành động và menu "⋯"), nên cập nhật
     * theo id bài chứ không chỉ cái nút vừa bấm — nếu không, hai nút nói hai
     * chuyện khác nhau về cùng một bài.
     */
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
    });

    if (!res.ok) throw new Error('loi');

    const data = await res.json();

    if (form.dataset.loai === 'thich') nhanThich(form, data);
    else nhanLuu(form, data);
}

/** Chèn emoji vào đúng chỗ con trỏ đang đứng. */
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

    // 1. Thích / lưu không tải lại trang.
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-toggle-json]');

        if (!form) return;

        e.preventDefault();

        try {
            await guiToggle(form);
        } catch {
            // Mạng hỏng: để biểu mẫu chạy kiểu cũ, khách vẫn bấm được.
            form.removeAttribute('data-toggle-json');
            form.submit();
        }
    });

    // 2. Bảng emoji.
    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-emoji]');

        if (!nut) return;

        const bang = nut.closest('[data-emoji-picker]');
        const o = document.querySelector(bang?.dataset.target || '');

        if (o) chenEmoji(o, nut.dataset.emoji);

        if (bang) bang.open = false;
    });

    // Bấm ra ngoài thì đóng bảng emoji và menu "⋯" đang mở.
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.emoji-picker[open], .post-menu[open]').forEach((d) => {
            if (!d.contains(e.target)) d.open = false;
        });
    });

    // 3. Hộp báo cáo biết đang báo nội dung nào.
    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-bao-cao]');

        if (!nut) return;

        const hop = document.querySelector('#hop-bao-cao');

        if (!hop) return;

        hop.querySelector('[data-bao-cao-loai]').value = nut.dataset.loai;
        hop.querySelector('[data-bao-cao-id]').value = nut.dataset.id;
    });

    // 4. Xem trước ảnh / video vừa chọn.
    document.addEventListener('change', (e) => {
        const input = e.target.closest('[data-media-input]');

        if (input) xemTruocMedia(input);
    });

    // 5. Gửi bài lỗi thì mở lại hộp soạn.
    const moLai = document.querySelector('[data-mo-lai]');

    if (moLai && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(moLai).show();
    }

    return goc;
}
