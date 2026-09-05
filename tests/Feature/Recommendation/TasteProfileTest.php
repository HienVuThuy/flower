<?php

namespace Tests\Feature\Recommendation;

use App\Enums\PlantColor;
use App\Enums\TaxonRank;
use App\Enums\TraitType;
use App\Enums\UserEventType;
use App\Models\Category;
use App\Models\PlantTaxon;
use App\Models\Product;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\TasteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gợi ý theo ĐẶC ĐIỂM và LOÀI.
 * ============================================================
 * Trước khi có `TasteProfile`, bộ máy gợi ý chỉ biết danh mục và hình
 * thức bán — hai chậu sen đá, một xanh một tím, với nó là giống hệt
 * nhau. Cửa hàng đã có sẵn dữ liệu màu, dáng, môi trường sống và loài
 * mà không dùng tới.
 *
 * Tệp này kiểm ba thứ dễ làm sai nhất khi thêm trục mới:
 *
 *   1. Trục mới có THẬT SỰ đổi thứ tự không, hay chỉ cộng thêm một hằng
 *      số vào mọi sản phẩm.
 *   2. Trục mới có LẤN ÁT các trục cũ không — bẫy "ai nhiều nhãn hơn thì
 *      thắng".
 *   3. Quan hệ họ hàng có bị nới rộng tới mức vô nghĩa không ("cùng giới
 *      Thực vật").
 *
 * Cả ba đều là loại lỗi mà mắt thường nhìn danh sách gợi ý sẽ không thấy,
 * vì kết quả nào trông cũng hợp lý.
 */
class TasteProfileTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();

        // Một danh mục cây cảnh dùng chung: giữ trục danh mục ở thế hoà
        // để đo được ảnh hưởng của riêng trục đang xét.
        $this->danhMuc = Category::factory()->create(['kind' => 'plant', 'is_active' => true]);
    }

    private function sanPham(string $ten, array $ghiDe = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => $ten,
            'category_id' => $this->danhMuc->id,
        ], $ghiDe));
    }

    private function gan(Product $p, TraitType $loai, string ...$giaTri): Product
    {
        $p->syncTraits($loai, $giaTri);

        return $p;
    }

    /** Ghi một hành vi của khách vãng lai trong phiên "phien-thu". */
    private function xem(Product $p, UserEventType $loai = UserEventType::ProductView): void
    {
        UserEvent::query()->create([
            'session_id' => 'phien-thu',
            'event_type' => $loai,
            'product_id' => $p->id,
            'category_id' => $p->category_id,
            'created_at' => now(),
        ]);
    }

    /** @return list<string> tên sản phẩm được gợi ý, theo đúng thứ tự */
    private function goiY(int $limit = 4): array
    {
        return collect(app(RecommendationService::class)->forViewer(null, 'phien-thu', $limit)['items'])
            ->map(fn (array $i) => $i['product']->name)
            ->all();
    }

    private function lyDoCho(string $ten): ?string
    {
        return collect(app(RecommendationService::class)->forViewer(null, 'phien-thu', 8)['items'])
            ->first(fn (array $i) => $i['product']->name === $ten)['reason'] ?? null;
    }

    #[Test]
    public function mau_sac_da_xem_day_san_pham_cung_mau_len_truoc(): void
    {
        /*
         * BÀI CỐT LÕI CỦA CẢ TÍNH NĂNG.
         *
         * Ba sản phẩm ứng viên nằm CÙNG danh mục, CÙNG hình thức bán —
         * trục danh mục và hình thức hoà nhau tuyệt đối. Thứ duy nhất
         * khác là màu. Nếu trục màu không hoạt động thì thứ tự trả về sẽ
         * tuỳ ý, không phải "trắng lên đầu".
         */
        foreach (['Đã xem 1', 'Đã xem 2', 'Đã xem 3'] as $ten) {
            $this->xem($this->gan($this->sanPham($ten), TraitType::Color, PlantColor::White->value));
        }

        /*
         * THỨ TỰ TẠO CÓ CHỦ Ý: ứng viên trắng được tạo SAU CÙNG.
         *
         * Bản đầu của bài này tạo nó trước, và bài XANH kể cả khi tắt hẳn
         * trục đặc điểm — vì khi mọi ứng viên hoà điểm, `sortByDesc` giữ
         * nguyên thứ tự cũ, tức là thứ tự id. Bài đang đo thứ tự chèn dữ
         * liệu chứ không đo thuật toán.
         *
         * Đặt nó cuối thì chỉ một trục màu chạy thật mới kéo được nó lên
         * đầu.
         */
        $this->gan($this->sanPham('Ứng viên đỏ'), TraitType::Color, PlantColor::Red->value);
        $this->gan($this->sanPham('Ứng viên tím'), TraitType::Color, PlantColor::Purple->value);
        $this->gan($this->sanPham('Ứng viên trắng'), TraitType::Color, PlantColor::White->value);

        $this->assertSame('Ứng viên trắng', $this->goiY()[0]);
    }

    #[Test]
    public function khop_mot_phan_so_thich_thi_chi_duoc_mot_phan_diem(): void
    {
        /*
         * ĐÂY MỚI LÀ BÀI CHẶN BẪY "NHIỀU NHÃN THÌ THẮNG" — bài bên dưới
         * chỉ chặn được nửa dễ của nó.
         *
         * Khách quan tâm BA đặc điểm ngang nhau (trắng, dáng X, đặt ở Y).
         * Một sản phẩm chạm đúng một trong ba phải được MỘT PHẦN BA điểm
         * của trục, không phải điểm tối đa.
         *
         * Chia cho TỔNG cho ra 1/3. Chia cho giá trị LỚN NHẤT — cách viết
         * thoạt nhìn cũng hợp lý — cho ra 1.0 cho cả hai, tức là sản phẩm
         * chạm một đặc điểm được coi là hợp y hệt sản phẩm chạm cả ba.
         * Khi ấy trục đặc điểm không còn phân biệt được gì.
         */
        $dang = array_key_first(TraitType::Shape->options());
        $viTri = array_key_first(TraitType::Placement->options());

        $daXem = $this->sanPham('Đã xem');
        $this->gan($daXem, TraitType::Color, PlantColor::White->value);
        $this->gan($daXem, TraitType::Shape, $dang);
        $this->gan($daXem, TraitType::Placement, $viTri);
        $this->xem($daXem);

        $motPhan = $this->gan($this->sanPham('Khớp một đặc điểm'), TraitType::Color, PlantColor::White->value);

        $duCa = $this->sanPham('Khớp cả ba');
        $this->gan($duCa, TraitType::Color, PlantColor::White->value);
        $this->gan($duCa, TraitType::Shape, $dang);
        $this->gan($duCa, TraitType::Placement, $viTri);

        $taste = $this->chanDungTuLichSu();

        $a = $taste->match($motPhan->load('traits', 'category'))['score'];
        $b = $taste->match($duCa->load('traits', 'category'))['score'];

        // Hai ứng viên chỉ khác nhau ở trục đặc điểm (trọng số 2.0), nên
        // chênh lệch điểm phải đúng bằng 2.0 × (1 − 1/3).
        $this->assertEqualsWithDelta(2.0 * (1 - 1 / 3), $b - $a, 0.0001);
    }

    #[Test]
    public function ly_do_noi_ro_dac_diem_nao_khien_san_pham_xuat_hien(): void
    {
        // Gợi ý không giải thích được thì với khách chỉ là một sản phẩm
        // ngẫu nhiên — đúng thứ khối "Gợi ý cho bạn" hứa là không phải.
        foreach (['Đã xem 1', 'Đã xem 2'] as $ten) {
            $this->xem($this->gan($this->sanPham($ten), TraitType::Color, PlantColor::White->value));
        }

        $this->gan($this->sanPham('Ứng viên trắng'), TraitType::Color, PlantColor::White->value);

        $this->assertSame('Cũng tông màu trắng', $this->lyDoCho('Ứng viên trắng'));
    }

    #[Test]
    public function san_pham_nhieu_nhan_khong_thang_chi_vi_nhieu_nhan(): void
    {
        /*
         * BẪY ĐÃ CHẶN BẰNG CHUẨN HOÁ — và là lý do mỗi trục phải về thang
         * 0..1 trước khi cộng.
         *
         * Cộng thẳng điểm thô thì một sản phẩm mang bảy nhãn luôn thắng
         * một sản phẩm chỉ có một nhãn, kể cả khi khách chỉ quan tâm đúng
         * một đặc điểm. Thứ tự gợi ý khi ấy phản ánh CÔNG SỨC NHẬP LIỆU
         * của admin chứ không phản ánh sở thích của khách.
         *
         * Ở đây khách chỉ thể hiện một sở thích: màu trắng. "Ứng viên đủ
         * nhãn" khớp đúng màu trắng và mang thêm sáu nhãn khách CHƯA hề
         * quan tâm. Nó không được vượt lên trên sản phẩm chỉ có mỗi màu
         * trắng.
         */
        foreach (['Đã xem 1', 'Đã xem 2', 'Đã xem 3'] as $ten) {
            $this->xem($this->gan($this->sanPham($ten), TraitType::Color, PlantColor::White->value));
        }

        $moiMauTrang = $this->gan($this->sanPham('Chỉ có màu trắng'), TraitType::Color, PlantColor::White->value);

        $duNhan = $this->sanPham('Ứng viên đủ nhãn');
        $this->gan($duNhan, TraitType::Color, PlantColor::White->value);
        $this->gan($duNhan, TraitType::Shape, array_key_first(TraitType::Shape->options()));
        $this->gan($duNhan, TraitType::GrowthForm, array_key_first(TraitType::GrowthForm->options()));
        $this->gan($duNhan, TraitType::Habitat, array_key_first(TraitType::Habitat->options()));
        $this->gan($duNhan, TraitType::Placement, array_key_first(TraitType::Placement->options()));
        $this->gan($duNhan, TraitType::FengShui, array_key_first(TraitType::FengShui->options()));

        $goiY = $this->goiY();

        // Hai sản phẩm khớp đúng như nhau (một nhãn màu trắng), nên điểm
        // phải BẰNG NHAU — không cái nào được đẩy lên trước cái nào.
        $taste = $this->chanDungTuLichSu();

        $a = $taste->match($moiMauTrang->load('traits', 'category'))['score'];
        $b = $taste->match($duNhan->load('traits', 'category'))['score'];

        $this->assertSame(
            round($a, 6),
            round($b, 6),
            'Sản phẩm nhiều nhãn đang được cộng điểm cho những nhãn khách chưa hề quan tâm.',
        );

        $this->assertContains('Chỉ có màu trắng', $goiY);
    }

    #[Test]
    public function cung_chi_thuc_vat_duoc_goi_y(): void
    {
        [$ho, $chi] = $this->nhanhPhanLoai();

        $loaiA = PlantTaxon::create(['parent_id' => $chi->id, 'rank' => TaxonRank::Species, 'name' => 'deliciosa', 'slug' => 'deliciosa']);
        $loaiB = PlantTaxon::create(['parent_id' => $chi->id, 'rank' => TaxonRank::Species, 'name' => 'adansonii', 'slug' => 'adansonii']);

        $this->xem($this->sanPham('Monstera deliciosa', ['taxon_id' => $loaiA->id]));

        // Cây không rõ loài tạo TRƯỚC — cùng lý do như bài màu sắc: nếu
        // trục phân loại không chạy, hai ứng viên hoà điểm và thứ tự id
        // sẽ quyết định, khiến bài xanh mà không kiểm gì.
        $this->sanPham('Cây không rõ loài');
        $this->sanPham('Monstera adansonii', ['taxon_id' => $loaiB->id]);

        $this->assertSame('Monstera adansonii', $this->goiY()[0]);
        $this->assertSame('Cùng chi Monstera', $this->lyDoCho('Monstera adansonii'));
    }

    #[Test]
    public function cung_gioi_thuc_vat_KHONG_duoc_tinh_la_giong_nhau(): void
    {
        /*
         * MỌI cây trong cửa hàng đều cùng Giới Thực vật. Nếu trục phân
         * loại tính cả bậc đó thì nó cộng đúng một hằng số vào tất cả
         * ứng viên — không phân biệt được gì, mà vẫn TRÔNG như đang chạy.
         *
         * Tệ hơn: câu lý do sẽ là "Cùng giới Thực vật", một câu đúng
         * tuyệt đối và vô dụng tuyệt đối.
         *
         * Nên chỉ tính từ bậc HỌ trở xuống.
         */
        $gioi = PlantTaxon::create(['rank' => TaxonRank::Kingdom, 'name' => 'Thực vật', 'slug' => 'thuc-vat']);
        $nganh = PlantTaxon::create(['parent_id' => $gioi->id, 'rank' => TaxonRank::Phylum, 'name' => 'Hạt kín', 'slug' => 'hat-kin']);

        $hoA = PlantTaxon::create(['parent_id' => $nganh->id, 'rank' => TaxonRank::Family, 'name' => 'Ráy', 'slug' => 'ray']);
        $hoB = PlantTaxon::create(['parent_id' => $nganh->id, 'rank' => TaxonRank::Family, 'name' => 'Xương rồng', 'slug' => 'xuong-rong']);

        $daXem = $this->sanPham('Cây họ Ráy', ['taxon_id' => $hoA->id]);
        $this->xem($daXem);

        $xaLa = $this->sanPham('Cây họ Xương rồng', ['taxon_id' => $hoB->id]);

        // Hai cây chỉ chung nhau ở bậc Ngành — quá rộng để gọi là giống
        // nhau. Trục phân loại phải cho ĐÚNG 0.
        $taste = $this->chanDungTuLichSu(taxonId: $hoA->id);

        $this->assertSame(
            0,
            $taste->taxonScores->get($nganh->id, 0),
            'Điểm phân loại đã lan lên tới bậc Ngành — bậc đó rộng tới mức mọi cây đều dính.',
        );
        $this->assertSame(0, $taste->taxonScores->get($gioi->id, 0));

        // Sản phẩm vẫn xuất hiện, nhưng nhờ trục danh mục — và lý do phải
        // nói đúng như vậy, không được bịa ra quan hệ họ hàng.
        $lyDo = $taste->match($xaLa->load('traits', 'category'))['reason'];

        $this->assertNotNull($lyDo);
        $this->assertStringNotContainsString('Cùng ngành', $lyDo);
        $this->assertStringNotContainsString('Cùng giới', $lyDo);
    }

    #[Test]
    public function nhan_dung_kem_khong_duoc_coi_la_so_thich(): void
    {
        /*
         * `accessory_for` là nhãn NỘI BỘ để tra phụ kiện mua kèm, không
         * phải một sở thích của khách. Để lọt vào chân dung thì mọi phụ
         * kiện gắn "dùng cho mọi loại hàng" sẽ khớp với tất cả mọi người,
         * và khối gợi ý biến thành quầy bán chậu.
         */
        $daXem = $this->sanPham('Đã xem');
        $this->gan($daXem, TraitType::AccessoryFor, 'all');
        $this->xem($daXem);

        $taste = new \App\Services\Recommendation\TasteProfile(collect([
            (object) [
                'event_type' => UserEventType::ProductView,
                'product_id' => $daXem->id,
                'category_id' => null,
                'selling_form' => null,
                'taxon_id' => null,
            ],
        ]));

        $this->assertTrue(
            $taste->traitScores->isEmpty(),
            'Nhãn "dùng kèm" đã lọt vào chân dung sở thích.',
        );
    }

    #[Test]
    public function chua_co_hanh_vi_thi_khong_gia_vo_la_ca_nhan_hoa(): void
    {
        $this->sanPham('Sản phẩm bất kỳ');

        $ket = app(RecommendationService::class)->forViewer(null, 'phien-chua-co-gi', 4);

        $this->assertFalse($ket['personalized']);
        $this->assertSame('Được nhiều người xem', $ket['items']->first()['reason']);
    }

    #[Test]
    public function nguoi_dung_da_dang_nhap_van_duoc_goi_y_theo_dac_diem(): void
    {
        $user = User::factory()->create();

        foreach (['Đã xem 1', 'Đã xem 2'] as $ten) {
            $p = $this->gan($this->sanPham($ten), TraitType::Color, PlantColor::Purple->value);

            UserEvent::query()->create([
                'user_id' => $user->id,
                'session_id' => 'phien-khac',
                'event_type' => UserEventType::AddToCart,
                'product_id' => $p->id,
                'category_id' => $p->category_id,
                'created_at' => now(),
            ]);
        }

        $this->gan($this->sanPham('Ứng viên tím'), TraitType::Color, PlantColor::Purple->value);
        $this->gan($this->sanPham('Ứng viên vàng'), TraitType::Color, PlantColor::Yellow->value);

        $ket = app(RecommendationService::class)->forViewer($user->id, null, 4);

        $this->assertTrue($ket['personalized']);
        $this->assertSame('Ứng viên tím', $ket['items']->first()['product']->name);
    }

    /**
     * Chân dung dựng từ ĐÚNG một lượt xem, để đo riêng một trục.
     *
     * Dựng thẳng từ dòng lịch sử thay vì gọi qua service: cần chạm tới
     * `match()` và các bảng điểm, thứ mà đầu ra của service đã gói lại.
     */
    private function chanDungTuLichSu(?int $taxonId = null): TasteProfile
    {
        return new TasteProfile(collect([
            (object) [
                'event_type' => UserEventType::ProductView,
                'product_id' => Product::where('name', 'like', 'Đã xem%')->orderBy('id')->value('id')
                    ?? Product::where('name', 'Cây họ Ráy')->value('id'),
                'category_id' => $this->danhMuc->id,
                'selling_form' => 'pot',
                'taxon_id' => $taxonId,
            ],
        ]));
    }

    /** @return array{0: PlantTaxon, 1: PlantTaxon} họ và chi */
    private function nhanhPhanLoai(): array
    {
        $gioi = PlantTaxon::create(['rank' => TaxonRank::Kingdom, 'name' => 'Thực vật', 'slug' => 'thuc-vat']);
        $ho = PlantTaxon::create(['parent_id' => $gioi->id, 'rank' => TaxonRank::Family, 'name' => 'Ráy', 'slug' => 'ray']);
        $chi = PlantTaxon::create(['parent_id' => $ho->id, 'rank' => TaxonRank::Genus, 'name' => 'Monstera', 'slug' => 'monstera']);

        return [$ho, $chi];
    }
}
