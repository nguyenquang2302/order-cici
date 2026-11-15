<?php

namespace App\Http\Controllers\Customer;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Hiển thị trang đặt món.
     */
    public function index(Request $request)
    {
        $pc = $request->attributes->get('current_pc');

        $categories = DB::table('categories')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        $products = DB::table('products')
            ->where('is_active', 1)
            ->where('stock_quantity', '>', 0)
            ->orderBy('name')
            ->get();

        $productsByCategory = $products->groupBy('category_id');

        foreach ($categories as $category) {
            $category->products = $productsByCategory->get(
                $category->id,
                collect()
            );
        }

        /*
         * Lấy tất cả đơn chưa thanh toán của máy hiện tại.
         */
        $orderQuery = DB::table('orders')
            ->where('payment_status', 'unpaid')
            ->where('status', '!=', 'cancelled');

        if ($pc) {
            $orderQuery->where('pc_id', $pc->id);
        } else {
            $orderQuery->whereNull('pc_id');
        }

        $orders = $orderQuery
            ->orderByDesc('id')
            ->get();

        $pendingOrders = $orders->map(function ($order) {
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'payment_reference' => $order->payment_reference,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'status' => $order->status,

                'subtotal' => (float) $order->subtotal,
                'topup_amount' => (float) ($order->topup_amount ?? 0),
                'total' => (float) $order->total,

                'qr_url' => $this->createQrUrl(
                    (float) $order->total,
                    $order->payment_reference
                ),

                'items' => $items,
            ];
        })->values();

        /*
         * Chỉ hiển thị QR gộp nếu nhóm đó vẫn chứa đúng
         * các đơn đang chờ hiện tại.
         */
        $groupPayment = null;

        if ($pendingOrders->count() >= 2) {
            $pendingIds = $pendingOrders
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $candidateGroup = DB::table('payment_groups')
                ->where('status', 'pending')
                ->when(
                    $pc,
                    fn ($query) => $query->where('pc_id', $pc->id),
                    fn ($query) => $query->whereNull('pc_id')
                )
                ->orderByDesc('id')
                ->first();

            if ($candidateGroup) {
                $groupOrderIds = DB::table('payment_group_orders')
                    ->where('payment_group_id', $candidateGroup->id)
                    ->pluck('order_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                if ($groupOrderIds === $pendingIds) {
                    $groupPayment = [
                        'id' => $candidateGroup->id,
                        'payment_reference' => $candidateGroup->payment_reference,
                        'total' => (float) $candidateGroup->total,
                        'order_count' => count($groupOrderIds),

                        'qr_url' => $this->createQrUrl(
                            (float) $candidateGroup->total,
                            $candidateGroup->payment_reference
                        ),
                    ];
                }
            }
        }

        /*
         * Kênh realtime riêng theo từng máy khách.
         */
        $channelId = $pc?->id ? (string) $pc->id : 'dev';

        $channelKey = $pc?->id
            ? 'pc:' . $pc->id
            : 'pc:dev';

        $channelToken = hash_hmac(
            'sha256',
            $channelKey,
            (string) config('app.key')
        );

        $realtimeChannel = "pc.{$channelId}.{$channelToken}";

        return Inertia::render('Customer/Order', [
            'pc' => $pc ? [
                'id' => $pc->id,
                'code' => $pc->code,
                'name' => $pc->name,
            ] : [
                'id' => null,
                'code' => 'DEV',
                'name' => 'Máy thử nghiệm',
            ],

            'categories' => $categories,
            'pendingOrders' => $pendingOrders,
            'groupPayment' => $groupPayment,
            'realtimeChannel' => $realtimeChannel,
        ]);
    }

    /**
     * Tạo một mã QR cho toàn bộ đơn chưa thanh toán.
     */
    public function createGroupPayment(Request $request)
    {
        $pc = $request->attributes->get('current_pc');

        $group = DB::transaction(function () use ($pc) {
            $ordersQuery = DB::table('orders')
                ->where('payment_status', 'unpaid')
                ->where('status', '!=', 'cancelled');

            if ($pc) {
                $ordersQuery->where('pc_id', $pc->id);
            } else {
                $ordersQuery->whereNull('pc_id');
            }

            $orders = $ordersQuery
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orders->count() < 2) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'group_payment' => 'Cần ít nhất 2 đơn chưa thanh toán để tạo QR gộp.',
                ]);
            }

            $allocations = [];
            $totalCents = 0;

            foreach ($orders as $order) {
                /*
                 * Tiền đã thanh toán của đơn.
                 */
                $paid = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                /*
                 * order.total đã bao gồm tiền sản phẩm + tiền nạp.
                 */
                $remainingCents = (int) round(
                    (float) $order->total * 100
                ) - (int) round(
                    (float) $paid * 100
                );

                if ($remainingCents <= 0) {
                    continue;
                }

                $allocations[] = [
                    'order_id' => $order->id,
                    'amount' => $remainingCents / 100,
                ];

                $totalCents += $remainingCents;
            }

            if (count($allocations) < 2 || $totalCents <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'group_payment' => 'Không đủ đơn hợp lệ để tạo thanh toán gộp.',
                ]);
            }

            /*
             * Mã nhóm CICI + 12 số.
             */
            do {
                $reference = 'CICI' . str_pad(
                    (string) random_int(0, 999999999999),
                    12,
                    '0',
                    STR_PAD_LEFT
                );

                $existsInOrders = DB::table('orders')
                    ->where('payment_reference', $reference)
                    ->exists();

                $existsInGroups = DB::table('payment_groups')
                    ->where('payment_reference', $reference)
                    ->exists();

            } while ($existsInOrders || $existsInGroups);

            $now = now();

            $groupId = DB::table('payment_groups')->insertGetId([
                'payment_reference' => $reference,
                'pc_id' => $pc->id ?? null,
                'total' => $totalCents / 100,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rows = [];

            foreach ($allocations as $allocation) {
                $rows[] = [
                    'payment_group_id' => $groupId,
                    'order_id' => $allocation['order_id'],
                    'amount' => $allocation['amount'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('payment_group_orders')->insert($rows);

            return DB::table('payment_groups')
                ->where('id', $groupId)
                ->first();
        });

        return redirect()
            ->route('customer.order')
            ->with(
                'success',
                'Đã tạo QR thanh toán gộp '
                . number_format((float) $group->total, 0, ',', '.')
                . 'đ.'
            );
    }

    /**
     * Tạo đơn hàng.
     *
     * Có thể:
     * - Chỉ gọi món.
     * - Chỉ nạp tiền.
     * - Vừa gọi món vừa nạp tiền.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => [
                'nullable',
                'array',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'topup_amount' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'customer_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'items.array' => 'Danh sách sản phẩm không hợp lệ.',
            'items.*.product_id.required' => 'Thiếu thông tin sản phẩm.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.quantity.required' => 'Vui lòng nhập số lượng.',
            'items.*.quantity.min' => 'Số lượng sản phẩm phải lớn hơn 0.',

            'topup_amount.integer' => 'Số tiền nạp phải là số nguyên.',
            'topup_amount.min' => 'Số tiền nạp không được âm.',

            'customer_name.max' => 'Tên khách hàng không được quá 100 ký tự.',
        ]);

        $pc = $request->attributes->get('current_pc');

        $order = DB::transaction(function () use ($validated, $pc) {
            $subtotal = 0;
            $orderItems = [];

            $items = $validated['items'] ?? [];

            /*
             * Tiền nạp.
             */
            $topupAmount = (int) ($validated['topup_amount'] ?? 0);

            /*
             * Phải có ít nhất sản phẩm hoặc tiền nạp.
             */
            if (empty($items) && $topupAmount <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => 'Vui lòng chọn món hoặc nhập số tiền muốn nạp.',
                ]);
            }

            /*
             * Kiểm tra sản phẩm và tồn kho.
             */
            foreach ($items as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'order' => 'Một sản phẩm không còn kinh doanh.',
                    ]);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'order' => 'Sản phẩm "'
                            . $product->name
                            . '" không đủ số lượng trong kho.',
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->selling_price;
                $lineTotal = $unitPrice * $quantity;

                /*
                 * Subtotal chỉ tính tiền sản phẩm.
                 */
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost_price' => (float) $product->cost_price,
                    'subtotal' => $lineTotal,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            /*
             * Tổng thanh toán bao gồm cả tiền nạp.
             */
            $total = $subtotal + $topupAmount;

            $orderNumber = 'CICI-'
                . now()->format('YmdHis')
                . '-'
                . strtoupper(Str::random(4));

            /*
             * Sinh mã thanh toán không trùng.
             */
            do {
                $paymentReference = 'CICI' . str_pad(
                    (string) random_int(0, 999999999999),
                    12,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = DB::table('orders')
                    ->where('payment_reference', $paymentReference)
                    ->exists();

                $groupExists = DB::table('payment_groups')
                    ->where('payment_reference', $paymentReference)
                    ->exists();

            } while ($exists || $groupExists);

            $customerName = isset($validated['customer_name'])
                ? trim($validated['customer_name'])
                : null;

            /*
             * Lưu đơn hàng.
             *
             * subtotal     = tiền sản phẩm.
             * topup_amount = tiền nạp.
             * total        = subtotal + topup_amount.
             */
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $orderNumber,
                'pc_id' => $pc->id ?? null,
                'created_by' => null,
                'customer_name' => $customerName ?: null,

                'status' => 'pending',
                'payment_status' => 'unpaid',

                'payment_reference' => $paymentReference,
                'payment_method' => 'mb_qr',

                'subtotal' => $subtotal,
                'discount' => 0,

                'topup_amount' => $topupAmount,
                'total' => $total,

                'note' => $validated['note'] ?? null,

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * Lưu chi tiết sản phẩm.
             * Nếu chỉ nạp tiền thì không có order_items.
             */
            if (!empty($orderItems)) {
                foreach ($orderItems as &$item) {
                    $item['order_id'] = $orderId;
                }

                unset($item);

                DB::table('order_items')->insert($orderItems);
            }

            /*
             * Tạo payment theo tổng tiền phải thanh toán.
             */
            DB::table('payments')->insert([
                'order_id' => $orderId,
                'received_by' => null,
                'amount' => $total,
                'method' => 'mb_qr',
                'status' => 'pending',
                'transaction_code' => null,
                'paid_at' => null,

                'note' => $topupAmount > 0
                    ? 'Chờ thanh toán QR - Có tiền nạp tài khoản'
                    : 'Chờ thanh toán qua QR',

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('orders')
                ->where('id', $orderId)
                ->first();
        });

        /*
         * Transaction đã commit thành công.
         * Phát sự kiện để Admin nhận biết đơn hàng mới.
         */
        try {
            event(new OrderCreated(
                (int) $order->id,
                (string) $order->order_number,
                $order->pc_id !== null
                    ? (int) $order->pc_id
                    : null
            ));
        } catch (\Throwable $e) {
            Log::warning('CiCi order created realtime failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        $order->qr_url = $this->createQrUrl(
            (float) $order->total,
            $order->payment_reference
        );

        $this->sendTelegramNotification($order, $pc);

        return redirect()
            ->route('customer.order')
            ->with(
                'success',
                'Đã gửi đơn hàng ' . $order->order_number
            );
    }

    /**
     * Tạo URL QR VietQR.
     */
    private function createQrUrl(float $amount, string $reference): ?string
    {
        $bankBin = config('services.mb.bank_bin');
        $accountNumber = config('services.mb.account_number');
        $accountName = config('services.mb.account_name');

        if (!$bankBin || !$accountNumber) {
            return null;
        }

        $query = http_build_query([
            'amount' => (int) round($amount),
            'addInfo' => $reference,
            'accountName' => $accountName,
        ]);

        return "https://img.vietqr.io/image/{$bankBin}-{$accountNumber}-compact2.png?{$query}";
    }

    /**
     * Gửi thông báo Telegram.
     */
    private function sendTelegramNotification($order, $pc = null): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            return;
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $topupAmount = (float) ($order->topup_amount ?? 0);
        $subtotal = (float) $order->subtotal;

        $message = "🛒 ĐƠN HÀNG MỚI - CICI GAMING\n\n";

        /*
         * Thông tin máy khách.
         */
        $pcName = $pc?->name ?: 'Không xác định';
        $pcCode = $pc?->code;

        $message .= "🖥 Máy: {$pcName}";

        if ($pcCode) {
            $message .= " ({$pcCode})";
        }

        $message .= "\n";

        /*
         * Thông tin khách hàng.
         */
        $message .= "👤 Khách hàng: "
            . ($order->customer_name ?: 'Chưa cung cấp')
            . "\n\n";

        $message .= "🧾 Mã đơn: {$order->order_number}\n";
        $message .= "💳 Mã thanh toán: {$order->payment_reference}\n\n";

        /*
         * Tiền sản phẩm.
         */
        $message .= "📦 DANH SÁCH MÓN:\n";

        if ($items->isEmpty()) {
            $message .= "• Không có sản phẩm\n";
        } else {
            foreach ($items as $item) {
                $message .= "• {$item->product_name} x{$item->quantity}";

                $message .= " - "
                    . number_format((float) $item->subtotal, 0, ',', '.')
                    . "đ\n";
            }
        }

        $message .= "\n";

        $message .= "🛍 Tiền sản phẩm: "
            . number_format($subtotal, 0, ',', '.')
            . "đ\n";

        /*
         * Tiền nạp được tách riêng.
         * Nhân viên thực hiện nạp thủ công sau khi xác nhận.
         */
        if ($topupAmount > 0) {
            $message .= "\n";
            $message .= "💳 NẠP TIỀN TÀI KHOẢN\n";

            $message .= "🖥 Máy nhận tiền: "
                . ($pcCode ?: 'Không xác định')
                . "\n";

            $message .= "💰 Số tiền nạp: "
                . number_format($topupAmount, 0, ',', '.')
                . "đ\n";

            $message .= "⚠️ Nhân viên cần xác nhận và nạp thủ công.\n";
        }

        $message .= "\n💵 TỔNG THANH TOÁN: "
            . number_format((float) $order->total, 0, ',', '.')
            . "đ\n";

        if ($order->note) {
            $message .= "\n📝 Ghi chú: {$order->note}";
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]
            );

            if (!$response->successful()) {
                Log::warning('CiCi Telegram notification failed', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CiCi Telegram notification exception', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}