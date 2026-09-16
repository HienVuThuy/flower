<?php

namespace App\Services\Cart;

use RuntimeException;

/** Lỗi nghiệp vụ của giỏ hàng — thông điệp viết cho KHÁCH đọc, nên controller có thể đưa thẳng ra flash message. */
class CartException extends RuntimeException
{
}
