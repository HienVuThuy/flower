<?php

namespace App\Services\Shop;

use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * THAM SỐ KINH DOANH — những con số trước đây cố định trong code/config mà chủ cửa hàng
 * cần tự chỉnh: ngưỡng, phí, điểm, hạn. Mặc định nằm trong config; admin lưu thì đè lên,
 * bỏ trống thì quay về mặc định. Mọi nơi dùng đọc qua đây, không đọc config trực tiếp.
 */
class ThamSoKinhDoanh
{
    private const TIEN_TO = 'tham_so:';

    /** kieu: tien (đồng) | so (số nguyên) | moc_tien (danh sách mốc tiền tăng dần) */
    public const DS = [
        'catalog.cao_cap_tu' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => 'Ngưỡng "Hoa cao cấp"', 'kieu' => 'tien', 'min' => 0, 'max' => 1000000000,
            'goi_y' => 'Hoa (tươi hoặc giả) có giá đang bán từ mức này vào bộ sưu tập Hoa cao cấp.'],
        'catalog.moc_gia' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => 'Mốc chia khoảng giá ở bộ lọc', 'kieu' => 'moc_tien', 'min' => 1000, 'max' => 1000000000,
            'goi_y' => 'Từ 1 đến 6 mốc, cách nhau bằng dấu phẩy. Ví dụ 300000, 500000, 1000000 thành "Dưới 300.000₫", "300.000 – 500.000₫"…'],
        'catalog.new_arrival_days' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => '"Hàng mới về" trong bao nhiêu ngày', 'kieu' => 'so', 'min' => 1, 'max' => 365, 'don_vi' => 'ngày'],
        'catalog.new_arrival_limit' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => 'Số món "Hàng mới về" ở trang chủ', 'kieu' => 'so', 'min' => 1, 'max' => 24, 'don_vi' => 'món'],
        'kinh_doanh.nguong_chi_con' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => 'Hiện "Chỉ còn X" khi tồn kho từ 1 đến', 'kieu' => 'so', 'min' => 0, 'max' => 100, 'don_vi' => 'cái',
            'goi_y' => 'Đặt 0 để tắt. Chỉ hiện con số tồn kho thật.'],
        'kinh_doanh.nhac_dip_truoc_ngay' => ['nhom' => 'Danh mục & hiển thị', 'nhan' => 'Nhắc ngày lễ tặng hoa trước', 'kieu' => 'so', 'min' => 0, 'max' => 90, 'don_vi' => 'ngày',
            'goi_y' => 'Trang chủ nhắc "Còn N ngày nữa là 20/10…" khi có hàng hợp dịp. Đặt 0 để tắt.'],

        'kinh_doanh.sap_het_hang' => ['nhom' => 'Kho & giỏ hàng', 'nhan' => 'Coi là "sắp hết hàng" khi tồn từ 1 đến', 'kieu' => 'so', 'min' => 1, 'max' => 1000, 'don_vi' => 'cái'],
        'kinh_doanh.gio_toi_da_moi_mon' => ['nhom' => 'Kho & giỏ hàng', 'nhan' => 'Số lượng tối đa mỗi món trong giỏ', 'kieu' => 'so', 'min' => 1, 'max' => 999, 'don_vi' => 'cái',
            'goi_y' => 'Đơn lớn hơn thì khách gửi yêu cầu "Sự kiện & số lượng lớn".'],
        'kinh_doanh.han_doi_ngay' => ['nhom' => 'Kho & giỏ hàng', 'nhan' => 'Hạn đổi hàng sau khi giao', 'kieu' => 'so', 'min' => 0, 'max' => 90, 'don_vi' => 'ngày'],

        'shipping.free_from' => ['nhom' => 'Giao hàng', 'nhan' => 'Miễn phí giao cho đơn từ', 'kieu' => 'tien', 'min' => 0, 'max' => 1000000000,
            'goi_y' => 'Phí giao do GHN tính theo địa chỉ; đơn từ mức này cửa hàng chịu phí. Đặt 0 để không miễn phí giao.'],

        'kinh_doanh.diem.dong_moi_diem_tich' => ['nhom' => 'Điểm thưởng', 'nhan' => 'Chi bao nhiêu thì được 1 điểm', 'kieu' => 'tien', 'min' => 1000, 'max' => 10000000],
        'kinh_doanh.diem.dong_moi_diem_dung' => ['nhom' => 'Điểm thưởng', 'nhan' => '1 điểm trừ được', 'kieu' => 'tien', 'min' => 1, 'max' => 100000],
        'kinh_doanh.diem.dung_toi_thieu' => ['nhom' => 'Điểm thưởng', 'nhan' => 'Dùng ít nhất mỗi đơn', 'kieu' => 'so', 'min' => 1, 'max' => 100000, 'don_vi' => 'điểm'],
        'kinh_doanh.diem.phan_tram_toi_da' => ['nhom' => 'Điểm thưởng', 'nhan' => 'Điểm trừ tối đa', 'kieu' => 'so', 'min' => 1, 'max' => 100, 'don_vi' => '% tiền hàng'],
        'kinh_doanh.diem.danh_gia_nhan_xet' => ['nhom' => 'Điểm thưởng', 'nhan' => 'Thưởng đánh giá có nhận xét', 'kieu' => 'so', 'min' => 0, 'max' => 1000, 'don_vi' => 'điểm'],
        'kinh_doanh.diem.danh_gia_chi_sao' => ['nhom' => 'Điểm thưởng', 'nhan' => 'Thưởng đánh giá chỉ chấm sao', 'kieu' => 'so', 'min' => 0, 'max' => 1000, 'don_vi' => 'điểm'],

        'kinh_doanh.cham_ho.phi_gap' => ['nhom' => 'Chăm cây hộ', 'nhan' => 'Phí nhận cây gấp', 'kieu' => 'tien', 'min' => 0, 'max' => 10000000,
            'goi_y' => 'Thu khi khách cần nhận cây lại mà báo trước quá ít ngày. Đặt 0 để không thu.'],
        'kinh_doanh.cham_ho.bao_gap_ngay' => ['nhom' => 'Chăm cây hộ', 'nhan' => 'Báo trước dưới bao nhiêu ngày thì tính là gấp', 'kieu' => 'so', 'min' => 0, 'max' => 30, 'don_vi' => 'ngày'],
        'kinh_doanh.cham_ho.nhac_truoc_ngay' => ['nhom' => 'Chăm cây hộ', 'nhan' => 'Nhắc khách trước hạn trả cây', 'kieu' => 'so', 'min' => 1, 'max' => 60, 'don_vi' => 'ngày',
            'goi_y' => 'Đến mốc này phiếu chuyển sang "Sắp trả cây" để cửa hàng chuẩn bị.'],

        'risk.thresholds.high_value' => ['nhom' => 'Rủi ro đơn hàng', 'nhan' => 'Đơn COD giá trị cao từ', 'kieu' => 'tien', 'min' => 0, 'max' => 1000000000,
            'goi_y' => 'Đơn trả khi nhận hàng từ mức này bị cộng điểm rủi ro.'],
        'risk.review_from' => ['nhom' => 'Rủi ro đơn hàng', 'nhan' => 'Cần xem lại khi điểm rủi ro từ', 'kieu' => 'so', 'min' => 1, 'max' => 100, 'don_vi' => 'điểm'],

        'ai.moi_phut' => ['nhom' => 'Trợ lý AI', 'nhan' => 'Số lượt hỏi mỗi khách mỗi phút', 'kieu' => 'so', 'min' => 1, 'max' => 60, 'don_vi' => 'lượt'],
        'ai.moi_ngay' => ['nhom' => 'Trợ lý AI', 'nhan' => 'Số lượt hỏi mỗi khách mỗi ngày', 'kieu' => 'so', 'min' => 1, 'max' => 1000, 'don_vi' => 'lượt',
            'goi_y' => 'Mỗi lượt hỏi là một lần trả tiền cho nhà cung cấp AI.'],
    ];

    public static function so(string $khoa): int
    {
        return (int) self::giaTri($khoa);
    }

    /** @return list<int> */
    public static function mocGia(): array
    {
        return array_map('intval', (array) self::giaTri('catalog.moc_gia'));
    }

    /** Khoảng giá [từ, đến] dựng từ các mốc — null là không chặn. */
    public static function khoangGia(): array
    {
        $moc = self::mocGia();

        if ($moc === []) {
            return [];
        }

        $out = [[null, $moc[0]]];

        for ($i = 1; $i < count($moc); $i++) {
            $out[] = [$moc[$i - 1], $moc[$i]];
        }

        $out[] = [end($moc), null];

        return $out;
    }

    public static function giaTri(string $khoa): mixed
    {
        $luu = self::docLuu($khoa);

        if ($luu === null || $luu === '') {
            return config($khoa);
        }

        return (self::DS[$khoa]['kieu'] ?? null) === 'moc_tien'
            ? array_map('intval', explode(',', $luu))
            : (int) $luu;
    }

    /** Chưa có bảng settings (cài mới, chưa migrate) thì dùng mặc định thay vì làm sập trang. */
    private static function docLuu(string $khoa): ?string
    {
        try {
            $luu = Setting::get(self::TIEN_TO . $khoa);
        } catch (QueryException) {
            return null;
        }

        return is_scalar($luu) ? (string) $luu : null;
    }

    public static function macDinh(string $khoa): mixed
    {
        return config($khoa);
    }

    public static function daDoi(string $khoa): bool
    {
        $luu = self::docLuu($khoa);

        return $luu !== null && $luu !== '';
    }

    /** Lưu một giá trị đã kiểm; null = quay về mặc định. */
    public static function luu(string $khoa, int|array|null $giaTri): void
    {
        if (! array_key_exists($khoa, self::DS)) {
            return;
        }

        $chuoi = is_array($giaTri) ? implode(',', $giaTri) : ($giaTri === null ? null : (string) $giaTri);

        $macDinh = self::macDinh($khoa);
        $chuoiMacDinh = is_array($macDinh) ? implode(',', $macDinh) : (string) $macDinh;

        Setting::set(self::TIEN_TO . $khoa, $chuoi === $chuoiMacDinh ? null : $chuoi);
    }

    /** Đọc chuỗi admin gõ ("300.000, 500.000") thành danh sách mốc; null nếu sai. */
    public static function docMoc(string $chuoi): ?array
    {
        $moc = [];

        foreach (preg_split('/[,;\s]+/', trim($chuoi)) ?: [] as $phan) {
            $so = preg_replace('/\D/', '', $phan);

            if ($so === '') {
                continue;
            }

            $moc[] = (int) $so;
        }

        $moc = array_values(array_unique($moc));
        sort($moc);

        return $moc === [] || count($moc) > 6 ? null : $moc;
    }

    public static function nhom(): array
    {
        $out = [];

        foreach (self::DS as $khoa => $dinhNghia) {
            $out[$dinhNghia['nhom']][$khoa] = $dinhNghia;
        }

        return $out;
    }
}
