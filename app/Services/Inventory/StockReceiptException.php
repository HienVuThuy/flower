<?php

namespace App\Services\Inventory;

use RuntimeException;

/** Lỗi có câu nói sẵn cho người dùng, ném ra từ StockReceiptService. */
class StockReceiptException extends RuntimeException
{
}
