/* Tải ảnh nền cho banner chương trình khuyến mại. */

import path from 'node:path';
import { fetchInto } from './lib/openverse.mjs';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'storage/app/public/promotions');

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
