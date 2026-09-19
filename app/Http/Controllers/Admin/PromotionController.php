<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\SyncPromotionProductsRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Product;
use App\Services\Media\ImageStore;
use App\Models\Promotion;
use App\Services\Pricing\PricingService;
use App\Services\Theme\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PromotionController extends Controller
{
    use LogsAdminActivity;

    public function __construct(
        private readonly PricingService $pricing,
        private readonly ThemeRegistry $themes,
        private readonly ImageStore $anh,
    ) {}

    public function index(Request $request): View
    {
        $promotions = Promotion::query()
            ->withCount('products')
            ->with('giftItem:id,name')
            ->when($request->filled('q'), fn ($q) => $q
                ->where('name', 'like', '%'.trim((string) $request->query('q')).'%'))

            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            ->orderByDesc('priority')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.promotions.index', [
            'promotions' => $promotions,
            'statuses' => PromotionStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.promotions.create', $this->formData());
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $this->chuanHoa($request->validated(), $request);

        if ($request->hasFile('banner')) {
            $data['banner'] = $this->anh->luu($request->file('banner'), 'promotions');
        }

        $promotion = Promotion::create($data);

        $this->logCrud('promotion.created', $promotion, 'chương trình khuyến mại', $promotion->name);

        return redirect()
            ->route('admin.promotions.edit', $promotion)
            ->with('success', 'Đã tạo chương trình. Giờ hãy chọn sản phẩm áp dụng.');
    }

    public function edit(Promotion $promotion): View
    {
        $promotion->load(['products.category']);

        return view('admin.promotions.edit', array_merge($this->formData(), [
            'promotion' => $promotion,
            'availableProducts' => Product::query()
                ->with('category')
                ->whereNotIn('id', $promotion->products->pluck('id'))
                ->orderBy('name')
                ->get(),
            'pricing' => $this->pricing,
        ]));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $data = $this->chuanHoa($request->validated(), $request);
        $oldBanner = $promotion->banner;
        $newBanner = null;

        if ($request->hasFile('banner')) {
            $newBanner = $this->anh->luu($request->file('banner'), 'promotions');
            $data['banner'] = $newBanner;
        }

        try {
            $promotion->update($data);
        } catch (\Throwable $e) {
            if ($newBanner) {
                $this->anh->xoa($newBanner);
            }

            throw $e;
        }

        if ($newBanner && $oldBanner) {
            $this->anh->xoa($oldBanner);
        }

        return redirect()
            ->route('admin.promotions.edit', $promotion)
            ->with('success', 'Đã cập nhật chương trình.');
    }

    public function syncProducts(
        SyncPromotionProductsRequest $request,
        Promotion $promotion
    ): RedirectResponse {
        $rows = $request->validated()['products'] ?? [];

        if ($promotion->laTangQua()) {
            $promotion->products()->sync(collect($rows)->mapWithKeys(fn ($row) => [$row['id'] => [
                'discount_type' => null,
                'discount_value' => null,
                'promotional_price' => null,
            ]])->all());

            return redirect()
                ->route('admin.promotions.edit', $promotion)
                ->with('success', 'Đã cập nhật danh sách sản phẩm của chương trình.');
        }

        $productIds = array_column($rows, 'id');
        $basePrices = Product::whereIn('id', $productIds)->pluck('base_price', 'id');

        $payload = [];

        foreach ($rows as $row) {
            $type = ! empty($row['discount_type'])
                ? PromotionType::from($row['discount_type'])
                : $promotion->type;

            $value = ($row['discount_value'] ?? null) !== null && $row['discount_value'] !== ''
                ? (float) $row['discount_value']
                : (float) $promotion->discount_value;

            $payload[$row['id']] = [
                'discount_type' => $row['discount_type'] ?: null,
                'discount_value' => ($row['discount_value'] ?? '') !== '' ? $row['discount_value'] : null,
                'promotional_price' => $this->pricing->preview(
                    $basePrices[$row['id']] ?? null,
                    $type,
                    $value
                ),
            ];
        }

        $promotion->products()->sync($payload);

        return redirect()
            ->route('admin.promotions.edit', $promotion)
            ->with('success', 'Đã cập nhật danh sách sản phẩm áp dụng.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        if ($promotion->used_count > 0) {
            return back()->with('error', 'Chương trình đã phát quà nên không xoá được. Hãy chuyển trạng thái sang "Đã kết thúc".');
        }

        if ($promotion->banner) {
            $this->anh->xoa($promotion->banner);
        }

        $this->logCrud('promotion.deleted', $promotion, 'chương trình khuyến mại', $promotion->name);

        $promotion->delete();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Đã xóa chương trình khuyến mại.');
    }

    private function chuanHoa(array $data, StorePromotionRequest $request): array
    {
        if ($data['type'] === PromotionType::TangQua->value) {
            $data['first_order_only'] = $request->boolean('first_order_only');

            return $data;
        }

        return $data + [
            'gift_item_id' => null,
            'gift_quantity' => 1,
            'min_order_amount' => null,
            'min_member_tier_id' => null,
            'first_order_only' => false,
            'per_user_limit' => null,
            'total_limit' => null,
        ];
    }

    private function formData(): array
    {
        return [
            'types' => PromotionType::selectable(),
            'kieuGiam' => PromotionType::kieuGiamGia(),
            'vatPham' => GiftItem::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']),
            'cacHang' => MemberTier::query()->orderBy('min_spend')->get(['id', 'name']),
            'statuses' => PromotionStatus::cases(),
            'themeOptions' => $this->themes->options(),
        ];
    }
}
