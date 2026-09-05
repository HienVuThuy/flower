/*
 * Phần dùng chung của các script tải ảnh từ Openverse.
 * ============================================================
 * TÁCH RA VÌ NAY CÓ HAI NƠI TẢI ẢNH — ảnh sản phẩm và ảnh danh mục.
 * Luật giấy phép, danh sách từ khoá cấm, và cách kiểm tra tệp tải về là
 * MỘT, nên chỉ được có một bản. Chép sang tệp thứ hai thì sớm muộn hai
 * bản sẽ lệch, và lệch ở đây nghĩa là một nhánh âm thầm nhận ảnh không
 * đủ điều kiện dùng thương mại.
 *
 * Cái KHÁC nhau giữa hai nơi được truyền vào qua tham số: thư mục lưu,
 * tỉ lệ khung ảnh mong muốn, và danh sách mục tiêu.
 */

import fs from 'node:fs';
import path from 'node:path';

export const API = 'https://api.openverse.org/v1/images/';
export const UA = { 'User-Agent': 'btlar-student-project/1.0' };

/** Chỉ nhận giấy phép cho phép dùng thương mại và sửa đổi. */
export const LICENSE_OK = new Set(['cc0', 'pdm', 'by', 'by-sa']);

/*
 * Loại ảnh không dùng được.
 *
 * Ba nhóm, mỗi nhóm đều từ một lần nhận nhầm có thật:
 *  1. không phải cây thật  — từng nhận một cây bonsai LEGO;
 *  2. tranh vẽ / bản khắc  — "Snake plant illustration from Les liliacées
 *     (1805)" là bản khắc thực vật học, không phải ảnh chụp;
 *  3. SAI DỊP             — "Funeral flower arrangement" (hoa tang lễ)
 *     tuyệt đối không được lên trang bán hoa khai trương.
 */
export const TITLE_BLOCK =
    /lego|toy|plastic|artificial|fake|origami|paper craft|drawing|painting|tattoo|cake|clip ?art|render|minecraft|cartoon|illustration|engraving|sketch|lithograph|botanical print|funeral|grave|cemetery|memorial|condolence/i;

/*
 * Từ BẮT BUỘC phải có trong tiêu đề ảnh.
 *
 * TITLE_BLOCK chỉ loại thứ xấu đã biết, nên vẫn lọt những ảnh hoàn toàn
 * không liên quan: truy vấn "flower arrangement basket large" từng trả về
 * một dãy TỔ ONG, "roses in gift box" trả về THIỆP CHÚC MỪNG. Openverse
 * xếp hạng theo độ liên quan mờ, nên phải tự đòi hỏi.
 */
export const MUST_DEFAULT =
    /flower|floral|bouquet|blossom|rose|plant|pot|succulent|cact|bonsai|leaf|leaves|garden/i;

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/**
 * Tải một ảnh về bộ nhớ và kiểm tra nó thật sự là ảnh.
 *
 * KHÔNG tin Content-Type suông: máy chủ ảnh hay trả về trang lỗi HTML
 * kèm header ảnh. Kiểm hai byte đầu (magic number) mới chắc.
 */
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

/**
 * Tìm ảnh hợp lệ trên Openverse.
 *
 * @param {object} opts
 * @param {RegExp} [opts.must]      từ bắt buộc trong tiêu đề
 * @param {'portrait'|'landscape'} [opts.shape]
 *        Khung ảnh đích. Thẻ sản phẩm là khung ĐỨNG (4:5) nên ảnh
 *        panorama bị cắt cụt hai đầu; thẻ danh mục là khung NGANG (16:9)
 *        nên ảnh dọc mới là thứ bị cắt. Lọc từ đây rẻ hơn nhiều so với
 *        tải về rồi mới phát hiện.
 * @param {number} [opts.minWidth]
 */
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

        // Phải NÓI VỀ hoa/cây, không chỉ là "không xấu".
        if (!(must ?? MUST_DEFAULT).test(it.title ?? '')) return false;

        /*
         * CHẶN RIÊNG THEO TỪNG MÓN.
         *
         * TITLE_BLOCK chặn những thứ xấu chung cho mọi món. Nhưng có
         * những từ chỉ sai VỚI MỘT MÓN cụ thể:
         *
         *   - "Money-tree (Pachira aquatica) FLOWERS" — đúng loài, nhưng
         *     là ảnh chụp HOA, còn sản phẩm là cây bện thân.
         *   - "FertiliSED BULB" — lọt qua /fertili/ nhưng ảnh là một củ
         *     hoa, không phải phân bón.
         *
         * Cấm những từ đó ở TITLE_BLOCK thì hỏng các món khác (rất
         * nhiều sản phẩm cần từ "flower" trong tiêu đề). Nên nó phải
         * khai được theo từng món.
         */
        if (block && block.test(it.title ?? '')) return false;

        if (!it.width || !it.height) return false;
        if (it.width < minWidth) return false;

        return shape === 'landscape'
            // Khung ngang: loại ảnh dọc, chấp nhận vuông trở lên.
            ? it.width >= it.height * 0.95
            // Khung đứng: loại panorama, chấp nhận vuông hoặc hơi đứng.
            : it.width <= it.height * 1.6;
    });
}

/**
 * Chạy trọn một đợt tải: tìm → tải → lưu → ghi nguồn.
 *
 * @param {object} cfg
 * @param {string} cfg.outDir       thư mục lưu ảnh
 * @param {string} cfg.filePrefix   tiền tố ghi vào credits.json (vd 'categories/')
 * @param {Array}  cfg.targets      [{ slug, q, alt?, must?, hint? }]
 * @param {'portrait'|'landscape'} [cfg.shape]
 * @param {string[]} [cfg.only]     chỉ chạy các slug này
 */
export async function fetchInto(cfg) {
    const { outDir, filePrefix, targets, shape = 'portrait', only = [] } = cfg;

    fs.mkdirSync(outDir, { recursive: true });

    const todo = only.length ? targets.filter((t) => only.includes(t.slug)) : targets;
    const creditsPath = path.join(outDir, 'credits.json');

    // Giữ lại phần ghi nguồn của những ảnh không chạy lại lần này.
    const credits = fs.existsSync(creditsPath)
        ? JSON.parse(fs.readFileSync(creditsPath, 'utf8')).filter(
              (c) => !todo.some((t) => c.slug === t.slug)
          )
        : [];

    for (const target of todo) {
        process.stdout.write(`${target.slug.padEnd(32)} `);

        try {
            // Openverse giới hạn tần suất; nghỉ giữa các lần gọi.
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
