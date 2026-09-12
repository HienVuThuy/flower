<?php

namespace App\Services\Exchange;

use App\Enums\ExchangeReason;
use App\Enums\ExchangeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Models\Exchange;
use App\Models\ExchangeItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockReturn;
use App\Services\Refund\RefundService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Đổi hàng: khách trả món này, nhận món khác.
 * ============================================================
 * CHÍNH SÁCH ĐANG ÁP DỤNG — ba con số, khai ngay ở đây:
 *
 *   1. HẠN 7 NGÀY kể từ khi đơn chuyển sang "đã giao".
 *   2. HOA TƯƠI KHÔNG ĐỔI. Hàng tươi sống quay về là hàng bỏ đi, không
 *      bán lại được cho ai; nhận đổi nghĩa là cửa hàng mất trắng món đó
 *      và khách vẫn nghĩ mình được phục vụ tử tế. Cây cảnh, chậu, vật tư
 *      thì đổi được.
 *   3. LỖI CỬA HÀNG thì cửa hàng chịu phí ship chiều đổi; KHÁCH ĐỔI Ý
 *      thì khách trả, và mức phí lấy đúng phí ship đã thu trên đơn gốc —
 *      không tự nghĩ ra một con số mới.
 *
 * Ba con số này nằm trong hằng số và trong enum lý do, không rải rác
 * trong controller: đổi chính sách là sửa một chỗ, và đọc mã là biết cửa
 * hàng đang hứa gì với khách.
 *
 * ============================================================
 * GIÁ: HÀNG TRẢ THEO GIÁ ĐÃ TRẢ, HÀNG MỚI THEO GIÁ HÔM NAY.
 *
 * Đây là chỗ dễ làm sai nhất và làm sai thì mất tiền thật. Khách mua
 * chậu 500.000₫ lúc đang giảm giá còn 350.000₫; nay đổi sang chậu khác.
 * Tính hàng trả theo giá niêm yết 500.000₫ là trả cho khách phần khuyến
 * mại mà họ chưa từng bỏ ra. Nên chiều trả về LUÔN lấy `unit_price` trên
 * dòng đơn — số khách thật sự đã trả cho mỗi cái.
 *
 * ============================================================
 * TIỀN CHỈ CÓ MỘT ĐƯỜNG RA.
 *
 * Cửa hàng nợ lại (hàng mới rẻ hơn) thì phiếu này KHÔNG tự trả tiền — nó
 * lập một chứng từ hoàn tiền và trỏ sang đó. Hoàn tiền đã có sổ riêng,
 * đã có bước xác nhận tiền thật sự đi, và đã được trừ khỏi doanh thu
 * thuần. Thêm một đường ra thứ hai là thêm một con số không ai đối chiếu.
 *
 * ============================================================
 * KHO: GIỮ HÀNG MỚI NGAY, NHẬN HÀNG CŨ SAU.
 *
 * Hàng mới trừ kho ngay lúc lập phiếu. Không giữ thì giữa lúc hẹn với
 * khách và lúc hàng cũ về, món đó đã bán cho người khác — và cửa hàng
 * phải gọi điện nuốt lời.
 *
 * Hàng cũ chỉ cộng lại kho khi ĐÃ NHẬN và được đánh dấu còn bán được.
 * Cùng nguyên tắc với hàng trả về ở QĐ-226: chậu vỡ khách gửi về không
 * phải hàng tồn.
 */
class ExchangeService
{
    /** Hạn đổi, tính từ lúc đơn chuyển sang "đã giao". */
    public const HAN_DOI_NGAY = 7;

    public function __construct(
        private readonly StockReturn $stock,
        private readonly RefundService $refunds,
    ) {
    }

    /**
     * Vì sao đơn này không đổi hàng được, hoặc null nếu được.
     *
     * Trả về CÂU CHỮ chứ không phải true/false: giao diện dùng đúng câu
     * này để nói với admin, thay vì ẩn nút mà không giải thích.
     */
    public function lyDoKhongDoiDuoc(Order $order): ?string
    {
        if ($order->status !== OrderStatus::Completed) {
            return 'Chỉ đổi hàng cho đơn đã giao. Đơn chưa giao thì sửa đơn hoặc huỷ đơn.';
        }

        if ($order->completed_at === null) {
            return 'Đơn này chưa có mốc giao hàng nên không tính được hạn đổi.';
        }

        $hetHan = $order->completed_at->copy()->addDays(self::HAN_DOI_NGAY);

        if ($hetHan->isPast()) {
            return sprintf(
                'Đã quá hạn đổi %d ngày kể từ khi giao (hết hạn %s).',
                self::HAN_DOI_NGAY,
                \App\Services\Time\Gio::hien($hetHan)->format('d/m/Y'),
            );
        }

        if ($this->dongDoiDuoc($order)->isEmpty()) {
            return 'Đơn này không có món nào còn đổi được.';
        }

        return null;
    }

    /**
     * Vì sao một dòng hàng không đổi được, hoặc null nếu được.
     */
    public function lyDoDongKhongDoiDuoc(OrderItem $item): ?string
    {
        if ($item->product?->product_type === ProductType::Flower) {
            return 'Hoa tươi không đổi được — hàng quay về không bán lại cho ai được.';
        }

        if ($this->conDoiDuoc($item) <= 0) {
            return 'Món này đã đổi hoặc đã trả về hết.';
        }

        return null;
    }

    /** Các dòng của đơn còn đổi được. */
    public function dongDoiDuoc(Order $order): \Illuminate\Support\Collection
    {
        return $order->items
            ->loadMissing('product')
            ->filter(fn (OrderItem $i) => $this->lyDoDongKhongDoiDuoc($i) === null)
            ->values();
    }

    /**
     * Số lượng của một dòng đơn đã được đổi (không tính phiếu đã huỷ).
     */
    public function soDaDoi(OrderItem $item): int
    {
        return (int) ExchangeItem::query()
            ->where('chieu', ExchangeItem::TRA_VE)
            ->where('order_item_id', $item->id)
            ->whereHas('exchange', fn ($q) => $q->where('status', '!=', ExchangeStatus::Huy->value))
            ->sum('quantity');
    }

    /**
     * Còn đổi được bao nhiêu cái của dòng này.
     *
     * TRỪ CẢ PHẦN ĐÃ TRẢ VỀ QUA HOÀN TIỀN. Hai đường đều lấy hàng ra khỏi
     * đơn; đếm riêng thì khách trả 2 cái qua hoàn tiền rồi đổi tiếp 2 cái
     * nữa của một dòng chỉ có 3.
     */
    public function conDoiDuoc(OrderItem $item): int
    {
        return max(0, (int) $item->quantity - $this->soDaDoi($item) - $this->refunds->soDaTra($item));
    }

    /**
     * Lập phiếu đổi hàng.
     *
     * @param  array{reason: string, note?: ?string,
     *               tra: array<int|string, array{quantity?: mixed}>,
     *               moi: array<int, array{product_id?: mixed, variant_id?: mixed, quantity?: mixed}>}  $data
     *               `tra` đánh khoá theo id dòng đơn.
     *
     * @throws ExchangeException
     */
    public function tao(Order $order, array $data): Exchange
    {
        $lyDo = ExchangeReason::from((string) $data['reason']);

        return DB::transaction(function () use ($order, $data, $lyDo) {
            /*
             * KHOÁ ĐƠN RỒI ĐỌC LẠI.
             *
             * Số "còn đổi được" phải đếm bên trong khoá. Kiểm bằng số đã
             * nạp từ trước thì hai người bấm cùng lúc cùng thấy "còn 2
             * cái" và cùng lập phiếu — đơn 3 cái bị đổi 4.
             */
            $khoa = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new ExchangeException('Không tìm thấy đơn hàng.');
            }

            $khoa->load('items.product');

            if ($ly = $this->lyDoKhongDoiDuoc($khoa)) {
                throw new ExchangeException($ly);
            }

            $dongTra = $this->dongHangTra($khoa, $data['tra'] ?? []);

            if ($dongTra === []) {
                throw new ExchangeException('Phải chọn ít nhất một món khách trả về.');
            }

            $dongMoi = $this->dongHangMoi($data['moi'] ?? []);

            if ($dongMoi === []) {
                throw new ExchangeException('Phải chọn ít nhất một món gửi cho khách.');
            }

            $tienTra = $this->tong($dongTra);
            $tienMoi = $this->tong($dongMoi);

            /*
             * PHÍ SHIP LẤY ĐÚNG SỐ ĐÃ THU TRÊN ĐƠN GỐC, không nghĩ ra số
             * mới. Khách đã biết con số đó; đưa ra một con số khác ở lần
             * đổi là bắt họ đi hỏi vì sao.
             */
            $phiShip = $lyDo->cuaHangChiuPhiShip()
                ? '0.00'
                : bcadd((string) $khoa->shipping_fee, '0', 2);

            $chenh = bcsub(bcadd($tienMoi, $phiShip, 2), $tienTra, 2);

            /*
             * MỘT LẦN GHI, DANH SÁCH KHOÁ VIẾT TAY.
             *
             * `code` và các cột tiền CỐ Ý không nằm trong `$fillable` —
             * không biểu mẫu nào được đặt chúng. Nhưng thế cũng nghĩa là
             * `Exchange::create()` chèn dòng khi `code` còn rỗng, mà cột
             * đó NOT NULL. Nên dựng đối tượng rồi ghi một lần, với đúng
             * danh sách khoá viết ra ở đây; không có `$request->all()`
             * nào đi qua chỗ này.
             */
            $phieu = new Exchange();

            $phieu->forceFill([
                'order_id' => $khoa->id,
                'reason' => $lyDo,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'code' => $this->sinhMa(),
                'tien_hang_tra' => $tienTra,
                'tien_hang_moi' => $tienMoi,
                'phi_ship' => $phiShip,
                'chenh_lech' => $chenh,
                'status' => ExchangeStatus::ChoNhan,
                'created_by' => Auth::id(),
            ])->save();

            foreach ([...$dongTra, ...$dongMoi] as $dong) {
                $phieu->items()->create($dong);
            }

            // GIỮ HÀNG MỚI NGAY. Xem chú thích đầu lớp.
            foreach ($dongMoi as $dong) {
                $this->giuHang($dong, -1);
            }

            /*
             * CỬA HÀNG NỢ LẠI thì lập luôn chứng từ hoàn tiền, ở trạng
             * thái chờ — tiền chỉ thật sự đi khi người thật xác nhận, y
             * như mọi khoản hoàn khác.
             *
             * Lập ngay chứ không đợi hoàn tất phiếu: khoản nợ đã phát
             * sinh từ lúc thoả thuận, và một khoản nợ chưa được ghi sổ là
             * một khoản nợ sẽ bị quên.
             */
            if (bccomp($chenh, '0', 2) < 0) {
                /*
                 * HOÀN TIỀN CÓ LUẬT RIÊNG và có thể từ chối — đơn chưa
                 * thanh toán thì không có gì để hoàn.
                 *
                 * Để lỗi của lớp hoàn tiền nổi thẳng lên màn hình thì
                 * người lập phiếu đọc "khách chưa trả tiền cho đơn này"
                 * và không hiểu vì sao mình lại thấy câu đó khi đang đổi
                 * hàng. Bọc lại, giữ nguyên câu gốc, nhưng nói rõ nó đến
                 * từ đâu.
                 */
                try {
                    $refund = $this->refunds->hoan($khoa, [
                        'amount' => (int) abs((float) $chenh),
                        /*
                         * LÝ DO "KHÁC", KHÔNG PHẢI "KHÁCH TRẢ HÀNG".
                         *
                         * "Khách trả hàng" bắt phiếu hoàn tiền phải kê rõ
                         * món nào quay về, và nó sẽ tự cộng lại kho. Ở đây
                         * hàng đã được phiếu đổi kê và xử lý rồi — để lý do
                         * đó là kho được cộng HAI LẦN cho cùng một món.
                         *
                         * Ghi chú mang mã phiếu đổi nên vẫn tra ngược được.
                         */
                        'reason' => RefundReason::Other->value,
                        'method' => RefundMethod::Cash->value,
                        'note' => 'Chênh lệch đổi hàng ' . $phieu->code,
                        // Hàng trả về đã được phiếu đổi xử lý; không nhờ
                        // phiếu hoàn tiền cộng lại kho lần nữa.
                        'items' => [],
                    ]);
                } catch (\App\Services\Refund\RefundException $e) {
                    throw new ExchangeException(
                        'Không lập được khoản trả lại chênh lệch: ' . $e->getMessage()
                    );
                }

                $phieu->forceFill(['refund_id' => $refund->id])->save();
            }

            return $phieu->fresh(['items', 'refund']);
        });
    }

    /**
     * Hàng cũ đã về kho.
     *
     * @param  array<int|string, mixed>  $banLaiDuoc  id dòng phiếu => còn bán được
     *
     * @throws ExchangeException
     */
    public function daNhanHang(Exchange $phieu, array $banLaiDuoc = []): void
    {
        DB::transaction(function () use ($phieu, $banLaiDuoc) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status !== ExchangeStatus::ChoNhan) {
                throw new ExchangeException('Phiếu này không còn ở bước chờ nhận hàng.');
            }

            $khoa->load('hangTra.orderItem');

            foreach ($khoa->hangTra as $dong) {
                $con = filter_var($banLaiDuoc[$dong->id] ?? false, FILTER_VALIDATE_BOOL);

                $dong->forceFill(['restock' => $con])->save();

                if ($con && $dong->orderItem) {
                    $this->stock->congLai($dong->orderItem, $dong->quantity);
                }
            }

            $khoa->forceFill([
                'status' => ExchangeStatus::DaNhan,
                'nhan_hang_at' => now(),
            ])->save();
        });

        $phieu->refresh();
    }

    /**
     * Hàng mới đã gửi và tiền đã xong.
     *
     * @throws ExchangeException
     */
    public function hoanTat(Exchange $phieu, string|int $daThu = 0): void
    {
        DB::transaction(function () use ($phieu, $daThu) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status !== ExchangeStatus::DaNhan) {
                throw new ExchangeException('Phải nhận hàng cũ về trước khi hoàn tất phiếu.');
            }

            $so = bcadd((string) (int) $daThu, '0', 2);

            if (bccomp($so, '0', 2) < 0) {
                throw new ExchangeException('Số tiền đã thu không thể là số âm.');
            }

            /*
             * KHÔNG THU QUÁ SỐ PHẢI BÙ.
             *
             * Thu thừa thì cửa hàng đang giữ tiền của khách mà không có
             * chứng từ nào nói vì sao — và con số đó sẽ đi thẳng vào doanh
             * thu.
             */
            if (bccomp($so, $khoa->conPhaiThu(), 2) > 0) {
                throw new ExchangeException(sprintf(
                    'Khách chỉ còn phải bù %s.',
                    \App\Services\Shop\Money::format($khoa->conPhaiThu()),
                ));
            }

            $khoa->forceFill([
                'da_thu' => bcadd((string) $khoa->da_thu, $so, 2),
                'status' => ExchangeStatus::HoanTat,
                'hoan_tat_at' => now(),
            ])->save();
        });

        $phieu->refresh();
    }

    /**
     * Huỷ phiếu và trả lại số hàng mới đang giữ.
     *
     * @throws ExchangeException
     */
    public function huy(Exchange $phieu, string $lyDo): void
    {
        $lyDo = trim($lyDo);

        if ($lyDo === '') {
            throw new ExchangeException('Huỷ phiếu phải ghi lý do — người đọc sau này cần biết vì sao.');
        }

        DB::transaction(function () use ($phieu, $lyDo) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status->daXong()) {
                throw new ExchangeException('Phiếu đã hoàn tất hoặc đã huỷ thì không huỷ lại được.');
            }

            $khoa->load('hangMoi', 'hangTra.orderItem');

            // Nhả số hàng mới đang giữ.
            foreach ($khoa->hangMoi as $dong) {
                $this->giuHang([
                    'product_id' => $dong->product_id,
                    'product_variant_id' => $dong->product_variant_id,
                    'quantity' => $dong->quantity,
                    'ten_hang' => $dong->ten_hang,
                ], 1);
            }

            /*
             * HÀNG CŨ ĐÃ CỘNG LẠI KHO THÌ PHẢI TRỪ RA.
             *
             * Huỷ sau khi đã nhận hàng nghĩa là hàng đó quay lại cho
             * khách. Bỏ qua bước này thì kho thừa đúng số hàng đã trả về,
             * và sai lệch chỉ lộ ra ở lần kiểm kê sau.
             */
            if ($khoa->status === ExchangeStatus::DaNhan) {
                foreach ($khoa->hangTra as $dong) {
                    if ($dong->restock && $dong->orderItem) {
                        $this->giuHang([
                            'product_id' => $dong->orderItem->product_id,
                            'product_variant_id' => $dong->orderItem->product_variant_id,
                            'quantity' => $dong->quantity,
                            'ten_hang' => $dong->ten_hang,
                        ], -1);
                    }
                }
            }

            $khoa->forceFill([
                'status' => ExchangeStatus::Huy,
                'huy_at' => now(),
                'ly_do_huy' => Str::limit($lyDo, 250, ''),
            ])->save();
        });

        $phieu->refresh();
    }

    /* ================= BÊN TRONG ================= */

    /**
     * @param  array<int|string, array{quantity?: mixed}>  $items
     * @return list<array<string, mixed>>
     */
    private function dongHangTra(Order $khoa, array $items): array
    {
        $ket = [];

        foreach ($items as $idDong => $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);

            if ($soLuong <= 0) {
                continue;
            }

            $item = $khoa->items->firstWhere('id', (int) $idDong);

            if (! $item) {
                throw new ExchangeException('Có dòng hàng không thuộc đơn này.');
            }

            if ($ly = $this->lyDoDongKhongDoiDuoc($item)) {
                throw new ExchangeException('"' . $item->product_name . '": ' . $ly);
            }

            $con = $this->conDoiDuoc($item);

            if ($soLuong > $con) {
                throw new ExchangeException(sprintf(
                    '"%s" chỉ còn đổi được %d.',
                    $item->product_name,
                    $con,
                ));
            }

            $ket[] = [
                'chieu' => ExchangeItem::TRA_VE,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'ten_hang' => $item->product_name . ($item->variant_name ? ' — ' . $item->variant_name : ''),
                'quantity' => $soLuong,
                // GIÁ ĐÃ TRẢ, không phải giá niêm yết hôm nay.
                'unit_price' => $item->unit_price,
                'restock' => false,
            ];
        }

        return $ket;
    }

    /**
     * @param  array<int, array{product_id?: mixed, variant_id?: mixed, quantity?: mixed}>  $items
     * @return list<array<string, mixed>>
     */
    private function dongHangMoi(array $items): array
    {
        $ket = [];

        foreach ($items as $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);
            $idSp = (int) ($dong['product_id'] ?? 0);

            if ($soLuong <= 0 || $idSp <= 0) {
                continue;
            }

            $sp = Product::find($idSp);

            if (! $sp) {
                throw new ExchangeException('Có món gửi đi không tồn tại.');
            }

            if ($sp->product_type === ProductType::Flower) {
                throw new ExchangeException(
                    '"' . $sp->name . '" là hoa tươi — không dùng làm hàng đổi được.'
                );
            }

            $idQc = (int) ($dong['variant_id'] ?? 0) ?: null;
            $qc = $idQc ? ProductVariant::where('product_id', $sp->id)->find($idQc) : null;

            if ($idQc && ! $qc) {
                throw new ExchangeException('Quy cách không thuộc sản phẩm đã chọn.');
            }

            /*
             * GIÁ HÔM NAY, LẤY TỪ NƠI CHỊU TRÁCH NHIỆM VỀ GIÁ.
             *
             * `PricingService` là chỗ duy nhất biết giá cuối sau khuyến
             * mại. Đọc thẳng `base_price` ở đây là bỏ qua mọi chương trình
             * đang chạy — khách đổi sang một món đang giảm 30% mà vẫn bị
             * tính giá niêm yết, và không ai phát hiện ra.
             */
            $gia = $qc?->price ?? $sp->price()->finalPrice;

            if ($gia === null) {
                throw new ExchangeException(
                    '"' . $sp->name . '" chưa có giá nên không tính được chênh lệch.'
                );
            }

            $ket[] = [
                'chieu' => ExchangeItem::GUI_DI,
                'order_item_id' => null,
                'product_id' => $sp->id,
                'product_variant_id' => $qc?->id,
                'ten_hang' => $sp->name . ($qc ? ' — ' . $qc->name : ''),
                'quantity' => $soLuong,
                'unit_price' => bcadd((string) $gia, '0', 2),
                'restock' => false,
            ];
        }

        return $ket;
    }

    /** @param  list<array<string, mixed>>  $dong */
    private function tong(array $dong): string
    {
        $tong = '0.00';

        foreach ($dong as $d) {
            $tong = bcadd($tong, bcmul((string) $d['unit_price'], (string) $d['quantity'], 2), 2);
        }

        return $tong;
    }

    /**
     * Giữ hoặc nhả hàng trong kho.
     *
     * `$huong` là -1 (trừ đi, giữ cho khách) hoặc 1 (cộng lại, nhả ra).
     *
     * @param  array<string, mixed>  $dong
     */
    private function giuHang(array $dong, int $huong): void
    {
        $so = (int) $dong['quantity'] * $huong;

        if ($so === 0) {
            return;
        }

        if ($dong['product_variant_id']) {
            ProductVariant::whereKey($dong['product_variant_id'])
                ->where('track_inventory', true)
                ->increment('stock_quantity', $so);

            return;
        }

        if ($dong['product_id']) {
            Product::whereKey($dong['product_id'])
                ->where('track_inventory', true)
                ->increment('stock_quantity', $so);
        }
    }

    private function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('DH-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! Exchange::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new ExchangeException('Không sinh được mã phiếu đổi, vui lòng thử lại.');
    }
}
