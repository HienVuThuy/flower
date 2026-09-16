<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Shop\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cửa hàng trả lời đánh giá của khách. */
class ReviewReplyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function review(int $rating = 2): Review
    {
        return Review::create([
            'product_id' => Product::factory()->for(Category::factory())->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => $rating,
            'comment' => 'Giao chậm hai ngày, hoa hơi héo.',
        ]);
    }

    private function reply(Review $review, ?string $text)
    {
        return $this->actingAs($this->admin())
            ->from('/admin/reviews')
            ->patch('/admin/reviews/'.$review->id.'/phan-hoi', ['admin_reply' => $text]);
    }

    #[Test]
    public function luu_duoc_phan_hoi_va_ghi_moc_thoi_gian(): void
    {
        $review = $this->review();

        $this->reply($review, 'Cửa hàng xin lỗi anh/chị. Đã đổi đơn vị giao cho tuyến này.')
            ->assertSessionHas('success');

        $sau = $review->fresh();

        $this->assertSame(
            'Cửa hàng xin lỗi anh/chị. Đã đổi đơn vị giao cho tuyến này.',
            $sau->admin_reply,
        );
        $this->assertNotNull($sau->admin_replied_at);
    }

    #[Test]
    public function sua_lai_cau_chu_KHONG_lam_moi_ngay_phan_hoi(): void
    {
        $review = $this->review();
        $this->reply($review, 'Cảm ơn anh chị.');

        $mocDau = $review->fresh()->admin_replied_at;

        Review::where('id', $review->id)->update([
            'admin_replied_at' => now()->subDays(30),
        ]);
        $mocCu = $review->fresh()->admin_replied_at;

        $this->reply($review, 'Cảm ơn anh/chị.');

        $this->assertEquals(
            $mocCu->timestamp,
            $review->fresh()->admin_replied_at->timestamp,
            'Sửa câu chữ không được đặt lại ngày phản hồi.',
        );
        $this->assertNotNull($mocDau);
    }

    #[Test]
    public function gui_o_trong_thi_xoa_phan_hoi(): void
    {
        $review = $this->review();
        $this->reply($review, 'Cảm ơn anh chị.');

        $this->reply($review, '')->assertSessionHas('success');

        $sau = $review->fresh();
        $this->assertNull($sau->admin_reply);
        $this->assertNull($sau->admin_replied_at, 'Xoá phản hồi thì mốc thời gian cũng phải mất.');
    }

    #[Test]
    public function chi_toan_khoang_trang_cung_la_xoa(): void
    {
        $review = $this->review();
        $this->reply($review, 'Cảm ơn anh chị.');

        $this->reply($review, "   \n  ");

        $this->assertNull($review->fresh()->admin_reply);
    }

    #[Test]
    public function khach_doc_duoc_phan_hoi_tren_trang_san_pham(): void
    {
        $review = $this->review();
        $this->reply($review, 'Cửa hàng đã đổi đơn vị giao cho tuyến này.');

        $this->get('/san-pham/'.$review->product->slug)
            ->assertOk()
            ->assertSee('Phản hồi từ ' . \App\Services\Shop\StoreProfile::name(), false)
            ->assertSee('Cửa hàng đã đổi đơn vị giao cho tuyến này.');
    }

    #[Test]
    public function danh_gia_bi_an_thi_phan_hoi_cung_khong_hien(): void
    {
        $review = $this->review();
        $this->reply($review, 'Cửa hàng xin lỗi anh chị.');

        $review->is_visible = false;
        $review->save();

        $this->get('/san-pham/'.$review->product->slug)
            ->assertOk()
            ->assertDontSee('Cửa hàng xin lỗi anh chị.');
    }

    #[Test]
    public function khach_thuong_khong_tu_viet_duoc_phan_hoi_cua_cua_hang(): void
    {
        $review = $this->review();

        $this->actingAs(User::factory()->create())
            ->patch('/admin/reviews/'.$review->id.'/phan-hoi', [
                'admin_reply' => 'Tôi tự khen tôi',
            ]);

        $this->assertNull($review->fresh()->admin_reply);
    }

    #[Test]
    public function phan_hoi_qua_dai_bi_tu_choi(): void
    {
        $review = $this->review();

        $this->reply($review, str_repeat('a', 1001))
            ->assertSessionHasErrors('admin_reply');

        $this->assertNull($review->fresh()->admin_reply);
    }
}
