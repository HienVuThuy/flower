/* Nút "Chép" — chép một chuỗi vào clipboard. */

const DONE_MS = 1400;

async function copy(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);

        return;
    }

    const box = document.createElement('textarea');
    box.value = text;
    box.setAttribute('readonly', '');
    box.style.position = 'fixed';
    box.style.opacity = '0';
    document.body.append(box);
    box.select();
    document.execCommand('copy');
    box.remove();
}

export function initCopyButtons() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const text = button.dataset.copy;
        const nhan = button.textContent;

        try {
            await copy(text);
            button.textContent = 'Đã chép';
            button.classList.add('is-copied');
        } catch {
            button.textContent = 'Hãy chép tay';
        }

        setTimeout(() => {
            button.textContent = nhan;
            button.classList.remove('is-copied');
        }, DONE_MS);
    });
}
