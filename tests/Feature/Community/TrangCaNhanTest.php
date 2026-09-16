<?php

namespace Tests\Feature\Community;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trang cá nhân ở Góc cây: xem lại bài của một người. */
class TrangCaNhanTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u, string $noiDung, array $ghiDe = []): CommunityPost
    {
        $p = new CommunityPost(['body' => $noiDung]);
        $p->user_id = $u->id;
        $p->forceFill($ghiDe + ['approved_at' => now()])->save();

        return $p;
    }

    #[Test]
    public function khach_chi_thay_bai_da_duyet_chu_khong_thay_bai_cho_duyet(): void
    {
        $chu = User::factory()->create(['name' => 'Chị Mai']);
        $this->bai($chu, 'Bài đã được duyệt của chị Mai.');
        $this->bai($chu, 'Bài còn chờ duyệt.', ['approved_at' => null]);
        $this->bai($chu, 'Bài bị ẩn.', ['hidden_at' => now(), 'hidden_reason' => 'Có số điện thoại']);

        $html = $this->get(route('shop.community.profile', $chu->id))->assertOk()->getContent();

        $this->assertStringContainsString('Chị Mai', $html);
        $this->assertStringContainsString('Bài đã được duyệt của chị Mai.', $html);
        $this->assertStringNotContainsString('Bài còn chờ duyệt.', $html);
        $this->assertStringNotContainsString('Bài bị ẩn.', $html);
    }

    #[Test]
    public function chinh_chu_thay_du_trang_thai_bai_cua_minh(): void
    {
        $chu = User::factory()->create();
        $this->bai($chu, 'Bài đã duyệt.');
        $this->bai($chu, 'Bài còn chờ duyệt.', ['approved_at' => null]);
        $this->bai($chu, 'Bài bị ẩn.', ['hidden_at' => now(), 'hidden_reason' => 'Vi phạm quy tắc']);

        $this->actingAs($chu)->get(route('shop.community.profile', $chu->id))
            ->assertOk()
            ->assertSee('Bài đã duyệt.')
            ->assertSee('Bài còn chờ duyệt.')
            ->assertSee('Đang chờ duyệt')
            ->assertSee('Bài bị ẩn.')
            ->assertSee('Vi phạm quy tắc')
            ->assertSee('đây là trang của bạn');
    }

    #[Test]
    public function con_so_dem_tu_bai_dang_hien(): void
    {
        $chu = User::factory()->create();
        $hien = $this->bai($chu, 'Bài đang hiện.');
        $an = $this->bai($chu, 'Bài bị ẩn.', ['hidden_at' => now()]);

        $this->actingAs(User::factory()->create())->post(route('shop.community.like', $hien->id), ['cam_xuc' => 'yeu']);
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $hien->id), ['body' => 'Bình luận của khách.']);

        DB::table('community_post_likes')->insert([
            'community_post_id' => $an->id, 'user_id' => User::factory()->create()->id,
            'reaction' => 'thich', 'created_at' => now(),
        ]);

        $html = $this->get(route('shop.community.profile', $chu->id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-so-bai>1<#', $html);
        $this->assertMatchesRegularExpression('#data-so-cam-xuc-nhan>1<#', $html);
        $this->assertMatchesRegularExpression('#data-so-binh-luan-nhan>1<#', $html);
    }

    #[Test]
    public function ten_nguoi_dang_dan_toi_trang_ca_nhan(): void
    {
        $chu = User::factory()->create(['name' => 'Anh Bảo']);
        $bai = $this->bai($chu, 'Bài của anh Bảo.');
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Hỏi chút nhé.']);

        $duong = route('shop.community.profile', $chu->id);

        $this->get(route('shop.community.index'))->assertSee($duong, false);
        $this->get(route('shop.community.show', $bai->id))->assertSee($duong, false);
    }

    #[Test]
    public function nguoi_khong_ton_tai_thi_404(): void
    {
        $this->get(route('shop.community.profile', 999999))->assertNotFound();
    }
}
