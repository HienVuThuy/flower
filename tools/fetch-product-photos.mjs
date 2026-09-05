/*
 * Tải ảnh đại diện cho từng SẢN PHẨM trong danh mục.
 * ============================================================
 * Khác tools/fetch-photos.mjs ở CHỖ LƯU: ảnh danh mục giao diện nằm
 * trong resources/images/ và đi qua Vite. Ảnh sản phẩm là DỮ LIỆU của
 * cửa hàng — admin phải thay được từ trang quản trị — nên lưu vào
 * storage/app/public/products/ giống hệt ảnh do admin tự tải lên, và cột
 * products.main_image trỏ tới đó.
 *
 * Luật giấy phép, danh sách từ khoá cấm và cách kiểm tra tệp tải về nằm
 * ở tools/lib/openverse.mjs — DÙNG CHUNG với script tải ảnh danh mục.
 * Chép sang đây một bản thứ hai thì sớm muộn hai bản sẽ lệch, và lệch ở
 * đây nghĩa là một nhánh âm thầm nhận ảnh không đủ điều kiện thương mại.
 *
 *     node tools/fetch-product-photos.mjs              (tất cả)
 *     node tools/fetch-product-photos.mjs bo-tulip-ha-lan
 */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/products');

// Danh sách sản phẩm và câu truy vấn nằm ở lib/ vì công cụ tải ảnh thư
// viện (fetch-product-gallery.mjs) cần đúng danh sách đó.
import { PRODUCT_TARGETS as TARGETS } from './lib/product-targets.mjs';

const result = await fetchInto({
    outDir: OUT,
    filePrefix: 'products/',
    targets: TARGETS,
    // Thẻ sản phẩm là khung ĐỨNG 4:5 — ảnh panorama sẽ bị cắt cụt hai đầu.
    shape: 'portrait',
    only: process.argv.slice(2),
});

console.log(`
Da tai ${result.saved}/${result.total} anh -> storage/app/public/products/`);
console.log('Gan vao co so du lieu bang:  php artisan products:link-photos');
