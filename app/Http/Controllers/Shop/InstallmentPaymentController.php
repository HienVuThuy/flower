<?php

namespace App\Http\Controllers\Shop;

use App\Enums\MomoFlow;
use App\Http\Controllers\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Installment\InstallmentException;
use App\Services\Installment\InstallmentService;
use App\Services\Payment\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Khách trả kỳ trả góp kế tiếp qua MoMo. */
class InstallmentPaymentController extends Controller
{
    use AuthorizesOrderAccess;

    public function momo(Request $request, Order $order, InstallmentService $traGop): RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        $flow = MomoFlow::tryFrom((string) $request->query('cach', '')) ?? MomoFlow::macDinh();

        try {
            return redirect()->away($traGop->moMomo($order, $flow));
        } catch (InstallmentException|PaymentException $e) {
            return redirect()->route('shop.orders.show', $order)->with('error', $e->getMessage());
        }
    }
}
