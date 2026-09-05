<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mọi thao tác với giỏ hàng đi qua đây.
 * ============================================================
 * Controller không được tự query bảng carts/cart_items. Gom về một
 * chỗ vì có vài quy tắc dễ làm sai nếu rải rác:
 *   - khách chưa đăng nhập dùng giỏ theo phiên, đăng nhập rồi thì
 *     phải GỘP giỏ phiên vào giỏ tài khoản chứ không bỏ đi;
 *   - thêm trùng sản phẩm thì cộng dồn, không tạo dòng mới;
 *   - số lượng luôn bị chặn bởi tồn kho thực tế.
 */
class CartService
{
    /** Số lượng tối đa cho một dòng, kể cả khi không quản lý tồn kho. */
    public const MAX_QUANTITY = 99;

    private ?Cart $resolved = null;

    /**
     * Số lượng thực tế sau khi bị kẹp theo tồn kho ở lần add() gần nhất.
     * null nghĩa là không bị cắt. Xem add() và lastClampedTo().
     */
    private ?int $lastClamped = null;

    /**
     * Lấy giỏ hiện tại, tạo mới nếu chưa có.
     * Kết quả được nhớ trong request để không truy vấn lặp.
     */
    public function current(): Cart
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $cart = Auth::check()
            ? Cart::firstOrCreate(['user_id' => Auth::id()])
            : Cart::firstOrCreate(['session_id' => session()->getId()]);

        return $this->resolved = $cart->load([
            'items.product.category',
            'items.product.promotions',
            'items.variant',
        ]);
    }

    /**
     * Gộp giỏ của phiên vào giỏ của tài khoản khi khách đăng nhập.
     *
     * Gọi từ sự kiện Login. Nếu bỏ qua bước này, khách bỏ hàng vào giỏ
     * rồi mới đăng nhập sẽ thấy giỏ trống — một lỗi rất hay gặp.
     */
    public function mergeSessionCartInto(int $userId, string $sessionId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->with('items')->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            $guestCart?->delete();

            return;
        }

        DB::transaction(function () use ($guestCart, $userId) {
            $userCart = Cart::firstOrCreate(['user_id' => $userId]);

            foreach ($guestCart->items as $item) {
                $existing = CartItem::where('cart_id', $userCart->id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => min(self::MAX_QUANTITY, $existing->quantity + $item->quantity),
                    ]);

                    continue;
                }

                $item->update(['cart_id' => $userCart->id]);
            }

            $guestCart->delete();
        });

        $this->resolved = null;
    }

    /**
     * Thêm sản phẩm vào giỏ.
     *
     * @throws CartException khi sản phẩm không bán được hoặc thiếu hàng
     */
    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        $this->assertPurchasable($product, $variant);

        $quantity = max(1, $quantity);
        $cart = $this->current();

        $item = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);

        $wanted = ($item->quantity ?? 0) + $quantity;
        $item->quantity = $this->clampToStock($wanted, $product, $variant);
        $item->save();

        /*
         * GHI LẠI VIỆC ĐÃ CẮT BỚT SỐ LƯỢNG.
         *
         * LỖI CŨ: khách bấm thêm 5 chậu khi kho còn 1 thì giỏ nhận 1,
         * còn màn hình báo "Đã thêm vào giỏ hàng" — y hệt lúc thêm đủ.
         * Họ đi tiếp tới bước thanh toán rồi mới phát hiện, và lúc đó
         * không biết mình gõ sai hay hệ thống làm sai.
         *
         * Cắt bớt là ĐÚNG (không bán thứ không có), nhưng im lặng thì
         * không. Ghi lại ở đây để nơi gọi nói cho khách biết.
         */
        $this->lastClamped = $item->quantity < $wanted
            ? $item->quantity
            : null;

        $this->resolved = null;

        return $item;
    }

    /**
     * Lần add() gần nhất có bị cắt bớt số lượng không.
     *
     * @return int|null số lượng thực tế đã thêm, null nghĩa là thêm đủ
     */
    public function lastClampedTo(): ?int
    {
        return $this->lastClamped;
    }

    /** Đặt lại số lượng cho một dòng. Số lượng 0 nghĩa là xoá dòng. */
    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($item);

            return;
        }

        $item->update([
            'quantity' => $this->clampToStock($quantity, $item->product, $item->variant),
        ]);

        $this->resolved = null;
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
        $this->resolved = null;
    }

    public function clear(): void
    {
        $this->current()->items()->delete();
        $this->resolved = null;
    }

    /**
     * Xoá CHỈ những món đã chọn. Gọi sau khi đặt hàng thành công.
     *
     * ĐÂY LÀ ĐIỂM MẤU CHỐT của việc mua từng phần: đơn hàng chỉ gồm món
     * đã chọn, nên chỉ được xoá đúng những món đó. Gọi clear() ở đây là
     * cuốn sạch cả những thứ khách cố ý để lại — mất dữ liệu của họ mà
     * không có cách nào lấy lại.
     *
     * @return int số dòng đã xoá
     */
    public function clearSelected(): int
    {
        $deleted = $this->current()->items()->where('is_selected', true)->delete();
        $this->resolved = null;

        return $deleted;
    }

    /**
     * Bật/tắt lựa chọn của MỘT dòng.
     *
     * Nhận CartItem đã qua route-model-binding, nhưng vẫn phải tự kiểm
     * tra chủ sở hữu: id đi qua URL nên người gửi sửa được thành bất kỳ
     * số nào, và thiếu phép kiểm này thì ai cũng đổi được giỏ của người
     * khác.
     */
    public function toggleSelection(CartItem $item, bool $selected): bool
    {
        if ($item->cart_id !== $this->current()->id) {
            return false;
        }

        $item->forceFill(['is_selected' => $selected])->save();
        $this->resolved = null;

        return true;
    }

    /**
     * Đặt lựa chọn cho TOÀN BỘ giỏ theo một danh sách id.
     *
     * Dùng cho biểu mẫu ô đánh dấu: trình duyệt chỉ gửi lên những ô ĐƯỢC
     * TÍCH, nên id không có trong danh sách nghĩa là khách vừa bỏ tích.
     * Vì vậy phải ghi cả hai chiều trong một lượt, không thể chỉ bật
     * những cái được gửi lên.
     *
     * @param  list<int>  $selectedIds
     */
    public function setSelection(array $selectedIds): void
    {
        $cart = $this->current();
        $ids = array_map('intval', $selectedIds);

        // Hai câu UPDATE, luôn giới hạn trong giỏ của chính người này —
        // id lạ trong danh sách gửi lên cũng không chạm được giỏ khác.
        $cart->items()->whereIn('id', $ids ?: [0])->update(['is_selected' => true]);
        $cart->items()->whereNotIn('id', $ids ?: [0])->update(['is_selected' => false]);

        $this->resolved = null;
    }

    /** Số món ĐANG CHỌN — dùng để chặn thanh toán khi chưa chọn gì. */
    public function selectedCount(): int
    {
        return $this->current()->items()->where('is_selected', true)->count();
    }

    /** Số món trong giỏ — dùng cho badge ở header, nên phải rẻ. */
    public function count(): int
    {
        return (int) CartItem::whereHas('cart', function ($q) {
            Auth::check()
                ? $q->where('user_id', Auth::id())
                : $q->where('session_id', session()->getId());
        })->sum('quantity');
    }

    /**
     * Chặn ngay từ đầu những thứ không được phép bán.
     *
     * public vì "Mua ngay" cũng phải qua đúng bộ kiểm tra này dù không
     * đi qua giỏ hàng — không được có đường tắt lỏng hơn.
     */
    public function assertPurchasable(Product $product, ?ProductVariant $variant): void
    {
        if ($product->price()->isContactForPrice()) {
            throw new CartException('Sản phẩm này chỉ nhận yêu cầu báo giá, không bán trực tiếp qua giỏ hàng.');
        }

        if ($product->status !== 'active') {
            throw new CartException('Sản phẩm hiện không còn được bán.');
        }

        if ($variant && $variant->product_id !== $product->id) {
            throw new CartException('Phiên bản sản phẩm không hợp lệ.');
        }

        if ($variant && ! $variant->is_active) {
            throw new CartException('Phiên bản này hiện không còn được bán.');
        }

        /*
         * SẢN PHẨM CÓ QUY CÁCH THÌ BẮT BUỘC PHẢI CHỌN MỘT.
         *
         * LỖI TRƯỚC KHI SỬA: không có phép kiểm này, nên gửi lên đúng
         * `product_id` mà bỏ trống `variant_id` là hàng vào giỏ ngon
         * lành — thành một dòng KHÔNG QUY CÁCH, tính theo `base_price`.
         *
         * Đo được trên dữ liệu thật: "Lưỡi hổ mini để bàn" có hai quy
         * cách (Chậu sứ trắng 180.000₫, Chậu gốm nâu 195.000₫). Thêm vào
         * giỏ không kèm quy cách → giỏ nhận một dòng variant = NULL, giá
         * 180.000₫.
         *
         * Hai hỏng hóc từ đó:
         *
         *  1. CỬA HÀNG KHÔNG BIẾT GIAO CHẬU NÀO. Đơn ghi "Lưỡi hổ mini
         *     để bàn" mà không nói chậu sứ hay chậu gốm. Người gói hàng
         *     phải gọi lại hỏi khách, hoặc đoán.
         *  2. LUÔN TÍNH GIÁ RẺ NHẤT. base_price bằng giá quy cách rẻ
         *     nhất, nên ai bỏ qua bước chọn cũng mua được chậu gốm nâu
         *     với giá chậu sứ trắng.
         *
         * Đường vào chính là các thẻ sản phẩm ở trang danh sách — chúng
         * gửi thẳng biểu mẫu mà không hề có ô chọn quy cách.
         *
         * ĐẶT PHÉP KIỂM Ở ĐÂY, KHÔNG Ở CONTROLLER: cả "Thêm vào giỏ",
         * "Mua ngay" lẫn mọi đường thêm hàng sau này đều đi qua hàm này.
         * Để ở controller là phải nhớ chép lại cho từng đường mới.
         */
        if ($variant === null && $product->variants()->where('is_active', true)->exists()) {
            throw new CartException(
                'Sản phẩm này có nhiều quy cách. Vui lòng chọn quy cách trước khi mua.'
            );
        }

        if ($this->stockOf($product, $variant) === 0) {
            throw new CartException('Sản phẩm đã hết hàng.');
        }
    }

    /** null = không quản lý tồn kho (bán theo mùa/đặt trước). */
    private function stockOf(Product $product, ?ProductVariant $variant): ?int
    {
        if ($variant) {
            return $variant->track_inventory ? (int) $variant->stock_quantity : null;
        }

        return $product->track_inventory ? (int) $product->stock_quantity : null;
    }

    private function clampToStock(int $wanted, Product $product, ?ProductVariant $variant): int
    {
        $stock = $this->stockOf($product, $variant);
        $limit = $stock === null ? self::MAX_QUANTITY : min($stock, self::MAX_QUANTITY);

        return max(1, min($wanted, $limit));
    }
}
