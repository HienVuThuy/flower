<?php

namespace Tests\Feature\Journal;

use App\Enums\JournalKind;
use App\Models\Category;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\UserEventType;
use App\Enums\UserRole;
use App\Services\Pricing\DemandSignals;
use App\Services\Pricing\PricingAdvisor;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Recommendation\PlantAdvisor;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** NHẬT KÝ CÁ NHÂN LÀ RIÊNG TƯ — và đây là chỗ giữ lời hứa đó. */
class JournalPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const BANG_RIENG_TU = ['journals', 'journal_entries', 'journal_metrics'];

    private function nguoiDungCoNhatKy(): User
    {
        $user = User::factory()->create();

        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create();

        $journal = new Journal([
            'title' => 'Sổ riêng của tôi',
            'kind' => JournalKind::Growth,
            'description' => 'Chuyện riêng, không phải dữ liệu bán hàng.',
            'product_id' => $product->id,
        ]);
        $journal->user_id = $user->id;
        $journal->save();

        $entry = $journal->entries()->create([
            'entry_date' => now()->subDays(3),
            'title' => 'Cây sắp chết vì tôi quên tưới',
            'body' => 'Không muốn ai đọc được câu này.',
        ]);

        $entry->metrics()->create(['name' => 'Chiều cao', 'value' => 24, 'unit' => 'cm']);

        UserEvent::query()->create([
            'user_id' => $user->id,
            'session_id' => 'phien-thu',
            'event_type' => UserEventType::ProductView,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'created_at' => now()->subDay(),
        ]);

        return $user;
    }

    private function bắtTruyVấn(callable $viec): array
    {
        $sql = [];

        DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });

        $viec();

        return $sql;
    }

    private function chạmBảngRiêngTư(array $sql): array
    {
        return array_values(array_filter($sql, function (string $s) {
            foreach (self::BANG_RIENG_TU as $bang) {
                if (preg_match('/[`"\s.]' . $bang . '[`"\s.]|[`"]' . $bang . '[`"]/i', $s)) {
                    return true;
                }
            }

            return false;
        }));
    }

    #[Test]
    public function bo_may_goi_y_khong_duoc_cham_vao_bang_nhat_ky(): void
    {
        $user = $this->nguoiDungCoNhatKy();

        $sql = $this->bắtTruyVấn(function () use ($user) {
            app(RecommendationService::class)->forViewer($user->id, 'phien-thu');
        });

        $viPham = $this->chạmBảngRiêngTư($sql);

        $this->assertSame(
            [],
            $viPham,
            "Bộ máy gợi ý đã đọc dữ liệu nhật ký riêng tư:\n" . implode("\n", $viPham),
        );

        $this->assertNotEmpty($sql, 'Bộ máy gợi ý không chạy truy vấn nào — bài kiểm thử này đang không kiểm gì cả.');
    }

    #[Test]
    public function trang_tu_van_chon_cay_cung_khong_duoc_cham_vao_nhat_ky(): void
    {
        $this->nguoiDungCoNhatKy();

        $sql = $this->bắtTruyVấn(function () {
            app(PlantAdvisor::class)->forBeginners();
            app(PlantAdvisor::class)->availablePlacements();
        });

        $this->assertSame([], $this->chạmBảngRiêngTư($sql));
    }

    #[Test]
    public function co_van_gia_cua_admin_khong_duoc_cham_vao_nhat_ky(): void
    {
        $this->nguoiDungCoNhatKy();

        $sql = $this->bắtTruyVấn(function () {
            (new PricingAdvisor(new DemandSignals(30)))->suggest();
        });

        $this->assertSame(
            [],
            $this->chạmBảngRiêngTư($sql),
            "Cố vấn giá đã đọc dữ liệu nhật ký riêng tư:\n" . implode("\n", $this->chạmBảngRiêngTư($sql)),
        );

        $this->assertNotEmpty($sql, 'Cố vấn giá không chạy truy vấn nào — bài này đang không kiểm gì cả.');
    }

    #[Test]
    public function trang_de_xuat_gia_cua_admin_khong_cham_vao_nhat_ky(): void
    {
        $this->nguoiDungCoNhatKy();

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $sql = $this->bắtTruyVấn(function () use ($admin) {
            $this->actingAs($admin)->get('/admin/de-xuat-gia')->assertOk();
        });

        $this->assertSame([], $this->chạmBảngRiêngTư($sql));
    }

    #[Test]
    public function trang_chu_khong_cham_vao_nhat_ky_ke_ca_khi_dang_nhap(): void
    {
        $user = $this->nguoiDungCoNhatKy();

        $sql = $this->bắtTruyVấn(function () use ($user) {
            $this->actingAs($user)->get('/')->assertOk();
        });

        $this->assertSame([], $this->chạmBảngRiêngTư($sql));
    }

    #[Test]
    public function nguoi_khac_khong_mo_duoc_so_cua_toi(): void
    {
        $toi = $this->nguoiDungCoNhatKy();
        $so = Journal::where('user_id', $toi->id)->firstOrFail();

        $nguoiKhac = User::factory()->create();

        $this->actingAs($nguoiKhac)
            ->get('/nhat-ky/' . $so->id)
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->get('/nhat-ky/' . $so->id . '/sua')
            ->assertNotFound();
    }

    #[Test]
    public function nguoi_khac_khong_sua_khong_xoa_duoc_so_cua_toi(): void
    {
        $toi = $this->nguoiDungCoNhatKy();
        $so = Journal::where('user_id', $toi->id)->firstOrFail();
        $entry = $so->entries()->firstOrFail();

        $nguoiKhac = User::factory()->create();

        $this->actingAs($nguoiKhac)
            ->put('/nhat-ky/' . $so->id, ['title' => 'Chiếm sổ', 'kind' => 'free'])
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->delete('/nhat-ky/' . $so->id)
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->post('/nhat-ky/' . $so->id . '/trang', ['entry_date' => now()->format('Y-m-d')])
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->delete('/nhat-ky/' . $so->id . '/trang/' . $entry->id)
            ->assertNotFound();

        $this->assertSame('Sổ riêng của tôi', $so->fresh()->title);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    #[Test]
    public function khach_vang_lai_khong_vao_duoc_phan_nhat_ky(): void
    {
        $this->get('/nhat-ky')->assertRedirect('/login');
        $this->get('/nhat-ky/tao-moi')->assertRedirect('/login');
    }

    #[Test]
    public function xoa_tai_khoan_thi_nhat_ky_di_theo(): void
    {
        $user = $this->nguoiDungCoNhatKy();

        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('journal_metrics', 1);

        $user->delete();

        $this->assertDatabaseCount('journals', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_metrics', 0);
    }

    #[Test]
    public function khong_gan_duoc_so_vao_cay_minh_chua_mua(): void
    {
        $user = User::factory()->create();

        $cayChuaMua = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create();

        $this->actingAs($user)
            ->post('/nhat-ky', [
                'title' => 'Sổ thử',
                'kind' => 'growth',
                'product_id' => $cayChuaMua->id,
            ])
            ->assertSessionHasErrors('product_id');
    }
}
