<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Mã xác thực không dùng được, kèm lý do NÓI ĐƯỢC CHO KHÁCH.
 *
 * Thông điệp của ngoại lệ này được hiển thị thẳng lên trang, nên nó
 * phải là câu tiếng Việt bình thường — không phải chi tiết kỹ thuật, và
 * không phải thứ tiết lộ mã đúng là gì.
 */
class EmailVerificationException extends RuntimeException
{
}
