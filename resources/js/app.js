import 'bootstrap';
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
import { initVideoEmbed } from './components/video-embed';

/*
 * Header đổi trạng thái khi cuộn — glass chỉ bật lúc cần (accent),
 * không phải trạng thái mặc định của navigation.
 */
const header = document.querySelector('.site-header');

if (header) {
    const onScroll = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

/*
 * MỘT HÀM KHỞI TẠO, GỌI LẠI ĐƯỢC.
 *
 * Trước đây đây là một dãy lời gọi trần. Điều hướng quản trị nay thay
 * ruột trang bằng JavaScript (xem admin/nav.js), nên phần nội dung mới
 * cần được khởi tạo lại — mà muốn gọi lại thì phải có tên để gọi.
 *
 * MỌI HÀM TRONG DANH SÁCH NÀY PHẢI GỌI LẠI ĐƯỢC NHIỀU LẦN. Cái nào gắn
 * sự kiện lên phần tử thì phải tự đánh dấu phần tử đã gắn — gọi hai lần
 * mà gắn hai lần thì một cú bấm chạy hai lượt.
 */
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
    initVideoEmbed();
}

bootUi();

// Điều hướng quản trị: khởi tạo SAU bootUi() và nhận chính nó làm tham
// số, để mỗi lần thay ruột trang thì phần nội dung mới được dựng lại.
initAdminNav(bootUi);
