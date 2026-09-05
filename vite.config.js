import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';

/*
 * Vite chỉ đóng gói tài nguyên nào được tham chiếu từ một entry. Ảnh chỉ
 * xuất hiện trong Blade thì không ai "import" cả, nên phải khai vào input
 * — nếu không, Vite::asset() ném lỗi "Unable to locate file in Vite
 * manifest" và cả trang chết.
 *
 * Đọc thẳng thư mục để thêm ảnh mới không phải sửa file này.
 */
const imagesIn = (dir) =>
    fs
        .readdirSync(`resources/images/${dir}`)
        .filter((f) => /\.(jpe?g|png|webp)$/i.test(f))
        .map((f) => `resources/images/${dir}/${f}`);

const catalogImages = [...imagesIn('catalog'), ...imagesIn('hero')];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Ảnh biên tập dùng trong Blade qua Vite::asset().
                // hero-bouquet.jpg đã bỏ: khung hero nay dùng bộ ảnh theo
                // theme ở resources/images/hero/.
                'resources/images/editorial/story-studio.jpg',
                ...catalogImages,
            ],
            refresh: true,
            /*
             * Không dùng `fonts:` của plugin nữa — xem giải thích trong
             * resources/css/core/fonts.css. Tóm tắt: plugin sinh một khối
             * @font-face cho MỖI file nên mỗi font bị khai 2 lần (woff2 +
             * woff); browser dùng khối cuối (.woff) trong khi <link preload>
             * trỏ .woff2 → tải cả hai, bản preload không bao giờ dùng tới.
             * Nay tự khai @font-face, chỉ woff2, có subset vietnamese.
             */
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
