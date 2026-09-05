<?php

namespace App\Services\Care;

use App\Enums\CareTask;
use App\Models\CareReminder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dựng và duy trì lịch nhắc chăm cây.
 * ============================================================
 * NƠI DUY NHẤT quyết định "đơn này sinh ra những lịch nhắc nào".
 *
 * Gọi từ OrderService khi đơn chuyển sang ĐÃ GIAO — không phải lúc đặt
 * hàng, vì lúc đó cây còn ở cửa hàng và nhắc tưới là thông báo rác.
 *
 * BA ĐIỀU KIỆN để một dòng đơn sinh lịch, thiếu một là bỏ qua:
 *   1. đơn có tài khoản (khách vãng lai không có chỗ nào để nhận nhắc
 *      định kỳ, và cũng không có nơi nào để tắt);
 *   2. sản phẩm còn tồn tại và có khai chu kỳ trong care_info;
 *   3. khách chưa tắt công tắc tổng.
 *
 * KHÔNG BỊA CHU KỲ. Cây nào admin chưa khai `water_days` thì không sinh
 * lịch nào — đoán "chắc là 3 ngày" rồi nhắc sai là làm hỏng cây của
 * khách bằng chính tính năng sinh ra để cứu nó.
 */
class CareScheduler
{
    /**
     * Dựng lịch cho toàn bộ cây trong một đơn vừa giao.
     *
     * @return int số lịch mới tạo
     */
    public function scheduleForOrder(Order $order): int
    {
        $user = $order->user;

        // Khách vãng lai: không có tài khoản thì không có nơi để nhận
        // nhắc định kỳ, và quan trọng hơn — không có nơi để TẮT nó.
        if ($user === null) {
            return 0;
        }

        $created = 0;

        foreach ($order->loadMissing('items')->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);

            // Sản phẩm đã bị xoá hẳn khỏi hệ thống. Đơn vẫn giữ bản chụp
            // tên và giá, nhưng không còn care_info để đọc chu kỳ.
            if (! $product) {
                continue;
            }

            $created += $this->scheduleForProduct($user, $product);
        }

        return $created;
    }

    /**
     * Dựng lịch cho MỘT cây. Trả về số lịch mới tạo.
     */
    public function scheduleForProduct(User $user, Product $product): int
    {
        $created = 0;

        foreach (CareTask::cases() as $task) {
            $days = $this->intervalFor($product, $task);

            if ($days === null) {
                continue;
            }

            /*
             * firstOrCreate chứ không updateOrCreate.
             *
             * Khách mua lại đúng cây đó lần thứ hai thì KHÔNG được dời
             * lịch hiện tại về mốc mới — cây thứ nhất vẫn đang cần tưới
             * đúng chu kỳ của nó. Và nếu khách đã tự tắt lịch này thì
             * updateOrCreate sẽ bật lại sau lưng họ.
             */
            $reminder = CareReminder::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'kind' => $task->value,
                ],
                [
                    'interval_days' => $days,
                    /*
                     * Kỳ đầu tính từ HÔM NAY cộng nguyên chu kỳ.
                     *
                     * Cây vừa giao thường đã được tưới ở cửa hàng, nên
                     * nhắc tưới ngay hôm nhận là vừa thừa vừa hại.
                     */
                    'next_due_at' => now()->addDays($days),
                ],
            );

            if ($reminder->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Các lịch của một khách, kèm sản phẩm, sắp theo mức cấp bách.
     *
     * @return Collection<int, CareReminder>
     */
    public function forUser(User $user): Collection
    {
        return CareReminder::query()
            ->where('user_id', $user->id)
            ->with('product')
            // Quá hạn lên đầu, rồi tới sắp tới hạn. Đây là thứ tự khách
            // cần nhìn: việc nào đang trễ thì phải thấy trước.
            ->orderBy('next_due_at')
            ->get()
            // Sản phẩm bị xoá hẳn: khoá ngoại đã cascade nên gần như
            // không xảy ra, lọc cho chắc.
            ->filter(fn (CareReminder $r) => $r->product !== null)
            ->values();
    }

    /**
     * Chu kỳ (số ngày) của một việc chăm sóc, hoặc null nếu chưa khai.
     *
     * Đọc từ care_info — nơi admin nhập. Giá trị lạ (chữ, số âm, 0) đều
     * trả về null: thà không có lịch còn hơn có một lịch nhắc mỗi 0 ngày.
     */
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
