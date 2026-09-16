/** BẤM RỒI MỚI NẠP TRÌNH PHÁT */
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
