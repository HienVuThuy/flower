<?php

namespace Tests\Feature\Admin;

use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phiếu nhập kho.
 * ============================================================
 * VẤN ĐỀ ĐÃ SỬA: kho chỉ đổi được qua ô nhập số ở trang sản phẩm — một
 * phép GÁN ĐÈ. Sau đó không ai trả lời được "vì sao tồn là 47", "mua vào
 * bao nhiêu tiền", hay "ai vừa ghi đè của ai".
 *
 * Bất biến được canh ở đây:
 *
 *   1. Tạo phiếu KHÔNG cộng vào kho — còn một bước ghi sổ nữa.
 *   2. Ghi sổ CỘNG THÊM, không gán đè.
 *   3. Ghi sổ ĐÚNG MỘT LẦN, kể cả khi bấm hai lần.
 *   4. Cộng vào đúng chỗ giữ tồn: quy cách có kho riêng.
 *   5. Phiếu đã ghi sổ KHÔNG xoá được.
 *   6. Giá để trống là NULL ("chưa biết"), không phải 0₫.
 */
class StockReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function hang(string $ten, int $ton = 0, bool $theoDoi = true): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('100000.00')
            ->create([
                'name' => $ten,
                'status' => 'published',
                'track_inventory' => $theoDoi,
                'stock_quantity' => $ton,
            ]);
    }

    private function quyCach(Product $p, string $ten, int $ton = 0): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $p->id,
            'name' => $ten,
            'price' => '120000.00',
            'is_active' => true,
            'track_inventory' => true,
            'stock_quantity' => $ton,
        ]);
    }

    /** Lập phiếu qua đúng đường admin đi. */
    private function lapPhieu(array $dong, array $them = []): StockReceipt
    {
        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', array_merge([
                'received_at' => now()->toDateString(),
                'supplier' => 'Vườn Đà Lạt',
                'items' => $dong,
            ], $them))
            ->assertRedirect();

        return StockReceipt::latest('id')->firstOrFail();
    }

    /* ================= 1. TẠO PHIẾU CHƯA CỘNG VÀO KHO ================= */

    #[Test]
    public function tao_phieu_KHONG_cong_vao_kho_ngay(): void
    {
        /*
         * Người lập phải nhìn lại phiếu rồi mới bấm ghi sổ. Cộng luôn thì
         * một lần gõ nhầm số lượng đi thẳng vào kho, và không có bước nào
         * để phát hiện.
         */
        $p = $this->hang('Sen đá', ton: 5);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 50, 'unit_cost' => 30000],
        ]);

        $this->assertSame(StockReceiptStatus::Draft, $phieu->status);
        $this->assertNull($phieu->posted_at);
        $this->assertSame(5, $p->fresh()->stock_quantity, 'Tạo phiếu đã cộng vào kho — lẽ ra phải chờ ghi sổ.');
    }

    /* ================= 2. GHI SỔ CỘNG THÊM ================= */

    #[Test]
    public function ghi_so_CONG_THEM_chu_khong_gan_de(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT.
         *
         * Giữa lúc admin mở phiếu và lúc bấm ghi sổ, có thể đã có đơn
         * hàng trừ kho. Gán đè `stock_quantity = 50` là xoá luôn phần đã
         * bán đó — kho nói còn 50 trong khi thực tế chỉ còn 47.
         */
        $p = $this->hang('Sen đá', ton: 5);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 50, 'unit_cost' => 30000],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertRedirect();

        $this->assertSame(55, $p->fresh()->stock_quantity, 'Đang gán đè thay vì cộng thêm.');
        $this->assertSame(StockReceiptStatus::Posted, $phieu->fresh()->status);
        $this->assertNotNull($phieu->fresh()->posted_at);
    }

    #[Test]
    public function ghi_so_hai_lan_KHONG_cong_kho_hai_lan(): void
    {
        /*
         * Bấm hai lần vì trang chậm là đủ để tái hiện. Không có chốt thì
         * kho cộng gấp đôi cho một phiếu, và không có gì trên màn hình
         * cho thấy điều đó.
         */
        $p = $this->hang('Sen đá', ton: 0);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => 1000],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');
        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');

        $this->assertSame(10, $p->fresh()->stock_quantity, 'Kho bị cộng hai lần cho một phiếu.');
    }

    #[Test]
    public function so_luong_AM_tru_bot_kho(): void
    {
        /*
         * Phiếu điều chỉnh là cách sửa một lần nhập nhầm — phiếu đã ghi
         * sổ không sửa được, nên phải có đường trừ bớt ra. Chặn số âm thì
         * cách duy nhất còn lại là sửa tay cột tồn kho, đúng thứ tính
         * năng này sinh ra để thay thế.
         */
        $p = $this->hang('Sen đá', ton: 100);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => -30],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->assertSame(70, $p->fresh()->stock_quantity);
    }

    /* ================= 3. ĐÚNG CHỖ GIỮ TỒN ================= */

    #[Test]
    public function nhap_theo_quy_cach_cong_vao_kho_cua_quy_cach(): void
    {
        /*
         * Sản phẩm có quy cách thì `products.stock_quantity` không phải
         * thứ khách mua. Cộng vào đó là cộng vào một con số không ai đọc,
         * còn quy cách thì vẫn hết hàng.
         */
        $p = $this->hang('Lưỡi hổ', ton: 0);
        $suTrang = $this->quyCach($p, 'Chậu sứ trắng', ton: 2);
        $gomNau = $this->quyCach($p, 'Chậu gốm nâu', ton: 9);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':' . $suTrang->id, 'quantity' => 20, 'unit_cost' => 50000],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->assertSame(22, $suTrang->fresh()->stock_quantity);
        $this->assertSame(9, $gomNau->fresh()->stock_quantity, 'Đã cộng nhầm sang quy cách khác.');
        $this->assertSame(0, $p->fresh()->stock_quantity, 'Đã cộng vào cột tồn của sản phẩm, nơi không ai đọc.');
    }

    #[Test]
    public function hang_KHONG_theo_doi_ton_thi_khong_hien_trong_o_chon(): void
    {
        /*
         * Cho chọn thứ không theo dõi tồn là bày ra một lựa chọn mà lúc
         * ghi sổ sẽ bị từ chối — sau khi người dùng đã gõ xong cả phiếu.
         */
        $this->hang('Có theo dõi');
        $this->hang('Không theo dõi', theoDoi: false);

        $html = $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Có theo dõi', $html);
        $this->assertStringNotContainsString('Không theo dõi', $html);
    }

    #[Test]
    public function nhap_san_pham_co_quy_cach_ma_khong_chon_quy_cach_thi_bi_tu_choi(): void
    {
        $p = $this->hang('Lưỡi hổ', ton: 0);
        $this->quyCach($p, 'Chậu sứ trắng');

        // Ép một dòng "cả sản phẩm" cho hàng có quy cách.
        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');

        $this->assertSame(StockReceiptStatus::Draft, $phieu->fresh()->status);
        $this->assertSame(0, $p->fresh()->stock_quantity);
    }

    /* ================= 4. PHIẾU ĐÃ GHI SỔ LÀ BẤT BIẾN ================= */

    #[Test]
    public function phieu_da_ghi_so_KHONG_xoa_duoc(): void
    {
        /*
         * Kho đã cộng theo nó. Xoá chứng từ sau khi nó đã tác động là làm
         * sổ sách không còn khớp với thực tế, và không ai lần ra được vì
         * sao lệch.
         */
        $p = $this->hang('Sen đá');
        $phieu = $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 5]]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->actingAs($this->admin())
            ->delete('/admin/nhap-kho/' . $phieu->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stock_receipts', ['id' => $phieu->id]);
    }

    #[Test]
    public function phieu_con_nhap_thi_xoa_duoc(): void
    {
        $p = $this->hang('Sen đá', ton: 7);
        $phieu = $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 5]]);

        $this->actingAs($this->admin())
            ->delete('/admin/nhap-kho/' . $phieu->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('stock_receipts', ['id' => $phieu->id]);
        $this->assertSame(7, $p->fresh()->stock_quantity, 'Xoá phiếu nháp không được đụng tới kho.');
    }

    #[Test]
    public function phieu_rong_KHONG_ghi_so_duoc(): void
    {
        $phieu = $this->lapPhieu([
            // Dòng trống — controller lọc bỏ, phiếu còn lại 0 dòng.
            ['mat_hang' => '', 'quantity' => 0],
        ]);

        $this->assertSame(0, $phieu->items()->count());

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');
    }

    /* ================= 5. GIÁ VỐN: NULL KHÁC 0 ================= */

    #[Test]
    public function gia_de_trong_luu_NULL_chu_khong_phai_0(): void
    {
        /*
         * NULL là "không có số liệu" (hàng tặng, hàng mẫu, chưa biết
         * giá); 0 là "nhận không mất tiền". Gộp lại thì giá vốn bình quân
         * bị kéo xuống bởi những lô chưa ai điền.
         */
        $p = $this->hang('Sen đá');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => ''],
        ]);

        $this->assertNull($phieu->items->first()->unit_cost);
        $this->assertTrue($phieu->hasUnpricedItems());
    }

    #[Test]
    public function tong_tien_BO_QUA_dong_chua_dien_gia_chu_khong_tinh_bang_0(): void
    {
        $p = $this->hang('Sen đá');
        $q = $this->hang('Lan hồ điệp');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => 20000],
            ['mat_hang' => $q->id . ':', 'quantity' => 5, 'unit_cost' => ''],
        ]);

        $this->assertEqualsWithDelta(200_000, $phieu->totalCost(), 0.01);

        // Và trang phiếu phải NÓI RA chỗ thiếu, không im lặng.
        $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/' . $phieu->id)
            ->assertOk()
            ->assertSee('chưa điền giá vốn', escape: false);
    }

    /* ================= 6. GHI LẠI AI LÀM ================= */

    #[Test]
    public function phieu_ghi_lai_nguoi_lap(): void
    {
        $admin = $this->admin();
        $p = $this->hang('Sen đá');

        $this->actingAs($admin)->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => $p->id . ':', 'quantity' => 3]],
        ]);

        $phieu = StockReceipt::latest('id')->firstOrFail();

        $this->assertSame($admin->id, $phieu->created_by);
        $this->assertSame($admin->name, $phieu->created_by_name);
    }

    /* ================= 7. XÁC THỰC VÀ PHÂN QUYỀN ================= */

    #[Test]
    public function ngay_nhap_o_tuong_lai_bi_chan(): void
    {
        /*
         * Hàng chưa về mà đã ghi ngày mai là làm báo cáo nhập hàng theo
         * tháng sai. Ngày trong QUÁ KHỨ thì hợp lệ — hàng về thứ Bảy,
         * thứ Hai mới ngồi nhập máy.
         */
        $p = $this->hang('Sen đá');

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', [
                'received_at' => now()->addDay()->toDateString(),
                'items' => [['mat_hang' => $p->id . ':', 'quantity' => 5]],
            ])
            ->assertSessionHasErrors('received_at');

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', [
                'received_at' => now()->subDays(3)->toDateString(),
                'items' => [['mat_hang' => $p->id . ':', 'quantity' => 5]],
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function ma_mat_hang_bia_tren_bieu_mau_bi_bo_qua(): void
    {
        /*
         * Chuỗi "id:idQuyCach" đến từ trình duyệt. Không tra lại trong cơ
         * sở dữ liệu thì một id bịa chui thẳng vào khoá ngoại.
         */
        $p = $this->hang('Sen đá');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 5],
            ['mat_hang' => '999999:', 'quantity' => 50],
        ]);

        $this->assertSame(1, $phieu->items()->count());
        $this->assertSame($p->id, $phieu->items->first()->product_id);
    }

    #[Test]
    public function khach_va_nguoi_dung_thuong_khong_vao_duoc(): void
    {
        $this->get('/admin/nhap-kho')->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get('/admin/nhap-kho')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post('/admin/nhap-kho', ['received_at' => now()->toDateString(), 'items' => []])
            ->assertForbidden();
    }

    #[Test]
    public function ma_phieu_khong_trung_nhau(): void
    {
        $p = $this->hang('Sen đá');

        $ma = collect(range(1, 5))
            ->map(fn () => $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 1]])->code)
            ->unique();

        $this->assertCount(5, $ma);
    }
}
