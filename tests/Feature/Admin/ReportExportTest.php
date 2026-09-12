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

    /* ================= EXCEL VÀ PDF ================= */

    /**
     * Tải về mà không cần biết định dạng trả về kiểu phản hồi nào.
     *
     * CSV/JSON/HTML là luồng, XLSX là tệp trên đĩa, PDF là chuỗi dựng
     * sẵn — ba loại phản hồi khác nhau. Bài kiểm thử không nên phải biết
     * điều đó mới lấy được nội dung.
     */
    private function taiVeNhiPhan(array $tuyChon = []): string
    {
        $res = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query(array_merge(['ky' => '30'], $tuyChon)));

        $res->assertOk();

        $base = $res->baseResponse;

        if ($base instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) file_get_contents($base->getFile()->getPathname());
        }

        if ($base instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $res->streamedContent();
        }

        return (string) $res->getContent();
    }

    /** Mở tệp .xlsx trong bộ nhớ và đọc ra một tệp XML bên trong. */
    private function trongXlsx(string $noiDung, string $duong): string
    {
        $tam = tempnam(sys_get_temp_dir(), 'kt-xlsx-');
        file_put_contents($tam, $noiDung);

        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($tam) === true, 'Tệp .xlsx không mở được như tệp nén');

        $xml = $zip->getFromName($duong);
        $zip->close();
        @unlink($tam);

        $this->assertIsString($xml, 'Trong tệp .xlsx không có ' . $duong);

        return $xml;
    }

    /** @return list<string> */
    private function tenCacTrangTinh(string $noiDung): array
    {
        preg_match_all('/<sheet name="([^"]+)"/', $this->trongXlsx($noiDung, 'xl/workbook.xml'), $m);

        return array_map(
            fn (string $t) => html_entity_decode($t, ENT_QUOTES, 'UTF-8'),
            $m[1],
        );
    }

    #[Test]
    public function XLSX_tach_moi_phan_ra_mot_trang_tinh_rieng(): void
    {
        /*
         * ĐÂY LÀ LÝ DO XLSX TỒN TẠI BÊN CẠNH CSV.
         *
         * CSV dồn mọi phần vào một bảng cách nhau bằng dòng trống — mở
         * bằng Excel là một trang dài không lọc, không xoay bảng được.
         * Nếu tệp .xlsx cũng chỉ có một trang tính thì nó không hơn gì
         * CSV, và cả định dạng này là thừa.
         */
        $ten = $this->tenCacTrangTinh($this->taiVeNhiPhan([
            'dinh_dang' => 'xlsx',
            'phan' => ['tong-quan', 'ban-chay', 'ton-kho'],
        ]));

        // Một trang Thông tin + ba phần đã chọn.
        $this->assertCount(4, $ten);
        $this->assertSame('Thông tin', $ten[0]);
        $this->assertStringContainsString('Tổng quan', $ten[1]);
        $this->assertStringContainsString('Sản phẩm bán chạy', $ten[2]);
        $this->assertStringContainsString('Tồn kho', $ten[3]);
    }

    #[Test]
    public function ten_trang_tinh_luon_hop_le_voi_Excel(): void
    {
        /*
         * Excel KHÔNG MỞ ĐƯỢC tệp có tên trang dài quá 31 ký tự, chứa
         * `: \ / ? * [ ]`, hay trùng nhau — hỏng cả tệp chứ không phải
         * hiện xấu. Nhãn phần ở đây là câu tiếng Việt dài, có cả dấu hai
         * chấm ("Đánh giá: tổng quan và phân bố sao"), nên cả ba đều có
         * thể xảy ra nếu lấy nhãn làm tên trang.
         */
        $ten = $this->tenCacTrangTinh($this->taiVeNhiPhan(['dinh_dang' => 'xlsx']));

        $this->assertNotEmpty($ten);

        foreach ($ten as $t) {
            $this->assertLessThanOrEqual(31, mb_strlen($t), 'Tên trang quá dài: ' . $t);
            $this->assertDoesNotMatchRegularExpression('~[:\\\\/?*\[\]]~u', $t, 'Tên trang có ký tự Excel cấm: ' . $t);
        }

        $this->assertSame(count($ten), count(array_unique($ten)), 'Có hai trang tính trùng tên');
    }

    #[Test]
    public function hai_phan_giong_nhau_o_31_ky_tu_dau_khong_lam_hong_tep(): void
    {
        /*
         * BÀI NÀY SINH RA TỪ MỘT PHÉP ĐỘT BIẾN SỐNG SÓT.
         *
         * Bỏ hẳn đoạn chống trùng tên trang mà mọi bài vẫn xanh — vì
         * trong 23 phần hiện có, không hai nhãn nào giống nhau ở 31 ký
         * tự đầu. Nghĩa là đoạn đó chưa từng được đo, và sẽ hỏng vào
         * đúng ngày có người thêm phần thứ 24 tên na ná phần cũ.
         *
         * Hai trang tính trùng tên thì Excel KHÔNG MỞ ĐƯỢC tệp — không
         * phải hiện xấu, mà là báo hỏng tệp.
         */
        $tam = tempnam(sys_get_temp_dir(), 'kt-trung-') . '.xlsx';

        $motPhan = fn (string $nhan) => [
            'label' => $nhan,
            'columns' => ['Cột'],
            'rows' => [['giá trị']],
        ];

        app(\App\Services\Analytics\Export\XlsxWriter::class)->ghi(
            collect([
                $motPhan('Doanh thu theo tỉnh thành phố trực thuộc trung ương, quý 1'),
                $motPhan('Doanh thu theo tỉnh thành phố trực thuộc trung ương, quý 2'),
                $motPhan('Doanh thu theo tỉnh thành phố trực thuộc trung ương, quý 3'),
            ]),
            'kỳ thử',
            $tam,
        );

        $ten = $this->tenCacTrangTinh((string) file_get_contents($tam));
        @unlink($tam);

        // Thông tin + ba phần, và không tên nào trùng tên nào.
        $this->assertCount(4, $ten);
        $this->assertSame(4, count(array_unique($ten)), 'Trùng tên trang: ' . implode(' | ', $ten));

        foreach ($ten as $t) {
            $this->assertLessThanOrEqual(31, mb_strlen($t), 'Tên trang quá dài: ' . $t);
        }
    }

    #[Test]
    public function XLSX_ghi_so_thanh_so_chu_ghi_thanh_chu(): void
    {
        $this->donDaGiao();

        $xml = $this->trongXlsx(
            $this->taiVeNhiPhan(['dinh_dang' => 'xlsx', 'phan' => ['tong-quan']]),
            // sheet1 là trang Thông tin, sheet2 mới là phần đầu tiên.
            'xl/worksheets/sheet2.xml',
        );

        /*
         * openspout ghi chữ là `t="inlineStr"`, còn số thì ô KHÔNG có
         * thuộc tính `t`. Đây là khác biệt duy nhất khiến Excel cộng
         * được cột tiền — CSV không có cách nào nói điều này.
         */
        $this->assertMatchesRegularExpression(
            '~<c r="B\d+"[^>]*><v>\d~',
            $xml,
            'Cột giá trị không có ô số nào — mọi thứ đang bị ghi thành chữ',
        );

        // Và ô số KHÔNG được mang t="inlineStr" — mang là Excel coi là chữ.
        // Chỉ xét từ dòng 2: dòng 1 là tên cột, vốn phải là chữ.
        $this->assertDoesNotMatchRegularExpression('~<c r="B2"[^>]*t="inlineStr"~', $xml);

        // Còn cột tên thì ngược lại: phải là chữ.
        $this->assertMatchesRegularExpression('~<c r="A2"[^>]*t="inlineStr"~', $xml);
        $this->assertStringContainsString('Tổng đơn', $xml);
    }

    #[Test]
    public function so_co_chu_so_0_dau_khong_bi_bien_thanh_so(): void
    {
        /*
         * `is_numeric('0912345678')` là true. Ghi nó thành số thì Excel
         * hiện `912345678` — SỐ ĐIỆN THOẠI SAI, im lặng. Mã đơn `0034`
         * cũng vậy.
         *
         * Gọi thẳng bộ ghi vì chưa phần báo cáo nào có cột kiểu này;
         * quy tắc vẫn phải đúng từ trước khi có phần đó.
         */
        $tam = tempnam(sys_get_temp_dir(), 'kt-so-') . '.xlsx';

        app(\App\Services\Analytics\Export\XlsxWriter::class)->ghi(
            collect([[
                'label' => 'Thử kiểu số',
                'columns' => ['Giữ nguyên chữ', 'Là số thật'],
                'rows' => [
                    ['0912345678', '1234567.00'],
                    ['0034', 42],
                    ['+84912345678', -5],
                ],
            ]]),
            'kỳ thử',
            $tam,
        );

        $xml = $this->trongXlsx((string) file_get_contents($tam), 'xl/worksheets/sheet2.xml');
        @unlink($tam);

        foreach (['0912345678', '0034', '+84912345678'] as $giuNguyen) {
            $this->assertStringContainsString(
                '<t>' . $giuNguyen . '</t>',
                $xml,
                $giuNguyen . ' phải được giữ nguyên làm chữ',
            );
        }

        // Còn cột bên phải thì phải là số thật.
        $this->assertMatchesRegularExpression('~<c r="B2"[^>]*><v>1234567~', $xml);
        $this->assertMatchesRegularExpression('~<c r="B3"[^>]*><v>42</v>~', $xml);
        $this->assertMatchesRegularExpression('~<c r="B4"[^>]*><v>-5</v>~', $xml);
    }

    #[Test]
    public function PDF_dung_phong_co_du_dau_tieng_Viet(): void
    {
        /*
         * ĐÂY LÀ ĐIỀU DUY NHẤT KHIẾN PDF DÙNG ĐƯỢC Ở ĐÂY.
         *
         * dompdf không đi hỏi phông của hệ điều hành; nó rơi về
         * Helvetica nếu không gọi đích danh một bộ có sẵn. Helvetica
         * KHÔNG có dấu tiếng Việt, và kết quả là một tệp PDF đầy ô
         * vuông — vẫn tải về được, vẫn mở được, chỉ là không đọc được.
         * Không có gì báo lỗi, nên chỉ bài này bắt được.
         */
        $pdf = $this->taiVeNhiPhan(['dinh_dang' => 'pdf', 'phan' => ['tong-quan']]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('DejaVuSans', $pdf, 'PDF không nhúng DejaVu — chữ có dấu sẽ thành ô vuông');
    }

    #[Test]
    public function PDF_va_HTML_dung_chung_mot_ban_dung_noi_dung(): void
    {
        /*
         * Hai tệp là cùng một báo cáo, chỉ khác cách người nhận mở ra.
         * Nếu dựng riêng thì sớm muộn một bên có cột mà bên kia không
         * có, và hai người cầm hai tệp sẽ cãi nhau về cùng một kỳ.
         */
        $html = app(\App\Services\Analytics\Export\ReportHtml::class);

        $bang = collect([[
            'label' => 'Một phần',
            'columns' => ['Chỉ số', 'Giá trị'],
            'rows' => [['Tổng đơn', 7]],
        ]]);

        $choMan = '';
        $choPdf = '';
        $html->viet($bang, 'kỳ thử', function (string $d) use (&$choMan) { $choMan .= $d; });
        $html->viet($bang, 'kỳ thử', function (string $d) use (&$choPdf) { $choPdf .= $d; }, choPdf: true);

        // Phần thân giống nhau.
        foreach (['<h2>Một phần</h2>', '<th>Chỉ số</th>', '<td>Tổng đơn</td>', '<td>7</td>'] as $doan) {
            $this->assertStringContainsString($doan, $choMan);
            $this->assertStringContainsString($doan, $choPdf);
        }

        // Chỉ phông là khác — và đúng chiều.
        $this->assertStringContainsString('DejaVu Sans', $choPdf);
        $this->assertStringNotContainsString('DejaVu Sans', $choMan);
    }

    #[Test]
    public function ten_san_pham_co_the_HTML_khong_chay_duoc_trong_tep_xuat(): void
    {
        /*
         * Tên sản phẩm, từ khoá khách gõ, tên người nhận — đều là chữ
         * NGƯỜI NGOÀI nhập vào, và đều đi thẳng vào báo cáo. Tệp HTML
         * xuất ra sẽ được mở bằng trình duyệt trên máy kế toán; một thẻ
         * script lọt vào đó là chạy trên máy họ.
         */
        Product::factory()
            ->for(Category::factory())
            ->stock(3)
            ->create(['name' => '<script>alert(1)</script>Cây xấu']);

        $html = $this->taiVeNhiPhan(['dinh_dang' => 'html', 'phan' => ['ton-kho']]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function moi_dinh_dang_deu_tai_ve_duoc_va_dung_kieu_tep(): void
    {
        $this->donDaGiao();

        $mong = [
            'csv' => 'text/csv',
            'xlsx' => 'spreadsheetml.sheet',
            'json' => 'application/json',
            'html' => 'text/html',
            'pdf' => 'application/pdf',
        ];

        // Danh sách định dạng khai ở một nơi; giao diện và bài này cùng đọc nó.
        $this->assertSame(
            array_keys($mong),
            array_keys(\App\Services\Analytics\ReportExporter::DINH_DANG),
        );

        foreach ($mong as $dinhDang => $kieu) {
            $res = $this->actingAs($this->admin())->get(
                '/admin/phan-tich/xuat/tai-ve?' . http_build_query([
                    'ky' => '30',
                    'dinh_dang' => $dinhDang,
                    'phan' => ['tong-quan'],
                ]),
            );

            $res->assertOk();

            $this->assertStringContainsString(
                $kieu,
                (string) $res->headers->get('Content-Type'),
                'Sai kiểu tệp cho ' . $dinhDang,
            );

            $this->assertStringContainsString(
                '.' . $dinhDang,
                (string) $res->headers->get('Content-Disposition'),
                'Sai đuôi tệp cho ' . $dinhDang,
            );
        }
    }
}
