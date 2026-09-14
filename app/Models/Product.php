<?php

namespace App\Models;

use App\Enums\CareDifficulty;
use App\Enums\CareProfile;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Observers\ProductObserver;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\ProductPrice;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * CỘT CHỈ MỤC TÌM KIẾM — search_name, search_text.
     *
     * CỐ Ý KHÔNG nằm trong $fillable. Chúng do
     * App\Services\Search\ProductSearchIndexer tính ra từ các cột khác,
     * nên không ai được phép gán tay: một giá trị gán bừa sẽ khiến sản
     * phẩm tìm ra bằng những chữ nó không hề mang, hoặc tệ hơn là biến
     * mất khỏi kết quả tìm kiếm mà mọi trang khác vẫn hiện bình thường.
     * Muốn dựng lại: `php artisan search:reindex`.
     */

    /**
     * LƯU Ý — sale_price / sale_starts_at / sale_ends_at đã NGỪNG
     * SỬ DỤNG (deprecated) từ khi có bảng `promotions`.
     *
     * Chúng bị bỏ khỏi $fillable và khỏi form admin, nên không còn
     * đường nào ghi vào nữa. Cột trong DB được giữ lại có chủ đích:
     * xoá cột là thao tác không hoàn tác được, trong khi giữ lại
     * không gây hại (không nơi nào đọc) và cho phép quay đầu an toàn.
     *
     * Khuyến mại nay do Promotion + PricingService quyết định.
     */
    protected $fillable = [
        'category_id',
        'taxon_id',
        'taxon_note',
        'name',
        'slug',
        'product_code',
        'short_description',
        'description',
        'care_info',
        'product_type',
        'selling_form',
        'base_price',
        /*
         * Nhóm thuế suất. NULL = "chưa phân loại, dùng mức mặc định của
         * cửa hàng" — xem App\Services\Tax\TaxCalculator::rateFor().
         */
        'tax_class_id',
        'main_image',
        'status',
        'track_inventory',
        'stock_quantity',
        'weight',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'care_info' => 'array',
        'track_inventory' => 'boolean',
        'stock_quantity' => 'integer',
        'view_count' => 'integer',

        /*
         * Hai trục phân loại nay là enum, không còn là chuỗi tự do.
         *
         * ĐÁNH ĐỔI PHẢI BIẾT: cast khiến giá trị lạ trong cơ sở dữ liệu
         * trở thành lỗi chết người — đọc bản ghi đó lên là ValueError chứ
         * không phải hiện nhãn sai. Đó là lý do migration
         * 2026_08_27_010000 có bước quét dọn bắt buộc trước khi cast
         * được bật ở đây.
         *
         * Đổi lại: không còn bảng nhãn chép tay rải rác trong Blade,
         * và không thể lưu được một giá trị mà giao diện không biết đọc.
         */
        'product_type' => ProductType::class,
        'selling_form' => SellingForm::class,
    ];

    /** Cache trong phạm vi instance để không tính giá lại nhiều lần. */
    private ?ProductPrice $resolvedPrice = null;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Nhóm thuế suất của sản phẩm.
     *
     * NULL là trạng thái BÌNH THƯỜNG, không phải dữ liệu thiếu: nó nghĩa
     * là "dùng mức mặc định của cửa hàng". Đừng đọc thẳng `rate` từ đây
     * để tính tiền — đi qua TaxCalculator::rateFor(), nơi biết cả luật
     * lùi về mặc định lẫn luật bật/tắt thuế.
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * CHỈ ẢNH. Bảng `product_images` nay chứa cả video (xem migration
     * add_video_to_product_images_table), nên quan hệ này lọc lại `kind` để mọi
     * nơi gọi nó từ trước — gallery, galleryPaths(), trang quản trị — giữ
     * nguyên ý nghĩa cũ.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('kind', ProductImage::ANH)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Ảnh và video chung một dải, theo đúng thứ tự người bán đã xếp. */
    public function media(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('kind', ProductImage::VIDEO)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Khối mô tả chi tiết: chữ và ảnh xen kẽ, theo thứ tự đã xếp. */
    public function blocks(): HasMany
    {
        return $this->hasMany(ProductBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Toàn bộ ảnh cho gallery: ảnh đại diện đứng đầu, rồi tới ảnh phụ.
     * Trả về mảng đường dẫn tương đối trong disk `public`.
     *
     * @return list<string>
     */
    public function galleryPaths(): array
    {
        $paths = [];

        if ($this->main_image) {
            $paths[] = $this->main_image;
        }

        foreach ($this->images as $image) {
            if (! in_array($image->path, $paths, true)) {
                $paths[] = $image->path;
            }
        }

        return $paths;
    }

    /**
     * Phải chỉ rõ tên bảng pivot: Laravel mặc định suy ra
     * `product_promotion` (ghép 2 tên model theo thứ tự alphabet),
     * trong khi bảng thực tế tên là `promotion_product`.
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withPivot(['discount_type', 'discount_value', 'promotional_price'])
            ->withTimestamps();
    }

    public function bulkOrderInquiries(): HasMany
    {
        return $this->hasMany(BulkOrderInquiry::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(UserEvent::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Nhãn phân loại nhiều-giá-trị. Xem App\Enums\TraitType. */
    /**
     * Nút phân loại sinh học của cây này.
     *
     * NULL LÀ HỢP LỆ và rất thường gặp: phụ kiện, vật tư, và cả những
     * bó hoa nhập theo lô mà cửa hàng không xác định chắc được loài.
     * Bắt buộc điền là bắt người nhập liệu bịa dữ liệu — xem chú thích
     * ở migration.
     */
    public function taxon(): BelongsTo
    {
        return $this->belongsTo(PlantTaxon::class, 'taxon_id');
    }

    public function traits(): HasMany
    {
        return $this->hasMany(ProductTrait::class);
    }

    /**
     * Các giá trị của một loại nhãn, dạng mảng chuỗi.
     *
     * Nhớ eager-load `traits` khi dùng trong vòng lặp; nếu không mỗi lần
     * gọi là một truy vấn. Đọc từ quan hệ ĐÃ NẠP chứ không truy vấn lại,
     * nên gọi ba lần cho ba loại nhãn vẫn chỉ tốn một lượt.
     *
     * @return list<string>
     */
    public function traitValues(TraitType $type): array
    {
        return $this->traits
            ->where('trait_type', $type)
            ->pluck('trait_value')
            ->all();
    }

    /**
     * Ghi đè toàn bộ nhãn của MỘT loại.
     *
     * NƠI DUY NHẤT được ghi vào product_traits. Bảng dùng varchar nên cơ
     * sở dữ liệu không chặn được giá trị lạ — chặn nằm ở đây, và chỉ giữ
     * được nếu không có đường ghi nào khác.
     *
     * Xoá rồi chèn lại thay vì so sánh từng cái: danh sách luôn dưới chục
     * phần tử, và cách này không thể để sót nhãn cũ mà form vừa bỏ tích.
     *
     * @param  list<string>  $values
     */
    public function syncTraits(TraitType $type, array $values): void
    {
        $valid = array_values(array_unique(array_filter(
            $values,
            fn ($v) => is_string($v) && ProductTrait::isValid($type, $v),
        )));

        $this->traits()->where('trait_type', $type)->delete();

        if ($valid === []) {
            return;
        }

        $this->traits()->createMany(array_map(
            fn (string $v) => ['trait_type' => $type->value, 'trait_value' => $v],
            $valid,
        ));

        // Quan hệ đã nạp trước đó nay lạc hậu; bỏ đi để lần đọc sau lấy
        // dữ liệu mới. Thiếu dòng này thì ngay sau khi lưu, trang vẫn
        // hiện nhãn cũ.
        $this->unsetRelation('traits');
    }

    /**
     * CHỈ HÀNG CHÍNH — hoa và cây cảnh.
     * ============================================================
     * Đây là cửa hàng hoa và cây cảnh. Chậu, đất, phân bón đều cần
     * thiết, nhưng không ai vào đây để mua một gói đất — họ mua đất VÌ
     * vừa mua một cái cây.
     *
     * Trước khi có scope này, trang chủ sắp theo "mới nhất" nên năm gói
     * vật tư thêm sau cùng đẩy hết hoa xuống dưới: khách mở trang bán hoa
     * và thấy đầu tiên là "Kéo cắt cành mũi cong".
     *
     * Dùng ở MỌI nơi trưng hàng cho khách duyệt: trang chủ, trang sản
     * phẩm, gợi ý cá nhân hoá. KHÔNG dùng ở khối "mua kèm" — chỗ đó tồn
     * tại đúng để bán vật tư.
     *
     * whereHas thay vì join: quan hệ đã khai sẵn, và join thủ công sẽ
     * nhân dòng nếu sau này category có quan hệ nhiều-nhiều.
     */
    public function scopeMainCatalog(Builder $query): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->plants()->active());
    }

    /**
     * HÀNG MỚI VỀ — nhập trong khoảng ngày cấu hình được.
     *
     * KHÁC HẲN `latest()`. `latest()` trả về "n món thêm gần đây nhất",
     * và nó luôn trả về đủ n món kể cả khi món cũ nhất trong đó đã nằm
     * kho từ năm ngoái. Scope này trả về "những món THẬT SỰ mới", và trả
     * về RỖNG khi không có món nào — đó là câu trả lời đúng, không phải
     * một khiếm khuyết cần lấp.
     *
     * Ngưỡng ngày đặt ở `config/catalog.php` kèm lý do chọn con số.
     */
    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query
            ->where('created_at', '>=', now()->subDays((int) config('catalog.new_arrival_days', 60)))
            ->latest();
    }

    /** CHỈ HÀNG PHỤ TRỢ — phụ kiện và vật tư chăm sóc. */
    public function scopeSupplyCatalog(Builder $query): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->supplies()->active());
    }

    /*
     * ->active() THÊM VÀO CẢ HAI SCOPE TRÊN.
     *
     * LỖI TRƯỚC KHI SỬA: tắt một danh mục (`is_active = false`) chỉ làm
     * danh mục biến mất khỏi trang Danh mục — sản phẩm bên trong vẫn
     * hiện đầy đủ ở /san-pham, vẫn tìm được, vẫn mua được.
     *
     * Với người quản trị thì "tắt danh mục" chỉ có một nghĩa: ngừng bán
     * nhóm hàng đó. Nếu nó chỉ giấu cái nhãn mà để hàng bày nguyên trên
     * kệ thì nút tắt là một lời hứa suông, và cửa hàng phát hiện ra
     * bằng cách có người đặt đúng thứ mình vừa ngừng bán.
     */

    /**
     * Lọc theo ĐỘ KHÓ CHĂM SÓC.
     * ============================================================
     * MỘT NƠI SỞ HỮU DUY NHẤT cho luật này — trước đây nó nằm riêng
     * trong `PlantAdvisor::onlyDifficulty()`, và khi trang sản phẩm cũng
     * cần lọc theo độ khó thì luật sẽ bị chép sang bản thứ hai.
     *
     * Chép thì bản thứ hai gần như chắc chắn quên vế thứ hai bên dưới,
     * và không có gì báo — kết quả chỉ đơn giản là sai.
     *
     * ============================================================
     * ĐIỀU KIỆN HÌNH THỨC BÁN LÀ BẮT BUỘC, KHÔNG PHẢI TÙY CHỌN.
     *
     * Hoa cắt cành không có khái niệm "dễ chăm" hay "khó chăm" — chúng
     * tàn sau vài ngày dù chăm kiểu gì. Để lọt vào đây thì bộ lọc "tôi
     * mới trồng cây" trả về một đống bó hoa, đúng thứ khách KHÔNG hỏi.
     */
    public function scopeWithCareDifficulty(Builder $query, CareDifficulty $difficulty): Builder
    {
        return $query
            ->where('care_info->difficulty', $difficulty->value)
            ->whereIn('selling_form', [
                SellingForm::Pot->value,
                SellingForm::Original->value,
                SellingForm::Set->value,
            ]);
    }

    /**
     * Lọc theo một nhãn. Dùng cho trang tư vấn chọn cây.
     */
    public function scopeWithTrait(Builder $query, TraitType $type, string $value): Builder
    {
        return $query->whereHas(
            'traits',
            fn ($q) => $q->where('trait_type', $type->value)->where('trait_value', $value),
        );
    }

    /**
     * Lọc theo một nhánh của cây phân loại sinh học.
     *
     * LẤY CẢ NHÁNH, KHÔNG CHỈ ĐÚNG NÚT ĐÓ. Chọn "Họ Ráy" phải ra cả
     * Monstera, Trầu bà, Kim tiền, Lan ý — chúng gắn ở bậc Chi và Loài
     * bên dưới, không gắn thẳng vào Họ.
     *
     * Chỉ khớp đúng nút được chọn thì bậc càng cao càng ít kết quả, tức
     * là ngược hẳn với thứ người dùng mong đợi: bấm vào một nhóm rộng
     * hơn mà lại ra ít hàng hơn.
     */
    public function scopeInTaxon(Builder $query, PlantTaxon $taxon): Builder
    {
        return $query->whereIn('taxon_id', $taxon->descendantIds());
    }

    /**
     * Sắp xếp theo GIÁ KHÁCH THẬT SỰ TRẢ, không phải giá niêm yết.
     *
     * LỖI TRƯỚC KHI SỬA: các trang danh sách đều orderBy('base_price').
     * Nhưng thẻ sản phẩm hiển thị giá SAU khuyến mại. Nên một chậu 500k
     * đang giảm còn 250k vẫn bị xếp cùng chỗ với hàng 500k, trong khi
     * khách nhìn thấy 250k. Chọn "Giá thấp đến cao" rồi thấy 250k nằm
     * sau 300k — thứ tự nói dối ngay trên màn hình.
     *
     * Trớ trêu là nó sai đúng ở nơi đau nhất: hàng đang giảm giá chính
     * là hàng cửa hàng muốn khách thấy trước.
     *
     * VÌ SAO PHẢI LÀM TRONG SQL, KHÔNG SẮP BẰNG PHP: danh sách có phân
     * trang. Sắp xếp sau khi đã lấy 12 sản phẩm của trang 1 chỉ đảo thứ
     * tự TRONG trang đó — món rẻ nhất nằm ở trang 3 vẫn ở trang 3.
     *
     * Biểu thức dưới đây chép lại đúng luật của PricingService: lấy mức
     * riêng của sản phẩm trong pivot nếu có, không thì lấy mức chung của
     * chương trình; chỉ tính ba kiểu đã cài đặt; nhiều chương trình cùng
     * áp thì lấy GIÁ THẤP NHẤT. Chênh lệch làm tròn ở hàng xu là có thể,
     * nhưng nó không đổi được thứ tự giữa hai sản phẩm.
     *
     * "Đang chạy" tính theo MỘT MỐC THỜI GIAN duy nhất do PHP truyền
     * xuống, không dùng NOW() của cơ sở dữ liệu: cùng một request phải
     * cho cùng một câu trả lời, kể cả khi nó chạy vắt qua nửa đêm.
     */
    public function scopeOrderByEffectivePrice(Builder $query, string $direction = 'asc'): Builder
    {
        $now = now();
        $moc = $now->toDateTimeString();
        $thu = $now->isoWeekday();          // 1 = Thứ Hai ... 7 = Chủ Nhật
        $gio = $now->format('H:i:s');

        /*
         * Dựng bằng query builder chứ KHÔNG viết một chuỗi SQL thô.
         *
         * Lý do rất cụ thể: cách hỏi "mảng JSON này có chứa số 3 không"
         * khác nhau giữa MariaDB (JSON_CONTAINS) và SQLite mà bộ kiểm
         * thử đang dùng (json_each). Viết tay tên hàm của một bên thì
         * bên kia ngã ngay khi chạy — và ngã ở tầng kiểm thử nghĩa là
         * không còn ai canh chỗ này nữa. Query builder tự sinh đúng câu
         * cho từng loại cơ sở dữ liệu.
         */
        $mucGiam = 'COALESCE(pp.discount_value, pr.discount_value)';

        $tinh = <<<SQL
            CASE COALESCE(pp.discount_type, pr.type)
                WHEN 'percent'      THEN products.base_price - products.base_price * $mucGiam / 100
                WHEN 'fixed_amount' THEN products.base_price - $mucGiam
                WHEN 'fixed_price'  THEN $mucGiam
                ELSE products.base_price
            END
            SQL;

        /*
         * HAI CHỐT CHẶN, chép đúng từ PricingService::resolve():
         * giá sau khuyến mại không bao giờ âm, và không bao giờ CAO HƠN
         * giá gốc. Mức giảm cấu hình sai — chẳng hạn đặt "giá cố định"
         * 899.000₫ cho món niêm yết 600.000₫ — thì bỏ qua khuyến mại,
         * chứ không bán đắt hơn giá niêm yết.
         *
         * Thiếu hai dòng này thì thứ tự lệch đúng ở những sản phẩm cấu
         * hình sai — nhóm khó phát hiện nhất, vì nhìn danh sách không ai
         * đoán được vì sao chúng nằm sai chỗ.
         *
         * Viết bằng CASE chứ không dùng LEAST/GREATEST: hai hàm đó không
         * có trong SQLite, mà bộ kiểm thử chạy trên SQLite.
         */
        $giaHieuLuc = DB::table('promotion_product as pp')
            ->join('promotions as pr', 'pr.id', '=', 'pp.promotion_id')
            ->selectRaw(<<<SQL
                COALESCE(MIN(
                    CASE
                        WHEN ($tinh) < 0 THEN 0
                        WHEN ($tinh) >= products.base_price THEN products.base_price
                        ELSE ($tinh)
                    END
                ), products.base_price)
                SQL)
            // Tương quan với hàng đang xét ở truy vấn ngoài.
            ->whereColumn('pp.product_id', 'products.id')
            ->where('pr.status', 'active')
            ->where(fn ($q) => $q->whereNull('pr.starts_at')->orWhere('pr.starts_at', '<=', $moc))
            ->where(fn ($q) => $q->whereNull('pr.ends_at')->orWhere('pr.ends_at', '>=', $moc))
            // Combo và "mua X tặng Y" chưa có công thức tính giá; đúng
            // như PricingService, bỏ qua chứ không đoán bừa một con số.
            ->whereIn(DB::raw('COALESCE(pp.discount_type, pr.type)'),
                ['percent', 'fixed_amount', 'fixed_price'])
            ->whereNotNull(DB::raw($mucGiam))
            /*
             * Không khai thứ nào = chạy mọi ngày. Hỏi cả số lẫn chuỗi vì
             * JSON có thể đang giữ [1,3] hoặc ["1","3"] tuỳ đường ghi.
             */
            ->where(fn ($q) => $q
                ->whereNull('pr.weekdays')
                ->orWhereJsonLength('pr.weekdays', 0)
                ->orWhereJsonContains('pr.weekdays', $thu)
                ->orWhereJsonContains('pr.weekdays', (string) $thu))
            /*
             * Khung giờ trong ngày. Khai thiếu một đầu thì coi như không
             * giới hạn — cùng cách hiểu với Promotion::isWithinDailyWindow().
             * Nhánh thứ hai là khung VẮT QUA NỬA ĐÊM (22:00 → 02:00), khi
             * giờ bắt đầu lớn hơn giờ kết thúc.
             */
            ->where(fn ($q) => $q
                ->whereNull('pr.daily_start_time')
                ->orWhereNull('pr.daily_end_time')
                ->orWhere(fn ($w) => $w
                    ->whereColumn('pr.daily_start_time', '<=', 'pr.daily_end_time')
                    ->where('pr.daily_start_time', '<=', $gio)
                    ->where('pr.daily_end_time', '>=', $gio))
                ->orWhere(fn ($w) => $w
                    ->whereColumn('pr.daily_start_time', '>', 'pr.daily_end_time')
                    ->where(fn ($x) => $x
                        ->where('pr.daily_start_time', '<=', $gio)
                        ->orWhere('pr.daily_end_time', '>=', $gio))));

        return $query->orderBy($giaHieuLuc, strtolower($direction) === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Dọn file ảnh KHI SẢN PHẨM BỊ XOÁ VĨNH VIỄN, không sớm hơn.
     *
     * Đặt ở model chứ không ở controller vì đây là điều luôn đúng với
     * mọi đường xoá vĩnh viễn — trang quản trị, lệnh dọn dẹp, hay một
     * màn hình nào đó viết sau này. Để ở controller thì mỗi đường mới
     * lại phải nhớ chép lại, và sớm muộn có một đường quên.
     *
     * Chỉ nghe forceDeleting, KHÔNG nghe deleted: xoá mềm còn khôi phục
     * được, mà file đã xoá thì không.
     *
     * forceDeleting (TRƯỚC khi xoá) chứ không phải forceDeleted (sau):
     * bản ghi product_images đi theo sản phẩm bằng khoá ngoại cascade,
     * nên nghe sau thì danh sách ảnh đã rỗng và không còn biết phải xoá
     * file nào. File ở lại vĩnh viễn mà không có gì trỏ tới nữa.
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (self $product) {
            /*
             * QUA ImageStore, không xoá file trần.
             *
             * Lỗi đã sửa: xoá thẳng ảnh gốc để lại mọi bản WebP và các cỡ
             * ảnh đã sinh, cùng dòng trong manifest. media() chứ không
             * images(): video tải lên cũng là một tệp trên đĩa. Và ảnh trong
             * khối mô tả trước đây không được dọn gì cả.
             */
            $anh = app(\App\Services\Media\ImageStore::class);

            $anh->xoa($product->main_image);

            foreach ($product->media as $tep) {
                $anh->xoa($tep->path);
            }

            foreach ($product->blocks as $khoi) {
                $anh->xoa($khoi->getAttribute('image'));
            }
        });
    }

    /**
     * Cân nặng để tính phí giao, tính bằng GRAM.
     *
     * Thứ tự ưu tiên có lý do nghiệp vụ rõ ràng:
     *
     *   1. cân nặng của QUY CÁCH — "Chậu đá 18cm" nặng hơn hẳn "Chậu đá
     *      12cm", dù cùng một sản phẩm;
     *   2. cân nặng của SẢN PHẨM — khi mọi quy cách nặng như nhau, hoặc
     *      hàng không có quy cách;
     *   3. mức mặc định trong cấu hình — khi cửa hàng chưa khai.
     *
     * Mức mặc định là con số TẠM, không phải con số đúng: khai thiếu thì
     * phí tính hụt và phần hụt cửa hàng chịu. Vì vậy cột `weight` để
     * nullable — null nghĩa là "chưa khai", một thông tin có ích để
     * trang quản trị nhắc.
     */
    public function shippingWeight(?ProductVariant $variant = null): int
    {
        $gram = $variant?->weight ?? $this->weight;

        return (int) ($gram ?: config('services.ghn.default_weight', 200));
    }

    /**
     * Những dòng đơn hàng đã bán sản phẩm này.
     *
     * Dùng để trả lời "người này đã mua cây nào" khi họ tạo sổ nhật ký —
     * nhật ký chỉ gắn được vào cây ĐÃ VỀ TAY, không gắn vào cây đang
     * ngắm trong giỏ hay danh sách yêu thích.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /** Người đang đăng nhập đã thích sản phẩm này chưa. */
    public function isWishlisted(): bool
    {
        return in_array(
            $this->id,
            Wishlist::productIdsFor(Auth::id()),
            strict: true,
        );
    }

    /**
     * Điểm trung bình và số lượt đánh giá ĐANG HIỂN THỊ.
     *
     * Dùng trong danh sách sản phẩm thì nhớ nạp sẵn ở controller:
     *     ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
     *     ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
     * Gọi thẳng hai hàm dưới đây trong vòng lặp sẽ sinh N+1.
     */
    public function ratingAverage(): ?float
    {
        /*
         * Dùng array_key_exists chứ KHÔNG dùng ??.
         *
         * Sản phẩm chưa ai đánh giá thì withAvg trả về null. Với ?? thì
         * null bị hiểu là "chưa nạp" và câu truy vấn dự phòng chạy lại
         * mỗi lần gọi — mà phần lớn sản phẩm đều chưa có đánh giá, nên đó
         * chính là trường hợp phổ biến nhất. Đo được 4 câu avg() thừa chỉ
         * trên một trang chi tiết.
         */
        $avg = array_key_exists('rating_avg', $this->attributes)
            ? $this->attributes['rating_avg']
            : $this->reviews()->visible()->avg('rating');

        return $avg === null ? null : round((float) $avg, 1);
    }

    public function ratingCount(): int
    {
        $count = array_key_exists('rating_count', $this->attributes)
            ? $this->attributes['rating_count']
            : $this->reviews()->visible()->count();

        return (int) $count;
    }

    /**
     * Giá đã tính khuyến mại.
     *
     * Khi dùng trong danh sách, nhớ eager-load `promotions` ở
     * controller (Product::with('promotions')) để tránh N+1.
     */
    /**
     * Hình thức bán dưới dạng enum.
     *
     * Cột đã được cast sang enum, nên hàm này chỉ còn là tên gọi quen
     * thuộc cho $product->selling_form. Giữ lại để không phải sửa những
     * chỗ đang gọi; code mới dùng thẳng thuộc tính cũng được.
     */
    public function sellingForm(): ?SellingForm
    {
        return $this->selling_form;
    }

    /**
     * Bộ thông tin chăm sóc áp dụng cho sản phẩm này.
     *
     * Guide mục 4.4: bó hoa và cây chậu KHÔNG dùng chung một bộ thuộc
     * tính. Hình thức bán quyết định bộ nào.
     */
    public function careProfile(): CareProfile
    {
        return $this->sellingForm()?->careProfile() ?? CareProfile::Minimal;
    }

    /**
     * Thông tin chăm sóc đã lọc theo đúng hồ sơ, bỏ ô rỗng.
     *
     * @return array<string, string>
     */
    public function careEntries(): array
    {
        if (! is_array($this->care_info)) {
            return [];
        }

        $out = [];

        foreach ($this->careProfile()->keys() as $key) {
            $value = $this->care_info[$key] ?? null;

            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function price(): ProductPrice
    {
        return $this->resolvedPrice ??= app(PricingService::class)->resolve($this);
    }

    public function isOnSale(): bool
    {
        return $this->price()->isDiscounted();
    }

    public function currentPrice(): ?string
    {
        return $this->price()->finalPrice;
    }

    /** Chương trình đang áp dụng cho sản phẩm này (nếu có). */
    public function activePromotion(): ?Promotion
    {
        return $this->price()->promotion;
    }

    /**
     * Còn hàng hay không. Sản phẩm không bật track_inventory thì
     * luôn coi là còn hàng (đặt theo yêu cầu, làm thủ công/theo mùa).
     */
    public function inStock(): bool
    {
        if (! $this->track_inventory) {
            return true;
        }

        return (int) $this->stock_quantity > 0;
    }

    /**
     * Sản phẩm này có thật sự mua được không — XÉT CẢ QUY CÁCH.
     *
     * VÌ SAO CẦN RIÊNG MỘT HÀM: `inStock()` chỉ nhìn cột tồn kho của
     * chính sản phẩm. Với hàng CÓ QUY CÁCH thì cột đó không phải là thứ
     * khách mua — họ mua một quy cách cụ thể, và mỗi quy cách có kho
     * riêng.
     *
     * Hậu quả nếu bỏ qua: mọi quy cách đã bán hết mà cột kho của sản
     * phẩm vẫn còn số dương thì trang hiện huy hiệu "Còn hàng", nút
     * "Thêm vào giỏ" vẫn sáng, còn bảng chọn quy cách thì mọi ô đều bị
     * vô hiệu hoá. Khách bấm mãi không được và không có gì giải thích.
     *
     * KHÔNG sửa thẳng `inStock()`: hàm đó còn được dùng ở chỗ chỉ quan
     * tâm tới kho của chính sản phẩm, và đổi ý nghĩa của nó là đổi hành
     * vi ở những nơi chưa xem xét tới.
     */
    public function isPurchasable(): bool
    {
        $variants = $this->relationLoaded('variants')
            // Đã nạp sẵn thì lọc trong bộ nhớ — trang danh sách gọi hàm
            // này cho từng thẻ sản phẩm, mỗi lần một truy vấn là N+1.
            ? $this->variants->where('is_active', true)
            : $this->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return $this->inStock();
        }

        // Chỉ cần MỘT quy cách còn hàng là còn bán được.
        return $variants->contains(
            fn (ProductVariant $v) => ! $v->track_inventory || (int) $v->stock_quantity > 0
        );
    }
}
