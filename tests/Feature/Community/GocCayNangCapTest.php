<?php

namespace Tests\Feature\Community;

use App\Enums\UserRole;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\CommunityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Góc cây nâng cấp: nhiều ảnh / video, trả lời lồng, sửa bài và bình luận, lưu bài, báo cáo và xử lý báo cáo. */
class GocCayNangCapTest extends TestCase
{
    use RefreshDatabase;

    private function bai(User $u, array $ghiDe = []): CommunityPost
    {
        $p = new CommunityPost(array_merge(['body' => 'Cây nhà mình ra lá mới rồi.'], $ghiDe));
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

    private function video(string $ten = 'ban-cong.mp4'): UploadedFile
    {
        $hop = fn (string $loai, string $trong) => pack('N', 8 + strlen($trong)) . $loai . $trong;
        $noiDung = $hop('ftyp', 'isom0000isomiso2')
            . $hop('moov', $hop('udta', $hop("\xA9xyz", "\x00\x12\x15\xC7" . '+10.7626+106.6602/')))
            . $hop('mdat', str_repeat('0', 64));

        $tam = tempnam(sys_get_temp_dir(), 'vid') . '.mp4';
        file_put_contents($tam, $noiDung);

        return new UploadedFile($tam, $ten, 'video/mp4', null, true);
    }

    #[Test]
    public function dang_nhieu_anh_va_video_video_bi_xoa_toa_do(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Ba góc ban công nhà mình sau một tháng.',
            'media' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                $this->video(),
            ],
        ])->assertRedirect(route('shop.community.index', ['tab' => 'cua-toi']));

        $bai = CommunityPost::where('user_id', $u->id)->sole();
        $media = $bai->media;

        $this->assertSame(['image', 'image', 'video'], $media->pluck('kind')->all());
        $this->assertSame([0, 1, 2], $media->pluck('sort_order')->all());

        foreach ($media as $m) {
            Storage::disk('public')->assertExists($m->path);
        }

        $tep = Storage::disk('public')->get($media->last()->path);
        $this->assertStringNotContainsString('+10.7626+106.6602/', $tep, 'Toạ độ GPS vẫn còn trong video công khai');
    }

    #[Test]
    public function chan_qua_nhieu_tep_qua_nhieu_video_va_tep_la(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Đăng thử một loạt ảnh.',
            'media' => array_map(fn ($i) => UploadedFile::fake()->image("anh{$i}.jpg"), range(1, 11)),
        ])->assertSessionHasErrors('media');

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Ba video một lúc.',
            'media' => [$this->video('1.mp4'), $this->video('2.mp4'), $this->video('3.mp4')],
        ])->assertSessionHasErrors('media');

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Tệp không phải ảnh hay video.',
            'media' => [UploadedFile::fake()->create('ke-hoach.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('media.0');

        $this->assertDatabaseCount('community_posts', 0);
    }

    #[Test]
    public function bai_chi_co_anh_van_dang_duoc_nhung_rong_hoan_toan_thi_khong(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.store'), ['media' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertSessionHasNoErrors();

        $this->actingAs($u)->post(route('shop.community.store'), [])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('community_posts', 1);
    }

    #[Test]
    public function sua_bai_da_dang_thi_quay_lai_cho_duyet_va_xoa_duoc_anh_cu(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Bài gốc của mình.',
            'media' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]);

        $bai = CommunityPost::sole();
        $bai->forceFill(['approved_at' => now()])->save();
        $anhBo = $bai->media->first();

        $this->actingAs($u)->get(route('shop.community.edit', $bai->id))->assertOk()->assertSee('gửi duyệt lại');

        $this->actingAs($u)->patch(route('shop.community.update', $bai->id), [
            'body' => 'Bài đã sửa lại cho rõ hơn.',
            'xoa_media' => [$anhBo->id],
        ])->assertRedirect(route('shop.community.index', ['tab' => 'cua-toi']));

        $bai->refresh();
        $this->assertSame('Bài đã sửa lại cho rõ hơn.', $bai->body);
        $this->assertNull($bai->approved_at, 'Sửa bài đã đăng thì phải duyệt lại');
        $this->assertNotNull($bai->edited_at);
        $this->assertCount(1, $bai->media()->get());
        Storage::disk('public')->assertMissing($anhBo->path);

        $this->get(route('shop.community.index'))->assertDontSee('Bài đã sửa lại cho rõ hơn.');

        $this->actingAs(User::factory()->create())->patch(route('shop.community.update', $bai->id), ['body' => 'Chen ngang'])
            ->assertNotFound();
    }

    #[Test]
    public function tra_loi_long_mot_tang_va_ghi_nguoi_duoc_tra_loi(): void
    {
        $bai = $this->bai(User::factory()->create());
        $a = User::factory()->create(['name' => 'Bạn A']);
        $b = User::factory()->create(['name' => 'Bạn B']);

        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'Cây đẹp quá!']);
        $goc = CommunityComment::sole();

        $this->actingAs($b)->post(route('shop.community.comment', $bai->id), ['body' => 'Mình cũng thấy vậy.', 'tra_loi' => $goc->id]);
        $traLoi = CommunityComment::where('body', 'Mình cũng thấy vậy.')->sole();

        $this->assertSame($goc->id, $traLoi->parent_id);
        $this->assertNull($traLoi->reply_to_user_id);

        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'Đúng rồi bạn.', 'tra_loi' => $traLoi->id]);
        $traLoi2 = CommunityComment::where('body', 'Đúng rồi bạn.')->sole();

        $this->assertSame($goc->id, $traLoi2->parent_id, 'Không lồng quá một tầng');
        $this->assertSame($b->id, $traLoi2->reply_to_user_id);

        $this->get(route('shop.community.show', $bai->id))->assertOk()->assertSee('trả lời Bạn B');

        $baiKhac = $this->bai(User::factory()->create(), ['body' => 'Bài khác hẳn.']);
        $this->actingAs($a)->post(route('shop.community.comment', $baiKhac->id), ['body' => 'Chen sang bài khác', 'tra_loi' => $goc->id])
            ->assertSessionHas('error');
        $this->assertSame(3, CommunityComment::count());
    }

    #[Test]
    public function sua_binh_luan_cua_chinh_minh(): void
    {
        $bai = $this->bai(User::factory()->create());
        $a = User::factory()->create();

        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'Cây tên gì vậy bạn?']);
        $bl = CommunityComment::sole();

        $this->actingAs(User::factory()->create())->patch(route('shop.community.comment.update', $bl->id), ['body' => 'Sửa trộm'])
            ->assertNotFound();

        $this->actingAs($a)->patch(route('shop.community.comment.update', $bl->id), ['body' => 'Cây này tên gì vậy bạn?'])
            ->assertRedirect();

        $bl->refresh();
        $this->assertSame('Cây này tên gì vậy bạn?', $bl->body);
        $this->assertNotNull($bl->edited_at);
        $this->get(route('shop.community.show', $bai->id))->assertSee('đã sửa');
    }

    #[Test]
    public function go_binh_luan_goc_thi_cac_tra_loi_di_theo(): void
    {
        $bai = $this->bai(User::factory()->create());
        $a = User::factory()->create();

        $this->actingAs($a)->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận gốc.']);
        $goc = CommunityComment::sole();
        $this->actingAs(User::factory()->create())->post(route('shop.community.comment', $bai->id), ['body' => 'Trả lời nhé.', 'tra_loi' => $goc->id]);

        $this->actingAs($a)->delete(route('shop.community.comment.destroy', $goc->id))->assertRedirect();

        $this->assertDatabaseCount('community_comments', 0);
    }

    #[Test]
    public function luu_bai_va_xem_lai_o_tab_da_luu(): void
    {
        $bai = $this->bai(User::factory()->create(), ['body' => 'Bài đáng lưu lại xem sau.']);
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('shop.community.index', ['tab' => 'da-luu']))
            ->assertOk()->assertDontSee('Bài đáng lưu lại xem sau.');

        $this->actingAs($u)->postJson(route('shop.community.save', $bai->id))->assertOk()->assertJson(['luu' => true]);

        $this->actingAs($u)->get(route('shop.community.index', ['tab' => 'da-luu']))
            ->assertOk()->assertSee('Bài đáng lưu lại xem sau.');

        $this->actingAs($u)->postJson(route('shop.community.save', $bai->id))->assertOk()->assertJson(['luu' => false]);
        $this->actingAs($u)->get(route('shop.community.index', ['tab' => 'da-luu']))
            ->assertOk()->assertDontSee('Bài đáng lưu lại xem sau.');

        auth()->logout();
        $this->get(route('shop.community.index', ['tab' => 'da-luu']))->assertRedirect(route('login'));
    }

    #[Test]
    public function bao_cao_bai_mot_lan_khong_bao_bai_cua_chinh_minh(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia);
        $nguoiBao = User::factory()->create();

        $this->actingAs($tacGia)->post(route('shop.community.report'), [
            'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'spam',
        ])->assertSessionHas('error');

        $this->actingAs($nguoiBao)->post(route('shop.community.report'), [
            'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'xuc_pham', 'ghi_chu' => 'Nội dung gây gổ',
        ])->assertSessionHas('success');

        $this->actingAs($nguoiBao)->post(route('shop.community.report'), [
            'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'spam',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('community_reports', 1);
    }

    #[Test]
    public function admin_an_bai_bi_bao_cao_thi_bai_bien_mat_va_bao_cao_dong(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia, ['body' => 'Bài bị báo cáo nhiều lần.']);

        foreach (range(1, 2) as $i) {
            $this->actingAs(User::factory()->create())->post(route('shop.community.report'), [
                'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'lua_dao',
            ]);
        }

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.community.index', ['loc' => 'bao-cao']))
            ->assertOk()
            ->assertSee('data-bao-cao="post-' . $bai->id . '"', false)
            ->assertSee('2 lượt báo cáo');

        $this->actingAs($admin)->post(route('admin.community.reports.handle'), [
            'loai' => 'post', 'id' => $bai->id, 'ket_qua' => 'an', 'ly_do' => 'Nội dung sai sự thật',
        ])->assertRedirect();

        $bai->refresh();
        $this->assertNotNull($bai->hidden_at);
        $this->assertSame('Nội dung sai sự thật', $bai->hidden_reason);
        $this->assertSame(0, CommunityReport::pending()->count());

        $this->get(route('shop.community.index'))->assertDontSee('Bài bị báo cáo nhiều lần.');
        $this->get(route('shop.community.show', $bai->id))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('shop.community.like', $bai->id))->assertNotFound();

        $this->actingAs($tacGia)->get(route('shop.community.index', ['tab' => 'cua-toi']))
            ->assertSee('Bị ẩn')->assertSee('Nội dung sai sự thật');

        $this->actingAs($admin)->patch(route('admin.community.hide', $bai))->assertRedirect();
        $this->get(route('shop.community.index'))->assertSee('Bài bị báo cáo nhiều lần.');
    }

    #[Test]
    public function admin_ket_luan_khong_vi_pham_thi_noi_dung_giu_nguyen(): void
    {
        $bai = $this->bai(User::factory()->create());
        $khach = User::factory()->create();
        $this->actingAs($khach)->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận bị báo nhầm.']);
        $bl = CommunityComment::sole();

        $this->actingAs(User::factory()->create())->post(route('shop.community.report'), [
            'loai' => 'comment', 'id' => $bl->id, 'ly_do' => 'khac',
        ])->assertSessionHas('success');

        $this->actingAs($this->admin())->post(route('admin.community.reports.handle'), [
            'loai' => 'comment', 'id' => $bl->id, 'ket_qua' => 'bo-qua',
        ])->assertRedirect();

        $this->assertNull($bl->fresh()->hidden_at);
        $this->assertSame(0, CommunityReport::pending()->count());
        $this->get(route('shop.community.show', $bai->id))->assertSee('Bình luận bị báo nhầm.');
    }

    #[Test]
    public function xoa_bai_thi_xoa_luon_bao_cao_va_tep(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('shop.community.store'), [
            'body' => 'Bài sẽ bị xoá.',
            'media' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $bai = CommunityPost::sole();
        $bai->forceFill(['approved_at' => now()])->save();
        $duongDan = $bai->media()->sole()->path;

        $this->actingAs(User::factory()->create())->post(route('shop.community.report'), [
            'loai' => 'post', 'id' => $bai->id, 'ly_do' => 'spam',
        ]);

        $this->actingAs($u)->delete(route('shop.community.destroy', $bai->id))->assertRedirect();

        $this->assertDatabaseCount('community_posts', 0);
        $this->assertDatabaseCount('community_reports', 0);
        $this->assertSame(0, CommunityPostMedia::count());
        Storage::disk('public')->assertMissing($duongDan);
    }

    #[Test]
    public function bang_tin_co_o_soan_bai_menu_va_dem_binh_luan(): void
    {
        $tacGia = User::factory()->create();
        $bai = $this->bai($tacGia, ['body' => 'Bài trên bảng tin.']);
        $khach = User::factory()->create();
        $this->actingAs($khach)->post(route('shop.community.comment', $bai->id), ['body' => 'Bình luận thứ nhất.']);

        $html = $this->actingAs($khach)->get(route('shop.community.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-bs-target="#hop-dang-bai"', $html, 'Khung đăng bài nằm trong hộp thoại');
        $this->assertStringContainsString('id="hop-bao-cao"', $html);
        $this->assertStringContainsString('data-bai="' . $bai->id . '"', $html);
        $this->assertStringContainsString('Bình luận thứ nhất.', $html, 'Bảng tin hiện bình luận gần nhất');
        $this->assertStringContainsString('data-luu="' . $bai->id . '"', $html);
        $this->assertStringContainsString('data-emoji-picker', $html);
    }
}
