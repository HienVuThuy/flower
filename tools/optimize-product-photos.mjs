/* Nén và cắt ảnh sản phẩm về đúng khung thẻ. */

import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '..');
const DIR = path.join(ROOT, 'storage/app/public/products');

const TARGET_W = 900;
const TARGET_H = 1125;
const QUALITY = 78;

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
        const tmp = full + '.tmp';

        await sharp(full)
            .rotate()
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
