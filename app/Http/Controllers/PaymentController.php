<?php

namespace App\Http\Controllers;

use App\Events\OrderPaymentUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $id) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Không tìm thấy đơn hàng.');
            }

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment' => 'Không thể thanh toán đơn hàng đã hủy.',
                ]);
            }

            $paid = DB::table('payments')
                ->where('order_id', $id)
                ->where('status', 'completed')
                ->sum('amount');

            // Tính bằng đơn vị xu để tránh sai số số thực.
            $totalCents = (int) round((float) $order->total * 100);
            $paidCents = (int) round((float) $paid * 100);
            $amountCents = (int) round((float) $validated['amount'] * 100);

            $remainingCents = $totalCents - $paidCents;

            if ($remainingCents <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Đơn hàng đã được thanh toán đủ.',
                ]);
            }

            if ($amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền thu vượt quá số tiền còn phải thanh toán.',
                ]);
            }

            DB::table('payments')->insert([
                'order_id' => $id,
                'received_by' => auth()->id(),
                'amount' => $amountCents / 100,
                'method' => $validated['method'],
                'status' => 'completed',
                'transaction_code' => null,
                'paid_at' => now(),
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newPaidCents = $paidCents + $amountCents;

            $paymentStatus = $newPaidCents >= $totalCents
                ? 'paid'
                : 'partial';

            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'payment_status' => $paymentStatus,
                    'updated_at' => now(),
                ]);

            // Phát sự kiện sau khi dữ liệu thanh toán được commit.
            $pcId = $order->pc_id !== null
                ? (int) $order->pc_id
                : null;

            $channelId = $pcId !== null ? (string) $pcId : 'dev';
            $channelKey = $pcId !== null ? 'pc:' . $pcId : 'pc:dev';

            $channelToken = hash_hmac(
                'sha256',
                $channelKey,
                (string) config('app.key')
            );

            $channelName = "pc.{$channelId}.{$channelToken}";

            DB::afterCommit(function () use ($channelName, $id) {
                try {
                    event(new OrderPaymentUpdated(
                        $channelName,
                        [(int) $id]
                    ));
                } catch (\Throwable $e) {
                    Log::warning('CiCi admin payment realtime failed', [
                        'order_id' => $id,
                        'channel' => $channelName,
                        'message' => $e->getMessage(),
                    ]);
                }
            });
        });

        return back()->with('success', 'Đã ghi nhận thanh toán.');
    }
}
