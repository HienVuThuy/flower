<?php

namespace Tests\Feature\Journal;

use App\Enums\JournalKind;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nhật ký cá nhân — chức năng.
 * ============================================================
 * Quyền riêng tư nằm ở `JournalPrivacyTest`. Tệp này giữ những chỗ dễ
 * hỏng về NGHIỆP VỤ, và phần lớn chúng là chuyện "câu trả lời đúng khi
 * chưa đủ dữ liệu":
 *
 *   - chưa ghi lần nào thì tiến độ mục tiêu là "chưa có số liệu", KHÔNG
 *     phải 0%;
 *   - hàng nhập chỉ số bỏ trống thì bỏ qua, KHÔNG báo lỗi;
 *   - một điểm dữ liệu thì không vẽ đường.
 */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function so(User $user, array $ghiDe = []): Journal
    {
        // `user_id` cố ý không nằm trong $fillable — gán riêng, đúng cách
        // controller làm.
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
        /*
         * Biểu mẫu luôn có ba hàng chỉ số, trong đó hàng cuối để trống cho
         * người dùng tự thêm. Bắt lỗi một hàng người ta không định điền là
         * chặn họ vì một việc họ không làm.
         */
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)
            ->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => now()->format('Y-m-d'),
                'metrics' => [
                    ['name' => 'Chiều cao', 'value' => '24', 'unit' => 'cm'],
                    ['name' => 'Số lá', 'value' => '', 'unit' => 'lá'],   // thiếu giá trị
                    ['name' => '', 'value' => '99', 'unit' => ''],         // thiếu tên
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
        /*
         * Cả lý do bảng `journal_metrics` tồn tại: người trồng lan ghi "số
         * nụ", người chơi bonsai ghi "đường kính thân". Làm thành cột thì
         * phải đoán trước mọi chỉ số của mọi loài.
         */
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
        /*
         * Người ta hay ghi bù: chủ nhật ngồi ghi lại cả tuần. Nếu dòng
         * thời gian xếp theo `created_at` thì bốn trang của bốn ngày dồn
         * hết vào chủ nhật, và biểu đồ sinh trưởng thành một cột dựng
         * đứng.
         */
        $user = User::factory()->create();
        $so = $this->so($user);

        foreach ([['2026-06-15', 24], ['2026-08-05', 40], ['2026-07-10', 31]] as [$ngay, $cao]) {
            $this->actingAs($user)->post('/nhat-ky/' . $so->id . '/trang', [
                'entry_date' => $ngay,
                'metrics' => [['name' => 'Chiều cao', 'value' => (string) $cao, 'unit' => 'cm']],
            ]);
        }

        // Chuỗi vẽ biểu đồ phải xếp TĂNG DẦN theo ngày, bất kể thứ tự nhập.
        $chuoi = $so->fresh()->metricSeries('Chiều cao');

        $this->assertSame([24.0, 31.0, 40.0], $chuoi->pluck('value')->all());
    }

    #[Test]
    public function khong_nhan_ngay_ghi_o_tuong_lai(): void
    {
        // Nhật ký là ghi lại thứ đã quan sát được. Một trang đề ngày mai
        // là dữ liệu chưa tồn tại.
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
        /*
         * BÀI QUAN TRỌNG. "0%" đọc ra là "đã bắt đầu và chưa đi được bước
         * nào"; "chưa có số liệu" là chuyện khác hẳn. Hiện 0% sẽ làm người
         * ta tưởng mình đang tụt lại trong khi thật ra chưa đo lần nào.
         */
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
        /*
         * Cây chết rồi thì quyển sổ vẫn là kỷ niệm, và vẫn là bài học cho
         * lần trồng sau. Đừng bắt người ta phải xoá mới cho gọn màn hình.
         */
        $user = User::factory()->create();
        $so = $this->so($user);

        $this->actingAs($user)->patch('/nhat-ky/' . $so->id . '/luu-tru')->assertRedirect();

        $this->assertTrue($so->fresh()->is_archived);
        $this->assertDatabaseHas('journals', ['id' => $so->id]);

        // Không hiện ở danh sách chính, nhưng hiện ở mục lưu trữ.
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
        /*
         * Một điểm thì không có "thay đổi theo thời gian" nào để nhìn.
         * Vẽ một đường thẳng qua đúng một điểm là bày ra một xu hướng
         * không tồn tại.
         */
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
            // Tooltip gốc của trình duyệt: giá trị chính xác đọc được mà
            // không cần một dòng JavaScript nào.
            ->assertSee('01/09/2026: 31 cm');
    }
}
