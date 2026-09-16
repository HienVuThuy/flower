<?php

namespace Tests\Feature\Journal;

use App\Enums\JournalKind;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nhật ký cá nhân — chức năng. */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function so(User $user, array $ghiDe = []): Journal
    {
        $so = new Journal(array_merge([
            'title' => 'Sổ thử',
            'kind' => JournalKind::Growth,
        ], $ghiDe));

        $so->user_id = $user->id;
        $so->save();

        return $so;
    }

    #[Test]
    public function tao_so_va_ghi_trang_dau_tien(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/nhat-ky', ['title' => 'Monstera góc phòng khách', 'kind' => 'growth'])
            ->assertRedirect();

        $so = Journal::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => now()->format('Y-m-d'),
                'title' => 'Vừa mua về',
                'body' => 'Đặt cạnh cửa sổ hướng đông.',
                'condition' => 'healthy',
                'metrics' => [
                    ['name' => 'Chiều cao', 'value' => '24', 'unit' => 'cm'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('journal_entries', ['journal_id' => $so->id, 'title' => 'Vừa mua về']);
        $this->assertDatabaseHas('journal_metrics', ['name' => 'Chiều cao', 'value' => '24.00', 'unit' => 'cm']);
    }

    #[Test]
    public function hang_chi_so_bo_trong_bi_bo_qua_chu_khong_bao_loi(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)
            ->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => now()->format('Y-m-d'),
                'metrics' => [
                    ['name' => 'Chiều cao', 'value' => '24', 'unit' => 'cm'],
                    ['name' => 'Số lá', 'value' => '', 'unit' => 'lá'],
                    ['name' => '', 'value' => '99', 'unit' => ''],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('journal_metrics', 1);
        $this->assertDatabaseHas('journal_metrics', ['name' => 'Chiều cao']);
    }

    #[Test]
    public function nguoi_dung_tu_dat_ten_chi_so_nao_cung_duoc(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, ['kind' => JournalKind::Free]);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'metrics' => [
                ['name' => 'Số nụ', 'value' => '3', 'unit' => 'nụ'],
                ['name' => 'Đường kính thân', 'value' => '1.8', 'unit' => 'cm'],
            ],
        ]);

        $this->assertDatabaseHas('journal_metrics', ['name' => 'Số nụ']);
        $this->assertDatabaseHas('journal_metrics', ['name' => 'Đường kính thân', 'value' => '1.80']);
    }

    #[Test]
    public function ghi_bu_ngay_cu_van_nam_dung_cho_tren_dong_thoi_gian(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        foreach ([['2026-06-15', 24], ['2026-08-05', 40], ['2026-07-10', 31]] as [$ngay, $cao]) {
            $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => $ngay,
                'metrics' => [['name' => 'Chiều cao', 'value' => (string) $cao, 'unit' => 'cm']],
            ]);
        }

        $chuoi = $so->fresh()->metricSeries('Chiều cao');

        $this->assertSame([24.0, 31.0, 40.0], $chuoi->pluck('value')->all());
    }

    #[Test]
    public function khong_nhan_ngay_ghi_o_tuong_lai(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)
            ->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => now()->addDays(3)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('entry_date');
    }

    #[Test]
    public function tien_do_muc_tieu_la_null_khi_chua_ghi_so_nao(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, ['target_metric' => 'Chiều cao', 'target_value' => 60]);

        $this->assertNull($so->goalProgress());

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'metrics' => [['name' => 'Chiều cao', 'value' => '48', 'unit' => 'cm']],
        ]);

        $this->assertSame(80.0, $so->fresh()->goalProgress());
    }

    #[Test]
    public function tien_do_tinh_theo_lan_ghi_gan_nhat_khong_phai_lan_dau(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user, ['target_metric' => 'Chiều cao', 'target_value' => 100]);

        foreach ([['2026-06-01', 20], ['2026-09-01', 75]] as [$ngay, $cao]) {
            $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => $ngay,
                'metrics' => [['name' => 'Chiều cao', 'value' => (string) $cao]],
            ]);
        }

        $this->assertSame(75.0, $so->fresh()->goalProgress());
    }

    #[Test]
    public function luu_tru_la_an_di_chu_khong_phai_xoa(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->patch('/nhat-ky/' . $so->id . '/luu-tru')->assertRedirect();

        $this->assertTrue($so->fresh()->is_archived);
        $this->assertDatabaseHas('journals', ['id' => $so->id]);

        $this->actingAs($user)->get('/nhat-ky')->assertDontSee('Sổ thử');
        $this->actingAs($user)->get('/nhat-ky?luu-tru=1')->assertSee('Sổ thử');
    }

    #[Test]
    public function xoa_so_keo_theo_moi_trang_va_chi_so_ben_trong(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'metrics' => [['name' => 'Chiều cao', 'value' => '24']],
        ]);

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_metrics', 1);

        $this->actingAs($user)->delete('/nhat-ky/' . $so->id)->assertRedirect();

        $this->assertDatabaseCount('journals', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_metrics', 0);
    }

    #[Test]
    public function danh_sach_chi_hien_so_cua_chinh_minh(): void
    {
        $toi = User::factory()->create();
        $nguoiKhac = User::factory()->create();

        $this->so($toi, ['title' => 'Sổ của tôi']);
        $this->so($nguoiKhac, ['title' => 'Sổ người khác']);

        $this->actingAs($toi)
            ->get('/nhat-ky')
            ->assertOk()
            ->assertSee('Sổ của tôi')
            ->assertDontSee('Sổ người khác');
    }

    #[Test]
    public function mot_diem_du_lieu_thi_khong_ve_duong(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
            'entry_date' => now()->format('Y-m-d'),
            'metrics' => [['name' => 'Chiều cao', 'value' => '24', 'unit' => 'cm']],
        ]);

        $this->actingAs($user)
            ->get('/nhat-ky/' . $so->id)
            ->assertOk()
            ->assertSee('Mới có một lần ghi')
            ->assertDontSee('journal-chart__line', false);
    }

    #[Test]
    public function bieu_do_hien_ra_tu_lan_ghi_thu_hai(): void
    {
        $user = User::factory()->create();
        $so = $this->so($user);

        foreach ([['2026-08-01', 24], ['2026-09-01', 31]] as [$ngay, $cao]) {
            $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => $ngay,
                'metrics' => [['name' => 'Chiều cao', 'value' => (string) $cao, 'unit' => 'cm']],
            ]);
        }

        $this->actingAs($user)
            ->get('/nhat-ky/' . $so->id)
            ->assertOk()
            ->assertSee('journal-chart__line', false)
            ->assertSee('01/09/2026: 31 cm');
    }
}
