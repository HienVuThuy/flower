<?php

namespace Tests\Feature\Smoke;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lưới an toàn: không trang nào được vỡ thành lỗi 500.
 * ============================================================
 * Bài này KHÔNG kiểm nội dung — đã có bài riêng cho từng tính năng. Nó
 * chỉ hỏi đúng một câu, trên MỌI đường dẫn GET không tham số của cả
 * trang khách lẫn trang quản trị: *mở lên có vỡ không?*
 *
 * Đó là câu hỏi mà những bài kiểm thử chi tiết hay bỏ sót, vì mỗi bài
 * chỉ mở đúng vài trang thuộc phần mình. Sửa một biến trong bố cục dùng
 * chung, đổi tên một cột, xoá một biến view — hỏng ở một trang không ai
 * nghĩ tới, và không có bài nào mở trang đó ra.
 *
 * RẺ: khoảng hai giây cho gần 50 đường dẫn. Đủ rẻ để chạy cùng mọi lần.
 *
 * ============================================================
 * BÀI NÀY TỰ KIỂM CHÍNH NÓ.
 *
 * Một bài quét mà tất cả trang quản trị đều trả 403 vẫn XANH — vì 403
 * không phải 5xx. Nó sẽ trông như đang canh gác cả trang quản trị trong
 * khi thật ra chưa từng mở nổi một trang nào ở đó.
 *
 * Vì vậy có thêm phép khẳng định đếm số trang quản trị VÀO ĐƯỢC. Nếu
 * phân quyền đổi và bài này không còn vào được nữa, nó báo đỏ ngay thay
 * vì lặng lẽ ngừng bảo vệ.
 *
 * (Cùng bài học với `JournalPrivacyTest`: một bài chỉ đi qua nhánh dễ
 * nhất là một bài không bảo vệ ai.)
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Không quét: những đường dẫn mà "mở bằng GET" không có nghĩa.
     *
     * @var list<string>
     */
    private const BO_QUA = [
        'up',        // đầu dò tình trạng của Laravel, không phải trang
        'logout',
        'dang-xuat',
    ];

    #[Test]
    public function khong_trang_nao_tra_ve_loi_500(): void
    {
        // Dữ liệu mẫu thật: trang trống và trang có hàng đi qua những
        // nhánh khác nhau, và nhánh có dữ liệu mới là nhánh hay vỡ.
        $this->seed();

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $loi = [];
        $trangQuanTriVaoDuoc = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            // Đường dẫn có tham số cần id thật của đúng loại dữ liệu —
            // việc đó thuộc về bài kiểm thử của từng tính năng.
            if (str_contains($uri, '{') || in_array($uri, self::BO_QUA, true)) {
                continue;
            }

            $res = $this->actingAs($admin)->get('/' . ltrim($uri, '/'));

            if ($res->getStatusCode() >= 500) {
                $loi[] = '/' . $uri . ' => ' . $res->getStatusCode();
            }

            if (str_starts_with($uri, 'admin') && $res->getStatusCode() === 200) {
                $trangQuanTriVaoDuoc++;
            }
        }

        $this->assertGreaterThan(
            5,
            $trangQuanTriVaoDuoc,
            'Bài quét không mở được trang quản trị nào — nó đang không canh gác phần đó.',
        );

        $this->assertSame([], $loi, "Có trang trả về lỗi máy chủ:\n" . implode("\n", $loi));
    }
}
