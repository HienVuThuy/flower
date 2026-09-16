<?php

namespace Tests\Feature\Admin;

use App\Enums\Quyen;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Phân quyền: nhân viên vào được gì, và KHÔNG vào được gì. */
class PhanQuyenTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    #[Test]
    public function moi_duong_dan_quan_tri_deu_khai_quyen(): void
    {
        $thieu = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            $coQuyen = collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'quyen:'));

            if (! $coQuyen) {
                $thieu[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        sort($thieu);

        $this->assertSame(
            [],
            $thieu,
            "Đường dẫn quản trị chưa khai `quyen:` — mọi nhân viên vào được:\n" . implode("\n", $thieu),
        );
    }

    #[Test]
    public function ten_quyen_go_nham_thi_no_ngay_chu_khong_lang_le_cho_qua(): void
    {
        $mw = new \App\Http\Middleware\CoQuyen();

        $req = \Illuminate\Http\Request::create('/admin/thu');
        $req->setUserResolver(fn () => $this->nguoi(UserRole::Admin));

        $this->expectException(\InvalidArgumentException::class);

        $mw->handle($req, fn () => new \Illuminate\Http\Response(), 'khong-co-that');
    }

    public static function khuCam(): array
    {
        return [
            'lãi gộp và giá vốn' => ['/admin/phan-tich/loi-nhuan', 'Lợi nhuận'],
            'người dùng' => ['/admin/users', 'Người dùng'],
            'cấu hình cửa hàng' => ['/admin/settings', 'Cài đặt'],
            'nhật ký thao tác' => ['/admin/nhat-ky', 'Nhật ký'],
            'sản phẩm' => ['/admin/products', 'Sản phẩm'],
            'danh mục' => ['/admin/categories', 'Danh mục'],
            'khuyến mại' => ['/admin/promotions', 'Khuyến mại'],
            'đề xuất giá' => ['/admin/de-xuat-gia', 'Đề xuất giá'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('khuCam')]
    public function nhan_vien_go_thang_dia_chi_van_bi_chan(string $duong, string $ten): void
    {
        $this->actingAs($this->nguoi(UserRole::Staff))
            ->get($duong)
            ->assertForbidden();
    }

    #[Test]
    public function nhan_vien_khong_thay_muc_bi_cam_o_thanh_dieu_huong(): void
    {
        $html = $this->actingAs($this->nguoi(UserRole::Staff))
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        foreach ([
            'admin.users.index',
            'admin.settings.edit',
            'admin.promotions.index',
            'admin.products.index',
            'admin.pricing-advisor.index',
        ] as $ten) {
            $this->assertStringNotContainsString(
                'href="' . route($ten) . '"',
                $html,
                'Thanh điều hướng vẫn bày mục ' . $ten,
            );
        }

        foreach (['admin.orders.index', 'admin.inventory.index', 'admin.reviews.index'] as $ten) {
            $this->assertStringContainsString(
                'href="' . route($ten) . '"',
                $html,
                'Ẩn nhầm mục ' . $ten,
            );
        }
    }

    #[Test]
    public function nhan_vien_van_xu_ly_duoc_don_kho_danh_gia_va_bao_cao(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        foreach ([
            '/admin/dashboard',
            '/admin/orders',
            '/admin/ton-kho',
            '/admin/nhap-kho',
            '/admin/kiem-ke',
            '/admin/reviews',
            '/admin/goc-cay',
            '/admin/bulk-inquiries',
            '/admin/phan-tich',
            '/admin/phan-tich/doanh-thu',
        ] as $duong) {
            $this->actingAs($nv)->get($duong)->assertOk();
        }
    }

    #[Test]
    public function chu_cua_hang_van_vao_duoc_moi_khu(): void
    {
        $chu = $this->nguoi(UserRole::Admin);

        foreach (array_column(self::khuCam(), 0) as $duong) {
            $this->actingAs($chu)->get($duong)->assertOk();
        }
    }

    #[Test]
    public function khach_van_khong_vao_duoc_trang_quan_tri(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();

        $this->actingAs($this->nguoi(UserRole::Customer))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function nhan_vien_khong_tu_nang_minh_len_chu_cua_hang(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        $this->actingAs($nv)
            ->patch('/admin/users/' . $nv->id . '/vai-tro', ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Staff, $nv->fresh()->role);
    }

    #[Test]
    public function nhan_vien_khong_sua_duoc_gia_ban(): void
    {
        $sp = Product::factory()->for(Category::factory())->price('100000.00')->create();

        $nv = $this->nguoi(UserRole::Staff);

        $this->actingAs($nv)
            ->get('/admin/products/' . $sp->id . '/edit')
            ->assertForbidden();

        $this->actingAs($nv)
            ->put('/admin/products/' . $sp->id, ['name' => 'Đổi trộm', 'price' => '1000.00'])
            ->assertForbidden();

        $this->assertNotSame('Đổi trộm', $sp->fresh()->name);
    }

    #[Test]
    public function bang_quyen_noi_dung_su_that_ve_tung_vai_tro(): void
    {
        $this->assertSame(Quyen::cases(), UserRole::Admin->quyen());

        $nv = UserRole::Staff->quyen();

        foreach ([Quyen::DonHang, Quyen::Kho, Quyen::DanhGia, Quyen::BaoCao] as $co) {
            $this->assertContains($co, $nv);
        }

        foreach ([Quyen::SanPham, Quyen::KhuyenMai, Quyen::TaiChinh, Quyen::HeThong] as $khong) {
            $this->assertNotContains($khong, $nv, 'Nhân viên không được có quyền ' . $khong->value);
        }

        $this->assertSame([], UserRole::Customer->quyen());

        $this->assertSame([UserRole::Admin, UserRole::Staff], UserRole::nhanSu());
    }
}
