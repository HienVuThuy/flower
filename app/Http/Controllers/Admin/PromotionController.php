<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\SyncPromotionProductsRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
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
        $data = $request->validated();

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
            // Danh sách để admin chọn thêm sản phẩm vào chương trình.
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
        $data = $request->validated();
        $oldBanner = $promotion->banner;
        $newBanner = null;

        if ($request->hasFile('banner')) {
            $newBanner = $this->anh->luu($request->file('banner'), 'promotions');
            $data['banner'] = $newBanner;
        }

        try {
            $promotion->update($data);
        } catch (\Throwable $e) {
            // Update hỏng thì dọn ảnh vừa upload, tránh rác trong storage.
            if ($newBanner) {
                Storage::disk('public')->delete($newBanner);
            }

            throw $e;
        }

        if ($newBanner && $oldBanner) {
            Storage::disk('public')->delete($oldBanner);
        }

        return redirect()
            ->route('admin.promotions.edit', $promotion)
            ->with('success', 'Đã cập nhật chương trình.');
    }

    /**
     * Đồng bộ danh sách sản phẩm áp dụng.
     *
     * Tính sẵn promotional_price để bảng admin hiển thị nhanh mà
     * không phải tính lại cho từng dòng. Giá hiển thị cho KHÁCH vẫn
     * do PricingService tính lúc render, nên cột này chỉ là cache.
     */
    public function syncProducts(
        SyncPromotionProductsRequest $request,
        Promotion $promotion
    ): RedirectResponse {
        $rows = $request->validated()['products'] ?? [];

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
        if ($promotion->banner) {
            Storage::disk('public')->delete($promotion->banner);
        }

        // promotion_product có onDelete cascade nên các liên kết
        // tự biến mất; sản phẩm KHÔNG bị ảnh hưởng.
        // Ghi trước khi xoá, để dòng nhật ký còn giữ đúng khoá chính.
        $this->logCrud('promotion.deleted', $promotion, 'chương trình khuyến mại', $promotion->name);

        $promotion->delete();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Đã xóa chương trình khuyến mại.');
    }

    private function formData(): array
    {
        return [
            'types' => PromotionType::selectable(),
            'statuses' => PromotionStatus::cases(),
            'themeOptions' => $this->themes->options(),
        ];
    }
}
