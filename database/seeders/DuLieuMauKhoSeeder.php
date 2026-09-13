<?php

namespace Database\Seeders;

use App\Enums\ExchangeReason;
use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\ReturnReason;
use App\Enums\ReturnSettlement;
use App\Enums\StockReceiptKind;
use App\Enums\SupplierKind;
use App\Enums\UserRole;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Exchange\ExchangeService;
use App\Services\Inventory\FlowerLotService;
use App\Services\Inventory\StockReceiptService;
use App\Services\Inventory\StockUnits;
use App\Services\Inventory\SupplierReturnService;
use App\Services\Refund\RefundService;
use App\Services\Time\Gio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu cho kho, thu mua, lô hoa, trả hàng, đổi hàng, hoàn tiền.
 * ============================================================
 * CHẠY BẰNG TAY, SAU KHI ĐÃ CÓ SẢN PHẨM VÀ ĐƠN HÀNG:
 *
 *     php artisan db:seed --class=DuLieuMauKhoSeeder
 *
 * Không nằm trong DatabaseSeeder: nó dựng chứng từ KHỚP VỚI đơn hàng đã
 * có, nên chạy trên cơ sở dữ liệu trống thì không có gì để khớp.
 *
 * ============================================================
 * VÌ SAO KHÔNG ĐƯỢC BỊA CON SỐ CỐ ĐỊNH.
 *
 * Đơn hàng cũ được tạo khi chưa có kho và chưa có nhà cung cấp. Nhét
 * thêm "nhập 50 cái" vào là trang Tồn kho, Lãi gộp, Thu mua nói ba câu
 * chuyện không khớp nhau. Nên mọi con số ở đây SUY RA từ dữ liệu đã có:
 *
 *   - TỒN KHO HIỆN TẠI GIỮ NGUYÊN. Tồn đầu kỳ tính ngược sao cho
 *         đầu kỳ + nhập − trả NCC − đã bán − hàng đổi gửi đi
 *                + hàng đổi nhận về bán lại được  =  tồn hiện tại
 *   - GIÁ VỐN HOA theo tháng bám doanh thu hoa thật của tháng đó
 *     (khoảng 44%), lô đóng trong đúng tháng hoa được bán.
 *   - ĐỔI HÀNG / HOÀN TIỀN gắn vào đơn đã giao có thật, lập trong hạn.
 *
 * ============================================================
 * ĐI QUA ĐÚNG CÁC SERVICE THẬT (ghi sổ, đóng lô, trả hàng, đổi hàng,
 * hoàn tiền), với đồng hồ đặt lùi về đúng ngày chứng từ — nên mọi ràng
 * buộc nghiệp vụ được kiểm y như khi nhân viên thao tác, và nhật ký ghi
 * đúng ngày. Tất cả trong MỘT transaction: lỗi giữa chừng thì không để
 * lại nửa bộ dữ liệu.
 *
 * Mọi bản ghi mẫu mang dấu "[dữ liệu mẫu]" trong ghi chú. Chạy lại thì
 * bỏ qua.
 */
class DuLieuMauKhoSeeder extends Seeder
{
    public const DAU = '[dữ liệu mẫu]';

    public const EMAIL = 'anaorin229@gmail.com';

    /** Từ khoá trong tên sản phẩm hoa → [loại hoa, đơn vị, giá mỗi đơn vị ở vựa]. */
    private const LOAI_HOA = [
        'đào' => ['Cành đào phai', 'canh', 400000],
        'cẩm tú cầu' => ['Cẩm tú cầu xanh', 'canh', 55000],
        'tulip' => ['Tulip Hà Lan', 'bo', 210000],
        'baby' => ['Baby trắng', 'kg', 180000],
        'cúc' => ['Cúc hoạ mi', 'bo', 90000],
        'hướng dương' => ['Hướng dương', 'bo', 120000],
        'mẫu đơn' => ['Mẫu đơn đỏ', 'bo', 300000],
        'hồng' => ['Hồng Đà Lạt', 'bo', 170000],
    ];

    private const LOAI_HOA_KHAC = ['Hoa phối tổng hợp', 'bo', 100000];

    /** @var array<string, Supplier> */
    private array $ncc = [];

    public function run(): void
    {
        if (Supplier::where('note', 'like', '%' . self::DAU . '%')->exists()) {
            $this->command?->warn('Đã có dữ liệu mẫu kho — bỏ qua.');

            return;
        }

        $admin = User::where('role', UserRole::Admin->value)->orderBy('id')->first();

        if (! $admin) {
            $this->command?->warn('Chưa có tài khoản quản trị — không lập được chứng từ.');

            return;
        }

        Auth::setUser($admin);

        $homNay = Carbon::now(Gio::mui())->toDateString();

        try {
            DB::transaction(fn () => $this->dung($homNay));
        } finally {
            Carbon::setTestNow();
        }
    }

    /* ================= KỊCH BẢN ================= */

    private function dung(string $homNay): void
    {
        $donDau = Order::min('created_at');
        $dauKy = $donDau
            ? Gio::hien($donDau)->subDays(4)->toDateString()
            : Carbon::parse($homNay)->subDays(90)->toDateString();

        $ngay = fn (int $cong) => min(
            Carbon::parse($dauKy)->addDays($cong)->toDateString(),
            Carbon::parse($homNay)->subDays(3)->toDateString(),
        );

        $this->lucDo($dauKy, '08:00');
        $this->taoNhaCungCap();

        $donVi = $this->donViKho();
        $daBan = $this->daBan($dauKy, $donVi);

        // ---- 1. Đổi hàng: lập kế hoạch TRƯỚC, vì nó giới hạn số được nhập.
        [$doiHang, $guiDi, $nhanVe] = $this->keHoachDoiHang($donVi);

        // ---- 2. Phiếu nhập và trả nhà cung cấp.
        [$phieuNhap, $tongNhap] = $this->keHoachNhap($donVi, $daBan, $nhanVe, $ngay);
        [$traNcc, $tongTra] = $this->keHoachTraNcc($phieuNhap);

        // ---- 3. Tồn đầu kỳ (không cộng vào kho).
        $this->lapTonDauKy($donVi, $daBan, $tongNhap, $tongTra, $guiDi, $nhanVe, $dauKy);

        // ---- 4. Đặt tồn về số trước khi có các chứng từ làm đổi tồn, rồi
        //         cho các service thật cộng/trừ — cuối cùng về đúng số hiện tại.
        foreach ($donVi as $khoa => $u) {
            $truoc = $u['ton'] - ($tongNhap[$khoa] ?? 0) + ($tongTra[$khoa] ?? 0)
                + ($guiDi[$khoa] ?? 0) - ($nhanVe[$khoa] ?? 0);

            if ($truoc !== $u['ton']) {
                $this->datTon($u, $truoc);
            }
        }

        $this->ghiPhieuNhap($phieuNhap);
        $this->ghiTraNcc($traNcc);
        $this->ghiDoiHang($doiHang);
        $this->ghiHoanTien(collect($doiHang)->pluck('don.id')->all());
        $this->loHoa($dauKy, $homNay);

        $this->command?->info(sprintf(
            'Đã dựng dữ liệu mẫu: %d nhà cung cấp, %d phiếu nhập, %d phiếu trả NCC, %d lô hoa, %d phiếu đổi hàng.',
            count($this->ncc),
            count($phieuNhap) + 1,
            count($traNcc),
            FlowerLot::where('note', 'like', '%' . self::DAU . '%')->count(),
            count($doiHang),
        ));
    }

    /* ================= NHÀ CUNG CẤP ================= */

    private function taoNhaCungCap(): void
    {
        $ds = [
            'vuon' => ['Nhà vườn Tây Tựu', SupplierKind::NongDan, '0901234561', 'Tây Tựu, Bắc Từ Liêm, Hà Nội', 'Cây trồng chậu tự ươm; giá ổn định, cây khoẻ.'],
            'vua' => ['Vựa cây cảnh Đông Anh', SupplierKind::Vua, '0901234562', 'Đông Anh, Hà Nội', 'Giá mềm hơn nhà vườn nhưng hay có cây dập lá khi chở.'],
            'gom' => ['Xưởng gốm Bát Tràng', SupplierKind::CuaHang, '0901234563', 'Bát Tràng, Gia Lâm, Hà Nội', 'Chậu sứ, bình, đĩa lót.'],
            'vattu' => ['Cửa hàng vật tư nông nghiệp Xanh', SupplierKind::CuaHang, '0901234564', 'Hoàng Mai, Hà Nội', 'Đất, phân bón, dụng cụ, đồ trang trí.'],
            'dalat' => ['Vựa hoa Đà Lạt', SupplierKind::Vua, '0901234565', 'Phường 7, Đà Lạt, Lâm Đồng', 'Hoa gửi xe đêm, về sáng; hao ít.'],
            'quangan' => ['Chợ hoa Quảng An', SupplierKind::Cho, '0901234566', 'Quảng An, Tây Hồ, Hà Nội', 'Rẻ hơn, lấy tận nơi lúc sáng sớm; chất lượng không đều.'],
        ];

        foreach ($ds as $ma => [$ten, $loai, $sdt, $diaChi, $ghiChu]) {
            $this->ncc[$ma] = Supplier::create([
                'name' => $ten,
                'kind' => $loai,
                'phone' => $sdt,
                'email' => self::EMAIL,
                'address' => $diaChi,
                'note' => self::DAU . ' ' . $ghiChu,
                'is_active' => true,
            ]);
        }
    }

    /* ================= KHO HÀNG ĐẾM ĐƯỢC ================= */

    /** @return array<string, array<string, mixed>> khoá "productId:variantId" */
    private function donViKho(): array
    {
        $ra = [];

        foreach (app(StockUnits::class)->danhSach(true) as $u) {
            $sp = Product::find($u['product_id']);
            $gia = $u['variant_id'] ? ProductVariant::find($u['variant_id'])?->price : $sp?->base_price;

            if (! $sp || $gia === null || (float) $gia <= 0) {
                continue;
            }

            $ra[$u['value']] = $u + [
                'gia' => (float) $gia,
                'loai' => $sp->product_type,
                'co_quy_cach' => $u['variant_id'] !== null,
            ];
        }

        return $ra;
    }

    /** @return array<string, int> */
    private function daBan(string $dauKy, array $donVi): array
    {
        $ra = [];

        $dong = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', $this->luc($dauKy, '00:00'))
            ->selectRaw('order_items.product_id as p, order_items.product_variant_id as v, sum(order_items.quantity) as q')
            ->groupBy('p', 'v')
            ->get();

        foreach ($dong as $d) {
            $khoa = $d->p . ':' . ($d->v ?? '');

            if (isset($donVi[$khoa])) {
                $ra[$khoa] = (int) $d->q;
            }
        }

        return $ra;
    }

    private function giaVon(array $u, float $heSo = 1.0): string
    {
        $tiLe = $u['loai'] === ProductType::Plant ? ($u['gia'] >= 2000000 ? 0.62 : 0.5) : 0.48;

        return (string) max(1000, round($u['gia'] * $tiLe * $heSo / 1000) * 1000);
    }

    private function keHoachNhap(array $donVi, array $daBan, array $nhanVe, callable $ngay): array
    {
        $nhom = ['vuon' => [], 'vua' => [], 'gom' => [], 'vattu' => []];

        foreach ($donVi as $khoa => $u) {
            $coTheBan = $u['ton'] + ($daBan[$khoa] ?? 0);

            // Không nhập quá tồn hiện tại (trừ phần hàng đổi nhận về): tồn đầu kỳ không được âm.
            $tran = max(0, $u['ton'] - ($nhanVe[$khoa] ?? 0));

            if ($u['loai'] === ProductType::Plant) {
                $sl = min(12, intdiv($coTheBan * 6, 10), $tran);

                if ($sl < 2) {
                    continue;
                }

                $a = intdiv($sl, 2);
                $nhom['vuon'][$khoa] = ['sl' => $a, 'gia' => $this->giaVon($u)];
                $nhom['vua'][$khoa] = ['sl' => $sl - $a, 'gia' => $this->giaVon($u, 0.92)];
            } else {
                $sl = min(20, intdiv($coTheBan * 6, 10), $tran);

                if ($sl < 2) {
                    continue;
                }

                $ma = preg_match('/chậu|bình|đĩa/iu', $u['ten']) === 1 ? 'gom' : 'vattu';
                $nhom[$ma][$khoa] = ['sl' => $sl, 'gia' => $this->giaVon($u)];
            }
        }

        $ngayCua = ['vuon' => $ngay(18), 'gom' => $ngay(46), 'vattu' => $ngay(49), 'vua' => $ngay(80)];

        $phieu = [];
        $tong = [];

        foreach ($nhom as $ma => $dong) {
            if ($dong === []) {
                continue;
            }

            $phieu[$ma] = ['ncc' => $this->ncc[$ma], 'ngay' => $ngayCua[$ma], 'dong' => $dong, 'model' => null];

            foreach ($dong as $khoa => $d) {
                $tong[$khoa] = ($tong[$khoa] ?? 0) + $d['sl'];
            }
        }

        return [$phieu, $tong];
    }

    private function keHoachTraNcc(array $phieuNhap): array
    {
        $tra = [];
        $tong = [];

        // Vựa rẻ hơn mà có cây dập (không được đền); vật tư giao nhầm được hoàn tiền.
        foreach ([['vua', 4, 2, ReturnSettlement::KhongDuocGi], ['vattu', 5, 3, ReturnSettlement::HoanTien]] as [$ma, $toiThieu, $sl, $cach]) {
            if (! isset($phieuNhap[$ma])) {
                continue;
            }

            $dong = collect($phieuNhap[$ma]['dong'])->filter(fn ($d) => $d['sl'] >= $toiThieu)->sortByDesc('sl');

            if ($dong->isEmpty()) {
                continue;
            }

            $khoa = $dong->keys()->first();
            $tra[] = ['ma' => $ma, 'khoa' => $khoa, 'sl' => $sl, 'cach' => $cach];
            $tong[$khoa] = ($tong[$khoa] ?? 0) + $sl;
        }

        return [$tra, $tong];
    }

    private function lapTonDauKy(array $donVi, array $daBan, array $nhap, array $tra, array $guiDi, array $nhanVe, string $dauKy): void
    {
        $this->lucDo($dauKy, '08:30');

        $phieu = StockReceipt::create([
            'code' => app(StockReceiptService::class)->sinhMa(),
            'note' => self::DAU . ' Khai tồn có sẵn trên kệ khi bắt đầu dùng hệ thống; giá vốn ước tính theo lần mua gần nhất.',
            'received_at' => $dauKy,
        ]);
        $phieu->forceFill(['kind' => StockReceiptKind::TonDauKy])->save();
        app(StockReceiptService::class)->gan($phieu);

        foreach ($donVi as $khoa => $u) {
            $sl = $u['ton'] + ($daBan[$khoa] ?? 0) - ($nhap[$khoa] ?? 0) + ($tra[$khoa] ?? 0)
                + ($guiDi[$khoa] ?? 0) - ($nhanVe[$khoa] ?? 0);

            if ($sl <= 0) {
                continue;
            }

            $phieu->items()->create([
                'product_id' => $u['product_id'],
                'product_variant_id' => $u['variant_id'],
                'product_name' => $u['ten'],
                'variant_name' => $u['quy_cach'],
                'quantity' => $sl,
                'unit_cost' => (string) (round((float) $this->giaVon($u) * 1.03 / 1000) * 1000),
            ]);
        }

        $this->lucDo($dauKy, '17:00');
        app(StockReceiptService::class)->ghiSo($phieu->fresh('items'));
    }

    private function datTon(array $u, int $so): void
    {
        $bang = $u['variant_id'] ? 'product_variants' : 'products';
        DB::table($bang)->where('id', $u['variant_id'] ?? $u['product_id'])->update(['stock_quantity' => $so]);
    }

    private function ghiPhieuNhap(array &$phieuNhap): void
    {
        foreach ($phieuNhap as $ma => &$p) {
            $this->lucDo($p['ngay'], '09:00');

            $phieu = StockReceipt::create([
                'code' => app(StockReceiptService::class)->sinhMa(),
                'supplier_id' => $p['ncc']->id,
                'supplier' => $p['ncc']->name,
                'note' => self::DAU . ' Hàng về theo đơn đặt trước.',
                'received_at' => $p['ngay'],
            ]);
            app(StockReceiptService::class)->gan($phieu);

            foreach ($p['dong'] as $khoa => $d) {
                [$idSp, $idQc] = array_pad(explode(':', $khoa), 2, '');
                $sp = Product::find((int) $idSp);
                $qc = $idQc !== '' ? ProductVariant::find((int) $idQc) : null;

                $phieu->items()->create([
                    'product_id' => $sp->id,
                    'product_variant_id' => $qc?->id,
                    'product_name' => $sp->name,
                    'variant_name' => $qc?->name,
                    'quantity' => $d['sl'],
                    'unit_cost' => $d['gia'],
                ]);
            }

            $this->lucDo($p['ngay'], '16:00');
            app(StockReceiptService::class)->ghiSo($phieu->fresh('items'));

            $p['model'] = $phieu->fresh('items');
        }
    }

    private function ghiTraNcc(array $traNcc): void
    {
        foreach ($traNcc as $t) {
            $goc = StockReceipt::with('items')
                ->where('supplier_id', $this->ncc[$t['ma']]->id)
                ->where('kind', StockReceiptKind::NhapMoi->value)
                ->latest('id')
                ->firstOrFail();

            [$idSp, $idQc] = array_pad(explode(':', $t['khoa']), 2, '');
            $dong = $goc->items->first(fn ($i) => (int) $i->product_id === (int) $idSp
                && (string) ($i->product_variant_id ?? '') === $idQc);

            $ngay = Carbon::parse($goc->received_at)->addDays(2)->toDateString();
            $this->lucDo($ngay, '10:00');

            $phieu = app(SupplierReturnService::class)->traHangDem($goc, [$dong->id => ['quantity' => $t['sl']]], [
                'returned_at' => $ngay,
                'reason' => ReturnReason::HangHong->value,
                'settlement' => $t['cach']->value,
                'settlement_amount' => null,
                'note' => self::DAU . ($t['cach'] === ReturnSettlement::KhongDuocGi
                    ? ' Cây dập lá khi chở, vựa không nhận đền.'
                    : ' Giao nhầm quy cách, cửa hàng vật tư hoàn tiền.'),
            ]);

            $this->lucDo($ngay, '11:00');
            app(StockReceiptService::class)->ghiSo($phieu);
        }
    }

    /* ================= ĐỔI HÀNG, HOÀN TIỀN ================= */

    /** Đơn đã giao, một món, món đó là hàng đếm được không có quy cách. */
    private function donDonGian(array $donVi): \Illuminate\Support\Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->with('items.product.variants')
            ->orderByDesc('completed_at')
            ->get()
            ->filter(function (Order $o) use ($donVi) {
                $i = $o->items->first();

                return $o->items->count() === 1
                    && $i->product
                    && $i->product_variant_id === null
                    && $i->product->variants->isEmpty()
                    && isset($donVi[$i->product_id . ':']);
            })
            ->values();
    }

    private function keHoachDoiHang(array $donVi): array
    {
        $ung = $this->donDonGian($donVi);
        $doi = [];
        $guiDi = [];
        $nhanVe = [];

        // Phiếu 1: hàng hỏng, gửi lại đúng món đó; món hỏng không bán lại.
        if ($don = $ung->get(0)) {
            $khoa = $don->items->first()->product_id . ':';
            $doi[] = ['don' => $don, 'ly_do' => ExchangeReason::HangHong, 'moi' => $khoa, 'ban_lai' => false,
                'ghi_chu' => 'Cây gãy cành khi giao, gửi cây khác.'];
            $guiDi[$khoa] = ($guiDi[$khoa] ?? 0) + 1;
        }

        // Phiếu 2: khách đổi ý sang món đắt hơn; món cũ còn nguyên, bán lại được.
        if ($don = $ung->first(fn ($o) => $o->items->first()->product_id !== $ung->get(0)?->items->first()->product_id)) {
            $cu = $don->items->first();
            $khoaCu = $cu->product_id . ':';

            $moi = collect($donVi)
                ->filter(fn ($u, $k) => ! $u['co_quy_cach'] && $u['loai'] === ProductType::Plant
                    && $u['gia'] > (float) $cu->unit_price && $k !== $khoaCu && $u['ton'] >= 1)
                ->sortBy('gia')
                ->keys()
                ->first();

            if ($moi) {
                $doi[] = ['don' => $don, 'ly_do' => ExchangeReason::KhachDoiY, 'moi' => $moi, 'ban_lai' => true,
                    'ghi_chu' => 'Khách muốn đổi sang cây to hơn, bù tiền chênh lệch.'];
                $guiDi[$moi] = ($guiDi[$moi] ?? 0) + 1;
                $nhanVe[$khoaCu] = ($nhanVe[$khoaCu] ?? 0) + 1;
            }
        }

        return [$doi, $guiDi, $nhanVe];
    }

    private function ghiDoiHang(array $doiHang): void
    {
        $dv = app(ExchangeService::class);

        foreach ($doiHang as $d) {
            $don = $d['don']->fresh('items');
            $giao = Gio::hien($don->completed_at);

            $this->lucDo($giao->copy()->addDays(2)->toDateString(), '09:30');

            $phieu = $dv->tao($don, [
                'reason' => $d['ly_do']->value,
                'note' => self::DAU . ' ' . $d['ghi_chu'],
                'tra' => [$don->items->first()->id => ['quantity' => 1]],
                'moi' => [['product_id' => (int) explode(':', $d['moi'])[0], 'variant_id' => null, 'quantity' => 1]],
            ]);

            $this->lucDo($giao->copy()->addDays(4)->toDateString(), '14:00');
            $phieu->load('hangTra');
            $dv->daNhanHang($phieu, $phieu->hangTra->mapWithKeys(fn ($dong) => [$dong->id => $d['ban_lai']])->all());

            $this->lucDo($giao->copy()->addDays(5)->toDateString(), '16:00');
            $phieu->refresh();
            $dv->hoanTat($phieu, (int) round((float) $phieu->conPhaiThu()));
        }
    }

    /** @param list<int> $boQua đơn đã dùng cho đổi hàng */
    private function ghiHoanTien(array $boQua): void
    {
        $dv = app(RefundService::class);

        $daGiao = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->whereNotIn('id', $boQua)
            ->with('items.product')
            ->orderByDesc('completed_at')
            ->get();

        // Hoa dập khi giao: hoàn một phần bằng tiền mặt.
        $donHoa = $daGiao
            ->filter(fn ($o) => $o->items->contains(fn ($i) => $i->product?->product_type === ProductType::Flower))
            ->sortByDesc(fn ($o) => (float) $o->subtotal)
            ->first();

        if ($donHoa) {
            $this->lucDo(Gio::hien($donHoa->completed_at)->addDay()->toDateString(), '10:00');
            $dv->hoan($donHoa, [
                'method' => RefundMethod::Cash->value,
                'reason' => RefundReason::Damaged->value,
                'amount' => (int) (round((float) $donHoa->subtotal * 0.3 / 10000) * 10000),
                'note' => self::DAU . ' Hoa dập vài bông khi giao, khách đồng ý nhận và được hoàn một phần.',
                'items' => [],
            ]);
        }

        // Giao trễ hẹn: hoàn phí giao bằng chuyển khoản.
        $donCay = $daGiao->first(fn ($o) => $o->id !== $donHoa?->id && (float) $o->shipping_fee > 0
            && $o->items->every(fn ($i) => $i->product?->product_type !== ProductType::Flower));

        if ($donCay) {
            $ngay = Gio::hien($donCay->completed_at)->addDay();
            $this->lucDo($ngay->toDateString(), '11:00');
            $dv->hoan($donCay, [
                'method' => RefundMethod::BankTransfer->value,
                'reason' => RefundReason::Other->value,
                'amount' => (int) round((float) $donCay->shipping_fee),
                'reference' => 'FT' . $ngay->format('ymd') . str_pad((string) $donCay->id, 6, '0', STR_PAD_LEFT),
                'note' => self::DAU . ' Giao trễ hẹn một ngày, hoàn lại phí giao.',
                'items' => [],
            ]);
        }
    }

    /* ================= HOA TƯƠI ================= */

    private function loHoa(string $dauKy, string $homNay): void
    {
        $dv = app(FlowerLotService::class);

        // Doanh thu hoa đã giao, gom theo (tháng, loại hoa).
        $nhom = [];

        $dong = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('products.product_type', ProductType::Flower->value)
            ->get(['order_items.product_name', 'order_items.line_total', 'orders.created_at']);

        foreach ($dong as $d) {
            $loai = $this->loaiHoa($d->product_name);
            $ngay = Gio::hien($d->created_at)->toDateString();
            $k = substr($ngay, 0, 7) . '|' . $loai[0];

            $nhom[$k] ??= ['loai' => $loai, 'doanh_thu' => 0.0, 'dau' => $ngay, 'cuoi' => $ngay];
            $nhom[$k]['doanh_thu'] += (float) $d->line_total;
            $nhom[$k]['dau'] = min($nhom[$k]['dau'], $ngay);
            $nhom[$k]['cuoi'] = max($nhom[$k]['cuoi'], $ngay);
        }

        ksort($nhom);
        $daTraMotLo = false;

        foreach ($nhom as $g) {
            $kind = $this->kind($g['loai']);
            $mua = max($dauKy, Carbon::parse($g['dau'])->subDay()->toDateString());
            $dong = min(Carbon::parse($g['cuoi'])->addDay()->toDateString(), Carbon::parse($homNay)->subDay()->toDateString());
            $giaVon = $g['doanh_thu'] * 0.44;

            $phan = $g['doanh_thu'] >= 3000000
                ? [['dalat', 0.55, 1.0, 0.08, FlowerQuality::Tot], ['quangan', 0.45, 0.88, 0.2, FlowerQuality::TrungBinh]]
                : [['dalat', 1.0, 1.0, 0.08, FlowerQuality::Tot]];

            foreach ($phan as [$ma, $tiPhan, $heSoGia, $tiHao, $chatLuong]) {
                $donGia = round($g['loai'][2] * $heSoGia / 1000) * 1000;
                $sl = $this->soLuongHoa($giaVon * $tiPhan / $donGia, $g['loai'][1]);
                $hao = $this->soLuongHoa($sl * $tiHao, $g['loai'][1], false);

                $lo = $this->taoLo($kind, $ma, $mua, $sl, $g['loai'][1], $sl * $donGia);

                // Một lô ở chợ phải trả lại vựa mà không được đền — để trang Thu mua có câu chuyện thật.
                if ($ma === 'quangan' && ! $daTraMotLo && $sl - $hao >= 2) {
                    $this->lucDo(Carbon::parse($mua)->addDay()->toDateString(), '09:00');
                    app(SupplierReturnService::class)->traHangHoa($lo, [
                        'quantity' => max(1, (int) floor($sl * 0.1)),
                        'reason' => ReturnReason::HangHong->value,
                        'settlement' => ReturnSettlement::KhongDuocGi->value,
                        'note' => self::DAU . ' Hoa úng gốc ngay khi mở thùng.',
                    ]);
                    $daTraMotLo = true;
                }

                $this->lucDo($dong, '20:00');
                $dv->dongLo($lo->fresh(), $hao, $chatLuong->value, self::DAU . ' Dùng hết trong đợt.');
            }
        }

        // Lô đang dùng: một lô mới lấy, một lô mở quá lâu (quên đóng) — đúng như ngoài đời.
        $this->taoLo($this->kind(self::LOAI_HOA['hồng']), 'dalat', Carbon::parse($homNay)->subDays(3)->toDateString(), 4, 'bo', 4 * 170000);
        $this->taoLo($this->kind(self::LOAI_HOA['cúc']), 'quangan', Carbon::parse($homNay)->subDays(FlowerLotService::NGAY_NHAC_DONG + 2)->toDateString(), 3, 'bo', 3 * 79000);
    }

    private function loaiHoa(string $tenSanPham): array
    {
        $ten = mb_strtolower($tenSanPham);

        foreach (self::LOAI_HOA as $tuKhoa => $loai) {
            if (str_contains($ten, $tuKhoa)) {
                return $loai;
            }
        }

        return self::LOAI_HOA_KHAC;
    }

    private function kind(array $loai): FlowerKind
    {
        return FlowerKind::firstOrCreate(
            ['name' => $loai[0]],
            ['default_unit' => $loai[1], 'note' => self::DAU, 'is_active' => true],
        );
    }

    private function soLuongHoa(float $so, string $donVi, bool $itNhatMot = true): float|int
    {
        if ($donVi === 'kg') {
            return max($itNhatMot ? 0.5 : 0, round($so, 1));
        }

        return max($itNhatMot ? 1 : 0, (int) round($so));
    }

    private function taoLo(FlowerKind $kind, string $ma, string $ngay, float|int $sl, string $donVi, float $tien): FlowerLot
    {
        $this->lucDo($ngay, '06:30');

        $lo = new FlowerLot();
        $lo->forceFill([
            'code' => app(FlowerLotService::class)->sinhMa(),
            'flower_kind_id' => $kind->id,
            'supplier_id' => $this->ncc[$ma]->id,
            'supplier_name' => $this->ncc[$ma]->name,
            'purchased_at' => $ngay,
            'quantity' => (string) $sl,
            'unit' => $donVi,
            'total_cost' => (string) round($tien),
            'status' => FlowerLotStatus::DangDung,
            'note' => self::DAU,
            'created_by' => Auth::id(),
        ])->save();

        return $lo;
    }

    /* ================= ĐỒNG HỒ ================= */

    /** Một giờ địa phương của một ngày, đổi về giờ lưu. */
    private function luc(string $ngay, string $gio): Carbon
    {
        return Carbon::parse($ngay . ' ' . $gio, Gio::mui())->setTimezone((string) config('app.timezone'));
    }

    private function lucDo(string $ngay, string $gio): void
    {
        Carbon::setTestNow($this->luc($ngay, $gio));
    }
}
