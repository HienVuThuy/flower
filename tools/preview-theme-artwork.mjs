/* Dựng trang xem thử artwork theme ở ĐÚNG kích thước hiển thị thật và trên ĐÚNG màu nền của từng theme. */

import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const IMG = path.join(ROOT, 'resources/images/themes');
const outDir = process.argv[2] || path.join(ROOT, 'storage/app/artwork-preview');

const THEMES = [
    {
        name: 'TẾT',
        bg: '#fdf5e6',
        ink: '#3b2a1a',
        items: [
            { file: 'tet/blossom-corner.svg', w: 220, h: 170, css: 'top:-16px;left:-10px' },
            { file: 'tet/lantern.svg', w: 62, h: 104, css: 'top:0;right:8%' },
        ],
    },
    {
        name: 'NOEL',
        bg: '#f4f5f0',
        ink: '#223a2a',
        items: [
            { file: 'noel/pine-corner.svg', w: 220, h: 170, css: 'top:-20px;right:-10px' },
            { file: 'noel/ornaments.svg', w: 128, h: 160, css: 'top:0;left:4%' },
        ],
    },
    {
        name: 'VALENTINE',
        bg: '#fdeef0',
        ink: '#5c1f2b',
        items: [
            { file: 'valentine/rose-corner.svg', w: 190, h: 150, css: 'top:-10px;right:-6px' },
            { file: 'valentine/ribbon.svg', w: 112, h: 88, css: 'top:8px;left:2%' },
        ],
    },
];

const css = `body{margin:0;font:15px system-ui}
.hero{position:relative;overflow:hidden;height:300px;margin-bottom:6px}
.d{position:absolute;background-repeat:no-repeat;background-size:contain}
h1{font:600 40px Georgia,serif;margin:0;padding:120px 0 0 44px}
.tag{position:absolute;bottom:8px;left:44px;font:12px system-ui;opacity:.5}`;

let html = `<meta charset="utf-8"><style>${css}</style>`;

for (const theme of THEMES) {
    html += `<div class="hero" style="background:${theme.bg}">`;

    for (const item of theme.items) {
        const data = fs.readFileSync(path.join(IMG, item.file)).toString('base64');
        html +=
            `<div class="d" style="width:${item.w}px;height:${item.h}px;${item.css};` +
            `background-image:url(data:image/svg+xml;base64,${data})"></div>`;
    }

    html +=
        `<h1 style="color:${theme.ink}">Vẻ đẹp tự nhiên — ${theme.name}</h1>` +
        `<span class="tag">${theme.items.map((i) => i.file).join('  ·  ')}</span></div>`;
}

fs.mkdirSync(outDir, { recursive: true });
const target = path.join(outDir, 'preview.html');
fs.writeFileSync(target, html);
console.log(target);
