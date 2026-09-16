<?php

namespace App\Services\Care;

use App\Enums\CareTask;
use App\Models\CareReminder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/** Dựng và duy trì lịch nhắc chăm cây. */
class CareScheduler
{
    public function scheduleForOrder(Order $order): int
    {
        $user = $order->user;

        if ($user === null) {
            return 0;
        }

        $created = 0;

        foreach ($order->loadMissing('items')->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);

            if (! $product) {
                continue;
            }

            $created += $this->scheduleForProduct($user, $product);
        }

        return $created;
    }

    public function scheduleForProduct(User $user, Product $product): int
    {
        $created = 0;

        foreach (CareTask::cases() as $task) {
            $days = $this->intervalFor($product, $task);

            if ($days === null) {
                continue;
            }

            $reminder = CareReminder::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'kind' => $task->value,
                ],
                [
                    'interval_days' => $days,
                    'next_due_at' => now()->addDays($days),
                ],
            );

            if ($reminder->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function forUser(User $user): Collection
    {
        return CareReminder::query()
            ->where('user_id', $user->id)
            ->with('product')
            ->orderBy('next_due_at')
            ->get()
            ->filter(fn (CareReminder $r) => $r->product !== null)
            ->values();
    }

    private function intervalFor(Product $product, CareTask $task): ?int
    {
        $care = $product->care_info;

        if (! is_array($care)) {
            return null;
        }

        $raw = $care[$task->configKey()] ?? null;

        if (! is_numeric($raw)) {
            return null;
        }

        $days = (int) $raw;

        return $days >= 1 && $days <= 365 ? $days : null;
    }
}
