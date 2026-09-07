<?php

namespace App\Services\Recommendation;

use App\Enums\CareDifficulty;
use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tư vấn chọn cây theo NHU CẦU, không theo lượt xem.
 * ============================================================
 * KHÁC HẲN RecommendationService, và hai lớp không thay thế nhau:
 *
 *   RecommendationService - "dựa trên thứ bạn vừa xem". Suy từ HÀNH VI,
 *                           chỉ chạy được khi khách đã xem vài sản phẩm.
 *   PlantAdvisor          - "bạn cần cây cho chỗ nào?". Suy từ ĐIỀU KIỆN
 *                           khách tự khai, chạy được ngay từ lượt truy
 *                           cập đầu tiên.
 *
 * Người mua cây lần đầu — nhóm đông nhất và bối rối nhất — không có hành
 * vi nào để mà suy. Họ chỉ biết "tôi có cái ban công đầy nắng" hoặc "tôi
 * hay quên tưới". Lớp này trả lời đúng những câu đó.
 *
 * MỌI TIÊU CHÍ ĐỀU ĐỌC TỪ DỮ LIỆU ADMIN NHẬP, không suy diễn:
 * hệ thống không tự đoán cây nào hợp mệnh Kim, không tự đoán cây nào
 * sống được trong phòng tắm. Chưa gán nhãn thì không có kết quả, và giao
 * diện nói thẳng là chưa có — không độn hàng cho đầy.
 */
class PlantAdvisor
{
    /** Số sản phẩm tối đa cho mỗi lần tư vấn. */
    private const LIMIT = 8;

    /**
     * Lọc theo bộ tiêu chí khách chọn. Tiêu chí nào null thì bỏ qua.
     *
     * @return Collection<int, Product>
     */
    public function suggest(
        ?Placement $placement = null,
        ?FengShuiElement $element = null,
        ?CareDifficulty $difficulty = null,
        int $limit = self::LIMIT,
        array $traits = [],
    ): Collection {
        $query = $this->baseQuery();

        if ($placement) {
            $query->withTrait(TraitType::Placement, $placement->value);
        }

        if ($element) {
            $query->withTrait(TraitType::FengShui, $element->value);
        }

        if ($difficulty) {
            $this->onlyDifficulty($query, $difficulty);
        }

        /*
         * NHÃN SINH THÁI — môi trường sống, dạng sống, dáng, màu.
         *
         * NHẬN THEO MẢNG chứ không thêm bốn tham số nữa. Bốn tiêu chí
         * hôm nay, và mỗi lần thêm một tiêu chí lại là một tham số mới
         * cho hàm này cùng mọi nơi gọi nó. Mảng thì thêm một loại nhãn
         * về sau không phải sửa chữ ký hàm.
         *
         * Khoá là `TraitType->value`, nên nơi gọi không tự bịa ra được
         * một loại nhãn không tồn tại.
         */
        foreach ($traits as $loai => $giaTri) {
            $type = TraitType::tryFrom((string) $loai);

            if ($type && $giaTri !== null && $giaTri !== '') {
                $query->withTrait($type, (string) $giaTri);
            }
        }

        return $query->limit($limit)->get();
    }

    /**
     * Các giá trị CÓ HÀNG của một loại nhãn, kèm số lượng và gợi ý.
     *
     * MỘT HÀM CHO MỌI LOẠI NHÃN, thay cho availablePlacements() /
     * availableElements() viết riêng từng cái. Bốn tiêu chí mới (môi
     * trường sống, dạng sống, dáng, màu) dùng chung hàm này, và tiêu chí
     * thứ năm về sau cũng vậy.
     *
     * `hint` lấy được thì lấy: Habitat, GrowthForm và PlantShape đều có
     * hàm hint() giải thích tiêu chí đó nghĩa là gì với người mua. Màu
     * sắc thì không cần — nhìn là biết.
     *
     * @return Collection<int, array{value: string, label: string, hint: ?string, total: int}>
     */
    public function availableTraitValues(TraitType $type): Collection
    {
        $counts = $this->traitCounts($type);

        return collect($type->options())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'hint' => $this->hintFor($type, $value),
                'total' => (int) ($counts[$value] ?? 0),
            ])
            // Không có hàng thì không hiện: một lựa chọn dẫn tới màn hình
            // trống là một lựa chọn không nên bày ra.
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    /**
     * Câu giải thích của một giá trị nhãn, nếu enum tương ứng có.
     *
     * Dùng `method_exists` thay vì một match() liệt kê từng enum: thêm
     * một loại nhãn mới có hint() thì nó tự được dùng, còn loại không có
     * thì trả null — không phải sửa gì ở đây.
     */
    private function hintFor(TraitType $type, string $value): ?string
    {
        $enum = match ($type) {
            TraitType::Habitat => \App\Enums\Habitat::tryFrom($value),
            TraitType::GrowthForm => \App\Enums\GrowthForm::tryFrom($value),
            TraitType::Shape => \App\Enums\PlantShape::tryFrom($value),
            default => null,
        };

        return $enum && method_exists($enum, 'hint') ? $enum->hint() : null;
    }

    /**
     * Số cây ứng với từng mức kinh nghiệm.
     *
     * Giống availablePlacements(): chỉ hiện lựa chọn có hàng thật, và
     * hiện luôn con số để khách biết trước bấm vào sẽ thấy bao nhiêu.
     *
     * @return Collection<int, array{difficulty: CareDifficulty, total: int}>
     */
    public function availableDifficulties(): Collection
    {
        return collect(CareDifficulty::cases())
            ->map(fn (CareDifficulty $d) => [
                'difficulty' => $d,
                'total' => $this->onlyDifficulty($this->baseQuery(), $d)->count(),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    /**
     * PHỤ KIỆN MUA KÈM cho một sản phẩm.
     * ============================================================
     * VÌ SAO KHÔNG NỐI TAY TỪNG CẶP SẢN PHẨM:
     * Cửa hàng có 15 cây và sẽ có thêm; bắt admin vào từng cây chọn
     * "chậu nào hợp" là công việc nhân lên theo cấp số nhân, và cây nhập
     * về sau này sẽ không có phụ kiện nào cho tới khi ai đó nhớ ra.
     *
     * Thay vào đó phụ kiện tự khai "tôi dùng kèm loại hàng nào"
     * (trait `accessory_for` = 'pot' / 'bouquet' / 'all'). Gán một lần
     * cho phụ kiện, áp dụng cho mọi cây cùng hình thức bán, kể cả cây
     * chưa tồn tại.
     *
     * @return Collection<int, Product>
     */
    public function accessoriesFor(Product $product, int $limit = 4): Collection
    {
        $form = $product->selling_form?->value;

        if ($form === null) {
            return collect();
        }

        return $this->baseQuery()
            ->where('id', '!=', $product->id)
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                // 'all' = phụ kiện dùng chung (phân bón, bình tưới).
                ->whereIn('trait_value', [$form, 'all']))
            ->limit($limit)
            ->get();
    }

    /**
     * Phụ kiện gợi ý cho CẢ GIỎ HÀNG.
     *
     * Gom hình thức bán của mọi món trong giỏ rồi tra một lần, thay vì
     * gọi accessoriesFor() cho từng món — giỏ 5 món sẽ thành 5 truy vấn
     * và danh sách đầy trùng lặp.
     *
     * @param  Collection<int, Product>|list<Product>  $products
     * @return Collection<int, Product>
     */
    public function accessoriesForBasket(iterable $products, int $limit = 4): Collection
    {
        $forms = collect($products)
            ->map(fn (Product $p) => $p->selling_form?->value)
            ->filter()
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return collect();
        }

        $ids = collect($products)->pluck('id')->filter()->all();

        return $this->baseQuery()
            ->when($ids !== [], fn ($q) => $q->whereNotIn('id', $ids))
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                ->whereIn('trait_value', $forms->push('all')->all()))
            ->limit($limit)
            ->get();
    }

    /**
     * Cây dễ chăm cho người mới.
     *
     * @return Collection<int, Product>
     */
    public function forBeginners(int $limit = self::LIMIT): Collection
    {
        return $this->onlyDifficulty($this->baseQuery(), CareDifficulty::Easy)
            ->limit($limit)
            ->get();
    }

    /**
     * Các vị trí THẬT SỰ có hàng, kèm số lượng.
     *
     * Trang tư vấn chỉ hiện những lựa chọn dẫn tới kết quả. Hiện đủ bảy
     * vị trí rồi để khách bấm vào bốn chỗ trống là làm họ mất công và
     * mất tin.
     *
     * @return Collection<int, array{placement: Placement, total: int}>
     */
    public function availablePlacements(): Collection
    {
        $counts = $this->traitCounts(TraitType::Placement);

        return collect(Placement::cases())
            ->map(fn (Placement $p) => [
                'placement' => $p,
                'total' => (int) ($counts[$p->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array{element: FengShuiElement, total: int}>
     */
    public function availableElements(): Collection
    {
        $counts = $this->traitCounts(TraitType::FengShui);

        return collect(FengShuiElement::cases())
            ->map(fn (FengShuiElement $e) => [
                'element' => $e,
                'total' => (int) ($counts[$e->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    /* ================= NỘI BỘ ================= */

    /**
     * Đếm số sản phẩm ĐANG BÁN theo từng giá trị của một loại nhãn.
     *
     * Join sang products và lọc trạng thái ngay trong truy vấn: đếm cả
     * sản phẩm đã ẩn thì con số hiện trên nút không khớp với số kết quả
     * bấm vào — kiểu sai nhỏ nhưng làm người dùng nghi ngờ mọi con số
     * khác trên trang.
     *
     * @return array<string, int>
     */
    private function traitCounts(TraitType $type): array
    {
        return \App\Models\ProductTrait::query()
            ->join('products', 'products.id', '=', 'product_traits.product_id')
            ->where('product_traits.trait_type', $type->value)
            ->where('products.status', 'active')
            ->whereNull('products.deleted_at')
            ->selectRaw('product_traits.trait_value, COUNT(*) as total')
            ->groupBy('product_traits.trait_value')
            ->pluck('total', 'trait_value')
            ->all();
    }

    /**
     * Sản phẩm đang bán và CÒN HÀNG.
     *
     * Gợi ý một cây rồi mở ra thấy "Hết hàng" là câu trả lời vô dụng —
     * cùng lý do đã áp cho RecommendationService.
     */
    private function baseQuery(): Builder
    {
        return Product::query()
            ->with(['category', 'promotions'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('track_inventory', false)
                ->orWhere('stock_quantity', '>', 0));
    }

    /**
     * Chỉ lấy cây ở một mức độ khó chăm.
     *
     * Độ khó nằm trong care_info->difficulty — chữ tự do trong JSON, nên
     * so khớp bằng giá trị của enum CareDifficulty chứ không gõ tay chuỗi.
     *
     * ĐIỀU KIỆN HÌNH THỨC BÁN LÀ BẮT BUỘC: hoa cắt cành không có khái
     * niệm "dễ chăm" hay "khó chăm" — chúng tàn sau vài ngày dù chăm kiểu
     * gì. Để lọt vào đây thì bộ lọc "tôi mới trồng cây" sẽ trả về một
     * đống bó hoa, đúng thứ khách KHÔNG hỏi.
     */
    private function onlyDifficulty(Builder $query, CareDifficulty $difficulty): Builder
    {
        // Luật thật nằm ở Product::scopeWithCareDifficulty() — một nơi
        // sở hữu duy nhất, vì trang sản phẩm cũng lọc theo độ khó.
        return $query->withCareDifficulty($difficulty);
    }
}
