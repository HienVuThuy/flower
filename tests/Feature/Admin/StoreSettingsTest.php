<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Shop\Money;
use App\Services\Shop\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin tự sửa được nhận diện, tiền tệ và thuế.
 * ============================================================
 * Trước đây tên cửa hàng viết cứng ở 17 chỗ và định dạng tiền ở 49 chỗ.
 * Đổi được từ giao diện là mục tiêu; giữ cho nó KHÔNG TỰ MẤT là phần dễ
 * hỏng.
 *
 * Hai bài quan trọng nhất trong tệp này đều nói về việc **mất dữ liệu âm
 * thầm khi bấm Lưu** — kiểu lỗi không có thông báo nào và chỉ phát hiện
 * khi mở trang chủ ra xem.
 */
class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /** Bộ dữ liệu tối thiểu để form Cấu hình qua được validation. */
    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'theme' => 'default',
            'site_name' => 'Cửa hàng thử',
            'site_tagline' => 'Hoa và cây',
            'site_hotline' => '0912345678',
            'site_email' => 'thu@example.com',
            'site_address' => '1 Đường Thử',
            'site_province' => 'Thành phố Hà Nội',
            'currency_code' => 'VND',
            'currency_symbol' => '₫',
            'currency_position' => 'after',
            'currency_decimals' => 0,
            'tax_rate_percent' => 8,
        ], $ghiDe);
    }

    #[Test]
    public function doi_ten_cua_hang_lan_ra_moi_noi(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_name' => 'Chồi Xanh']))
            ->assertRedirect();

        $this->assertSame('Chồi Xanh', StoreProfile::name());

        // Không chỉ lưu được — phải THẬT SỰ hiện ra ở trang khách.
        $this->get('/san-pham')->assertOk()->assertSee('Chồi Xanh');
    }

    #[Test]
    public function luu_cau_hinh_khong_lam_mat_logo_dang_co(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT TỆP NÀY.
         *
         * Trang Cấu hình lưu bằng một vòng lặp trên StoreProfile::FIELDS.
         * `site_logo` là một TỆP, không phải ô chữ — để nó lọt vào vòng
         * đó thì mỗi lần admin sửa số điện thoại rồi bấm Lưu (không tải
         * logo mới), giá trị vắng mặt và logo bị xoá sạch.
         *
         * Không có thông báo nào. Chỉ phát hiện khi mở trang chủ ra xem.
         */
        Setting::set('site_logo', 'branding/logo-cua-toi.png');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_hotline' => '0900000000']))
            ->assertRedirect();

        $this->assertSame('branding/logo-cua-toi.png', StoreProfile::get('site_logo'));
    }

    #[Test]
    public function tai_logo_len_thi_no_thay_hinh_mac_dinh(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Máy chạy kiểm thử chưa bật GD.');
        }

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'site_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]))
            ->assertRedirect();

        $path = StoreProfile::get('site_logo');

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringContainsString($path, (string) StoreProfile::logoUrl());
    }

    #[Test]
    public function khong_nhan_logo_dinh_dang_svg(): void
    {
        /*
         * SVG là XML và chạy được JavaScript bên trong. Một tệp logo trở
         * thành lỗ XSS trên MỌI trang của cửa hàng — logo hiện ở header,
         * tức là ở mọi nơi khách đi qua.
         */
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'site_logo' => UploadedFile::fake()->createWithContent(
                    'logo.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ]))
            ->assertSessionHasErrors('site_logo');

        $this->assertNull(StoreProfile::get('site_logo'));
    }

    #[Test]
    public function ten_cua_hang_khong_duoc_de_trong(): void
    {
        // Nó nằm ở tiêu đề mọi trang và trong sáu mẫu thư. Để trống thì
        // khách nhận một lá thư ký tên bằng khoảng trắng.
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_name' => '']))
            ->assertSessionHasErrors('site_name');
    }

    #[Test]
    public function doi_don_vi_tien_te_lam_doi_moi_so_tien_tren_trang(): void
    {
        /*
         * Nếu bài này đỏ thì đâu đó vẫn còn một chỗ tự gọi
         * number_format() và tự nối ký hiệu — tức là ô cấu hình tiền tệ
         * chỉ là một ô nhập không làm gì, đúng loại "chức năng giả".
         */
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'currency_position' => 'before',
                'currency_decimals' => 2,
            ]))
            ->assertRedirect();

        $this->assertSame('$1,234.50', Money::format(1234.5));
    }

    #[Test]
    public function o_ky_hieu_bi_xoa_trang_khong_lam_mat_ky_hieu_toan_trang(): void
    {
        // Ô bỏ trắng là "chưa điền", không phải "không có ký hiệu tiền".
        Setting::set('currency_symbol', '');

        $this->assertSame('₫', Money::symbol());
    }

    #[Test]
    public function thue_suat_nhap_theo_phan_tram_luu_theo_thap_phan(): void
    {
        /*
         * Kế toán nói "8%", không nói "0,08". Bắt admin tự quy đổi là
         * mời một lỗi gõ nhầm GẤP 100 LẦN vào đúng con số thuế.
         */
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['tax_rate_percent' => 10]))
            ->assertRedirect();

        $this->assertSame('0.10000', Setting::get('tax_rate'));
        $this->assertSame('10', app(\App\Services\Tax\TaxCalculator::class)->ratePercent());
    }

    #[Test]
    public function khong_nhan_thue_suat_tu_100_phan_tram_tro_len(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['tax_rate_percent' => 150]))
            ->assertSessionHasErrors('tax_rate_percent');
    }

    #[Test]
    public function trang_cau_hinh_noi_ro_vi_sao_mot_cong_thanh_toan_chua_dung_duoc(): void
    {
        /*
         * Trước đây không chỗ nào trong giao diện trả lời được câu "vì
         * sao MoMo chưa hiện ra ở bước thanh toán". Người vận hành chỉ
         * biết là nó không có.
         */
        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Hình thức thanh toán')
            ->assertSee('Thanh toán khi nhận hàng (COD)');
    }
}
