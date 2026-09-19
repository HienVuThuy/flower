<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkAction;
use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Enums\TraitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxClass;
use App\Services\Media\ImageStore;
use App\Services\Product\ProductBlockService;
use App\Services\Product\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HandlesBulkAction;
    use SortsAdminList;

    use LogsAdminActivity;

    private const LOW_STOCK = 5;

    public function __construct(
        private readonly ProductImageService $images,
        private readonly ProductBlockService $blocks,
        private readonly ImageStore $anh,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->when($request->query('thung_rac') === '1', fn ($q) => $q->onlyTrashed())
            ->with(['category', 'promotions'])

            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));

                $query->where(function ($q) use ($tu) {
                    $q->where('name', 'like', '%'.$tu.'%')
                        ->orWhere('product_code', 'like', '%'.$tu.'%');
                });
            })

            ->when(
                $request->filled('category'),
                fn ($q) => $q->where('category_id', $request->integer('category'))
            )

            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )

            ->when($request->query('kho') === 'het', fn ($q) => $q
                ->where('track_inventory', true)
                ->where('stock_quantity', '<=', 0))

            ->when($request->query('kho') === 'sap-het', fn ($q) => $q
                ->where('track_inventory', true)
                ->whereBetween('stock_quantity', [1, self::LOW_STOCK]))

            ->tap(fn ($q) => $this->applySort($q, $request, [
                'ten' => 'name',
                'gia' => 'base_price',
                'ton-kho' => 'stock_quantity',
                'trang-thai' => 'status',
                'ngay' => 'created_at',
            ], fn ($q) => $q->latest()))

            ->paginate(20)

            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'lowStock' => self::LOW_STOCK,
            'soDaXoa' => Product::onlyTrashed()->count(),
            'thungRac' => $request->query('thung_rac') === '1',
        ]);
    }


    public function create(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $taxClasses = TaxClass::active()->orderBy('id')->get();

        return view(
            'admin.products.create',
            compact('categories', 'taxClasses') + ['taxa' => $this->phanLoaiChoBieuMau()]
        );
    }


    public function store(
        StoreProductRequest $request
    ): RedirectResponse {

        $data = $request->validated();


        $variants =
            $data['variants'] ?? [];

        unset($data['variants'], $data['gallery'], $data['remove_images'], $data['video_urls'], $data['video_files'], $data['blocks']);

        $traits = $data['traits'] ?? [];

        unset($data['traits']);


        $storedImage = null;

        $storedGallery = [];


        try {

            $product = DB::transaction(
                function () use (
                    $request,
                    $data,
                    $variants,
                    &$storedImage,
                    &$storedGallery
                ) {

                    if (
                        $request->hasFile(
                            'main_image'
                        )
                    ) {

                        $storedImage =
                            $this->anh->luu(
                                $request->file('main_image'),
                                'products'
                            );

                        $data['main_image'] =
                            $storedImage;
                    }


                    $product =
                        Product::create(
                            $data
                        );

                    foreach (TraitType::cases() as $traitType) {
                        $product->syncTraits(
                            $traitType,
                            (array) ($traits[$traitType->value] ?? []),
                        );
                    }


                    foreach (
                        $variants
                        as $variant
                    ) {

                        if (
                            ($variant['_delete'] ?? false)
                        ) {
                            continue;
                        }


                        $product
                            ->variants()
                            ->create([
                                'name' =>
                                    $variant['name'],

                                'code' =>
                                    $variant['code']
                                    ?? null,

                                'price' =>
                                    $variant['price']
                                    ?? null,

                                'description' =>
                                    $variant['description']
                                    ?? null,

                                'is_active' =>
                                    $variant['is_active']
                                    ?? true,

                                'sort_order' =>
                                    $variant['sort_order']
                                    ?? 0,

                                'stock_quantity' =>
                                    $variant['stock_quantity']
                                    ?? null,

                                'track_inventory' =>
                                    isset($variant['stock_quantity'])
                                    && $variant['stock_quantity'] !== '',
                            ]);
                    }


                    if ($request->hasFile('gallery')) {
                        $storedGallery = $this->images->attach(
                            $product,
                            $request->file('gallery')
                        );
                    }

                    $storedGallery = array_merge(
                        $storedGallery,
                        $this->images->attachVideos(
                            $product,
                            (array) $request->input('video_urls', []),
                            (array) $request->file('video_files', []),
                        ),
                        $this->blocks->sync($product, $this->blockRows($request)),
                    );


                    return $product;
                }
            );


        } catch (\Throwable $e) {

            if ($storedImage) {

                app(\App\Services\Media\ImageStore::class)->xoa($storedImage);
            }

            $this->images->rollback($storedGallery);

            throw $e;
        }


        $this->logCrud('product.created', $product, 'sản phẩm', $product->name);


        return redirect()
            ->route(
                'admin.products.show',
                $product
            )
            ->with(
                'success',
                'Thêm sản phẩm thành công.'
            );
    }


    private function blockRows(\Illuminate\Http\Request $request): array
    {
        $rows = [];

        foreach ((array) $request->input('blocks', []) as $i => $row) {
            $row = is_array($row) ? $row : [];
            $row['image'] = $request->file("blocks.{$i}.image");

            $rows[] = $row;
        }

        return $rows;
    }


    public function show(
        Product $product
    ): View {

        $product->load([
            'category',
            'promotions',
            'media',
            'blocks',
            'traits',
            'reviews',

            'variants' => function ($query) {
                $query
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ]);


        $banHang = \App\Models\OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.status', \App\Enums\OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as so_luong')
            ->selectRaw('COALESCE(SUM(order_items.line_total - order_items.discount_amount), 0) as doanh_thu')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as so_don')
            ->selectRaw('MAX(orders.created_at) as ban_gan_nhat')
            ->first();

        $product->loadCount([
            'reviews as so_danh_gia',
            'wishlists as so_yeu_thich',
        ]);

        $product->loadAvg(['reviews as diem_trung_binh'], 'rating');

        return view('admin.products.show', [
            'product' => $product,
            'banHang' => $banHang,

            'trongGio' => (int) \App\Models\CartItem::where('product_id', $product->id)->sum('quantity'),
        ]);
    }


    public function edit(
        Product $product
    ): View {

        $categories = Category::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->category_id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();


        $product->load([
            'images',
            'variants' => function ($query) {
                $query
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ]);


        $taxClasses = TaxClass::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($product->tax_class_id, fn ($q) => $q->orWhere('id', $product->tax_class_id)))
            ->orderBy('id')
            ->get();

        return view(
            'admin.products.edit',
            compact(
                'product',
                'categories',
                'taxClasses'
            ) + ['taxa' => $this->phanLoaiChoBieuMau()]
        );
    }


    private function phanLoaiChoBieuMau(): array
    {
        $bac = [\App\Enums\TaxonRank::Family, \App\Enums\TaxonRank::Genus, \App\Enums\TaxonRank::Species];

        $nut = \App\Models\PlantTaxon::query()
            ->whereIn('rank', array_map(fn ($b) => $b->value, $bac))
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($t) => $t->rank->value);

        $ket = [];

        foreach ($bac as $b) {
            if ($nut->has($b->value)) {
                $ket[] = ['nhan' => $b->label(), 'nut' => $nut[$b->value]];
            }
        }

        return $ket;
    }


    public function update(
        UpdateProductRequest $request,
        Product $product
    ): RedirectResponse {

        $giaTruoc = $product->base_price;
        $trangThaiTruoc = $product->status;

        $data =
            $request->validated();


        $variants =
            $data['variants'] ?? [];

        $removeImages = $data['remove_images'] ?? [];

        unset($data['variants'], $data['gallery'], $data['remove_images'], $data['video_urls'], $data['video_files'], $data['blocks']);

        $traits = $data['traits'] ?? [];

        unset($data['traits']);


        $oldImage =
            $product->main_image;


        $newImage = null;

        $storedGallery = [];


        try {

            DB::transaction(
                function () use (
                    $request,
                    $data,
                    $variants,
                    $product,
                    $removeImages,
                    &$newImage,
                    &$storedGallery
                ) {

                    $this->images->detach($product, $removeImages);

                    if ($request->hasFile('gallery')) {
                        $storedGallery = $this->images->attach(
                            $product,
                            $request->file('gallery')
                        );
                    }

                    $storedGallery = array_merge(
                        $storedGallery,
                        $this->images->attachVideos(
                            $product,
                            (array) $request->input('video_urls', []),
                            (array) $request->file('video_files', []),
                        ),
                        $this->blocks->sync($product, $this->blockRows($request)),
                    );


                    if (
                        $request->hasFile(
                            'main_image'
                        )
                    ) {

                        $newImage =
                            $this->anh->luu(
                                $request->file('main_image'),
                                'products'
                            );

                        $data['main_image'] =
                            $newImage;
                    }


                    $product->update(
                        $data
                    );

                    foreach (TraitType::cases() as $traitType) {
                        $product->syncTraits(
                            $traitType,
                            (array) ($traits[$traitType->value] ?? []),
                        );
                    }


                    $existingIds =
                        $product
                            ->variants()
                            ->pluck('id')
                            ->map(
                                fn ($id) =>
                                    (string) $id
                            )
                            ->all();


                    $keptIds = [];


                    foreach (
                        $variants
                        as $variant
                    ) {

                        $variantId =
                            isset(
                                $variant['id']
                            )
                                ? (string)
                                    $variant['id']
                                : null;


                        if ($variantId) {

                            if (
                                !in_array(
                                    $variantId,
                                    $existingIds,
                                    true
                                )
                            ) {

                                abort(404);
                            }


                            $model =
                                $product
                                    ->variants()
                                    ->findOrFail(
                                        $variantId
                                    );


                            if (
                                ($variant['_delete'] ?? false)
                            ) {

                                $model->delete();

                                continue;
                            }


                            $keptIds[] =
                                $variantId;


                            $model->update([
                                'name' =>
                                    $variant['name'],

                                'code' =>
                                    $variant['code']
                                    ?? null,

                                'price' =>
                                    $variant['price']
                                    ?? null,

                                'description' =>
                                    $variant['description']
                                    ?? null,

                                'is_active' =>
                                    $variant['is_active']
                                    ?? true,

                                'sort_order' =>
                                    $variant['sort_order']
                                    ?? 0,

                                'stock_quantity' =>
                                    $variant['stock_quantity']
                                    ?? null,

                                'track_inventory' =>
                                    isset($variant['stock_quantity'])
                                    && $variant['stock_quantity'] !== '',
                            ]);


                            continue;
                        }


                        if (
                            ($variant['_delete'] ?? false)
                        ) {
                            continue;
                        }


                        $product
                            ->variants()
                            ->create([
                                'name' =>
                                    $variant['name'],

                                'code' =>
                                    $variant['code']
                                    ?? null,

                                'price' =>
                                    $variant['price']
                                    ?? null,

                                'description' =>
                                    $variant['description']
                                    ?? null,

                                'is_active' =>
                                    $variant['is_active']
                                    ?? true,

                                'sort_order' =>
                                    $variant['sort_order']
                                    ?? 0,

                                'stock_quantity' =>
                                    $variant['stock_quantity']
                                    ?? null,

                                'track_inventory' =>
                                    isset($variant['stock_quantity'])
                                    && $variant['stock_quantity'] !== '',
                            ]);
                    }


                    $idsToDelete =
                        array_diff(
                            $existingIds,
                            $keptIds
                        );


                    if (
                        !empty($idsToDelete)
                    ) {

                        $product
                            ->variants()
                            ->whereIn(
                                'id',
                                $idsToDelete
                            )
                            ->delete();
                    }
                }
            );


        } catch (\Throwable $e) {

            if ($newImage) {

                app(\App\Services\Media\ImageStore::class)->xoa($newImage);
            }

            $this->images->rollback($storedGallery);

            throw $e;
        }


        if (
            $newImage &&
            $oldImage
        ) {

            app(\App\Services\Media\ImageStore::class)->xoa($oldImage);
        }


        $this->audit()->log(
            'product.updated',
            sprintf('Sửa sản phẩm "%s"', $product->name),
            $product,
            array_filter([
                'gia_truoc' => $giaTruoc,
                'gia_sau' => $product->base_price,
                'trang_thai_truoc' => $trangThaiTruoc,
                'trang_thai_sau' => $product->status,
            ], fn ($v) => $v !== null),
        );


        return redirect()
            ->route(
                'admin.products.show',
                $product
            )
            ->with(
                'success',
                'Cập nhật sản phẩm thành công.'
            );
    }


    public function destroy(
        Product $product
    ): RedirectResponse {

        $this->logCrud('product.deleted', $product, 'sản phẩm', $product->name);

        $product->delete();


        return redirect()
            ->route(
                'admin.products.index'
            )
            ->with(
                'success',
                'Xóa sản phẩm thành công.'
            );
    }

    public function restore(int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        $product->restore();
        $product->forceFill(['status' => 'draft'])->save();

        $this->logCrud('product.restored', $product, 'sản phẩm', $product->name);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Đã khôi phục "' . $product->name . '" về trạng thái nháp — kiểm lại giá và tồn rồi hãy mở bán.');
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        if (trim((string) $request->input('xac_nhan')) !== $product->name) {
            return back()->with('error', 'Gõ đúng tên sản phẩm để xoá vĩnh viễn.');
        }

        $this->logCrud('product.force_deleted', $product, 'sản phẩm', $product->name);

        try {
            $product->forceDelete();
        } catch (\Illuminate\Database\QueryException) {
            return back()->with('error', 'Không xoá vĩnh viễn được: vẫn còn chứng từ (phiếu nhập, kiểm kê, đổi hàng…) trỏ tới sản phẩm này. Để nó trong thùng rác.');
        }

        return redirect()
            ->route('admin.products.index', ['thung_rac' => 1])
            ->with('success', 'Đã xoá vĩnh viễn "' . $product->name . '".');
    }

    public function bulk(Request $request): RedirectResponse
    {
        ['viec' => $viec, 'ids' => $ids] = $this->validateBulk(
            $request,
            ['ban', 'an', 'nhap', 'noi-bat', 'bo-noi-bat', 'xoa'],
            'products',
        );

        $products = Product::whereKey($ids)->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Không tìm thấy sản phẩm nào để cập nhật.');
        }

        $so = $products->count();

        if ($viec === 'xoa') {
            foreach ($products as $product) {
                $product->delete();
            }

            $this->audit()->log(
                'product.bulk_deleted',
                sprintf('Xoá hàng loạt %d sản phẩm', $so),
                null,
                ['ids' => $ids, 'ten' => $products->pluck('name')->all()],
            );

            return back()->with('success', "Đã xoá {$so} sản phẩm.");
        }

        if (in_array($viec, ['noi-bat', 'bo-noi-bat'], true)) {
            $bat = $viec === 'noi-bat';
            Product::whereKey($ids)->update(['is_featured' => $bat]);

            $this->audit()->log(
                'product.bulk_featured',
                sprintf('%s %d sản phẩm nổi bật', $bat ? 'Đánh dấu' : 'Bỏ đánh dấu', $so),
                null,
                ['ids' => $ids, 'noi_bat' => $bat],
            );

            return back()->with('success', ($bat ? 'Đã đánh dấu nổi bật ' : 'Đã bỏ nổi bật ') . "{$so} sản phẩm.");
        }

        $trangThai = match ($viec) {
            'ban' => 'active',
            'an' => 'inactive',
            'nhap' => 'draft',
        };

        Product::whereKey($ids)->update(['status' => $trangThai]);

        $nhan = match ($viec) {
            'ban' => 'đang bán',
            'an' => 'tạm ẩn',
            'nhap' => 'bản nháp',
        };

        $this->audit()->log(
            'product.bulk_updated',
            sprintf('Chuyển %d sản phẩm sang "%s"', $so, $nhan),
            null,
            ['ids' => $ids, 'trang_thai' => $trangThai],
        );

        return back()->with('success', "Đã chuyển {$so} sản phẩm sang \"{$nhan}\".");
    }
}
