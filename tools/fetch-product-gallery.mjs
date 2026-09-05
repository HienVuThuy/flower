/*
 * Tải ẢNH PHỤ cho thư viện ảnh của trang chi tiết sản phẩm.
 * ============================================================
 * Khác `fetch-product-photos.mjs` ở chỗ: script kia lấy MỘT ảnh đại diện
 * cho mỗi sản phẩm (`products.main_image`); script này lấy THÊM vài ảnh
 * nữa vào bảng `product_images`.
 *
 * Vì sao cần: `components/product/gallery.blade.php` đã dựng sẵn khung
 * thư viện có ảnh nhỏ bấm để đổi ảnh lớn — nhưng bảng `product_images`
 * rỗng hoàn toàn, nên mọi sản phẩm hiện đúng một ảnh và hàng ảnh nhỏ
 * không bao giờ xuất hiện. Một khung thư viện chỉ có một ảnh là một cái
 * khung rỗng có viền.
 *
 * ============================================================
 * KHÔNG LẤY LẠI ẢNH ĐÃ DÙNG LÀM ẢNH ĐẠI DIỆN.
 *
 * Cùng câu truy vấn thì Openverse trả về cùng thứ tự kết quả, nên ảnh
 * đầu tiên gần như luôn là ảnh đã dùng. Không loại nó ra thì thư viện có
 * hai ô giống hệt nhau — trông như trang bị lỗi.
 *
 * Loại theo `foreign_landing_url` chứ không theo tên tệp: cùng một bức
 * ảnh có thể tải về dưới hai tên khác nhau.
 *
 *     node tools/fetch-product-gallery.mjs              (tất cả)
 *     node tools/fetch-product-gallery.mjs bo-tulip-ha-lan
 */

import fs from 'node:fs';
import path from 'node:path';
import { search, download, sleep } from './lib/openverse.mjs';
import { PRODUCT_TARGETS } from './lib/product-targets.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const PRODUCTS = path.join(ROOT, 'storage/app/public/products');
const OUT = path.join(PRODUCTS, 'gallery');

/*
 * BAO NHIÊU ẢNH PHỤ LÀ ĐỦ.
 *
 * Hai. Cộng ảnh đại diện là ba ô — đủ để hàng ảnh nhỏ có nghĩa và đủ để
 * khách xem cây từ vài góc. Nhiều hơn thì mỗi lần mở trang là thêm vài
 * trăm KB cho thứ phần lớn khách không bấm tới, và với ảnh stock thì ảnh
 * thứ tư trở đi thường đã lạc đề.
 */
const SO_ANH_PHU = 2;

fs.mkdirSync(OUT, { recursive: true });

/* Ảnh đại diện đã dùng — để không lấy trùng. */
const mainCredits = (() => {
    const p = path.join(PRODUCTS, 'credits.json');

    if (!fs.existsSync(p)) {
        console.log('Chua co products/credits.json. Chay truoc: node tools/fetch-product-photos.mjs');
        process.exit(1);
    }

    return JSON.parse(fs.readFileSync(p, 'utf8'));
})();

const daDung = new Map(mainCredits.map((c) => [c.slug, c.pageUrl]));

const only = process.argv.slice(2);
const todo = only.length ? PRODUCT_TARGETS.filter((t) => only.includes(t.slug)) : PRODUCT_TARGETS;

const creditsPath = path.join(OUT, 'credits.json');

// Giữ lại phần ghi nguồn của những ảnh không chạy lại lần này.
const credits = fs.existsSync(creditsPath)
    ? JSON.parse(fs.readFileSync(creditsPath, 'utf8')).filter(
          (c) => !todo.some((t) => c.slug === t.slug)
      )
    : [];

let tong = 0;

for (const target of todo) {
    process.stdout.write(`${target.slug.padEnd(32)} `);

    try {
        await sleep(1200);

        /*
         * GOM KẾT QUẢ TỪ MỌI CÂU TRUY VẤN, không dừng ở câu đầu tiên.
         *
         * Khác `fetchInto`, vốn dừng ngay khi một câu cho ra kết quả vì
         * nó chỉ cần một ảnh. Ở đây cần vài ảnh KHÁC NHAU, và câu truy
         * vấn thứ hai thường cho ra góc chụp khác hẳn — đúng thứ một
         * thư viện ảnh cần.
         */
        const ungVien = [];
        const daThay = new Set();

        for (const q of [target.q, ...(target.alt ?? [])]) {
            const kq = await search(q, {
                must: target.must,
                block: target.block,
                shape: 'portrait',
            });

            for (const it of kq) {
                const khoa = it.foreign_landing_url || it.url;

                if (khoa && khoa !== daDung.get(target.slug) && !daThay.has(khoa)) {
                    daThay.add(khoa);
                    ungVien.push(it);
                }
            }

            if (ungVien.length >= SO_ANH_PHU * 4) break;
            await sleep(800);
        }

        let luu = 0;

        for (const it of ungVien) {
            if (luu >= SO_ANH_PHU) break;

            const got = await download(it.url);
            await sleep(400);

            if (!got.ok) continue;

            luu++;
            const file = `${target.slug}-${luu + 1}.${got.ext}`;
            fs.writeFileSync(path.join(OUT, file), got.buf);

            credits.push({
                slug: target.slug,
                file: 'products/gallery/' + file,
                sort: luu,
                hint: target.hint ?? '',
                title: it.title ?? '',
                author: it.creator ?? 'Khong ghi ten',
                license: `${(it.license ?? '').toUpperCase()} ${it.license_version ?? ''}`.trim(),
                licenseUrl: it.license_url ?? '',
                pageUrl: it.foreign_landing_url ?? '',
            });
        }

        tong += luu;
        console.log(luu ? `${luu} anh phu` : 'BO QUA - khong co anh khac hop le');
    } catch (err) {
        console.log('LOI: ' + err.message);
    }
}

fs.writeFileSync(creditsPath, JSON.stringify(credits, null, 2));

console.log(`
Da tai ${tong} anh phu -> storage/app/public/products/gallery/`);
console.log('Gan vao co so du lieu bang:  php artisan products:link-gallery');
