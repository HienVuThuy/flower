<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BulkOrderInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Số điện thoại và email ở trang yêu cầu báo giá bấm được.
 */
class LienHeBaoGiaTest extends TestCase
{
    use RefreshDatabase;

    private function trang(array $ghiDe = [])
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        $yc = BulkOrderInquiry::create(array_merge([
            'contact_name' => 'Chị Lan',
            'contact_phone' => '0912 345-678',
            'contact_email' => 'lan@khach.test',
            'occasion' => 'Tiệc cưới',
        ], $ghiDe));

        return $this->actingAs($u)->get(route('admin.bulk-inquiries.show', $yc));
    }

    #[Test]
    public function so_dien_thoai_thanh_lien_ket_goi_chi_con_chu_so(): void
    {
        // Khách gõ có khoảng trắng và gạch nối; số gọi được thì không có.
        $this->trang()
            ->assertOk()
            ->assertSee('href="tel:0912345678"', false);
    }

    #[Test]
    public function email_thanh_lien_ket_gui_thu_co_tieu_de_theo_dip(): void
    {
        $html = $this->trang()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="mailto:lan@khach\.test\?subject=[^"]+"#', $html);
        $this->assertStringContainsString(rawurlencode('Báo giá Tiệc cưới'), $html);
    }

    #[Test]
    public function khong_co_email_thi_khong_co_lien_ket_rong(): void
    {
        $this->trang(['contact_email' => null])
            ->assertOk()
            ->assertDontSee('href="mailto:', false);
    }
}
