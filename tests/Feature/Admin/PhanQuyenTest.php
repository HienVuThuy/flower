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

/**
 * Phân quyền: nhân viên vào được gì, và KHÔNG vào được gì.
 * ============================================================
 * TRƯỚC BẢN NÀY chỉ có admin/khách: ai vào được trang quản trị thì vào
 * được TẤT CẢ — giá vốn, lãi gộp, phân quyền, cấu hình cửa hàng. Một cửa
 * hàng thật có người chỉ xử lý đơn và nhập kho; đưa cho họ tài khoản
 * admin nghĩa là đưa luôn quyền đổi giá và xem lãi.
 *
 * ============================================================
 * BÀI QUAN TRỌNG NHẤT Ở ĐÂY LÀ BÀI ĐẦU TIÊN.
 *
 * Không phải "nhân viên bị chặn ở trang X" — mà là **mọi đường dẫn quản
 * trị đều có khai quyền**. Quên một dòng route là dòng đó mở cho mọi
 * nhân viên, và không có gì báo: trang vẫn chạy, vẫn đẹp, chỉ là ai cũng
 * vào được.
 *
 * ============================================================
 * ẨN Ở THANH ĐIỀU HƯỚNG KHÔNG PHẢI LÀ KHOÁ.
 *
 * Một mục bị `@can` ẩn đi mà đường dẫn vẫn mở là thứ NGUY HIỂM HƠN không
 * khoá gì: nhìn vào thì tin là đã khoá. Nên mỗi khu vực đều có hai phép
 * kiểm — không thấy mục, VÀ gõ thẳng địa chỉ vẫn bị chặn.
 */
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

    /* ================= KHÔNG ĐƯỜNG DẪN NÀO BỊ BỎ QUÊN ================= */

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
        /*
         * Gõ nhầm `quyen:tai-chinh2` mà middleware lặng lẽ cho qua thì cả
         * khu vực đó mất bảo vệ và không có gì báo. Hỏng lúc chạy thử còn
         * hơn mở cửa lúc chạy thật.
         */
        $mw = new \App\Http\Middleware\CoQuyen();

        $req = \Illuminate\Http\Request::create('/admin/thu');
        $req->setUserResolver(fn () => $this->nguoi(UserRole::Admin));

        $this->expectException(\InvalidArgumentException::class);

        $mw->handle($req, fn () => new \Illuminate\Http\Response(), 'khong-co-that');
    }

    /* ================= NHÂN VIÊN BỊ CHẶN Ở ĐÂU ================= */

    /** @return list<array{0: string, 1: string}> */
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

        /*
         * SO VỚI ĐỊA CHỈ DO CHÍNH route() DỰNG RA.
         *
         * Bản đầu của bài này viết tay `href="http://localhost/admin/users"`.
         * Máy chủ thật in ra `http://localhost:8000/...`, nên năm phép
         * khẳng định "không chứa" KHÔNG BAO GIỜ ĐỎ ĐƯỢC — chúng chỉ trang
         * trí. Phép đột biến (bỏ @can quanh mục Người dùng) đi qua sạch sẽ
         * và chỉ bị lộ nhờ chạy đột biến.
         *
         * Dựng từ route() thì đổi APP_URL bao nhiêu lần bài vẫn đo đúng
         * thứ nó nói là đang đo.
         */
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

        // Và những mục vào được thì vẫn còn — không ẩn nhầm cả thanh.
        foreach (['admin.orders.index', 'admin.inventory.index', 'admin.reviews.index'] as $ten) {
            $this->assertStringContainsString(
                'href="' . route($ten) . '"',
                $html,
                'Ẩn nhầm mục ' . $ten,
            );
        }
    }

    /* ================= NHÂN VIÊN LÀM ĐƯỢC GÌ ================= */

    #[Test]
    public function nhan_vien_van_xu_ly_duoc_don_kho_danh_gia_va_bao_cao(): void
    {
        /*
         * Phân quyền mà khoá luôn việc của người ta thì họ quay lại xin
         * tài khoản admin, và cả hệ thống quyền thành vô nghĩa.
         */
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
        // Khách vãng lai: bị đẩy về trang đăng nhập. Kiểm TRƯỚC khi
        // actingAs, vì actingAs giữ nguyên cho mọi yêu cầu sau đó.
        $this->get('/admin/dashboard')->assertRedirect();

        // Đã đăng nhập nhưng là khách mua hàng: 403, không phải chuyển hướng.
        $this->actingAs($this->nguoi(UserRole::Customer))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    /* ================= KHÔNG TỰ NÂNG QUYỀN ================= */

    #[Test]
    public function nhan_vien_khong_tu_nang_minh_len_chu_cua_hang(): void
    {
        /*
         * Đây là lỗ hổng đắt nhất nếu quên: ai sửa được phân quyền thì tự
         * cho mình mọi quyền còn lại, và mọi lớp khoá khác thành trang
         * trí.
         */
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

        // Không mở được trang sửa...
        $this->actingAs($nv)
            ->get('/admin/products/' . $sp->id . '/edit')
            ->assertForbidden();

        // ...và gửi thẳng biểu mẫu cũng không được.
        //
        // Phải kiểm cả hai: chặn trang sửa mà quên chặn đường LƯU là khoá
        // cái cửa còn để ngỏ cái cửa sổ — người gửi chỉ cần một dòng
        // lệnh, không cần mở trang nào.
        $this->actingAs($nv)
            ->put('/admin/products/' . $sp->id, ['name' => 'Đổi trộm', 'price' => '1000.00'])
            ->assertForbidden();

        $this->assertNotSame('Đổi trộm', $sp->fresh()->name);
    }

    /* ================= BẢNG QUYỀN ================= */

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

        // Vai trò gán được cho tài khoản quản trị: chủ cửa hàng và nhân viên.
        $this->assertSame([UserRole::Admin, UserRole::Staff], UserRole::nhanSu());
    }
}
