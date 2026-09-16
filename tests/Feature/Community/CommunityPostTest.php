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

/**
 * "Góc cây của bạn" — mạng xã hội nhẹ.
 * ============================================================
 * ĐÂY LÀ NỘI DUNG NGƯỜI LẠ ĐĂNG LÊN TRANG BÁN HÀNG.
 *
 * Ba luật không được nới, và cả ba đều được canh ở đây:
 *
 *   1. Chưa duyệt thì KHÔNG ai ngoài chính người đăng nhìn thấy.
 *   2. Ảnh phải bị TƯỚC METADATA — ảnh chụp bằng điện thoại mang theo
 *      toạ độ GPS nhà người chụp.
 *   3. Chỉ gắn được sản phẩm ĐÃ MUA — nếu không thì mục này thành chỗ
 *      dựng bằng chứng giả về việc mua hàng, ngay cạnh chính sản phẩm.
 */
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

    /* ================= DUYỆT ================= */

    #[Test]
    public function bai_chua_duyet_KHONG_hien_ra_ngoai(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT.
         *
         * Hiện ngay rồi gỡ sau nghĩa là trong khoảng giữa hai việc đó,
         * trang của cửa hàng đang hiển thị bất kỳ thứ gì ai đó vừa gửi
         * lên — kể cả lúc 2 giờ sáng khi không ai trực.
         */
        $nguoiDang = User::factory()->create();
        $this->bai($nguoiDang, ['body' => 'Nội dung chưa được duyệt']);

        // Khách vãng lai không thấy.
        $this->get('/goc-cay')->assertOk()->assertDontSee('Nội dung chưa được duyệt');

        // Người dùng khác cũng không thấy.
        $this->actingAs(User::factory()->create())
            ->get('/goc-cay')
            ->assertOk()
            ->assertDontSee('Nội dung chưa được duyệt');
    }

    #[Test]
    public function chinh_nguoi_dang_van_thay_bai_cua_minh_kem_trang_thai(): void
    {
        /*
         * Gửi xong mà màn hình không đổi gì thì họ tưởng hỏng và gửi lại
         * — rồi admin có ba bài giống hệt để duyệt.
         */
        $user = User::factory()->create();
        $this->bai($user, ['body' => 'Bài của chính tôi']);

        // Bài chưa duyệt nằm ở mục "Bài của tôi" — chỗ trang đưa họ tới ngay sau khi gửi.
        $this->actingAs($user)
            ->get('/goc-cay?tab=cua-toi')
            ->assertOk()
            ->assertSee('Bài của chính tôi')
            ->assertSee('Đang chờ duyệt');

        // Và KHÔNG lẫn vào bảng tin chung.
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
        // Duyệt thì xoá dấu từ chối và ngược lại — một trạng thái phải là
        // MỘT trạng thái, không phải hai dấu chồng nhau.
        $this->assertNull($post->approved_at);
        $this->assertSame('Ảnh mờ quá', $post->reject_reason);
    }

    #[Test]
    public function tu_choi_BAT_BUOC_co_ly_do(): void
    {
        /*
         * Từ chối im lặng thì khách đăng lại y hệt, rồi lại bị từ chối,
         * và họ kết luận là trang bị hỏng.
         */
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

    /* ================= ẢNH ================= */

    #[Test]
    public function anh_dang_len_bi_tuoc_metadata(): void
    {
        /*
         * Ảnh ở đây hiện CÔNG KHAI. Khách chụp cây trên ban công nhà mình
         * bằng điện thoại; tệp đó mang theo toạ độ GPS chính xác tới vài
         * mét.
         *
         * Kiểm bằng cách khẳng định ảnh đi qua ImageStore: đó là cửa duy
         * nhất có tước metadata. Gọi thẳng `$file->store()` là bỏ qua
         * đúng lớp bảo vệ đó.
         */
        Storage::fake('public');

        $user = User::factory()->create();

        $tam = $this->anhCoGps(800, 600);

        // BẢO HIỂM CHO CHÍNH BÀI NÀY: ảnh mẫu phải THẬT SỰ có GPS, nếu
        // không thì phép khẳng định bên dưới xanh một cách vô nghĩa.
        $this->assertTrue($this->conGps($tam), 'Ảnh mẫu không có GPS — bài đang không kiểm gì.');

        $this->actingAs($user)->post('/goc-cay', [
            'body' => 'Cây monstera nhà mình sau ba tháng.',
            'media' => [new UploadedFile($tam, 'ban-cong.jpg', 'image/jpeg', null, true)],
        ])->assertRedirect();

        $post = CommunityPost::where('user_id', $user->id)->firstOrFail();

        $anh = $post->media()->sole();
        $this->assertSame('image', $anh->kind);
        Storage::disk('public')->assertExists($anh->path);

        // Đọc bằng exif_read_data THÔ, không chỉ bằng hàm kiểm của dự án.
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

    /* ================= QUYỀN ================= */

    #[Test]
    public function chi_gan_duoc_cay_DA_MUA(): void
    {
        /*
         * Cho gắn sản phẩm bất kỳ thì mục này thành chỗ dựng bằng chứng
         * giả về việc đã mua hàng — ngay cạnh chính sản phẩm đó. Cùng
         * luật với nhật ký (QĐ-129).
         */
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

        // 404 chứ không 403 — 403 xác nhận bài đó có tồn tại (QĐ-123).
        $this->actingAs(User::factory()->create())
            ->delete('/goc-cay/' . $post->id)
            ->assertNotFound();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id]);
    }

    #[Test]
    public function khach_vang_lai_xem_duoc_nhung_khong_dang_duoc(): void
    {
        // Xem thì mở: cả điểm của mục này là khách chưa mua nhìn thấy cây
        // người khác đã mua.
        $this->get('/goc-cay')->assertOk();

        $this->post('/goc-cay', ['body' => 'Chen vào không đăng nhập'])->assertRedirect('/login');
        $this->assertDatabaseCount('community_posts', 0);
    }

    #[Test]
    public function nguoi_dung_thuong_khong_tu_duyet_bai_cua_minh(): void
    {
        /*
         * HAI LỚP CHẶN, không phải một.
         *
         *   1. Controller dựng model bằng một MẢNG TƯỜNG MINH, không
         *      truyền `$request->all()`.
         *   2. `approved_at` cố ý không nằm trong $fillable.
         *
         * Đã đo bằng đột biến: phá riêng lớp nào thì bài vẫn xanh — lớp
         * còn lại giữ được. Chỉ khi phá CẢ HAI nó mới đỏ. Ghi rõ ra đây
         * vì người đọc dễ tưởng bài này canh đúng một dòng $fillable.
         *
         * Cùng kiểu phòng thủ hai lớp đã dùng cho cột JSON của nhật ký
         * (QĐ-148).
         */
        $user = User::factory()->create();

        $this->actingAs($user)->post('/goc-cay', [
            'body' => 'Bài tự duyệt thử xem sao.',
            'approved_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $post = CommunityPost::where('user_id', $user->id)->firstOrFail();

        $this->assertNull($post->approved_at, 'Người dùng vừa tự duyệt được bài của mình.');

        // Và họ cũng không gọi được route duyệt của admin.
        $this->actingAs($user)
            ->patch('/admin/goc-cay/' . $post->id . '/duyet')
            ->assertForbidden();
    }

    #[Test]
    public function xoa_tai_khoan_thi_bai_di_theo(): void
    {
        /*
         * Khác với bài blog (là tài sản của cửa hàng): bài ở đây là nội
         * dung cá nhân của khách kèm ảnh nhà họ. Người yêu cầu xoá tài
         * khoản đang yêu cầu xoá cả thứ đó.
         */
        $user = User::factory()->create();
        $this->bai($user, ['approved_at' => now()]);

        $this->assertDatabaseCount('community_posts', 1);

        $user->delete();

        $this->assertDatabaseCount('community_posts', 0);
    }
}
