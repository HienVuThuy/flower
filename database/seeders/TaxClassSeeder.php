<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * Các NHÓM THUẾ SUẤT cửa hàng có thể chọn.
 * ============================================================
 * ⚠️ SEEDER NÀY KHÔNG PHÂN LOẠI SẢN PHẨM, VÀ ĐÓ LÀ CHỦ Ý.
 *
 * Nó chỉ dựng sẵn những LỰA CHỌN để trang quản trị có gì mà chọn. Không
 * một sản phẩm nào bị gán nhóm ở đây — tất cả vẫn để `tax_class_id`
 * NULL, tức "dùng mức mặc định của cửa hàng".
 *
 * Vì sao không gán sẵn: mã nguồn KHÔNG BIẾT mặt hàng của cửa hàng này
 * thuộc diện nào. Việc phân loại phải căn cứ mặt hàng thực tế và quy
 * định áp dụng tại thời điểm bán — kế toán quyết định. Đoán giúp rồi ghi
 * vào cơ sở dữ liệu là bịa ra một dữ kiện kế toán, và nó sẽ đi thẳng vào
 * hoá đơn mà không ai kiểm lại.
 *
 * ============================================================
 * CHẠY LẠI ĐƯỢC. Tra theo `code` rồi cập nhật, nên chạy hai lần không
 * sinh bản sao — và cũng KHÔNG ghi đè `is_active` nếu admin đã tắt một
 * nhóm: chỉ những dòng chưa tồn tại mới được tạo.
 */
class TaxClassSeeder extends Seeder
{
    public function run(): void
    {
        $nhom = [
            [
                'code' => 'vat_10',
                'name' => 'VAT 10%',
                'rate' => '0.10000',
                'note' => 'Mức phổ thông cho hàng hoá, dịch vụ không thuộc nhóm ưu đãi.',
            ],
            [
                'code' => 'vat_8',
                'name' => 'VAT 8%',
                'rate' => '0.08000',
                'note' => 'Mức giảm áp dụng theo chính sách từng thời kỳ — kiểm tra hiệu lực trước khi dùng.',
            ],
            [
                'code' => 'vat_5',
                'name' => 'VAT 5%',
                'rate' => '0.05000',
                'note' => 'Nhóm ưu đãi; một số vật tư nông nghiệp nằm ở mức này.',
            ],
            [
                'code' => 'vat_0',
                'name' => 'VAT 0%',
                'rate' => '0.00000',
                /*
                 * PHẢI GHI RÕ 0% KHÁC "KHÔNG CHỊU THUẾ".
                 *
                 * Người cấu hình nhìn hai dòng cạnh nhau rất dễ chọn
                 * nhầm, và hậu quả chỉ lộ ra ở khâu kê khai.
                 */
                'note' => 'Hàng xuất khẩu đủ điều kiện. KHÁC "không chịu thuế": vẫn là hàng chịu thuế, mức 0%.',
            ],
            [
                'code' => 'vat_exempt',
                'name' => 'Không chịu VAT',
                // NULL, không phải 0 — xem TaxClass::isExempt().
                'rate' => null,
                'note' => 'Không thuộc đối tượng chịu VAT. Sản phẩm nông nghiệp chưa chế biến có trường hợp thuộc nhóm này — hỏi kế toán trước khi gán.',
            ],
        ];

        foreach ($nhom as $dong) {
            TaxClass::firstOrCreate(
                ['code' => $dong['code']],
                [
                    'name' => $dong['name'],
                    'rate' => $dong['rate'],
                    'note' => $dong['note'],
                    'is_active' => true,
                ],
            );
        }
    }
}
