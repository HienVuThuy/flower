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
use App\Models\ProductVariant;
use App\Services\Media\ImageStore;
use App\Models\Promotion;
use App\Services\Pricing\PricingService;
use App\Services\Theme\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
            ->withCount(['products', 'products as dong_qua_count' => fn ($q) => $q->where('promotion_product.discount_type', PromotionType::TangQua->value)])
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
        $promotion->load(['products.category', 'giftItem']);

        return view('admin.promotions.edit', array_merge($this->formData(), [
            'promotion' => $promotion,
            'sanPhamLamQua' => Product::query()
                ->whereIn('status', ['active', 'out_of_stock'])
                ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('name')
                ->get(['id', 'name']),
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
        $basePrices = Product::whereIn('id', array_column($rows, 'id'))->pluck('base_price', 'id');

        $payload = DB::transaction(function () use ($rows, $promotion, $basePrices) {
            $payload = [];

            foreach ($rows as $i => $row) {
                $rieng = ! empty($row['discount_type']) ? PromotionType::from($row['discount_type']) : null;
                $kieu = $rieng ?? $promotion->type;

                if ($kieu === PromotionType::TangQua) {
                    $payload[$row['id']] = [
                        'discount_type' => $rieng?->value,
                        'discount_value' => null,
                        'promotional_price' => null,
                        'gift_item_id' => $this->vatPhamQua($row['qua'] ?? null, "products.{$i}.qua"),
                        'gift_quantity' => $row['gift_quantity'] ?? null,
                    ];

                    continue;
                }

                $coMucRieng = ($row['discount_value'] ?? '') !== '' && $row['discount_value'] !== null;

                $payload[$row['id']] = [
                    'discount_type' => $rieng?->value,
                    'discount_value' => $coMucRieng ? $row['discount_value'] : null,
                    'promotional_price' => $this->pricing->preview(
                        $basePrices[$row['id']] ?? null,
                        $kieu,
                        $coMucRieng ? (float) $row['discount_value'] : (float) $promotion->discount_value,
                    ),
                    'gift_item_id' => null,
                    'gift_quantity' => null,
                ];
            }

            return $payload;
        });

        $promotion->products()->sync($payload);

        return redirect()
            ->route('admin.promotions.edit', $promotion)
            ->with('success', 'Đã cập nhật danh sách sản phẩm và ưu đãi từng sản phẩm.');
    }

    /** "vp:ID" = vật phẩm quà có sẵn, "sp:ID[:quy cách]" = lấy sản phẩm đang bán làm quà. */
    private function vatPhamQua(?string $ma, string $o): ?int
    {
        if ($ma === null || $ma === '') {
            return null;
        }

        $phan = explode(':', $ma);

        if ($phan[0] === 'vp') {
            return GiftItem::whereKey((int) $phan[1])->value('id')
                ?? throw ValidationException::withMessages([$o => 'Vật phẩm quà không còn tồn tại.']);
        }

        $sp = Product::find((int) $phan[1]) ?? throw ValidationException::withMessages([$o => 'Sản phẩm làm quà không còn tồn tại.']);
        $qc = isset($phan[2]) ? (int) $phan[2] : null;

        if ($qc !== null && ! ProductVariant::whereKey($qc)->where('product_id', $sp->id)->exists()) {
            throw ValidationException::withMessages([$o => 'Quy cách không thuộc sản phẩm đã chọn làm quà.']);
        }

        return GiftItem::tuSanPham($sp, $qc)->id;
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
        $data['first_order_only'] = $request->boolean('first_order_only');

        if ($data['type'] === PromotionType::TangQua->value) {
            return $data;
        }

        return ['gift_item_id' => null, 'gift_quantity' => 1] + $data;
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
