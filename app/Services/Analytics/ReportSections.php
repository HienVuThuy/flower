<?php

namespace App\Services\Analytics;

use App\Enums\UserEventType;
use Illuminate\Support\Collection;

/**
 * DANH SÁCH DUY NHẤT các phần có thể xuất ra tệp.
 * ============================================================
 * VÌ SAO MỘT NƠI: màn hình chọn phần và đoạn mã ghi tệp phải nói về
 * cùng một danh sách. Khai ở hai nơi thì sớm muộn ô đánh dấu có một
 * phần mà tệp không có, hoặc ngược lại — và không có gì báo, người dùng
 * chỉ nhận một tệp thiếu.
 *
 * Mỗi phần tự khai TÊN CỘT và cách lấy DÒNG. Nhờ vậy thêm một báo cáo
 * mới là thêm một mục ở đây, không phải sửa cả bộ xuất file lẫn giao
 * diện.
 *
 * ============================================================
 * MỌI CON SỐ ĐẾM TỪ CƠ SỞ DỮ LIỆU, đúng kỳ admin đang xem. Không ước
 * lượng, không làm tròn cho đẹp, không điền chỗ trống.
 */
class ReportSections
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    /**
     * Mọi phần, kèm nhãn và nhóm để giao diện xếp cho gọn.
     *
     * @return array<string, array{label: string, group: string, note: string}>
     */
    public static function danhSach(): array
    {
        return [
            'tong-quan' => [
                'label' => 'Tổng quan đơn hàng và doanh thu',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Số đơn, đã giao, đã huỷ, doanh thu, giá trị đơn trung bình.',
            ],
            'doanh-thu-ngay' => [
                'label' => 'Doanh thu theo từng ngày',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Mỗi ngày một dòng, kể cả ngày không có đơn.',
            ],
            'trang-thai' => [
                'label' => 'Cơ cấu trạng thái đơn',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Số đơn và tiền theo từng trạng thái.',
            ],
            'thanh-toan' => [
                'label' => 'Hình thức thanh toán',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Khách chọn cách nào, cách nào mang về tiền.',
            ],
            'ma-giam-gia' => [
                'label' => 'Mã giảm giá đã dùng',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Đọc từ bản chụp trên đơn, mã bị xoá vẫn còn dấu vết.',
            ],
            'khach-hang' => [
                'label' => 'Khách mua nhiều nhất',
                'group' => 'Tiền và đơn hàng',
                'note' => 'Chỉ khách có tài khoản; đơn khách vãng lai không gom được.',
            ],

            'van-chuyen' => [
                'label' => 'Phí ship thu của khách so với cước trả GHN, theo tháng',
                'group' => 'Vận chuyển',
                'note' => 'Chỉ vận đơn cửa hàng trả cước, chưa huỷ, có số liệu cước. Cước theo lúc tạo vận đơn, chưa gồm phí hoàn.',
            ],
            'bu-ship' => [
                'label' => 'Đơn cửa hàng bù ship',
                'group' => 'Vận chuyển',
                'note' => 'Từng đơn có cước GHN cao hơn phí thu của khách, bù nhiều nhất trước.',
            ],

            'pheu' => [
                'label' => 'Phễu chuyển đổi',
                'group' => 'Hành vi khách hàng',
                'note' => 'Đếm theo PHIÊN, không phải theo lượt.',
            ],
            'san-pham-xem' => [
                'label' => 'Sản phẩm được xem nhiều',
                'group' => 'Hành vi khách hàng',
                'note' => 'Từ nhật ký sự kiện.',
            ],
            'tu-khoa' => [
                'label' => 'Từ khoá khách tìm',
                'group' => 'Hành vi khách hàng',
                'note' => 'Kể cả từ khoá không ra kết quả nào.',
            ],
            'danh-muc' => [
                'label' => 'Danh mục được xem nhiều',
                'group' => 'Hành vi khách hàng',
                'note' => 'Từ nhật ký sự kiện.',
            ],

            'ban-chay' => [
                'label' => 'Sản phẩm bán chạy',
                'group' => 'Sản phẩm',
                'note' => 'Chỉ đơn đã giao. Gom theo mã sản phẩm, không theo tên.',
            ],

            'ton-kho' => [
                'label' => 'Tồn kho đầy đủ',
                'group' => 'Sản phẩm',
                'note' => 'Mọi mặt hàng có theo dõi tồn, kèm tốc độ bán và số ngày còn bán được. Giá trị tính theo GIÁ BÁN, không phải giá vốn.',
            ],
        ];
    }

    /** Mã của mọi phần — dùng để kiểm tra dữ liệu gửi lên. */
    public static function maHopLe(): array
    {
        return array_keys(self::danhSach());
    }

    /**
     * Dựng một phần thành bảng: tiêu đề, tên cột, các dòng.
     *
     * @return array{label: string, columns: list<string>, rows: list<list<scalar|null>>}
     */
    public function bang(string $ma): array
    {
        $nhan = self::danhSach()[$ma]['label'] ?? $ma;

        return match ($ma) {
            'tong-quan' => $this->tongQuan($nhan),
            'doanh-thu-ngay' => $this->doanhThuNgay($nhan),
            'trang-thai' => $this->trangThai($nhan),
            'thanh-toan' => $this->thanhToan($nhan),
            'ma-giam-gia' => $this->maGiamGia($nhan),
            'khach-hang' => $this->khachHang($nhan),
            'van-chuyen' => $this->vanChuyen($nhan),
            'bu-ship' => $this->buShip($nhan),
            'pheu' => $this->pheu($nhan),
            'san-pham-xem' => $this->sanPhamXem($nhan),
            'tu-khoa' => $this->tuKhoa($nhan),
            'danh-muc' => $this->danhMuc($nhan),
            'ban-chay' => $this->banChay($nhan),
            'ton-kho' => $this->tonKho($nhan),

            // Mã lạ không bao giờ tới được đây (controller đã lọc), nhưng
            // trả về bảng rỗng vẫn hơn là ném lỗi giữa lúc ghi tệp.
            default => ['label' => $nhan, 'columns' => [], 'rows' => []],
        };
    }

    private function tongQuan(string $nhan): array
    {
        $o = $this->analytics->orderStats();

        return [
            'label' => $nhan,
            'columns' => ['Chỉ số', 'Giá trị'],
            'rows' => [
                ['Tổng đơn', $o['total']],
                ['Đã giao', $o['completed']],
                ['Đã huỷ', $o['cancelled']],
                ['Doanh thu (đơn đã giao)', $o['revenue']],
                /*
                 * null nghĩa là MẪU SỐ BẰNG 0 — chưa có đơn đã giao nào.
                 * Ghi 0 ở đây là nói "giá trị đơn trung bình bằng 0", một
                 * câu khác hẳn và sai.
                 */
                ['Giá trị đơn trung bình', $o['average'] ?? 'chưa có đơn đã giao'],
            ],
        ];
    }

    private function doanhThuNgay(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Ngày', 'Số đơn đã giao', 'Doanh thu'],
            'rows' => $this->analytics->revenueByDay()
                ->map(fn ($d) => [$d['date'], $d['orders'], $d['revenue']])
                ->all(),
        ];
    }

    private function trangThai(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Trạng thái', 'Số đơn', 'Tổng tiền'],
            'rows' => $this->analytics->statusBreakdown()
                ->map(fn ($r) => [$r['status']->label(), $r['total'], $r['revenue']])
                ->all(),
        ];
    }

    private function thanhToan(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Hình thức', 'Số đơn', 'Doanh thu (đơn đã giao)'],
            'rows' => $this->analytics->paymentMix()
                ->map(fn ($r) => [$r['method']->label(), $r['total'], $r['revenue']])
                ->all(),
        ];
    }

    private function maGiamGia(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Mã', 'Số đơn', 'Tổng tiền đã giảm'],
            'rows' => $this->analytics->couponUsage(100)
                ->map(fn ($r) => [$r['code'], $r['orders'], $r['discount']])
                ->all(),
        ];
    }

    private function khachHang(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Khách hàng', 'Email', 'Số đơn', 'Doanh thu'],
            'rows' => $this->analytics->topCustomers(100)
                ->map(fn ($r) => [$r['name'], $r['email'], $r['orders'], $r['revenue']])
                ->all(),
        ];
    }

    private function vanChuyen(string $nhan): array
    {
        $tong = $this->analytics->shippingCost();

        $dong = $this->analytics->shippingCostByMonth()
            ->map(fn ($m) => [$m['thang'], $m['don'], $m['thu'], $m['tra'], $m['chenh']])
            ->all();

        /*
         * DÒNG TỔNG, rồi DÒNG GHI CHÚ cho phần bị loại.
         *
         * Tệp xuất ra bị mở ở chỗ không có giao diện giải thích. Không
         * ghi số vận đơn bị loại ngay trong tệp thì người đọc bảng tính
         * tưởng tổng là của cả kỳ.
         */
        $dong[] = ['Tổng', $tong['tinh_duoc'], $tong['thu'], $tong['tra'], $tong['chenh']];
        $dong[] = ['Không tính: người nhận trả cước', $tong['loai']['nguoi_nhan_tra'], null, null, null];
        $dong[] = ['Không tính: vận đơn đã huỷ', $tong['loai']['da_huy'], null, null, null];
        $dong[] = ['Không tính: GHN không báo cước', $tong['loai']['thieu_cuoc'], null, null, null];

        return [
            'label' => $nhan,
            'columns' => ['Tháng', 'Số vận đơn', 'Thu của khách', 'Trả GHN', 'Cửa hàng bù (âm = thu dư)'],
            'rows' => $dong,
        ];
    }

    private function buShip(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Mã đơn', 'Mã vận đơn', 'Tỉnh', 'Thu của khách', 'Trả GHN', 'Cửa hàng bù'],
            'rows' => $this->analytics->shippingSubsidies(1000)
                ->map(fn ($d) => [
                    $d['order']->order_number,
                    $d['order']->ghn_order_code,
                    $d['order']->shipping_province,
                    $d['thu'],
                    $d['tra'],
                    $d['chenh'],
                ])
                ->all(),
        ];
    }

    private function pheu(string $nhan): array
    {
        $f = $this->analytics->funnel();

        return [
            'label' => $nhan,
            'columns' => ['Bước', 'Số phiên'],
            'rows' => [
                ['Phiên có xem sản phẩm', $f['views']],
                ['Phiên có thêm vào giỏ', $f['carts']],
                ['Phiên có mua', $f['purchases']],
                ['Xem → Giỏ (%)', $f['view_to_cart'] ?? 'không tính được'],
                ['Giỏ → Mua (%)', $f['cart_to_purchase'] ?? 'không tính được'],
            ],
        ];
    }

    private function sanPhamXem(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Sản phẩm', 'Lượt xem'],
            'rows' => $this->analytics->topProducts(UserEventType::ProductView, 100)
                // `product` là null khi sản phẩm đã xoá mà nhật ký còn —
                // ghi rõ thay vì để ô trống không giải thích.
                ->map(fn ($r) => [$r['product']?->name ?? '(sản phẩm đã xoá)', $r['total']])
                ->all(),
        ];
    }

    private function tuKhoa(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Từ khoá', 'Lượt tìm'],
            'rows' => $this->analytics->topSearches(100)
                ->map(fn ($r) => [$r['term'], $r['total']])
                ->all(),
        ];
    }

    private function danhMuc(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Danh mục', 'Lượt xem'],
            'rows' => $this->analytics->topCategories(100)
                ->map(fn ($r) => [$r['category']?->name ?? '(danh mục đã xoá)', $r['total']])
                ->all(),
        ];
    }

    private function banChay(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Sản phẩm', 'Số lượng', 'Doanh thu'],
            'rows' => $this->analytics->bestSellers(100)
                ->map(fn ($r) => [$r['name'], $r['quantity'], $r['revenue']])
                ->all(),
        ];
    }

    /**
     * Tồn kho — dùng chung một nguồn với trang Tồn kho.
     *
     * `cover` null nghĩa là CẢ KỲ KHÔNG BÁN ĐƯỢC CÁI NÀO (mẫu số bằng
     * 0). Ghi chữ chứ không ghi số: một con số ở đó là bịa, còn ô trống
     * thì người đọc tệp không biết vì sao trống.
     */
    private function tonKho(string $nhan): array
    {
        return [
            'label' => $nhan,
            'columns' => ['Mặt hàng', 'Quy cách', 'Tồn', 'Đã bán trong kỳ', 'Bán/ngày', 'Còn bán được (ngày)', 'Giá bán', 'Giá trị theo giá bán'],
            'rows' => app(InventoryReport::class)->rows()
                ->map(fn (array $r) => [
                    $r['name'],
                    $r['variant'] ?? '',
                    $r['stock'],
                    $r['sold'],
                    round($r['per_day'], 2),
                    $r['cover'] === null ? 'chưa bán được cái nào' : round($r['cover'], 1),
                    $r['price'],
                    $r['value'],
                ])
                ->all(),
        ];
    }

    /**
     * Dựng nhiều phần một lượt, giữ nguyên thứ tự đã khai.
     *
     * GIỮ THỨ TỰ CỦA DANH SÁCH, không theo thứ tự người dùng tích: tệp
     * xuất ra phải luôn cùng một bố cục để so hai kỳ với nhau được.
     *
     * @param  list<string>  $ma
     * @return Collection<int, array{label: string, columns: list<string>, rows: list}>
     */
    public function nhieuBang(array $ma): Collection
    {
        return collect(self::maHopLe())
            ->filter(fn (string $m) => in_array($m, $ma, true))
            ->map(fn (string $m) => $this->bang($m))
            ->values();
    }
}
