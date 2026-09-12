<?php

namespace App\Services\Inventory;

use RuntimeException;

/**
 * Việc với lô hoa không làm được, và LÝ DO ĐỌC ĐƯỢC nằm trong thông báo.
 *
 * Mọi thông điệp ném ra từ FlowerLotService đều viết cho người đứng ở
 * quầy đọc — controller in thẳng ra màn hình.
 */
class FlowerLotException extends RuntimeException
{
}
