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
