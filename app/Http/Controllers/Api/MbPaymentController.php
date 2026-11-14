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
        $amountCents = (int) round((float) $data['amount'] * 100);
        $amount = $amountCents / 100;

        // Mã QR đơn và QR gộp đều dùng định dạng CICI + 12 chữ số.
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

                // Lưu giao dịch ngân hàng độc lập với bảng payments.
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
                    'matched_payment_group_id' => null,
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

                // Không có mã CICI: vẫn lưu giao dịch để admin đối soát.
                if (!$paymentReference) {
                    $this->updateBankTransaction($bankTransaction->id, [
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

                /*
                 * 3. Ưu tiên tìm mã trong payment_groups.
                 * Nếu không tồn tại mới xử lý theo luồng thanh toán một đơn cũ.
                 */
                $paymentGroup = DB::table('payment_groups')
                    ->where('payment_reference', $paymentReference)
                    ->lockForUpdate()
                    ->first();

                if ($paymentGroup) {
                    return $this->processGroupPayment(
                        $paymentGroup,
                        $bankTransaction->id,
                        $transactionCode,
                        $content,
                        $amount,
                        $amountCents,
                        $now
                    );
                }

                // 4. Luồng thanh toán riêng từng đơn (giữ nguyên hành vi cũ).
                $order = DB::table('orders')
                    ->where('payment_reference', $paymentReference)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    $this->updateBankTransaction($bankTransaction->id, [
                        'reconciliation_status' => 'unmatched',
                        'note' => 'Không tìm thấy đơn hàng hoặc nhóm thanh toán tương ứng.',
                        'updated_at' => $now,
                    ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'unmatched',
                        'message' => 'Order not found. Transaction saved.',
                    ]);
                }

                $this->updateBankTransaction($bankTransaction->id, [
                    'matched_order_id' => $order->id,
                    'updated_at' => $now,
                ]);

                if ($order->status === 'cancelled') {
                    $this->updateBankTransaction($bankTransaction->id, [
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

                $existingPayment = DB::table('payments')
                    ->where('transaction_code', $transactionCode)
                    ->first();

                if ($existingPayment) {
                    $this->updateBankTransaction($bankTransaction->id, [
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

                $paid = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                $totalCents = (int) round((float) $order->total * 100);
                $paidCents = (int) round((float) $paid * 100);
                $remainingCents = $totalCents - $paidCents;

                if ($order->payment_status === 'paid' || $remainingCents <= 0) {
                    $this->updateBankTransaction($bankTransaction->id, [
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

                if ($amountCents !== $remainingCents) {
                    $expected = $remainingCents / 100;

                    $this->updateBankTransaction($bankTransaction->id, [
                        'reconciliation_status' => 'mismatch',
                        'note' => 'Sai số tiền. Còn phải thanh toán: ' . $expected . ' | Nhận được: ' . $amount,
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

                $paymentMethod = $paidCents > 0 ? 'mixed' : 'mb_qr';

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'payment_status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'updated_at' => $now,
                    ]);

                $this->updateBankTransaction($bankTransaction->id, [
                    'reconciliation_status' => 'paid',
                    'note' => 'Đã tự động ghép và xác nhận thanh toán đơn ' . $order->order_number,
                    'updated_at' => $now,
                ]);

                Log::info('CiCi MB payment successful', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'transaction_code' => $transactionCode,
                    'amount' => $amount,
                ]);

                // Chỉ phát realtime sau khi giao dịch DB đã commit thành công.
                $this->scheduleRealtimeUpdate(
                    $order->pc_id !== null ? (int) $order->pc_id : null,
                    [(int) $order->id]
                );

                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment confirmed successfully',
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                ]);
            });
        } catch (QueryException $e) {
            // Transaction_code là unique trong bank_transactions nên webhook lặp sẽ dừng tại đây.
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

    /**
     * Xử lý giao dịch theo mã QR gộp.
     * Chỉ tự động thanh toán khi đúng tổng tiền và tất cả đơn vẫn còn nguyên số dư.
     */
    private function processGroupPayment(
        object $group,
        int $bankTransactionId,
        string $transactionCode,
        string $content,
        float $amount,
        int $amountCents,
        $now
    ) {
        $this->updateBankTransaction($bankTransactionId, [
            'matched_payment_group_id' => $group->id,
            'updated_at' => $now,
        ]);

        if ($group->status === 'paid') {
            $this->updateBankTransaction($bankTransactionId, [
                'reconciliation_status' => 'already_paid',
                'note' => 'Nhóm đơn này đã được thanh toán trước đó. Giao dịch mới cần admin đối soát, không cộng doanh thu lần hai.',
                'updated_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'status' => 'already_paid',
                'message' => 'Payment group already paid. Transaction saved for manual reconciliation.',
            ]);
        }

        $allocations = DB::table('payment_group_orders')
            ->where('payment_group_id', $group->id)
            ->orderBy('order_id')
            ->get();

        if ($allocations->isEmpty()) {
            $this->markGroupMismatch($bankTransactionId, 'Nhóm thanh toán không có đơn hàng chi tiết.', $now);
            return response()->json([
                'success' => true,
                'status' => 'mismatch',
                'message' => 'Payment group has no order allocations. Manual review required.',
            ]);
        }

        $groupTotalCents = (int) round((float) $group->total * 100);
        $allocationTotalCents = (int) round((float) $allocations->sum('amount') * 100);

        if ($amountCents !== $groupTotalCents || $allocationTotalCents !== $groupTotalCents) {
            $this->markGroupMismatch(
                $bankTransactionId,
                'Sai tổng tiền QR gộp. Cần thanh toán: ' . ($groupTotalCents / 100)
                    . ' | Nhận được: ' . $amount . '. Admin cần kiểm tra thủ công.',
                $now
            );

            return response()->json([
                'success' => true,
                'status' => 'mismatch',
                'message' => 'Group amount does not match. Saved for manual reconciliation.',
                'expected' => $groupTotalCents / 100,
                'received' => $amount,
            ]);
        }

        // Khóa tất cả đơn trong nhóm theo thứ tự ID để tránh hai giao dịch cùng phân bổ một đơn.
        $orderIds = $allocations->pluck('order_id')->map(fn ($id) => (int) $id)->all();
        $orders = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Kiểm tra toàn bộ trước khi ghi bất kỳ khoản thanh toán nào.
        foreach ($allocations as $allocation) {
            $order = $orders->get($allocation->order_id);

            if (!$order || $order->status === 'cancelled' || $order->payment_status === 'paid') {
                $this->markGroupMismatch(
                    $bankTransactionId,
                    'Có đơn trong nhóm đã bị hủy, đã thanh toán hoặc không còn tồn tại. Không tự động phân bổ để tránh ghi nhận trùng. Admin cần kiểm tra thủ công.',
                    $now
                );

                return response()->json([
                    'success' => true,
                    'status' => 'mismatch',
                    'message' => 'One or more orders changed after group QR creation. Manual review required.',
                ]);
            }

            $paid = DB::table('payments')
                ->where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount');

            $remainingCents = (int) round((float) $order->total * 100)
                - (int) round((float) $paid * 100);
            $expectedAllocationCents = (int) round((float) $allocation->amount * 100);

            if ($remainingCents !== $expectedAllocationCents) {
                $this->markGroupMismatch(
                    $bankTransactionId,
                    'Số dư của đơn ' . $order->order_number . ' đã thay đổi sau khi tạo QR gộp. Không tự động phân bổ; admin cần kiểm tra thủ công.',
                    $now
                );

                return response()->json([
                    'success' => true,
                    'status' => 'mismatch',
                    'message' => 'Order balances changed after group QR creation. Manual review required.',
                ]);
            }
        }

        // Tất cả điều kiện hợp lệ: tạo một khoản phân bổ riêng cho mỗi đơn.
        // transaction_code để NULL ở các payment con vì mã giao dịch ngân hàng chỉ có một;
        // mã duy nhất được lưu ở bank_transactions và liên kết qua payment_group_id.
        foreach ($allocations as $allocation) {
            $order = $orders->get($allocation->order_id);

            $paidBefore = DB::table('payments')
                ->where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount');

            DB::table('payments')->insert([
                'order_id' => $order->id,
                'payment_group_id' => $group->id,
                'received_by' => null,
                'amount' => $allocation->amount,
                'method' => 'mb_qr',
                'status' => 'completed',
                'transaction_code' => null,
                'paid_at' => $now,
                'note' => 'Thanh toán QR gộp ' . $group->payment_reference . ' | MB transaction ' . $transactionCode . ' | ' . $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'payment_status' => 'paid',
                    'payment_method' => ((int) round((float) $paidBefore * 100)) > 0 ? 'mixed' : 'mb_qr',
                    'updated_at' => $now,
                ]);
        }

        DB::table('payment_groups')
            ->where('id', $group->id)
            ->update([
                'status' => 'paid',
                'updated_at' => $now,
            ]);

        $this->updateBankTransaction($bankTransactionId, [
            'reconciliation_status' => 'paid',
            'note' => 'Đã xác nhận thanh toán gộp ' . $group->payment_reference
                . ' cho ' . $allocations->count() . ' đơn. Mã giao dịch MB: ' . $transactionCode,
            'updated_at' => $now,
        ]);

        Log::info('CiCi MB group payment successful', [
            'payment_group_id' => $group->id,
            'payment_reference' => $group->payment_reference,
            'transaction_code' => $transactionCode,
            'amount' => $amount,
            'order_ids' => $orderIds,
        ]);

        // Một sự kiện cho cả nhóm; trang của đúng máy sẽ tải lại danh sách đơn.
        $this->scheduleRealtimeUpdate(
            $group->pc_id !== null ? (int) $group->pc_id : null,
            $orderIds,
            (int) $group->id
        );

        return response()->json([
            'success' => true,
            'status' => 'paid',
            'message' => 'Group payment confirmed successfully',
            'payment_reference' => $group->payment_reference,
            'orders_paid' => $allocations->count(),
            'amount' => $amount,
        ]);
    }

    private function markGroupMismatch(int $bankTransactionId, string $note, $now): void
    {
        $this->updateBankTransaction($bankTransactionId, [
            'reconciliation_status' => 'mismatch',
            'note' => $note,
            'updated_at' => $now,
        ]);
    }

    /**
     * Lên lịch phát sự kiện sau commit để lỗi Reverb không làm hỏng webhook MB.
     */
    private function scheduleRealtimeUpdate(?int $pcId, array $orderIds, ?int $paymentGroupId = null): void
    {
        $channelId = $pcId !== null ? (string) $pcId : 'dev';
        $channelKey = $pcId !== null ? 'pc:' . $pcId : 'pc:dev';
        $channelToken = hash_hmac('sha256', $channelKey, (string) config('app.key'));
        $channelName = "pc.{$channelId}.{$channelToken}";
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

        DB::afterCommit(function () use ($channelName, $orderIds, $paymentGroupId): void {
            try {
                event(new \App\Events\OrderPaymentUpdated(
                    $channelName,
                    $orderIds,
                    $paymentGroupId
                ));
            } catch (\Throwable $e) {
                // Thanh toán đã commit; lỗi broadcast chỉ ghi log, không trả webhook 500.
                Log::warning('CiCi payment realtime broadcast failed', [
                    'channel' => $channelName,
                    'order_ids' => $orderIds,
                    'payment_group_id' => $paymentGroupId,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    private function updateBankTransaction(int $id, array $values): void
    {
        DB::table('bank_transactions')->where('id', $id)->update($values);
    }
}