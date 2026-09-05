/*
 * Tải ảnh nền cho banner chương trình khuyến mại.
 * ============================================================
 * Banner là ảnh NGANG và rất rộng (chiếm hết chiều ngang trang), nên
 * `shape: 'landscape'` — ảnh dọc đặt vào đó sẽ bị cắt mất đầu và chân.
 *
 * KHÔNG BẮT BUỘC PHẢI CÓ. `components/seasonal/campaign-banner.blade.php`
 * đã có sẵn trạng thái không-ảnh (`campaign-banner--has-image` là một
 * modifier, không phải mặc định) và trông vẫn tử tế. Ảnh chỉ làm nó đẹp
 * hơn, không phải thứ thiếu-thì-vỡ.
 *
 * Vì thế script này chỉ tải, KHÔNG tự gán vào cơ sở dữ liệu — admin tự
 * chọn ở trang Khuyến mại. Banner là quyết định thương hiệu, và một bức
 * ảnh stock tôi chọn hộ chưa chắc đã hợp với chiến dịch họ đang chạy.
 *
 *     node tools/fetch-promotion-banners.mjs
 */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/promotions');

/*
 * Khoá theo `theme_key` của chương trình, không theo slug: nhiều chương
 * trình cùng chủ đề dùng chung được một ảnh nền, và chủ đề thì lặp lại
 * hằng năm còn slug thì không.
 */
const TARGETS = [
    {
        slug: 'noel',
        q: 'christmas pine branches',
        alt: ['christmas decoration red gold', 'winter pine wreath'],
        must: /christmas|pine|winter|wreath|fir/i,
        hint: 'Nền banner chủ đề Giáng sinh',
    },
    {
        slug: 'tet',
        q: 'apricot blossom',
        alt: ['peach blossom branch', 'lunar new year flowers'],
        must: /blossom|apricot|peach|lunar|tet/i,
        hint: 'Nền banner chủ đề Tết',
    },
    {
        slug: 'valentine',
        q: 'red roses',
        alt: ['rose bouquet red', 'roses close up'],
        must: /rose|flower|floral/i,
        hint: 'Nền banner chủ đề Valentine',
    },
];

const result = await fetchInto({
    outDir: OUT,
    filePrefix: 'promotions/',
    targets: TARGETS,
    shape: 'landscape',
    only: process.argv.slice(2),
});

console.log(`
Da tai ${result.saved}/${result.total} anh -> storage/app/public/promotions/`);
console.log('Chon anh cho tung chuong trinh o trang quan tri: Khuyen mai -> Sua.');
