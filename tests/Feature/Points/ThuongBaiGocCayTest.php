<?php

namespace Tests\Feature\Points;

use App\Enums\PointReason;
use App\Enums\UserRole;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Points\PointLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thưởng điểm cho bài Góc cây: theo chất lượng (người duyệt chấm) và tần suất (trần mỗi tuần).
 */
class ThuongBaiGocCayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Thứ Hai 14/09/2026, 10:00 giờ Việt Nam.
        $this->travelTo(Carbon::parse('2026-09-14 03:00:00', 'UTC'));

        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
    }

    private function bai(User $u, array $ghiDe = [], bool $coAnh = false): CommunityPost
    {
        $p = new CommunityPost(array_merge(['body' => 'Cây monstera nhà mình ra thêm bốn lá mới.'], $ghiDe));
        $p->user_id = $u->id;
        $p->save();

        if ($coAnh) {
            (new \App\Models\CommunityPostMedia())->forceFill([
                'community_post_id' => $p->id, 'kind' => 'image', 'path' => 'community/anh-' . $p->id . '.jpg', 'sort_order' => 0,
            ])->save();
        }

        return $p;
    }

    private function duyet(CommunityPost $p, bool $noiBat = false)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.community.index'))
            ->patch(route('admin.community.approve', $p), $noiBat ? ['noi_bat' => '1'] : []);
    }

    private function soDu(User $u): int
    {
        return app(PointLedger::class)->soDu($u);
    }

    #[Test]
    public function diem_theo_chat_luong_co_ban_co_anh_noi_bat(): void
    {
        $u = User::factory()->create();

        $this->duyet($this->bai($u))->assertSessionHas('success', fn ($m) => str_contains($m, 'Cộng 20 điểm'));
        $this->assertSame(20, $this->soDu($u));

        $this->duyet($this->bai($u, coAnh: true));
        $this->assertSame(50, $this->soDu($u), 'Có ảnh: 20 + 10');

        $this->duyet($this->bai($u, coAnh: true), noiBat: true);
        $this->assertSame(110, $this->soDu($u), 'Có ảnh và nổi bật: 20 + 10 + 30');
    }

    #[Test]
    public function tu_choi_roi_duyet_lai_khong_cong_hai_lan(): void
    {
        $u = User::factory()->create();
        $p = $this->bai($u);

        $this->duyet($p);
        $this->actingAs($this->admin)->patch(route('admin.community.reject', $p), ['reject_reason' => 'Ảnh mờ']);
        $this->duyet($p, noiBat: true);

        $this->assertSame(20, $this->soDu($u));
    }

    #[Test]
    public function qua_tran_tuan_van_duyet_nhung_khong_cong_va_noi_ro(): void
    {
        $u = User::factory()->create();

        // Điểm chuỗi ngày KHÔNG tính vào trần bài viết.
        app(PointLedger::class)->cong($u, 5, PointReason::ChuoiNgay, 'chuoi:2026-09-14');

        foreach (range(1, 3) as $i) {
            $this->duyet($this->bai($u));
        }

        $thu4 = $this->bai($u);
        $this->duyet($thu4)->assertSessionHas('success', fn ($m) => str_contains($m, 'đủ 3 bài trong tuần này'));

        $this->assertNotNull($thu4->fresh()->approved_at, 'Vẫn được duyệt');
        $this->assertSame(65, $this->soDu($u));

        /*
         * DUYỆT LẠI MỘT BÀI ĐÃ THƯỞNG, lúc khách đã chạm trần: không được báo
         * "đủ 3 bài" — bài này đã được thưởng rồi, trần không liên quan. Thử
         * phá code đã chứng minh: bỏ phép kiểm "đã thưởng" mà sổ vẫn đúng
         * (khoá UNIQUE chặn cộng lần hai), chỉ có câu báo là sai.
         */
        $baiDau = CommunityPost::where('user_id', $u->id)->orderBy('id')->first();
        $this->actingAs($this->admin)->patch(route('admin.community.reject', $baiDau), ['reject_reason' => 'Gỡ tạm']);
        $this->duyet($baiDau)->assertSessionHas('success', fn ($m) => ! str_contains($m, 'đủ 3 bài'));
        $this->assertSame(65, $this->soDu($u));
    }

    #[Test]
    public function tuan_moi_tinh_theo_thu_hai_gio_viet_nam(): void
    {
        $u = User::factory()->create();

        // Chủ nhật 13/09, 20:00 Hà Nội: thưởng đủ 3 bài.
        $this->travelTo(Carbon::parse('2026-09-13 13:00:00', 'UTC'));
        foreach (range(1, 3) as $i) {
            $this->duyet($this->bai($u));
        }

        // Thứ Hai 14/09, 05:00 Hà Nội = Chủ nhật 22:00 UTC. Theo lịch Việt Nam đã sang tuần mới.
        $this->travelTo(Carbon::parse('2026-09-13 22:00:00', 'UTC'));
        $this->duyet($this->bai($u));

        $this->assertSame(80, $this->soDu($u));
    }

    #[Test]
    public function khach_va_nguoi_duyet_thay_diem_da_thuong_cua_tung_bai(): void
    {
        $u = User::factory()->create();
        $coThuong = $this->bai($u, ['body' => 'Bài một được thưởng nhé.']);
        $this->duyet($coThuong, noiBat: true);
        $choDuyet = $this->bai($u, ['body' => 'Bài hai còn chờ duyệt.']);

        $html = $this->actingAs($u)->get(route('shop.community.index', ['tab' => 'cua-toi']))->assertOk()->getContent();

        /*
         * SO THEO THUỘC TÍNH CỦA ĐÚNG BÀI, không theo chữ "điểm" ở đâu đó sau
         * tên bài. Bản đầu dò "Bài hai … điểm</" và khớp nhầm dòng "+50 điểm"
         * của bài một nằm ngay bên dưới (danh sách xếp bài mới lên trước).
         */
        $this->assertStringContainsString('data-diem-bai="' . $coThuong->id . '">+50 điểm<', $html);
        $this->assertStringNotContainsString('data-diem-bai="' . $choDuyet->id . '"', $html);
        $this->assertStringContainsString('Tối đa 3 bài được thưởng mỗi tuần', $html);

        $html = $this->actingAs($this->admin)->get(route('admin.community.index', ['loc' => 'da-duyet']))->assertOk()->getContent();
        $this->assertStringContainsString('data-diem-bai="' . $coThuong->id . '"', $html);
        $this->assertStringNotContainsString('data-diem-bai="' . $choDuyet->id . '"', $html);
    }
}
