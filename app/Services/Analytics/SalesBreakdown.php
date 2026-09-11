<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use Illuminate\Support\Collection;

/**
 * Doanh thu cắt theo từng chiều: danh mục, tỉnh, khung giờ, khách mới/cũ.
 * ============================================================
 * MỌI CON SỐ Ở ĐÂY CHỈ TÍNH ĐƠN ĐÃ GIAO — cùng định nghĩa doanh thu với
 * AnalyticsService — trừ bản đồ khung giờ, vốn trả lời câu hỏi khác ("khách
 * ĐẶT hàng lúc nào") nên đếm mọi đơn đã đặt.
 *
 * Kỳ lấy từ AnalyticsService::khoang(), không tự tính: "30 ngày qua" ở trang
 * này phải là đúng khoảng ở trang Tổng hợp.
 */
class SalesBreakdown
{
    private KhoangThoiGian $khoang;

    public function __construct()
    {
        $this->khoang = new KhoangThoiGian();
    }

    public function trong(KhoangThoiGian $khoang): static
    {
        $this->khoang = $khoang;

        return $this;
    }

    /* ================= THEO DANH MỤC ================= */

    /**
     * Doanh thu HÀNG theo danh mục.
     *
     * TIỀN CỦA MỘT DÒNG = `line_total − discount_amount`: giá sau khuyến mại,
     * trừ phần mã giảm giá đã chia về dòng đó. Chưa gồm phí ship (không thuộc
     * danh mục nào) và chưa trừ hoàn tiền (hoàn tiền ghi theo đơn, không theo
     * dòng). Tổng các danh mục vì thế KHÔNG bằng doanh thu ở trang Tổng hợp —
     * và `ma_giam_chua_chia` nói rõ phần chênh do đâu.
     *
     * DANH MỤC HIỆN TẠI của sản phẩm: dòng đơn chụp tên và giá, không chụp
     * danh mục. Chuyển sản phẩm sang danh mục khác thì doanh thu cũ đi theo.
     *
     * @return array{dong: Collection<int, array{ten: string, doanh_thu: string, so_luong: int, so_don: int, ti_le: float|null}>,
     *               tong: string, ma_giam_chua_chia: string}
     */
    public function theoDanhMuc(): array
    {
        $dong = $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at'),
            'orders.created_at',
        )
            ->groupBy('products.category_id')
            ->selectRaw(
                'products.category_id as danh_muc,'
                .' SUM(order_items.line_total - order_items.discount_amount) as tien,'
                .' SUM(order_items.quantity) as sl,'
                .' COUNT(DISTINCT order_items.order_id) as don'
            )
            ->get();

        $ten = Category::whereIn('id', $dong->pluck('danh_muc')->filter())->pluck('name', 'id');

        $tong = '0.00';
        foreach ($dong as $d) {
            $tong = bcadd($tong, (string) $d->tien, 2);
        }

        /*
         * MÃ GIẢM GIÁ CHƯA CHIA ĐƯỢC VỀ DÒNG.
         *
         * Việc chia mã về từng dòng có từ khi làm thuế GTGT. Đơn cũ hơn có
         * `coupon_discount` ở đầu đơn nhưng `discount_amount` của các dòng
         * đều là 0 — doanh thu danh mục của chúng cao hơn thực tế đúng bằng
         * số này. Nói ra, không lặng lẽ chia đoán.
         */
        $donGiao = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        );

        $maTrenDon = (string) (clone $donGiao)->sum('coupon_discount');
        $maTrenDong = (string) $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at'),
            'orders.created_at',
        )->sum('order_items.discount_amount');

        $chuaChia = bcsub(bcadd($maTrenDon, '0', 2), bcadd($maTrenDong, '0', 2), 2);

        return [
            'dong' => $dong
                ->map(fn ($d) => [
                    'ten' => $d->danh_muc === null
                        ? '(sản phẩm đã xoá)'
                        : (string) ($ten[$d->danh_muc] ?? '(danh mục đã xoá)'),
                    'doanh_thu' => bcadd((string) $d->tien, '0', 2),
                    'so_luong' => (int) $d->sl,
                    'so_don' => (int) $d->don,
                    'ti_le' => bccomp($tong, '0', 2) > 0
                        ? round((float) $d->tien / (float) $tong * 100, 1)
                        : null,
                ])
                ->sortByDesc(fn ($d) => (float) $d['doanh_thu'])
                ->values(),
            'tong' => $tong,
            'ma_giam_chua_chia' => bccomp($chuaChia, '0', 2) > 0 ? $chuaChia : '0.00',
        ];
    }

    /* ================= THEO TỈNH ================= */

    /**
     * Doanh thu theo tỉnh/thành nhận hàng, đã trừ hoàn tiền.
     *
     * GỘP TÊN VIẾT KHÁC NHAU CỦA CÙNG MỘT TỈNH. Dữ liệu thật có "Hà Nội" (1
     * đơn) và "Thành phố Hà Nội" (19 đơn) — gom thẳng theo chuỗi là tách một
     * tỉnh làm hai dòng, và dòng lớn thấp hơn sự thật. Xem TenTinh::khoa().
     *
     * KHÔNG tự gộp theo đơn vị hành chính mới sau sáp nhập tỉnh: đó là đổi
     * địa lý của đơn cũ, và bảng tra nào dùng để gộp cũng là một giả định.
     * Tên hiển thị là tên khách đã chọn lúc đặt.
     *
     * @return Collection<int, array{ten: string, so_don: int, doanh_thu: string, hoan_tien: string, thuan: string, trung_binh: string}>
     */
    public function theoTinh(): Collection
    {
        $don = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        )->get(['id', 'shipping_province', 'grand_total']);

        $hoan = Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', $don->pluck('id'))
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount) as tien')
            ->pluck('tien', 'order_id');

        return $don
            ->groupBy(fn ($o) => TenTinh::khoa($o->shipping_province))
            ->map(function (Collection $nhom) use ($hoan) {
                $doanhThu = '0.00';
                $daHoan = '0.00';

                foreach ($nhom as $o) {
                    $doanhThu = bcadd($doanhThu, (string) $o->grand_total, 2);
                    $daHoan = bcadd($daHoan, (string) ($hoan[$o->id] ?? '0'), 2);
                }

                $thuan = bcsub($doanhThu, $daHoan, 2);

                return [
                    'ten' => TenTinh::nhan($nhom->pluck('shipping_province')),
                    'so_don' => $nhom->count(),
                    'doanh_thu' => $doanhThu,
                    'hoan_tien' => $daHoan,
                    'thuan' => $thuan,
                    'trung_binh' => bcdiv($thuan, (string) $nhom->count(), 2),
                ];
            })
            ->sortByDesc(fn ($d) => (float) $d['thuan'])
            ->values();
    }

    /* ================= KHUNG GIỜ ================= */

    /**
     * Số đơn ĐẶT theo thứ trong tuần × giờ, theo GIỜ VIỆT NAM.
     *
     * Đếm mọi đơn đã đặt, kể cả đơn sau đó bị huỷ: câu hỏi là "khách vào mua
     * lúc nào" — để biết trực đơn giờ nào, chạy khuyến mại giờ nào — không
     * phải "tiền về lúc nào".
     *
     * Thứ tự hàng: Thứ Hai tới Chủ Nhật, như lịch Việt Nam, không phải
     * Chủ Nhật trước như `dayOfWeek` của PHP.
     *
     * @return array{o: array<int, array<int, int>>, theo_thu: array<int, int>, theo_gio: array<int, int>, tong: int, cao_nhat: int}
     */
    public function theoKhungGio(): array
    {
        $o = array_fill(0, 7, array_fill(0, 24, 0));

        $this->khoang->apDung(Order::query(), 'created_at')
            ->pluck('created_at')
            ->each(function ($t) use (&$o) {
                $dp = KhoangThoiGian::diaPhuong($t);
                // isoWeekday: 1 = Thứ Hai … 7 = Chủ Nhật.
                $o[$dp->isoWeekday() - 1][(int) $dp->format('G')]++;
            });

        $theoThu = array_map('array_sum', $o);
        $theoGio = array_map(fn (int $h) => array_sum(array_column($o, $h)), range(0, 23));

        return [
            'o' => $o,
            'theo_thu' => $theoThu,
            'theo_gio' => $theoGio,
            'tong' => array_sum($theoThu),
            'cao_nhat' => max(array_map('max', $o)),
        ];
    }

    /* ================= KHÁCH MỚI / QUAY LẠI ================= */

    /**
     * Doanh thu từ đơn ĐẦU TIÊN so với đơn MUA LẠI, trong kỳ.
     *
     * "Đơn đầu tiên" của một khách = đơn đã giao có id nhỏ nhất của tài khoản
     * đó TRÊN TOÀN BỘ LỊCH SỬ, không phải trong kỳ. Tính trong kỳ thì khách
     * mua từ năm ngoái mà tháng này mua lần đầu-trong-tháng bị gọi là "khách
     * mới".
     *
     * KHÁCH VÃNG LAI (không tài khoản) để riêng: không có gì nối hai đơn của
     * cùng một người. Đoán bằng số điện thoại là gán nhầm cho người dùng chung
     * một số, nên không đoán.
     *
     * `ti_le_mua_lai` tính trên TOÀN BỘ LỊCH SỬ và nói rõ như vậy: trong một
     * kỳ 7 ngày gần như không ai kịp mua lần hai, con số đó luôn gần 0.
     *
     * @return array{moi: array{khach: int, don: int, doanh_thu: string},
     *               quay_lai: array{khach: int, don: int, doanh_thu: string},
     *               vang_lai: array{don: int, doanh_thu: string},
     *               ti_le_mua_lai: float|null, khach_co_don: int, khach_mua_lai: int}
     */
    public function khachMoiVaQuayLai(): array
    {
        $donDau = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MIN(id) as dau, COUNT(*) as so_don')
            ->get()
            ->keyBy('user_id');

        $trongKy = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        )->get(['id', 'user_id', 'grand_total']);

        $ket = [
            'moi' => ['khach' => [], 'don' => 0, 'doanh_thu' => '0.00'],
            'quay_lai' => ['khach' => [], 'don' => 0, 'doanh_thu' => '0.00'],
            'vang_lai' => ['don' => 0, 'doanh_thu' => '0.00'],
        ];

        foreach ($trongKy as $o) {
            if ($o->user_id === null) {
                $ket['vang_lai']['don']++;
                $ket['vang_lai']['doanh_thu'] = bcadd($ket['vang_lai']['doanh_thu'], (string) $o->grand_total, 2);

                continue;
            }

            $nhom = (int) $donDau[$o->user_id]->dau === (int) $o->id ? 'moi' : 'quay_lai';

            $ket[$nhom]['khach'][$o->user_id] = true;
            $ket[$nhom]['don']++;
            $ket[$nhom]['doanh_thu'] = bcadd($ket[$nhom]['doanh_thu'], (string) $o->grand_total, 2);
        }

        foreach (['moi', 'quay_lai'] as $nhom) {
            $ket[$nhom]['khach'] = count($ket[$nhom]['khach']);
        }

        $coDon = $donDau->count();
        $muaLai = $donDau->filter(fn ($r) => (int) $r->so_don >= 2)->count();

        return $ket + [
            'khach_co_don' => $coDon,
            'khach_mua_lai' => $muaLai,
            'ti_le_mua_lai' => $coDon > 0 ? round($muaLai / $coDon * 100, 1) : null,
        ];
    }
}
