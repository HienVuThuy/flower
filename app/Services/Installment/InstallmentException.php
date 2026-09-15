<?php

namespace App\Services\Installment;

use App\Services\Order\OrderException;

/**
 * Lỗi trả góp, với câu nói được cho người dùng.
 *
 * Kế thừa OrderException: ném ra giữa lúc tạo đơn thì mọi nơi đang bắt lỗi
 * đặt hàng vẫn bắt được, và transaction tạo đơn cuộn lại cả đơn lẫn kho.
 */
class InstallmentException extends OrderException
{
}
