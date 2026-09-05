<?php

namespace App\Services\Recommendation;

use App\Enums\TaxonRank;
use App\Enums\TraitType;
use App\Models\PlantTaxon;
use App\Models\Product;
use App\Models\ProductTrait;
use Illuminate\Support\Collection;

/**
 * Chân dung sở thích của một người xem, dựng từ hành vi công khai.
 * ============================================================
 * ⚠️ RANH GIỚI RIÊNG TƯ — ĐỌC TRƯỚC KHI SỬA.
 *
 * Lớp này CHỈ được đọc `user_events` và những bảng mô tả SẢN PHẨM
 * (`products`, `product_traits`, `plant_taxa`). Ba bảng nhật ký —
 * `journals`, `journal_entries`, `journal_metrics` — TUYỆT ĐỐI không
 * được chạm vào, kể cả gián tiếp qua quan hệ.
 *
 * Cám dỗ là có thật: nhật ký là nơi khách viết thẳng ra họ trồng cây gì,
 * chăm được không, đang chờ giá bao nhiêu — tín hiệu tốt hơn hẳn mọi thứ
 * ở đây. Nhưng khách được hứa là không ai đọc, và lời hứa đó không có
 * ngoại lệ "để phục vụ khách tốt hơn". Xem QĐ-123.
 *
 * `JournalPrivacyTest` bọc `DB::listen()` quanh bộ máy gợi ý và sẽ báo
 * đỏ kèm đúng câu SQL nếu có ai nối vào.
 *
 * ============================================================
 * BỐN TRỤC, MỖI TRỤC MỘT CÂU HỎI KHÁC NHAU
 *
 *   1. Danh mục      — họ đang tìm NHÓM HÀNG nào?
 *   2. Hình thức bán — bó, chậu, giỏ hay hộp? (Guide §4.3: đặc thù ngành)
 *   3. Đặc điểm      — màu gì, dáng gì, đặt ở đâu, hợp mệnh nào?
 *   4. Phân loại     — cùng chi, cùng họ thực vật?
 *
 * Trục 3 và 4 là phần mới. Trước đây bộ máy chỉ biết danh mục và hình
 * thức bán, nên hai chậu sen đá — một xanh một tím — với nó là giống hệt
 * nhau. Khách xem năm cây màu trắng liên tiếp thì đó là một tín hiệu rõ,
 * và cửa hàng đã có sẵn dữ liệu màu cho 33 sản phẩm mà không dùng.
 *
 * ============================================================
 * MỖI TRỤC ĐƯỢC CHUẨN HOÁ VỀ 0..1 RỒI MỚI CỘNG THEO TRỌNG SỐ
 *
 * Cộng thẳng điểm thô là sai, và sai nặng: một sản phẩm mang bảy nhãn
 * đặc điểm sẽ luôn thắng một sản phẩm đúng danh mục nhưng chỉ có hai
 * nhãn — không phải vì nó hợp hơn, mà vì nó được gắn nhiều nhãn hơn.
 * Thứ tự gợi ý khi ấy phản ánh công sức nhập liệu của admin chứ không
 * phản ánh sở thích của khách.
 *
 * Chuẩn hoá xong thì trọng số trục (`AXIS_WEIGHTS`) là chỗ DUY NHẤT
 * tuyên bố cửa hàng tin trục nào quan trọng hơn — sửa một chỗ, đọc được
 * bằng mắt, và không lẫn vào phép tính.
 */
class TasteProfile
{
    /**
     * Trọng số từng loại hành vi.
     *
     * Mua > thích > thêm giỏ > xem, vì mức độ cam kết giảm dần. Xem một
     * sản phẩm có thể chỉ là bấm nhầm; mua thì không.
     *
     * Giữ nguyên thang cũ của `RecommendationService` — đổi thang cùng
     * lúc với việc thêm hai trục mới thì không biết thay đổi nào gây ra
     * khác biệt nào.
     */
    private const WEIGHTS = [
        'purchase' => 5,
        'wishlist' => 4,
        'add_to_cart' => 3,
        'product_view' => 1,
        'category_view' => 1,
        'search' => 0,
    ];

    /**
     * Tầm quan trọng của từng trục, sau khi mỗi trục đã về thang 0..1.
     *
     * Danh mục nặng nhất vì đó là câu hỏi khách tự đặt ra khi bấm vào
     * menu. Đặc điểm và hình thức bán ngang nhau: cả hai đều là "kiểu
     * hàng tôi thích" chứ không phải "thứ tôi đang tìm".
     *
     * Phân loại sinh học nhẹ nhất, và có lý do: phần lớn khách mua cây
     * cảnh không nghĩ theo chi/họ. Nó là tín hiệu phụ để phá thế hoà,
     * không phải trục dẫn dắt — đặt nặng thì trang gợi ý biến thành bài
     * giảng thực vật học.
     */
    private const AXIS_WEIGHTS = [
        'category' => 3.0,
        'trait' => 2.0,
        'form' => 2.0,
        'taxon' => 1.0,
    ];

    /**
     * Bậc phân loại thấp nhất còn đáng gọi là "giống nhau".
     *
     * Mọi cây trong cửa hàng đều cùng Giới Thực vật; nói với khách "vì
     * bạn quan tâm giới Thực vật" là một câu vô nghĩa. Từ bậc HỌ trở
     * xuống (Họ → Chi → Loài) mới là quan hệ đủ hẹp để có ý nghĩa với
     * người mua.
     */
    private const BAC_TOI_THIEU = TaxonRank::Family;

    /** @var Collection<int|string, int> điểm theo category_id */
    public readonly Collection $categoryScores;

    /** @var Collection<string, int> điểm theo giá trị selling_form */
    public readonly Collection $formScores;

    /** @var Collection<string, int> điểm theo khoá "loại:giá_trị" của nhãn */
    public readonly Collection $traitScores;

    /** @var Collection<int, int> điểm theo id nút phân loại (đã lan lên tổ tiên) */
    public readonly Collection $taxonScores;

    /**
     * @param  Collection<int, object>  $history  các dòng user_events đã join
     *                                            sang products
     */
    public function __construct(Collection $history)
    {
        $this->categoryScores = $this->diemTheoCot($history, 'category_id');
        $this->formScores = $this->diemTheoCot($history, 'selling_form');
        $this->traitScores = $this->diemTheoNhan($history);
        $this->taxonScores = $this->diemTheoPhanLoai($history);
    }

    /** Không có tín hiệu nào — người này hoàn toàn xa lạ. */
    public function isEmpty(): bool
    {
        return $this->categoryScores->isEmpty()
            && $this->formScores->isEmpty()
            && $this->traitScores->isEmpty()
            && $this->taxonScores->isEmpty();
    }

    /**
     * Độ hợp của một sản phẩm với chân dung này, và lý do đọc được.
     *
     * Sản phẩm phải đã nạp sẵn `traits` (và `category` nếu muốn có lý do
     * nhắc tên danh mục) — nếu không thì mỗi lần gọi là một truy vấn.
     *
     * @return array{score: float, reason: ?string}
     */
    public function match(Product $product): array
    {
        $truc = [
            'category' => $this->hopDanhMuc($product),
            'form' => $this->hopHinhThuc($product),
            'trait' => $this->hopDacDiem($product),
            'taxon' => $this->hopPhanLoai($product),
        ];

        $diem = 0.0;

        foreach ($truc as $ten => ['affinity' => $doHop]) {
            $diem += self::AXIS_WEIGHTS[$ten] * $doHop;
        }

        return [
            'score' => $diem,
            'reason' => $this->lyDo($truc),
        ];
    }

    /**
     * Trọng số quyết định THỨ TỰ; câu lý do thì chọn theo ĐỘ CỤ THỂ.
     * ============================================================
     * Hai việc khác nhau, và gộp chúng làm hỏng việc thứ hai.
     *
     * Nếu lấy lý do từ trục có điểm nhân trọng số cao nhất thì danh mục
     * (trọng số 3.0) gần như luôn thắng, và mọi gợi ý trên trang đều nói
     * đúng một câu: *"Vì bạn quan tâm Cây để bàn"*. Câu đó đúng, nhưng nó
     * không giải thích vì sao khách thấy CHÍNH sản phẩm này chứ không
     * phải mười sản phẩm khác cùng danh mục — mà đó mới là câu hỏi họ
     * đang có. Hai trục đặc điểm và phân loại khi ấy chạy trong bóng tối:
     * chúng đổi thứ tự nhưng không bao giờ được nhắc tên.
     *
     * Nên xếp theo ĐỘ HẸP: loài/chi hẹp hơn đặc điểm, đặc điểm hẹp hơn
     * hình thức bán, hình thức bán hẹp hơn danh mục. Trục hẹp hơn nói
     * được nhiều hơn.
     *
     * NGƯỠNG là chỗ chặn nói quá: một trục chỉ được đứng ra làm lý do khi
     * độ hợp của nó ít nhất bằng NGUONG_LY_DO. Không có ngưỡng thì một
     * sản phẩm trùng đúng một nhãn phụ sẽ được quảng cáo là "cũng tông
     * màu trắng" trong khi nó lọt vào danh sách chủ yếu nhờ danh mục —
     * tức là một lời giải thích sai.
     *
     * @param  array<string, array{affinity: float, reason: ?string}>  $truc
     */
    private function lyDo(array $truc): ?string
    {
        // Hẹp nhất trước.
        foreach (['taxon', 'trait', 'form', 'category'] as $ten) {
            $t = $truc[$ten];

            if ($t['reason'] !== null && $t['affinity'] >= self::NGUONG_LY_DO) {
                return $t['reason'];
            }
        }

        // Không trục nào đủ mạnh để đứng tên: lấy bất kỳ trục nào có
        // đóng góp thật, còn hơn trả về một gợi ý không lời giải thích.
        foreach (['taxon', 'trait', 'form', 'category'] as $ten) {
            if ($truc[$ten]['reason'] !== null && $truc[$ten]['affinity'] > 0) {
                return $truc[$ten]['reason'];
            }
        }

        return null;
    }

    /**
     * Độ hợp tối thiểu để một trục được đứng ra làm lời giải thích.
     *
     * Một nửa: trục đó phải là mối quan tâm đáng kể của khách, không phải
     * một điểm trùng hợp lẻ.
     */
    private const NGUONG_LY_DO = 0.5;

    /* ================= TỪNG TRỤC ================= */

    /** @return array{affinity: float, reason: ?string} */
    private function hopDanhMuc(Product $product): array
    {
        $diem = (int) ($this->categoryScores[$product->category_id] ?? 0);

        if ($diem <= 0) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        return [
            'affinity' => $diem / (int) $this->categoryScores->max(),
            'reason' => $product->relationLoaded('category') && $product->category
                ? 'Vì bạn quan tâm ' . $product->category->name
                : null,
        ];
    }

    /** @return array{affinity: float, reason: ?string} */
    private function hopHinhThuc(Product $product): array
    {
        $form = $product->selling_form?->value;
        $diem = $form ? (int) ($this->formScores[$form] ?? 0) : 0;

        if ($diem <= 0) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        return [
            'affinity' => $diem / (int) $this->formScores->max(),
            'reason' => 'Cùng hình thức ' . ($product->selling_form?->label() ?? 'bán'),
        ];
    }

    /**
     * Độ hợp về đặc điểm = PHẦN SỞ THÍCH mà sản phẩm này chạm được.
     *
     * Chia cho TỔNG điểm nhãn chứ không chia cho điểm nhãn cao nhất: nhờ
     * vậy sản phẩm khớp nhiều đặc điểm được cộng thêm thật, nhưng vẫn
     * không thể vượt quá 1.0 dù có gắn bao nhiêu nhãn đi nữa. Đó chính
     * là chỗ chặn "ai nhiều nhãn hơn thì thắng".
     *
     * @return array{affinity: float, reason: ?string}
     */
    private function hopDacDiem(Product $product): array
    {
        if ($this->traitScores->isEmpty() || ! $product->relationLoaded('traits')) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $khop = [];

        foreach ($product->traits as $nhan) {
            /*
             * `accessory_for` bị loại: đó là nhãn NỘI BỘ để tra phụ kiện
             * mua kèm, không phải một sở thích. Để lọt vào đây thì mọi
             * phụ kiện "dùng cho mọi loại hàng" sẽ khớp với tất cả mọi
             * người, và khối gợi ý biến thành quầy bán chậu.
             */
            if ($nhan->trait_type === TraitType::AccessoryFor) {
                continue;
            }

            $khoa = $this->khoaNhan($nhan->trait_type, $nhan->trait_value);
            $diem = (int) ($this->traitScores[$khoa] ?? 0);

            if ($diem > 0) {
                $khop[$khoa] = ['diem' => $diem, 'nhan' => $nhan];
            }
        }

        if ($khop === []) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $tong = (int) $this->traitScores->sum();
        $duoc = array_sum(array_column($khop, 'diem'));

        // Nhãn mạnh nhất là nhãn được nêu ra làm lý do.
        $manh = collect($khop)->sortByDesc('diem')->first()['nhan'];

        return [
            'affinity' => min(1.0, $duoc / max(1, $tong)),
            'reason' => $this->lyDoNhan($manh->trait_type, $manh->label()),
        ];
    }

    /**
     * Độ hợp về phân loại sinh học, tính theo TỔ TIÊN CHUNG SÂU NHẤT.
     *
     * Điểm đã được lan sẵn lên toàn bộ chuỗi tổ tiên khi dựng chân dung
     * (xem `diemTheoPhanLoai`), nên ở đây chỉ cần tra nút sâu nhất của
     * sản phẩm có mặt trong bảng điểm.
     *
     * @return array{affinity: float, reason: ?string}
     */
    private function hopPhanLoai(Product $product): array
    {
        if ($this->taxonScores->isEmpty() || ! $product->taxon_id) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $chuoi = $this->chuoiToTien($product->taxon_id);

        // Đi từ nút cụ thể nhất trở lên: Loài khớp thì không cần xét Họ.
        foreach ($chuoi->reverse() as $nut) {
            if ($nut->rank->level() < self::BAC_TOI_THIEU->level()) {
                break;
            }

            $diem = (int) ($this->taxonScores[$nut->id] ?? 0);

            if ($diem > 0) {
                return [
                    'affinity' => $diem / (int) $this->taxonScores->max(),
                    'reason' => 'Cùng ' . mb_strtolower($nut->rank->label()) . ' ' . $nut->name,
                ];
            }
        }

        return ['affinity' => 0.0, 'reason' => null];
    }

    /* ================= DỰNG CHÂN DUNG ================= */

    /** @param  Collection<int, object>  $history */
    private function diemTheoCot(Collection $history, string $cot): Collection
    {
        return $history
            ->filter(fn ($e) => ! empty($e->{$cot}))
            ->groupBy(fn ($e) => (string) $e->{$cot})
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $diem) => $diem > 0)
            ->sortDesc();
    }

    /**
     * Điểm cho từng nhãn đặc điểm.
     *
     * MỘT truy vấn cho toàn bộ sản phẩm trong lịch sử, không phải một
     * truy vấn mỗi sản phẩm. Lịch sử tối đa 60 dòng nên `whereIn` luôn
     * nhỏ.
     *
     * @param  Collection<int, object>  $history
     * @return Collection<string, int>
     */
    private function diemTheoNhan(Collection $history): Collection
    {
        $diemSanPham = $history
            ->filter(fn ($e) => ! empty($e->product_id))
            ->groupBy(fn ($e) => (int) $e->product_id)
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $d) => $d > 0);

        if ($diemSanPham->isEmpty()) {
            return collect();
        }

        $nhan = ProductTrait::query()
            ->whereIn('product_id', $diemSanPham->keys()->all())
            ->where('trait_type', '!=', TraitType::AccessoryFor->value)
            ->get(['product_id', 'trait_type', 'trait_value']);

        $ket = [];

        foreach ($nhan as $n) {
            $khoa = $this->khoaNhan($n->trait_type, $n->trait_value);
            $ket[$khoa] = ($ket[$khoa] ?? 0) + (int) $diemSanPham[(int) $n->product_id];
        }

        return collect($ket)->sortDesc();
    }

    /**
     * Điểm phân loại, LAN LÊN toàn bộ chuỗi tổ tiên.
     *
     * Xem một cây Monstera deliciosa thì không chỉ nút Loài đó được
     * điểm: Chi Monstera và Họ Ráy cũng được, vì đó chính là thứ làm
     * cho Trầu bà — cùng họ, khác chi — trở nên đáng gợi ý.
     *
     * Dừng ở BẬC TỐI THIỂU (Họ). Lan tiếp lên Bộ, Lớp, Ngành, Giới thì
     * mọi cây trong cửa hàng đều dính điểm và trục này mất hết khả năng
     * phân biệt — nó sẽ cộng đúng một hằng số vào mọi sản phẩm.
     *
     * @param  Collection<int, object>  $history
     * @return Collection<int, int>
     */
    private function diemTheoPhanLoai(Collection $history): Collection
    {
        $diemNut = $history
            ->filter(fn ($e) => ! empty($e->taxon_id))
            ->groupBy(fn ($e) => (int) $e->taxon_id)
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $d) => $d > 0);

        if ($diemNut->isEmpty()) {
            return collect();
        }

        $ket = [];

        foreach ($diemNut as $taxonId => $diem) {
            foreach ($this->chuoiToTien((int) $taxonId) as $nut) {
                if ($nut->rank->level() < self::BAC_TOI_THIEU->level()) {
                    continue;
                }

                $ket[$nut->id] = ($ket[$nut->id] ?? 0) + (int) $diem;
            }
        }

        return collect($ket)->sortDesc();
    }

    /* ================= TIỆN ÍCH ================= */

    /**
     * Toàn bộ cây phân loại, nạp MỘT LẦN cho cả vòng đời đối tượng.
     *
     * Cây phân loại của cửa hàng có cỡ vài chục nút. Một câu truy vấn
     * lấy hết rồi dựng quan hệ trong PHP luôn rẻ hơn việc đi ngược lên
     * theo `parent` cho từng sản phẩm ứng viên — thứ sẽ sinh bảy truy
     * vấn mỗi sản phẩm.
     *
     * @var Collection<int, PlantTaxon>|null
     */
    private ?Collection $cayPhanLoai = null;

    /** @return Collection<int, PlantTaxon> chuỗi từ gốc xuống tới nút này */
    private function chuoiToTien(int $taxonId): Collection
    {
        $this->cayPhanLoai ??= PlantTaxon::query()
            ->get(['id', 'parent_id', 'rank', 'name'])
            ->keyBy('id');

        $chuoi = collect();
        $nut = $this->cayPhanLoai->get($taxonId);

        // Trần cứng số bậc: `parent_id` là cột thường và một lần sửa tay
        // có thể tạo ra vòng A→B→A. Xem chú thích ở PlantTaxon::chain().
        $conLai = count(TaxonRank::cases());

        while ($nut && $conLai-- > 0) {
            $chuoi->prepend($nut);
            $nut = $nut->parent_id ? $this->cayPhanLoai->get($nut->parent_id) : null;
        }

        return $chuoi;
    }

    private function khoaNhan(TraitType $type, string $value): string
    {
        return $type->value . ':' . $value;
    }

    /**
     * Lý do viết theo đúng loại nhãn.
     *
     * "Cũng màu trắng" đọc tự nhiên; "Cũng vị trí đặt bàn làm việc" thì
     * không. Mỗi loại nhãn cần một cách nói riêng — gộp thành một mẫu
     * câu duy nhất là tiết kiệm mã nguồn bằng cách làm câu chữ trở nên
     * ngớ ngẩn.
     */
    private function lyDoNhan(TraitType $type, string $nhan): string
    {
        return match ($type) {
            TraitType::Color => 'Cũng tông màu ' . mb_strtolower($nhan),
            TraitType::Shape => 'Cùng dáng ' . mb_strtolower($nhan),
            TraitType::GrowthForm => 'Cùng dạng ' . mb_strtolower($nhan),
            TraitType::Habitat => 'Cùng môi trường sống: ' . $nhan,
            TraitType::Placement => 'Cũng hợp đặt ' . mb_strtolower($nhan),
            TraitType::FengShui => 'Cũng hợp mệnh ' . $nhan,
            TraitType::AccessoryFor => 'Dùng kèm ' . mb_strtolower($nhan),
        };
    }

    private function trongSo(mixed $eventType): int
    {
        $khoa = $eventType instanceof \App\Enums\UserEventType
            ? $eventType->value
            : (string) $eventType;

        return self::WEIGHTS[$khoa] ?? 0;
    }
}
