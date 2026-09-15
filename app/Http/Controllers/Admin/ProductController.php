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

    /**
     * Dưới ngưỡng này thì coi là sắp hết hàng.
     *
     * Một con số chung cho mọi sản phẩm là chưa đúng lắm — bó hoa bán
     * mỗi ngày vài chục khác hẳn cây bonsai bán mỗi tháng một cây. Nhưng
     * ngưỡng riêng cho từng sản phẩm cần thêm một cột và một ô nhập ở
     * form; khi nào cửa hàng thấy con số này vướng thì làm.
     */
    private const LOW_STOCK = 5;

    public function __construct(
        private readonly ProductImageService $images,
        private readonly ProductBlockService $blocks,
        private readonly ImageStore $anh,
    ) {}

    /*
     * =========================================================
     * DANH SÁCH PRODUCT
     * =========================================================
     */
    public function index(Request $request): View
    {
        $products = Product::query()
            // Thùng rác: chỉ sản phẩm đã xoá mềm. Mặc định xoá mềm đã bị loại.
            ->when($request->query('thung_rac') === '1', fn ($q) => $q->onlyTrashed())
            ->with(['category', 'promotions'])

            /*
             * TÌM THEO TÊN HOẶC MÃ SẢN PHẨM.
             *
             * Hai cột này vì đó là hai thứ admin có trong tay khi cần
             * tìm: khách đọc tên qua điện thoại, hoặc đọc mã trên đơn.
             *
             * Ghép LIKE hai đầu (`%tu%`) nên không dùng được chỉ mục —
             * chấp nhận được ở bảng vài nghìn sản phẩm và là cái giá phải
             * trả để tìm được cụm ở GIỮA tên ("tulip" trong "Hộp hoa
             * tulip vàng"). Chỉ khớp đầu chuỗi thì gần như không tìm ra gì.
             */
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

            /*
             * LỌC THEO TÌNH TRẠNG KHO — việc admin làm thường xuyên nhất.
             *
             * `het` chỉ tính hàng CÓ QUẢN LÝ TỒN KHO. Hàng làm theo đơn
             * (track_inventory = false) có stock_quantity = 0 nhưng không
             * hề hết hàng; gộp chung là mỗi lần lọc "hết hàng" lại thấy
             * toàn hoa cưới.
             */
            ->when($request->query('kho') === 'het', fn ($q) => $q
                ->where('track_inventory', true)
                ->where('stock_quantity', '<=', 0))

            ->when($request->query('kho') === 'sap-het', fn ($q) => $q
                ->where('track_inventory', true)
                ->whereBetween('stock_quantity', [1, self::LOW_STOCK]))

            ->tap(fn ($q) => $this->applySort($q, $request, [
                /*
                 * CỘT NÀO SẮP ĐƯỢC — do trang này quyết định, không do
                 * URL. Xem SortsAdminList để biết vì sao bắt buộc.
                 */
                'ten' => 'name',
                'gia' => 'base_price',
                // 'ton-kho' chứ không phải 'kho': trang này ĐÃ có bộ lọc
                // ?kho=het. Trùng tên thì hai thứ khác nhau cùng đọc một
                // tham số, và người sửa sau sẽ mất một buổi để hiểu.
                'ton-kho' => 'stock_quantity',
                'trang-thai' => 'status',
                'ngay' => 'created_at',
            ], fn ($q) => $q->latest()))

            ->paginate(20)

            /*
             * withQueryString() — BẮT BUỘC khi có bộ lọc.
             *
             * Thiếu nó thì bấm sang trang 2 là mất sạch điều kiện lọc và
             * admin quay về danh sách đầy đủ mà không hiểu vì sao.
             */
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'lowStock' => self::LOW_STOCK,
            'soDaXoa' => Product::onlyTrashed()->count(),
            'thungRac' => $request->query('thung_rac') === '1',
        ]);
    }


    /*
     * =========================================================
     * FORM THÊM PRODUCT
     * =========================================================
     */
    public function create(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /*
         * NHÓM THUẾ SUẤT ĐANG BẬT.
         *
         * Chỉ lấy dòng còn hoạt động: nhóm đã tắt vẫn phải giữ lại vì
         * đơn cũ trỏ tới nó, nhưng không được mời admin chọn tiếp.
         */
        $taxClasses = TaxClass::active()->orderBy('id')->get();

        return view(
            'admin.products.create',
            compact('categories', 'taxClasses') + ['taxa' => $this->phanLoaiChoBieuMau()]
        );
    }


    /*
     * =========================================================
     * LƯU PRODUCT + VARIANTS
     * =========================================================
     */
    public function store(
        StoreProductRequest $request
    ): RedirectResponse {

        /*
         * Lấy dữ liệu đã validation.
         */
        $data = $request->validated();


        /*
         * Lấy Variant ra khỏi dữ liệu Product.
         */
        $variants =
            $data['variants'] ?? [];

        unset($data['variants'], $data['gallery'], $data['remove_images'], $data['video_urls'], $data['video_files'], $data['blocks']);

        /*
         * Tách nhãn phân loại ra khỏi dữ liệu Product.
         *
         * `traits` KHÔNG phải cột của bảng products — để sót lại trong
         * $data là Eloquent ném lỗi "column not found". Cùng lý do với
         * variants/gallery ngay bên trên.
         */
        $traits = $data['traits'] ?? [];

        unset($data['traits']);


        /*
         * Theo dõi ảnh mới để nếu transaction lỗi
         * thì xóa file đã upload.
         */
        $storedImage = null;

        // Ảnh phụ (gallery) — theo dõi riêng để rollback được.
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

                    /*
                     * Upload ảnh.
                     */
                    if (
                        $request->hasFile(
                            'main_image'
                        )
                    ) {

                        // ImageStore: lưu xong thì sinh luôn bản WebP.
                        $storedImage =
                            $this->anh->luu(
                                $request->file('main_image'),
                                'products'
                            );

                        $data['main_image'] =
                            $storedImage;
                    }


                    /*
                     * Tạo Product.
                     */
                    $product =
                        Product::create(
                            $data
                        );

                    /*
                     * NHÃN PHÂN LOẠI (vị trí đặt, hợp mệnh, dùng kèm).
                     *
                     * Đặt ngay sau khi ghi sản phẩm và TRONG cùng
                     * transaction: nhãn không có ý nghĩa nếu thiếu sản
                     * phẩm, và ngược lại một sản phẩm ghi xong mà nhãn
                     * hỏng thì nó biến mất khỏi trang tư vấn mà không ai
                     * biết.
                     *
                     * syncTraits() tự loại giá trị không hợp lệ — xem
                     * ProductTrait::isValid(). Đây là ràng buộc duy nhất,
                     * vì cột trong cơ sở dữ liệu là varchar.
                     */
                    foreach (TraitType::cases() as $traitType) {
                        $product->syncTraits(
                            $traitType,
                            (array) ($traits[$traitType->value] ?? []),
                        );
                    }


                    /*
                     * Tạo các Variant.
                     */
                    foreach (
                        $variants
                        as $variant
                    ) {

                        /*
                         * Variant mới không cần
                         * xử lý _delete.
                         *
                         * Tuy nhiên vẫn bỏ qua nếu
                         * _delete = 1.
                         */
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


                    /*
                     * Ảnh phụ cho gallery.
                     */
                    if ($request->hasFile('gallery')) {
                        $storedGallery = $this->images->attach(
                            $product,
                            $request->file('gallery')
                        );
                    }

                    /*
                     * Video (link + tệp) và các khối mô tả chi tiết.
                     *
                     * Tệp vừa lưu được gom chung vào $storedGallery để nhánh
                     * catch bên dưới dọn hết trong một lần — transaction hỏng mà
                     * để lại tệp là đĩa đầy dần bằng thứ không bản ghi nào trỏ tới.
                     */
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

            /*
             * Transaction lỗi:
             * xóa ảnh vừa upload.
             */
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


    /*
     * =========================================================
     * XEM PRODUCT
     * =========================================================
     */
    /**
     * Gộp dữ liệu khối mô tả: phần chữ nằm trong input, ảnh nằm trong file.
     *
     * Laravel để hai thứ ở hai chỗ; ProductBlockService cần một mảng duy nhất
     * để không phải biết request trông thế nào.
     *
     * @return array<int, array<string, mixed>>
     */
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


        /*
         * SỐ LIỆU BÁN HÀNG — đếm ở đây, không đếm trong Blade.
         *
         * Trang này trước chỉ hiện những gì admin đã nhập. Câu hỏi thật khi mở
         * một sản phẩm ra xem là "nó bán thế nào": đã bán bao nhiêu, mang về bao
         * nhiêu tiền, khách chấm mấy sao, còn nằm trong giỏ ai không.
         *
         * Chỉ tính ĐƠN ĐÃ GIAO — cùng định nghĩa doanh thu với trang Phân tích.
         */
        $banHang = \App\Models\OrderItem::query()
            ->hangBan() // món đem tặng làm quà không tính là đã bán
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

            // Còn nằm trong giỏ của ai: hàng sắp bán được, hoặc giỏ bị bỏ dở.
            'trongGio' => (int) \App\Models\CartItem::where('product_id', $product->id)->sum('quantity'),
        ]);
    }


    /*
     * =========================================================
     * FORM SỬA PRODUCT
     * =========================================================
     */
    public function edit(
        Product $product
    ): View {

        /*
         * GIỮ DANH MỤC VÀ NHÓM THUẾ ĐANG GẮN, dù đã ẩn / đã tắt.
         *
         * Lỗi đã sửa: trang sửa chỉ nạp danh mục đang hoạt động và nhóm thuế
         * đang bật. Sản phẩm thuộc danh mục vừa ẩn thì ô chọn không có dòng
         * của nó — bấm Lưu để sửa giá là lặng lẽ đổi danh mục hoặc nhóm thuế.
         * Chỉ dòng ĐANG GẮN được giữ; sản phẩm mới vẫn không chọn được chúng.
         */
        $categories = Category::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->category_id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();


        /*
         * Load Variant để _form có thể
         * hiển thị ngay.
         */
        $product->load([
            'images',
            'variants' => function ($query) {
                $query
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ]);


        /*
         * NHÓM THUẾ SUẤT ĐANG BẬT.
         *
         * Chỉ lấy dòng còn hoạt động: nhóm đã tắt vẫn phải giữ lại vì
         * đơn cũ trỏ tới nó, nhưng không được mời admin chọn tiếp.
         */
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


    /**
     * Các nút phân loại để gắn cho sản phẩm, nhóm theo bậc: Họ, Chi, Loài.
     * ============================================================
     * BỎ CÁC BẬC TRÊN HỌ (Giới, Ngành, Lớp, Bộ): gắn một chậu cây vào "Giới
     * Thực vật" đúng mà vô ích — trang /loai-cay không lọc được gì từ đó.
     *
     * Vẫn cho chọn Chi và Họ, không chỉ Loài: "sen đá mix" là nhiều loài
     * trong một chậu; ép chọn tới loài là ép bịa (xem migration
     * create_plant_taxa_table).
     *
     * Một hàm cho cả trang tạo lẫn trang sửa — hai bản thì sớm muộn một
     * trang thiếu bậc.
     *
     * @return list<array{nhan: string, nut: \Illuminate\Support\Collection<int, \App\Models\PlantTaxon>}>
     */
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


    /*
     * =========================================================
     * UPDATE PRODUCT + VARIANTS
     * =========================================================
     */
    public function update(
        UpdateProductRequest $request,
        Product $product
    ): RedirectResponse {

        // Chụp lại TRƯỚC khi ghi đè: sau update() thì hai giá trị
        // này đã là giá trị mới, và nhật ký không còn gì để so.
        $giaTruoc = $product->base_price;
        $trangThaiTruoc = $product->status;

        /*
         * Dữ liệu đã validation.
         */
        $data =
            $request->validated();


        /*
         * Tách Variant ra.
         */
        $variants =
            $data['variants'] ?? [];

        // gallery/remove_images không phải cột của products.
        $removeImages = $data['remove_images'] ?? [];

        unset($data['variants'], $data['gallery'], $data['remove_images'], $data['video_urls'], $data['video_files'], $data['blocks']);

        /*
         * Tách nhãn phân loại ra khỏi dữ liệu Product.
         *
         * `traits` KHÔNG phải cột của bảng products — để sót lại trong
         * $data là Eloquent ném lỗi "column not found". Cùng lý do với
         * variants/gallery ngay bên trên.
         */
        $traits = $data['traits'] ?? [];

        unset($data['traits']);


        /*
         * Ảnh cũ.
         */
        $oldImage =
            $product->main_image;


        /*
         * Ảnh mới.
         */
        $newImage = null;

        // Ảnh phụ vừa upload — dùng để rollback nếu transaction hỏng.
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

                    /*
                     * =========================
                     * GALLERY
                     * =========================
                     *
                     * Xoá trước rồi thêm, để admin vừa bỏ ảnh cũ vừa
                     * thêm ảnh mới trong cùng một lần lưu.
                     */
                    $this->images->detach($product, $removeImages);

                    if ($request->hasFile('gallery')) {
                        $storedGallery = $this->images->attach(
                            $product,
                            $request->file('gallery')
                        );
                    }

                    // Video và khối mô tả — xem chú thích ở store().
                    $storedGallery = array_merge(
                        $storedGallery,
                        $this->images->attachVideos(
                            $product,
                            (array) $request->input('video_urls', []),
                            (array) $request->file('video_files', []),
                        ),
                        $this->blocks->sync($product, $this->blockRows($request)),
                    );


                    /*
                     * =========================
                     * UPDATE IMAGE
                     * =========================
                     */

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


                    /*
                     * =========================
                     * UPDATE PRODUCT
                     * =========================
                     */

                    $product->update(
                        $data
                    );

                    /*
                     * NHÃN PHÂN LOẠI (vị trí đặt, hợp mệnh, dùng kèm).
                     *
                     * Đặt ngay sau khi ghi sản phẩm và TRONG cùng
                     * transaction: nhãn không có ý nghĩa nếu thiếu sản
                     * phẩm, và ngược lại một sản phẩm ghi xong mà nhãn
                     * hỏng thì nó biến mất khỏi trang tư vấn mà không ai
                     * biết.
                     *
                     * syncTraits() tự loại giá trị không hợp lệ — xem
                     * ProductTrait::isValid(). Đây là ràng buộc duy nhất,
                     * vì cột trong cơ sở dữ liệu là varchar.
                     */
                    foreach (TraitType::cases() as $traitType) {
                        $product->syncTraits(
                            $traitType,
                            (array) ($traits[$traitType->value] ?? []),
                        );
                    }


                    /*
                     * =========================
                     * LẤY ID VARIANT HIỆN TẠI
                     * =========================
                     */

                    $existingIds =
                        $product
                            ->variants()
                            ->pluck('id')
                            ->map(
                                fn ($id) =>
                                    (string) $id
                            )
                            ->all();


                    /*
                     * Các Variant vẫn còn tồn tại
                     * sau khi submit.
                     */
                    $keptIds = [];


                    /*
                     * =========================
                     * XỬ LÝ TỪNG VARIANT
                     * =========================
                     */

                    foreach (
                        $variants
                        as $variant
                    ) {

                        /*
                         * ID có thể rỗng nếu đây
                         * là Variant mới.
                         */
                        $variantId =
                            isset(
                                $variant['id']
                            )
                                ? (string)
                                    $variant['id']
                                : null;


                        /*
                         * =========================
                         * VARIANT CŨ
                         * =========================
                         */

                        if ($variantId) {

                            /*
                             * Chống việc gửi ID Variant
                             * thuộc Product khác.
                             */
                            if (
                                !in_array(
                                    $variantId,
                                    $existingIds,
                                    true
                                )
                            ) {

                                abort(404);
                            }


                            /*
                             * Tìm Variant thuộc
                             * Product hiện tại.
                             */
                            $model =
                                $product
                                    ->variants()
                                    ->findOrFail(
                                        $variantId
                                    );


                            /*
                             * =========================
                             * XÓA VARIANT
                             * =========================
                             */

                            if (
                                ($variant['_delete'] ?? false)
                            ) {

                                $model->delete();

                                /*
                                 * Không đưa ID vào
                                 * $keptIds vì Variant
                                 * đã bị xóa.
                                 */

                                continue;
                            }


                            /*
                             * Variant này vẫn tồn tại.
                             */
                            $keptIds[] =
                                $variantId;


                            /*
                             * =========================
                             * UPDATE VARIANT
                             * =========================
                             */

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


                        /*
                         * =========================
                         * VARIANT MỚI
                         * =========================
                         */

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


                    /*
                     * =========================
                     * XÓA CÁC VARIANT BỊ BỎ KHỎI FORM
                     * =========================
                     *
                     * Trường hợp này chủ yếu dùng để
                     * đảm bảo database không giữ Variant
                     * mà form không còn gửi.
                     */
                    $idsToDelete =
                        array_diff(
                            $existingIds,
                            $keptIds
                        );


                    /*
                     * Các Variant đã được xử lý
                     * _delete = 1 thì đã xóa rồi.
                     *
                     * Các Variant không còn xuất hiện
                     * trong request cũng bị xóa.
                     */
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

            /*
             * Nếu update thất bại:
             * xóa ảnh mới.
             */
            if ($newImage) {

                app(\App\Services\Media\ImageStore::class)->xoa($newImage);
            }

            $this->images->rollback($storedGallery);

            throw $e;
        }


        /*
         * Update thành công:
         * xóa ảnh cũ.
         */
        if (
            $newImage &&
            $oldImage
        ) {

            // xoa() dọn cả bản WebP và các cỡ ảnh, không chỉ ảnh gốc.
            app(\App\Services\Media\ImageStore::class)->xoa($oldImage);
        }


        /*
         * GHI CẢ GIÁ CŨ VÀ GIÁ MỚI.
         *
         * Đổi giá là thao tác hay bị hỏi lại nhất, và bản ghi sản phẩm
         * chỉ giữ giá HIỆN TẠI — nhìn vào không biết hôm qua nó bao
         * nhiêu. Không ghi lại ở đây thì con số cũ mất vĩnh viễn.
         */
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


    /*
     * =========================================================
     * DELETE PRODUCT
     * =========================================================
     */
    public function destroy(
        Product $product
    ): RedirectResponse {

        /*
         * XOÁ MỀM — VÀ VÌ THẾ KHÔNG ĐỘNG VÀO FILE ẢNH.
         *
         * LỖI TRƯỚC KHI SỬA: chỗ này xoá file ảnh chính và toàn bộ ảnh
         * phụ trên đĩa, RỒI mới gọi $product->delete(). Nhưng Product
         * dùng SoftDeletes, nên delete() chỉ ghi một dấu thời gian —
         * bản ghi vẫn nguyên, chờ được khôi phục.
         *
         * Hai việc đó ngược nhau: một nửa hành động thì hoàn tác được,
         * nửa kia thì không. Khôi phục sản phẩm sẽ ra một trang hàng có
         * đủ tên, giá, mô tả và không có lấy một tấm ảnh — chỉ còn những
         * ô vỡ, vì bản ghi product_images vẫn trỏ vào file đã bị xoá.
         *
         * Xoá mềm tồn tại ở đây có lý do: order_items trỏ về product_id,
         * và lịch sử đơn hàng phải đọc được sau nhiều năm. Nên thứ phải
         * đổi là việc xoá file, không phải việc xoá mềm.
         *
         * File được dọn ở đúng lúc nó thật sự thành rác — khi sản phẩm
         * bị xoá VĨNH VIỄN. Xem Product::booted().
         */
        // Ghi trước khi xoá, để dòng nhật ký còn giữ đúng khoá chính.
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

    /**
     * Khôi phục sản phẩm từ thùng rác.
     *
     * Khôi phục về trạng thái NHÁP, không về trạng thái cũ: sản phẩm nằm
     * trong thùng rác có thể đã hết mùa, sai giá — hiện lại ngay lên cửa
     * hàng là bán một thứ chưa ai kiểm lại.
     */
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

    /**
     * Xoá VĨNH VIỄN — chỉ cho sản phẩm đã ở trong thùng rác, và phải gõ đúng tên.
     *
     * Gõ tên chứ không chỉ bấm "OK": hộp xác nhận của trình duyệt bị bấm
     * qua theo phản xạ. Xoá vĩnh viễn dọn luôn file ảnh (Product::booted),
     * không hoàn tác được.
     *
     * Chứng từ còn trỏ tới sản phẩm (phiếu đổi hàng, phiếu kiểm kê…) thì cơ
     * sở dữ liệu chặn — báo ra, không để trang lỗi 500.
     */
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

    /*
     * =========================================================
     * THAO TÁC HÀNG LOẠT
     * =========================================================
     */
    public function bulk(Request $request): RedirectResponse
    {
        ['viec' => $viec, 'ids' => $ids] = $this->validateBulk(
            $request,
            ['ban', 'an', 'nhap', 'xoa'],
            'products',
        );

        /*
         * ĐỌC RA TRƯỚC KHI GHI.
         *
         * Cần bản ghi thật cho hai việc: đếm đúng số dòng ĐÃ đổi (chứ
         * không phải số ô đã tích), và ghi được tên vào nhật ký. Xoá
         * xong rồi mới đọc thì không còn gì để đọc.
         *
         * whereKey với mảng id đã qua kiểm tra `exists` ở validateBulk.
         */
        $products = Product::whereKey($ids)->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Không tìm thấy sản phẩm nào để cập nhật.');
        }

        $so = $products->count();

        if ($viec === 'xoa') {
            /*
             * Xoá MỀM, đúng như nút Xoá của từng sản phẩm — và vì thế
             * cũng KHÔNG đụng vào file ảnh. Xem ProductController::destroy()
             * và Product::booted() để biết vì sao.
             */
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

        $trangThai = match ($viec) {
            'ban' => 'active',
            'an' => 'inactive',
            'nhap' => 'draft',
        };

        /*
         * MỘT CÂU UPDATE cho cả nhóm, không lặp save() từng bản ghi.
         *
         * Ba mươi lần save() là ba mươi lượt đi lại với cơ sở dữ liệu,
         * và nếu đứt giữa chừng thì mười lăm cái đầu đã đổi còn mười
         * lăm cái sau thì chưa — một trạng thái không ai muốn dọn.
         */
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

        // Nói RÕ SỐ LƯỢNG: "đã cập nhật" trống không thì người dùng phải
        // tự đếm lại mới biết có sót cái nào không.
        return back()->with('success', "Đã chuyển {$so} sản phẩm sang \"{$nhan}\".");
    }
}
