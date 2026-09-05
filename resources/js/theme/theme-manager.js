/**
 * ThemeManager — cầu nối giữa `data-theme` trên <html> và hiệu ứng
 * theo mùa (Layer 3).
 *
 * Hai điểm quan trọng:
 *
 * 1. NẠP ĐỘNG. Module hiệu ứng chỉ được tải khi theme tương ứng
 *    đang bật. Vite tách mỗi effect thành một chunk riêng, nên
 *    khách xem theme mặc định KHÔNG tải một dòng code tuyết/cánh
 *    hoa nào.
 *
 * 2. LIFECYCLE. Effect cũ luôn destroy() xong trước khi effect mới
 *    init(), kể cả khi admin bấm thử nhiều theme liên tiếp ở trang
 *    Cài đặt — không có hai hiệu ứng chạy song song, không rò rỉ
 *    animation frame.
 */

/*
 * Import động: đường dẫn phải tĩnh đủ để Vite phân tích được, nên
 * dùng bản đồ hàm thay vì ghép chuỗi `./effects/${name}.js`.
 */
const EFFECT_LOADERS = {
    noel: () => import('./effects/noel-effect.js'),
    tet: () => import('./effects/tet-effect.js'),
    valentine: () => import('./effects/valentine-effect.js'),
};

class ThemeManager {
    constructor() {
        this.currentEffect = null;
        this.currentTheme = null;

        // Tăng mỗi lần đổi theme. Vì nạp module là bất đồng bộ, cần
        // token này để bỏ qua kết quả của lần đổi đã cũ (tránh trường
        // hợp bấm nhanh noel → tet nhưng module noel về sau và bật lên).
        this.token = 0;
    }

    async applyTheme(theme, effectName = undefined) {
        if (theme === this.currentTheme) return;

        const myToken = ++this.token;

        if (this.currentEffect) {
            this.currentEffect.destroy();
            this.currentEffect = null;
        }

        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;

        // Không truyền effectName thì suy từ chính tên theme.
        const key = effectName === undefined ? theme : effectName;
        const loader = key ? EFFECT_LOADERS[key] : null;

        if (!loader) return;

        try {
            const module = await loader();

            // Người dùng đã đổi sang theme khác trong lúc chờ tải.
            if (myToken !== this.token) return;

            const EffectClass = Object.values(module)[0];
            this.currentEffect = new EffectClass();
            this.currentEffect.init();
            this.currentEffect.enable();
        } catch (error) {
            // Hiệu ứng chỉ là trang trí — tải hỏng thì bỏ qua,
            // tuyệt đối không để vỡ trang.
            console.warn('Không tải được hiệu ứng theme:', key, error);
        }
    }

    boot() {
        const root = document.documentElement;

        /*
         * Khu quản trị không dùng theme mùa vụ (layout gắn data-admin).
         * Không thoát sớm ở đây thì boot() sẽ tự gắn data-theme="default"
         * vào trang admin — thừa, và mở đường cho màu cửa hàng rò rỉ
         * ngược vào công cụ vận hành.
         */
        if (root.hasAttribute('data-admin')) {
            return;
        }

        const theme = root.getAttribute('data-theme') || 'default';
        const effect = root.getAttribute('data-theme-effect') || null;

        this.currentTheme = null;
        this.applyTheme(theme, effect);
    }
}

window.ThemeManager = new ThemeManager();
window.ThemeManager.boot();
