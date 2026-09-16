<?php

namespace App\Services\Auth;

use RuntimeException;

/** Mã xác thực không dùng được, kèm lý do NÓI ĐƯỢC CHO KHÁCH. */
class EmailVerificationException extends RuntimeException
{
}
