/* Phần dùng chung của các script tải ảnh từ Openverse. */

import fs from 'node:fs';
import path from 'node:path';

export const API = 'https://api.openverse.org/v1/images/';
export const UA = { 'User-Agent': 'btlar-student-project/1.0' };

export const LICENSE_OK = new Set(['cc0', 'pdm', 'by', 'by-sa']);

export const TITLE_BLOCK =
    /lego|toy|plastic|artificial|fake|origami|paper craft|drawing|painting|tattoo|cake|clip ?art|render|minecraft|cartoon|illustration|engraving|sketch|lithograph|botanical print|funeral|grave|cemetery|memorial|condolence/i;

export const MUST_DEFAULT =
    /flower|floral|bouquet|blossom|rose|plant|pot|succulent|cact|bonsai|leaf|leaves|garden/i;

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export async function download(url) {
    let res;

    try {
        res = await fetch(url, { headers: UA, redirect: 'follow' });
    } catch {
        return { ok: false, why: 'khong ket noi duoc' };
    }

    if (!res.ok) return { ok: false, why: `HTTP ${res.status}` };

    const type = res.headers.get('content-type') ?? '';
    if (!type.startsWith('image/')) return { ok: false, why: `tra ve ${type || 'khong ro'}` };

    const buf = Buffer.from(await res.arrayBuffer());
    const isJpeg = buf[0] === 0xff && buf[1] === 0xd8;
    const isPng = buf[0] === 0x89 && buf[1] === 0x50;

    if (buf.length < 15_000 || (!isJpeg && !isPng)) {
        return { ok: false, why: `du lieu hong (${buf.length} B)` };
    }

    return { ok: true, buf, ext: isPng ? 'png' : 'jpg' };
}

export async function search(q, opts = {}) {
    const { must, block, shape = 'portrait', minWidth = 700 } = opts;

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

        if (!(must ?? MUST_DEFAULT).test(it.title ?? '')) return false;

        if (block && block.test(it.title ?? '')) return false;

        if (!it.width || !it.height) return false;
        if (it.width < minWidth) return false;

        return shape === 'landscape'
            ? it.width >= it.height * 0.95
            : it.width <= it.height * 1.6;
    });
}

export async function fetchInto(cfg) {
    const { outDir, filePrefix, targets, shape = 'portrait', only = [] } = cfg;

    const dem = new Map();

    for (const t of targets) {
        dem.set(t.slug, (dem.get(t.slug) ?? 0) + 1);
    }

    const trung = [...dem].filter(([, n]) => n > 1).map(([s]) => s);

    if (trung.length) {
        throw new Error('Slug trung trong danh sach targets: ' + trung.join(', '));
    }

    fs.mkdirSync(outDir, { recursive: true });

    const todo = only.length ? targets.filter((t) => only.includes(t.slug)) : targets;
    const creditsPath = path.join(outDir, 'credits.json');

    const credits = fs.existsSync(creditsPath)
        ? JSON.parse(fs.readFileSync(creditsPath, 'utf8')).filter(
              (c) => !todo.some((t) => c.slug === t.slug)
          )
        : [];

    for (const target of todo) {
        process.stdout.write(`${target.slug.padEnd(32)} `);

        try {
            await sleep(1200);

            let results = [];

            for (const q of [target.q, ...(target.alt ?? [])]) {
                results = await search(q, { must: target.must, block: target.block, shape });
                if (results.length) break;
                await sleep(800);
            }

            let saved = null;
            let why = 'khong co anh hop le';

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
                console.log('BO QUA - ' + why);
                continue;
            }

            const file = `${target.slug}.${saved.ext}`;
            fs.writeFileSync(path.join(outDir, file), saved.buf);

            const license = `${(saved.it.license ?? '').toUpperCase()} ${saved.it.license_version ?? ''}`.trim();

            credits.push({
                slug: target.slug,
                file: filePrefix + file,
                hint: target.hint ?? '',
                title: saved.it.title ?? '',
                author: saved.it.creator ?? 'Khong ghi ten',
                license,
                licenseUrl: saved.it.license_url ?? '',
                pageUrl: saved.it.foreign_landing_url ?? '',
            });

            console.log(`${String(Math.round(saved.buf.length / 1024)).padStart(5)} KB  ${license}`);
        } catch (err) {
            console.log('LOI: ' + err.message);
        }
    }

    fs.writeFileSync(creditsPath, JSON.stringify(credits, null, 2));

    return { total: targets.length, saved: credits.length };
}
