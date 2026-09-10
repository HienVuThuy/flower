<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Analytics\ReportSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Xuất dữ liệu phân tích: chọn phần, chọn định dạng.
 * ============================================================
 * VẤN ĐỀ ĐÃ SỬA: nút "Xuất CSV" tải về một tệp CỐ ĐỊNH gồm năm phần.
 * Ai chỉ cần bảng bán chạy vẫn phải tải cả tệp rồi tự xoá bốn phần
 * thừa; ai cần bảng khách hàng thì không có cách nào lấy.
 *
 * Bất biến được canh ở đây:
 *
 *   1. Chọn phần nào thì tệp có ĐÚNG phần đó.
 *   2. Không chọn gì -> xuất tất cả, KHÔNG phải tệp rỗng.
 *   3. Mã phần lạ bị lọc, không đưa thẳng vào bộ dựng bảng.
 *   4. Định dạng lạ lùi về CSV, không nổ lỗi.
 *   5. Thứ tự phần trong tệp là thứ tự ĐÃ KHAI, không theo thứ tự tích.
 *   6. CSV có BOM — thiếu nó là Excel đọc tiếng Việt thành ký tự rác.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    /** Một đơn đã giao, để bảng nào cũng có ít nhất một dòng thật. */
    private function donDaGiao(): Order
    {
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('250000.00')
            ->stock(10)
            ->create(['name' => 'Cây kiểm thử báo cáo', 'weight' => 500]);

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();
        $order->forceFill(['status' => OrderStatus::Completed])->save();

        return $order;
    }

    private function taiVe(array $tuyChon = []): string
    {
        $res = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query(array_merge(['ky' => '30'], $tuyChon)));

        $res->assertOk();

        return $res->streamedContent();
    }

    /* ================= TRANG CHỌN ================= */

    #[Test]
    public function trang_chon_liet_ke_du_moi_phan_va_tich_san_tat_ca(): void
    {
        /*
         * TÍCH SẴN TẤT CẢ: người vào đây thường muốn cả bộ, và ai chỉ
         * cần một phần thì bỏ tích nhanh hơn là tích từng cái.
         */
        $html = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat')
            ->assertOk()
            ->getContent();

        foreach (ReportSections::danhSach() as $ma => $m) {
            $this->assertStringContainsString('value="' . $ma . '"', $html, 'Thiếu phần: ' . $ma);
            $this->assertStringContainsString($m['label'], $html);
        }

        // Số ô đã tích phải bằng số phần.
        $this->assertSame(
            count(ReportSections::danhSach()),
            substr_count($html, 'name="phan[]"'),
        );
    }

    #[Test]
    public function khach_va_nguoi_dung_thuong_khong_vao_duoc(): void
    {
        $this->get('/admin/phan-tich/xuat')->assertRedirect();
        $this->get('/admin/phan-tich/xuat/tai-ve')->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get('/admin/phan-tich/xuat')
            ->assertForbidden();
    }

    /* ================= CHỌN ĐÚNG PHẦN ================= */

    #[Test]
    public function chon_mot_phan_thi_tep_chi_co_phan_do(): void
    {
        $this->donDaGiao();

        $csv = $this->taiVe(['phan' => ['ban-chay']]);

        $this->assertStringContainsString('SẢN PHẨM BÁN CHẠY', $csv);
        $this->assertStringContainsString('Cây kiểm thử báo cáo', $csv);

        // Và KHÔNG có những phần không chọn.
        $this->assertStringNotContainsString('PHỄU CHUYỂN ĐỔI', $csv);
        $this->assertStringNotContainsString('TỪ KHOÁ KHÁCH TÌM', $csv);
    }

    #[Test]
    public function khong_chon_gi_thi_xuat_TAT_CA_chu_khong_ra_tep_rong(): void
    {
        /*
         * Trả về một tệp rỗng là đúng chữ nhưng vô dụng: người dùng bấm
         * "Tải về", nhận một tệp không có gì, và không biết mình đã quên
         * bước nào.
         */
        $csv = $this->taiVe();

        foreach (ReportSections::danhSach() as $m) {
            $this->assertStringContainsString(mb_strtoupper($m['label']), $csv);
        }
    }

    #[Test]
    public function ma_phan_la_bi_loc_bo(): void
    {
        /*
         * `phan[]` đến từ trình duyệt, ai cũng sửa được. Không lọc thì
         * một chuỗi bịa đi thẳng vào bộ dựng bảng.
         */
        $csv = $this->taiVe(['phan' => ['ban-chay', 'khong-co-that']]);

        $this->assertStringContainsString('SẢN PHẨM BÁN CHẠY', $csv);
        $this->assertStringNotContainsString('khong-co-that', $csv);
    }

    #[Test]
    public function thu_tu_phan_theo_danh_sach_da_khai_chu_khong_theo_thu_tu_tich(): void
    {
        /*
         * Tệp xuất ra phải luôn cùng một bố cục để so hai kỳ với nhau
         * được. Theo thứ tự người dùng tích thì mỗi lần xuất một khác.
         */
        $csv = $this->taiVe(['phan' => ['ban-chay', 'tong-quan']]);

        $viTriTongQuan = strpos($csv, 'TỔNG QUAN ĐƠN HÀNG');
        $viTriBanChay = strpos($csv, 'SẢN PHẨM BÁN CHẠY');

        $this->assertNotFalse($viTriTongQuan);
        $this->assertNotFalse($viTriBanChay);
        $this->assertLessThan($viTriBanChay, $viTriTongQuan, 'Thứ tự phần trong tệp không theo danh sách đã khai.');
    }

    #[Test]
    public function gui_len_TOAN_ma_la_thi_xuat_tat_ca_chu_khong_ra_tep_rong(): void
    {
        /*
         * ĐÂY LÀ CHỖ DUY NHẤT chốt lọc ở controller thật sự khác biệt.
         *
         * Bài "mã phần lạ bị lọc bỏ" ở trên vẫn xanh kể cả khi bỏ hẳn
         * chốt đó — đã kiểm bằng cách bỏ. Vì `nhieuBang()` cũng duyệt
         * theo danh sách hợp lệ nên mã lạ rơi ra ở lớp dưới.
         *
         * Nhưng khi gửi lên TOÀN mã lạ thì hai lớp cho hai kết quả khác
         * hẳn: không có chốt ở controller thì `$chon` vẫn "có phần tử",
         * nhánh "không chọn gì thì xuất tất cả" không chạy, và người
         * dùng nhận một tệp RỖNG mà không hiểu vì sao.
         */
        $csv = $this->taiVe(['phan' => ['bia-dat-1', 'bia-dat-2']]);

        foreach (ReportSections::danhSach() as $m) {
            $this->assertStringContainsString(mb_strtoupper($m['label']), $csv);
        }
    }

    /* ================= BA ĐỊNH DẠNG ================= */

    #[Test]
    public function CSV_co_BOM_de_Excel_doc_dung_tieng_Viet(): void
    {
        /*
         * Không có ba byte này, Excel trên Windows đọc CSV theo bảng mã
         * hệ thống và mọi tên sản phẩm tiếng Việt thành ký tự rác.
         */
        $csv = $this->taiVe(['dinh_dang' => 'csv']);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    #[Test]
    public function JSON_dung_khoa_co_ten_chu_khong_phai_mang_vi_tri(): void
    {
        /*
         * `["Kim tiền", 12]` bắt người nhận phải đọc thứ tự cột ở chỗ
         * khác rồi tự đếm — và hỏng ngay khi thứ tự cột đổi.
         */
        $this->donDaGiao();

        $json = json_decode($this->taiVe(['dinh_dang' => 'json', 'phan' => ['ban-chay']]), true);

        $this->assertSame('30 ngày qua', $json['ky']);

        $phan = $json['phan'][0];
        $this->assertSame(['Sản phẩm', 'Số lượng', 'Doanh thu'], $phan['cot']);

        $dong = $phan['dong'][0];
        $this->assertArrayHasKey('Sản phẩm', $dong);
        $this->assertSame('Cây kiểm thử báo cáo', $dong['Sản phẩm']);
        $this->assertSame(2, $dong['Số lượng']);
    }

    #[Test]
    public function HTML_tu_dung_mot_minh_khong_can_tep_CSS_ngoai(): void
    {
        /*
         * Người nhận mở tệp trên máy họ; ở đó không có máy chủ nào để
         * tải CSS về. Kiểu dáng phải nhúng thẳng.
         */
        $html = $this->taiVe(['dinh_dang' => 'html', 'phan' => ['tong-quan']]);

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringContainsString('Tổng quan đơn hàng', $html);
    }

    #[Test]
    public function dinh_dang_la_lui_ve_CSV_chu_khong_no_loi(): void
    {
        $noiDung = $this->taiVe(['dinh_dang' => 'khong-co-that']);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $noiDung);
    }

    /* ================= SỐ LIỆU PHẢI THẬT ================= */

    #[Test]
    public function con_so_trong_tep_khop_voi_don_hang_that(): void
    {
        /*
         * Bài quan trọng nhất: tệp báo cáo mà sai số thì nó tệ hơn không
         * có báo cáo — người ta ra quyết định dựa vào nó.
         */
        $order = $this->donDaGiao();

        $json = json_decode($this->taiVe(['dinh_dang' => 'json', 'phan' => ['tong-quan']]), true);

        $dong = collect($json['phan'][0]['dong'])->keyBy('Chỉ số');

        $this->assertSame(1, $dong['Tổng đơn']['Giá trị']);
        $this->assertSame(1, $dong['Đã giao']['Giá trị']);
        $this->assertEqualsWithDelta((float) $order->grand_total, $dong['Doanh thu (đơn đã giao)']['Giá trị'], 0.01);
    }

    #[Test]
    public function chua_co_don_nao_thi_ghi_ro_chu_khong_ghi_so_0(): void
    {
        /*
         * "Giá trị đơn trung bình = 0" và "chưa có đơn đã giao" là hai
         * câu khác hẳn nhau. Mẫu số bằng 0 thì không có trung bình nào
         * để nói, và bịa ra số 0 là bịa một kết luận.
         */
        $json = json_decode($this->taiVe(['dinh_dang' => 'json', 'phan' => ['tong-quan']]), true);

        $dong = collect($json['phan'][0]['dong'])->keyBy('Chỉ số');

        $this->assertSame('chưa có đơn đã giao', $dong['Giá trị đơn trung bình']['Giá trị']);
    }
}
