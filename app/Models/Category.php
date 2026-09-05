<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CategoryObserver::class])]
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'kind' => CategoryKind::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Danh mục HÀNG CHÍNH (hoa, cây cảnh) — mặc định của mọi trang bán.
     *
     * NULL cũng tính là hàng chính: bản ghi tạo trước migration thêm cột
     * này đều là hoa và cây cảnh. Bỏ sót điều kiện đó thì catalog cũ biến
     * mất khỏi trang chủ mà không báo lỗi gì.
     */
    public function scopePlants(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('kind', CategoryKind::Plant->value)
            ->orWhereNull('kind'));
    }

    /**
     * Danh mục đang bật.
     *
     * TÁCH RIÊNG khỏi plants()/supplies() vì hai câu hỏi khác nhau:
     * "danh mục này thuộc nhóm nào" và "cửa hàng có đang mở bán nhóm
     * này không". Gộp lại thì trang quản trị — nơi PHẢI thấy cả danh
     * mục đã tắt — không dùng lại được scope nhóm.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Danh mục PHỤ TRỢ (phụ kiện, vật tư chăm sóc). */
    public function scopeSupplies(Builder $query): Builder
    {
        return $query->where('kind', CategoryKind::Supply->value);
    }

    public function isSupply(): bool
    {
        return $this->kind === CategoryKind::Supply;
    }
}