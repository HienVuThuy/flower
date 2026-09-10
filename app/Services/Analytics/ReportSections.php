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
            'pheu' => $this->pheu($nhan),
            'san-pham-xem' => $this->sanPhamXem($nhan),
            'tu-khoa' => $this->tuKhoa($nhan),
            'danh-muc' => $this->danhMuc($nhan),
            'ban-chay' => $this->banChay($nhan),

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
