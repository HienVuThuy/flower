<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gộp các trang phụ vào trang chính bằng hàng tab.
 * ============================================================
 * Thanh bên chỉ giữ trang chính. Trang phụ (Tồn đầu kỳ, Trả hàng nhà cung
 * cấp, Loại hoa, Chuyên mục cẩm nang, Trang nội dung) thành tab của trang
 * chính; Phân tích thu mua đã có tab ở trang Phân tích.
 */
class GopDieuHuongTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /** Phần HTML của thanh bên. */
    private function thanhBen(string $html): string
    {
        $dau = strpos($html, 'id="adminNav"');
        $this->assertNotFalse($dau);

        return substr($html, $dau, strpos($html, '</aside>', $dau) - $dau);
    }

    private function href(string $route): string
    {
        return 'href="' . route($route) . '"';
    }

    #[Test]
    public function thanh_ben_KHONG_con_cac_trang_phu(): void
    {
        $ben = $this->thanhBen($this->actingAs($this->admin())->get(route('admin.dashboard'))->getContent());

        foreach ([
            'admin.analytics.purchasing',
            'admin.opening-stock.create',
            'admin.supplier-returns.index',
            'admin.flower-kinds.index',
            'admin.blog-categories.index',
            'admin.page-contents.edit',
        ] as $route) {
            $this->assertStringNotContainsString($this->href($route), $ben, $route . ' không còn nằm trên thanh bên');
        }

        foreach (['admin.stock-receipts.index', 'admin.flower-lots.index', 'admin.blog.index', 'admin.settings.edit'] as $route) {
            $this->assertStringContainsString($this->href($route), $ben, $route . ' vẫn là trang chính');
        }
    }

    #[Test]
    public function moi_trang_trong_nhom_co_tab_toi_cac_trang_con_lai(): void
    {
        $this->actingAs($this->admin());

        $nhom = [
            ['admin.stock-receipts.index', 'admin.opening-stock.create', 'admin.supplier-returns.index'],
            ['admin.flower-lots.index', 'admin.flower-kinds.index'],
            ['admin.blog.index', 'admin.blog-categories.index'],
            ['admin.settings.edit', 'admin.page-contents.edit'],
        ];

        foreach ($nhom as $cacTrang) {
            foreach ($cacTrang as $trang) {
                $html = $this->get(route($trang))->assertOk()->getContent();

                $dau = strpos($html, 'aria-label="Các trang cùng nhóm"');
                $this->assertNotFalse($dau, $trang . ' phải có hàng tab');
                $tab = substr($html, $dau, strpos($html, '</nav>', $dau) - $dau);

                foreach ($cacTrang as $khac) {
                    $this->assertStringContainsString($this->href($khac), $tab, $trang . ' thiếu tab tới ' . $khac);
                }

                $this->assertMatchesRegularExpression(
                    '#' . preg_quote($this->href($trang), '#') . '\s+class="analytics-tabs__tab is-active"#',
                    $tab,
                    $trang . ': tab của chính trang đang mở phải sáng',
                );
            }
        }
    }

    #[Test]
    public function muc_cha_tren_thanh_ben_sang_khi_dang_o_trang_con(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            'admin.opening-stock.create' => 'admin.stock-receipts.index',
            'admin.supplier-returns.index' => 'admin.stock-receipts.index',
            'admin.flower-kinds.index' => 'admin.flower-lots.index',
            'admin.blog-categories.index' => 'admin.blog.index',
            'admin.page-contents.edit' => 'admin.settings.edit',
        ] as $con => $cha) {
            $ben = $this->thanhBen($this->get(route($con))->getContent());

            $this->assertMatchesRegularExpression(
                '#' . preg_quote($this->href($cha), '#') . '\s+class="admin-nav-link is-active"#',
                $ben,
                'Ở ' . $con . ' thì mục ' . $cha . ' phải sáng',
            );
        }
    }

    #[Test]
    public function bieu_mau_viet_bai_dan_toi_chuyen_muc(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.blog.create'))
            ->assertOk()
            ->assertSee('Chưa có chuyên mục nào.')
            ->assertSee($this->href('admin.blog-categories.index'), false);
    }

    #[Test]
    public function tab_thu_mua_van_con_trong_trang_phan_tich(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('href="' . route('admin.analytics.purchasing'), false);
    }
}
