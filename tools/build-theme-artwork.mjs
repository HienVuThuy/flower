/* Sinh artwork SVG cho các theme mùa vụ. */

import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const iconSet = require('@iconify-json/game-icons/icons.json');

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'resources/images/themes');

const GRID = 512;

function place(name, at) {
    const icon = iconSet.icons[name];

    if (!icon) {
        throw new Error(`Không có icon "${name}" trong bộ game-icons`);
    }

    const { x, y, size, rotate = 0, opacity = 1, color, flip = false, bleed = false } = at;
    const scale = size / GRID;

    const transform = [
        `translate(${x + size / 2} ${y + size / 2})`,
        `rotate(${rotate})`,
        `scale(${flip ? -scale : scale} ${scale})`,
        `translate(${-GRID / 2} ${-GRID / 2})`,
    ].join(' ');

    used.add(name);

    if (!bleed) {
        const over = [];

        if (x < 0) over.push(`trái ${(-x).toFixed(0)}px`);
        if (y < 0) over.push(`trên ${(-y).toFixed(0)}px`);
        if (x + size > canvas.width) over.push(`phải ${(x + size - canvas.width).toFixed(0)}px`);
        if (y + size > canvas.height) over.push(`dưới ${(y + size - canvas.height).toFixed(0)}px`);

        if (over.length) {
            warnings.push(`  ${canvas.file}: "${name}" lòi ra ${over.join(', ')}`);
        }
    }

    return `  <use href="#i-${name}" transform="${transform}" color="${color}" opacity="${opacity}"/>`;
}

let used = new Set();
let canvas = { file: '', width: 0, height: 0 };
const warnings = [];

function compose({ file, width, height, layers }) {
    used = new Set();
    canvas = { file, width, height };

    const body = layers
        .map((l) => (typeof l === 'string' ? `  ${l}` : place(l.icon, l)))
        .join('\n');

    const defs = [...used]
        .map((n) => `    <g id="i-${n}">${iconSet.icons[n].body}</g>`)
        .join('\n');

    const svg =
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}">\n` +
        (defs ? `  <defs>\n${defs}\n  </defs>\n` : '') +
        `${body}\n</svg>\n`;

    const target = path.join(OUT, file);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, svg);

    return { file, bytes: Buffer.byteLength(svg) };
}

function onCurve([p0, p1, p2, p3], t) {
    const u = 1 - t;
    const b = [u * u * u, 3 * u * u * t, 3 * u * t * t, t * t * t];

    return {
        x: b[0] * p0[0] + b[1] * p1[0] + b[2] * p2[0] + b[3] * p3[0],
        y: b[0] * p0[1] + b[1] * p1[1] + b[2] * p2[1] + b[3] * p3[1],
    };
}

function curvePath([p0, p1, p2, p3], { stroke, width }) {
    return (
        `<path d="M${p0[0]} ${p0[1]} C ${p1[0]} ${p1[1]}, ${p2[0]} ${p2[1]}, ${p3[0]} ${p3[1]}" ` +
        `stroke="${stroke}" stroke-width="${width}" stroke-linecap="round" fill="none"/>`
    );
}

function at(point, size, rest) {
    return { x: point.x - size / 2, y: point.y - size / 2, size, ...rest };
}

function banhChung(x, y, size) {
    const s = size / 100;
    const band = 13;

    return (
        `<g transform="translate(${x} ${y}) scale(${s})">` +
        `<rect x="2" y="2" width="96" height="96" rx="7" fill="#20452c"/>` +
        `<rect x="8" y="8" width="84" height="84" rx="4" fill="#2f6138"/>` +
        `<rect x="${33 - band / 2}" y="2" width="${band}" height="96" fill="#e2d3a8"/>` +
        `<rect x="${67 - band / 2}" y="2" width="${band}" height="96" fill="#e2d3a8"/>` +
        `<rect x="2" y="${33 - band / 2}" width="96" height="${band}" fill="#d8c79a"/>` +
        `<rect x="2" y="${67 - band / 2}" width="96" height="${band}" fill="#d8c79a"/>` +
        `</g>`
    );
}

function liXi(x, y, w, h, rotate = 0) {
    return (
        `<g transform="translate(${x} ${y}) rotate(${rotate})">` +
        `<rect x="0" y="0" width="${w}" height="${h}" rx="5" fill="#c8102e"/>` +
        `<rect x="4" y="4" width="${w - 8}" height="${h - 8}" rx="3" fill="none" stroke="#e3b23c" stroke-width="1.6"/>` +
        `<path d="M0 0 H${w} L${w / 2} ${h * 0.3} Z" fill="#a70d24"/>` +
        `<circle cx="${w / 2}" cy="${h * 0.6}" r="${w * 0.19}" fill="#e3b23c"/>` +
        `<circle cx="${w / 2}" cy="${h * 0.6}" r="${w * 0.12}" fill="none" stroke="#a70d24" stroke-width="1.4"/>` +
        `</g>`
    );
}


function banhGiay(cx, cy, r) {
    return (
        `<g>` +
        `<ellipse cx="${cx}" cy="${cy + r * 0.22}" rx="${r}" ry="${r * 0.62}" fill="#ddd6c4"/>` +
        `<ellipse cx="${cx}" cy="${cy}" rx="${r}" ry="${r * 0.66}" fill="#f2ece0"/>` +
        `<ellipse cx="${cx - r * 0.22}" cy="${cy - r * 0.16}" rx="${r * 0.36}" ry="${r * 0.2}" fill="#fbf7ef" opacity="0.75"/>` +
        `<path d="M${cx - r * 1.05} ${cy + r * 0.5} q ${r} ${r * 0.34} ${r * 2.1} 0" stroke="#3f6b45" stroke-width="${r * 0.16}" fill="none" stroke-linecap="round"/>` +
        `</g>`
    );
}

function rng(seed) {
    let s = seed >>> 0;

    return () => {
        s = (s * 1664525 + 1013904223) >>> 0;
        return s / 4294967296;
    };
}

function snowDrift({ width, height, seed }) {
    const rand = rng(seed);

    const settled = height * 0.18;
    const room = height - settled;
    const steps = 26;

    const ph = [rand() * 6.283, rand() * 6.283, rand() * 6.283];
    const envelope = (t) =>
        0.56 +
        0.22 * Math.sin(t * 2.1 + ph[0]) +
        0.13 * Math.sin(t * 3.7 + ph[1]) +
        0.08 * Math.sin(t * 6.3 + ph[2]);

    const pts = [];
    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const x = t * width;

        const taper = 1;

        const thickness = Math.max(0, envelope(t * Math.PI * 2)) + (rand() - 0.5) * 0.13;
        const y = height - settled - room * Math.min(1, thickness) * taper;

        pts.push({ x, y: Math.min(y, height - settled) });
    }

    let d = `M0 ${height} L0 ${pts[0].y.toFixed(1)}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i - 1] ?? pts[i];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] ?? p2;

        const c1x = p1.x + (p2.x - p0.x) / 6;
        const c1y = p1.y + (p2.y - p0.y) / 6;
        const c2x = p2.x - (p3.x - p1.x) / 6;
        const c2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${c1x.toFixed(1)} ${c1y.toFixed(1)}, ${c2x.toFixed(1)} ${c2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
    }
    d += ` L${width} ${height} Z`;

    let drips = '';
    for (let i = 0; i < 5; i++) {
        const cx = width * (0.08 + rand() * 0.86);
        const rx = 2 + rand() * 3.2;
        drips += `<ellipse cx="${cx.toFixed(1)}" cy="${height}" rx="${rx.toFixed(1)}" ry="${(rx * (1.1 + rand() * 1.4)).toFixed(1)}" fill="url(#sg)"/>`;
    }

    let wet = '';
    for (let i = 0; i < 3; i++) {
        const cx = width * (0.15 + rand() * 0.7);
        const r = 4 + rand() * 5;
        wet += `<ellipse cx="${cx.toFixed(1)}" cy="${(height - settled * 0.4).toFixed(1)}" rx="${(r * 1.6).toFixed(1)}" ry="${r.toFixed(1)}" fill="#e3ebf0" opacity="0.34"/>`;
    }

    return (
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}">` +
        `<defs><linearGradient id="sg" x1="0" y1="0" x2="0" y2="1">` +
        `<stop offset="0" stop-color="#ffffff"/>` +
        `<stop offset="0.55" stop-color="#fafcfd"/>` +
        `<stop offset="1" stop-color="#e6edf2"/>` +
        `</linearGradient></defs>` +
        `<path d="${d}" fill="url(#sg)"/>${drips}${wet}` +
        `</svg>`
    );
}

function writeRaw(file, svg) {
    const target = path.join(OUT, file);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, svg + '\n');

    return { file, bytes: Buffer.byteLength(svg) + 1 };
}

const C = {
    tet: { branch: '#7a5230', blossom: '#d9a441', blossomDeep: '#c08a2e', red: '#b4232f', gold: '#d9a441' },
    noel: { pine: '#2f4a37', pineLight: '#456b50', snow: '#dfeae2', red: '#8a2e2e', gold: '#c9a13b' },
    valentine: { rose: '#8c3549', roseLight: '#b5495f', blush: '#d78b9b', leaf: '#5c7a54' },
};

const jobs = [];

const maiBranch = [[-8, 8], [70, 60], [110, 30], [235, 92]];
const maiTwig = [[64, 52], [92, 96], [120, 118], [150, 168]];

jobs.push(compose({
    file: 'tet/blossom-corner.svg',
    width: 260,
    height: 200,
    layers: [
        curvePath(maiBranch, { stroke: C.tet.branch, width: 5 }),
        curvePath(maiTwig, { stroke: C.tet.branch, width: 3 }),

        at(onCurve(maiBranch, 0.18), 30, { icon: 'three-leaves', rotate: 140, color: '#6f8f5e', opacity: 0.85 }),
        at(onCurve(maiTwig, 0.45), 24, { icon: 'three-leaves', rotate: 30, color: '#6f8f5e', opacity: 0.8 }),

        at(onCurve(maiBranch, 0.06), 40, { icon: 'spoted-flower', rotate: -12, color: C.tet.blossomDeep, bleed: true }),
        at(onCurve(maiBranch, 0.34), 52, { icon: 'spoted-flower', rotate: 16, color: C.tet.blossom }),
        at(onCurve(maiBranch, 0.58), 36, { icon: 'spoted-flower', rotate: -24, color: C.tet.blossomDeep, opacity: 0.92 }),
        at(onCurve(maiBranch, 0.82), 44, { icon: 'spoted-flower', rotate: 8, color: C.tet.blossom }),
        at(onCurve(maiBranch, 1), 26, { icon: 'spoted-flower', rotate: 32, color: C.tet.blossom, opacity: 0.8 }),
        at(onCurve(maiTwig, 0.72), 34, { icon: 'spoted-flower', rotate: -8, color: C.tet.blossom, opacity: 0.9 }),
        at(onCurve(maiTwig, 1), 24, { icon: 'spoted-flower', rotate: 20, color: C.tet.blossomDeep, opacity: 0.75 }),
    ],
}));

jobs.push(compose({
    file: 'tet/lantern.svg',
    width: 120,
    height: 200,
    layers: [
        '<path d="M60 0 V34" stroke="#c08a2e" stroke-width="3"/>',
        { icon: 'asian-lantern', x: -2, y: 22, size: 124, color: C.tet.red, bleed: true },
    ],
}));

jobs.push(compose({
    file: 'noel/pine-corner.svg',
    width: 260,
    height: 200,
    layers: [
        { icon: 'pine-tree', x: 24, y: 101, size: 64, color: C.noel.pineLight, opacity: 0.5 },
        { icon: 'pine-tree', x: 74, y: 73, size: 92, color: C.noel.pineLight, opacity: 0.75 },
        { icon: 'pine-tree', x: 116, y: 33, size: 132, color: C.noel.pine },
        { icon: 'pine-tree', x: 176, y: 81, size: 84, color: C.noel.pine, opacity: 0.9 },

        { icon: 'snowflake-2', x: 8, y: 24, size: 26, color: C.noel.pine, opacity: 0.42 },
        { icon: 'snowflake-2', x: 52, y: 6, size: 18, color: C.noel.pine, opacity: 0.34 },
        { icon: 'snowflake-2', x: 30, y: 58, size: 14, color: C.noel.pine, opacity: 0.3 },
    ],
}));

jobs.push(compose({
    file: 'noel/ornaments.svg',
    width: 200,
    height: 220,
    layers: [
        `<path d="M4 34 C 60 18, 140 22, 196 44" stroke="${C.noel.pine}" stroke-width="7" stroke-linecap="round" fill="none"/>`,
        `<path d="M28 30 l-14 -14 M74 22 l-10 -16 M126 24 l6 -17 M172 36 l16 -12" stroke="${C.noel.pine}" stroke-width="4" stroke-linecap="round" fill="none"/>`,
        '<path d="M52 30 V96 M100 24 V116 M148 32 V90" stroke="#c9a13b" stroke-width="2.5" fill="none"/>',
        `<circle cx="52" cy="118" r="22" fill="${C.noel.red}"/>`,
        `<circle cx="45" cy="110" r="7" fill="#ffffff" opacity="0.28"/>`,
        `<circle cx="100" cy="140" r="25" fill="${C.noel.gold}"/>`,
        `<circle cx="92" cy="131" r="8" fill="#ffffff" opacity="0.3"/>`,
        `<circle cx="148" cy="112" r="19" fill="${C.noel.pineLight}"/>`,
        `<circle cx="142" cy="105" r="6" fill="#ffffff" opacity="0.26"/>`,
    ],
}));

jobs.push(compose({
    file: 'valentine/rose-corner.svg',
    width: 260,
    height: 200,
    layers: [
        { icon: 'shut-rose', x: 40, y: -28, size: 208, rotate: 16, color: C.valentine.rose, flip: true, bleed: true },
        { icon: 'shut-rose', x: 6, y: 52, size: 128, rotate: -22, color: C.valentine.roseLight, opacity: 0.85, flip: true },
    ],
}));

jobs.push(compose({
    file: 'valentine/ribbon.svg',
    width: 180,
    height: 140,
    layers: [
        { icon: 'bow-tie-ribbon', x: 14, y: 4, size: 152, color: C.valentine.roseLight, bleed: true },
    ],
}));


jobs.push(writeRaw('noel/snow-cap.svg', snowDrift({ width: 640, height: 30, seed: 991177 })));

jobs.push(writeRaw('noel/snow-cap-wide.svg', snowDrift({ width: 1400, height: 26, seed: 7788 })));

jobs.push(compose({
    file: 'tet/gift-cluster.svg',
    width: 460,
    height: 160,
    layers: [
        banhChung(4, 56, 96),
        banhGiay(150, 118, 40),
        liXi(196, 40, 58, 92, -8),
        { icon: 'present', x: 262, y: 52, size: 100, color: C.tet.red },
        { icon: 'two-coins', x: 352, y: 96, size: 58, color: C.tet.gold },
        { icon: 'tied-scroll', x: 348, y: 26, size: 74, rotate: -12, color: '#a8202c' },
        { icon: 'spoted-flower', x: 424, y: 14, size: 30, color: C.tet.blossom, opacity: 0.9 },
    ],
}));

jobs.push(compose({
    file: 'noel/gift-cluster.svg',
    width: 460,
    height: 160,
    layers: [
        { icon: 'snowman', x: 0, y: 24, size: 118, color: '#5d7a86' },
        { icon: 'candy-canes', x: 104, y: 60, size: 84, color: C.noel.red, opacity: 0.95 },
        { icon: 'present', x: 176, y: 52, size: 104, color: C.noel.red },
        { icon: 'socks', x: 268, y: 44, size: 88, color: '#9c3333' },
        { icon: 'gingerbread-man', x: 340, y: 60, size: 78, color: '#8a5a2b' },
        { icon: 'ringing-bell', x: 398, y: 66, size: 62, color: C.noel.gold },
        { icon: 'snowflake-2', x: 128, y: 12, size: 22, color: C.noel.pine, opacity: 0.4 },
    ],
}));

jobs.push(compose({
    file: 'valentine/gift-cluster.svg',
    width: 460,
    height: 160,
    layers: [
        { icon: 'bear-face', x: 4, y: 46, size: 96, color: '#9a6a52' },
        { icon: 'present', x: 96, y: 48, size: 100, color: C.valentine.rose },
        { icon: 'chocolate-bar', x: 190, y: 62, size: 86, rotate: -10, color: '#6b4128' },
        { icon: 'love-letter', x: 268, y: 58, size: 92, color: C.valentine.roseLight },
        { icon: 'ring-box', x: 356, y: 66, size: 76, color: C.valentine.rose },
        { icon: 'shining-heart', x: 424, y: 20, size: 34, color: C.valentine.blush, opacity: 0.9, bleed: true },
    ],
}));

const daoBranch = [[248, 6], [170, 58], [120, 30], [10, 96]];

jobs.push(compose({
    file: 'tet/peach-corner.svg',
    width: 260,
    height: 200,
    layers: [
        curvePath(daoBranch, { stroke: '#6b4a34', width: 5 }),
        at(onCurve(daoBranch, 0.1), 42, { icon: 'spoted-flower', rotate: 10, color: '#e79ab0', bleed: true }),
        at(onCurve(daoBranch, 0.34), 52, { icon: 'spoted-flower', rotate: -16, color: '#f2b7c8' }),
        at(onCurve(daoBranch, 0.6), 38, { icon: 'spoted-flower', rotate: 24, color: '#e79ab0' }),
        at(onCurve(daoBranch, 0.85), 46, { icon: 'spoted-flower', rotate: -6, color: '#f2b7c8' }),
        at(onCurve(daoBranch, 1), 28, { icon: 'spoted-flower', rotate: 18, color: '#e79ab0', opacity: 0.85, bleed: true }),
        at(onCurve(daoBranch, 0.22), 28, { icon: 'three-leaves', rotate: 120, color: '#6f8f5e', opacity: 0.8 }),
    ],
}));

jobs.push(compose({
    file: 'tet/firework.svg',
    width: 140,
    height: 140,
    layers: [
        { icon: 'firework-rocket', x: 10, y: 10, size: 120, rotate: 25, color: C.tet.gold },
    ],
}));

jobs.push(compose({
    file: 'noel/reindeer.svg',
    width: 160,
    height: 150,
    layers: [
        { icon: 'deer', x: 6, y: 6, size: 140, color: '#5a4632' },
    ],
}));

jobs.push(compose({
    file: 'valentine/cupid.svg',
    width: 140,
    height: 140,
    layers: [
        { icon: 'cupidon-arrow', x: 10, y: 10, size: 120, rotate: -12, color: C.valentine.roseLight },
    ],
}));

for (const j of jobs) {
    console.log(`${j.file.padEnd(30)} ${String(j.bytes).padStart(6)} B`);
}

if (warnings.length) {
    console.warn('\nCẢNH BÁO — hình bị viewBox cắt:');
    warnings.forEach((w) => console.warn(w));
    process.exitCode = 1;
}
