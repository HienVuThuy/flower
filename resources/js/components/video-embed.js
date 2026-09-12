/**
 * BẤM RỒI MỚI NẠP TRÌNH PHÁT
 * ============================================================
 * Mặc định trang chỉ có một cái nút; iframe của YouTube/Vimeo chỉ được dựng khi
 * khách thật sự bấm. Nhờ vậy người chỉ lướt qua trang sản phẩm không bị bên thứ
 * ba tải về ~1MB JavaScript và gắn thẻ theo dõi.
 *
 * Không có tệp này thì cái nút vẫn là một liên kết mở trang gốc — xem
 * components/product/videos.blade.php.
 */
export function initVideoEmbed() {
    document
        .querySelectorAll('[data-video-embed]:not([data-video-bound])')
        .forEach((nut) => {
            nut.dataset.videoBound = '1';

            nut.addEventListener('click', (e) => {
                e.preventDefault();

                const khung = document.createElement('div');
                khung.className = 'product-video product-video--playing';

                const iframe = document.createElement('iframe');

                // `?autoplay=1`: khách vừa bấm "xem", bắt bấm phát lần nữa là
                // thừa một bước cho đúng thao tác họ vừa làm.
                iframe.src = `${nut.dataset.videoEmbed}?autoplay=1`;
                iframe.title = nut.dataset.videoTitle || 'Video sản phẩm';
                iframe.loading = 'lazy';
                iframe.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
                iframe.allowFullscreen = true;
                iframe.referrerPolicy = 'strict-origin-when-cross-origin';

                khung.append(iframe);
                nut.replaceWith(khung);
            });
        });
}
