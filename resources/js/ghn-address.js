/* CHỌN NƠI NHẬN HÀNG VÀ TÍNH CƯỚC (Giao Hàng Nhanh) */

const KHONG_TAI_DUOC = '-- Không tải được, vui lòng nhập tay --';

function dungOption(danhSach, khoaMa, khoaTen, nhanDau) {
    const dau = `<option value="">${nhanDau}</option>`;

    return dau + danhSach.map((x) => {
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

    let tinhCu = oTinh?.value?.trim() || '';
    let quanCu = oQuan?.dataset.cu?.trim() || '';
    let phuongCu = oPhuong?.dataset.cu?.trim() || '';

    const rutGon = (s) => (s || '')
        .toLowerCase()
        .replace(/^(thành phố|tỉnh|tp\.?|quận|huyện|thị xã|phường|xã|thị trấn)\s+/i, '')
        .replace(/\s+/g, ' ')
        .trim();

    const chonLaiTheoTen = (chon, ten) => {
        const ds = [...chon.options];
        const tim = ds.find((o) => o.textContent.trim() === ten)
            || ds.find((o) => rutGon(o.textContent) === rutGon(ten));

        if (tim) {
            chon.value = tim.value;
            chon.dispatchEvent(new Event('change'));
        }
    };

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

    const veNhapTay = () => doiKhoi(false);

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

            const duocMien = document.querySelector('.order-summary__row--discount + .order-summary__row--total')
                || document.querySelector('[data-free-shipping]');

            oTong.textContent = tien(tienHang + (duocMien ? 0 : cuoc));
        }
    };

    taiJson(urlTinh)
        .then((res) => {
            if (res.code !== 200 || !Array.isArray(res.data)) throw new Error('GHN');

            tinh.innerHTML = dungOption(res.data, 'ProvinceID', 'ProvinceName', '-- Chọn Tỉnh/Thành --');

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

    tinh.addEventListener('change', function () {
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

                if (phuongCu) {
                    const ten = phuongCu;
                    phuongCu = '';
                    chonLaiTheoTen(phuong, ten);
                }
            })
            .catch(() => veSinh(phuong, KHONG_TAI_DUOC));
    });

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
