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
        'tax_class_id',
        'main_image',
        'status',
        'is_featured',
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
        'is_featured' => 'boolean',
        'stock_quantity' => 'integer',
        'view_count' => 'integer',

        'product_type' => ProductType::class,
        'selling_form' => SellingForm::class,
    ];

    private ?ProductPrice $resolvedPrice = null;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('kind', ProductImage::ANH)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

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

    public function blocks(): HasMany
    {
        return $this->hasMany(ProductBlock::class)->orderBy('sort_order')->orderBy('id');
    }

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

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withPivot(['discount_type', 'discount_value', 'promotional_price', 'gift_item_id', 'gift_quantity'])
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

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(PlantTaxon::class, 'taxon_id');
    }

    public function traits(): HasMany
    {
        return $this->hasMany(ProductTrait::class);
    }

    public function traitValues(TraitType $type): array
    {
        return $this->traits
            ->where('trait_type', $type)
            ->pluck('trait_value')
            ->all();
    }

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

        $this->unsetRelation('traits');
    }

    public function scopeMainCatalog(Builder $query): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->plants()->active());
    }

    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query
            ->where('created_at', '>=', now()->subDays((int) config('catalog.new_arrival_days', 60)))
            ->latest();
    }

    public function scopeSupplyCatalog(Builder $query): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->supplies()->active());
    }

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

    public function scopeWithTrait(Builder $query, TraitType $type, string $value): Builder
    {
        return $query->whereHas(
            'traits',
            fn ($q) => $q->where('trait_type', $type->value)->where('trait_value', $value),
        );
    }

    public function scopeInTaxon(Builder $query, PlantTaxon $taxon): Builder
    {
        return $query->whereIn('taxon_id', $taxon->descendantIds());
    }

    public function scopeOrderByEffectivePrice(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy(self::giaHieuLucSql(), strtolower($direction) === 'desc' ? 'desc' : 'asc');
    }

    /** Lọc theo giá đang bán (sau khuyến mại). Hàng "liên hệ báo giá" không có giá nên không lọt vào khoảng nào. */
    public function scopeEffectivePriceBetween(Builder $query, ?int $tu, ?int $den): Builder
    {
        $query->where('products.base_price', '>', 0);

        if ($tu !== null) {
            $query->where(self::giaHieuLucSql(), '>=', $tu);
        }

        if ($den !== null) {
            $query->where(self::giaHieuLucSql(), '<=', $den);
        }

        return $query;
    }

    /** Giá đang bán tính bằng SQL — cùng luật với PricingService, để sắp xếp và lọc được ngay trong truy vấn. */
    public static function giaHieuLucSql(): \Illuminate\Database\Query\Builder
    {
        $now = now();
        $moc = $now->toDateTimeString();

        $diaPhuong = $now->copy()->setTimezone(\App\Services\Time\Gio::mui());
        $thu = $diaPhuong->isoWeekday();
        $gio = $diaPhuong->format('H:i:s');

        $mucGiam = 'COALESCE(pp.discount_value, pr.discount_value)';

        $tinh = <<<SQL
            CASE COALESCE(pp.discount_type, pr.type)
                WHEN 'percent'      THEN products.base_price - products.base_price * $mucGiam / 100
                WHEN 'fixed_amount' THEN products.base_price - $mucGiam
                WHEN 'fixed_price'  THEN $mucGiam
                ELSE products.base_price
            END
            SQL;

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
            ->whereColumn('pp.product_id', 'products.id')
            ->where('pr.status', 'active')
            ->where(fn ($q) => $q->whereNull('pr.starts_at')->orWhere('pr.starts_at', '<=', $moc))
            ->where(fn ($q) => $q->whereNull('pr.ends_at')->orWhere('pr.ends_at', '>=', $moc))
            ->whereIn(DB::raw('COALESCE(pp.discount_type, pr.type)'),
                ['percent', 'fixed_amount', 'fixed_price'])
            ->whereNotNull(DB::raw($mucGiam))
            ->where(fn ($q) => $q
                ->whereNull('pr.weekdays')
                ->orWhereJsonLength('pr.weekdays', 0)
                ->orWhereJsonContains('pr.weekdays', $thu)
                ->orWhereJsonContains('pr.weekdays', (string) $thu))
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

        return $giaHieuLuc;
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $product) {
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

    public function shippingWeight(?ProductVariant $variant = null): int
    {
        $gram = $variant?->weight ?? $this->weight;

        return (int) ($gram ?: config('services.ghn.default_weight', 200));
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function isWishlisted(): bool
    {
        return in_array(
            $this->id,
            Wishlist::productIdsFor(Auth::id()),
            strict: true,
        );
    }

    public function ratingAverage(): ?float
    {
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

    public function sellingForm(): ?SellingForm
    {
        return $this->selling_form;
    }

    public function careProfile(): CareProfile
    {
        return $this->sellingForm()?->careProfile() ?? CareProfile::Minimal;
    }

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

    public function activePromotion(): ?Promotion
    {
        return $this->price()->promotion;
    }

    public function inStock(): bool
    {
        if (! $this->track_inventory) {
            return true;
        }

        return (int) $this->stock_quantity > 0;
    }

    public function isPurchasable(): bool
    {
        $variants = $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)
            : $this->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return $this->inStock();
        }

        return $variants->contains(
            fn (ProductVariant $v) => ! $v->track_inventory || (int) $v->stock_quantity > 0
        );
    }
}
