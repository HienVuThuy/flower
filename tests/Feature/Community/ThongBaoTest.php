<?php

namespace Tests\Feature\Community;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thông báo trong trang: có người bình luận bài của bạn, trả lời bình luận của
 * bạn, và cửa hàng duyệt / từ chối / ẩn bài của bạn.
 */
class ThongBaoTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u, array $ghiDe = []): CommunityPost
    {
        $p = new CommunityPost(array_merge(['body' => 'Cây nhà mình ra lá mới.'], $ghiDe));
        $p->user_id = $u->id;
        $p->approved_at = $ghiDe['approved_at'] ?? now();
        $p->save();

        return $p;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    /** @return \Illuminate\Support\Collection<int, UserNotification> */
    private function cua(User $u)
    {
        return UserNotification::where('user_id', $u->id)->latest('id')->get();
    }

    #[Test]
    public function co_nguoi_binh_luan_bai_cua_ban_thi_duoc_bao(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $khach = User::factory()->create(['name' => 'Chị Lan']);

        $this->actingAs($khach)->post(route('shop.community.comment', $bai->id), ['body' => 'Cây đẹp quá!']);

        $tb = $this->cua($tacGia)->sole();
        $this->assertSame(NotificationType::BinhLuanBai, $tb->type);
        $this->assertSame($khach->id, $tb->actor_id);
        $this->assertSame($bai->id, $tb->community_post_id);
        $this->assertNull($tb->read_at);

        // Tự bình luận bài của mình: không tự báo cho mình.
        $this->actingAs($tacGia)->post(route('shop.community.comment', $bai->id), ['body' => 'Cảm ơn mọi người.']);
        $this->assertCount(1, $this->cua($tacGia));
    }

    #[Test]
    public function tra_loi_bao_dung_nguoi_va_moi_nguoi_mot_lan(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận gốc của A.']);
        $goc = CommunityComment::sole();

        // B trả lời A: tác giả bài và A cùng được báo, mỗi người một cái.
        $this->actingAs($b)->post(route('shop.community.comment', $bai->id), ['body' => 'B trả lời A.', 'tra_loi' => $goc->id]);

        $this->assertCount(2, $this->cua($tacGia), 'Chủ bài: một cho bình luận gốc, một cho câu trả lời');
        $cuaA = $this->cua($a)->sole();
        $this->assertSame(NotificationType::TraLoi, $cuaA->type);
        $this->assertSame($b->id, $cuaA->actor_id);

        // A trả lời chính câu trả lời của B: B được báo, A không tự nhận.
        $traLoiB = CommunityComment::where('body', 'B trả lời A.')->sole();
        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'A nói lại.', 'tra_loi' => $traLoiB->id]);

        $this->assertCount(1, $this->cua($b));
        $this->assertCount(1, $this->cua($a), 'A không được báo về việc chính A vừa làm');
    }

    #[Test]
    public function mot_viec_chi_mot_thong_bao_cho_moi_nguoi(): void
    {
        /*
         * Tác giả bài tự bình luận, rồi người khác trả lời đúng bình luận đó:
         * tác giả vừa là chủ bài vừa là chủ bình luận gốc — vẫn chỉ nhận MỘT
         * thông báo, không phải hai cái cho cùng một câu trả lời.
         */
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);

        $this->actingAs($tacGia)->post(route('shop.community.comment', $bai->id), ['body' => 'Mình bổ sung thêm.']);
        $goc = CommunityComment::sole();

        $this->actingAs(User::factory()->create())
            ->post(route('shop.community.comment', $bai->id), ['body' => 'Cho mình hỏi thêm.', 'tra_loi' => $goc->id]);

        $this->assertCount(1, $this->cua($tacGia));
    }

    #[Test]
    public function cua_hang_duyet_tu_choi_va_an_bai_deu_bao_cho_tac_gia(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia, ['approved_at' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.community.approve', $bai));
        $this->assertSame(NotificationType::BaiDuocDuyet, $this->cua($tacGia)->first()->type);

        $this->actingAs($admin)->patch(route('admin.community.reject', $bai), ['reject_reason' => 'Ảnh mờ quá']);
        $tuChoi = $this->cua($tacGia)->first();
        $this->assertSame(NotificationType::BaiTuChoi, $tuChoi->type);
        $this->assertSame('Ảnh mờ quá', $tuChoi->note);

        $this->actingAs($admin)->patch(route('admin.community.approve', $bai));
        $this->actingAs($admin)->patch(route('admin.community.hide', $bai), ['hidden_reason' => 'Có số điện thoại rao bán']);
        $an = $this->cua($tacGia)->first();
        $this->assertSame(NotificationType::BaiBiAn, $an->type);
        $this->assertSame('Có số điện thoại rao bán', $an->note);
    }

    #[Test]
    public function an_bai_sau_bao_cao_cung_bao_cho_tac_gia(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);

        $this->actingAs(User::factory()->create())->post(route('shop.community.report'), [
            'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'spam',
        ]);

        $this->actingAs($this->admin())->post(route('admin.community.reports.handle'), [
            'loai' => 'post', 'id' => $bai->id, 'ket_qua' => 'an', 'ly_do' => 'Quảng cáo trá hình',
        ]);

        $tb = $this->cua($tacGia)->sole();
        $this->assertSame(NotificationType::BaiBiAn, $tb->type);
        $this->assertSame('Quảng cáo trá hình', $tb->note);
    }

    #[Test]
    public function chuong_dem_so_chua_doc_va_mo_thong_bao_thi_danh_dau_da_doc(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận mới.']);

        $html = $this->actingAs($tacGia)->get(route('shop.community.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-thong-bao-badge', $html);
        $this->assertMatchesRegularExpression('#data-thong-bao-badge[^>]*>1<#', $html);

        $tb = $this->cua($tacGia)->sole();
        $this->actingAs($tacGia)->get(route('shop.notifications.index'))->assertOk()->assertSee('đã bình luận bài của bạn');

        // Bấm vào: đánh dấu đã đọc rồi đi tới đúng bình luận.
        $this->actingAs($tacGia)->get(route('shop.notifications.open', $tb->id))
            ->assertRedirect(route('shop.community.show', $bai->id) . '#binh-luan-' . $tb->community_comment_id);

        $this->assertNotNull($tb->fresh()->read_at);

        $html = $this->actingAs($tacGia)->get(route('shop.community.index'))->getContent();
        $this->assertMatchesRegularExpression('#data-thong-bao-badge[^>]*hidden#', $html, 'Đọc hết thì huy hiệu ẩn đi');
    }

    #[Test]
    public function thong_bao_ve_bai_chua_duyet_dan_ve_muc_bai_cua_toi(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia, ['approved_at' => null]);

        $this->actingAs($this->admin())->patch(route('admin.community.reject', $bai), ['reject_reason' => 'Ảnh mờ']);

        $tb = $this->cua($tacGia)->sole();
        $this->actingAs($tacGia)->get(route('shop.notifications.open', $tb->id))
            ->assertRedirect(route('shop.community.index', ['tab' => 'cua-toi']));
    }

    #[Test]
    public function danh_dau_tat_ca_da_doc_va_khong_xem_duoc_thong_bao_nguoi_khac(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);

        foreach (range(1, 3) as $i) {
            $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận số ' . $i]);
        }

        $this->assertCount(3, $this->cua($tacGia));

        $this->actingAs($tacGia)->post(route('shop.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, UserNotification::where('user_id', $tacGia->id)->chuaDoc()->count());

        $cuaNguoiKhac = $this->cua($tacGia)->first();
        $this->actingAs(User::factory()->create())->get(route('shop.notifications.open', $cuaNguoiKhac->id))->assertNotFound();
    }

    #[Test]
    public function xoa_bai_thi_thong_bao_di_theo(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận sẽ mất.']);

        $this->assertCount(1, $this->cua($tacGia));

        $this->actingAs($tacGia)->delete(route('shop.community.destroy', $bai->id));

        $this->assertCount(0, $this->cua($tacGia));
    }
}
