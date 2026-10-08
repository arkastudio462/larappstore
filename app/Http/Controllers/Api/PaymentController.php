<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook server-to-server dari gerbang pembayaran.
 *
 * Rute ini dikecualikan dari CSRF (lihat `bootstrap/app.php`) karena tidak
 * membawa sesi browser; keasliannya diverifikasi lewat tanda tangan Midtrans.
 */
class PaymentController extends Controller
{
    public function notification(
        Request $request,
        PaymentGateway $gateway,
        OrderFulfillmentService $fulfillment,
    ): JsonResponse {
        $payload = $request->all();

        if (! $gateway->verifyNotification($payload)) {
            return response()->json(['message' => 'Tanda tangan notifikasi tidak valid.'], 403);
        }

        $order = Order::query()
            ->where('order_no', (string) $request->input('order_id'))
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $status = (string) $request->input('transaction_status');
        $fraud = (string) $request->input('fraud_status', 'accept');

        if ($this->isSettled($status, $fraud)) {
            if ($paymentType = $request->input('payment_type')) {
                $order->forceFill(['payment_method' => (string) $paymentType])->save();
            }

            $fulfillment->fulfill($order);
        } elseif (isset($this->failureStatuses()[$status])) {
            $this->markUnpaid($order, $status);
        }

        return response()->json(['message' => 'Notifikasi diproses.']);
    }

    private function isSettled(string $status, string $fraud): bool
    {
        if ($status === 'settlement') {
            return true;
        }

        return $status === 'capture' && $fraud === 'accept';
    }

    /**
     * @return array<string, string>
     */
    private function failureStatuses(): array
    {
        return [
            'deny' => Order::STATUS_FAILED,
            'cancel' => Order::STATUS_CANCELLED,
            'expire' => Order::STATUS_EXPIRED,
        ];
    }

    private function markUnpaid(Order $order, string $status): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->forceFill([
            'status' => $this->failureStatuses()[$status],
        ])->save();
    }
}
