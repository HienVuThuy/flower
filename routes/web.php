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


Route::get('/', [HomeController::class, 'index'])->name('welcome');


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

Route::get('phu-kien', [SupplyController::class, 'index'])->name('shop.supplies.index');


Route::get('nhu-cau/{intent}', [IntentController::class, 'show'])
    ->name('shop.intents.show');


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


if (config('features.cart')) {

    Route::prefix('gio-hang')
        ->name('shop.cart.')
        ->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/', [CartController::class, 'store'])->name('store');
            Route::delete('/tat-ca', [CartController::class, 'clear'])
                ->middleware('throttle:20,1')
                ->name('clear');

            Route::patch('/{cartItem}', [CartController::class, 'update'])->name('update');
            Route::delete('/{cartItem}', [CartController::class, 'destroy'])->name('destroy');

            Route::post('/chon', [CartController::class, 'select'])->name('select');

            Route::get('/khoi', [CartController::class, 'fragment'])->name('fragment');
        });

    Route::post('mua-ngay', [CartController::class, 'buyNow'])->name('shop.cart.buy-now');

    Route::prefix('thanh-toan')
        ->name('shop.checkout.')
        ->group(function () {
            Route::get('/', [CheckoutController::class, 'details'])->name('details');
            Route::post('/', [CheckoutController::class, 'storeDetails'])->name('store-details');

            Route::get('van-chuyen', [CheckoutController::class, 'shipping'])->name('shipping');

            Route::post('ma-giam-gia', [CheckoutController::class, 'applyCoupon'])->name('apply-coupon');
            Route::delete('ma-giam-gia', [CheckoutController::class, 'removeCoupon'])->name('remove-coupon');
            Route::post('ma-giam-gia/tu-chon', [CheckoutController::class, 'autoCoupon'])->name('auto-coupon');

            Route::post('diem-thuong', [CheckoutController::class, 'applyPoints'])->middleware('auth')->name('apply-points');
            Route::delete('diem-thuong', [CheckoutController::class, 'removePoints'])->middleware('auth')->name('remove-points');

            Route::get('xac-nhan', [CheckoutController::class, 'confirm'])->name('confirm');
            Route::post('dat-hang', [CheckoutController::class, 'place'])->name('place');
        });

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

            Route::post('/gui-lien-ket-mat-khau', [ProfileController::class, 'sendPasswordResetLink'])
                ->middleware('throttle:3,1')
                ->name('password-link');

            Route::put('/thong-bao', [ProfileController::class, 'updateNotifications'])
                ->name('notifications');

            Route::post('/xoa/yeu-cau', [ProfileController::class, 'requestDeletion'])
                ->middleware('throttle:3,10')
                ->name('delete.request');

            Route::get('/xoa/xac-nhan/{user}', [ProfileController::class, 'confirmDeletion'])
                ->middleware('signed')
                ->name('delete.confirm');

            Route::delete('/xoa/{user}', [ProfileController::class, 'destroyAccount'])
                ->middleware('signed')
                ->name('delete');

            Route::delete('/phien-dang-nhap', [ProfileController::class, 'revokeSession'])
                ->name('sessions.revoke');
        });

    Route::prefix('lich-cham-cay')
        ->name('shop.care.')
        ->middleware(['auth', 'verified'])
        ->group(function () {
            Route::get('/', [CareController::class, 'index'])->name('index');
            Route::patch('/{reminder}', [CareController::class, 'toggle'])->name('toggle');
            Route::post('/{reminder}/xong', [CareController::class, 'done'])->name('done');
        });

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

            Route::get('/tra-cuu', [OrderLookupController::class, 'form'])->name('lookup');

            Route::post('/tra-cuu', [OrderLookupController::class, 'find'])
                ->middleware('throttle:5,1')
                ->name('lookup.find');

            Route::get('/{order}', [ShopOrderController::class, 'show'])->name('show');

            Route::post('/{order}/huy', [ShopOrderController::class, 'cancel'])
                ->middleware('throttle:10,1')
                ->name('cancel');

            Route::get('/{order}/thanh-toan-momo', [MomoController::class, 'payAgain'])
                ->middleware('throttle:10,1')
                ->name('momo.pay');

            Route::get('/{order}/tra-gop-momo', [\App\Http\Controllers\Shop\InstallmentPaymentController::class, 'momo'])
                ->middleware('throttle:10,1')
                ->name('tra-gop.momo');
        });

    Route::prefix('thanh-toan/momo')
        ->name('shop.payment.momo.')
        ->group(function () {
            Route::get('/ket-qua', [MomoController::class, 'callback'])->name('callback');
            Route::post('/ipn', [MomoController::class, 'ipn'])->name('ipn');
        });

    Route::get('thanh-toan/momo/{order}', [MomoController::class, 'start'])
        ->name('shop.payment.momo.start');
}


Route::get('nguon-anh', [CreditsController::class, 'index'])->name('shop.credits');


Route::get('cam-nang', [BlogController::class, 'index'])->name('shop.blog.index');
Route::get('cam-nang/{post}', [BlogController::class, 'show'])->name('shop.blog.show');

Route::get('goc-cay', [CommunityController::class, 'index'])->name('shop.community.index');

Route::post('tro-ly-ai', [\App\Http\Controllers\Shop\AiChatController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('shop.ai.ask');
Route::delete('tro-ly-ai', [\App\Http\Controllers\Shop\AiChatController::class, 'destroy'])
    ->name('shop.ai.reset');
Route::get('goc-cay/thanh-vien/{user}', [CommunityController::class, 'profile'])
    ->whereNumber('user')
    ->name('shop.community.profile');

Route::get('goc-cay/{post}', [CommunityController::class, 'show'])->whereNumber('post')->name('shop.community.show');

Route::get('voucher', [VoucherController::class, 'index'])->name('shop.vouchers.index');

Route::get('voucher/{coupon}', [VoucherController::class, 'show'])
    ->name('shop.vouchers.show');


Route::get('chon-cay', [AdvisorController::class, 'index'])->name('shop.advisor.index');

Route::get('loai-cay', [PlantTaxonController::class, 'index'])->name('shop.taxa.index');
Route::get('loai-cay/{taxon}', [PlantTaxonController::class, 'show'])->name('shop.taxa.show');


Route::get('trang/{slug}', [PageController::class, 'show'])
    ->name('shop.pages.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('goc-cay', [CommunityController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('shop.community.store');

    Route::get('goc-cay/{post}/sua', [CommunityController::class, 'edit'])
        ->whereNumber('post')
        ->name('shop.community.edit');

    Route::patch('goc-cay/{post}', [CommunityController::class, 'update'])
        ->whereNumber('post')
        ->middleware('throttle:20,1')
        ->name('shop.community.update');

    Route::delete('goc-cay/{post}', [CommunityController::class, 'destroy'])
        ->name('shop.community.destroy');

    Route::post('goc-cay/{post}/thich', [CommunityController::class, 'like'])
        ->middleware('throttle:60,1')
        ->name('shop.community.like');

    Route::post('goc-cay/{post}/luu', [CommunityController::class, 'save'])
        ->middleware('throttle:60,1')
        ->name('shop.community.save');

    Route::post('goc-cay/{post}/binh-luan', [CommunityController::class, 'comment'])
        ->middleware('throttle:10,1')
        ->name('shop.community.comment');

    Route::post('goc-cay/binh-luan/{comment}/cam-xuc', [CommunityController::class, 'reactComment'])
        ->middleware('throttle:60,1')
        ->name('shop.community.comment.react');

    Route::patch('goc-cay/binh-luan/{comment}', [CommunityController::class, 'updateComment'])
        ->middleware('throttle:20,1')
        ->name('shop.community.comment.update');

    Route::delete('goc-cay/binh-luan/{comment}', [CommunityController::class, 'destroyComment'])
        ->name('shop.community.comment.destroy');

    Route::patch('goc-cay/{post}/an-cua-toi', [\App\Http\Controllers\Shop\CommunityPostOwnerController::class, 'hide'])
        ->whereNumber('post')
        ->name('shop.community.owner.hide');

    Route::patch('goc-cay/{post}/ghim', [\App\Http\Controllers\Shop\CommunityPostOwnerController::class, 'pin'])
        ->whereNumber('post')
        ->name('shop.community.owner.pin');

    Route::patch('goc-cay/{post}/khoa-binh-luan', [\App\Http\Controllers\Shop\CommunityPostOwnerController::class, 'lockComments'])
        ->whereNumber('post')
        ->name('shop.community.owner.lock');

    Route::patch('goc-cay/binh-luan/{comment}/an', [\App\Http\Controllers\Shop\CommunityPostOwnerController::class, 'hideComment'])
        ->whereNumber('comment')
        ->name('shop.community.owner.comment-hide');

    Route::post('goc-cay/bao-cao', [CommunityController::class, 'report'])
        ->middleware('throttle:20,1')
        ->name('shop.community.report');

    Route::post('voucher/{coupon}/luu', [VoucherController::class, 'claim'])
        ->name('shop.vouchers.claim');

    Route::patch('voucher/{coupon}/luu', [VoucherController::class, 'unhide'])
        ->name('shop.vouchers.unhide');

    Route::delete('voucher/{coupon}/luu', [VoucherController::class, 'discard'])
        ->name('shop.vouchers.discard');

    Route::post('diem-thuong/doi', [\App\Http\Controllers\Shop\PointController::class, 'redeem'])
        ->middleware('throttle:10,1')
        ->name('shop.points.redeem');
});


Route::prefix('api')
    ->name('api.')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::get('goi-y-tim-kiem', SearchSuggestionController::class)
            ->name('search.suggest');
    });


Route::middleware('auth')->group(function () {

    Route::get('thong-bao', [\App\Http\Controllers\Shop\NotificationController::class, 'index'])
        ->name('shop.notifications.index');

    Route::get('thong-bao/{notification}', [\App\Http\Controllers\Shop\NotificationController::class, 'open'])
        ->whereNumber('notification')
        ->name('shop.notifications.open');

    Route::post('thong-bao/doc-het', [\App\Http\Controllers\Shop\NotificationController::class, 'readAll'])
        ->name('shop.notifications.read-all');

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

        Route::post('{journal}/moc', [JournalController::class, 'storeMilestone'])->name('milestones.store');
        Route::patch('{journal}/moc/{milestone}', [JournalController::class, 'toggleMilestone'])->name('milestones.toggle');
        Route::delete('{journal}/moc/{milestone}', [JournalController::class, 'destroyMilestone'])->name('milestones.destroy');
    });

Route::get('yeu-thich', [WishlistController::class, 'index'])
        ->name('shop.wishlist.index');

    Route::post('yeu-thich/{product:slug}', [WishlistController::class, 'toggle'])
        ->name('shop.wishlist.toggle');
});


Route::middleware('guest')->group(function () {

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


Route::middleware('auth')->group(function () {

    Route::get('xac-thuc-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::post('xac-thuc-email', [EmailVerificationController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('verification.confirm');

    Route::post('xac-thuc-email/gui-lai', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('xac-thuc-email/{id}/{hash}', [EmailVerificationController::class, 'verifyLink'])
        ->middleware('signed')
        ->name('verification.verify');
});


Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,staff'])
    ->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('quyen:bao-cao')
            ->name('dashboard');

        Route::resource(
            'categories',
            CategoryController::class
        )
            ->except(['show'])
            ->middleware('quyen:san-pham');


        Route::post('products/hang-loat', [ProductController::class, 'bulk'])
            ->middleware('quyen:san-pham')
            ->name('products.bulk');

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


        if (config('features.cart')) {
            Route::resource('coupons', CouponController::class)
                ->except(['show'])
                ->parameters(['coupons' => 'coupon'])
                ->middleware('quyen:khuyen-mai');
        }

        Route::get('phan-tich', [AnalyticsController::class, 'index'])
            ->middleware('quyen:bao-cao')
            ->name('analytics.index');

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

        Route::get('phan-tich/thu-mua', [AnalyticsPagesController::class, 'purchasing'])
            ->middleware('quyen:kho')
            ->name('analytics.purchasing');

        Route::get('de-xuat-gia', [PricingAdvisorController::class, 'index'])
            ->middleware('quyen:khuyen-mai')
            ->name('pricing-advisor.index');

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
        Route::patch('goc-cay/binh-luan/{comment}/an', [CommunityModerationController::class, 'toggleComment'])
            ->middleware('quyen:danh-gia')
            ->name('community.comments.toggle');

        Route::patch('goc-cay/{post}/an', [CommunityModerationController::class, 'toggleHidden'])
            ->middleware('quyen:danh-gia')
            ->name('community.hide');

        Route::post('goc-cay/bao-cao', [CommunityModerationController::class, 'handleReport'])
            ->middleware('quyen:danh-gia')
            ->name('community.reports.handle');

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

        Route::get('lo-hoa/{flowerLot}/sua', [FlowerLotController::class, 'edit'])
            ->middleware('quyen:kho')
            ->name('flower-lots.edit');

        Route::put('lo-hoa/{flowerLot}', [FlowerLotController::class, 'update'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.update');

        Route::delete('lo-hoa/{flowerLot}', [FlowerLotController::class, 'destroy'])
            ->middleware(['quyen:kho', 'throttle:30,1'])
            ->name('flower-lots.destroy');

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

        Route::resource('nhap-kho', StockReceiptController::class)
            ->parameters(['nhap-kho' => 'stockReceipt'])
            ->except(['edit', 'update'])
            ->names('stock-receipts')
            ->middleware('quyen:kho');

        Route::post('nhap-kho/{stockReceipt}/ghi-so', [StockReceiptController::class, 'post'])
            ->middleware('quyen:kho')
            ->middleware('throttle:20,1')
            ->name('stock-receipts.post');

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

        Route::patch('reviews/{review}/phan-hoi', [AdminReviewController::class, 'reply'])
            ->middleware('quyen:danh-gia')
            ->name('reviews.reply');

        Route::resource('promotions', PromotionController::class)
            ->except(['show'])
            ->middleware('quyen:khuyen-mai');

        Route::put('promotions/{promotion}/products', [PromotionController::class, 'syncProducts'])
            ->middleware('quyen:khuyen-mai')
            ->name('promotions.sync-products');


        Route::prefix('orders')
            ->name('orders.')
            ->middleware('quyen:don-hang')
            ->group(function () {
                Route::get('/', [AdminOrderController::class, 'index'])->name('index');
                Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
                Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('update-status');
                Route::patch('/{order}/thanh-toan', [AdminOrderController::class, 'updatePayment'])
                    ->middleware('quyen:tai-chinh')
                    ->name('payment');

                Route::patch('/{order}/ghi-chu', [AdminOrderController::class, 'updateNote'])
                    ->name('note');

                Route::patch('/{order}/giao-hang', [AdminOrderController::class, 'updateDelivery'])
                    ->middleware('throttle:30,1')
                    ->name('delivery');

                Route::get('/{order}/in', [AdminOrderController::class, 'printSlip'])
                    ->name('print');

                Route::post('/{order}/van-don', [AdminOrderController::class, 'createShipment'])
                    ->name('shipment.create');

                Route::delete('/{order}/van-don', [AdminOrderController::class, 'cancelShipment'])
                    ->name('shipment.cancel');

                Route::post('/{order}/hoan-tien', [RefundController::class, 'store'])
                    ->middleware(['quyen:tai-chinh', 'throttle:20,1'])
                    ->name('refunds.store');
            });

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

        Route::get('hoan-tien', [RefundController::class, 'index'])
            ->middleware('quyen:tai-chinh')
            ->name('refunds.index');

        Route::get('tra-gop', [\App\Http\Controllers\Admin\InstallmentController::class, 'index'])
            ->middleware('quyen:tai-chinh')
            ->name('installments.index');

        Route::put('tra-gop/cau-hinh', [\App\Http\Controllers\Admin\InstallmentController::class, 'updateSettings'])
            ->middleware('quyen:tai-chinh')
            ->name('installments.settings');

        Route::post('orders/{order}/tra-gop/thu', [\App\Http\Controllers\Admin\InstallmentController::class, 'record'])
            ->middleware(['quyen:don-hang', 'quyen:tai-chinh', 'throttle:20,1'])
            ->name('orders.installments.record');

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

        Route::get('hang-thanh-vien', [\App\Http\Controllers\Admin\MemberTierController::class, 'index'])
            ->middleware('quyen:khuyen-mai')
            ->name('member-tiers.index');
        Route::put('hang-thanh-vien', [\App\Http\Controllers\Admin\MemberTierController::class, 'update'])
            ->middleware(['quyen:khuyen-mai', 'throttle:20,1'])
            ->name('member-tiers.update');

        Route::get('qua-tang', [\App\Http\Controllers\Admin\ProductGiftController::class, 'index'])
            ->middleware('quyen:khuyen-mai')->name('product-gifts.index');
        Route::get('qua-tang/chon', [\App\Http\Controllers\Admin\ProductGiftController::class, 'open'])
            ->middleware('quyen:khuyen-mai')->name('product-gifts.open');
        Route::get('qua-tang/san-pham/{product}', [\App\Http\Controllers\Admin\ProductGiftController::class, 'edit'])
            ->middleware('quyen:khuyen-mai')->name('product-gifts.edit');
        Route::post('qua-tang/san-pham/{product}', [\App\Http\Controllers\Admin\ProductGiftController::class, 'store'])
            ->middleware(['quyen:khuyen-mai', 'throttle:30,1'])->name('product-gifts.store');
        Route::put('qua-tang/san-pham/{product}/{productGift}', [\App\Http\Controllers\Admin\ProductGiftController::class, 'update'])
            ->middleware(['quyen:khuyen-mai', 'throttle:30,1'])->name('product-gifts.update');
        Route::delete('qua-tang/san-pham/{product}/{productGift}', [\App\Http\Controllers\Admin\ProductGiftController::class, 'destroy'])
            ->middleware(['quyen:khuyen-mai', 'throttle:30,1'])->name('product-gifts.destroy');

        Route::resource('khuyen-mai-qua', \App\Http\Controllers\Admin\GiftCampaignController::class)
            ->except('show')
            ->parameters(['khuyen-mai-qua' => 'giftCampaign'])
            ->names('gift-campaigns')
            ->middleware('quyen:khuyen-mai');
        Route::resource('vat-pham-qua', \App\Http\Controllers\Admin\GiftItemController::class)
            ->except('show')
            ->parameters(['vat-pham-qua' => 'giftItem'])
            ->names('gift-items')
            ->middleware('quyen:khuyen-mai');

        Route::prefix('bulk-inquiries')
            ->name('bulk-inquiries.')
            ->middleware('quyen:don-hang')
            ->group(function () {
                Route::get('/', [AdminBulkInquiryController::class, 'index'])->name('index');
                Route::get('/{bulkInquiry}', [AdminBulkInquiryController::class, 'show'])->name('show');
                Route::patch('/{bulkInquiry}/status', [AdminBulkInquiryController::class, 'updateStatus'])->name('update-status');
            });


        Route::get('trang-noi-dung', [\App\Http\Controllers\Admin\PageContentController::class, 'edit'])
            ->middleware('quyen:he-thong')
            ->name('page-contents.edit');

        Route::put('trang-noi-dung', [\App\Http\Controllers\Admin\PageContentController::class, 'update'])
            ->middleware(['quyen:he-thong', 'throttle:20,1'])
            ->name('page-contents.update');

        Route::get('users', [UserController::class, 'index'])
            ->middleware('quyen:he-thong')
            ->name('users.index');

        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('quyen:he-thong')
            ->name('users.show');

        Route::patch('users/{user}/vai-tro', [UserController::class, 'updateRole'])
            ->middleware('quyen:he-thong')
            ->name('users.role');

        Route::patch('users/{user}/khoa', [UserController::class, 'updateLock'])
            ->middleware('quyen:he-thong')
            ->name('users.lock');


        Route::get('nhat-ky', [ActivityLogController::class, 'index'])
            ->middleware('quyen:he-thong')
            ->name('activity-logs.index');


        Route::get('settings', [SettingsController::class, 'edit'])
            ->middleware('quyen:he-thong')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])
            ->middleware('quyen:he-thong')->name('settings.update');
    });
