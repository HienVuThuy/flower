<?php

namespace App\Services\Payment;

use RuntimeException;

/** Lỗi có câu nói sẵn cho người dùng, ném ra từ các lớp cổng thanh toán. */
class PaymentException extends RuntimeException
{
}
