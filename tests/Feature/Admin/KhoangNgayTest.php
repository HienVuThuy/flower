<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Khoảng ngày tự chọn cho trang Tổng quan và Phân tích.
 * ============================================================
 * Trước bản này chỉ có ba mốc dựng sẵn: 7 ngày, 30 ngày, toàn bộ. Muốn
 * xem "tháng 8" hay "tuần lễ khuyến mại 12–18/09" thì không có cách nào.
 *
 * ============================================================
 * BẤT BIẾN ĐƯỢC CANH Ở ĐÂY:
 *
 *   1. Ngày là ngày theo LỊCH VIỆT NAM, không phải lịch UTC.
 *   2. Ngày cuối được tính TRỌN NGÀY — mốc kết thúc là mốc mở.
 *   3. Kỳ trước của một khoảng dài đúng bằng khoảng đó, nằm ngay trước.
 *   4. Mọi liên kết (tab, nút xuất) mang khoảng đi theo.
 *   5. Tham số lạ lùi về mặc định, không nổ.
 */
class KhoangNgayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /**
     * Một đơn ĐÃ GIAO, đặt đúng mốc giờ Việt Nam cho trước.
     *
     * Ghi thẳng vào bảng thay vì đi qua luồng đặt hàng: bài này đo cách
     * CẮT KHOẢNG, không đo nghiệp vụ đặt hàng, và luồng thật không cho
     * chọn giờ đặt.
     */
    private function don(string $gioVN, string $tien = '100000.00'): Order
    {
        $sp = Product::factory()
            ->for(Category::factory())
            ->price($tien)
            ->create();

        $moc = Carbon::parse($gioVN, 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-' . $moc->format('ymdHis') . '-' . $sp->id,
            'recipient_name' => 'Khách thử khoảng',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'grand_total' => $tien,
            'status' => OrderStatus::Completed,
            'created_at' => $moc,
            'updated_at' => $moc,
        ])->save();

        return $don;
    }

    private function soDon(array $thamSo): int
    {
        $a = app(AnalyticsService::class);
        ChonKy::tuThamSo($thamSo['ky'] ?? null, $thamSo['tu'] ?? null, $thamSo['den'] ?? null)
            ->apDung($a);

        return (int) $a->orderStats()['total'];
    }

    /* ================= CẮT KHOẢNG ================= */

    #[Test]
    public function chi_dem_don_nam_trong_khoang_da_chon(): void
    {
        $this->don('2026-08-31 10:00');   // trước khoảng
        $this->don('2026-09-01 10:00');   // trong
        $this->don('2026-09-05 10:00');   // trong
        $this->don('2026-09-08 10:00');   // sau khoảng

        $this->assertSame(2, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
    }

    #[Test]
    public function ngay_cuoi_duoc_tinh_TRON_NGAY(): void
    {
        /*
         * MỐC KẾT THÚC LÀ MỐC MỞ — nửa đêm của ngày kế tiếp, không phải
         * 23:59:59 của ngày cuối.
         *
         * Mọi nơi áp khoảng đều so bằng `<` ở đầu này. Để 23:59:59 thì
         * đơn đặt trong giây cuối cùng của ngày cuối biến mất — lặng lẽ,
         * và đúng vào ngày mà người xem quan tâm nhất.
         */
        $this->don('2026-09-07 23:59:59');

        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
    }

    #[Test]
    public function ngay_la_ngay_theo_lich_Viet_Nam(): void
    {
        /*
         * Đơn đặt lúc 00:30 sáng ngày 08/09 giờ Hà Nội được lưu là 17:30
         * ngày 07/09 giờ UTC. Cắt khoảng theo lịch UTC thì nó rơi vào
         * ngày 07 — một đơn của hôm nay bị đếm sang hôm qua.
         */
        $this->don('2026-09-08 00:30');

        $this->assertSame(0, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07']));
        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-08', 'den' => '2026-09-08']));
    }

    /* ================= DỮ LIỆU GỬI LÊN ================= */

    #[Test]
    public function chon_nguoc_thi_doi_cho_chu_khong_bat_lam_lai(): void
    {
        $this->don('2026-09-03 10:00');

        $this->assertSame(1, $this->soDon(['ky' => 'tuy-chon', 'tu' => '2026-09-07', 'den' => '2026-09-01']));
    }

    #[Test]
    public function tham_so_la_thi_lui_ve_moc_dung_san(): void
    {
        /*
         * `?tu=<script>` là thứ bất kỳ ai cũng gõ được vào thanh địa chỉ.
         * Lùi về mặc định, không nổ, và KHÔNG hiểu thành một khoảng nào.
         */
        foreach ([
            ['tu' => '<script>', 'den' => '2026-09-07'],
            ['tu' => '2026-09-01', 'den' => 'tomorrow'],
            ['tu' => '2026-09-01', 'den' => null],
            ['tu' => '2026-02-31', 'den' => '2026-03-05'],   // ngày không có thật
            ['tu' => ['2026-09-01'], 'den' => '2026-09-07'], // mảng
        ] as $xau) {
            $ky = ChonKy::tuThamSo('tuy-chon', $xau['tu'], $xau['den']);

            $this->assertFalse($ky->laTuyChon(), 'Nhận nhầm tham số lạ: ' . json_encode($xau));
            $this->assertSame('30', $ky->ma);
        }
    }

    #[Test]
    public function ngay_31_02_khong_bi_don_thanh_03_03(): void
    {
        /*
         * `createFromFormat('Y-m-d', '2026-02-31')` KHÔNG báo lỗi — nó
         * dồn sang 03/03. Người gõ nhầm sẽ nhận số liệu của một khoảng
         * khác hẳn khoảng họ tưởng, và tiêu đề trang thì ghi 03/03 nên
         * trông như chính họ đã chọn thế.
         */
        $this->assertFalse(ChonKy::tuThamSo('tuy-chon', '2026-02-31', '2026-03-05')->laTuyChon());
    }

    /* ================= SO VỚI KỲ TRƯỚC ================= */

    #[Test]
    public function ky_truoc_cua_mot_khoang_dai_dung_bang_khoang_do(): void
    {
        /*
         * Chọn 08/09–14/09 (7 ngày) thì kỳ trước phải là 01/09–07/09.
         *
         * Công thức cũ lấy độ dài từ mốc bắt đầu tới BÂY GIỜ — đúng với
         * "7 ngày qua" nhưng sai hẳn với một khoảng đã kết thúc, và kỳ
         * càng cũ thì sai càng nhiều.
         */
        $this->don('2026-09-03 10:00');   // nằm trong kỳ trước
        $this->don('2026-09-10 10:00');   // nằm trong kỳ này
        $this->don('2026-08-20 10:00');   // xa hơn cả kỳ trước

        $a = app(AnalyticsService::class);
        $ky = ChonKy::tuThamSo('tuy-chon', '2026-09-08', '2026-09-14');
        $ky->apDung($a);

        $this->assertSame(1, (int) $a->orderStats()['total']);

        $this->assertTrue($a->forPreviousPeriod($ky->ma));
        $this->assertSame(1, (int) $a->orderStats()['total'], 'Kỳ trước phải đúng 7 ngày ngay trước đó');
    }

    /* ================= ĐI QUA GIAO DIỆN ================= */

    #[Test]
    public function moi_lien_ket_tren_trang_mang_theo_khoang(): void
    {
        /*
         * Chỉ cần một liên kết quên `tu`/`den` là bấm sang tab khác lặng
         * lẽ nhảy về "30 ngày qua", trong khi người xem tin rằng mình
         * vẫn đang ở khoảng cũ và đem hai con số của hai khoảng khác
         * nhau ra so với nhau.
         */
        $html = $this->actingAs($this->admin())
            ->get('/admin/phan-tich?ky=tuy-chon&tu=2026-09-01&den=2026-09-07')
            ->assertOk()
            ->getContent();

        // Các tab
        $this->assertStringContainsString('tu=2026-09-01', $html);
        $this->assertStringContainsString('den=2026-09-07', $html);

        // Ô ngày điền sẵn đúng cái người dùng đã chọn
        $this->assertStringContainsString('value="2026-09-01"', $html);
        $this->assertStringContainsString('value="2026-09-07"', $html);

        // Đếm số liên kết mang khoảng: 5 tab + nút xuất, không ít hơn.
        $this->assertGreaterThanOrEqual(
            6,
            substr_count($html, 'tu=2026-09-01'),
            'Có liên kết trên trang không mang theo khoảng ngày',
        );
    }

    #[Test]
    public function tep_xuat_ra_ghi_dung_khoang_chu_khong_ghi_30_ngay(): void
    {
        /*
         * Tệp rời khỏi màn hình rồi thì cái nhãn kỳ là thứ duy nhất cho
         * biết nó nói về khoảng nào. Ghi sai nhãn còn tệ hơn không ghi.
         */
        $csv = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query([
                'ky' => 'tuy-chon',
                'tu' => '2026-09-01',
                'den' => '2026-09-07',
                'dinh_dang' => 'csv',
                'phan' => ['tong-quan'],
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('01/09/2026 – 07/09/2026', $csv);
        $this->assertStringNotContainsString('30 ngày qua', $csv);
    }

    #[Test]
    public function trang_Tong_quan_cung_nhan_khoang(): void
    {
        $this->don('2026-09-03 10:00');

        $this->actingAs($this->admin())
            ->get('/admin/dashboard?ky=tuy-chon&tu=2026-09-01&den=2026-09-07')
            ->assertOk()
            ->assertSee('2026-09-01', false);
    }

    #[Test]
    public function nhan_ky_doc_ra_dung_ngay_nguoi_dung_da_chon(): void
    {
        $ky = ChonKy::tuThamSo('tuy-chon', '2026-09-01', '2026-09-07');

        // Nhãn hiện NGÀY CUỐI, không phải mốc mở nằm sau nó.
        $this->assertSame('01/09/2026 – 07/09/2026', $ky->nhan());
        $this->assertSame('2026-09-07', $ky->oDen());

        $this->assertSame(
            ['ky' => 'tuy-chon', 'tu' => '2026-09-01', 'den' => '2026-09-07'],
            $ky->thamSo(),
        );
    }
}
