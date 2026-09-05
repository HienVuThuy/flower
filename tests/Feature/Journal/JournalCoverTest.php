<?php

namespace Tests\Feature\Journal;

use App\Enums\JournalKind;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ảnh bìa sổ — ba trạng thái, không phải hai.
 * ============================================================
 * Ô tải tệp để trống có thể nghĩa là "không đổi gì" HOẶC "bỏ ảnh đi" —
 * trình duyệt gửi lên y hệt nhau. Không phân biệt được hai ý đó thì người
 * dùng KHÔNG BAO GIỜ gỡ được ảnh bìa đã lỡ chọn: mỗi lần lưu là ảnh cũ
 * lại quay về, và họ sẽ tưởng trang bị hỏng.
 */
class JournalCoverTest extends TestCase
{
    use RefreshDatabase;

    private function so(User $user): Journal
    {
        $so = new Journal(['title' => 'Sổ có bìa', 'kind' => JournalKind::Growth]);
        $so->user_id = $user->id;
        $so->save();

        return $so;
    }

    private function anh(string $ten = 'bia.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($ten, 800, 600);
    }

    #[Test]
    public function tai_len_duoc_anh_bia_khi_tao_so(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/nhat-ky', [
            'title' => 'Sổ mới',
            'kind' => 'growth',
            'cover_image' => $this->anh(),
        ])->assertRedirect();

        $so = Journal::where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($so->cover_image, 'Ảnh bìa không được lưu — kiểm enctype của biểu mẫu.');
        Storage::disk('public')->assertExists($so->cover_image);
    }

    #[Test]
    public function luu_lai_ma_khong_chon_tep_thi_GIU_NGUYEN_anh_cu(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa',
            'kind' => 'growth',
            'cover_image' => $this->anh(),
        ]);

        $cu = $so->fresh()->cover_image;
        $this->assertNotNull($cu);

        // Lưu lần nữa, lần này không đính tệp nào.
        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Đổi tên thôi',
            'kind' => 'growth',
        ])->assertRedirect();

        $this->assertSame($cu, $so->fresh()->cover_image, 'Sửa tên sổ đã làm mất ảnh bìa.');
    }

    #[Test]
    public function tich_bo_anh_bia_thi_go_that_va_xoa_ca_tep(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa',
            'kind' => 'growth',
            'cover_image' => $this->anh(),
        ]);

        $cu = $so->fresh()->cover_image;
        Storage::disk('public')->assertExists($cu);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa',
            'kind' => 'growth',
            'remove_cover' => '1',
        ])->assertRedirect();

        $this->assertNull($so->fresh()->cover_image);

        // Gỡ khỏi cơ sở dữ liệu mà để tệp nằm lại là giữ đúng thứ người
        // dùng vừa yêu cầu xoá.
        Storage::disk('public')->assertMissing($cu);
    }

    #[Test]
    public function thay_anh_bia_thi_xoa_tep_cu(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa', 'kind' => 'growth', 'cover_image' => $this->anh('mot.jpg'),
        ]);
        $cu = $so->fresh()->cover_image;

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa', 'kind' => 'growth', 'cover_image' => $this->anh('hai.jpg'),
        ]);
        $moi = $so->fresh()->cover_image;

        $this->assertNotSame($cu, $moi);
        Storage::disk('public')->assertMissing($cu);
        Storage::disk('public')->assertExists($moi);
    }

    #[Test]
    public function xoa_so_thi_anh_bia_va_anh_tung_trang_deu_bi_xoa(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa', 'kind' => 'growth', 'cover_image' => $this->anh(),
        ]);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'photo' => $this->anh('trang.jpg'),
        ]);

        $bia = $so->fresh()->cover_image;
        $trang = $so->entries()->firstOrFail()->photo;

        Storage::disk('public')->assertExists($bia);
        Storage::disk('public')->assertExists($trang);

        $this->actingAs($user)->delete('/nhat-ky/' . $so->id)->assertRedirect();

        Storage::disk('public')->assertMissing($bia);
        Storage::disk('public')->assertMissing($trang);
    }

    #[Test]
    public function khong_nhan_tep_khong_phai_anh(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->put('/nhat-ky/' . $so->id, [
            'title' => 'Sổ có bìa',
            'kind' => 'growth',
            'cover_image' => UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('cover_image');

        $this->assertNull($so->fresh()->cover_image);
    }
}
