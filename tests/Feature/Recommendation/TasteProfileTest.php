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

/** Gợi ý theo ĐẶC ĐIỂM và LOÀI. */
class TasteProfileTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();

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
        foreach (['Đã xem 1', 'Đã xem 2', 'Đã xem 3'] as $ten) {
            $this->xem($this->gan($this->sanPham($ten), TraitType::Color, PlantColor::White->value));
        }

        $this->gan($this->sanPham('Ứng viên đỏ'), TraitType::Color, PlantColor::Red->value);
        $this->gan($this->sanPham('Ứng viên tím'), TraitType::Color, PlantColor::Purple->value);
        $this->gan($this->sanPham('Ứng viên trắng'), TraitType::Color, PlantColor::White->value);

        $this->assertSame('Ứng viên trắng', $this->goiY()[0]);
    }

    #[Test]
    public function khop_mot_phan_so_thich_thi_chi_duoc_mot_phan_diem(): void
    {
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

        $this->assertEqualsWithDelta(2.0 * (1 - 1 / 3), $b - $a, 0.0001);
    }

    #[Test]
    public function ly_do_noi_ro_dac_diem_nao_khien_san_pham_xuat_hien(): void
    {
        foreach (['Đã xem 1', 'Đã xem 2'] as $ten) {
            $this->xem($this->gan($this->sanPham($ten), TraitType::Color, PlantColor::White->value));
        }

        $this->gan($this->sanPham('Ứng viên trắng'), TraitType::Color, PlantColor::White->value);

        $this->assertSame('Cũng tông màu trắng', $this->lyDoCho('Ứng viên trắng'));
    }

    #[Test]
    public function san_pham_nhieu_nhan_khong_thang_chi_vi_nhieu_nhan(): void
    {
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

        $this->sanPham('Cây không rõ loài');
        $this->sanPham('Monstera adansonii', ['taxon_id' => $loaiB->id]);

        $this->assertSame('Monstera adansonii', $this->goiY()[0]);
        $this->assertSame('Cùng chi Monstera', $this->lyDoCho('Monstera adansonii'));
    }

    #[Test]
    public function cung_gioi_thuc_vat_KHONG_duoc_tinh_la_giong_nhau(): void
    {
        $gioi = PlantTaxon::create(['rank' => TaxonRank::Kingdom, 'name' => 'Thực vật', 'slug' => 'thuc-vat']);
        $nganh = PlantTaxon::create(['parent_id' => $gioi->id, 'rank' => TaxonRank::Phylum, 'name' => 'Hạt kín', 'slug' => 'hat-kin']);

        $hoA = PlantTaxon::create(['parent_id' => $nganh->id, 'rank' => TaxonRank::Family, 'name' => 'Ráy', 'slug' => 'ray']);
        $hoB = PlantTaxon::create(['parent_id' => $nganh->id, 'rank' => TaxonRank::Family, 'name' => 'Xương rồng', 'slug' => 'xuong-rong']);

        $daXem = $this->sanPham('Cây họ Ráy', ['taxon_id' => $hoA->id]);
        $this->xem($daXem);

        $xaLa = $this->sanPham('Cây họ Xương rồng', ['taxon_id' => $hoB->id]);

        $taste = $this->chanDungTuLichSu(taxonId: $hoA->id);

        $this->assertSame(
            0,
            $taste->taxonScores->get($nganh->id, 0),
            'Điểm phân loại đã lan lên tới bậc Ngành — bậc đó rộng tới mức mọi cây đều dính.',
        );
        $this->assertSame(0, $taste->taxonScores->get($gioi->id, 0));

        $lyDo = $taste->match($xaLa->load('traits', 'category'))['reason'];

        $this->assertNotNull($lyDo);
        $this->assertStringNotContainsString('Cùng ngành', $lyDo);
        $this->assertStringNotContainsString('Cùng giới', $lyDo);
    }

    #[Test]
    public function nhan_dung_kem_khong_duoc_coi_la_so_thich(): void
    {
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

    private function nhanhPhanLoai(): array
    {
        $gioi = PlantTaxon::create(['rank' => TaxonRank::Kingdom, 'name' => 'Thực vật', 'slug' => 'thuc-vat']);
        $ho = PlantTaxon::create(['parent_id' => $gioi->id, 'rank' => TaxonRank::Family, 'name' => 'Ráy', 'slug' => 'ray']);
        $chi = PlantTaxon::create(['parent_id' => $ho->id, 'rank' => TaxonRank::Genus, 'name' => 'Monstera', 'slug' => 'monstera']);

        return [$ho, $chi];
    }
}
