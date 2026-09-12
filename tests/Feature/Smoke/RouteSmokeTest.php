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
 * GIÁ: khoảng một phút cho toàn bộ đường dẫn GET, kể cả những đường có
 * tham số. Đắt hơn bản chỉ quét đường dẫn trần rất nhiều — đổi lại nó
 * mới thật sự mở các trang sửa, nơi có nhiều mã Blade nhất và là nơi
 * lỗi 500 đã thật sự xảy ra.
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
    /**
     * Tham số đường dẫn -> lớp dữ liệu lấy bản ghi thật.
     *
     * ============================================================
     * VÌ SAO PHẢI QUÉT CẢ ĐƯỜNG DẪN CÓ THAM SỐ.
     *
     * Bản trước bỏ qua mọi đường dẫn chứa `{`, với lý do "việc đó thuộc
     * bài kiểm thử của từng tính năng". Nghe hợp lý, nhưng đó đúng là
     * chỗ đã để lọt một lỗi thật: trang **sửa khuyến mại** vỡ thành lỗi
     * 500 trên nhánh chính, và không bài nào mở nó ra.
     *
     * Trang danh sách thì bài nào cũng mở; trang sửa từng bản ghi thì
     * hay bị bỏ quên — mà đó lại là nơi có nhiều mã Blade nhất.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const THAM_SO = [
        'product' => \App\Models\Product::class,
        'category' => \App\Models\Category::class,
        'order' => \App\Models\Order::class,
        'promotion' => \App\Models\Promotion::class,
        'coupon' => \App\Models\Coupon::class,
        'user' => \App\Models\User::class,
        'review' => \App\Models\Review::class,
        'post' => \App\Models\BlogPost::class,
        'blogPost' => \App\Models\BlogPost::class,
        'journal' => \App\Models\Journal::class,
        'supplier' => \App\Models\Supplier::class,
    ];

    /**
     * Đường dẫn có tham số mà bài này CHƯA dựng được dữ liệu.
     *
     * Mỗi dòng ở đây là một khoảng trống đã biết, không phải một chỗ đã
     * kiểm. Chúng cần dữ liệu đi qua cả một luồng nghiệp vụ (đặt hàng,
     * nhập kho, kiểm kê, viết nhật ký) — dựng ở đây thì bài quét biến
     * thành bài nghiệp vụ.
     *
     * @var list<string>
     */
    private const CHUA_PHU = [
        'admin/bulk-inquiries/{bulkInquiry}',
        // Phiếu đổi hàng cần một đơn ĐÃ GIAO cùng dòng hàng — xem ExchangeTest.
        'admin/doi-hang/{exchange}',
        'admin/kiem-ke/{stockCount}',
        'admin/nhap-kho/{stockReceipt}',
        'admin/orders/{order}',
        'dat-lai-mat-khau/{token}',
        'dia-chi/{address}/sua',
        'dia-gioi/phuong-xa/{districtId}',
        'dia-gioi/quan-huyen/{provinceId}',
        'don-hang/{order}',
        'don-hang/{order}/thanh-toan-momo',
        'loai-cay/{taxon}',
        'nhat-ky/{journal}',
        'nhat-ky/{journal}/sua',
        'nhu-cau/{intent}',
        'storage/{path}',
        'thanh-toan/momo/{order}',
        'trang/{slug}',
        'xac-thuc-email/{id}/{hash}',
    ];

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

        $this->duLieuChoThamSo();

        $loi = [];
        $boQua = [];
        $trangQuanTriVaoDuoc = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::BO_QUA, true)) {
                continue;
            }

            $duong = $this->thayThamSo($uri);

            // Không có bản ghi thật cho tham số thì bỏ qua — chứ không
            // đoán một id rồi báo đỏ vì 404.
            if ($duong === null) {
                $boQua[] = $uri;

                continue;
            }

            $res = $this->actingAs($admin)->get($duong);

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

        /*
         * TỰ KIỂM LẦN HAI: danh sách bỏ qua phải ĐÚNG BẰNG danh sách đã
         * khai, không hơn.
         *
         * Không có khẳng định này thì chỉ cần seeder ngừng tạo một loại
         * dữ liệu là hàng loạt trang lặng lẽ rơi khỏi phạm vi quét, và
         * bài vẫn xanh — đúng cái đã xảy ra: bản đầu của phần quét đường
         * dẫn có tham số bỏ qua TOÀN BỘ trang sửa vì chưa có bản ghi nào,
         * nên nó không bắt được lỗi 500 mà nó sinh ra để bắt.
         *
         * Thêm một đường dẫn có tham số mới thì bài này đỏ, cho tới khi
         * người thêm hoặc dựng dữ liệu cho nó, hoặc ghi nó vào đây kèm lý
         * do. Đó là một quyết định có ý thức, không phải một khoảng lặng.
         */
        sort($boQua);

        $this->assertSame(
            self::CHUA_PHU,
            $boQua,
            "Danh sách đường dẫn không quét được đã đổi.\nHiện tại:\n" . implode("\n", $boQua),
        );
    }

    /**
     * Dựng mỗi loại một bản ghi để đường dẫn có tham số mở được.
     *
     * Dùng dữ liệu tối thiểu chứ không đi qua luồng nghiệp vụ: bài này
     * hỏi "trang có vỡ không", không hỏi "nghiệp vụ có đúng không".
     */
    private function duLieuChoThamSo(): void
    {
        \App\Models\Product::factory()
            ->for(\App\Models\Category::factory())
            ->create();

        \App\Models\Coupon::factory()->create();

        \App\Models\Supplier::create([
            'name' => 'Vựa quét thử',
            'kind' => 'vua',
        ]);

        \App\Models\Promotion::create([
            'name' => 'Chương trình quét thử',
            'slug' => 'chuong-trinh-quet-thu',
        ]);

        $danhMuc = \App\Models\BlogCategory::create([
            'name' => 'Chuyên mục quét thử',
            'slug' => 'chuyen-muc-quet-thu',
        ]);

        \App\Models\BlogPost::create([
            'title' => 'Bài viết quét thử',
            'slug' => 'bai-viet-quet-thu',
            'body' => '<p>Nội dung.</p>',
            'blog_category_id' => $danhMuc->id,
        ]);
    }

    /**
     * Đổi `{promotion}` trong đường dẫn thành khoá của một bản ghi có thật.
     *
     * Trả null khi chưa có bản ghi nào cho tham số đó — nghĩa là bài này
     * không mở trang ấy lần chạy này, chứ không phải trang ấy hỏng.
     */
    private function thayThamSo(string $uri): ?string
    {
        $duong = '/' . ltrim($uri, '/');

        foreach (self::THAM_SO as $ten => $model) {
            foreach (['{' . $ten . '}', '{' . $ten . '?}'] as $cho) {
                if (! str_contains($duong, $cho)) {
                    continue;
                }

                $ban = $model::query()->first();

                if ($ban === null) {
                    return null;
                }

                $duong = str_replace($cho, (string) $ban->getRouteKey(), $duong);
            }
        }

        // Còn sót tham số nào chưa khai thì không đoán bừa.
        return str_contains($duong, '{') ? null : $duong;
    }
}
