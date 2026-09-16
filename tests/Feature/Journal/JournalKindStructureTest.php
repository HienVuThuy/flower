<?php

namespace Tests\Feature\Journal;

use App\Enums\JournalKind;
use App\Enums\JournalSticker;
use App\Enums\JournalTheme;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Mỗi loại sổ một kết cấu riêng. */
class JournalKindStructureTest extends TestCase
{
    use RefreshDatabase;

    private function so(User $user, JournalKind $kind): Journal
    {
        $so = new Journal(['title' => 'Sổ ' . $kind->value, 'kind' => $kind]);
        $so->user_id = $user->id;
        $so->save();

        return $so;
    }

    #[Test]
    public function moi_loai_so_khai_it_nhat_mot_khoi_va_mot_o_nhap(): void
    {
        foreach (JournalKind::cases() as $kind) {
            $this->assertNotEmpty($kind->panels(), "Loại sổ {$kind->value} không có khối nào.");
            $this->assertNotEmpty($kind->entryFields(), "Loại sổ {$kind->value} không có ô nhập nào.");

            $this->assertTrue($kind->hasField('date'), "Loại sổ {$kind->value} thiếu ô ngày ghi.");
        }
    }

    #[Test]
    public function co_khoi_bieu_do_thi_phai_co_duong_ghi_ra_chi_so(): void
    {
        foreach (JournalKind::cases() as $kind) {
            if (! $kind->hasPanel('chart')) {
                continue;
            }

            $this->assertTrue(
                $kind->hasField('metrics') || $kind->hasField('price'),
                "Loại sổ {$kind->value} vẽ biểu đồ nhưng không có đường nào ghi ra chỉ số — "
                .'biểu đồ sẽ trống vĩnh viễn.',
            );
        }
    }

    #[Test]
    public function so_gia_tu_ghi_gia_thanh_chi_so_de_ve_bieu_do(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Price);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'price' => '2150000',
            'place' => 'Vườn ươm Văn Giang',
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_metrics', ['name' => 'Giá', 'value' => '2150000.00']);

        $trang = $so->entries()->firstOrFail();
        $this->assertSame(2150000, (int) $trang->field('price'));
        $this->assertSame('Vườn ươm Văn Giang', $trang->field('place'));
    }

    #[Test]
    public function o_nhap_rieng_cua_loai_so_khac_phai_bi_bo_qua(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Free);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'title' => 'Ghi chép tự do',
            'price' => '999000',
            'rating' => 5,
            'place' => 'Chỗ nào đó',
        ])->assertRedirect();

        $trang = $so->entries()->firstOrFail();

        $this->assertNull($trang->data, 'Khoá của loại sổ khác đã lọt vào cột data.');
        $this->assertNull($trang->field('price'));
        $this->assertNull($trang->field('rating'));
    }

    #[Test]
    public function trang_so_chi_hien_dung_nhung_khoi_cua_loai_so_do(): void
    {
        $user = User::factory()->create();

        $gia = $this->so($user, JournalKind::Price);

        $this->actingAs($user)
            ->get('/nhat-ky/' . $gia->id)
            ->assertOk()
            ->assertSee('Các lần khảo giá')
            ->assertSee('Giá đang ở đâu')
            ->assertDontSee('Dòng thời gian')
            ->assertDontSee('Các mốc cần đạt');

        $mucTieu = $this->so($user, JournalKind::Goal);

        $this->actingAs($user)
            ->get('/nhat-ky/' . $mucTieu->id)
            ->assertOk()
            ->assertSee('Các mốc cần đạt')
            ->assertSee('Dòng thời gian')
            ->assertDontSee('Các lần khảo giá');
    }

    #[Test]
    public function bieu_mau_ghi_them_chi_hoi_dung_nhung_o_cua_loai_so_do(): void
    {
        $user = User::factory()->create();

        $gia = $this->so($user, JournalKind::Price);

        $this->actingAs($user)
            ->get('/nhat-ky/' . $gia->id)
            ->assertOk()
            ->assertSee('Khảo ở đâu')
            ->assertSee('Ghi một lần khảo giá')
            ->assertDontSee('Tình trạng cây')
            ->assertDontSee('Hôm nay đã làm gì');

        $sinhTruong = $this->so($user, JournalKind::Growth);

        $this->actingAs($user)
            ->get('/nhat-ky/' . $sinhTruong->id)
            ->assertOk()
            ->assertSee('Tình trạng cây')
            ->assertSee('Hôm nay đã làm gì')
            ->assertDontSee('Khảo ở đâu');
    }

    #[Test]
    public function nhan_dan_chi_hien_bo_hop_voi_loai_so(): void
    {
        $this->assertCount(3, JournalSticker::forKind(JournalKind::Price));
        $this->assertCount(3, JournalSticker::forKind(JournalKind::Goal));
        $this->assertCount(12, JournalSticker::forKind(JournalKind::Growth));

        foreach (JournalSticker::forKind(JournalKind::Price) as $nhan) {
            $this->assertSame('Đánh dấu', $nhan->group());
        }
    }

    #[Test]
    public function moi_nhan_dan_deu_ve_ra_hinh_that(): void
    {
        foreach (JournalSticker::cases() as $nhan) {
            $svg = view('components.journal.sticker', ['sticker' => $nhan, 'size' => 24])->render();

            $this->assertStringContainsString('<svg', $svg, "Nhãn {$nhan->value} không vẽ ra svg.");
            $this->assertStringContainsString(
                '<path',
                $svg,
                "Nhãn {$nhan->value} vẽ ra svg RỖNG — thiếu nhánh trong sticker.blade.php.",
            );
            $this->assertStringContainsString($nhan->meaning(), $svg);
        }
    }

    #[Test]
    public function giao_dien_so_mac_dinh_theo_loai_khi_chua_chon(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Price);

        $this->assertSame(JournalTheme::Paper, $so->theme());
        $this->assertNull($so->theme()->pattern());

        $so->theme_key = JournalTheme::Dusk;
        $so->save();

        $this->assertSame(JournalTheme::Dusk, $so->fresh()->theme());
    }

    #[Test]
    public function moc_muc_tieu_chi_danh_dau_duoc_bang_nut_bam(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Goal);

        $this->actingAs($user)
            ->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Giâm cành ra rễ'])
            ->assertRedirect();

        $moc = $so->milestones()->firstOrFail();
        $this->assertNull($moc->done_at);

        $moc->update(['done_at' => now()->subYear()]);
        $this->assertNull($moc->fresh()->done_at, 'done_at đã bị gán hàng loạt.');

        $this->actingAs($user)
            ->patch('/nhat-ky/' . $so->id . '/moc/' . $moc->id)
            ->assertRedirect();

        $this->assertNotNull($moc->fresh()->done_at);
        $this->assertTrue($moc->fresh()->done_at->isToday());
    }

    #[Test]
    public function bam_lai_lan_nua_thi_bo_danh_dau(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Goal);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Một mốc']);
        $moc = $so->milestones()->firstOrFail();

        $this->actingAs($user)->patch('/nhat-ky/' . $so->id . '/moc/' . $moc->id);
        $this->assertNotNull($moc->fresh()->done_at);

        $this->actingAs($user)->patch('/nhat-ky/' . $so->id . '/moc/' . $moc->id);
        $this->assertNull($moc->fresh()->done_at, 'Không bỏ đánh dấu được — bấm nhầm là kẹt.');
    }

    #[Test]
    public function moc_da_xong_thi_khong_bao_gio_bi_coi_la_qua_han(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Goal);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/moc', [
            'title' => 'Việc đã trễ',
            'due_date' => now()->subMonth()->format('Y-m-d'),
        ]);

        $moc = $so->milestones()->firstOrFail();
        $this->assertTrue($moc->isOverdue());

        $this->actingAs($user)->patch('/nhat-ky/' . $so->id . '/moc/' . $moc->id);

        $this->assertFalse($moc->fresh()->isOverdue());
    }

    #[Test]
    public function nguoi_khac_khong_dung_duoc_moc_cua_toi(): void
    {
        $toi = User::factory()->create();
        $so = $this->so($toi, JournalKind::Goal);

        $this->actingAs($toi)->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Mốc riêng']);
        $moc = $so->milestones()->firstOrFail();

        $nguoiKhac = User::factory()->create();

        $this->actingAs($nguoiKhac)
            ->patch('/nhat-ky/' . $so->id . '/moc/' . $moc->id)
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->delete('/nhat-ky/' . $so->id . '/moc/' . $moc->id)
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Chen vào'])
            ->assertNotFound();

        $this->assertSame(1, $so->milestones()->count());
    }

    #[Test]
    public function xoa_so_thi_moc_di_theo(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Goal);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Một mốc']);
        $this->assertDatabaseCount('journal_milestones', 1);

        $this->actingAs($user)->delete('/nhat-ky/' . $so->id);

        $this->assertDatabaseCount('journal_milestones', 0);
    }

    #[Test]
    public function tien_do_moc_la_null_khi_chua_dat_moc_nao(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Goal);

        $this->assertNull($so->load('milestones')->milestoneProgress());

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/moc', ['title' => 'Một mốc']);

        $this->assertSame(
            ['done' => 0, 'total' => 1, 'percent' => 0.0],
            $so->fresh()->load('milestones')->milestoneProgress(),
        );
    }

    #[Test]
    public function thong_ke_gia_la_null_khi_moi_khao_mot_lan(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Price);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'price' => '500000',
        ]);

        $this->assertNull($so->fresh()->load('entries')->priceStats());

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->subDay()->format('Y-m-d'),
            'price' => '400000',
            'place' => 'Chợ Bưởi',
        ]);

        $tk = $so->fresh()->load('entries')->priceStats();

        $this->assertSame(400000.0, $tk['low']);
        $this->assertSame(500000.0, $tk['high']);
        $this->assertSame('Chợ Bưởi', $tk['place_low']);

        $this->assertSame(500000.0, $tk['latest']);
    }

    #[Test]
    public function dem_viec_cham_soc_theo_dung_so_lan_da_ghi(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, JournalKind::Growth);

        foreach ([['water'], ['water', 'fertilise'], ['water']] as $i => $viec) {
            $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => now()->subDays($i)->format('Y-m-d'),
                'care' => $viec,
            ]);
        }

        $dem = $so->fresh()->load('entries')->careTally();

        $this->assertSame(3, $dem['water']);
        $this->assertSame(1, $dem['fertilise']);
        $this->assertArrayNotHasKey('repot', $dem->all());
    }
}
