<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\Placement;
use App\Enums\SellingForm;
use App\Enums\ShoppingIntent;
use App\Enums\TraitType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Trang hướng dẫn theo NHU CẦU ("Chọn theo nhu cầu" ở trang chủ).
 * ============================================================
 * TRƯỚC KHI CÓ TRANG NÀY: bốn thẻ trên trang chủ chỉ là liên kết tới
 * `/san-pham?selling_form=bouquet`. Khách bấm "Người mới bắt đầu trồng
 * cây" và rơi vào một lưới sản phẩm đã lọc — đúng hàng, nhưng không trả
 * lời câu hỏi thật của họ: bắt đầu từ đâu, cần mua thêm gì, chăm thế nào
 * để cây không chết trong hai tuần.
 *
 * Trang này trả lời câu hỏi đó TRƯỚC, rồi mới đưa hàng ra.
 *
 * BỐN KHỐI, cùng một khuôn cho cả bốn nhu cầu:
 *   1. Dẫn nhập     — nói thẳng trang này giúp được gì
 *   2. Hướng dẫn    — các bước hoặc lưu ý, viết cho người chưa biết gì
 *   3. Hàng gợi ý   — sản phẩm chọn theo ĐÚNG tiêu chí của nhu cầu đó
 *   4. Mua kèm      — dụng cụ/vật tư cần cho nhu cầu đó (nếu có)
 *
 * NỘI DUNG HƯỚNG DẪN NẰM TRONG BLADE, không trong cơ sở dữ liệu: nó là
 * bài viết có cấu trúc, không phải dữ liệu để lọc hay đếm. Xem ghi chú ở
 * App\Enums\ShoppingIntent.
 */
class IntentController extends Controller
{
    public function __construct(
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function show(string $intent): View
    {
        /*
         * tryFrom + abort 404: đường dẫn lạ thì trả 404 thật, không nhận
         * bừa rồi hiện một trang trống. Khác với trang tư vấn (ở đó tham
         * số hỏng chỉ là một bộ lọc bị bỏ qua) — ở đây tham số CHÍNH LÀ
         * trang, không có nó thì không có gì để hiện.
         */
        $case = ShoppingIntent::tryFrom($intent);

        abort_if($case === null, 404);

        return view('shop.intents.show', [
            'intent' => $case,
            'products' => $this->productsFor($case),
            'extras' => $this->extrasFor($case),
        ]);
    }

    /**
     * Hàng gợi ý cho một nhu cầu.
     *
     * Mỗi nhu cầu một tiêu chí khác hẳn nhau — đó là lý do trang này tồn
     * tại thay vì một bộ lọc dùng chung.
     *
     * @return Collection<int, Product>
     */
    private function productsFor(ShoppingIntent $intent): Collection
    {
        return match ($intent) {
            /*
             * QUÀ TẶNG: hàng đã gói sẵn, cầm đi tặng được ngay. Bó, hộp,
             * giỏ — không phải chậu cây trần.
             */
            ShoppingIntent::Gift => $this->base()
                ->whereIn('selling_form', [
                    SellingForm::Bouquet->value,
                    SellingForm::Box->value,
                    SellingForm::Basket->value,
                    SellingForm::Set->value,
                ])
                ->orderByEffectivePrice('asc')
                ->take(8)
                ->get(),

            /*
             * TRANG TRÍ: cây sống, đặt được TRONG NHÀ. Lọc theo nhãn vị
             * trí chứ không theo hình thức bán — cùng là "cây chậu" nhưng
             * bonsai sân vườn không phải thứ để trang trí phòng khách.
             */
            ShoppingIntent::Decor => $this->base()
                ->whereHas('traits', fn ($q) => $q
                    ->where('trait_type', TraitType::Placement->value)
                    ->whereIn('trait_value', [
                        Placement::LivingRoom->value,
                        Placement::Bedroom->value,
                        Placement::Desk->value,
                        Placement::WindowSill->value,
                        Placement::Hallway->value,
                    ]))
                ->take(8)
                ->get(),

            /*
             * SỰ KIỆN: lẵng, kệ, và hàng làm theo yêu cầu. Hàng sự kiện
             * thường không quản lý tồn kho vì làm theo đơn — nên KHÔNG
             * lọc còn hàng ở đây, base() đã cho phép track_inventory=false.
             */
            ShoppingIntent::Event => $this->base()
                ->where(fn (Builder $q) => $q
                    ->whereIn('selling_form', [
                        SellingForm::Arrangement->value,
                        SellingForm::Basket->value,
                    ])
                    ->orWhereHas('category', fn ($c) => $c->where('slug', 'hoa-khai-truong-su-kien')))
                ->take(8)
                ->get(),

            // NGƯỜI MỚI: đúng bộ lọc của trang tư vấn, dùng lại nguyên vẹn.
            ShoppingIntent::Beginner => $this->advisor->suggest(
                difficulty: CareDifficulty::Easy,
                limit: 8,
            ),
        };
    }

    /**
     * Dụng cụ / vật tư nên mua kèm cho nhu cầu đó.
     *
     * Trả về rỗng cho nhu cầu không cần gì thêm — và Blade sẽ KHÔNG render
     * khối đó. Hiện một khối "mua kèm" trống chỉ để đủ bố cục là hiện một
     * lời mời rỗng.
     *
     * @return Collection<int, Product>
     */
    private function extrasFor(ShoppingIntent $intent): Collection
    {
        $forms = match ($intent) {
            // Người mới và người trang trí đều mua cây chậu -> cần chậu,
            // đất, phân, bình tưới.
            ShoppingIntent::Beginner, ShoppingIntent::Decor => ['pot', 'original', 'set'],
            // Quà tặng và sự kiện là hoa cắt -> cần bình cắm, dưỡng hoa.
            ShoppingIntent::Gift, ShoppingIntent::Event => ['bouquet', 'basket', 'box', 'arrangement'],
        };

        return Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->supplyCatalog()
            ->where('status', 'active')
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                ->whereIn('trait_value', array_merge($forms, ['all'])))
            ->orderByEffectivePrice('asc')
            ->take(4)
            ->get();
    }

    /** Hàng chính, đang bán, còn bán được. */
    private function base(): Builder
    {
        return Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->where('status', 'active');
    }
}
