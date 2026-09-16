<?php

namespace Tests\Feature\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chủ bài tự quản lý bài của mình: tạm ẩn, ghim, khoá bình luận, ẩn bình luận.
 * ============================================================
 * RANH GIỚI PHẢI GIỮ — mỗi việc dưới đây có một test riêng:
 *
 *   - tự ẩn KHÁC bị cửa hàng ẩn: mình bật lại được, cửa hàng ẩn thì không;
 *   - ghim chỉ đổi thứ tự TRANG CÁ NHÂN, mỗi người một bài;
 *   - khoá bình luận chặn ở SERVER, không phải chỉ giấu ô nhập;
 *   - ẩn bình luận chỉ làm được trên bài của mình, và không mở lại được bình
 *     luận do cửa hàng ẩn.
 */
class QuanLyBaiCuaToiTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u, string $noiDung, array $ghiDe = []): CommunityPost
    {
        $p = new CommunityPost(['body' => $noiDung]);
        $p->user_id = $u->id;
        $p->forceFill($ghiDe + ['approved_at' => now()])->save();

        return $p;
    }

    private function binhLuan(CommunityPost $bai, User $u, string $noiDung): CommunityComment
    {
        $bl = new CommunityComment(['body' => $noiDung]);
        $bl->forceFill(['community_post_id' => $bai->id, 'user_id' => $u->id])->save();

        return $bl;
    }

    // ---------------------------------------------------------------- tạm ẩn

    #[Test]
    public function bai_chu_tu_an_thi_bien_khoi_bang_tin_va_trang_nguoi_khac(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài sắp được tạm ẩn.');

        $this->actingAs($chu)->patch(route('shop.community.owner.hide', $bai->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($bai->fresh()->author_hidden_at);

        auth()->logout();
        $this->get(route('shop.community.index'))->assertDontSee('Bài sắp được tạm ẩn.');
        $this->get(route('shop.community.show', $bai->id))->assertNotFound();

        // Người đăng nhập khác cũng vậy: ngoại lệ "xem bài mình tự ẩn" chỉ cho CHÍNH CHỦ.
        $khach = User::factory()->create();
        $this->actingAs($khach)->get(route('shop.community.show', $bai->id))->assertNotFound();
        $this->actingAs($khach)->get(route('shop.community.profile', $chu->id))
            ->assertDontSee('Bài sắp được tạm ẩn.');
    }

    #[Test]
    public function chinh_chu_van_mo_duoc_bai_minh_tam_an_de_bat_lai(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài tạm ẩn.', ['author_hidden_at' => now()]);

        $this->actingAs($chu)->get(route('shop.community.show', $bai->id))
            ->assertOk()
            ->assertSee('Bạn đang ẩn')
            ->assertSee('Hiện lại bài');

        // Trên trang cá nhân, bài đó hiện kèm nhãn trạng thái và KHÔNG mở ô bình luận
        // — nó đang không hiện với ai, bày ô bình luận ra là nói dối.
        $this->actingAs($chu)->get(route('shop.community.profile', $chu->id))
            ->assertOk()
            ->assertSee('Bạn đang ẩn')
            ->assertDontSee('Viết bình luận');

        $this->actingAs($chu)->patch(route('shop.community.owner.hide', $bai->id));

        $this->assertNull($bai->fresh()->author_hidden_at);
    }

    #[Test]
    public function chu_bai_khong_tu_mo_duoc_bai_do_cua_hang_an(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài vi phạm.', ['hidden_at' => now(), 'hidden_reason' => 'Có số điện thoại']);

        $this->actingAs($chu)->patch(route('shop.community.owner.hide', $bai->id));

        // Nút của chủ bài chỉ động tới cột của chủ bài; cột cửa hàng ẩn vẫn nguyên.
        $this->assertNotNull($bai->fresh()->hidden_at);
        $this->get(route('shop.community.show', $bai->id))->assertNotFound();
    }

    #[Test]
    public function nguoi_khac_khong_an_duoc_bai_cua_toi(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài của người khác.');

        $this->actingAs(User::factory()->create())
            ->patch(route('shop.community.owner.hide', $bai->id))
            ->assertNotFound();

        $this->assertNull($bai->fresh()->author_hidden_at);
    }

    // ------------------------------------------------------------------ ghim

    #[Test]
    public function bai_ghim_nam_dau_trang_ca_nhan_va_moi_nguoi_chi_mot_bai(): void
    {
        $chu = User::factory()->create();
        $cu = $this->bai($chu, 'Bài cũ.');
        $moi = $this->bai($chu, 'Bài mới.');

        $this->actingAs($chu)->patch(route('shop.community.owner.pin', $cu->id));

        $html = $this->get(route('shop.community.profile', $chu->id))->assertOk()->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'Bài mới.'),
            mb_strpos($html, 'Bài cũ.'),
            'Bài được ghim phải nằm trên bài mới hơn.',
        );

        // Ghim bài khác thì bài cũ tự bỏ ghim — "ghim" mà có nhiều cái thì vô nghĩa.
        $this->actingAs($chu)->patch(route('shop.community.owner.pin', $moi->id));

        $this->assertNull($cu->fresh()->pinned_at);
        $this->assertNotNull($moi->fresh()->pinned_at);

        // Bấm lần nữa là bỏ ghim.
        $this->actingAs($chu)->patch(route('shop.community.owner.pin', $moi->id));
        $this->assertNull($moi->fresh()->pinned_at);
    }

    #[Test]
    public function ghim_khong_day_bai_len_dau_bang_tin_chung(): void
    {
        $chu = User::factory()->create();
        $cu = $this->bai($chu, 'Bài cũ được ghim.', ['approved_at' => now()->subDay()]);
        $this->bai($chu, 'Bài mới nhất.');

        $this->actingAs($chu)->patch(route('shop.community.owner.pin', $cu->id));

        $html = $this->get(route('shop.community.index'))->assertOk()->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'Bài cũ được ghim.'),
            mb_strpos($html, 'Bài mới nhất.'),
            'Bảng tin chung vẫn xếp theo thời gian, ghim không được chen lên.',
        );
    }

    #[Test]
    public function khong_ghim_duoc_bai_dang_an_hoac_chua_duyet(): void
    {
        $chu = User::factory()->create();
        $an = $this->bai($chu, 'Bài đang tự ẩn.', ['author_hidden_at' => now()]);
        $cho = $this->bai($chu, 'Bài chờ duyệt.', ['approved_at' => null]);

        foreach ([$an, $cho] as $bai) {
            $this->actingAs($chu)->patch(route('shop.community.owner.pin', $bai->id))
                ->assertSessionHas('error');
            $this->assertNull($bai->fresh()->pinned_at);
        }
    }

    #[Test]
    public function an_bai_thi_go_luon_ghim(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài vừa ghim vừa ẩn.', ['pinned_at' => now()]);

        $this->actingAs($chu)->patch(route('shop.community.owner.hide', $bai->id));

        $this->assertNull($bai->fresh()->pinned_at);
    }

    // -------------------------------------------------------- khoá bình luận

    #[Test]
    public function khoa_binh_luan_chan_o_server_chu_khong_chi_giau_o_nhap(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài sắp khoá bình luận.');
        $khach = User::factory()->create();

        $this->actingAs($chu)->patch(route('shop.community.owner.lock', $bai->id));
        $this->assertNotNull($bai->fresh()->comments_locked_at);

        $this->actingAs($khach)
            ->post(route('shop.community.comment', $bai->id), ['body' => 'Cố tình gửi thẳng lên.'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('community_comments', ['body' => 'Cố tình gửi thẳng lên.']);

        $this->actingAs($khach)->get(route('shop.community.show', $bai->id))
            ->assertOk()
            ->assertSee('Chủ bài đã khoá bình luận')
            ->assertDontSee('Viết bình luận');

        // Mở lại thì bình luận được ngay.
        $this->actingAs($chu)->patch(route('shop.community.owner.lock', $bai->id));
        $this->actingAs($khach)->post(route('shop.community.comment', $bai->id), ['body' => 'Đã mở lại rồi.']);
        $this->assertDatabaseHas('community_comments', ['body' => 'Đã mở lại rồi.']);
    }

    #[Test]
    public function khoa_binh_luan_khong_xoa_binh_luan_cu(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài có bình luận cũ.');
        $this->binhLuan($bai, User::factory()->create(), 'Bình luận từ trước khi khoá.');

        $this->actingAs($chu)->patch(route('shop.community.owner.lock', $bai->id));

        $this->get(route('shop.community.show', $bai->id))->assertOk()->assertSee('Bình luận từ trước khi khoá.');
    }

    // -------------------------------------------------------- ẩn bình luận

    #[Test]
    public function chu_bai_an_binh_luan_tren_bai_cua_minh(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài của tôi.');
        $bl = $this->binhLuan($bai, User::factory()->create(), 'Bình luận khó nghe.');

        $this->actingAs($chu)->patch(route('shop.community.owner.comment-hide', $bl->id))
            ->assertSessionHas('success');

        $this->assertNotNull($bl->fresh()->hidden_at);
        $this->assertSame($chu->id, $bl->fresh()->hidden_by);

        // Người khác không thấy nữa...
        auth()->logout();
        $this->get(route('shop.community.show', $bai->id))->assertDontSee('Bình luận khó nghe.');

        // ...còn chính chủ bài vẫn thấy, kèm đường bật lại.
        $this->actingAs($chu)->get(route('shop.community.show', $bai->id))
            ->assertSee('Bình luận khó nghe.')
            ->assertSee('Bạn đang ẩn bình luận này')
            ->assertSee('Hiện lại');

        $this->actingAs($chu)->patch(route('shop.community.owner.comment-hide', $bl->id));
        $this->assertNull($bl->fresh()->hidden_at);
    }

    #[Test]
    public function chu_bai_khong_mo_lai_duoc_binh_luan_do_cua_hang_an(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài của tôi.');
        $bl = $this->binhLuan($bai, User::factory()->create(), 'Bình luận cửa hàng đã ẩn.');
        $bl->forceFill(['hidden_at' => now(), 'hidden_by' => null])->save();

        $this->actingAs($chu)->patch(route('shop.community.owner.comment-hide', $bl->id))
            ->assertSessionHas('error');

        $this->assertNotNull($bl->fresh()->hidden_at);
    }

    #[Test]
    public function khong_an_duoc_binh_luan_tren_bai_nguoi_khac(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài của người khác.');
        $bl = $this->binhLuan($bai, $chu, 'Bình luận trên bài người khác.');

        $khach = User::factory()->create();

        $this->actingAs($khach)
            ->patch(route('shop.community.owner.comment-hide', $bl->id))
            ->assertSessionHas('error');

        $this->assertNull($bl->fresh()->hidden_at);

        // Và nút đó cũng không được bày ra cho người ngoài bấm.
        $this->actingAs($khach)->get(route('shop.community.show', $bai->id))
            ->assertOk()
            ->assertDontSee('Ẩn khỏi bài');
    }

    // ------------------------------------------------------------ lối vào

    #[Test]
    public function co_nut_vao_trang_ca_nhan_cua_chinh_minh(): void
    {
        $toi = User::factory()->create();
        $duong = route('shop.community.profile', $toi->id);

        $this->actingAs($toi)->get(route('shop.community.index'))
            ->assertOk()
            ->assertSee($duong, false)
            ->assertSee('Trang cá nhân');

        // Menu tài khoản ở đầu trang cũng có, để vào được từ mọi trang.
        $this->actingAs($toi)->get('/')->assertSee($duong, false);
    }

    #[Test]
    public function khach_chua_dang_nhap_khong_dung_duoc_nut_quan_ly(): void
    {
        $chu = User::factory()->create();
        $bai = $this->bai($chu, 'Bài công khai.');

        $this->patch(route('shop.community.owner.hide', $bai->id))->assertRedirect(route('login'));
        $this->patch(route('shop.community.owner.pin', $bai->id))->assertRedirect(route('login'));
        $this->patch(route('shop.community.owner.lock', $bai->id))->assertRedirect(route('login'));

        $this->assertNull($bai->fresh()->author_hidden_at);
    }
}
