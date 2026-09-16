/** KHUNG CHAT TRỢ LÝ AI */
export function initAiChat() {
    document.querySelectorAll('[data-ai-form]:not([data-ai-bound])').forEach((form) => {
        form.dataset.aiBound = '1';

        const khung = form.closest('[data-ai-chat]');
        const log = khung?.querySelector('[data-ai-log]');
        const o = form.querySelector('textarea[name="cau_hoi"]');
        const nutGui = form.querySelector('button[type="submit"]');
        const nutMoi = khung?.querySelector('[data-ai-reset]');
        const token = form.querySelector('input[name="_token"]')?.value ?? '';

        if (!log || !o) return;

        const them = (vaiTro, chu) => {
            const p = document.createElement('p');
            p.className = `ai-chat__msg ai-chat__msg--${vaiTro}`;
            p.textContent = chu;
            log.appendChild(p);
            log.scrollTop = log.scrollHeight;

            return p;
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const cauHoi = o.value.trim();
            if (cauHoi === '' || nutGui.disabled) return;

            them('user', cauHoi);
            o.value = '';
            nutGui.disabled = true;
            const cho = them('ai', 'Đang trả lời…');

            const body = new FormData();
            body.append('cau_hoi', cauHoi);

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body,
                });

                const data = await res.json().catch(() => ({}));

                cho.textContent = res.ok
                    ? data.tra_loi ?? ''
                    : data.loi ?? data.message ?? 'Chưa gửi được câu hỏi. Vui lòng thử lại.';

                if (!res.ok) cho.classList.add('ai-chat__msg--loi');
            } catch {
                cho.textContent = 'Không kết nối được. Vui lòng thử lại.';
                cho.classList.add('ai-chat__msg--loi');
            } finally {
                nutGui.disabled = false;
                o.focus();
            }
        });

        nutMoi?.addEventListener('click', async () => {
            await fetch(nutMoi.dataset.url, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            }).catch(() => null);

            log.replaceChildren();
        });
    });
}
