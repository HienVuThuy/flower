<?php

namespace App\Services\Catalog;

use App\Enums\NotificationType;
use App\Mail\StockAlertMail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** "Báo tôi khi có hàng": ghi nhận đăng ký và báo lại đúng một lần khi hàng về. */
class StockAlertService
{
    public function dangKyDuoc(Product $product, ?ProductVariant $variant = null): bool
    {
        return ! $this->conHang($product, $variant)
            && in_array($product->status, ['active', 'out_of_stock'], true);
    }

    public function daDangKy(User $user, Product $product, ?ProductVariant $variant = null): bool
    {
        return $this->cua($user, $product, $variant)->exists();
    }

    public function dangKy(User $user, Product $product, ?ProductVariant $variant = null): void
    {
        $dang = $this->cua($user, $product, $variant)->first() ?? new StockAlert();

        $dang->forceFill([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'notified_at' => null,
        ])->save();
    }

    public function huy(User $user, Product $product, ?ProductVariant $variant = null): void
    {
        $this->cua($user, $product, $variant)->delete();
    }

    /** Hàng vừa về: báo cho mọi người đang chờ, mỗi người một lần cho mỗi lượt đăng ký. */
    public function hangVe(Product $product, ?ProductVariant $variant = null): int
    {
        if (! $this->conHang($product, $variant) || $product->status !== 'active') {
            return 0;
        }

        $cho = StockAlert::query()
            ->where('product_id', $product->id)
            ->when(
                $variant !== null,
                fn ($q) => $q->where(fn ($w) => $w->whereNull('product_variant_id')->orWhere('product_variant_id', $variant->id)),
                fn ($q) => $q->whereNull('product_variant_id'),
            )
            ->whereNull('notified_at')
            ->with('user')
            ->get();

        $so = 0;

        foreach ($cho as $dang) {
            if ($dang->user === null) {
                continue;
            }

            $this->bao($dang->user, $product);
            $dang->forceFill(['notified_at' => now()])->save();
            $so++;
        }

        return $so;
    }

    private function bao(User $user, Product $product): void
    {
        (new UserNotification())->forceFill([
            'user_id' => $user->id,
            'type' => NotificationType::HangVe,
            'product_id' => $product->id,
        ])->save();

        if (! $user->hasVerifiedEmail()) {
            return;
        }

        try {
            Mail::to($user->email)->send(new StockAlertMail($product, $user->name));
        } catch (\Throwable $e) {
            Log::warning('Không gửi được thư báo hàng về', [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'loi' => $e->getMessage(),
            ]);
        }
    }

    private function cua(User $user, Product $product, ?ProductVariant $variant)
    {
        return StockAlert::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id);
    }

    private function conHang(Product $product, ?ProductVariant $variant): bool
    {
        if ($variant !== null) {
            return ! $variant->track_inventory || (int) $variant->stock_quantity > 0;
        }

        return $product->inStock();
    }
}
