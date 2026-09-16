<?php

namespace Tests\Feature\Checkout;

use App\Models\Address;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/** Địa chỉ trong sổ điền sẵn ĐỦ BA CẤP ở bước thanh toán. */
class DiaChiDaLuuTest extends CheckoutTestCase
{
    #[Test]
    public function dia_chi_da_luu_dien_san_ca_phuong_xa(): void
    {
        $user = User::factory()->create();

        Address::create([
            'user_id' => $user->id,
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'address_line' => '12 Đường Thử Nghiệm',
            'ward' => 'Phường Bến Nghé',
            'district' => 'Quận 1',
            'province' => 'Thành phố Hồ Chí Minh',
            'label' => 'home',
        ])->makeDefault();

        $this->actingAs($user);
        $this->addToCart($this->product());

        $html = $this->get(self::DETAILS)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#id="shipping_province"[^>]*value="Thành phố Hồ Chí Minh"#',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '#id="shipping_district"[^>]*data-cu="Quận 1"[^>]*value="Quận 1"#s',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '#id="shipping_ward"[^>]*data-cu="Phường Bến Nghé"[^>]*value="Phường Bến Nghé"#s',
            $html,
            'Ô phường/xã thiếu data-cu thì JavaScript không chọn lại được cấp này',
        );
    }
}
