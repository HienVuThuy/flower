/* Tải ảnh minh hoạ danh mục / nhu cầu / sản phẩm. */

import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'resources/images/catalog');
const API = 'https://api.openverse.org/v1/images/';
const UA = { 'User-Agent': 'btlar-student-project/1.0' };

const LICENSE_OK = new Set(['cc0', 'pdm', 'by', 'by-sa']);

const TITLE_BLOCK =
    /lego|toy|plastic|artificial|fake|origami|paper craft|drawing|painting|tattoo|cake|clip ?art|render|minecraft/i;

const TARGETS = [
    { slug: 'cat-hoa', q: 'rose bouquet florist', hint: 'Danh mục: Hoa' },
    {
        slug: 'cat-cay-canh',
        q: 'ornamental plants in pots nursery',
        alt: ['garden centre plant pots row', 'potted green plants outdoor display'],
        hint: 'Danh mục: Cây cảnh',
    },
    {
        slug: 'cat-cay-de-ban',
        q: 'potted plant on office desk',
        alt: ['small green plant pot table', 'houseplant on windowsill pot'],
        hint: 'Danh mục: Cây để bàn',
    },
    {
        slug: 'cat-bonsai',
        q: 'bonsai juniper tree pot exhibition',
        alt: ['bonsai display real tree', 'bonsai pine tree pot'],
        hint: 'Danh mục: Cây bonsai',
    },

    { slug: 'intent-tang-nguoi-thuong', q: 'flower bouquet gift wrapped', hint: 'Nhu cầu: Tặng người thương' },
    {
        slug: 'intent-trang-tri',
        q: 'indoor plants styled living room',
        alt: ['green plants modern interior', 'houseplant decor bright room'],
        hint: 'Nhu cầu: Trang trí không gian sống',
    },
    { slug: 'intent-su-kien', q: 'wedding flower table decoration', hint: 'Nhu cầu: Khai trương & sự kiện' },
    { slug: 'intent-nguoi-moi', q: 'succulent plants in pots', hint: 'Nhu cầu: Người mới trồng cây' },

    { slug: 'product-monstera', q: 'monstera deliciosa plant pot', hint: 'Sản phẩm: Monstera Deliciosa' },
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function download(url) {
    let res;

    try {
        res = await fetch(url, { headers: UA, redirect: 'follow' });
    } catch {
        return { ok: false, why: 'không kết nối được' };
    }

    if (!res.ok) return { ok: false, why: `HTTP ${res.status}` };

    const type = res.headers.get('content-type') ?? '';
    if (!type.startsWith('image/')) return { ok: false, why: `trả về ${type || 'không rõ'}` };

    const buf = Buffer.from(await res.arrayBuffer());
    const isJpeg = buf[0] === 0xff && buf[1] === 0xd8;
    const isPng = buf[0] === 0x89 && buf[1] === 0x50;

    if (buf.length < 15_000 || (!isJpeg && !isPng)) {
        return { ok: false, why: `dữ liệu hỏng (${buf.length} B)` };
    }

    return { ok: true, buf, ext: isPng ? 'png' : 'jpg' };
}

async function search(q) {
    const url =
        API +
        '?' +
        new URLSearchParams({
            q,
            license_type: 'commercial,modification',
            size: 'medium',
            page_size: '20',
            mature: 'false',
        });

    const res = await fetch(url, { headers: UA });
    if (!res.ok) throw new Error(`API ${res.status}`);

    const json = await res.json();

    return (json.results ?? []).filter((it) => {
        if (!LICENSE_OK.has((it.license ?? '').toLowerCase())) return false;
        if (TITLE_BLOCK.test(it.title ?? '')) return false;
        if (!it.width || !it.height) return false;

        if (it.width < it.height * 1.1) return false;
        if (it.width < 900) return false;

        return true;
    });
}

fs.mkdirSync(OUT, { recursive: true });

const only = process.argv.slice(2);
const todo = only.length ? TARGETS.filter((t) => only.includes(t.slug)) : TARGETS;

const creditsPath = path.join(OUT, 'credits.json');
const credits = only.length && fs.existsSync(creditsPath)
    ? JSON.parse(fs.readFileSync(creditsPath, 'utf8')).filter((c) => !only.some((s) => c.file.startsWith(s)))
    : [];

for (const target of todo) {
    process.stdout.write(`${target.slug.padEnd(28)} `);

    try {
        await sleep(1500);

        let results = [];
        for (const q of [target.q, ...(target.alt ?? [])]) {
            results = await search(q);
            if (results.length) break;
            await sleep(900);
        }

        let saved = null;
        let why = 'không có ảnh hợp lệ';

        for (const it of results.slice(0, 6)) {
            const got = await download(it.url);

            if (got.ok) {
                saved = { it, ...got };
                break;
            }

            why = got.why;
            await sleep(400);
        }

        if (!saved) {
            console.log('BỎ QUA — ' + why);
            continue;
        }

        const file = `${target.slug}.${saved.ext}`;
        fs.writeFileSync(path.join(OUT, file), saved.buf);

        const license = `${(saved.it.license ?? '').toUpperCase()} ${saved.it.license_version ?? ''}`.trim();

        credits.push({
            file,
            hint: target.hint,
            title: saved.it.title ?? '',
            author: saved.it.creator ?? 'Không ghi tên',
            license,
            licenseUrl: saved.it.license_url ?? '',
            pageUrl: saved.it.foreign_landing_url ?? '',
        });

        console.log(`${String(Math.round(saved.buf.length / 1024)).padStart(5)} KB  ${license}`);
    } catch (err) {
        console.log('LỖI: ' + err.message);
    }
}

fs.writeFileSync(path.join(OUT, 'credits.json'), JSON.stringify(credits, null, 2));
console.log(`\nĐã tải ${credits.length}/${TARGETS.length} ảnh. Nguồn ghi ở catalog/credits.json`);
