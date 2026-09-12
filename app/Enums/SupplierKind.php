<?php

namespace App\Enums;

/**
 * Nguồn hàng thuộc loại nào.
 * ============================================================
 * KHÔNG PHẢI NHÃN CHO ĐẸP.
 *
 * Cùng một giá tiền, ba nguồn này không phải cùng một lựa chọn:
 *
 *   - Nông dân: rẻ nhất, nhưng phải đặt trước và có mùa mới có.
 *   - Vựa: đắt hơn, bù lại lúc nào cũng có và giao tận nơi.
 *   - Chợ đầu mối: rẻ lúc sáng sớm, nhưng phải tự đi lấy và tự chọn.
 *
 * Nên khi so giá phải so TRONG CÙNG MỘT LOẠI trước, rồi mới so chéo —
 * so thẳng giá chợ với giá vựa rồi kết luận "vựa đắt" là bỏ qua tiền
 * xăng, tiền công đi lấy và rủi ro hết hàng.
 */
enum SupplierKind: string
{
    case NongDan = 'nong_dan';
    case Vua = 'vua';
    case Cho = 'cho';
    case CuaHang = 'cua_hang';
    case VuonNha = 'vuon_nha';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::NongDan => 'Nông dân / nhà vườn',
            self::Vua => 'Vựa / đầu mối',
            self::Cho => 'Chợ',
            self::CuaHang => 'Cửa hàng / công ty',
            self::VuonNha => 'Vườn nhà',
            self::Khac => 'Khác',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::NongDan => 'Thường rẻ nhất, nhưng phải đặt trước và phụ thuộc mùa.',
            self::Vua => 'Đắt hơn nhưng có quanh năm, hay giao tận nơi.',
            self::Cho => 'Rẻ lúc sáng sớm, phải tự đi lấy và tự chọn.',
            self::CuaHang => 'Có hoá đơn, giá ổn định, thường dùng cho chậu và vật tư.',
            self::VuonNha => 'Cây tự trồng. Không mất tiền mua nhưng vẫn có giá vốn: giống, đất, phân, công.',
            self::Khac => 'Không thuộc nhóm nào ở trên.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
