<?php

namespace Tests\Feature;

use App\Enums\ExchangeStatus;
use App\Enums\FlowerLotStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\RefundStatus;
use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Exchange;
use App\Models\ExchangeItem;
use App\Models\FlowerLot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Admin\WorkQueue;
use Database\Seeders\DuLieuMauKhoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dữ liệu mẫu kho phải KHỚP với đơn hàng đã có. */
class DuLieuMauKhoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $sp = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-13 03:00:00');

        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();

        $dm = Category::factory()->create();

        foreach ([
            'kim' => ['Kim ngân thử', ProductType::Plant, '320000.00', 10, true],
            'lan' => ['Lan hồ điệp thử', ProductType::Plant, '650000.00', 8, true],
            'chau' => ['Chậu sứ thử', ProductType::Other, '120000.00', 30, true],
            'hong' => ['Hộp hoa hồng thử', ProductType::Flower, '690000.00', 14, true],
            'dao' => ['Cành đào thử', ProductType::Flower, '950000.00', 0, false],
        ] as $ma => [$ten, $loai, $gia, $ton, $theoDoi]) {
            $p = Product::factory()->for($dm)->price($gia)->stock($ton)->create(['name' => $ten]);
            $p->forceFill(['product_type' => $loai, 'track_inventory' => $theoDoi, 'stock_quantity' => $ton])->save();
            $this->sp[$ma] = $p->fresh();
        }

        $this->don('kim', 2, '2026-08-10');
        $this->don('kim', 1, '2026-08-20');
        $this->don('chau', 2, '2026-08-05');
        $this->don('hong', 6, '2026-08-25');
        $this->don('dao', 4, '2026-07-04');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function don(string $ma, int $sl, string $ngayGiao): Order
    {
        $p = $this->sp[$ma];
        $tien = bcmul((string) $p->base_price, (string) $sl, 2);

        $don = Order::create([
            'order_number' => 'FP-MAU-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => bcadd($tien, '25000.00', 2),
        ]);

        $giao = Carbon::parse($ngayGiao . ' 06:00:00');
        $don->forceFill([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'completed_at' => $giao,
            'created_at' => $giao->copy()->subDays(2),
        ])->save();

        $don->items()->create([
            'product_id' => $p->id,
            'product_name' => $p->name,
            'quantity' => $sl,
            'unit_base_price' => $p->base_price,
            'unit_price' => $p->base_price,
            'line_total' => $tien,
        ]);

        return $don;
    }

    private function chay(): void
    {
        (new DuLieuMauKhoSeeder())->run();
    }

    #[Test]
    public function ton_kho_hien_tai_KHONG_doi(): void
    {
        $this->chay();

        $this->assertSame(10, (int) $this->sp['kim']->fresh()->stock_quantity);
        $this->assertSame(8, (int) $this->sp['lan']->fresh()->stock_quantity);
        $this->assertSame(30, (int) $this->sp['chau']->fresh()->stock_quantity);
    }

    #[Test]
    public function dang_thuc_ton_kho_dung_cho_tung_mat_hang(): void
    {
        $this->chay();

        foreach (['kim', 'lan', 'chau'] as $ma) {
            $p = $this->sp[$ma];

            $phieu = fn (StockReceiptKind $loai) => (int) StockReceiptItem::query()
                ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
                ->where('stock_receipts.status', StockReceiptStatus::Posted->value)
                ->where('stock_receipts.kind', $loai->value)
                ->where('stock_receipt_items.product_id', $p->id)
                ->sum('stock_receipt_items.quantity');

            $daBan = (int) OrderItem::where('product_id', $p->id)->sum('quantity');

            $guiDi = (int) ExchangeItem::where('chieu', ExchangeItem::GUI_DI)->where('product_id', $p->id)->sum('quantity');

            $nhanVe = (int) ExchangeItem::query()
                ->join('order_items', 'order_items.id', '=', 'exchange_items.order_item_id')
                ->where('exchange_items.restock', true)
                ->where('order_items.product_id', $p->id)
                ->sum('exchange_items.quantity');

            $tinh = $phieu(StockReceiptKind::TonDauKy) + $phieu(StockReceiptKind::NhapMoi) + $phieu(StockReceiptKind::TraNcc)
                - $daBan - $guiDi + $nhanVe;

            $this->assertSame((int) $p->fresh()->stock_quantity, $tinh, $p->name . ': sổ sách không khớp tồn');
        }
    }

    #[Test]
    public function hoa_tuoi_KHONG_vao_phieu_nhap_ma_vao_lo(): void
    {
        $this->chay();

        $this->assertSame(0, StockReceiptItem::whereIn('product_id', [$this->sp['hong']->id, $this->sp['dao']->id])->count());
        $this->assertGreaterThan(0, FlowerLot::query()->daDong()->count());
    }

    #[Test]
    public function gia_von_hoa_bam_doanh_thu_hoa(): void
    {
        $this->chay();

        $doanhThu = (float) OrderItem::whereIn('product_id', [$this->sp['hong']->id, $this->sp['dao']->id])->sum('line_total');
        $giaVon = FlowerLot::query()->daDong()->get()->sum(fn ($l) => (float) $l->tienThucTe());

        $tiLe = $giaVon / $doanhThu;
        $this->assertGreaterThan(0.3, $tiLe, 'Giá vốn hoa thấp vô lý');
        $this->assertLessThan(0.6, $tiLe, 'Giá vốn hoa cao vô lý');
    }

    #[Test]
    public function co_nhieu_nguon_de_so_gia_va_mot_lo_quen_dong(): void
    {
        $this->chay();

        $this->assertSame(6, Supplier::count());
        $this->assertSame(6, Supplier::where('email', DuLieuMauKhoSeeder::EMAIL)->count());

        $coLoaiHaiNguon = FlowerLot::query()->daDong()
            ->selectRaw('flower_kind_id, unit, count(distinct supplier_id) as so_nguon')
            ->groupBy('flower_kind_id', 'unit')
            ->get()
            ->contains(fn ($r) => (int) $r->so_nguon >= 2);

        $this->assertTrue($coLoaiHaiNguon, 'Phải có một loại hoa mua từ hai nguồn để so giá');

        $this->assertTrue(collect(app(WorkQueue::class)->items())->contains(fn ($v) => str_contains($v['label'], 'lô hoa mở quá')));
    }

    #[Test]
    public function doi_hang_va_hoan_tien_di_qua_dung_quy_trinh(): void
    {
        $this->chay();

        $this->assertSame(2, Exchange::where('status', ExchangeStatus::HoanTat->value)->count());
        $this->assertGreaterThanOrEqual(1, Refund::where('status', RefundStatus::Completed->value)->count());
        $this->assertSame(1, StockReceipt::where('kind', StockReceiptKind::TonDauKy->value)->count());
        $this->assertGreaterThanOrEqual(1, StockReceipt::where('kind', StockReceiptKind::TraNcc->value)->where('status', StockReceiptStatus::Posted->value)->count());
    }

    #[Test]
    public function chay_lai_KHONG_tao_trung(): void
    {
        $this->chay();
        $truoc = [Supplier::count(), StockReceipt::count(), FlowerLot::count(), Exchange::count(), Refund::count()];

        $this->chay();

        $this->assertSame($truoc, [Supplier::count(), StockReceipt::count(), FlowerLot::count(), Exchange::count(), Refund::count()]);
        $this->assertSame(10, (int) $this->sp['kim']->fresh()->stock_quantity);
    }

    #[Test]
    public function cac_trang_quan_tri_mo_duoc_voi_du_lieu_mau(): void
    {
        $this->chay();

        $this->actingAs($this->admin);

        foreach ([
            route('admin.analytics.purchasing', ['ky' => 'all']),
            route('admin.analytics.profit', ['ky' => 'all']),
            route('admin.stock-receipts.index'),
            route('admin.flower-lots.index'),
            route('admin.supplier-returns.index'),
            route('admin.exchanges.index'),
            route('admin.refunds.index'),
            route('admin.suppliers.index'),
            route('admin.inventory.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
