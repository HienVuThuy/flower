/* Tải ảnh đại diện cho từng SẢN PHẨM trong danh mục. */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/products');

import { PRODUCT_TARGETS as TARGETS } from './lib/product-targets.mjs';

const result = await fetchInto({
    outDir: OUT,
    filePrefix: 'products/',
    targets: TARGETS,
    shape: 'portrait',
    only: process.argv.slice(2),
});

console.log(`
Da tai ${result.saved}/${result.total} anh -> storage/app/public/products/`);
console.log('Gan vao co so du lieu bang:  php artisan products:link-photos');
