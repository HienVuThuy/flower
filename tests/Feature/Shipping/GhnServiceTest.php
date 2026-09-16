<?php

namespace Tests\Feature\Shipping;

use App\Services\Shipping\GHNService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Lớp nối tới Giao Hàng Nhanh. */
class GhnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
        config()->set('services.ghn.from_district_id', 1482);

        Cache::flush();
    }

    private function ghn(): GHNService
    {
        return app(GHNService::class);
    }

    #[Test]
    public function loai_bo_ban_ghi_rac_cua_moi_truong_thu(): void
    {
        config()->set('services.ghn.skip_province_ids', [2002, 298]);

        Http::fake([
            '*/master-data/province' => Http::response([
                'code' => 200,
                'data' => [
                    ['ProvinceID' => 2002, 'ProvinceName' => 'Hà Nội 02', 'Status' => 1],
                    ['ProvinceID' => 201, 'ProvinceName' => 'Hà Nội', 'Status' => 1],
                    ['ProvinceID' => 298, 'ProvinceName' => 'Test - Alert - Tỉnh - 001', 'Status' => 1],
                ],
            ]),
        ]);

        $ten = collect($this->ghn()->getProvinces()['data'])->pluck('ProvinceName')->all();

        $this->assertSame(['Hà Nội'], $ten);
    }

    #[Test]
    public function KHONG_goi_them_gi_chi_de_loc_danh_muc(): void
    {
        config()->set('services.ghn.skip_province_ids', [2002]);

        Http::fake(['*' => Http::response(['code' => 200, 'data' => []])]);

        $this->ghn()->getProvinces();

        Http::assertSentCount(1);
    }

    #[Test]
    public function goi_thanh_cong_thi_dem_lai_khong_goi_lai(): void
    {
        Http::fake([
            '*/master-data/province' => Http::response(['code' => 200, 'data' => []]),
            '*/master-data/district' => Http::response(['code' => 200, 'data' => []]),
        ]);

        $this->ghn()->getProvinces();
        $this->ghn()->getProvinces();
        $this->ghn()->getProvinces();

        Http::assertSentCount(1);
    }

    #[Test]
    public function dang_hong_thi_tam_nghi_khong_goi_lai_ngay(): void
    {
        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $this->ghn()->getProvinces();
        $soLanDau = count(Http::recorded());

        $this->ghn()->getProvinces();

        $this->assertSame(
            $soLanDau,
            count(Http::recorded()),
            'Vừa hỏng thì không được gọi lại ngay.',
        );
    }

    #[Test]
    public function KHONG_dem_cau_tra_loi_loi_lau_dai(): void
    {
        $ghnDaSong = false;

        Http::fake(function () use (&$ghnDaSong) {
            if (! $ghnDaSong) {
                return Http::response(['code' => 500], 500);
            }

            return Http::response([
                'code' => 200,
                'data' => [
                    ['ProvinceID' => 201, 'ProvinceName' => 'Hà Nội', 'DistrictID' => 1482],
                ],
            ]);
        });

        $this->assertNotSame(200, $this->ghn()->getProvinces()['code'], 'Lúc đầu GHN hỏng.');

        $ghnDaSong = true;
        $this->travel(61)->seconds();

        $this->assertSame(
            200,
            $this->ghn()->getProvinces()['code'],
            'GHN hồi phục thì phải dùng được ngay, không đợi hết 24 giờ đệm.',
        );
    }

    #[Test]
    public function chua_khai_token_thi_khong_goi_ra_ngoai(): void
    {
        config()->set('services.ghn.token', '');
        Http::fake();

        $this->assertSame(-1, $this->ghn()->getProvinces()['code']);

        Http::assertNothingSent();
    }

    #[Test]
    public function thong_so_kien_hang_dung_chung_cho_bao_gia_va_van_don(): void
    {
        $goi = $this->ghn()->packageParameters(750);

        $this->assertSame(750, $goi['weight']);
        $this->assertSame((int) config('services.ghn.box.length'), $goi['length']);
        $this->assertSame((int) config('services.ghn.service_type_id'), $goi['service_type_id']);
    }

    #[Test]
    public function khoi_luong_khong_bao_gio_bang_khong(): void
    {
        $this->assertGreaterThan(0, $this->ghn()->packageParameters(0)['weight']);
    }
}
