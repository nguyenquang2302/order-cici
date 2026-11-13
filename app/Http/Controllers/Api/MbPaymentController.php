<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class MbPaymentController extends Controller
{
    public function store(Request $request)
    {
        // 1. Xác thực API secret
        $secret = config('services.mb.payment_secret');
        $authorization = $request->bearerToken();

        if (
            empty($secret) ||
            empty($authorization) ||
            ! hash_equals((string) $secret, (string) $authorization)
        ) {
            return response()->json([
                'success' => false,
                'status' => 'unauthorized',
                'message' => 'Invalid API secret',
            ], 401);
        }

        // 2. Kiểm tra dữ liệu giao dịch
        $validated = $request->validate([
            'payment.transaction_id' => ['required', 'string', 'max:100'],
            'payment.amount' => ['required', 'numeric', 'gt:0'],
            'payment.content' => ['nullable', 'string', 'max:2000'],
            'payment.date' => ['nullable', 'string', 'max:100'],
            'payment.account_receiver' => ['nullable', 'string', 'max:100'],
            'payment.gate' => ['nullable', 'string', 'max:50'],
        ]);

        $data = $validated['payment'];

        $transactionCode = trim($data['transaction_id']);
        $content = trim($data['content'] ?? '');

        // Quy đổi thành đơn vị xu để tránh sai số float
        $amountCents = (int) round((float) $data['amount'] * 100);
        $amount = $amountCents / 100;

        // 3. Tìm mã đơn trong nội dung chuyển khoản
        $paymentReference = null;

        if (preg_match('/CICI([0-9]{12})/i', $content, $matches)) {
            $paymentReference = strtoupper($matches[0]);
        }

        try {
            return DB::transaction(function () use (
                $transactionCode,
                $content,
                $amount,
                $amountCents,
                $paymentReference,
                $data,
                $request
            ) {
                $now = now();

                // 4. Lưu giao dịch ngân hàng độc lập với payments
                DB::table('bank_transactions')->insert([
                    'transaction_code' => $transactionCode,
                    'amount' => $amount,
                    'content' => $content ?: null,
                    'payment_reference' => $paymentReference,
                    'transaction_date' => $data['date'] ?? null,
                    'account_receiver' => $data['account_receiver'] ?? null,
                    'gate' => $data['gate'] ?? null,
                    'direction' => 'incoming',
                    'matched_order_id' => null,
                    'reconciliation_status' => 'received',
                    'note' => null,
                    'raw_payload' => json_encode(
                        $request->input('payment'),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $bankTransaction = DB::table('bank_transactions')
                    ->where('transaction_code', $transactionCode)
                    ->lockForUpdate()
                    ->first();

                // 5. Không có mã CICI: vẫn lưu để đối soát
                if (!$paymentReference) {
                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'unmatched',
                            'note' => 'Không tìm thấy mã đơn CICI trong nội dung chuyển khoản.',
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'unmatched',
                        'message' => 'Transaction saved for manual reconciliation',
                    ]);
                }

                // 6. Tìm và khóa đơn hàng
                $order = DB::table('orders')
                    ->where('payment_reference', $paymentReference)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'unmatched',
                            'note' => 'Không tìm thấy đơn hàng tương ứng.',
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'unmatched',
                        'message' => 'Order not found. Transaction saved.',
                    ]);
                }

                // Đã tìm thấy đơn, lưu liên kết để đối soát
                DB::table('bank_transactions')
                    ->where('id', $bankTransaction->id)
                    ->update([
                        'matched_order_id' => $order->id,
                        'updated_at' => $now,
                    ]);

                // 7. Đơn đã hủy: không tự động ghi nhận thanh toán
                if ($order->status === 'cancelled') {
                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'cancelled_order',
                            'note' => 'Tiền chuyển vào sau khi đơn hàng đã bị hủy. Cần kiểm tra thủ công.',
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'cancelled_order',
                        'message' => 'Transaction saved for manual review',
                    ]);
                }

                // 8. Kiểm tra giao dịch đã từng được ghi vào payments chưa
                $existingPayment = DB::table('payments')
                    ->where('transaction_code', $transactionCode)
                    ->first();

                if ($existingPayment) {
                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'already_recorded',
                            'note' => 'Giao dịch đã tồn tại trong bảng payments.',
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'already_recorded',
                        'message' => 'Transaction was already recorded',
                    ]);
                }

                // 9. Tính số tiền đã thanh toán thành công
                $paid = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                $totalCents = (int) round((float) $order->total * 100);
                $paidCents = (int) round((float) $paid * 100);

                $remainingCents = $totalCents - $paidCents;

                // 10. Đơn đã thanh toán đủ bằng tiền mặt/chuyển khoản khác
                if (
                    $order->payment_status === 'paid' ||
                    $remainingCents <= 0
                ) {
                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'already_paid',
                            'note' => 'Đơn đã được thanh toán đủ trước khi giao dịch MB đến. Không cộng doanh thu lần hai.',
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'already_paid',
                        'message' => 'Transaction saved for reconciliation. No duplicate payment.',
                    ]);
                }

                // 11. Chỉ chấp nhận đúng số tiền còn thiếu
                if ($amountCents !== $remainingCents) {
                    $expected = $remainingCents / 100;

                    DB::table('bank_transactions')
                        ->where('id', $bankTransaction->id)
                        ->update([
                            'reconciliation_status' => 'mismatch',
                            'note' => 'Sai số tiền. Còn phải thanh toán: '
                                . $expected
                                . ' | Nhận được: '
                                . $amount,
                            'updated_at' => $now,
                        ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'mismatch',
                        'message' => 'Amount does not match remaining balance',
                        'expected' => $expected,
                        'received' => $amount,
                    ]);
                }

                // 12. Ghi nhận thanh toán hợp lệ
                DB::table('payments')->insert([
                    'order_id' => $order->id,
                    'received_by' => null,
                    'amount' => $amount,
                    'method' => 'mb_qr',
                    'status' => 'completed',
                    'transaction_code' => $transactionCode,
                    'paid_at' => $now,
                    'note' => 'MB Bank | ' . $content,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Nếu trước đó đã thu tiền mặt một phần thì đây là thanh toán hỗn hợp
                $paymentMethod = $paidCents > 0
                    ? 'mixed'
                    : 'mb_qr';

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'payment_status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'updated_at' => $now,
                    ]);

                // 13. Đánh dấu giao dịch đã đối soát thành công
                DB::table('bank_transactions')
                    ->where('id', $bankTransaction->id)
                    ->update([
                        'reconciliation_status' => 'paid',
                        'note' => 'Đã tự động ghép và xác nhận thanh toán đơn '
                            . $order->order_number,
                        'updated_at' => $now,
                    ]);

                Log::info('CiCi MB payment successful', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'transaction_code' => $transactionCode,
                    'amount' => $amount,
                ]);

                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment confirmed successfully',
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                ]);
            });
        } catch (QueryException $e) {
            // Giao dịch đã tồn tại: webhook được gửi lại
            $existing = DB::table('bank_transactions')
                ->where('transaction_code', $transactionCode)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'status' => 'duplicate',
                    'message' => 'Transaction already received',
                    'reconciliation_status' => $existing->reconciliation_status,
                ]);
            }

            Log::error('CiCi MB database error', [
                'transaction_code' => $transactionCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'database_error',
                'message' => 'Unable to save transaction',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('CiCi MB payment API error', [
                'transaction_code' => $transactionCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'server_error',
                'message' => 'Unable to process transaction',
            ], 500);
        }
    }
}
