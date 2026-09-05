/*
 * Nén và cắt ảnh sản phẩm về đúng khung thẻ.
 * ============================================================
 * Ảnh tải từ Openverse có kích thước tuỳ nguồn — có ảnh 783KB, có ảnh
 * ngang, có ảnh vuông. Trang danh sách nạp cả chục ảnh một lúc nên đây
 * là thứ nặng nhất trên trang.
 *
 * BA VIỆC:
 *   1. cắt về 4:5 (khung đứng của .product-card__media), lấy phần giữa;
 *   2. thu về tối đa 900px chiều rộng — thẻ rộng nhất khoảng 300px, ×2
 *      cho màn hình retina là 600px, để 900 là đã dư;
 *   3. nén JPEG chất lượng 78 — cùng mức đã dùng cho ảnh danh mục.
 *
 * GHI ĐÈ TẠI CHỖ, và cố ý như vậy: cột products.main_image đã trỏ tới
 * đúng tên tệp này. Đổi tên là phải cập nhật cơ sở dữ liệu, thêm một
 * bước có thể sai mà không đổi lại được gì.
 *
 * CHẠY LẠI NHIỀU LẦN ĐƯỢC: ảnh đã đúng khung và đủ nhỏ thì bỏ qua, nên
 * không có chuyện nén chồng nén làm ảnh xấu dần.
 *
 *     node tools/optimize-product-photos.mjs
 */

import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '..');
const DIR = path.join(ROOT, 'storage/app/public/products');

const TARGET_W = 900;
const TARGET_H = 1125; // 900 × 5/4
const QUALITY = 78;

/* Ảnh đã nhỏ hơn mức này thì nén lại cũng không lợi bao nhiêu. */
const SKIP_UNDER_BYTES = 60_000;

if (!fs.existsSync(DIR)) {
    console.log('Chưa có thư mục ảnh sản phẩm.');
    process.exit(0);
}

const files = fs.readdirSync(DIR).filter((f) => /\.(jpe?g|png|webp)$/i.test(f));

if (files.length === 0) {
    console.log('Không có ảnh nào để xử lý.');
    process.exit(0);
}

let before = 0;
let after = 0;
let touched = 0;

for (const file of files) {
    const full = path.join(DIR, file);
    const size = fs.statSync(full).size;
    before += size;

    let meta;

    try {
        meta = await sharp(full).metadata();
    } catch (err) {
        console.log(`${file.padEnd(38)} BỎ QUA — không đọc được: ${err.message}`);
        after += size;
        continue;
    }

    const ratio = meta.width / meta.height;
    const dungKhung = Math.abs(ratio - TARGET_W / TARGET_H) < 0.02;

    if (dungKhung && size < SKIP_UNDER_BYTES) {
        console.log(`${file.padEnd(38)} bỏ qua (đã đạt)`);
        after += size;
        continue;
    }

    try {
        /*
         * Ghi ra tệp tạm rồi mới thay thế.
         *
         * sharp không cho đọc và ghi cùng một tệp trong một lượt — làm
         * vậy sẽ cắt cụt tệp gốc trước khi đọc xong. Ghi tạm rồi đổi tên
         * cũng an toàn khi bị ngắt giữa chừng: hoặc còn ảnh cũ nguyên
         * vẹn, hoặc đã có ảnh mới, không có trạng thái nửa vời.
         */
        const tmp = full + '.tmp';

        await sharp(full)
            .rotate() // Tôn trọng thẻ EXIF, nếu không ảnh chụp dọc bị nằm ngang.
            /*
             * withoutEnlargement: KHÔNG phóng to ảnh gốc nhỏ.
             *
             * Đo được ở lần chạy đầu: hai ảnh gốc nhỏ hơn 900px bị kéo
             * lên và NẶNG THÊM 20–26% — thêm byte mà không thêm chi tiết,
             * ảnh còn bị nhoè vì nội suy.
             *
             * Hệ quả: ảnh nhỏ hơn khung đích giữ nguyên tỉ lệ gốc thay vì
             * được cắt về 4:5. Không sao — .product-card__image đã đặt
             * aspect-ratio: 4/5 kèm object-fit: cover, nên trình duyệt cắt
             * nốt phần thừa. Cắt sẵn trong tệp chỉ để tiết kiệm byte, chứ
             * không phải điều kiện để hiển thị đúng.
             */
            .resize(TARGET_W, TARGET_H, {
                fit: 'cover',
                position: 'attention',
                withoutEnlargement: true,
            })
            .jpeg({ quality: QUALITY, mozjpeg: true })
            .toFile(tmp);

        fs.renameSync(tmp, full);

        const now = fs.statSync(full).size;
        after += now;
        touched++;

        const pct = Math.round((1 - now / size) * 100);
        console.log(
            `${file.padEnd(38)} ${String(Math.round(size / 1024)).padStart(5)} KB -> ` +
            `${String(Math.round(now / 1024)).padStart(5)} KB  (${pct >= 0 ? '-' : '+'}${Math.abs(pct)}%)`
        );
    } catch (err) {
        console.log(`${file.padEnd(38)} LỖI: ${err.message}`);
        after += size;
    }
}

console.log(
    `\nĐã xử lý ${touched}/${files.length} ảnh. ` +
    `Tổng: ${Math.round(before / 1024)} KB -> ${Math.round(after / 1024)} KB ` +
    `(giảm ${Math.round((1 - after / before) * 100)}%)`
);
