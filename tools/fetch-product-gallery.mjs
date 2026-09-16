/* Tải ẢNH PHỤ cho thư viện ảnh của trang chi tiết sản phẩm. */

import fs from 'node:fs';
import path from 'node:path';
import { search, download, sleep } from './lib/openverse.mjs';
import { PRODUCT_TARGETS } from './lib/product-targets.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const PRODUCTS = path.join(ROOT, 'storage/app/public/products');
const OUT = path.join(PRODUCTS, 'gallery');

const SO_ANH_PHU = 2;

fs.mkdirSync(OUT, { recursive: true });

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
