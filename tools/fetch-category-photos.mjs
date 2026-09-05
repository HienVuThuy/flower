/*
 * Tải ảnh đại diện cho từng DANH MỤC.
 * ============================================================
 * Bốn danh mục đầu (Hoa, Cây cảnh, Cây để bàn, Bonsai) đã có ảnh từ
 * trước. Sáu danh mục còn lại chưa có, nên thẻ danh mục ở trang chủ và
 * trang /danh-muc hiện hình lá giữ chỗ — trông như dữ liệu bị thiếu chứ
 * không như một lựa chọn thiết kế.
 *
 * KHUNG NGANG, khác ảnh sản phẩm: thẻ danh mục là dải ngang 16:9, nên ảnh
 * dọc bị cắt cụt trên dưới. Tham số `shape: 'landscape'` lọc từ phía
 * Openverse, rẻ hơn nhiều so với tải về rồi mới phát hiện.
 *
 * Lưu vào storage/app/public/categories/ — ảnh danh mục là DỮ LIỆU của
 * cửa hàng (admin thay được từ trang quản trị), không phải tài sản giao
 * diện. Cột categories.image trỏ tới đó.
 *
 *     node tools/fetch-category-photos.mjs             (tất cả)
 *     node tools/fetch-category-photos.mjs hoa-cuoi    (một danh mục)
 */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/categories');

/*
 * slug PHẢI TRÙNG slug danh mục trong cơ sở dữ liệu — lệnh
 * `php artisan categories:link-photos` ghép hai bên theo tên tệp.
 *
 * VIẾT TRUY VẤN NGẮN. Openverse nối các từ bằng AND nên truy vấn càng
 * dài càng dễ ra 0 kết quả; danh sách `alt` là các phương án lùi dần về
 * từ chung hơn.
 */
const TARGETS = [
    {
        slug: 'hoa-cuoi',
        q: 'bridal bouquet wedding',
        alt: ['wedding flowers white', 'bride bouquet roses'],
        hint: 'Danh mục: Hoa cưới',
    },
    {
        slug: 'hoa-khai-truong-su-kien',
        q: 'flower arrangement event',
        alt: ['floral arrangement large', 'flower basket display'],
        // Chặn hẳn hoa tang lễ: TITLE_BLOCK đã lọc, thêm một lớp nữa vì
        // đây là danh mục KHAI TRƯƠNG — nhầm ở đây là xúc phạm khách.
        must: /flower|floral|bouquet|arrangement|basket/i,
        hint: 'Danh mục: Hoa khai trương & sự kiện',
    },
    {
        slug: 'hoa-qua-tang',
        q: 'flower gift box',
        alt: ['roses gift wrapped', 'flower bouquet gift'],
        hint: 'Danh mục: Hoa quà tặng',
    },
    {
        slug: 'sen-da-xuong-rong',
        q: 'succulents cactus collection',
        alt: ['succulent plants pots', 'cactus garden'],
        must: /succulent|cact|echeveria|aloe|plant/i,
        hint: 'Danh mục: Sen đá & Xương rồng',
    },
    {
        slug: 'phu-kien',
        q: 'flower pots shelf',
        alt: ['ceramic plant pots', 'gardening tools pots'],
        must: /pot|planter|ceramic|tool|garden|watering/i,
        hint: 'Danh mục: Phụ kiện',
    },
    {
        slug: 'vat-tu-cham-soc',
        q: 'potting soil gardening',
        alt: ['compost garden soil', 'gardening supplies'],
        must: /soil|compost|fertili|garden|potting|supply|supplies/i,
        hint: 'Danh mục: Vật tư chăm sóc',
    },

    /* ---------- hai danh mục tách ra sau, chưa từng có ảnh ---------- */
    {
        slug: 'chau-va-de-lot',
        q: 'flower pots',
        alt: ['terracotta pots', 'ceramic pot', 'plant pot'],
        must: /pot|planter|saucer|terracotta|ceramic/i,
        /*
         * Chặn ảnh có cây bên trong: danh mục này bán CHẬU, không bán
         * cây trồng sẵn. Ảnh một chậu đầy cây làm khách tưởng mua về là
         * có cả cây.
         *
         * Câu truy vấn dài đã trả về 0 kết quả (Openverse nối các từ bằng
         * AND — xem chú thích ở product-targets.mjs). Rút ngắn xuống hai
         * chữ mới có kết quả.
         */
        block: /bonsai|orchid in|blooming/i,
        hint: 'Danh mục: Chậu & đế lót',
    },
    {
        slug: 'phu-goc-tieu-canh',
        q: 'decorative pebbles moss terrarium',
        alt: ['white gravel stones garden', 'moss stones miniature garden'],
        must: /pebble|gravel|stone|moss|terrarium|miniature/i,
        hint: 'Danh mục: Phủ gốc & tiểu cảnh',
    },
];

const result = await fetchInto({
    outDir: OUT,
    filePrefix: 'categories/',
    targets: TARGETS,
    shape: 'landscape',
    only: process.argv.slice(2),
});

console.log(`\nDa tai ${result.saved}/${result.total} anh -> storage/app/public/categories/`);
console.log('Gan vao co so du lieu bang:  php artisan categories:link-photos');
