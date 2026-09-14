<?php

namespace App\Services\Points;

/**
 * Không làm được việc với điểm (không đủ điểm, gói không có…).
 * Thông điệp đọc được cho khách.
 */
class PointException extends \RuntimeException
{
}
