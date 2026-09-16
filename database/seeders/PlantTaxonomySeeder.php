<?php

namespace Database\Seeders;

use App\Enums\TaxonRank;
use App\Models\PlantTaxon;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Cây phân loại sinh học + gán sản phẩm vào đúng nhánh. */
class PlantTaxonomySeeder extends Seeder
{
    private array $nut = [];

    public function run(): void
    {
        $this->dungCay();
        $this->ganSanPham();
    }

    private function them(
        string $slug,
        TaxonRank $rank,
        string $name,
        ?string $scientificName = null,
        ?string $parentSlug = null,
        ?string $description = null,
        ?string $image = null,
    ): PlantTaxon {
        $parent = $parentSlug ? ($this->nut[$parentSlug] ?? null) : null;

        $taxon = PlantTaxon::firstOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => $parent?->id,
                'rank' => $rank,
                'name' => $name,
                'scientific_name' => $scientificName,
                'description' => $description,
                'image' => $image,
            ],
        );

        if (! $taxon->wasRecentlyCreated) {
            $taxon->fill(array_filter([
                'name' => $name,
                'scientific_name' => $scientificName,
                'description' => $description,
                'image' => $image,
            ]))->save();
        }

        return $this->nut[$slug] = $taxon;
    }

    private function dungCay(): void
    {
        $this->them('gioi-thuc-vat', TaxonRank::Kingdom, 'Thực vật', 'Plantae', null,
            'Toàn bộ cây xanh — sinh vật tự dưỡng bằng quang hợp.');

        $this->them('nganh-hat-kin', TaxonRank::Phylum, 'Hạt kín', 'Magnoliophyta', 'gioi-thuc-vat',
            'Cây có hoa và có quả bọc hạt. Nhóm đông nhất trong giới thực vật.');

        $this->them('lop-hai-la-mam', TaxonRank::ClassRank, 'Hai lá mầm', 'Magnoliopsida', 'nganh-hat-kin',
            'Hạt có hai lá mầm, gân lá hình mạng, thân thường phân nhánh nhiều.');

        $this->them('bo-hoa-hong', TaxonRank::Order, 'Hoa hồng', 'Rosales', 'lop-hai-la-mam');
        $this->them('ho-hoa-hong', TaxonRank::Family, 'Hoa hồng', 'Rosaceae', 'bo-hoa-hong',
            'Họ lớn gồm hoa hồng, đào, mận, táo — hoa thường 5 cánh.',
            'products/tmcYavg77ioD6YLFCmle8klPMERTeroW9LF2vIpa.jpg');
        $this->them('chi-hoa-hong', TaxonRank::Genus, 'Hoa hồng', 'Rosa', 'ho-hoa-hong',
            'Hoa hồng thương mại hầu hết là giống lai, không quy về một loài đơn.',
            'products/tmcYavg77ioD6YLFCmle8klPMERTeroW9LF2vIpa.jpg');
        $this->them('chi-man-mo-dao', TaxonRank::Genus, 'Mận mơ đào', 'Prunus', 'ho-hoa-hong');
        $this->them('loai-dao', TaxonRank::Species, 'Đào', 'Prunus persica', 'chi-man-mo-dao',
            'Cây đào — nở vào dịp Tết ở miền Bắc.',
            'products/canh-dao-phai-choi-tet.jpg');

        $this->them('bo-cuc', TaxonRank::Order, 'Cúc', 'Asterales', 'lop-hai-la-mam');
        $this->them('ho-cuc', TaxonRank::Family, 'Cúc', 'Asteraceae', 'bo-cuc',
            'Cái mà ta gọi là "bông hoa" thực ra là một cụm gồm rất nhiều hoa nhỏ.',
            'products/huong-duong-ruc-ro.jpg');
        $this->them('chi-huong-duong', TaxonRank::Genus, 'Hướng dương', 'Helianthus', 'ho-cuc');
        $this->them('loai-huong-duong', TaxonRank::Species, 'Hướng dương', 'Helianthus annuus', 'chi-huong-duong',
            'Cụm hoa quay theo hướng mặt trời khi còn non.',
            'products/huong-duong-ruc-ro.jpg');
        $this->them('chi-cuc', TaxonRank::Genus, 'Cúc', 'Chrysanthemum', 'ho-cuc',
            'Cúc cắt cành thương mại là giống lai — dừng ở bậc chi.',
            'products/bo-cuc-hoa-mi-trang.jpg');

        $this->them('bo-cam-chuong', TaxonRank::Order, 'Cẩm chướng', 'Caryophyllales', 'lop-hai-la-mam');
        $this->them('ho-cam-chuong', TaxonRank::Family, 'Cẩm chướng', 'Caryophyllaceae', 'bo-cam-chuong',
            'Hoa nhỏ, nhiều cánh mảnh; gồm cẩm chướng và hoa baby.',
            'products/gio-hoa-baby-trang.jpg');
        $this->them('chi-baby', TaxonRank::Genus, 'Hoa baby', 'Gypsophila', 'ho-cam-chuong');
        $this->them('loai-baby', TaxonRank::Species, 'Hoa baby', 'Gypsophila paniculata', 'chi-baby',
            'Hoa nhỏ li ti, dùng làm nền cho các loài hoa lớn.',
            'products/gio-hoa-baby-trang.jpg');
        $this->them('ho-xuong-rong', TaxonRank::Family, 'Xương rồng', 'Cactaceae', 'bo-cam-chuong',
            'Gai thay cho lá để giảm thoát hơi nước; thân mọng trữ nước.',
            'products/xuong-rong-bi-chau-dat-nung.jpg');

        $this->them('bo-thu-du', TaxonRank::Order, 'Thù du', 'Cornales', 'lop-hai-la-mam');
        $this->them('ho-tu-cau', TaxonRank::Family, 'Tú cầu', 'Hydrangeaceae', 'bo-thu-du',
            'Cụm hoa lớn hình cầu gồm rất nhiều hoa nhỏ.',
            'products/bo-cam-tu-cau-xanh.jpg');
        $this->them('chi-tu-cau', TaxonRank::Genus, 'Tú cầu', 'Hydrangea', 'ho-tu-cau');
        $this->them('loai-cam-tu-cau', TaxonRank::Species, 'Cẩm tú cầu', 'Hydrangea macrophylla', 'chi-tu-cau',
            'Màu hoa đổi theo độ chua của đất: đất chua ra xanh, đất kiềm ra hồng.',
            'products/bo-cam-tu-cau-xanh.jpg');

        $this->them('bo-tai-hum', TaxonRank::Order, 'Tai hùm', 'Saxifragales', 'lop-hai-la-mam');
        $this->them('ho-mau-don', TaxonRank::Family, 'Mẫu đơn', 'Paeoniaceae', 'bo-tai-hum',
            'Hoa to nhiều lớp cánh; cây sống lâu năm.',
            'products/bo-hoa-mau-don-do.jpg');
        $this->them('chi-mau-don', TaxonRank::Genus, 'Mẫu đơn', 'Paeonia', 'ho-mau-don',
            null, 'products/bo-hoa-mau-don-do.jpg');
        $this->them('ho-thuoc-bong', TaxonRank::Family, 'Thuốc bỏng', 'Crassulaceae', 'bo-tai-hum',
            'Nhóm "sen đá" — lá dày trữ nước, chịu hạn rất tốt.',
            'products/sen-da-mix-chau-da.jpg');

        $this->them('bo-bong', TaxonRank::Order, 'Bông', 'Malvales', 'lop-hai-la-mam');
        $this->them('ho-cam-quy', TaxonRank::Family, 'Cẩm quỳ', 'Malvaceae', 'bo-bong',
            'Họ của bông, ca cao và kim ngân — lá thường có lông mịn.',
            'products/kim-ngan-ben-than.jpg');
        $this->them('chi-kim-ngan', TaxonRank::Genus, 'Kim ngân', 'Pachira', 'ho-cam-quy');
        $this->them('loai-kim-ngan', TaxonRank::Species, 'Kim ngân', 'Pachira aquatica', 'chi-kim-ngan',
            'Thân dẻo nên bện được; trong tự nhiên mọc ở vùng đầm lầy.',
            'products/kim-ngan-ben-than.jpg');

        $this->them('bo-long-dom', TaxonRank::Order, 'Long đởm', 'Gentianales', 'lop-hai-la-mam');
        $this->them('ho-truc-dao', TaxonRank::Family, 'Trúc đào', 'Apocynaceae', 'bo-long-dom',
            'Thân có nhựa mủ trắng; nhiều loài trong họ có độc.',
            'products/bonsai-mai-chieu-thuy.jpg');
        $this->them('chi-mai-chieu-thuy', TaxonRank::Genus, 'Mai chiếu thuỷ', 'Wrightia', 'ho-truc-dao');
        $this->them('loai-mai-chieu-thuy', TaxonRank::Species, 'Mai chiếu thuỷ', 'Wrightia religiosa', 'chi-mai-chieu-thuy',
            'Hoa trắng nhỏ hướng xuống đất — tên gọi bắt nguồn từ đó.',
            'products/bonsai-mai-chieu-thuy.jpg');

        $this->them('lop-mot-la-mam', TaxonRank::ClassRank, 'Một lá mầm', 'Liliopsida', 'nganh-hat-kin',
            'Hạt có một lá mầm, gân lá song song. Gồm lúa, cau dừa, lan, ráy.');

        $this->them('bo-trach-ta', TaxonRank::Order, 'Trạch tả', 'Alismatales', 'lop-mot-la-mam');
        $this->them('ho-ray', TaxonRank::Family, 'Ráy', 'Araceae', 'bo-trach-ta',
            'Họ của phần lớn cây cảnh trồng trong nhà: chịu bóng giỏi, lá to bản.',
            'products/monstera-deliciosa.jpg');
        $this->them('chi-monstera', TaxonRank::Genus, 'Trầu bà lá xẻ', 'Monstera', 'ho-ray');
        $this->them('loai-monstera-deliciosa', TaxonRank::Species, 'Trầu bà lá xẻ', 'Monstera deliciosa', 'chi-monstera',
            'Lá xẻ thuỳ khi cây trưởng thành; trong tự nhiên bám thân cây khác leo lên.',
            'products/monstera-deliciosa.jpg');
        $this->them('chi-kim-tien', TaxonRank::Genus, 'Kim tiền', 'Zamioculcas', 'ho-ray');
        $this->them('loai-kim-tien', TaxonRank::Species, 'Kim tiền', 'Zamioculcas zamiifolia', 'chi-kim-tien',
            'Thân củ dưới đất trữ nước — sống được rất lâu không tưới.',
            'products/kim-tien-chau-su.jpg');
        $this->them('chi-trau-ba', TaxonRank::Genus, 'Trầu bà', 'Epipremnum', 'ho-ray');
        $this->them('loai-trau-ba', TaxonRank::Species, 'Trầu bà vàng', 'Epipremnum aureum', 'chi-trau-ba',
            'Leo bám bằng rễ khí sinh; để rủ hay cho leo cột đều được.',
            'products/trau-ba-leo-cot.jpg');
        $this->them('chi-lan-y', TaxonRank::Genus, 'Lan ý', 'Spathiphyllum', 'ho-ray',
            '"Hoa" trắng thực ra là mo bao quanh cụm hoa thật.',
            'products/cay-lan-y-chau-su-trang.jpg');

        $this->them('bo-loa-ken', TaxonRank::Order, 'Loa kèn', 'Liliales', 'lop-mot-la-mam');
        $this->them('ho-loa-ken', TaxonRank::Family, 'Loa kèn', 'Liliaceae', 'bo-loa-ken',
            'Mọc từ củ, hoa sáu cánh xếp thành hai vòng.',
            'products/bo-tulip-ha-lan.jpg');
        $this->them('chi-tulip', TaxonRank::Genus, 'Tulip', 'Tulipa', 'ho-loa-ken');
        $this->them('loai-tulip', TaxonRank::Species, 'Tulip vườn', 'Tulipa gesneriana', 'chi-tulip',
            'Mọc từ củ; cần một mùa lạnh để ra hoa.',
            'products/bo-tulip-ha-lan.jpg');

        $this->them('bo-mang-tay', TaxonRank::Order, 'Măng tây', 'Asparagales', 'lop-mot-la-mam');
        $this->them('ho-lan', TaxonRank::Family, 'Lan', 'Orchidaceae', 'bo-mang-tay',
            'Họ lớn nhất của thực vật có hoa; phần lớn sống bám trên thân cây khác.',
            'products/lan-ho-diep-tim-chau-su.jpg');
        $this->them('chi-ho-diep', TaxonRank::Genus, 'Lan hồ điệp', 'Phalaenopsis', 'ho-lan',
            'Lan hồ điệp bán trên thị trường gần như đều là giống lai.',
            'products/lan-ho-diep-tim-chau-su.jpg');
        $this->them('ho-mang-tay', TaxonRank::Family, 'Măng tây', 'Asparagaceae', 'bo-mang-tay',
            'Gồm măng tây, lưỡi hổ, huyết dụ — phần lớn rất chịu khô.',
            'products/luoi-ho-mini-de-ban.jpg');
        $this->them('chi-huyet-du', TaxonRank::Genus, 'Huyết giác', 'Dracaena', 'ho-mang-tay',
            'Trước đây lưỡi hổ được xếp vào chi Sansevieria; nay gộp vào chi này.');
        $this->them('loai-luoi-ho', TaxonRank::Species, 'Lưỡi hổ', 'Dracaena trifasciata', 'chi-huyet-du',
            'Trước đây xếp vào chi Sansevieria; chịu khô và thiếu sáng rất giỏi.',
            'products/luoi-ho-mini-de-ban.jpg');

        $this->them('nganh-duong-xi', TaxonRank::Phylum, 'Dương xỉ', 'Polypodiophyta', 'gioi-thuc-vat',
            'Không có hoa và không có hạt — sinh sản bằng bào tử ở mặt dưới lá.',
            'products/duong-xi-boston-treo.jpg');
        $this->them('lop-duong-xi', TaxonRank::ClassRank, 'Dương xỉ', 'Polypodiopsida', 'nganh-duong-xi');
        $this->them('bo-duong-xi', TaxonRank::Order, 'Dương xỉ', 'Polypodiales', 'lop-duong-xi');
        $this->them('ho-rang-than', TaxonRank::Family, 'Ráng thận', 'Nephrolepidaceae', 'bo-duong-xi',
            'Dương xỉ có lá kép lông chim, thường trồng chậu treo.',
            'products/duong-xi-boston-treo.jpg');
        $this->them('chi-rang-than', TaxonRank::Genus, 'Ráng thận', 'Nephrolepis', 'ho-rang-than');
        $this->them('loai-duong-xi-boston', TaxonRank::Species, 'Dương xỉ Boston', 'Nephrolepis exaltata', 'chi-rang-than',
            'Ưa ẩm cao; lá khô mép là dấu hiệu không khí quá khô.',
            'products/duong-xi-boston-treo.jpg');

        $this->them('nganh-thong', TaxonRank::Phylum, 'Thông', 'Pinophyta', 'gioi-thuc-vat',
            'Hạt trần, không có hoa thật; lá thường hình kim hoặc vảy.',
            'products/bonsai-tung-la-han-dang-truc.jpg');
        $this->them('lop-thong', TaxonRank::ClassRank, 'Thông', 'Pinopsida', 'nganh-thong');
        $this->them('bo-thong', TaxonRank::Order, 'Thông', 'Pinales', 'lop-thong');
        $this->them('ho-kim-giao', TaxonRank::Family, 'Kim giao', 'Podocarpaceae', 'bo-thong',
            'Cây hạt trần nhiệt đới, lá dẹt chứ không hình kim.',
            'products/bonsai-tung-la-han-dang-truc.jpg');
        $this->them('chi-tung-la-han', TaxonRank::Genus, 'Tùng la hán', 'Podocarpus', 'ho-kim-giao');
        $this->them('loai-tung-la-han', TaxonRank::Species, 'Tùng la hán', 'Podocarpus macrophyllus', 'chi-tung-la-han',
            'Lá dẹt chứ không hình kim; sống rất lâu năm, hay dùng làm bonsai.',
            'products/bonsai-tung-la-han-dang-truc.jpg');
    }

    private function ghiChuDungSom(): array
    {
        return [
            'Hoa hồng đỏ Ecuador' => 'Hoa hồng cắt cành thương mại là giống lai (Rosa × hybrida), '
                . 'không quy về một loài tự nhiên nào — nên dừng ở bậc chi.',
            'Hộp hoa hồng pastel' => 'Hoa hồng cắt cành thương mại là giống lai (Rosa × hybrida), '
                . 'không quy về một loài tự nhiên nào — nên dừng ở bậc chi.',
            'Bó cúc hoạ mi trắng' => 'Cúc cắt cành trên thị trường là giống lai qua nhiều đời, '
                . 'không xác định được loài gốc — nên dừng ở bậc chi.',
            'Bó hoa mẫu đơn đỏ' => 'Mẫu đơn cắt cành là giống trồng lai tạo, '
                . 'không ứng với một loài hoang dã cụ thể — nên dừng ở bậc chi.',
            'Lan hồ điệp tím chậu sứ' => 'Gần như toàn bộ lan hồ điệp bán trên thị trường là giống lai '
                . 'giữa nhiều loài Phalaenopsis — nên dừng ở bậc chi.',
            'Cây lan ý chậu sứ trắng' => 'Lan ý trồng chậu thường là giống lai của Spathiphyllum wallisii, '
                . 'khó khẳng định loài chính xác — nên dừng ở bậc chi.',

            'Vạn niên thanh chậu sứ' => 'Ở Việt Nam "vạn niên thanh" được dùng cho vài chi khác nhau '
                . 'trong họ Ráy (Dieffenbachia, Aglaonema) — nên dừng ở bậc họ.',
            'Sen đá mix chậu đá' => 'Một chậu gồm nhiều loài sen đá khác nhau, '
                . 'không có một loài đại diện — nên dừng ở bậc họ.',
            'Sen đá nâu chậu sứ mini' => '"Sen đá nâu" là tên gọi theo màu ngoài chợ, '
                . 'không ứng với một loài xác định — nên dừng ở bậc họ.',
            'Sen đá kim cương chậu treo' => '"Sen đá kim cương" là tên gọi thương mại, '
                . 'không ứng với một loài xác định — nên dừng ở bậc họ.',
            'Xương rồng bi chậu đất nung' => '"Xương rồng bi" là tên gọi theo hình dáng ngoài chợ, '
                . 'dùng cho nhiều chi xương rồng thân cầu — nên dừng ở bậc họ.',
        ];
    }

    private function ganSanPham(): void
    {
        $ban = [
            'Hoa hồng đỏ Ecuador' => 'chi-hoa-hong',
            'Hộp hoa hồng pastel' => 'chi-hoa-hong',
            'Cành đào phai chơi Tết' => 'loai-dao',
            'Hướng dương rực rỡ' => 'loai-huong-duong',
            'Giỏ hoa hướng dương mini' => 'loai-huong-duong',
            'Bó cúc hoạ mi trắng' => 'chi-cuc',
            'Giỏ hoa baby trắng' => 'loai-baby',
            'Bó cẩm tú cầu xanh' => 'loai-cam-tu-cau',
            'Bó hoa mẫu đơn đỏ' => 'chi-mau-don',
            'Bó tulip Hà Lan' => 'loai-tulip',
            'Hộp hoa tulip vàng' => 'loai-tulip',

            'Monstera Deliciosa chậu gốm' => 'loai-monstera-deliciosa',
            'Kim tiền chậu sứ' => 'loai-kim-tien',
            'Trầu bà leo cột' => 'loai-trau-ba',
            'Cây lan ý chậu sứ trắng' => 'chi-lan-y',

            'Vạn niên thanh chậu sứ' => 'ho-ray',

            'Kim ngân bện thân' => 'loai-kim-ngan',
            'Lan hồ điệp tím chậu sứ' => 'chi-ho-diep',
            'Lưỡi hổ mini để bàn' => 'loai-luoi-ho',
            'Lưỡi hổ vàng viền để bàn' => 'loai-luoi-ho',
            'Dương xỉ Boston treo' => 'loai-duong-xi-boston',
            'Bonsai tùng la hán dáng trực' => 'loai-tung-la-han',
            'Bonsai mai chiếu thuỷ' => 'loai-mai-chieu-thuy',

            'Sen đá mix chậu đá' => 'ho-thuoc-bong',
            'Sen đá nâu chậu sứ mini' => 'ho-thuoc-bong',
            'Sen đá kim cương chậu treo' => 'ho-thuoc-bong',
            'Xương rồng bi chậu đất nung' => 'ho-xuong-rong',
        ];

        $ghiChu = $this->ghiChuDungSom();
        $daGan = 0;
        $thieuGhiChu = [];

        foreach ($ban as $tenSanPham => $slug) {
            $taxon = $this->nut[$slug] ?? null;
            $product = Product::where('name', $tenSanPham)->first();

            if (! $taxon || ! $product) {
                continue;
            }

            $product->taxon_id = $taxon->id;

            $product->taxon_note = $taxon->rank === TaxonRank::Species
                ? null
                : ($ghiChu[$tenSanPham] ?? null);

            $product->saveQuietly();
            $daGan++;
        }

        foreach ($ban as $tenSanPham => $slug) {
            $taxon = $this->nut[$slug] ?? null;

            if ($taxon && $taxon->rank !== TaxonRank::Species && ! isset($ghiChu[$tenSanPham])) {
                $thieuGhiChu[] = $tenSanPham;
            }
        }

        $this->command?->info(sprintf(
            'Phân loại: %d nút, đã gắn %d sản phẩm (những sản phẩm phối nhiều loài cố ý để trống).',
            PlantTaxon::count(),
            $daGan,
        ));

        if ($thieuGhiChu) {
            $this->command?->warn(
                'Dừng trên bậc Loài mà chưa giải thích vì sao: ' . implode(', ', $thieuGhiChu)
            );
        }
    }
}
