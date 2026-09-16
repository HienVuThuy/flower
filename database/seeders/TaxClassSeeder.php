<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * Các NHÓM THUẾ SUẤT cửa hàng có thể chọn.
 * ⚠️ SEEDER NÀY KHÔNG PHÂN LOẠI SẢN PHẨM, VÀ ĐÓ LÀ CHỦ Ý.
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
                'note' => 'Hàng xuất khẩu đủ điều kiện. KHÁC "không chịu thuế": vẫn là hàng chịu thuế, mức 0%.',
            ],
            [
                'code' => 'vat_exempt',
                'name' => 'Không chịu VAT',
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
