<?php

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

Route::get('sitemap.xml', \App\Http\Controllers\Shop\SitemapController::class)->name('sitemap');

/* robots.txt sinh động để địa chỉ sitemap luôn đúng tên miền đang chạy. */
Route::get('robots.txt', function () {
    $rieng = ['/admin', '/gio-hang', '/thanh-toan', '/tai-khoan', '/don-hang', '/thong-bao', '/tin-nhan', '/nhat-ky'];

    $dong = ['User-agent: *', 'Allow: /'];

    foreach ($rieng as $duong) {
        $dong[] = 'Disallow: ' . $duong;
    }

    $dong[] = '';
    $dong[] = 'Sitemap: ' . route('sitemap');

    return response(implode(PHP_EOL, $dong) . PHP_EOL, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

Route::middleware(['auth', 'verified'])->group(function () {

    /* Báo tôi khi có hàng lại. */
    Route::post('san-pham/{product}/bao-hang-ve', [\App\Http\Controllers\Shop\StockAlertController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('shop.stock-alerts.store');

    Route::delete('san-pham/{product}/bao-hang-ve', [\App\Http\Controllers\Shop\StockAlertController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('shop.stock-alerts.destroy');
    Route::get('tin-nhan', [\App\Http\Controllers\Shop\ChatController::class, 'index'])->name('shop.chat.index');
    Route::get('tin-nhan/tin', [\App\Http\Controllers\Shop\ChatController::class, 'messages'])
        ->middleware('throttle:60,1')
        ->name('shop.chat.messages');
    Route::get('tin-nhan/chua-doc', [\App\Http\Controllers\Shop\ChatController::class, 'unread'])
        ->middleware('throttle:60,1')
        ->name('shop.chat.unread');
    Route::post('tin-nhan', [\App\Http\Controllers\Shop\ChatController::class, 'send'])
        ->middleware('throttle:20,1')
        ->name('shop.chat.send');

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

require __DIR__.'/admin.php';
