<?php

namespace App\Services\Exchange;

use RuntimeException;

/**
 * Việc đổi hàng không làm được, và LÝ DO ĐỌC ĐƯỢC nằm trong thông báo.
 *
 * Mọi thông điệp ném ra từ ExchangeService đều viết cho người dùng cuối
 * đọc — controller in thẳng ra màn hình. Không có câu nào kiểu
 * "Constraint violation": người đứng ở quầy không sửa được cái đó.
 */
class ExchangeException extends RuntimeException
{
}
