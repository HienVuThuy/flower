/*
 * Nút "Chép" — chép một chuỗi vào clipboard.
 * ============================================================
 * DÙNG CHO: mã vận đơn GHN ở trang quản trị đơn hàng.
 *
 * (Trước đây còn dùng cho số tài khoản và nội dung chuyển khoản; hình
 * thức chuyển khoản đã được gỡ khỏi hệ thống.)
 *
 * VÌ SAO CẦN: khách xem trang đơn hàng trên điện thoại rồi mở app ngân
 * hàng ở cùng máy đó. Gõ tay 14 chữ số giữa hai ứng dụng là chỗ dễ sai
 * nhất trong cả quy trình — mà sai số tài khoản thì tiền đi vào tài
 * khoản người lạ.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Số vẫn hiện đầy đủ trên màn hình và
 * bôi đen chép tay được. CSS ẩn nút này khi không có JavaScript
 * (html:not(.has-js)) để không bày ra một cái nút bấm vào không có gì
 * xảy ra.
 */

/** Nút hiện chữ "Đã chép" bao lâu rồi trở lại. */
const DONE_MS = 1400;

/**
 * Chép bằng navigator.clipboard, lùi về cách cũ nếu không dùng được.
 *
 * navigator.clipboard CHỈ CÓ trong ngữ cảnh an toàn (https hoặc
 * localhost). Trang chạy thử qua http://192.168.x.x trong mạng LAN —
 * đúng tình huống xem trên điện thoại — thì nó là undefined. Không có
 * đường lùi thì nút im lặng không làm gì.
 */
async function copy(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);

        return;
    }

    // Cách cũ: một ô ẩn + execCommand. Đã lỗi thời nhưng chạy ở mọi nơi.
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
    /*
     * MỘT trình lắng nghe trên document, không phải mỗi nút một cái —
     * cùng lý do với add-to-cart.js.
     */
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
            /*
             * Trình duyệt từ chối quyền clipboard. NÓI RA, không im lặng:
             * khách tưởng đã chép rồi dán vào app ngân hàng cái gì đó cũ
             * từ trước là sai số tài khoản.
             */
            button.textContent = 'Hãy chép tay';
        }

        setTimeout(() => {
            button.textContent = nhan;
            button.classList.remove('is-copied');
        }, DONE_MS);
    });
}
