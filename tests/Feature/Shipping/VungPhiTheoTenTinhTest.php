<?php

namespace Tests\Feature\Shipping;

use App\Services\Shipping\ShippingRates;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Một tỉnh có HAI cách gọi tên, phí phải như nhau.
 * ============================================================
 * Bảng `config/shipping.php` ghi "Thành phố Hà Nội" (danh mục của cửa hàng),
 * còn GHN trả về "Hà Nội" — và tên GHN chính là tên được lưu vào đơn khi khách
 * chọn địa chỉ bằng ba ô chọn. Khớp tuyệt đối thì đơn nội thành rơi về vùng xa
 * nhất và phí dự phòng (lúc không hỏi được GHN) tính sai, âm thầm.
 */
class VungPhiTheoTenTinhTest extends TestCase
{
    #[Test]
    public function ten_ghn_va_ten_day_du_cho_cung_mot_vung(): void
    {
        $r = app(ShippingRates::class);

        $this->assertSame($r->zoneOf('Thành phố Hà Nội'), $r->zoneOf('Hà Nội'));
        $this->assertSame($r->zoneOf('Thành phố Hà Nội'), $r->zoneOf('  hà nội '), 'Khoảng trắng và chữ hoa không được đổi vùng');
        $this->assertSame((string) $r->feeFor('Thành phố Hà Nội'), (string) $r->feeFor('Hà Nội'));
    }

    #[Test]
    public function tinh_ngoai_bang_van_ve_vung_mac_dinh(): void
    {
        $r = app(ShippingRates::class);
        $macDinh = (string) config('shipping.default_zone', 'far');

        $this->assertSame($macDinh, $r->zoneOf('Một Tỉnh Không Có Thật'));
        $this->assertSame($macDinh, $r->zoneOf(null));
        $this->assertSame($macDinh, $r->zoneOf('   '));
    }
}
