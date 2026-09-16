<?php

namespace Tests\Feature\Community;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CommunityPost;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TaoAnhCoGps;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Góc cây của bạn" — mạng xã hội nhẹ. */
class CommunityPostTest extends TestCase
{
    use RefreshDatabase;
    use TaoAnhCoGps;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function bai(User $user, array $ghiDe = []): CommunityPost
    {
        $post = new CommunityPost(array_merge(['body' => 'Cây nhà mình mới ra lá.'], $ghiDe));
        $post->user_id = $user->id;

        foreach (['approved_at', 'rejected_at'] as $k) {
            if (array_key_exists($k, $ghiDe)) {
                $post->{$k} = $ghiDe[$k];
            }
        }

        $post->save();

        return $post;
    }

    #[Test]
    public function bai_chua_duyet_KHONG_hien_ra_ngoai(): void
    {
        $nguoiDang = User::factory()->create();
        $this->bai($nguoiDang, ['body' => 'Nội dung chưa được duyệt']);

        $this->get('/goc-cay')->assertOk()->assertDontSee('Nội dung chưa được duyệt');

        $this->actingAs(User::factory()->create())
            ->get('/goc-cay')
            ->assertOk()
            ->assertDontSee('Nội dung chưa được duyệt');
    }

    #[Test]
    public function chinh_nguoi_dang_van_thay_bai_cua_minh_kem_trang_thai(): void
    {
        $user = User::factory()->create();
        $this->bai($user, ['body' => 'Bài của chính tôi']);

        $this->actingAs($user)
            ->get('/goc-cay?tab=cua-toi')
            ->assertOk()
            ->assertSee('Bài của chính tôi')
            ->assertSee('Đang chờ duyệt');

        $this->actingAs($user)->get('/goc-cay')->assertOk()->assertDontSee('Bài của chính tôi');
    }

    #[Test]
    public function bai_da_duyet_thi_moi_nguoi_deu_thay(): void
    {
        $this->bai(User::factory()->create(), [
            'body' => 'Ban công nhà mình',
            'approved_at' => now(),
        ]);

        $this->get('/goc-cay')->assertOk()->assertSee('Ban công nhà mình');
    }

    #[Test]
    public function admin_duyet_va_tu_choi_duoc(): void
    {
        $post = $this->bai(User::factory()->create());

        $this->actingAs($this->admin())
            ->patch('/admin/goc-cay/' . $post->id . '/duyet')
            ->assertRedirect();

        $this->assertNotNull($post->fresh()->approved_at);

        $this->actingAs($this->admin())
            ->patch('/admin/goc-cay/' . $post->id . '/tu-choi', ['reject_reason' => 'Ảnh mờ quá'])
            ->assertRedirect();

        $post->refresh();

        $this->assertNotNull($post->rejected_at);
        $this->assertNull($post->approved_at);
        $this->assertSame('Ảnh mờ quá', $post->reject_reason);
    }

    #[Test]
    public function tu_choi_BAT_BUOC_co_ly_do(): void
    {
        $post = $this->bai(User::factory()->create());

        $this->actingAs($this->admin())
            ->patch('/admin/goc-cay/' . $post->id . '/tu-choi', ['reject_reason' => ''])
            ->assertSessionHasErrors('reject_reason');

        $this->assertNull($post->fresh()->rejected_at);
    }

    #[Test]
    public function ly_do_tu_choi_hien_lai_cho_chinh_nguoi_dang(): void
    {
        $user = User::factory()->create();
        $post = $this->bai($user);

        $this->actingAs($this->admin())
            ->patch('/admin/goc-cay/' . $post->id . '/tu-choi', ['reject_reason' => 'Ảnh không liên quan tới cây']);

        $this->actingAs($user)
            ->get('/goc-cay?tab=cua-toi')
            ->assertOk()
            ->assertSee('Ảnh không liên quan tới cây');
    }

    #[Test]
    public function anh_dang_len_bi_tuoc_metadata(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $tam = $this->anhCoGps(800, 600);

        $this->assertTrue($this->conGps($tam), 'Ảnh mẫu không có GPS — bài đang không kiểm gì.');

        $this->actingAs($user)->post('/goc-cay', [
            'body' => 'Cây monstera nhà mình sau ba tháng.',
            'media' => [new UploadedFile($tam, 'ban-cong.jpg', 'image/jpeg', null, true)],
        ])->assertRedirect();

        $post = CommunityPost::where('user_id', $user->id)->firstOrFail();

        $anh = $post->media()->sole();
        $this->assertSame('image', $anh->kind);
        Storage::disk('public')->assertExists($anh->path);

        $this->assertFalse(
            $this->conGps(Storage::disk('public')->path($anh->path)),
            'TOẠ ĐỘ GPS VẪN CÒN trong ảnh công khai — đây là địa chỉ nhà của khách.',
        );
    }

    #[Test]
    public function xoa_bai_thi_xoa_ca_anh(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/goc-cay', [
            'body' => 'Cây monstera nhà mình sau ba tháng.',
            'media' => [UploadedFile::fake()->image('ban-cong.jpg')],
        ]);

        $post = CommunityPost::where('user_id', $user->id)->firstOrFail();
        $anh = $post->media()->sole()->path;

        $this->actingAs($user)->delete('/goc-cay/' . $post->id)->assertRedirect();

        Storage::disk('public')->assertMissing($anh);
        $this->assertDatabaseCount('community_posts', 0);
    }

    #[Test]
    public function chi_gan_duoc_cay_DA_MUA(): void
    {
        $danhMuc = Category::factory()->create(['kind' => 'plant', 'is_active' => true]);
        $chuaMua = Product::factory()->for($danhMuc)->create();

        $this->actingAs(User::factory()->create())->post('/goc-cay', [
            'body' => 'Cây này đẹp lắm, mình mua ở đây.',
            'product_id' => $chuaMua->id,
        ])->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('community_posts', 0);
    }

    #[Test]
    public function nguoi_khac_khong_xoa_duoc_bai_cua_toi(): void
    {
        $toi = User::factory()->create();
        $post = $this->bai($toi);

        $this->actingAs(User::factory()->create())
            ->delete('/goc-cay/' . $post->id)
            ->assertNotFound();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id]);
    }

    #[Test]
    public function khach_vang_lai_xem_duoc_nhung_khong_dang_duoc(): void
    {
        $this->get('/goc-cay')->assertOk();

        $this->post('/goc-cay', ['body' => 'Chen vào không đăng nhập'])->assertRedirect('/login');
        $this->assertDatabaseCount('community_posts', 0);
    }

    #[Test]
    public function nguoi_dung_thuong_khong_tu_duyet_bai_cua_minh(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/goc-cay', [
            'body' => 'Bài tự duyệt thử xem sao.',
            'approved_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $post = CommunityPost::where('user_id', $user->id)->firstOrFail();

        $this->assertNull($post->approved_at, 'Người dùng vừa tự duyệt được bài của mình.');

        $this->actingAs($user)
            ->patch('/admin/goc-cay/' . $post->id . '/duyet')
            ->assertForbidden();
    }

    #[Test]
    public function xoa_tai_khoan_thi_bai_di_theo(): void
    {
        $user = User::factory()->create();
        $this->bai($user, ['approved_at' => now()]);

        $this->assertDatabaseCount('community_posts', 1);

        $user->delete();

        $this->assertDatabaseCount('community_posts', 0);
    }
}
