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

/** Xuất dữ liệu phân tích: chọn phần, chọn định dạng. */
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

    #[Test]
    public function trang_chon_liet_ke_du_moi_phan_va_tich_san_tat_ca(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat')
            ->assertOk()
            ->getContent();

        foreach (ReportSections::danhSach() as $ma => $m) {
            $this->assertStringContainsString('value="' . $ma . '"', $html, 'Thiếu phần: ' . $ma);
            $this->assertStringContainsString($m['label'], $html);
        }

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

    #[Test]
    public function chon_mot_phan_thi_tep_chi_co_phan_do(): void
    {
        $this->donDaGiao();

        $csv = $this->taiVe(['phan' => ['ban-chay']]);

        $this->assertStringContainsString('SẢN PHẨM BÁN CHẠY', $csv);
        $this->assertStringContainsString('Cây kiểm thử báo cáo', $csv);

        $this->assertStringNotContainsString('PHỄU CHUYỂN ĐỔI', $csv);
        $this->assertStringNotContainsString('TỪ KHOÁ KHÁCH TÌM', $csv);
    }

    #[Test]
    public function khong_chon_gi_thi_xuat_TAT_CA_chu_khong_ra_tep_rong(): void
    {
        $csv = $this->taiVe();

        foreach (ReportSections::danhSach() as $m) {
            $this->assertStringContainsString(mb_strtoupper($m['label']), $csv);
        }
    }

    #[Test]
    public function ma_phan_la_bi_loc_bo(): void
    {
        $csv = $this->taiVe(['phan' => ['ban-chay', 'khong-co-that']]);

        $this->assertStringContainsString('SẢN PHẨM BÁN CHẠY', $csv);
        $this->assertStringNotContainsString('khong-co-that', $csv);
    }

    #[Test]
    public function thu_tu_phan_theo_danh_sach_da_khai_chu_khong_theo_thu_tu_tich(): void
    {
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
        $csv = $this->taiVe(['phan' => ['bia-dat-1', 'bia-dat-2']]);

        foreach (ReportSections::danhSach() as $m) {
            $this->assertStringContainsString(mb_strtoupper($m['label']), $csv);
        }
    }

    #[Test]
    public function CSV_co_BOM_de_Excel_doc_dung_tieng_Viet(): void
    {
        $csv = $this->taiVe(['dinh_dang' => 'csv']);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    #[Test]
    public function JSON_dung_khoa_co_ten_chu_khong_phai_mang_vi_tri(): void
    {
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

    #[Test]
    public function con_so_trong_tep_khop_voi_don_hang_that(): void
    {
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
        $json = json_decode($this->taiVe(['dinh_dang' => 'json', 'phan' => ['tong-quan']]), true);

        $dong = collect($json['phan'][0]['dong'])->keyBy('Chỉ số');

        $this->assertSame('chưa có đơn đã giao', $dong['Giá trị đơn trung bình']['Giá trị']);
    }

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
        $ten = $this->tenCacTrangTinh($this->taiVeNhiPhan([
            'dinh_dang' => 'xlsx',
            'phan' => ['tong-quan', 'ban-chay', 'ton-kho'],
        ]));

        $this->assertCount(4, $ten);
        $this->assertSame('Thông tin', $ten[0]);
        $this->assertStringContainsString('Tổng quan', $ten[1]);
        $this->assertStringContainsString('Sản phẩm bán chạy', $ten[2]);
        $this->assertStringContainsString('Tồn kho', $ten[3]);
    }

    #[Test]
    public function ten_trang_tinh_luon_hop_le_voi_Excel(): void
    {
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
            'xl/worksheets/sheet2.xml',
        );

        $this->assertMatchesRegularExpression(
            '~<c r="B\d+"[^>]*><v>\d~',
            $xml,
            'Cột giá trị không có ô số nào — mọi thứ đang bị ghi thành chữ',
        );

        $this->assertDoesNotMatchRegularExpression('~<c r="B2"[^>]*t="inlineStr"~', $xml);

        $this->assertMatchesRegularExpression('~<c r="A2"[^>]*t="inlineStr"~', $xml);
        $this->assertStringContainsString('Tổng đơn', $xml);
    }

    #[Test]
    public function so_co_chu_so_0_dau_khong_bi_bien_thanh_so(): void
    {
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

        $this->assertMatchesRegularExpression('~<c r="B2"[^>]*><v>1234567~', $xml);
        $this->assertMatchesRegularExpression('~<c r="B3"[^>]*><v>42</v>~', $xml);
        $this->assertMatchesRegularExpression('~<c r="B4"[^>]*><v>-5</v>~', $xml);
    }

    #[Test]
    public function PDF_dung_phong_co_du_dau_tieng_Viet(): void
    {
        $pdf = $this->taiVeNhiPhan(['dinh_dang' => 'pdf', 'phan' => ['tong-quan']]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('DejaVuSans', $pdf, 'PDF không nhúng DejaVu — chữ có dấu sẽ thành ô vuông');
    }

    #[Test]
    public function PDF_va_HTML_dung_chung_mot_ban_dung_noi_dung(): void
    {
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

        foreach (['<h2>Một phần</h2>', '<th>Chỉ số</th>', '<td>Tổng đơn</td>', '<td>7</td>'] as $doan) {
            $this->assertStringContainsString($doan, $choMan);
            $this->assertStringContainsString($doan, $choPdf);
        }

        $this->assertStringContainsString('DejaVu Sans', $choPdf);
        $this->assertStringNotContainsString('DejaVu Sans', $choMan);
    }

    #[Test]
    public function ten_san_pham_co_the_HTML_khong_chay_duoc_trong_tep_xuat(): void
    {
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
