/* Tải ảnh đại diện cho từng DANH MỤC. */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/categories');

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

    {
        slug: 'chau-va-de-lot',
        q: 'flower pots',
        alt: ['terracotta pots', 'ceramic pot', 'plant pot'],
        must: /pot|planter|saucer|terracotta|ceramic/i,
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
