/*
 * CHỌN NƠI NHẬN HÀNG VÀ TÍNH CƯỚC (Giao Hàng Nhanh)
 * ============================================================
 * Ba ô chọn nối nhau — Tỉnh → Quận/Huyện → Phường/Xã — rồi hỏi cước.
 *
 * MỖI Ô GIỮ HAI GIÁ TRỊ:
 *   - <select>       : MÃ SỐ của GHN, thứ dùng để hỏi danh mục và cước;
 *   - <input hidden> : TÊN CHỮ, thứ lưu vào đơn hàng cho người đọc.
 *
 * Đơn hàng là chứng từ phải đọc được sau nhiều năm, kể cả khi GHN đổi mã
 * hoặc cửa hàng đổi sang đơn vị vận chuyển khác. Chỉ lưu mã thì nhân
 * viên mở đơn cũ thấy `to_district_id = 1482` mà không biết đó là đâu.
 *
 * CON SỐ CƯỚC Ở ĐÂY CHỈ ĐỂ XEM TRƯỚC.
 * Biểu mẫu KHÔNG gửi lên số tiền nào. Lúc ghi đơn, máy chủ hỏi lại GHN
 * bằng chính mã quận/phường đã lưu — xem App\Services\Shipping\ShippingQuote.
 * Tài liệu hướng dẫn ghi tổng tiền vào một ô ẩn rồi cho máy chủ tin ô
 * đó; làm vậy thì sửa một dòng trong DevTools là được giao miễn phí.
 *
 * KHÔNG CÓ TỆP NÀY THÌ SAO: phần dự phòng trong Blade hiện ra, khách
 * nhập tay tỉnh/quận/phường như trước, và phí lùi về bảng theo tỉnh.
 * Kém chính xác hơn nhưng vẫn đặt được hàng.
 */

const KHONG_TAI_DUOC = '-- Không tải được, vui lòng nhập tay --';

function dungOption(danhSach, khoaMa, khoaTen, nhanDau) {
    const dau = `<option value="">${nhanDau}</option>`;

    return dau + danhSach.map((x) => {
        /*
         * Dùng textContent qua một phần tử tạm thay vì nhét thẳng tên vào
         * chuỗi HTML: tên do GHN trả về, và một dấu ngoặc nhọn trong đó
         * là đủ để biến nó thành thẻ HTML thật.
         */
        const tam = document.createElement('option');

        tam.value = x[khoaMa];
        tam.textContent = x[khoaTen];

        return tam.outerHTML;
    }).join('');
}

async function taiJson(url, tuyChon = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        ...tuyChon,
    });

    return res.json();
}

export function initGhnAddress() {
    const tinh = document.querySelector('[data-ghn-province]');
    const quan = document.querySelector('[data-ghn-district]');
    const phuong = document.querySelector('[data-ghn-ward]');

    // Không phải trang thanh toán.
    if (!tinh || !quan || !phuong) return;

    const duPhong = document.querySelector('[data-ghn-fallback]');
    const oTinh = document.getElementById('shipping_province');
    const oQuan = document.getElementById('shipping_district');
    const oPhuong = document.getElementById('shipping_ward');
    const oMaQuan = document.querySelector('[data-ghn-district-id]');
    const oMaPhuong = document.querySelector('[data-ghn-ward-code]');

    const hopCuoc = document.querySelector('[data-ghn-fee-box]');
    const chuCuoc = document.querySelector('[data-ghn-fee-text]');
    const ghiChu = document.querySelector('[data-ghn-fee-note]');

    const urlTinh = tinh.dataset.urlProvinces;
    const mauUrlQuan = tinh.dataset.urlDistricts;
    const mauUrlPhuong = tinh.dataset.urlWards;
    const urlCuoc = tinh.dataset.urlFee;
    const token = tinh.dataset.token;

    const khoiChon = [...document.querySelectorAll('.ghn-select')];

    /*
     * TÊN QUẬN / PHƯỜNG CẦN CHỌN LẠI — DÙNG ĐÚNG MỘT LẦN.
     *
     * Có hai lúc cần: khách chọn một địa chỉ trong sổ, và biểu mẫu quay về vì
     * sai một ô khác. Cả hai đều đã có sẵn tên chữ trong ô ẩn.
     *
     * "Dùng một lần" là phần quan trọng: sau khi khách TỰ đổi tỉnh hay quận,
     * tên cũ không còn nghĩa gì — mà tên phường thì hay trùng nhau giữa các
     * quận ("Phường 1", "Thị trấn ..."), nên giữ lại là mời một lựa chọn sai
     * tự nhảy vào ô của khách.
     */
    let tinhCu = oTinh?.value?.trim() || '';
    let quanCu = oQuan?.dataset.cu?.trim() || '';
    let phuongCu = oPhuong?.dataset.cu?.trim() || '';

    /*
     * MỘT TỈNH CÓ HAI CÁCH GỌI TÊN.
     *
     * Sổ địa chỉ và bảng phí của cửa hàng ghi "Thành phố Hồ Chí Minh", danh mục
     * GHN trả về "Hồ Chí Minh". Khớp tuyệt đối thì địa chỉ đã lưu không chọn
     * lại được tỉnh, và vì ba ô nối nhau nên quận / phường cũng đứng im.
     */
    const rutGon = (s) => (s || '')
        .toLowerCase()
        .replace(/^(thành phố|tỉnh|tp\.?|quận|huyện|thị xã|phường|xã|thị trấn)\s+/i, '')
        .replace(/\s+/g, ' ')
        .trim();

    /** Chọn lại theo TÊN trong danh sách vừa tải, rồi quên tên đó đi. */
    const chonLaiTheoTen = (chon, ten) => {
        const ds = [...chon.options];
        const tim = ds.find((o) => o.textContent.trim() === ten)
            || ds.find((o) => rutGon(o.textContent) === rutGon(ten));

        if (tim) {
            chon.value = tim.value;
            chon.dispatchEvent(new Event('change'));
        }
    };

    /**
     * Bật một khối và tắt khối kia — VỪA ẩn VỪA vô hiệu hoá.
     *
     * `hidden` một mình là chưa đủ: ô bị ẩn VẪN ĐƯỢC GỬI LÊN. Hai khối
     * này cùng có `name="shipping_province"`, nên nếu cả hai còn sống
     * thì biểu mẫu gửi cả hai và giá trị SAU đè giá trị TRƯỚC — ô nhập
     * tay rỗng xoá mất lựa chọn GHN, và đơn báo thiếu tỉnh dù khách vừa
     * chọn xong.
     *
     * Ô đã `disabled` thì trình duyệt không gửi. Đó là thứ thật sự tách
     * được hai khối ra.
     */
    const doiKhoi = (dungGhn) => {
        khoiChon.forEach((k) => {
            k.hidden = !dungGhn;
            k.querySelectorAll('select, input').forEach((o) => {
                o.disabled = !dungGhn;
            });
        });

        if (duPhong) {
            duPhong.hidden = dungGhn;
            duPhong.querySelectorAll('select, input').forEach((o) => {
                o.disabled = dungGhn;
            });
        }
    };

    /**
     * Quay về nhập tay khi GHN không dùng được.
     *
     * Gọi ở MỌI nhánh hỏng. Không có nó thì khách nhìn ba ô chọn rỗng,
     * không nhập được gì, và không đặt được hàng — mà nguyên nhân nằm ở
     * một dịch vụ bên ngoài, không phải lỗi của họ.
     */
    const veNhapTay = () => doiKhoi(false);

    /*
     * Bật khối GHN NGAY, trước cả khi danh mục về.
     *
     * Đợi tải xong mới bật thì khách thấy khối nhập tay hiện ra rồi
     * biến mất — một cú nhấp nháy khiến người ta tưởng trang lỗi. Hỏng
     * thì veNhapTay() đưa lại về đúng chỗ cũ.
     */
    doiKhoi(true);

    const veSinh = (chon, nhan) => {
        chon.innerHTML = `<option value="">${nhan}</option>`;
        chon.disabled = true;
    };

    const anCuoc = () => {
        if (hopCuoc) hopCuoc.hidden = true;
        if (oMaPhuong) oMaPhuong.value = '';
    };

    const tien = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(n)) + '₫';

    /**
     * Cho bảng tóm tắt bên phải nói cùng con số với hộp cước.
     *
     * VẤN ĐỀ NÓ SỬA: bảng đó dựng ở MÁY CHỦ từ mã địa giới trong phiên,
     * mà lúc khách còn đang chọn thì phiên chưa có gì — nên nó hiện mức
     * tạm theo tỉnh (50.000₫) trong khi hộp ngay dưới ô địa chỉ đã có
     * cước GHN thật (42.900₫).
     *
     * Hai con số tiền khác nhau trên cùng một màn hình là lỗi nặng hơn
     * cả việc hiện sai một con số: khách không biết tin cái nào.
     *
     * CỘNG Ở ĐÂY CHỈ ĐỂ HIỂN THỊ. Lúc ghi đơn máy chủ tính lại toàn bộ
     * và không đọc con số nào từ biểu mẫu.
     */
    const dongBoTomTat = (cuoc, uocTinh) => {
        const oPhi = document.querySelector('[data-ship-fee]');
        const oTong = document.querySelector('[data-grand-total]');
        const oGhiChu = document.querySelector('[data-ship-note]');

        if (oPhi) oPhi.textContent = tien(cuoc);

        if (oGhiChu) {
            oGhiChu.textContent = uocTinh ? 'tạm tính theo vùng' : 'cước GHN';
        }

        if (oTong) {
            const tienHang = parseFloat(oTong.dataset.itemsTotal || '0') || 0;

            /*
             * Được miễn phí giao thì tổng KHÔNG cộng cước.
             *
             * Nhận biết bằng chính dòng "Miễn phí giao hàng" mà máy chủ
             * đã dựng — không tự đoán lại ngưỡng miễn phí ở trình duyệt.
             * Đoán lại là bản sao thứ hai của một luật tính tiền, và bản
             * sao sẽ lệch khi cửa hàng đổi ngưỡng.
             */
            const duocMien = document.querySelector('.order-summary__row--discount + .order-summary__row--total')
                || document.querySelector('[data-free-shipping]');

            oTong.textContent = tien(tienHang + (duocMien ? 0 : cuoc));
        }
    };

    /* ---------- 1. Tải danh sách Tỉnh/Thành ---------- */

    taiJson(urlTinh)
        .then((res) => {
            if (res.code !== 200 || !Array.isArray(res.data)) throw new Error('GHN');

            tinh.innerHTML = dungOption(res.data, 'ProvinceID', 'ProvinceName', '-- Chọn Tỉnh/Thành --');

            /*
             * Chọn lại tỉnh cũ khi biểu mẫu quay về vì lỗi kiểm tra.
             *
             * Không có bước này thì mỗi lần sai một ô bất kỳ, khách phải
             * chọn lại cả ba cấp địa chỉ từ đầu — và họ sẽ bỏ giữa chừng.
             * Khớp theo TÊN vì đó là thứ đã lưu trong `old()`.
             */
            if (tinhCu) {
                const ten = tinhCu;
                tinhCu = '';
                chonLaiTheoTen(tinh, ten);
            }
        })
        .catch(() => {
            tinh.innerHTML = `<option value="">${KHONG_TAI_DUOC}</option>`;
            veNhapTay();
        });

    /* ---------- 2. Chọn Tỉnh → tải Quận/Huyện ---------- */

    tinh.addEventListener('change', function () {
        // Lưu TÊN để ghi vào đơn; mã số chỉ sống trong ô <select>.
        if (oTinh) oTinh.value = this.options[this.selectedIndex]?.textContent?.trim() ?? '';

        veSinh(quan, '-- Đang tải... --');
        veSinh(phuong, '-- Chọn Quận/Huyện trước --');
        if (oQuan) oQuan.value = '';
        if (oPhuong) oPhuong.value = '';
        if (oMaQuan) oMaQuan.value = '';
        anCuoc();

        if (!this.value) {
            veSinh(quan, '-- Chọn Tỉnh/Thành trước --');

            return;
        }

        taiJson(mauUrlQuan.replace('__ID__', this.value))
            .then((res) => {
                if (res.code !== 200 || !Array.isArray(res.data)) throw new Error('GHN');

                /*
                 * TỈNH KHÔNG CÓ QUẬN/HUYỆN NÀO — nói thẳng ra.
                 *
                 * Danh mục của GHN có bản ghi rác trông y hệt tỉnh thật.
                 * Phần lớn đã bị lọc ở máy chủ, nhưng danh sách chặn là
                 * cố định nên không thể chắc đã hết.
                 *
                 * Bỏ qua trường hợp này thì khách chọn xong tỉnh và nhìn
                 * một ô quận/huyện rỗng, không có gì giải thích — họ sẽ
                 * bấm đi bấm lại rồi bỏ cuộc. Một câu ngắn nói đúng
                 * chuyện gì đang xảy ra rẻ hơn nhiều so với một đơn hàng
                 * mất đi.
                 */
                if (res.data.length === 0) {
                    veSinh(quan, '-- Tỉnh/thành này chưa có dữ liệu, vui lòng chọn mục khác --');

                    return;
                }

                quan.innerHTML = dungOption(res.data, 'DistrictID', 'DistrictName', '-- Chọn Quận/Huyện --');
                quan.disabled = false;

                if (quanCu) {
                    const ten = quanCu;
                    quanCu = '';
                    chonLaiTheoTen(quan, ten);
                }
            })
            .catch(() => veSinh(quan, KHONG_TAI_DUOC));
    });

    /* ---------- 3. Chọn Quận/Huyện → tải Phường/Xã ---------- */

    quan.addEventListener('change', function () {
        if (oQuan) oQuan.value = this.options[this.selectedIndex]?.textContent?.trim() ?? '';
        if (oMaQuan) oMaQuan.value = this.value;

        veSinh(phuong, '-- Đang tải... --');
        if (oPhuong) oPhuong.value = '';
        anCuoc();

        if (!this.value) {
            veSinh(phuong, '-- Chọn Quận/Huyện trước --');

            return;
        }

        taiJson(mauUrlPhuong.replace('__ID__', this.value))
            .then((res) => {
                if (res.code !== 200 || !Array.isArray(res.data)) throw new Error('GHN');

                phuong.innerHTML = dungOption(res.data, 'WardCode', 'WardName', '-- Chọn Phường/Xã --');
                phuong.disabled = false;

                /*
                 * LỖI ĐÃ SỬA: hai cấp trên tự chọn lại còn cấp này thì không.
                 * Khách dùng địa chỉ trong sổ thấy tỉnh và quận điền sẵn, riêng
                 * phường bỏ trống — và `to_ward_code` rỗng theo, nên máy chủ
                 * không hỏi được cước GHN lẫn không tạo được vận đơn.
                 */
                if (phuongCu) {
                    const ten = phuongCu;
                    phuongCu = '';
                    chonLaiTheoTen(phuong, ten);
                }
            })
            .catch(() => veSinh(phuong, KHONG_TAI_DUOC));
    });

    /* ---------- 4. Chọn Phường/Xã → hỏi cước ---------- */

    phuong.addEventListener('change', function () {
        if (oPhuong) oPhuong.value = this.options[this.selectedIndex]?.textContent?.trim() ?? '';
        if (oMaPhuong) oMaPhuong.value = this.value;

        if (!this.value || !quan.value) {
            anCuoc();

            return;
        }

        if (hopCuoc) hopCuoc.hidden = false;
        if (chuCuoc) chuCuoc.textContent = 'đang tính...';
        if (ghiChu) ghiChu.textContent = '';

        taiJson(urlCuoc, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                to_district_id: quan.value,
                to_ward_code: this.value,
            }),
        })
            .then((res) => {
                if (res.code !== 200 || !res.data) throw new Error('GHN');

                const cuoc = parseInt(res.data.total, 10) || 0;

                if (chuCuoc) {
                    chuCuoc.textContent = tien(cuoc);
                }

                dongBoTomTat(cuoc, res.uoc_tinh);

                /*
                 * NÓI RÕ KHI ĐÂY LÀ MỨC TẠM.
                 *
                 * Máy chủ trả cờ `uoc_tinh` khi phải lùi về bảng phí theo
                 * tỉnh. Không nói ra thì khách tưởng đó là cước chính
                 * xác, rồi tổng tiền ở bước cuối lại khác — và họ không
                 * có cách nào hiểu vì sao.
                 */
                if (ghiChu) {
                    ghiChu.textContent = res.uoc_tinh
                        ? '(tạm tính theo vùng, chưa lấy được cước GHN)'
                        : '';
                }
            })
            .catch(() => {
                if (chuCuoc) chuCuoc.textContent = 'chưa tính được';
                if (ghiChu) ghiChu.textContent = '(phí sẽ được tính lại khi đặt hàng)';
            });
    });
}
