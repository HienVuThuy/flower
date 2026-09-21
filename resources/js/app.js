/* Chỉ ba thành phần Bootstrap được dùng thật (data-bs-toggle: modal, collapse, offcanvas).
   Nạp cả bộ tốn thêm ~60KB cho mọi trang. */
import Modal from 'bootstrap/js/dist/modal';
import Collapse from 'bootstrap/js/dist/collapse';
import Offcanvas from 'bootstrap/js/dist/offcanvas';

window.bootstrap = { Modal, Collapse, Offcanvas };
import './product-variants';
import './theme/theme-manager';
import { initProductDetail } from './components/product-detail';
import { initHeroCarousel } from './components/hero-carousel';
import { initCareProfile } from './admin/care-profile';
import { initPasswordToggles } from './password-toggle';
import { initBannerRotator } from './components/banner-rotator';
import { initAccountMenu } from './account-menu';
import { initSearchSuggest } from './search-suggest';
import { initFlash } from './flash';
import { initBoardingQuote } from './boarding-quote';
import { initAnnouncement } from './announcement';
import { initCartLive } from './cart-live';
import { initAddToCart } from './add-to-cart';
import { initCopyButtons } from './copy-button';
import { initOtpResend } from './otp-resend';
import { initBulkActions } from './admin/bulk-actions';
import { initSchemeToggle } from './scheme-toggle';
import { initVariantDialog } from './variant-dialog';
import { initGhnAddress } from './ghn-address';
import { initWishlist } from './wishlist';
import { initJournalForm } from './journal-form';
import { initAdminNav } from './admin/nav';
import { initExportPicker } from './admin/export-picker';
import { initReceiptLines } from './admin/receipt-lines';
import { initStockLock } from './admin/stock-lock';
import { initChonKy } from './admin/chon-ky';
import { initTuoiSoLieu } from './admin/tuoi-so-lieu';
import { initProductBlocks } from './admin/product-blocks';
import { initGiftVariantPicker } from './admin/gift-variant-picker';
import { initRefundGiftAutofill } from './admin/refund-gift-autofill';
import { initAiChat } from './ai-chat';
import { initCommunity } from './community';
import { initVideoEmbed } from './components/video-embed';
import { initLiveChat } from './live-chat';
import { initAdminLiveChat } from './admin/live-chat';
import { initPromotionForm } from './admin/promotion-form';
import { initPromotionProducts } from './admin/promotion-products';
import { initQuickBuy } from './components/quick-buy';
import { initFilterLoading } from './components/filter-loading';

const header = document.querySelector('.site-header');

if (header) {
    const onScroll = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

export function bootUi() {
    initProductDetail();
    initHeroCarousel();
    initCareProfile();
    initPasswordToggles();
    initBannerRotator();
    initAccountMenu();
    initSearchSuggest();
    initFlash();
    initAnnouncement();
    initCartLive();
    initAddToCart();
    initCopyButtons();
    initOtpResend();
    initBulkActions();
    initSchemeToggle();
    initVariantDialog();
    initGhnAddress();
    initWishlist();
    initJournalForm();
    initExportPicker();
    initReceiptLines();
    initStockLock();
    initChonKy();
    initTuoiSoLieu();
    initProductBlocks();
    initGiftVariantPicker();
    initRefundGiftAutofill();
    initAiChat();
    initCommunity();
    initVideoEmbed();
    initLiveChat();
    initAdminLiveChat();
    initPromotionForm();
    initPromotionProducts();
    initQuickBuy();
    initFilterLoading();
    initBoardingQuote();
}

bootUi();

initAdminNav(bootUi);
