<?php

namespace Tests\Feature\Community;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Góc cây: thích, bình luận, trang một bài, và bài khoe cây trên trang sản phẩm.
 */
class GocCayTuongTacTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u, bool $duyet = true, array $ghiDe = []): CommunityPost
    {
        $p = new CommunityPost(array_merge(['body' => 'Cây monstera nhà mình ra thêm lá mới.'], $ghiDe));
        $p->user_id = $u->id;
        $p->approved_at = $duyet ? now() : null;
        $p->save();

        return $p;
    }

    private function daNhanDon(User $u): User
    {
        $don = Order::create([
            'order_number' => 'FP-GC-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => '100000.00', 'discount_total' => '0.00',
            'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => '100000.00',
        ]);
        $don->forceFill(['user_id' => $u->id, 'status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid])->save();

        return $u;
    }

    private function soDu(User $u): int
    {
        return app(PointLedger::class)->soDu($u);
    }

    #[Test]
    public function bai_chua_duyet_khong_xem_khong_thich_khong_binh_luan_duoc(): void
    {
        $tacGia = User::factory()->create();
        $cho = $this->bai($tacGia, duyet: false);
        $khach = $this->daNhanDon(User::factory()->create());

        $this->get(route('shop.community.show', $cho->id))->assertNotFound();
        $this->actingAs($tacGia)->get(route('shop.community.show', $cho->id))->assertNotFound();
        $this->actingAs($khach)->post(route('shop.community.like', $cho->id))->assertNotFound();
        $this->actingAs($khach)->post(route('shop.community.comment', $cho->id), ['body' => 'Đẹp quá'])->assertNotFound();

        $this->assertDatabaseCount('community_post_likes', 0);
        $this->assertDatabaseCount('community_comments', 0);
    }

    #[Test]
    public function thich_roi_bo_thich_va_so_luot_hien_dung(): void
    {
        $p = $this->bai(User::factory()->create());
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.like', $p->id))->assertRedirect();
        $html = $this->actingAs($u)->get(route('shop.community.show', $p->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-thich="' . $p->id . '"[^>]*>.*?<span data-so-thich>1</span>#s', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);

        $this->actingAs($u)->post(route('shop.community.like', $p->id));
        $this->assertDatabaseCount('community_post_likes', 0);
    }

    #[Test]
    public function luot_thich_cua_nguoi_khac_cong_diem_khong_tu_thich_khong_bom_lai(): void
    {
        $tacGia = User::factory()->create();
        $p = $this->bai($tacGia);

        $b = User::factory()->create();
        $this->actingAs($b)->post(route('shop.community.like', $p->id));
        $this->assertSame(2, $this->soDu($tacGia));

        // Bỏ thích rồi thích lại: không cộng lần hai.
        $this->actingAs($b)->post(route('shop.community.like', $p->id));
        $this->actingAs($b)->post(route('shop.community.like', $p->id));
        $this->assertSame(2, $this->soDu($tacGia));

        // Tự thích và người chưa xác thực email: không cộng.
        $this->actingAs($tacGia)->post(route('shop.community.like', $p->id));
        $chuaXacThuc = User::factory()->unverified()->create();
        app(\App\Services\Community\CommunityInteraction::class)->doiThich($chuaXacThuc, $p);

        $this->assertSame(2, $this->soDu($tacGia));
    }

    #[Test]
    public function diem_tu_luot_thich_co_tran_moi_tuan(): void
    {
        $tacGia = User::factory()->create();
        $p = $this->bai($tacGia);

        foreach (range(1, 12) as $i) {
            $this->actingAs(User::factory()->create())->post(route('shop.community.like', $p->id));
        }

        $this->assertSame(20, $this->soDu($tacGia));
        $this->assertDatabaseCount('community_post_likes', 12);
    }

    #[Test]
    public function binh_luan_danh_cho_khach_da_nhan_don(): void
    {
        $p = $this->bai(User::factory()->create());

        $moi = User::factory()->create();
        $this->actingAs($moi)->post(route('shop.community.comment', $p->id), ['body' => 'Mua ở đâu vậy bạn?'])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('community_comments', 0);
        $this->actingAs($moi)->get(route('shop.community.show', $p->id))->assertSee('data-khong-binh-luan', false);

        $khach = $this->daNhanDon(User::factory()->create(['name' => 'Chị Lan']));
        $this->actingAs($khach)->post(route('shop.community.comment', $p->id), ['body' => 'Cây nhà bạn đẹp quá!'])
            ->assertRedirect(route('shop.community.show', $p->id) . '#binh-luan');

        $this->get(route('shop.community.show', $p->id))->assertSee('Cây nhà bạn đẹp quá!')->assertSee('Chị Lan');
    }

    #[Test]
    public function cua_hang_an_binh_luan_thi_khong_hien_va_khong_dem(): void
    {
        $p = $this->bai(User::factory()->create());
        $khach = $this->daNhanDon(User::factory()->create());
        $this->actingAs($khach)->post(route('shop.community.comment', $p->id), ['body' => 'Liên hệ zalo 09xx mua cây rẻ']);
        $this->actingAs($khach)->post(route('shop.community.comment', $p->id), ['body' => 'Cây xinh ghê']);
        $rac = CommunityComment::where('body', 'like', 'Liên hệ%')->sole();

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)->get(route('admin.community.index', ['loc' => 'binh-luan']))
            ->assertOk()->assertSee('data-binh-luan-admin="' . $rac->id . '"', false);
        $this->actingAs($admin)->patch(route('admin.community.comments.toggle', $rac))->assertRedirect();

        $this->assertNotNull($rac->fresh()->hidden_at);
        $this->get(route('shop.community.show', $p->id))->assertDontSee('Liên hệ zalo')->assertSee('Cây xinh ghê');
        $this->get(route('shop.community.index'))->assertSee('data-so-binh-luan="' . $p->id . '">1 bình luận', false);

        // Nhân viên không có quyền đánh giá thì không ẩn được — nhân viên mặc định CÓ quyền này, nên kiểm khách.
        $this->actingAs($khach)->patch(route('admin.community.comments.toggle', $rac))->assertForbidden();
    }

    #[Test]
    public function chi_go_duoc_binh_luan_cua_chinh_minh(): void
    {
        $p = $this->bai(User::factory()->create());
        $a = $this->daNhanDon(User::factory()->create());
        $this->actingAs($a)->post(route('shop.community.comment', $p->id), ['body' => 'Bình luận của A']);
        $bl = CommunityComment::sole();

        $this->actingAs(User::factory()->create())->delete(route('shop.community.comment.destroy', $bl->id))->assertNotFound();
        $this->assertDatabaseHas('community_comments', ['id' => $bl->id]);

        $this->actingAs($a)->delete(route('shop.community.comment.destroy', $bl->id))->assertRedirect();
        $this->assertDatabaseMissing('community_comments', ['id' => $bl->id]);
    }

    #[Test]
    public function sap_xep_duoc_thich_nhieu_dua_bai_nhieu_luot_len_dau(): void
    {
        $tacGia = User::factory()->create();
        $nhieu = $this->bai($tacGia, ghiDe: ['body' => 'Bài nhiều lượt thích nhất']);
        $moiHon = $this->bai($tacGia, ghiDe: ['body' => 'Bài mới hơn mà ít thích']);
        $moiHon->forceFill(['approved_at' => now()->addMinute()])->save();

        foreach (range(1, 3) as $i) {
            $this->actingAs(User::factory()->create())->post(route('shop.community.like', $nhieu->id));
        }

        $html = $this->get(route('shop.community.index'))->getContent();
        $this->assertLessThan(strpos($html, 'Bài nhiều lượt thích nhất'), strpos($html, 'Bài mới hơn mà ít thích'));

        $html = $this->get(route('shop.community.index', ['sap-xep' => 'thich-nhieu']))->getContent();
        $this->assertLessThan(strpos($html, 'Bài mới hơn mà ít thích'), strpos($html, 'Bài nhiều lượt thích nhất'));
    }

    #[Test]
    public function trang_san_pham_hien_bai_khoe_da_duyet_gan_voi_san_pham(): void
    {
        $sp = Product::factory()->for(Category::factory())->create(['status' => 'active']);
        $u = User::factory()->create();

        $this->get(route('shop.products.show', $sp))->assertOk()->assertDontSee('data-khach-khoe', false);

        $this->bai($u, ghiDe: ['body' => 'Cây này về nhà mình rồi', 'product_id' => $sp->id]);
        $this->bai($u, duyet: false, ghiDe: ['body' => 'Bài chờ duyệt gắn cây', 'product_id' => $sp->id]);

        $this->get(route('shop.products.show', $sp))
            ->assertSee('Khách khoe cây này')
            ->assertSee('Cây này về nhà mình rồi')
            ->assertDontSee('Bài chờ duyệt gắn cây');
    }
}
