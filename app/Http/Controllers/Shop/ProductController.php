<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\SellingForm;
use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\ProductTrait;
use App\Models\PlantTaxon;
use App\Enums\TraitType;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\UserEvent;
use App\Services\Recommendation\PlantAdvisor;
use App\Services\Recommendation\RecommendationService;
use App\Services\Search\ProductSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSearch $search,
        private readonly RecommendationService $recommendations,
        private readonly PlantAdvisor $advisor,
    ) {
    }

    /**
     * Giữ tên gọi cũ để không phải sửa mọi nơi đang dùng, nhưng nguồn
     * dữ liệu nay là App\Enums\SellingForm — một chỗ duy nhất.
     *
     * @return array<string, string>
     */
    public static function sellingForms(): array
    {
        return SellingForm::options();
    }

    public function index(Request $request): View
    {
        /*
         * TRANG NÀY CHỈ BÁN HÀNG CHÍNH — hoa và cây cảnh.
         *
         * Phụ kiện và vật tư có trang riêng (SupplyController). Trộn
         * chung thì bộ lọc "Hình thức" đầy những mục vô nghĩa với vật tư,
         * và khách lướt tìm hoa phải bỏ qua mấy trang chậu với phân bón.
         */
        $query = Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        if ($request->filled('selling_form')) {
            $query->where('selling_form', $request->string('selling_form'));
        }

        if ($request->filled('care_difficulty')) {
            $query->where('care_info->difficulty', $request->string('care_difficulty'));
        }

        /*
         * LỌC THEO NHÃN SINH THÁI — màu, dạng sống, môi trường, dáng,
         * vị trí đặt, mệnh.
         *
         * MỘT VÒNG LẶP CHO CẢ SÁU, không phải sáu khối if giống hệt
         * nhau. TraitType::filterable() giữ danh sách và queryKey() giữ
         * tên tham số URL, nên thêm một tiêu chí mới về sau chỉ là thêm
         * một case vào enum — không phải sửa controller, không phải sửa
         * giao diện.
         *
         * CỘNG DỒN (AND) giữa các loại nhãn: chọn "màu trắng" và "cây
         * leo" thì phải ra cây leo hoa trắng, không phải hợp của hai
         * danh sách. Đó là cách mọi bộ lọc thương mại điện tử hoạt động
         * và là thứ người dùng mong đợi.
         *
         * Giá trị lạ trên URL bị bỏ qua chứ KHÔNG abort(404): người ta
         * chép link cho nhau, và một tham số hỏng không đáng để cả trang
         * biến mất.
         */
        $traitFilters = [];

        foreach (TraitType::filterable() as $type) {
            $value = (string) $request->query($type->queryKey(), '');

            if ($value === '' || ! array_key_exists($value, $type->options())) {
                continue;
            }

            $query->withTrait($type, $value);
            $traitFilters[$type->queryKey()] = $value;
        }

        /*
         * LỌC THEO ĐỘ KHÓ CHĂM SÓC — tiêu chí đến từ trang "Chọn cây".
         *
         * Đây là tiêu chí DUY NHẤT mà trang chọn cây có còn trang này
         * thì không, nên nó là thứ phải chuyển sang khi gộp hai trang
         * (xem QĐ-172). Sáu tiêu chí còn lại đã trùng sẵn.
         *
         * Luật lọc nằm ở Product::scopeWithCareDifficulty(), không viết
         * lại ở đây: luật đó gồm hai vế, và vế thứ hai (chỉ tính hàng
         * trồng chậu) là thứ một bản chép tay sẽ quên.
         */
        $careDifficulty = CareDifficulty::tryFrom((string) $request->query('kinh-nghiem', ''));

        if ($careDifficulty) {
            $query->withCareDifficulty($careDifficulty);
        }

        /*
         * LỌC THEO NHÁNH PHÂN LOẠI SINH HỌC.
         *
         * scopeInTaxon lấy CẢ NHÁNH bên dưới — xem chú thích ở đó.
         */
        $activeTaxon = null;

        if ($request->filled('loai')) {
            $activeTaxon = PlantTaxon::where('slug', $request->string('loai'))->first();

            if ($activeTaxon) {
                $query->inTaxon($activeTaxon);
            }
        }

        // Lọc theo chương trình khuyến mại — dùng cho nút "Xem ưu đãi"
        // ở thanh thông báo và banner chiến dịch.
        $activePromotion = null;

        if ($request->filled('promotion')) {
            $activePromotion = Promotion::query()
                ->where('slug', $request->string('promotion'))
                ->first();

            if ($activePromotion) {
                $query->whereHas(
                    'promotions',
                    fn ($q) => $q->where('promotions.id', $activePromotion->id)
                );
            }
        }

        /*
         * TÌM KIẾM — xem App\Services\Search\ProductSearch.
         *
         * Đặt SAU các bộ lọc khác và TRƯỚC phần sắp xếp, vì hai lý do:
         * bản sao câu truy vấn dùng cho lần thử nới lỏng phải mang theo
         * đủ bộ lọc danh mục/hình thức, còn thứ tự sắp xếp thì phụ thuộc
         * vào việc có từ khoá hay không.
         */
        $search = $this->search->terms($request->query('q'));
        $sort = $request->string('sort')->toString();

        // Giữ nguyên câu truy vấn chưa có điều kiện từ khoá, phòng khi
        // phải tìm lại ở chế độ nới lỏng.
        $withoutKeywords = clone $query;

        $this->search->filter($query, $search);

        $this->applySort($query, $sort, $search);

        $products = $query->paginate(12)->withQueryString();

        /*
         * NỚI LỎNG khi không có sản phẩm nào chứa ĐỦ mọi từ khoá.
         *
         * "hoa bonsai" là ví dụ thật trong catalog này: có hàng "hoa",
         * có hàng "bonsai", không có món nào vừa hoa vừa bonsai. Trang
         * trống ở đây là đúng luật nhưng vô ích với khách.
         *
         * Chỉ tốn thêm một câu truy vấn, và chỉ trong đúng trường hợp
         * này — lần tìm nào có kết quả thì không bao giờ chạy tới đây.
         * Điều kiện `hasMultipleTokens` là bắt buộc: một từ khoá duy nhất
         * thì nới lỏng chẳng khác gì lần tìm vừa rồi.
         */
        $searchRelaxed = false;

        if ($products->isEmpty() && $search->hasMultipleTokens()) {
            $relaxed = $withoutKeywords;
            $this->search->filter($relaxed, $search, matchAll: false);
            $this->applySort($relaxed, $sort, $search);

            $products = $relaxed->paginate(12)->withQueryString();
            $searchRelaxed = $products->isNotEmpty();
        }

        if ($search->isNotEmpty()) {
            /*
             * Ghi lại từ khoá khách tìm.
             * Guide muc 25 liet ke 'search' la mot trong sau event can
             * theo doi, va muc 9 muon phan tich "san pham duoc tim kiem
             * nhieu" — khong ghi thi sau nay khong co du lieu de phan tich.
             *
             * Ghi CẢ chữ khách gõ lẫn chữ hệ thống đã sửa thành, cùng số
             * kết quả. Chỉ ghi bản đã sửa thì sau này không ai biết khách
             * hay gõ sai chỗ nào; chỉ ghi bản gốc thì không đánh giá được
             * bộ sửa lỗi đoán có đúng không.
             */
            UserEvent::log(UserEventType::Search, $request, [
                'meta' => array_filter([
                    'q' => $search->original,
                    'used' => $search->wasCorrected() ? $search->suggestion() : null,
                    'relaxed' => $searchRelaxed ?: null,
                    'results' => $products->total(),
                ], fn ($v) => $v !== null),
            ]);
        }

        $categories = Category::query()
            ->plants()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sellingForms = self::sellingForms();

        return view('shop.products.index', compact(
            'products',
            'categories',
            'sellingForms',
            'activePromotion',
            'search',
            'searchRelaxed',
            'traitFilters',
            'activeTaxon',
            'careDifficulty',
        ) + [
            /*
             * CHỈ HIỆN NHỮNG NHÃN THẬT SỰ CÓ HÀNG.
             *
             * Dựng bộ lọc từ enum thì nó liệt kê đủ 10 dạng sống và 9
             * môi trường, kể cả những thứ cửa hàng chưa từng bán. Khách
             * bấm "Họ cau dừa" và nhận về màn hình trống — một lựa chọn
             * dẫn tới ngõ cụt là một lựa chọn không nên hiện ra.
             *
             * Một truy vấn cho toàn bộ nhãn, không phải mỗi loại một
             * truy vấn.
             */
            'traitOptions' => $this->traitOptionsInUse(),

            /*
             * CHỈ HIỆN ĐỘ KHÓ THẬT SỰ CÓ HÀNG — cùng nguyên tắc với các
             * nhãn ở trên. `availableDifficulties()` đã lọc sẵn những
             * mức có 0 sản phẩm.
             */
            'careDifficulties' => app(\App\Services\Recommendation\PlantAdvisor::class)->availableDifficulties(),

            /*
             * MỌI THAM SỐ LỌC — MỘT DANH SÁCH DUY NHẤT.
             *
             * Ba nơi cần đúng danh sách này: input ẩn giữ chip khi bấm
             * "Áp dụng", nút "Xoá bộ lọc", và khối mời "Chọn cây theo
             * nhu cầu" (chỉ hiện khi CHƯA lọc gì).
             *
             * Ba bản chép tay thì chúng lệch nhau, và lệch ở đây nghĩa là
             * một bộ lọc âm thầm không xoá được, hoặc khối mời hiện ra
             * đúng lúc khách đã biết mình muốn gì.
             *
             * Phần nhãn lấy từ TraitType::filterable(), không liệt kê tay.
             */
            'moiThamSoLoc' => array_merge(
                ['q', 'category', 'selling_form', 'sort', 'kinh-nghiem', 'loai', 'promotion'],
                array_map(fn (TraitType $t) => $t->queryKey(), TraitType::filterable()),
            ),
        ]);
    }

    /**
     * Những giá trị nhãn ĐANG CÓ HÀNG, kèm số lượng.
     *
     * Chỉ đếm trong nhóm hàng chính và hàng đang bán được — đúng tập
     * hợp mà bộ lọc sẽ tìm trên đó. Đếm cả kho thì con số bên cạnh nhãn
     * nói một đằng còn kết quả trả ra một nẻo.
     *
     * @return array<string, array<string, int>> trait_type => [value => số sản phẩm]
     */
    private function traitOptionsInUse(): array
    {
        $rows = ProductTrait::query()
            ->select('trait_type', 'trait_value', DB::raw('count(*) as n'))
            ->whereIn('product_id', Product::query()
                ->mainCatalog()
                ->whereIn('status', ['active', 'out_of_stock'])
                ->select('id'))
            ->groupBy('trait_type', 'trait_value')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            /*
             * ->value chứ không dùng thẳng $row->trait_type.
             *
             * ProductTrait ép cột này sang enum TraitType, nên nó là một
             * đối tượng chứ không phải chuỗi — và PHP không cho dùng
             * đối tượng làm khoá mảng. View tra bảng này bằng
             * $type->value nên khoá cũng phải là chuỗi đó.
             */
            $out[$row->trait_type->value][$row->trait_value] = (int) $row->n;
        }

        return $out;
    }

    /**
     * Thứ tự hiển thị danh sách sản phẩm.
     *
     * Ý muốn của khách luôn thắng: đã chọn "Giá tăng dần" thì đó là thứ
     * tự duy nhất, không trộn thêm điểm liên quan vào. Chỉ khi khách chưa
     * chọn gì mà lại đang tìm từ khoá thì mới xếp theo độ liên quan —
     * lúc đó "mới nhất" gần như chắc chắn không phải thứ họ muốn thấy
     * đầu tiên.
     */
    private function applySort(Builder $query, string $sort, SearchTerms $search): void
    {
        switch ($sort) {
            case 'price_asc':
                // Theo giá KHÁCH THẤY, tức giá sau khuyến mại —
                // xem Product::scopeOrderByEffectivePrice().
                $query->orderByEffectivePrice('asc');

                return;

            case 'price_desc':
                $query->orderByEffectivePrice('desc');

                return;

            case 'popular':
                $query->orderByDesc('view_count');

                return;
        }

        if ($search->isNotEmpty()) {
            $this->search->orderByRelevance($query, $search);
        }

        /*
         * Luôn chốt bằng `latest()`, kể cả khi đã xếp theo độ liên quan.
         *
         * Nhiều sản phẩm dễ có cùng số điểm. Không có tiêu chí phụ thì
         * thứ tự giữa chúng do cơ sở dữ liệu tự quyết và có thể khác nhau
         * giữa hai lần chạy — nghĩa là chuyển sang trang 2 có sản phẩm
         * hiện lại lần nữa, có sản phẩm không bao giờ được nhìn thấy.
         */
        $query->latest();
    }

    public function show(Request $request, Product $product): View
    {
        abort_unless(in_array($product->status, ['active', 'out_of_stock'], true), 404);

        $product->load([
            'category',
            'promotions',
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),

            // Nạp sẵn: hai component bên dưới gọi tới, thiếu dòng này là mỗi
            // lần dựng trang thêm hai truy vấn.
            'blocks',
            'videos',
        ]);

        /*
         * Nạp sẵn điểm trung bình và số lượt cho CHÍNH sản phẩm này.
         * Route binding chỉ trả về bản ghi trần, nên nếu thiếu hai dòng
         * dưới thì mỗi lần Blade gọi ratingCount()/ratingAverage() lại là
         * một truy vấn — đo được 11 câu thừa trên một trang.
         */
        $product->loadAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating');
        $product->loadCount(['reviews as rating_count' => fn ($q) => $q->visible()]);

        /*
         * SỐ LIỆU THẬT CHO KHÁCH TỰ TIN MUA — đã bán, còn ít, quy cách phổ
         * biến. Đọc từ đơn đã giao và kho; không đủ căn cứ thì null và trang
         * không in gì. Xem App\Services\Catalog\SocialProof.
         */
        $bangChung = app(\App\Services\Catalog\SocialProof::class);
        $daBan = $bangChung->banGanDay($product);
        $chiCon = $bangChung->chiCon($product, $product->variants->isNotEmpty());
        $quyCachPhoBien = $product->variants->isNotEmpty() ? $bangChung->quyCachBanChay($product) : null;

        $product->increment('view_count');

        /*
         * Ghi kèm NGUỒN TRUY CẬP nếu có (?ref=goi-y:home).
         *
         * Đây là mảnh còn thiếu để trả lời "gợi ý có hiệu quả không".
         * Trước đây chỉ đếm được số lần khối gợi ý được hiển thị, không
         * đếm được số lần có người bấm vào — mà chỉ số thứ hai mới cho
         * biết gợi ý đoán đúng hay đoán bừa.
         *
         * KHÔNG tạo loại sự kiện mới: đây vẫn đúng là một lượt xem sản
         * phẩm, chỉ khác ở chỗ biết khách đến từ đâu. Thêm loại sự kiện
         * riêng sẽ làm mọi phép đếm lượt xem hiện có thiếu mất phần này.
         *
         * Cắt 40 ký tự và chỉ nhận chữ/số/dấu phân cách: `ref` đến từ
         * thanh địa chỉ nên ai cũng đặt được giá trị tuỳ ý, mà giá trị đó
         * đi thẳng vào cột meta rồi hiện lại trên trang Phân tích.
         */
        $ref = (string) $request->query('ref', '');
        $ref = preg_match('/^[a-z0-9:_-]{1,40}$/', $ref) === 1 ? $ref : null;

        UserEvent::log(UserEventType::ProductView, $request, array_filter([
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'meta' => $ref ? ['ref' => $ref] : null,
        ], fn ($v) => $v !== null));

        $related = Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->take(4)
            ->get();

        /*
         * ĐÁNH GIÁ.
         *
         * Chỉ lấy bài đang hiển thị. Nạp kèm `user` vì mỗi bài đều in tên
         * người viết — thiếu dòng này là mỗi đánh giá thêm một truy vấn.
         */
        $reviews = $product->reviews()
            ->visible()
            ->with('user')
            ->latest()
            ->paginate(5, ['*'], 'danh_gia');

        /*
         * Đơn hàng cho phép người đang đăng nhập viết đánh giá, hoặc null.
         * Blade chỉ hiện form khi biến này khác null — cùng đúng một hàm
         * mà ReviewController dùng để chặn, nên nút và quyền không lệch.
         */
        $reviewableOrder = Auth::check()
            ? Review::pendingOrderFor(Auth::id(), $product->id)
            : null;

        /*
         * Không còn truyền $sellingForms: trang chi tiết nay đọc nhãn
         * thẳng từ enum ($product->selling_form->label()). Danh sách
         * đầy đủ chỉ trang danh sách cần, để dựng các chip lọc.
         */
        /*
         * GỢI Ý THEO HÀNH VI — khác hẳn "Sản phẩm liên quan" ở trên.
         *
         * "Liên quan" = cùng danh mục, giống nhau với mọi người xem.
         * "Gợi ý" = dựa trên thứ CHÍNH NGƯỜI NÀY vừa xem, thích, bỏ giỏ.
         * Hai khối trả lời hai câu hỏi khác nhau ("còn gì giống thế này?"
         * và "còn gì hợp với tôi?"), nên giữ cả hai chứ không thay thế.
         *
         * Loại trừ chính sản phẩm đang xem VÀ những sản phẩm liên quan
         * vừa hiện ngay bên trên — nếu không, với 15 sản phẩm thì hai
         * khối gần như chắc chắn trùng nhau.
         */
        $recommendations = $this->recommendations->forViewer(
            Auth::id(),
            $request->session()->getId(),
            limit: 4,
            excludeIds: $related->pluck('id')->push($product->id)->all(),
        );

        /*
         * PHỤ KIỆN MUA KÈM.
         *
         * Khác hai khối trên: "liên quan" và "gợi ý" đều đưa ra thứ THAY
         * THẾ món đang xem, còn khối này đưa ra thứ dùng CÙNG nó. Ba khối
         * trả lời ba câu hỏi khác nhau nên cùng tồn tại được.
         */
        $accessories = $this->advisor->accessoriesFor($product);

        /*
         * KHÁCH KHOE CÂY NÀY — bài Góc cây ĐÃ DUYỆT gắn với sản phẩm. Ảnh cây
         * thật ở nhà người mua là bằng chứng xã hội thật nhất trang có; và
         * bài chỉ gắn được với cây ĐÃ MUA (QĐ-129), nên không dựng giả được.
         */
        $baiKhoe = \App\Models\CommunityPost::approved()
            ->where('product_id', $product->id)
            ->with('user:id,name')
            ->latest('approved_at')
            ->limit(4)
            ->get(['id', 'user_id', 'body', 'photo', 'approved_at']);

        // Quà tặng kèm khi mua sản phẩm này — nói TRƯỚC khi khách bỏ vào giỏ.
        $quaKem = app(\App\Services\Gift\GiftResolver::class)->choSanPham($product);

        return view('shop.products.show', compact(
            'quaKem',
            'baiKhoe',
            'product',
            'related',
            'reviews',
            'reviewableOrder',
            'recommendations',
            'accessories',
            'daBan',
            'chiCon',
            'quyCachPhoBien',
        ));
    }
}
