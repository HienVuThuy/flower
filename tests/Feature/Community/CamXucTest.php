<?php

namespace Tests\Feature\Community;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cảm xúc dưới bài Góc cây: thích, yêu thích, haha, wow, buồn.
 * ============================================================
 * MỘT NGƯỜI MỘT CẢM XÚC cho một bài: đổi cảm xúc là SỬA dòng đã có, nên số đếm
 * không nhân lên và điểm thưởng cho tác giả không cộng lại.
 */
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

        // Không gửi loại nào: mặc định là "Thích".
        $this->actingAs($u)->post(route('shop.community.like', $bai->id))->assertRedirect();
        $this->assertSame('thich', $this->dong($bai, $u)->reaction);

        // Đổi sang "Yêu thích": vẫn MỘT dòng.
        $this->actingAs($u)->post(route('shop.community.like', $bai->id), ['cam_xuc' => 'yeu']);
        $this->assertSame('yeu', $this->dong($bai, $u)->reaction);
        $this->assertDatabaseCount('community_post_likes', 1);

        // Bấm lại đúng cảm xúc đang có: bỏ hẳn.
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
