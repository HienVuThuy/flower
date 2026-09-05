<?php

namespace App\Enums;

/**
 * HÀNG CHÍNH hay HÀNG PHỤ TRỢ.
 * ============================================================
 * ĐÂY LÀ CỬA HÀNG HOA VÀ CÂY CẢNH. Chậu, đất, phân bón, kéo cắt cành đều
 * cần thiết, nhưng không ai vào đây để mua một gói đất — họ mua đất VÌ
 * vừa mua một cái cây.
 *
 * Trước khi có trục này, trang chủ sắp theo "mới nhất" nên năm gói vật tư
 * seed sau cùng đẩy hết hoa xuống dưới. Khách mở trang bán hoa ra và thấy
 * đầu tiên là "Kéo cắt cành mũi cong" — đúng kỹ thuật, sai hoàn toàn về
 * nghiệp vụ.
 *
 * HAI GIÁ TRỊ, VÀ RANH GIỚI RẤT RÕ:
 *   Plant  - thứ khách đến đây để mua: hoa, cây, bonsai, sen đá.
 *   Supply - thứ khách mua KÈM: chậu, đĩa lót, đất, phân, dụng cụ.
 *
 * KHÔNG TRÙNG VỚI CÁC TRỤC ĐÃ CÓ, và đó là điều kiện để nó tồn tại:
 *   category      - "Hoa cưới", "Sen đá"   -> nhóm hàng để duyệt
 *   selling_form  - bó / chậu / giỏ        -> hàng ở dạng gì
 *   product_type  - hoa / cây / khác       -> bản chất sinh học
 *   kind          - chính / phụ trợ        -> VAI TRÒ trong cửa hàng
 * Ba trục đầu đều không trả lời được câu "thứ này có đáng lên trang chủ
 * không". Xem QĐ-08 về nguyên tắc không trộn trục.
 */
enum CategoryKind: string
{
    case Plant = 'plant';
    case Supply = 'supply';

    public function label(): string
    {
        return match ($this) {
            self::Plant => 'Hoa & cây cảnh',
            self::Supply => 'Phụ kiện & vật tư',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Plant => 'Hàng chính của cửa hàng',
            self::Supply => 'Đồ dùng và vật tư mua kèm',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
