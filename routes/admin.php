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
use App\Http\Controllers\Admin\BusinessParamsController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\SupplierReturnController;
use App\Http\Controllers\Admin\FlowerKindController;
use App\Http\Controllers\Admin\FlowerLotController;
use App\Http\Controllers\Admin\OpeningStockController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

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

        Route::get('users/them', [UserController::class, 'create'])
            ->middleware('quyen:he-thong')
            ->name('users.create');

        Route::post('users', [UserController::class, 'store'])
            ->middleware(['quyen:he-thong', 'throttle:20,1'])
            ->name('users.store');

        Route::get('users/{user}/sua', [UserController::class, 'edit'])
            ->middleware('quyen:he-thong')
            ->name('users.edit');

        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware(['quyen:he-thong', 'throttle:30,1'])
            ->name('users.update');

        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware(['quyen:he-thong', 'throttle:10,1'])
            ->name('users.destroy');

        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('quyen:he-thong')
            ->name('users.show');

        Route::patch('users/{user}/vai-tro', [UserController::class, 'updateRole'])
            ->middleware('quyen:he-thong')
            ->name('users.role');

        Route::patch('users/{user}/khoa', [UserController::class, 'updateLock'])
            ->middleware('quyen:he-thong')
            ->name('users.lock');

        Route::prefix('tin-nhan')
            ->name('chat.')
            ->middleware('quyen:ho-tro')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\ChatController::class, 'users'])->middleware('throttle:120,1')->name('users');
                Route::get('/{user}', [\App\Http\Controllers\Admin\ChatController::class, 'messages'])->middleware('throttle:120,1')->name('messages');
                Route::post('/{user}', [\App\Http\Controllers\Admin\ChatController::class, 'send'])->middleware('throttle:30,1')->name('send');
            });

        Route::get('nhat-ky', [ActivityLogController::class, 'index'])
            ->middleware('quyen:he-thong')
            ->name('activity-logs.index');


        Route::get('settings', [SettingsController::class, 'edit'])
            ->middleware('quyen:he-thong')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])
            ->middleware('quyen:he-thong')->name('settings.update');

        Route::get('settings/tham-so', [BusinessParamsController::class, 'edit'])
            ->middleware('quyen:he-thong')->name('business-params.edit');
        Route::put('settings/tham-so', [BusinessParamsController::class, 'update'])
            ->middleware('quyen:he-thong')->name('business-params.update');
    });
