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

/**
 * NHẬT KÝ CÁ NHÂN LÀ RIÊNG TƯ — và đây là chỗ giữ lời hứa đó.
 * ============================================================
 * Người ta ghi vào nhật ký chuyện cây nhà mình chết vì quên tưới, giá
 * mình đang chờ, mục tiêu mình chưa làm được. Trang danh sách sổ nói
 * thẳng với họ: *"Chỉ mình bạn đọc được. Cửa hàng không dùng nội dung ở
 * đây cho gợi ý sản phẩm hay bất kỳ thống kê nào."*
 *
 * Một câu như thế mà chỉ nằm trong tài liệu thì lần sửa sau sẽ quên. Ba
 * tháng nữa, khi có người muốn "gợi ý thông minh hơn", ba bảng nhật ký
 * nằm ngay đó và đầy ắp thứ đúng là tín hiệu tốt nhất về sở thích —
 * chẳng có gì trong mã nguồn ngăn họ dùng.
 *
 * Vì vậy bài đầu tiên trong tệp này ĐẾM TRUY VẤN: gọi bộ máy gợi ý rồi
 * khẳng định không câu SQL nào chạm vào `journals`, `journal_entries`,
 * `journal_metrics`. Ai nối nhật ký vào gợi ý sẽ làm đỏ bài này ngay
 * trong lần chạy đầu tiên, kèm đúng câu truy vấn đã vi phạm.
 */
class JournalPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const BANG_RIENG_TU = ['journals', 'journal_entries', 'journal_metrics'];

    private function nguoiDungCoNhatKy(): User
    {
        $user = User::factory()->create();

        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create();

        /*
         * `user_id` GÁN RIÊNG, không đi qua create().
         *
         * Nó cố ý KHÔNG nằm trong $fillable của Journal: chủ sở hữu không
         * bao giờ được nhận từ dữ liệu gửi lên. Bài kiểm thử phải tạo dữ
         * liệu theo đúng cách controller làm, nếu không nó đang kiểm một
         * đường đi không tồn tại.
         */
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

        /*
         * LỊCH SỬ XEM — BẮT BUỘC PHẢI CÓ, và đây là bài học đắt nhất của
         * tệp này.
         *
         * Bản đầu của bài kiểm thử không tạo dữ liệu này. Nó XANH, và
         * trông như quyền riêng tư đã được bảo vệ. Nhưng khi chèn thử một
         * câu truy vấn đọc bảng `journals` vào giữa RecommendationService
         * thì bài VẪN XANH — tức là nó không kiểm gì cả.
         *
         * Lý do: `forViewer()` THOÁT SỚM khi người dùng chưa có lịch sử
         * xem — nó trả về danh sách phổ biến và không bao giờ chạy tới
         * nhánh cá nhân hoá, đúng cái nhánh có nguy cơ đụng vào nhật ký
         * nhất.
         *
         * Một bài kiểm thử quyền riêng tư chỉ đi qua nhánh "chưa biết gì
         * về người này" là một bài kiểm thử không bảo vệ ai.
         */
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

    /**
     * Chạy một việc và trả về mọi câu SQL nó sinh ra.
     *
     * @return list<string>
     */
    private function bắtTruyVấn(callable $viec): array
    {
        $sql = [];

        DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });

        $viec();

        return $sql;
    }

    /** @param list<string> $sql */
    private function chạmBảngRiêngTư(array $sql): array
    {
        return array_values(array_filter($sql, function (string $s) {
            foreach (self::BANG_RIENG_TU as $bang) {
                // Khớp cả `journals` lẫn "journals" — mỗi hệ CSDL bọc tên
                // bảng một kiểu.
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
        /*
         * BÀI QUAN TRỌNG NHẤT CỦA CẢ TÍNH NĂNG.
         *
         * Không kiểm "kết quả gợi ý có giống nhau không" — cách đó bỏ lọt
         * trường hợp nhật ký được đọc nhưng tình cờ không đổi thứ hạng.
         * Kiểm ở tầng SQL thì không có chỗ nào lách được.
         */
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

        // Bảo hiểm cho chính bài kiểm thử: nếu bộ máy gợi ý không chạy
        // truy vấn nào thì phép khẳng định ở trên đúng một cách vô nghĩa.
        $this->assertNotEmpty($sql, 'Bộ máy gợi ý không chạy truy vấn nào — bài kiểm thử này đang không kiểm gì cả.');
    }

    #[Test]
    public function trang_tu_van_chon_cay_cung_khong_duoc_cham_vao_nhat_ky(): void
    {
        // PlantAdvisor là bộ máy gợi ý thứ hai của hệ thống. Chặn một cái
        // mà quên cái kia thì lỗ rò vẫn còn nguyên.
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
        /*
         * CÁM DỖ LỚN NHẤT NẰM Ở ĐÂY, không phải ở bộ máy gợi ý.
         *
         * Nhật ký kiểu "Bảng giá" là nơi khách tự tay ghi ra mức giá họ
         * đang chờ. Với một công cụ định giá thì đó là dữ liệu quý nhất
         * có thể tưởng tượng — và dùng nó là bán đứng đúng lời hứa in
         * trên trang nhật ký của họ.
         *
         * Chặn ở tầng SQL, không chặn bằng lời dặn trong tài liệu.
         */
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
        // Kiểm cả TRANG, không chỉ lớp dịch vụ: một khối phụ nào đó trong
        // Blade cũng có thể nạp quan hệ và mở đúng cánh cửa vừa khoá.
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
        /*
         * Trang chủ là nơi cá nhân hoá mạnh nhất: gợi ý theo lịch sử xem,
         * "được yêu thích gần đây". Đúng chỗ mà nhật ký dễ bị kéo vào
         * nhất.
         */
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

        /*
         * 404, KHÔNG PHẢI 403.
         *
         * 403 xác nhận quyển sổ đó CÓ TỒN TẠI. Người dò id sẽ đếm được
         * người khác có bao nhiêu sổ và chúng nằm ở id nào. 404 thì không
         * phân biệt được "không có" với "không phải của bạn".
         */
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

        // Ghi thêm trang vào sổ người khác cũng phải bị chặn.
        $this->actingAs($nguoiKhac)
            ->post('/nhat-ky/' . $so->id . '/trang', ['entry_date' => now()->format('Y-m-d')])
            ->assertNotFound();

        $this->actingAs($nguoiKhac)
            ->delete('/nhat-ky/' . $so->id . '/trang/' . $entry->id)
            ->assertNotFound();

        // Sổ vẫn còn nguyên, chưa bị đổi tên.
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
        /*
         * Đây là dữ liệu riêng tư của người đó. Giữ lại một quyển sổ mồ
         * côi không còn chủ là giữ lại đúng thứ đáng lẽ phải xoá — và
         * người dùng vừa yêu cầu xoá tài khoản chính là đang yêu cầu xoá
         * nó.
         */
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
        /*
         * Cho gắn sổ vào bất kỳ sản phẩm nào thì trang sổ trở thành một
         * cách dò xem cửa hàng bán gì — và tệ hơn, một cách dựng dữ liệu
         * giả về việc mình đã mua.
         */
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
