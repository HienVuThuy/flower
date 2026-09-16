<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;

/** Hợp đồng chung của một cổng thanh toán trực tuyến. */
interface PaymentGateway
{
    public function createPayment(Order $order): string;

    public function verifySignature(array $payload): bool;

    public function isSuccessful(array $payload): bool;

    public function orderNumberFrom(array $payload): ?string;
}
