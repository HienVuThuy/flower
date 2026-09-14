<?php

use App\Http\Controllers\Admin\BulkInquiryController as AdminBulkInquiryController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExchangeController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnalyticsPagesController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\StockCountController;
use App\Http\Controllers\Admin\StockReceiptController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\CommunityModerationController;
use App\Http\Controllers\Admin\PricingAdvisorController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\SupplierReturnController;
use App\Http\Controllers\Admin\FlowerKindController;
use App\Http\Controllers\Admin\FlowerLotController;
use App\Http\Controllers\Admin\OpeningStockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\SearchSuggestionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Shop\AddressController;
use App\Http\Controllers\Shop\AdvisorController;
use App\Http\Controllers\Shop\PlantTaxonController;
use App\Http\Controllers\Shop\BulkInquiryController;
use App\Http\Controllers\Shop\CareController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\BlogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CommunityController;
use App\Http\Controllers\Shop\MomoController;
use App\Http\Controllers\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\CreditsController;
use App\Http\Controllers\Shop\EventController;
use App\Http\Controllers\Shop\IntentController;
use App\Http\Controllers\Shop\JournalController;
use App\Http\Controllers\Shop\OrderLookupController;
use App\Http\Controllers\Shop\DisplaySchemeController;
use App\Http\Controllers\Shop\GHNController;
use App\Http\Controllers\Shop\ProfileController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\SupplyController;
use App\Http\Controllers\Shop\VoucherController;
use App\Http\Controllers\Shop\WishlistController;
use App\Http\Controllers\Shop\CategoryController as ShopCategoryController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use Illuminate\Support\Facades\Route;


/*
 * =========================
 * TRANG CHỦ
 * =========================
 */

Route::get('/', [HomeController::class, 'index'])->name('welcome');


/*
 * =========================
 * CỬA HÀNG CÔNG KHAI (guest + customer)
 * =========================
 */

Route::prefix('danh-muc')
    ->name('shop.categories.')
    ->group(function () {
        Route::get('/', [ShopCategoryController::class, 'index'])->name('index');
        Route::get('/{category:slug}', [ShopCategoryController::class, 'show'])->name('show');
    });

Route::prefix('san-pham')
    ->name('shop.products.')
    ->group(function () {
        Route::get('/', [ShopProductController::class, 'index'])->name('index');
        Route::get('/{product:slug}', [ShopProductController::class, 'show'])->name('show');
    });

/*
 * =========================
 * PHỤ KIỆN & VẬT TƯ CHĂM SÓC
 * =========================
 * Tách hẳn khỏi /san-pham: đây là cửa hàng hoa và cây cảnh, vật tư là
 * hàng phụ trợ. Xem App\Enums\CategoryKind và QĐ-31.
 */
Route::get('phu-kien', [SupplyController::class, 'index'])->name('shop.supplies.index');


/*
 * =========================
 * CHỌN THEO NHU CẦU
 * =========================
 * Bốn thẻ ở trang chủ trước đây chỉ là liên kết tới /san-pham đã lọc.
 * Nay mỗi nhu cầu là một trang hướng dẫn thật: dẫn nhập, các bước, hàng
 * gợi ý theo đúng tiêu chí của nhu cầu đó, và dụng cụ nên mua kèm.
 *
 * Tham số là slug của App\Enums\ShoppingIntent; giá trị lạ trả 404.
 */
Route::get('nhu-cau/{intent}', [IntentController::class, 'show'])
    ->name('shop.intents.show');


/*
 * =========================
 * TRANG SỰ KIỆN
 * =========================
 * Landing page riêng cho một chương trình khuyến mại: bối cảnh + hàng
 * trong chương trình + mã giảm giá của chính sự kiện đó.
 *
 * KHÔNG chặn chương trình đã kết thúc — đường dẫn được chia sẻ trên mạng
 * xã hội, chết link là mất khách. Trang tự nói rõ đã hết hạn. Chỉ chương
 * trình còn NHÁP mới trả 404 (xem EventController).
 */
Route::get('su-kien/{promotion:slug}', [EventController::class, 'show'])
    ->name('shop.events.show');

Route::prefix('dat-so-luong-lon')
    ->name('shop.bulk-inquiry.')
    ->group(function () {
        Route::get('/', [BulkInquiryController::class, 'create'])->name('create');

        Route::post('/', [BulkInquiryController::class, 'store'])
            ->middleware('throttle:5,10')
            ->name('store');
    });


/*
 * =========================
 * GIỎ HÀNG & THANH TOÁN
 * =========================
 * Cả nhóm chỉ tồn tại khi cờ features.cart bật. Tắt cờ thì route biến
 * mất hoàn toàn — không có chuyện giao diện tắt nút nhưng endpoint vẫn
 * gọi được bằng tay.
 */

if (config('features.cart')) {

    Route::prefix('gio-hang')
        ->name('shop.cart.')
        ->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/', [CartController::class, 'store'])->name('store');
            /*
             * XOÁ SẠCH GIỎ — PHẢI ĐĂNG KÝ TRƯỚC '/{cartItem}'.
             *
             * Laravel khớp route theo THỨ TỰ ĐĂNG KÝ. Đặt sau thì
             * DELETE '/gio-hang/tat-ca' rơi vào '/{cartItem}', route
             * model binding đi tìm một dòng giỏ mang khoá "tat-ca",
             * không thấy, và trả 404. Đo được: cả ba bài xoá giỏ đều
             * nhận 404 cho tới khi chuyển lên đây.
             *
             * Cùng cái bẫy với '/don-hang/tra-cuu' — xem chú thích ở
             * nhóm route đơn hàng.
             *
             * throttle: một cú bấm liên tục không được biến thành hàng
             * chục lượt xoá — dù xoá giỏ rỗng là vô hại, nó vẫn là
             * request thật.
             */
            Route::delete('/tat-ca', [CartController::class, 'clear'])
                ->middleware('throttle:20,1')
                ->name('clear');

            Route::patch('/{cartItem}', [CartController::class, 'update'])->name('update');
            Route::delete('/{cartItem}', [CartController::class, 'destroy'])->name('destroy');

            /*
             * Chọn món để thanh toán.
             *
             * POST cho CẢ GIỎ trong một lượt: ô đánh dấu chỉ gửi lên
             * những cái được tích, nên "bỏ tích" không sinh request riêng
             * nào. Nhận từng dòng một thì hệ thống không bao giờ biết
             * khách vừa bỏ tích cái gì.
             */
            Route::post('/chon', [CartController::class, 'select'])->name('select');

            /*
             * VẼ LẠI RIÊNG KHỐI GIỎ — chỉ dùng cho JavaScript.
             *
             * VÌ SAO CẦN: trên chính trang giỏ hàng có khối "Có thể bạn
             * cần thêm". Bấm thêm một phụ kiện ở đó đi qua route store()
             * và không tải lại trang, nên trước đây danh sách hàng cùng
             * phần tiền bên cạnh vẫn hiện trạng thái CŨ — món vừa thêm
             * không xuất hiện, tổng tiền không đổi. Khách bấm thêm lần
             * nữa vì tưởng hụt.
             *
             * GET vì nó không đổi gì cả, chỉ đọc và vẽ. Dùng lại select()
             * để làm việc này thì sai nghĩa: đó là route GHI lựa chọn.
             */
            Route::get('/khoi', [CartController::class, 'fragment'])->name('fragment');
        });

    Route::post('mua-ngay', [CartController::class, 'buyNow'])->name('shop.cart.buy-now');

    Route::prefix('thanh-toan')
        ->name('shop.checkout.')
        ->group(function () {
            /*
             * BƯỚC 1 — thông tin người nhận + giao hàng + thanh toán.
             *
             * Gộp từ hai bước cũ. Lý do: phí giao phụ thuộc tỉnh (nhập ở
             * bước 1), nên tổng tiền chỉ đúng từ bước 2 trở đi — khách
             * điền xong bước 1 vẫn chưa biết phải trả bao nhiêu.
             */
            Route::get('/', [CheckoutController::class, 'details'])->name('details');
            Route::post('/', [CheckoutController::class, 'storeDetails'])->name('store-details');

            /*
             * Đường dẫn cũ của bước 2 — chuyển hướng 301 về bước 1.
             * Khách có thể còn tab đang mở hoặc đã lưu dấu trang; trả 404
             * là làm mất một người đang giữa đường mua hàng.
             */
            Route::get('van-chuyen', [CheckoutController::class, 'shipping'])->name('shipping');

            // Mã giảm giá — nằm TRONG bước 1, không phải bước riêng
            Route::post('ma-giam-gia', [CheckoutController::class, 'applyCoupon'])->name('apply-coupon');
            Route::delete('ma-giam-gia', [CheckoutController::class, 'removeCoupon'])->name('remove-coupon');
            Route::post('ma-giam-gia/tu-chon', [CheckoutController::class, 'autoCoupon'])->name('auto-coupon');

            // Bước 2 — xác nhận
            Route::get('xac-nhan', [CheckoutController::class, 'confirm'])->name('confirm');
            Route::post('dat-hang', [CheckoutController::class, 'place'])->name('place');
        });

    /*
     * HỒ SƠ TÀI KHOẢN (Guide §11 — "User → Hồ sơ").
     *
     * throttle trên bước đổi mật khẩu: biểu mẫu này nhận mật khẩu hiện
     * tại, nên nó cũng là chỗ dò mật khẩu của người đang mượn máy. Năm
     * lần mỗi phút là đủ cho người gõ nhầm, không đủ cho máy dò.
     */
    /*
     * CHẾ ĐỘ SÁNG / TỐI — tuỳ chọn của người xem, không cần đăng nhập.
     *
     * Đặt NGOÀI nhóm 'auth': phần lớn người xem trang bán hàng là khách
     * vãng lai, và bắt đăng nhập mới đổi được nền là biến một tuỳ chọn
     * dễ chịu thành một rào cản.
     */
    /*
     * =========================================================
     * ĐỊA GIỚI VÀ CƯỚC GIAO HÀNG (GHN)
     * =========================================================
     * Ba route đầu chỉ ĐỌC danh mục tỉnh/quận/phường của GHN nên để GET
     * — chúng không đổi gì, và trình duyệt được phép lưu đệm.
     *
     * Route tính cước là POST dù nghe như một phép đọc: nó phải đọc GIỎ
     * HÀNG trong phiên để biết khối lượng, tức là phụ thuộc trạng thái
     * người dùng. Để GET thì phần tải trước của trình duyệt và mọi trình
     * quét đều gọi được, mỗi lần là một request thật sang GHN.
     *
     * KHÔNG đòi đăng nhập: khách vãng lai cũng đặt hàng được ở cửa hàng
     * này, nên họ cũng phải xem được cước.
     *
     * Throttle vì mỗi lần gọi là một lần đi ra ngoài, và hạn mức GHN là
     * của cửa hàng. 60 lần/phút đủ rộng cho người đang chọn địa chỉ.
     */
    Route::prefix('dia-gioi')
        ->name('locations.')
        ->middleware('throttle:60,1')
        ->group(function () {
            Route::get('/tinh-thanh', [GHNController::class, 'getProvinces'])
                ->name('provinces');

            Route::get('/quan-huyen/{provinceId}', [GHNController::class, 'getDistricts'])
                ->whereNumber('provinceId')
                ->name('districts');

            Route::get('/phuong-xa/{districtId}', [GHNController::class, 'getWards'])
                ->whereNumber('districtId')
                ->name('wards');

            Route::post('/tinh-cuoc', [GHNController::class, 'getShippingFee'])
                ->name('fee');
        });

    Route::post('che-do-hien-thi', [DisplaySchemeController::class, 'update'])
        ->name('shop.display-scheme');

    /*
     * ============================================================
     * NHỮNG TRANG ĐÒI EMAIL ĐÃ XÁC THỰC (middleware `verified`)
     * ============================================================
     * CHỌN THEO NGUYÊN TẮC: khoá những trang GẮN VỚI DANH TÍNH — hồ sơ,
     * sổ địa chỉ, lịch sử đơn, ví voucher, danh sách yêu thích, lịch chăm
     * cây. Một địa chỉ email chưa ai chứng minh là có thật thì không nên
     * được dùng để nhận mã giảm giá hay xem lại đơn đã mua.
     *
     * CỐ Ý KHÔNG KHOÁ GIỎ HÀNG VÀ THANH TOÁN, dù bài thực hành gợi ý.
     * Cửa hàng này CHO PHÉP khách vãng lai đặt hàng. Khoá giỏ với người
     * đã đăng ký nhưng chưa xác thực, trong khi người không có tài khoản
     * mua thoải mái, là phạt đúng nhóm khách thân thiết hơn — và làm mất
     * một đơn hàng có thật để đổi lấy một quy tắc không bảo vệ được gì.
     *
     * Tài khoản tạo TRƯỚC khi có chức năng này đã được đánh dấu đã xác
     * thực trong migration create_email_verification_codes_table — nếu
     * không, bật middleware này là khoá toàn bộ khách hiện có ra ngoài.
     */
    /*
     * ĐÁNH GIÁ CỦA TÔI — thuộc khu tài khoản, nên cũng đòi email đã xác
     * thực như hồ sơ và lịch sử đơn.
     *
     * Chỉ có index: nút gỡ trỏ về route shop.reviews.destroy sẵn có.
     * Viết một đường xoá thứ hai là hai bộ phép kiểm quyền phải giữ
     * đồng bộ.
     */
    Route::get('danh-gia-cua-toi', [ReviewController::class, 'mine'])
        ->middleware(['auth', 'verified'])
        ->name('shop.reviews.mine');

    Route::prefix('tai-khoan')
        ->name('shop.profile.')
        ->middleware(['auth', 'verified'])
        ->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/', [ProfileController::class, 'update'])->name('update');

            Route::put('/mat-khau', [ProfileController::class, 'updatePassword'])
                ->middleware('throttle:5,1')
                ->name('password');

            /*
             * Gửi liên kết đặt lại mật khẩu cho CHÍNH mình, dùng khi
             * đang đăng nhập mà không còn nhớ mật khẩu cũ — xem
             * ProfileController::sendPasswordResetLink().
             *
             * Trang "Quên mật khẩu" nằm sau middleware `guest` nên
             * người đang đăng nhập không vào được; không có đường này
             * thì họ phải đăng xuất thật rồi mới đặt lại được, mà không
             * ai đoán ra bước đó.
             *
             * Throttle chặt: mỗi lần bấm là một lá thư thật gửi đi.
             */
            Route::post('/gui-lien-ket-mat-khau', [ProfileController::class, 'sendPasswordResetLink'])
                ->middleware('throttle:3,1')
                ->name('password-link');

            Route::put('/thong-bao', [ProfileController::class, 'updateNotifications'])
                ->name('notifications');

            /*
             * ============================================================
             * XOÁ TÀI KHOẢN — ba bước, cố ý tách rời
             * ============================================================
             *
             *   1. POST  .../xoa/yeu-cau   → gửi thư, KHÔNG xoá gì
             *   2. GET   .../xoa/xac-nhan  → mở trang xác nhận, KHÔNG xoá gì
             *   3. DELETE .../xoa          → xoá thật
             *
             * Gộp bất kỳ hai bước nào cũng mất một lớp bảo vệ: gộp 1-2
             * thì bấm một nút là mất tài khoản; gộp 2-3 thì phần xem
             * trước liên kết của Gmail cũng xoá được.
             *
             * Bước 2 và 3 mang middleware `signed` — chữ ký nằm trong
             * liên kết gửi qua email, nên chỉ người ĐỌC ĐƯỢC HỘP THƯ mới
             * tới được. Đó chính là phần "xác minh email".
             *
             * Throttle ở bước 1: mỗi lần bấm là một lá thư thật.
             */
            Route::post('/xoa/yeu-cau', [ProfileController::class, 'requestDeletion'])
                ->middleware('throttle:3,10')
                ->name('delete.request');

            Route::get('/xoa/xac-nhan/{user}', [ProfileController::class, 'confirmDeletion'])
                ->middleware('signed')
                ->name('delete.confirm');

            Route::delete('/xoa/{user}', [ProfileController::class, 'destroyAccount'])
                ->middleware('signed')
                ->name('delete');

            /*
             * Đá một thiết bị khỏi tài khoản. DELETE chứ không phải GET:
             * đây là thao tác thay đổi trạng thái, mà GET thì trình duyệt
             * và trình quét được phép tự gọi lại bất cứ lúc nào.
             */
            Route::delete('/phien-dang-nhap', [ProfileController::class, 'revokeSession'])
                ->name('sessions.revoke');
        });

    /*
     * LỊCH CHĂM CÂY — dịch vụ sau bán.
     *
     * Nằm TRONG khối features.cart vì lịch chỉ sinh ra từ đơn hàng đã
     * giao; tắt module đặt hàng thì không ai có lịch nào.
     *
     * PATCH cho bật/tắt, POST cho "vừa làm xong": cả hai đều đổi trạng
     * thái nên không được là GET — trình duyệt và trình quét được phép tự
     * gọi lại GET bất cứ lúc nào.
     */
    Route::prefix('lich-cham-cay')
        ->name('shop.care.')
        ->middleware(['auth', 'verified'])
        ->group(function () {
            Route::get('/', [CareController::class, 'index'])->name('index');
            Route::patch('/{reminder}', [CareController::class, 'toggle'])->name('toggle');
            Route::post('/{reminder}/xong', [CareController::class, 'done'])->name('done');
        });

    /*
     * SỔ ĐỊA CHỈ — chỉ dành cho tài khoản đã đăng nhập.
     * Khách vãng lai vẫn đặt hàng được, chỉ là phải nhập lại mỗi lần.
     */
    Route::prefix('dia-chi')
        ->name('shop.addresses.')
        ->middleware(['auth', 'verified'])
        ->group(function () {
            Route::get('/', [AddressController::class, 'index'])->name('index');
            Route::get('/them', [AddressController::class, 'create'])->name('create');
            Route::post('/', [AddressController::class, 'store'])->name('store');
            Route::get('/{address}/sua', [AddressController::class, 'edit'])->name('edit');
            Route::put('/{address}', [AddressController::class, 'update'])->name('update');
            Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
            Route::patch('/{address}/mac-dinh', [AddressController::class, 'makeDefault'])->name('make-default');
        });

    /*
     * ĐÁNH GIÁ SẢN PHẨM — bắt buộc đăng nhập.
     *
     * Khách vãng lai đặt hàng được nhưng không đánh giá được: không có tài
     * khoản thì không có cách nào để họ sửa hay gỡ bài của chính mình.
     *
     * Nằm TRONG khối features.cart là có chủ đích: quyền đánh giá dựa trên
     * đơn hàng đã giao, tắt module đặt hàng thì không ai đủ điều kiện.
     */
    Route::middleware('auth')->group(function () {

        Route::post('san-pham/{product:slug}/danh-gia', [ReviewController::class, 'store'])
            ->name('shop.reviews.store');

        Route::delete('danh-gia/{review}', [ReviewController::class, 'destroy'])
            ->name('shop.reviews.destroy');

    });

    Route::prefix('don-hang')
        ->name('shop.orders.')
        ->group(function () {
            Route::get('/', [ShopOrderController::class, 'index'])
                ->middleware(['auth', 'verified'])
                ->name('index');

            /*
             * TRA CỨU ĐƠN CHO KHÁCH VÃNG LAI.
             *
             * Đặt TRƯỚC '/{order}', nếu không 'tra-cuu' sẽ bị nuốt làm
             * tham số {order} và không bao giờ tới được controller này.
             *
             * throttle:5,1 — tối đa 5 lần thử mỗi phút cho một IP. Mã đơn
             * chỉ có 4 ký tự ngẫu nhiên, không chặn thì dò được bằng máy.
             */
            Route::get('/tra-cuu', [OrderLookupController::class, 'form'])->name('lookup');

            Route::post('/tra-cuu', [OrderLookupController::class, 'find'])
                ->middleware('throttle:5,1')
                ->name('lookup.find');

            Route::get('/{order}', [ShopOrderController::class, 'show'])->name('show');

            /*
             * Khách tự huỷ đơn. POST chứ không GET: huỷ đơn là hành động
             * làm thay đổi dữ liệu và không quay lại được, không được để
             * xảy ra chỉ vì ai đó mở một đường dẫn.
             */
            Route::post('/{order}/huy', [ShopOrderController::class, 'cancel'])
                ->middleware('throttle:10,1')
                ->name('cancel');

            /*
             * THANH TOÁN LẠI cho đơn MoMo trả hụt.
             *
             * KHÔNG tạo đơn mới — vẫn là đơn cũ, chỉ thêm một lượt giao
             * dịch. throttle để một cú bấm liên tục không đẻ ra hàng
             * chục bản ghi giao dịch rỗng.
             */
            Route::get('/{order}/thanh-toan-momo', [MomoController::class, 'payAgain'])
                ->middleware('throttle:10,1')
                ->name('momo.pay');
        });

    /*
     *--------------------------------------------------------------------
     * ĐƯỜNG VỀ CỦA CỔNG THANH TOÁN
     *--------------------------------------------------------------------
     * KHÔNG có middleware 'auth': MoMo gọi vào đây, không phải khách.
     * Cũng vì thế IPN được miễn kiểm CSRF trong bootstrap/app.php.
     *
     * Thứ thay thế cho việc đăng nhập là CHỮ KÝ trong gói tin — xem
     * MomoGateway::verifySignature(). Không có chữ ký đúng thì gói tin
     * bị vứt, bất kể ai gửi.
     */
    Route::prefix('thanh-toan/momo')
        ->name('shop.payment.momo.')
        ->group(function () {
            Route::get('/ket-qua', [MomoController::class, 'callback'])->name('callback');
            Route::post('/ipn', [MomoController::class, 'ipn'])->name('ipn');
        });

    // Bắt đầu trả tiền ngay sau khi đặt đơn MoMo.
    Route::get('thanh-toan/momo/{order}', [MomoController::class, 'start'])
        ->name('shop.payment.momo.start');
}


/*
 * GHI CÔNG TÁC GIẢ ẢNH.
 *
 * Công khai, không cần đăng nhập: giấy phép CC BY buộc ghi công ở nơi
 * người xem thấy được, mà phần lớn người xem là khách vãng lai.
 */
Route::get('nguon-anh', [CreditsController::class, 'index'])->name('shop.credits');


/*
 * =========================
 * VOUCHER
 * =========================
 * XEM được khi chưa đăng nhập, LƯU thì phải đăng nhập.
 *
 * Voucher là một trong những lý do chính khiến người ta chịu tạo tài
 * khoản; giấu cả trang sau màn đăng nhập là bỏ mất đúng tác dụng đó.
 * Nhưng ví voucher gắn với một tài khoản cụ thể nên hai thao tác ghi
 * bắt buộc phải có `auth`.
 */
/*
 * =========================
 * CẨM NANG (blog) — công khai
 * =========================
 * Đường dẫn tiếng Việt không dấu như mọi trang khác. `cam-nang` chứ
 * không `blog`: đó là chữ khách đọc trên menu, và URL nên nói cùng một
 * thứ với menu.
 */
Route::get('cam-nang', [BlogController::class, 'index'])->name('shop.blog.index');
Route::get('cam-nang/{post}', [BlogController::class, 'show'])->name('shop.blog.show');

/*
 * =========================
 * GÓC CÂY CỦA BẠN — xem công khai, đăng phải đăng nhập
 * =========================
 * Xem thì mở cho mọi người: cả điểm của mục này là khách chưa mua nhìn
 * thấy cây người khác đã mua. Bắt đăng nhập để XEM là đóng đúng cánh cửa
 * mình vừa mở.
 */
Route::get('goc-cay', [CommunityController::class, 'index'])->name('shop.community.index');

Route::get('voucher', [VoucherController::class, 'index'])->name('shop.vouchers.index');

/*
 * Điều kiện đầy đủ của một mã — CÔNG KHAI.
 *
 * Khách phải đọc được điều kiện TRƯỚC khi quyết định lưu; bắt đăng nhập
 * để xem điều kiện là đặt rào ngay chỗ họ đang cân nhắc. Mã nội bộ trả
 * 404 (xem VoucherController::show).
 *
 * Đặt TRƯỚC nhóm `auth` bên dưới để không dính middleware của nhóm đó.
 */
Route::get('voucher/{coupon}', [VoucherController::class, 'show'])
    ->name('shop.vouchers.show');


/*
 * =========================
 * TƯ VẤN CHỌN CÂY
 * =========================
 * Sắp xếp hàng theo cách KHÁCH nghĩ về vấn đề của họ ("tôi có ban công
 * đầy nắng"), khác với trang sản phẩm vốn sắp theo cách CỬA HÀNG nghĩ về
 * hàng hoá (danh mục, hình thức bán, giá).
 *
 * Chỉ đọc, công khai, không tham số nào bắt buộc.
 */
Route::get('chon-cay', [AdvisorController::class, 'index'])->name('shop.advisor.index');

/*
 * DUYỆT THEO PHÂN LOẠI SINH HỌC — Giới → Ngành → Lớp → Bộ → Họ → Chi → Loài.
 *
 * TÁCH KHỎI /danh-muc, và đó là chủ ý: danh mục là cách CỬA HÀNG bày
 * hàng theo dịp mua ("Hoa cưới", "Cây để bàn"), còn đây là cách THIÊN
 * NHIÊN xếp. Cùng một cây nằm ở cả hai chỗ, ở hai vị trí không liên
 * quan gì nhau — ép thành một cây là buộc phải bỏ một trong hai cách
 * tìm.
 *
 * Đặt route con TRƯỚC? Không cần: `loai-cay` và `loai-cay/{slug}` không
 * chồng lấn nhau.
 */
Route::get('loai-cay', [PlantTaxonController::class, 'index'])->name('shop.taxa.index');
Route::get('loai-cay/{taxon}', [PlantTaxonController::class, 'show'])->name('shop.taxa.show');


/*
 * =========================
 * TRANG NỘI DUNG TĨNH
 * =========================
 * MỘT route cho cả năm trang. Slug phải nằm trong danh sách đóng khai ở
 * PageController — ghép thẳng slug vào tên view là mở đường cho
 * `../../` đi tới bất kỳ tệp Blade nào trong dự án.
 */
Route::get('trang/{slug}', [PageController::class, 'show'])
    ->name('shop.pages.show');

/*
 * LƯU VOUCHER VỀ VÍ — đòi email đã xác thực.
 *
 * Ví voucher gắn với DANH TÍNH: mỗi mã có giới hạn số lần dùng cho mỗi
 * tài khoản. Cho tài khoản chưa xác thực lưu mã nghĩa là ai cũng tạo
 * được vô số tài khoản bằng email không có thật để vét sạch mã.
 *
 * Trang XEM voucher vẫn công khai — xem thì không lấy mất của ai cái gì.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    // Đăng bài và xoá bài của chính mình — cần đăng nhập.
    Route::post('goc-cay', [CommunityController::class, 'store'])
        ->name('shop.community.store');

    Route::delete('goc-cay/{post}', [CommunityController::class, 'destroy'])
        ->name('shop.community.destroy');

    Route::post('voucher/{coupon}/luu', [VoucherController::class, 'claim'])
        ->name('shop.vouchers.claim');

    Route::patch('voucher/{coupon}/luu', [VoucherController::class, 'unhide'])
        ->name('shop.vouchers.unhide');

    Route::delete('voucher/{coupon}/luu', [VoucherController::class, 'discard'])
        ->name('shop.vouchers.discard');

    // Đổi điểm thưởng lấy voucher — cùng lý do cần email đã xác thực như lưu voucher.
    Route::post('diem-thuong/doi', [\App\Http\Controllers\Shop\PointController::class, 'redeem'])
        ->middleware('throttle:10,1')
        ->name('shop.points.redeem');
});


/*
 * =========================
 * ENDPOINT JSON
 * =========================
 * Chỗ duy nhất trong dự án trả JSON thay vì HTML. Đặt tiền tố /api/ để
 * đọc `route:list` là phân biệt được ngay.
 *
 * THROTTLE LÀ BẮT BUỘC, không phải cho đẹp: endpoint này công khai,
 * không cần đăng nhập, và mỗi lần gọi là một lần quét bảng sản phẩm cộng
 * một lượt duyệt từ điển. Ô gợi ý gọi theo từng phím gõ, nên chỉ cần một
 * script viết vụng (hoặc cố ý) là đủ làm nghẽn. 60 lượt/phút thoải mái
 * cho người gõ thật — JavaScript đã gộp phím chờ 220ms trước khi gọi.
 */
Route::prefix('api')
    ->name('api.')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::get('goi-y-tim-kiem', SearchSuggestionController::class)
            ->name('search.suggest');
    });


/*
 * =========================
 * YÊU THÍCH
 * =========================
 * Nằm NGOÀI khối features.cart: lưu sản phẩm để xem sau không liên quan
 * gì tới việc có bán hàng hay không. Tắt module giỏ hàng thì trang này
 * vẫn có ý nghĩa.
 */
Route::middleware('auth')->group(function () {

    /*
 * NHẬT KÝ CÁ NHÂN — riêng tư tuyệt đối.
 * ============================================================
 * Mọi route ở đây nằm sau `auth`: không có khách vãng lai nào chạm tới
 * được. Bên trong controller, mỗi truy vấn còn lọc theo user_id ngay lúc
 * lấy dữ liệu chứ không kiểm sau — xem chú thích ở JournalController.
 *
 * Đường dẫn dùng ID chứ không dùng slug: sổ là của riêng một người, đặt
 * tên trùng nhau là chuyện bình thường và không có lý do gì để tên sổ
 * xuất hiện trên thanh địa chỉ.
 */
Route::prefix('nhat-ky')
    ->name('shop.journals.')
    ->group(function () {
        Route::get('/', [JournalController::class, 'index'])->name('index');
        Route::get('tao-moi', [JournalController::class, 'create'])->name('create');
        Route::post('/', [JournalController::class, 'store'])->name('store');

        Route::get('{journal}', [JournalController::class, 'show'])->name('show');
        Route::get('{journal}/sua', [JournalController::class, 'edit'])->name('edit');
        Route::put('{journal}', [JournalController::class, 'update'])->name('update');
        Route::patch('{journal}/luu-tru', [JournalController::class, 'archive'])->name('archive');
        Route::delete('{journal}', [JournalController::class, 'destroy'])->name('destroy');

        Route::post('{journal}/trang', [JournalController::class, 'storeEntry'])->name('entries.store');
        Route::delete('{journal}/trang/{entry}', [JournalController::class, 'destroyEntry'])->name('entries.destroy');

        /*
         * MỐC MỤC TIÊU — chỉ có nghĩa với sổ Mục tiêu, nhưng route thì
         * không giới hạn theo loại sổ.
         *
         * Loại sổ đổi được bất cứ lúc nào ở trang sửa. Nếu route chặn
         * theo loại thì một quyển sổ Mục tiêu đổi sang Ghi chép tự do sẽ
         * mang theo các mốc không xoá được — dữ liệu kẹt lại mà không có
         * đường vào. Giao diện quyết định hiện khối nào; route chỉ giữ
         * quyền sở hữu.
         */
        Route::post('{journal}/moc', [JournalController::class, 'storeMilestone'])->name('milestones.store');
        Route::patch('{journal}/moc/{milestone}', [JournalController::class, 'toggleMilestone'])->name('milestones.toggle');
        Route::delete('{journal}/moc/{milestone}', [JournalController::class, 'destroyMilestone'])->name('milestones.destroy');
    });

/*
 * YÊU THÍCH CHỈ CẦN ĐĂNG NHẬP, không cần xác thực email.
 *
 * Lỗi đã sửa: nút thả tim chỉ cần đăng nhập, nhưng trang danh sách lại
 * bắt xác thực — khách chưa xác thực bấm tim được, mở trang ra thì bị
 * đuổi đi xác thực. Wishlist không đụng tới tiền hay dữ liệu người khác.
 */
Route::get('yeu-thich', [WishlistController::class, 'index'])
        ->name('shop.wishlist.index');

    Route::post('yeu-thich/{product:slug}', [WishlistController::class, 'toggle'])
        ->name('shop.wishlist.toggle');
});


/*
 * =========================
 * XÁC THỰC (Guest only)
 * =========================
 */

Route::middleware('guest')->group(function () {

    /*
     * QUÊN MẬT KHẨU.
     *
     * throttle ở ĐÂY chặn theo IP; broker của Laravel còn chặn riêng
     * theo email (config auth.passwords.users.throttle = 60 giây). Hai
     * lớp cần cả hai: chặn theo email không cản được kẻ quét hàng nghìn
     * địa chỉ khác nhau, còn chặn theo IP không cản được kẻ dội liên tục
     * vào một địa chỉ từ nhiều máy.
     *
     * Bước nhập email nặng hơn (gửi thư) nên siết chặt hơn bước đặt lại.
     */
    Route::get('quen-mat-khau', [PasswordResetController::class, 'showLinkForm'])
        ->name('password.request');

    Route::post('quen-mat-khau', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');

    Route::post('dat-lai-mat-khau', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->name('password.update');

    Route::get('register', [AuthController::class, 'showRegistrationForm'])
        ->name('register');

    Route::post('register', [AuthController::class, 'register']);

    Route::get('login', [AuthController::class, 'showLoginForm'])
        ->name('login');

    Route::post('login', [AuthController::class, 'login']);
});

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');


/*
 * =========================
 * XÁC THỰC EMAIL (OTP 6 chữ số)
 * =========================
 *
 * TÊN ROUTE PHẢI GIỮ NGUYÊN `verification.notice` / `verification.verify`
 * / `verification.send`: middleware `verified` của Laravel chuyển hướng
 * theo TÊN, không theo đường dẫn. Đổi tên là middleware ném lỗi "Route
 * [verification.notice] not defined" thay vì đưa khách tới trang nhập mã.
 *
 * Đường dẫn thì tiếng Việt cho đồng bộ với phần còn lại của trang.
 */
Route::middleware('auth')->group(function () {

    Route::get('xac-thuc-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    /*
     * throttle:10,1 — mười lần gõ mã mỗi phút trên mỗi IP.
     *
     * Đây là lớp chặn THỨ HAI. Lớp chính là bộ đếm attempts trong
     * EmailVerifier, vì nó bám theo TÀI KHOẢN nên đổi IP cũng không
     * thoát. Lớp này chỉ để một máy không nện được cả nghìn lần/phút.
     */
    Route::post('xac-thuc-email', [EmailVerificationController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('verification.confirm');

    // 6 lần mỗi phút — đúng con số bài thực hành dùng. Gửi thư tốn tiền
    // và làm phiền hộp thư người khác, nên phải chặn chặt hơn gõ mã.
    Route::post('xac-thuc-email/gui-lai', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    /*
     * ĐƯỜNG LIÊN KẾT CÓ CHỮ KÝ — cách mặc định của Laravel, giữ lại.
     *
     * `signed` kiểm chữ ký và hạn của liên kết. Thiếu nó thì ai đoán
     * đúng {id} và {hash} là xác thực hộ được người khác.
     */
    Route::get('xac-thuc-email/{id}/{hash}', [EmailVerificationController::class, 'verifyLink'])
        ->middleware('signed')
        ->name('verification.verify');
});


/*
 * =========================
 * KHU VỰC QUẢN TRỊ (admin)
 * =========================
 *
 * Yêu cầu đăng nhập và role "admin".
 */

/*
 * ============================================================
 * KHU QUẢN TRỊ — CỔNG CHUNG RỘNG, KHOÁ NẰM Ở TỪNG KHU
 * ============================================================
 * Cổng ngoài chỉ hỏi "có phải nhân sự không" (`role:admin,staff`). Ai
 * vào được KHU NÀO thì do `quyen:` của từng nhóm quyết định, và bảng
 * quyền nằm ở UserRole::quyen() — một nơi duy nhất.
 *
 * MỌI ĐƯỜNG DẪN TRONG NÀY PHẢI CÓ `quyen:`. Quên một dòng là dòng đó mở
 * cho mọi nhân viên. Có bài kiểm thử đi qua từng đường dẫn quản trị và
 * báo đỏ nếu thiếu — xem PhanQuyenTest.
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,staff'])
    ->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('quyen:bao-cao')
            ->name('dashboard');

        /*
         * =========================
         * CATEGORY
         * =========================
         */

        Route::resource(
            'categories',
            CategoryController::class
        )
            /*
             * KHÔNG có trang chi tiết danh mục ở khu quản trị.
             *
             * Route::resource() tự sinh GET /admin/categories/{category}
             * trỏ tới show(), nhưng controller không có hàm đó — mở
             * /admin/categories/1 là lỗi 500. Đo được trước khi sửa.
             *
             * Bỏ route đi thay vì viết một trang show() rỗng: danh mục
             * chỉ có tên, ảnh và thứ tự — trang sửa đã hiện đủ, thêm một
             * trang chỉ để xem là thêm chỗ phải bảo trì mà không ai vào.
             */
            ->except(['show'])
            ->middleware('quyen:san-pham');


        /*
         * =========================
         * PRODUCT
         * =========================
         *
         * Variant được quản lý bên trong
         * Product Create/Edit.
         *
         * Không tạo Variant CRUD riêng.
         */

        /*
         * HÀNG LOẠT — khai TRƯỚC Route::resource.
         *
         * resource() sinh ra GET/PUT/DELETE products/{product}. Nếu
         * route này khai sau, Laravel khớp 'products/hang-loat' vào
         * {product} trước và cố tìm sản phẩm có mã "hang-loat" — lỗi
         * 404 cho một đường dẫn hoàn toàn hợp lệ. Thứ tự khai là thứ
         * tự khớp.
         */
        Route::post('products/hang-loat', [ProductController::class, 'bulk'])
            ->middleware('quyen:san-pham')
            ->name('products.bulk');

        /*
         * THÙNG RÁC: khôi phục và xoá vĩnh viễn sản phẩm đã xoá mềm.
         *
         * Tham số là {id} số, không phải {product}: route model binding bỏ
         * qua bản ghi đã xoá mềm, nên {product} không bao giờ tìm thấy thứ
         * nằm trong thùng rác. Controller tìm bằng onlyTrashed().
         */
        Route::patch('products/{id}/khoi-phuc', [ProductController::class, 'restore'])
            ->whereNumber('id')
            ->middleware(['quyen:san-pham', 'throttle:30,1'])
            ->name('products.restore');

        Route::delete('products/{id}/xoa-vinh-vien', [ProductController::class, 'forceDestroy'])
            ->whereNumber('id')
            ->middleware(['quyen:san-pham', 'throttle:10,1'])
            ->name('products.force-destroy');

        Route::resource(
            'products',
            ProductController::class
        )->middleware('quyen:san-pham');


        /*
         * =========================
         * CHƯƠNG TRÌNH KHUYẾN MẠI
         * =========================
         *
         * Sản phẩm áp dụng được quản lý ngay trong trang sửa
         * chương trình (syncProducts), không có CRUD pivot riêng.
         */

        /*
         * MÃ GIẢM GIÁ — khách tự nhập, khác Khuyến mại (cửa hàng áp sẵn).
         * Chỉ bật khi module giỏ hàng bật: không có thanh toán thì mã
         * giảm giá không dùng vào đâu được.
         */
        if (config('features.cart')) {
            Route::resource('coupons', CouponController::class)
                ->except(['show'])
                ->parameters(['coupons' => 'coupon'])
                ->middleware('quyen:khuyen-mai');
        }

        /*
         * ĐÁNH GIÁ — chỉ có index + bật/tắt hiển thị.
         * Không có create/edit: nội dung là của khách, cửa hàng không được
         * sửa lời người ta rồi để nguyên tên người viết.
         */
        /*
         * PHÂN TÍCH (Guide §11 — "Admin → Analytics").
         * Chỉ đọc, không có thao tác ghi nên không cần throttle.
         */
        Route::get('phan-tich', [AnalyticsController::class, 'index'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.index');

        // Các trang con — xem AnalyticsPagesController.
        Route::get('phan-tich/doanh-thu', [AnalyticsPagesController::class, 'sales'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.sales');
        Route::get('phan-tich/khach-hang', [AnalyticsPagesController::class, 'customers'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.customers');
        Route::get('phan-tich/danh-gia', [AnalyticsPagesController::class, 'reviews'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.reviews');
        Route::get('phan-tich/loi-nhuan', [AnalyticsPagesController::class, 'profit'])
            ->middleware('quyen:tai-chinh')
            ->name('analytics.profit');

        /*
         * THU MUA thuộc quyền `kho`, không phải `bao-cao`.
         *
         * Người quyết định "kỳ sau lấy hoa ở đâu" là người đi lấy hàng.
         * Trang chỉ nói về tiền bỏ ra — không có giá bán, không có lãi —
         * nên không mở thêm gì mà khu kho chưa thấy.
         */
        Route::get('phan-tich/thu-mua', [AnalyticsPagesController::class, 'purchasing'])
            ->middleware('quyen:kho')
            ->name('analytics.purchasing');

        /*
         * ĐỀ XUẤT GIÁ & ƯU ĐÃI.
         *
         * Chỉ ĐỌC — không có route ghi nào. Công cụ nhắc admin, còn việc
         * đổi giá hay tạo chương trình vẫn phải bấm ở trang Khuyến mại.
         * Tự động hoá việc đó là tự động hoá một quyết định kinh doanh.
         */
        Route::get('de-xuat-gia', [PricingAdvisorController::class, 'index'])
            ->middleware('quyen:khuyen-mai')
            ->name('pricing-advisor.index');

        /*
         * CẨM NANG — quản trị.
         *
         * Nội dung bài được in ra trang dưới dạng HTML thô (chỗ duy nhất
         * trong dự án làm vậy), nên route ghi PHẢI nằm trong nhóm
         * `role:admin`. Xem chú thích đầu BlogPostController.
         */
        /*
         * CHUYÊN MỤC CẨM NANG — đường dẫn riêng, KHÔNG lồng dưới `cam-nang/`.
         *
         * Lồng vào thì `cam-nang/chuyen-muc` đứng cạnh `cam-nang/{post}` của
         * resource bên dưới, và một bài viết tên "chuyen-muc" là đủ để hai
         * đường dẫn giành nhau.
         */
        Route::get('chuyen-muc-cam-nang', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'index'])
            ->middleware('quyen:san-pham')
            ->name('blog-categories.index');

        Route::post('chuyen-muc-cam-nang', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'store'])
            ->middleware(['quyen:san-pham', 'throttle:30,1'])
            ->name('blog-categories.store');

        Route::put('chuyen-muc-cam-nang/{blogCategory}', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'update'])
            ->middleware(['quyen:san-pham', 'throttle:30,1'])
            ->name('blog-categories.update');

        Route::delete('chuyen-muc-cam-nang/{blogCategory}', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'destroy'])
            ->middleware(['quyen:san-pham', 'throttle:30,1'])
            ->name('blog-categories.destroy');

        Route::resource('cam-nang', BlogPostController::class)
            ->parameters(['cam-nang' => 'post'])
            ->except(['show'])
            ->names('blog')
            ->middleware('quyen:san-pham');

        /* DUYỆT BÀI "Góc cây của bạn". */
        Route::get('goc-cay', [CommunityModerationController::class, 'index'])
            ->middleware('quyen:danh-gia')
            ->name('community.index');
        Route::patch('goc-cay/{post}/duyet', [CommunityModerationController::class, 'approve'])
            ->middleware('quyen:danh-gia')
            ->name('community.approve');
        Route::patch('goc-cay/{post}/tu-choi', [CommunityModerationController::class, 'reject'])
            ->middleware('quyen:danh-gia')
            ->name('community.reject');
        Route::delete('goc-cay/{post}', [CommunityModerationController::class, 'destroy'])
            ->middleware('quyen:danh-gia')
            ->name('community.destroy');

        /*
         * XUẤT DỮ LIỆU — hai bước: chọn rồi mới tải.
         *
         * Cả hai đều là GET vì đây là thao tác ĐỌC, không đổi gì trong
         * hệ thống. Nhờ vậy đường dẫn kết quả chép và lưu dấu trang được
         * — "doanh thu 30 ngày, dạng CSV" thành một liên kết gửi cho kế
         * toán mỗi tháng.
         *
         * Đặt TRƯỚC route có tham số nào khác trong nhóm này không quan
         * trọng ở đây (không có route 'phan-tich/{...}'), nhưng giữ hai
         * đường cạnh nhau để lần sau thêm route con còn nhìn thấy.
         */
        /*
         * TỒN KHO — tách khỏi trang Sản phẩm.
         *
         * Trang Sản phẩm trả lời "cửa hàng bán những gì"; trang này trả
         * lời "phải nhập gì, phải bỏ gì, tiền đang nằm ở đâu". Chỉ đọc,
         * không có route ghi nào: sửa tồn kho vẫn ở trang sản phẩm, nơi
         * có đủ ngữ cảnh để biết mình đang sửa cái gì.
         */
        /*
         * =========================
         * NHÀ CUNG CẤP
         * =========================
         * KHÔNG có destroy: phiếu nhập cũ trỏ tới đây, xoá là mất dấu vết
         * những lần đã mua. Ngừng làm ăn thì tắt `is_active`.
         */
        /*
         * TỒN ĐẦU KỲ — khai giá vốn cho hàng đã có sẵn trên kệ.
         *
         * Chỉ có create + store: đây là việc làm một lần, và kết quả là
         * một phiếu nhập bình thường (loại `ton_dau_ky`) nên xem và ghi
         * sổ đi theo đường của phiếu nhập.
         */
        /*
         * =========================
         * HOA TƯƠI: LOẠI HOA VÀ LÔ
         * =========================
         * Lô đã đóng KHÔNG sửa và KHÔNG xoá: nó là một con số đã đi vào
         * giá vốn của một kỳ. Cùng nguyên tắc với phiếu nhập đã ghi sổ.
         */
        Route::get('loai-hoa', [FlowerKindController::class, 'index'])
            ->middleware('quyen:kho')
            ->name('flower-kinds.index');

        Route::post('loai-hoa', [FlowerKindController::class, 'store'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-kinds.store');

        Route::put('loai-hoa/{flowerKind}', [FlowerKindController::class, 'update'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-kinds.update');

        Route::get('lo-hoa', [FlowerLotController::class, 'index'])
            ->middleware('quyen:kho')
            ->name('flower-lots.index');

        Route::get('lo-hoa/ghi', [FlowerLotController::class, 'create'])
            ->middleware('quyen:kho')
            ->name('flower-lots.create');

        Route::post('lo-hoa', [FlowerLotController::class, 'store'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.store');

        Route::patch('lo-hoa/{flowerLot}/dong', [FlowerLotController::class, 'close'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.close');

        // Sửa / xoá chỉ cho lô CÒN MỞ và chưa ghi trả hàng — service chặn.
        Route::get('lo-hoa/{flowerLot}/sua', [FlowerLotController::class, 'edit'])
            ->middleware('quyen:kho')
            ->name('flower-lots.edit');

        Route::put('lo-hoa/{flowerLot}', [FlowerLotController::class, 'update'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.update');

        Route::delete('lo-hoa/{flowerLot}', [FlowerLotController::class, 'destroy'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.destroy');

        /*
         * TRẢ HÀNG CHO NHÀ CUNG CẤP.
         *
         * Một trang cho cả hai loại hàng; bên dưới là hai cơ chế khác
         * nhau (phiếu số âm cho hàng đếm được, ghi thẳng lên lô cho hoa)
         * nhưng với người dùng đó là MỘT việc.
         */
        Route::get('tra-hang-ncc', [SupplierReturnController::class, 'index'])
            ->middleware('quyen:kho')
            ->name('supplier-returns.index');

        Route::post('tra-hang-ncc/phieu/{stockReceipt}', [SupplierReturnController::class, 'storeGoods'])
            ->middleware(['quyen:kho', 'throttle:20,1'])
            ->name('supplier-returns.goods');

        Route::post('tra-hang-ncc/lo-hoa/{flowerLot}', [SupplierReturnController::class, 'storeFlower'])
            ->middleware(['quyen:kho', 'throttle:20,1'])
            ->name('supplier-returns.flower');

        Route::get('ton-dau-ky', [OpeningStockController::class, 'create'])
            ->middleware('quyen:kho')
            ->name('opening-stock.create');

        Route::post('ton-dau-ky', [OpeningStockController::class, 'store'])
            ->middleware(['quyen:kho', 'throttle:10,1'])
            ->name('opening-stock.store');

        Route::get('nha-cung-cap', [SupplierController::class, 'index'])
            ->middleware('quyen:kho')
            ->name('suppliers.index');

        Route::get('nha-cung-cap/them', [SupplierController::class, 'create'])
            ->middleware('quyen:kho')
            ->name('suppliers.create');

        Route::post('nha-cung-cap', [SupplierController::class, 'store'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('suppliers.store');

        Route::get('nha-cung-cap/{supplier}/sua', [SupplierController::class, 'edit'])
            ->middleware('quyen:kho')
            ->name('suppliers.edit');

        Route::put('nha-cung-cap/{supplier}', [SupplierController::class, 'update'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('suppliers.update');

        Route::get('ton-kho', [InventoryController::class, 'index'])
            ->middleware('quyen:kho')
            ->name('inventory.index');

        /*
         * PHIẾU NHẬP KHO.
         *
         * KHÔNG CÓ `update`: ghi sổ là hành động có tác động thật (kho đã
         * cộng thêm), và sửa một chứng từ sau khi nó đã tác động là làm
         * sổ sách không khớp thực tế. Nhập nhầm thì lập phiếu điều chỉnh
         * với số lượng âm.
         *
         * `destroy` chỉ dùng được cho phiếu còn NHÁP — controller chặn.
         */
        Route::resource('nhap-kho', StockReceiptController::class)
            ->parameters(['nhap-kho' => 'stockReceipt'])
            ->except(['edit', 'update'])
            ->names('stock-receipts')
            ->middleware('quyen:kho');

        /*
         * GHI SỔ — POST chứ không GET: nó CỘNG vào kho và không lùi
         * được. Không được phép xảy ra chỉ vì ai đó mở một đường dẫn.
         *
         * throttle: một cú bấm liên tục không được biến thành nhiều lượt
         * ghi sổ. Bản thân service cũng khoá hàng và chặn ghi hai lần,
         * nhưng chặn từ sớm thì rẻ hơn.
         */
        Route::post('nhap-kho/{stockReceipt}/ghi-so', [StockReceiptController::class, 'post'])
            ->middleware('quyen:kho')
            ->middleware('throttle:20,1')
            ->name('stock-receipts.post');

        /*
         * KIỂM KÊ KHO — cùng khuôn với phiếu nhập: không sửa, xoá chỉ khi còn
         * nháp, ghi sổ bằng POST có throttle.
         */
        Route::resource('kiem-ke', StockCountController::class)
            ->parameters(['kiem-ke' => 'stockCount'])
            ->except(['edit', 'update'])
            ->names('stock-counts')
            ->middleware('quyen:kho');

        Route::post('kiem-ke/{stockCount}/ghi-so', [StockCountController::class, 'post'])
            ->middleware('quyen:kho')
            ->middleware('throttle:20,1')
            ->name('stock-counts.post');

        Route::get('phan-tich/xuat', [AnalyticsController::class, 'exportForm'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.export-form');

        Route::get('phan-tich/xuat/tai-ve', [AnalyticsController::class, 'export'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.export');

        Route::get('reviews', [AdminReviewController::class, 'index'])
            ->middleware('quyen:danh-gia')
            ->name('reviews.index');

        Route::post('reviews/hang-loat', [AdminReviewController::class, 'bulk'])
            ->middleware('quyen:danh-gia')
            ->name('reviews.bulk');

        Route::patch('reviews/{review}/hien-thi', [AdminReviewController::class, 'toggle'])
            ->middleware('quyen:danh-gia')
            ->name('reviews.toggle');

        /*
         * Phản hồi của cửa hàng — CÔNG KHAI, khách nào cũng đọc được.
         * Gửi ô trống nghĩa là xoá phản hồi, không cần route riêng.
         */
        Route::patch('reviews/{review}/phan-hoi', [AdminReviewController::class, 'reply'])
            ->middleware('quyen:danh-gia')
            ->name('reviews.reply');

        Route::resource('promotions', PromotionController::class)
            ->except(['show'])
            ->middleware('quyen:khuyen-mai');

        Route::put('promotions/{promotion}/products', [PromotionController::class, 'syncProducts'])
            ->middleware('quyen:khuyen-mai')
            ->name('promotions.sync-products');


        /*
         * =========================
         * YÊU CẦU ĐẶT SỐ LƯỢNG LỚN
         * =========================
         */

        /*
         * ĐƠN HÀNG
         * Không dùng Route::resource: đơn hàng KHÔNG được tạo/sửa/xoá
         * từ admin. Nhân viên chỉ xem và đổi trạng thái — đơn là chứng
         * từ do khách tạo ra.
         */
        Route::prefix('orders')
            ->name('orders.')
            ->middleware('quyen:don-hang')
            ->group(function () {
                Route::get('/', [AdminOrderController::class, 'index'])->name('index');
                Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
                Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('update-status');
                /*
                 * MỘT route cho cả ba thao tác thanh toán: ghi nhận đã
                 * trả, hoàn tiền, và gỡ đánh dấu khi bấm nhầm. Luật
                 * chuyển trạng thái nằm trong PaymentStatus — thêm route
                 * riêng cho từng thao tác là thêm chỗ để quên phép kiểm.
                 */
                /*
                 * THANH TOÁN VÀ HOÀN TIỀN LÀ VIỆC TIỀN — thêm quyền tài chính.
                 *
                 * Lỗ hổng đã sửa: hai đường này nằm dưới quyền `don-hang` của cả
                 * nhóm, nên nhân viên đánh dấu được "đã thanh toán" và ghi hoàn
                 * tiền mặt / chuyển khoản (hoàn tất ngay) — trong khi Quyen::
                 * TaiChinh tồn tại đúng để tách việc đó ra. Middleware cộng dồn:
                 * phải có CẢ đơn hàng lẫn tài chính.
                 */
                Route::patch('/{order}/thanh-toan', [AdminOrderController::class, 'updatePayment'])
                    ->middleware('quyen:tai-chinh')
                    ->name('payment');

                // Ghi chú nội bộ — chỉ cửa hàng đọc, khách không thấy.
                Route::patch('/{order}/ghi-chu', [AdminOrderController::class, 'updateNote'])
                    ->name('note');

                // Sửa thông tin giao hàng khi khách gọi báo nhập nhầm — xem OrderController::updateDelivery().
                Route::patch('/{order}/giao-hang', [AdminOrderController::class, 'updateDelivery'])
                    ->middleware('throttle:30,1')
                    ->name('delivery');

                // Phiếu soạn hàng + phiếu giao hàng. Chỉ ĐỌC, nên không throttle.
                Route::get('/{order}/in', [AdminOrderController::class, 'printSlip'])
                    ->name('print');

                /*
                 * VẬN ĐƠN GIAO HÀNG NHANH.
                 *
                 * POST/DELETE chứ không phải GET: cả hai đều gọi ra
                 * ngoài tới GHN và tính tiền thật cho cửa hàng. Để GET
                 * thì trình duyệt, trình quét và phần tải trước đều gọi
                 * được — nghĩa là một chuyến xe có thể được đặt mà không
                 * ai bấm nút nào.
                 */
                Route::post('/{order}/van-don', [AdminOrderController::class, 'createShipment'])
                    ->name('shipment.create');

                Route::delete('/{order}/van-don', [AdminOrderController::class, 'cancelShipment'])
                    ->name('shipment.cancel');

                /*
                 * HOÀN TIỀN.
                 *
                 * throttle: hoàn qua MoMo là chuyển tiền thật ra khỏi cửa
                 * hàng. Luật "không hoàn quá số đã trả" đã chặn ở dịch vụ,
                 * nhưng không có lý do gì để một người bấm được hàng chục
                 * lần mỗi phút.
                 */
                Route::post('/{order}/hoan-tien', [RefundController::class, 'store'])
                    ->middleware(['quyen:tai-chinh', 'throttle:20,1'])
                    ->name('refunds.store');
            });

        /*
         * =========================
         * ĐỔI HÀNG
         * =========================
         * Lập phiếu nằm trong trang đơn (POST vào đường dẫn của đơn); các
         * bước sau đi theo phiếu. Không dùng Route::resource: phiếu đổi
         * KHÔNG sửa và KHÔNG xoá được — nó là chứng từ, sai thì huỷ và lập
         * phiếu khác, y như hoàn tiền.
         */
        Route::post('orders/{order}/doi-hang', [ExchangeController::class, 'store'])
            ->middleware(['quyen:don-hang', 'throttle:20,1'])
            ->name('exchanges.store');

        Route::get('doi-hang', [ExchangeController::class, 'index'])
            ->middleware('quyen:don-hang')
            ->name('exchanges.index');

        Route::get('doi-hang/{exchange}', [ExchangeController::class, 'show'])
            ->middleware('quyen:don-hang')
            ->name('exchanges.show');

        Route::patch('doi-hang/{exchange}/nhan-hang', [ExchangeController::class, 'nhanHang'])
            ->middleware(['quyen:don-hang', 'throttle:20,1'])
            ->name('exchanges.receive');

        Route::patch('doi-hang/{exchange}/hoan-tat', [ExchangeController::class, 'hoanTat'])
            ->middleware(['quyen:don-hang', 'throttle:20,1'])
            ->name('exchanges.complete');

        Route::patch('doi-hang/{exchange}/huy', [ExchangeController::class, 'huy'])
            ->middleware(['quyen:don-hang', 'throttle:20,1'])
            ->name('exchanges.cancel');

        Route::patch('hoan-tien/{refund}/xac-nhan', [RefundController::class, 'confirm'])
            ->middleware('quyen:tai-chinh')
            ->middleware('throttle:20,1')
            ->name('refunds.confirm');

        Route::patch('hoan-tien/{refund}/that-bai', [RefundController::class, 'fail'])
            ->middleware('quyen:tai-chinh')
            ->middleware('throttle:20,1')
            ->name('refunds.fail');

        /*
         * DANH SÁCH HOÀN TIỀN — mọi khoản, không phải từng đơn.
         *
         * Trước đây khoản hoàn chỉ xem được trong trang của từng đơn: muốn
         * đối soát "tháng này đã trả lại khách bao nhiêu" là mở từng đơn.
         * Cùng quyền tài chính với nút xác nhận tiền đã đi.
         */
        Route::get('hoan-tien', [RefundController::class, 'index'])
            ->middleware('quyen:tai-chinh')
            ->name('refunds.index');

        /*
         * SỔ THU CHI — chi phí vận hành và lãi ròng ước tính theo tháng.
         * Quyền tài chính: lương từng người và lãi ròng. Xem ExpenseController.
         */
        Route::prefix('thu-chi')
            ->name('expenses.')
            ->middleware('quyen:tai-chinh')
            ->group(function () {
                Route::get('/', [ExpenseController::class, 'index'])->name('index');
                Route::get('/them', [ExpenseController::class, 'create'])->name('create');
                Route::post('/', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('store');
                Route::post('/chep-co-dinh', [ExpenseController::class, 'copyFixed'])->middleware('throttle:10,1')->name('copy-fixed');
                Route::get('/{expense}/sua', [ExpenseController::class, 'edit'])->name('edit');
                Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('throttle:30,1')->name('update');
                Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');
            });

        Route::prefix('bulk-inquiries')
            ->name('bulk-inquiries.')
            ->middleware('quyen:don-hang')
            ->group(function () {
                Route::get('/', [AdminBulkInquiryController::class, 'index'])->name('index');
                Route::get('/{bulkInquiry}', [AdminBulkInquiryController::class, 'show'])->name('show');
                Route::patch('/{bulkInquiry}/status', [AdminBulkInquiryController::class, 'updateStatus'])->name('update-status');
            });


        /*
         * =========================
         * NGƯỜI DÙNG (chỉ xem)
         * =========================
         */

        /*
         * TRANG NỘI DUNG (giới thiệu, chính sách) — sửa được mà không đụng
         * mã nguồn. Quyền hệ thống, cùng chỗ với Cài đặt.
         */
        Route::get('trang-noi-dung', [\App\Http\Controllers\Admin\PageContentController::class, 'edit'])
            ->middleware('quyen:he-thong')
            ->name('page-contents.edit');

        Route::put('trang-noi-dung', [\App\Http\Controllers\Admin\PageContentController::class, 'update'])
            ->middleware(['quyen:he-thong', 'throttle:20,1'])
            ->name('page-contents.update');

        Route::get('users', [UserController::class, 'index'])
            ->middleware('quyen:he-thong')
            ->name('users.index');

        // Hồ sơ một khách: đơn, địa chỉ, tiền đã chi. Chỉ ĐỌC.
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('quyen:he-thong')
            ->name('users.show');

        /*
         * KHÔNG dùng Route::resource cho người dùng.
         *
         * Tài khoản không được TẠO hay XOÁ từ khu quản trị: người dùng
         * tự đăng ký, và xoá thì làm mất người đứng tên trên đơn cũ.
         * resource() sẽ sinh ra create/store/destroy — ba route không
         * có việc để làm, mà vẫn phải nhớ là chúng tồn tại.
         *
         * PATCH chứ không PUT: cả hai chỉ sửa MỘT thuộc tính, không
         * thay cả bản ghi.
         */
        Route::patch('users/{user}/vai-tro', [UserController::class, 'updateRole'])
            ->middleware('quyen:he-thong')
            ->name('users.role');

        Route::patch('users/{user}/khoa', [UserController::class, 'updateLock'])
            ->middleware('quyen:he-thong')
            ->name('users.lock');


        /*
         * =========================
         * CÀI ĐẶT (theme realtime...)
         * =========================
         */

        /*
         * =========================
         * NHẬT KÝ THAO TÁC
         * =========================
         *
         * CHỈ CÓ MỘT ROUTE, và cố ý là vậy. Không có destroy: nhật ký mà
         * người bị ghi xoá được thì không dùng để đối chiếu, mà đối
         * chiếu là toàn bộ lý do nó tồn tại.
         *
         * Đường dẫn tiếng Việt như các trang quản trị khác.
         */
        Route::get('nhat-ky', [ActivityLogController::class, 'index'])
            ->middleware('quyen:he-thong')
            ->name('activity-logs.index');


        Route::get('settings', [SettingsController::class, 'edit'])
            ->middleware('quyen:he-thong')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])
            ->middleware('quyen:he-thong')->name('settings.update');
    });
