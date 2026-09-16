<?php

namespace Tests\Feature\Community;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cảm xúc dưới bài Góc cây: thích, yêu thích, haha, wow, buồn. */
class CamXucTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u): CommunityPost
    {
        $p = new CommunityPost(['body' => 'Cây nhà mình ra lá mới.']);
        $p->user_id = $u->id;
        $p->approved_at = now();
        $p->save();

        return $p;
    }

    private function dong(CommunityPost $bai, User $u): ?object
    {
        return DB::table('community_post_likes')
            ->where('community_post_id', $bai->id)
            ->where('user_id', $u->id)
            ->first();
    }

    #[Test]
    public function chon_doi_va_bo_cam_xuc(): void
    {
        $bai = $this->bai(User::factory()->create());
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.like', $bai->id))->assertRedirect();
        $this->assertSame('thich', $this->dong($bai, $u)->reaction);

        $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'yeu']);
        $this->assertSame('yeu', $this->dong($bai, $u)->reaction);
        $this->assertDatabaseCount('community_post_likes', 1);

        $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'yeu']);
        $this->assertNull($this->dong($bai, $u));
    }

    #[Test]
    public function doi_cam_xuc_khong_cong_diem_lan_hai(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'haha']);
        $this->assertSame(2, app(PointLedger::class)->soDu($tacGia));

        foreach (['wow', 'buon', 'yeu'] as $loai) {
            $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => $loai]);
        }

        $this->assertSame(2, app(PointLedger::class)->soDu($tacGia), 'Đổi cảm xúc không phải là một lượt mới');
        $this->assertSame('yeu', $this->dong($bai, $u)->reaction);
    }

    #[Test]
    public function json_tra_loai_va_tom_tat_theo_loai(): void
    {
        $bai = $this->bai(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson(route('shop.community.like', $bai->id), ['cam_xuc' => 'wow'])
            ->assertOk()
            ->assertJson(['thich' => true, 'loai' => 'wow', 'so' => 1, 'tom_tat' => [['loai' => 'wow', 'so' => 1]]]);

        $this->actingAs(User::factory()->create())->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'wow']);

        $json = $this->actingAs(User::factory()->create())
            ->postJson(route('shop.community.like', $bai->id), ['cam_xuc' => 'haha'])
            ->assertOk()
            ->json();

        $this->assertSame(3, $json['so']);
        $this->assertSame([['loai' => 'wow', 'so' => 2], ['loai' => 'haha', 'so' => 1]], $json['tom_tat'], 'Loại nhiều lượt đứng trước');
    }

    #[Test]
    public function loai_cam_xuc_la_bi_tu_choi(): void
    {
        $bai = $this->bai(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'phan_no'])
            ->assertSessionHasErrors('cam_xuc');

        $this->assertDatabaseCount('community_post_likes', 0);
    }

    #[Test]
    public function cam_xuc_cho_binh_luan_doi_va_bo_duoc(): void
    {
        $bai = $this->bai(User::factory()->create());
        $nguoiBinhLuan = User::factory()->create();
        $this->actingAs($nguoiBinhLuan)->post(route('shop.community.comment', $bai->id), ['body' => 'Cây đẹp quá!']);
        $bl = \App\Models\CommunityComment::sole();

        $u = User::factory()->create();
        $dong = fn () => DB::table('community_comment_reactions')
            ->where('community_comment_id', $bl->id)->where('user_id', $u->id)->first();

        $this->actingAs($u)->postJson(route('shop.community.comment.react', $bl->id), ['cam_xuc' => 'haha'])
            ->assertOk()
            ->assertJson(['thich' => true, 'loai' => 'haha', 'so' => 1, 'tom_tat' => [['loai' => 'haha', 'so' => 1]]]);

        $this->actingAs($u)->post(route('shop.community.comment.react', $bl->id), ['cam_xuc' => 'yeu']);
        $this->assertSame('yeu', $dong()->reaction);
        $this->assertDatabaseCount('community_comment_reactions', 1);

        $this->actingAs($u)->post(route('shop.community.comment.react', $bl->id), ['cam_xuc' => 'yeu']);
        $this->assertNull($dong());

        $this->assertSame(0, app(PointLedger::class)->soDu($nguoiBinhLuan));
    }

    #[Test]
    public function khong_bay_to_cam_xuc_voi_binh_luan_da_an(): void
    {
        $bai = $this->bai(User::factory()->create());
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận sẽ bị ẩn.']);
        $bl = \App\Models\CommunityComment::sole();
        $bl->forceFill(['hidden_at' => now()])->save();

        $this->actingAs(User::factory()->create())
            ->post(route('shop.community.comment.react', $bl->id), ['cam_xuc' => 'thich'])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_reactions', 0);
    }

    #[Test]
    public function trang_mot_bai_hien_nut_cam_xuc_cua_binh_luan(): void
    {
        $bai = $this->bai(User::factory()->create());
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận có cảm xúc.']);
        $bl = \App\Models\CommunityComment::sole();

        $this->actingAs($u)->post(route('shop.community.comment.react', $bl->id), ['cam_xuc' => 'wow']);

        $html = $this->actingAs($u)->get(route('shop.community.show', $bai->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-thich="bl-' . $bl->id . '"', $html);
        $this->assertStringContainsString('data-nhan-thich>Wow<', $html);
    }

    #[Test]
    public function bang_tin_hien_bang_chon_va_cam_xuc_dang_co(): void
    {
        $bai = $this->bai(User::factory()->create());
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'yeu']);

        $html = $this->actingAs($u)->get(route('shop.community.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-nhan-thich>Yêu thích<', $html, 'Nút chính hiện cảm xúc đang chọn');
        $this->assertStringContainsString('cam-xuc--yeu', $html);
        $this->assertStringContainsString('data-so-cam-xuc>1<', $html);

        foreach (['thich', 'yeu', 'haha', 'wow', 'buon'] as $loai) {
            $this->assertStringContainsString('data-chon-cam-xuc="' . $loai . '"', $html);
        }
    }
}
