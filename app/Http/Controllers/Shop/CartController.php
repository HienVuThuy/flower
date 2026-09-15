<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserEvent;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutSource;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutSource $checkout,
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function index(): RedirectResponse|View
    {
        /*
         * MỞ TRANG GIỎ HÀNG = HUỶ LƯỢT "MUA NGAY" ĐANG DANG DỞ.
         * ============================================================
         * LỖI ĐO ĐƯỢC TRƯỚC KHI SỬA:
         *   1. khách thêm "Đất trồng 5kg" (55.000đ) vào giỏ;
         *   2. bấm "Mua ngay" ở "Bó tulip Hà Lan" (520.000đ);
         *   3. quay lại trang giỏ hàng.
         * Giỏ hàng hiện tổng 105.000đ, nhưng bấm "Tiến hành thanh toán"
         * thì trang sau hiện 520.000đ. Hai con số khác nhau cho cùng một
         * nút — và khác về TIỀN, thứ không được phép sai.
         *
         * Nguyên nhân: trang giỏ đọc cartBasket() (chỉ giỏ), còn trang
         * thanh toán đọc basket() — hàm này ưu tiên phiên "mua ngay" nếu
         * đang có. Phiên đó sống trong session và không có gì dọn nó.
         *
         * CÁCH SỬA: coi việc mở trang giỏ hàng là một quyết định rõ ràng
         * — "tôi muốn xem lại giỏ của tôi". Lượt mua ngay bị huỷ tại đây,
         * VÀ NÓI RA cho khách biết. Huỷ im lặng thì khách quay lại trang
         * thanh toán và không hiểu vì sao món mình vừa bấm mua biến mất.
         */
        if ($this->checkout->hasDirect()) {
            $this->checkout->clearDirect();

            return redirect()
                ->route('shop.cart.index')
                ->with('info', 'Đã huỷ lượt "Mua ngay" vì bạn quay lại giỏ hàng. Giỏ hàng của bạn vẫn nguyên vẹn.');
        }

        return view('shop.cart.index', $this->duLieuGio());
    }

    /**
     * Vẽ lại riêng khối giỏ, không kèm khung trang.
     *
     * Chỉ trả JSON — không có đường HTML nào cần tới nó, và một route
     * trả về nửa cái trang khi mở thẳng bằng trình duyệt là thứ gây khó
     * hiểu về sau.
     */
    public function fragment(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'cartCount' => $this->cart->count(),
            'html' => view('shop.cart.partials.noi-dung', $this->duLieuGio())->render(),
        ]);
    }

    /**
     * Dữ liệu để vẽ ruột trang giỏ hàng.
     *
     * DÙNG CHUNG cho hai đường: tải cả trang (index) và vẽ lại riêng khối
     * giỏ sau khi sửa (traLoiGio). Tách ra vì nếu chép làm hai bản thì
     * bản ít người để ý hơn sẽ thiếu một biến — và cái thiếu đó chỉ lộ
     * ra sau khi khách bấm sửa số lượng, tức là đúng lúc không ai đang
     * nhìn màn hình lập trình viên.
     *
     * @return array<string, mixed>
     */
    private function duLieuGio(): array
    {
        $cart = $this->cart->current();
        $basket = $this->checkout->cartBasket();

        return [
            'cart' => $cart,
            // Số tiền do CheckoutBasket tính, dùng chung với trang thanh toán.
            'basket' => $basket,

            /*
             * QUÀ KÈM theo từng dòng — tính lại từ số lượng hiện tại mỗi lần
             * vẽ giỏ (GiftResolver), nên đổi số lượng hay xoá món là quà tự
             * đổi theo. Chỉ món đang được chọn mới có quà: món không mua thì
             * không có quyền nhận quà.
             */
            'quaTheoDong' => app(\App\Services\Gift\GiftResolver::class)->theoDong($basket),

            /*
             * PHỤ KIỆN MUA KÈM cho cả giỏ.
             *
             * Tra MỘT lần cho toàn bộ giỏ chứ không gọi cho từng món: giỏ
             * năm món sẽ thành năm truy vấn và một danh sách đầy trùng
             * lặp. accessoriesForBasket() gom hình thức bán của mọi món
             * rồi tra một lượt, và tự loại những thứ đã có trong giỏ.
             *
             * Đây là chỗ hợp lý nhất để gợi ý mua kèm: khách đã quyết
             * định mua, chưa trả tiền, và đang nhìn đúng danh sách hàng
             * của mình.
             */
            'accessories' => $this->advisor->accessoriesForBasket(
                $cart->items->pluck('product')->filter(),
            ),

            /*
             * Số món đang chọn — hiện ở dòng "Đang chọn n/m món" và
             * quyết định ô "Chọn tất cả" có được tích sẵn không.
             *
             * KHÔNG dùng để tắt nút Thanh toán: bấm nút khi chưa tích gì
             * sẽ bị CheckoutController::requireItems() đẩy về đây kèm
             * câu "Bạn chưa tích món nào để thanh toán". Một nút mờ đi
             * không nói được lý do; một câu trả lời thì có.
             *
             * Đếm trong PHP từ quan hệ đã nạp, không thêm truy vấn nào.
             */
            'selectedCount' => $cart->items->filter(fn ($i) => $i->is_selected !== false)->count(),
        ];
    }

    /**
     * Trả lời một thao tác sửa giỏ — HAI DẠNG, một luồng xử lý.
     *
     *   - Biểu mẫu gửi bình thường -> chuyển hướng back() kèm thông báo
     *   - JavaScript gọi bằng fetch -> JSON kèm HTML của khối giỏ mới
     *
     * VÌ SAO TRẢ HTML CHỨ KHÔNG TRẢ SỐ: đổi một dòng trong giỏ làm đổi
     * thành tiền của dòng, tạm tính, giảm giá, phí giao, tổng cộng, số
     * món đang chọn và trạng thái nút Thanh toán. Trả về từng con số rồi
     * để trình duyệt ráp lại là chép luật tính tiền sang JavaScript —
     * ngay lần sửa cách tính khuyến mại đầu tiên, hai bên sẽ lệch nhau,
     * và bên khách hàng nhìn thấy là bên sai.
     *
     * Máy chủ vẽ, trình duyệt thay chỗ. Con số hiện ra sau khi bấm luôn
     * là con số máy chủ vừa tính, y như khi tải lại cả trang.
     */
    private function traLoiGio(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'cartCount' => $this->cart->count(),
            'html' => view('shop.cart.partials.noi-dung', $this->duLieuGio())->render(),
        ]);
    }

    /**
     * Ghi lại những món khách đã tích để thanh toán.
     *
     * MỘT BIỂU MẪU CHO CẢ GIỎ, không phải mỗi dòng một request.
     *
     * Ô đánh dấu chỉ gửi lên những cái ĐƯỢC TÍCH, nên id vắng mặt nghĩa
     * là vừa bị bỏ tích. Xử lý cả hai chiều trong một lượt là cách duy
     * nhất đúng — nhận từng dòng một thì "bỏ tích" không sinh request nào
     * và hệ thống không bao giờ biết.
     */
    public function select(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        /*
         * KHÔNG kiểm tra id có thuộc giỏ này không ở đây — CartService
         * ::setSelection() luôn giới hạn câu UPDATE trong giỏ của chính
         * người đang thao tác, nên id lạ gửi lên cũng không chạm được
         * giỏ khác. Kiểm hai lần chỉ tạo cảm giác an toàn giả nếu một
         * trong hai chỗ bị bỏ quên sau này.
         */
        $this->cart->setSelection($validated['selected'] ?? []);

        /*
         * KHÔNG kèm thông báo cho đường tải lại trang: ô đánh dấu tự nó
         * đã cho thấy kết quả, thêm một dòng "Đã cập nhật lựa chọn" chỉ
         * là tiếng ồn. Đường JSON vẫn cần một chuỗi vì hàm dùng chung
         * nhận nó, nhưng cart-live.js cố ý không hiện toast cho thao tác
         * này — xem chú thích ở đó.
         */
        if (! $request->expectsJson()) {
            return back();
        }

        return $this->traLoiGio($request, 'Đã cập nhật lựa chọn.');
    }

    /**
     * Thêm vào giỏ.
     *
     * TRẢ VỀ HAI DẠNG, cùng một luồng xử lý:
     *
     *   - Trình duyệt gửi biểu mẫu bình thường  -> chuyển hướng back()
     *   - JavaScript gọi bằng fetch             -> JSON
     *
     * VÌ SAO KHÔNG TÁCH THÀNH HAI ENDPOINT: mọi phép kiểm tra (còn hàng,
     * có bán trực tiếp không, số lượng hợp lệ) chỉ được có MỘT bản. Thêm
     * một endpoint /api/gio-hang riêng là mở đường cho hai bản luật, và
     * bản ít người dùng hơn sẽ là bản bị quên khi sửa.
     *
     * Nhánh JSON chỉ khác ở chỗ ĐÓNG GÓI câu trả lời, không khác ở luật.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validatePayload($request);

        try {
            $this->cart->add(
                $data['product'],
                $data['quantity'],
                $data['variant'],
            );
        } catch (CartException $e) {
            return $request->expectsJson()
                // 422 chứ không phải 200: đây là "yêu cầu hợp lệ về cú
                // pháp nhưng không thực hiện được". Trả 200 kèm ok=false
                // thì mọi công cụ theo dõi đều thấy một request thành
                // công, và lỗi thật biến mất khỏi biểu đồ.
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        $this->logCartEvent(UserEventType::AddToCart, $request, $data);

        /*
         * NÓI RA KHI SỐ LƯỢNG BỊ CẮT BỚT.
         *
         * Khách bấm thêm 5 chậu khi kho còn 1 thì giỏ nhận 1. Cắt bớt là
         * ĐÚNG — không bán thứ không có. Nhưng báo "Đã thêm vào giỏ hàng"
         * y hệt lúc thêm đủ thì họ chỉ phát hiện ở bước thanh toán, và
         * lúc đó không biết mình gõ sai hay hệ thống làm sai.
         */
        $clamped = $this->cart->lastClampedTo();

        $message = $clamped === null
            ? 'Đã thêm vào giỏ hàng.'
            : sprintf('Chỉ còn %d sản phẩm nên giỏ hàng nhận %d.', $clamped, $clamped);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,

                /*
                 * TRẢ LUÔN SỐ MÓN TRONG GIỎ.
                 *
                 * Không trả thì JavaScript phải tự cộng thêm ở phía
                 * trình duyệt — mà con số đó sai ngay khi khách mở hai
                 * tab, hoặc khi CartService gộp dòng trùng thay vì thêm
                 * dòng mới. Máy chủ đếm là con số duy nhất đúng.
                 */
                'cartCount' => $this->cart->count(),

                // true khi số lượng bị cắt theo tồn kho — giao diện đổi
                // màu thông báo cho đúng, xem add-to-cart.js.
                'clamped' => $clamped !== null,
            ]);
        }

        /*
         * Bị cắt bớt thì báo bằng `info`, không phải `success`.
         *
         * Không phải lỗi (hàng vẫn vào giỏ), cũng không phải thành công
         * trọn vẹn (khách không nhận được thứ họ bấm). `info` là màu
         * trung tính đúng cho việc hệ thống tự điều chỉnh — xem chú thích
         * ở layouts/app.blade.php.
         */
        return back()->with($clamped === null ? 'success' : 'info', $message);
    }

    /**
     * "Mua ngay": đi thẳng từ trang sản phẩm tới thanh toán.
     *
     * KHÔNG thêm vào giỏ hàng. Trước đây hàm này gọi CartService::add()
     * rồi chuyển hướng, nên khách đang có 3 món trong giỏ mà bấm "Mua
     * ngay" ở món thứ 4 sẽ thanh toán cả 4 — sai hẳn ý nghĩa của nút.
     *
     * Món mua ngay được giữ riêng trong session; giỏ hàng giữ nguyên để
     * khách quay lại mua tiếp.
     */
    public function buyNow(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            // Vẫn kiểm tra như khi thêm vào giỏ: còn bán, đúng biến thể,
            // còn hàng. Mua ngay không được phép lỏng hơn.
            $this->cart->assertPurchasable($data['product'], $data['variant']);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->checkout->setDirect(
            $data['product'],
            $data['quantity'],
            $data['variant'],
        );

        $this->logCartEvent(UserEventType::BuyNow, $request, $data);

        return redirect()->route('shop.checkout.details');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:' . CartService::MAX_QUANTITY],
        ], [], ['quantity' => 'số lượng']);

        $this->cart->updateQuantity($cartItem, (int) $validated['quantity']);

        return $this->traLoiGio($request, 'Đã cập nhật giỏ hàng.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($cartItem);

        $this->cart->remove($cartItem);

        return $this->traLoiGio($request, 'Đã xoá sản phẩm khỏi giỏ hàng.');
    }

    /**
     * Xoá sạch giỏ hàng.
     *
     * DÙNG clear() CHỨ KHÔNG PHẢI clearSelected(): khách bấm "Xoá tất
     * cả" là muốn giỏ trống, kể cả những món họ đang bỏ tích. Xoá mỗi
     * phần đã tích rồi báo "đã xoá tất cả" là nói sai việc vừa làm.
     *
     * KHÔNG có bước hoàn tác. Vì thế nút phải hỏi lại trước khi gửi —
     * xem `data-confirm` ở trang giỏ hàng.
     */
    public function clear(Request $request): RedirectResponse|JsonResponse
    {
        $soMon = $this->cart->current()->items()->count();

        if ($soMon === 0) {
            return $this->traLoiGio($request, 'Giỏ hàng đang trống.');
        }

        $this->cart->clear();

        return $this->traLoiGio($request, 'Đã xoá tất cả sản phẩm khỏi giỏ hàng.');
    }

    /**
     * Chặn việc sửa/xoá dòng trong giỏ của NGƯỜI KHÁC.
     *
     * Route model binding chỉ lấy CartItem theo id, không kiểm tra chủ
     * sở hữu — thiếu bước này thì đổi id trên URL là xoá được giỏ hàng
     * của người lạ.
     */
    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->cart->current()->id, 403);
    }

    /**
     * Ghi nhận hành vi thêm vào giỏ.
     *
     * Guide mục 9 muốn phân tích "sản phẩm được thêm vào giỏ nhiều",
     * mục 25 liệt kê add_to_cart là một event cần theo dõi. Ghi SAU khi
     * CartService::add() thành công, không ghi lúc bấm nút — thêm thất
     * bại (hết hàng, sai biến thể) không được tính là một lần thêm giỏ.
     *
     * @param  array{product: Product, variant: ?ProductVariant, quantity: int}  $data
     */
    /**
     * Ghi lại một sự kiện giỏ hàng.
     *
     * NHẬN LOẠI SỰ KIỆN QUA THAM SỐ, không hằng định AddToCart bên
     * trong: "Mua ngay" đi qua đúng hàm này nhưng KHÔNG phải là thêm
     * vào giỏ, và ghi sai loại làm hỏng phễu chuyển đổi.
     */
    private function logCartEvent(UserEventType $type, Request $request, array $data): void
    {
        UserEvent::log($type, $request, [
            'product_id' => $data['product']->id,
            'category_id' => $data['product']->category_id,
            'meta' => [
                'quantity' => $data['quantity'],
                'variant_id' => $data['variant']?->id,
            ],
        ]);
    }

    /**
     * @return array{product: Product, variant: ?ProductVariant, quantity: int}
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . CartService::MAX_QUANTITY],
        ], [], [
            'product_id' => 'sản phẩm',
            'variant_id' => 'phiên bản',
            'quantity' => 'số lượng',
        ]);

        return [
            'product' => Product::findOrFail($validated['product_id']),
            'variant' => isset($validated['variant_id'])
                ? ProductVariant::find($validated['variant_id'])
                : null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
        ];
    }
}
