<?php

namespace App\Enums;

/**
 * Danh sách hành vi được ghi nhận cho nền tảng Kinh tế số / gợi ý sản
 * phẩm (Guide §9 và §25).
 *
 * CẢ SÁU LOẠI ĐỀU ĐÃ CÓ NƠI GHI:
 *
 *   product_view   Shop\ProductController::show()
 *   category_view  Shop\CategoryController::show()
 *   search         Shop\ProductController::index()
 *   add_to_cart    Shop\CartController::store()
 *   wishlist       Shop\WishlistController::toggle()   — chỉ khi THÊM
 *   purchase       Shop\CheckoutController::place()
 *
 * Thêm một case mới ở đây mà không nối vào chỗ ghi thì enum đang hứa
 * một thứ mã nguồn không làm — trang Phân tích sẽ hiện một dòng luôn
 * bằng 0 mà không ai biết vì sao.
 */
enum UserEventType: string
{
    case ProductView = 'product_view';
    case CategoryView = 'category_view';
    case Search = 'search';
    case AddToCart = 'add_to_cart';

    /*
     * MUA NGAY LÀ MỘT SỰ KIỆN RIÊNG, không phải "thêm vào giỏ".
     *
     * Theo đúng thiết kế của hệ thống, "Mua ngay" KHÔNG đụng vào giỏ
     * hàng — món đó giữ riêng trong session. Ghi nó là AddToCart làm
     * phễu View → Cart → Purchase nói dối: một khách xem rồi bấm Mua
     * ngay rồi đặt hàng được đếm là đã qua bước giỏ hàng, dù họ chưa
     * bao giờ mở giỏ.
     *
     * Hậu quả cụ thể: tỷ lệ "xem → giỏ" bị thổi lên, tỷ lệ "giỏ → mua"
     * bị kéo xuống — cả hai đều lệch về hướng làm người đọc kết luận
     * sai về chỗ khách rơi rụng.
     */
    case BuyNow = 'buy_now';
    case Wishlist = 'wishlist';
    case Purchase = 'purchase';

    /** Tên tiếng Việt để hiển thị trong trang Phân tích. */
    public function label(): string
    {
        return match ($this) {
            self::ProductView => 'Xem sản phẩm',
            self::CategoryView => 'Xem danh mục',
            self::Search => 'Tìm kiếm',
            self::AddToCart => 'Thêm vào giỏ',
            self::BuyNow => 'Mua ngay',
            self::Wishlist => 'Thêm vào yêu thích',
            self::Purchase => 'Đặt hàng',
        };
    }
}
