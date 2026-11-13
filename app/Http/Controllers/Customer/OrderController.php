<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            $category->products = $productsByCategory->get($category->id, collect());
        }

        /*
         * Lấy tất cả đơn chưa thanh toán của máy hiện tại.
         * Mỗi đơn có mã chuyển khoản và QR riêng.
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
                'total' => (float) $order->total,
                'qr_url' => $this->createQrUrl(
                    (float) $order->total,
                    $order->payment_reference
                ),
                'items' => $items,
            ];
        })->values();

        // Chỉ hiển thị QR gộp nếu nhóm đó vẫn chứa đúng các đơn đang chờ hiện tại.
        // Nếu khách đặt thêm đơn hoặc thanh toán riêng một đơn, QR cũ sẽ tự ẩn.
        $groupPayment = null;
        if ($pendingOrders->count() >= 2) {
            $pendingIds = $pendingOrders->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            $candidateGroup = DB::table('payment_groups')
                ->where('status', 'pending')
                ->when($pc, fn ($query) => $query->where('pc_id', $pc->id), fn ($query) => $query->whereNull('pc_id'))
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
        ]);
    }

    /**
     * Tạo một mã QR cho toàn bộ đơn chưa thanh toán của máy hiện tại.
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

            $orders = $ordersQuery->orderBy('id')->lockForUpdate()->get();

            if ($orders->count() < 2) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'group_payment' => 'Cần ít nhất 2 đơn chưa thanh toán để tạo QR gộp.',
                ]);
            }

            $allocations = [];
            $totalCents = 0;

            foreach ($orders as $order) {
                $paid = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                $remainingCents = (int) round((float) $order->total * 100)
                    - (int) round((float) $paid * 100);

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

            // Mã nhóm dùng cùng định dạng CICI + 12 số để Node MB hiện tại nhận diện được.
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

            return DB::table('payment_groups')->where('id', $groupId)->first();
        });

        return redirect()
            ->route('customer.order')
            ->with('success', 'Đã tạo QR thanh toán gộp ' . number_format((float) $group->total, 0, ',', '.') . 'đ.');
    }

    /**
     * Tạo đơn hàng.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Vui lòng chọn món trước khi gửi đơn.',
            'items.min' => 'Vui lòng chọn ít nhất một món.',
        ]);

        $pc = $request->attributes->get('current_pc');

        $order = DB::transaction(function () use ($validated, $pc) {
            // Cho phép tạo nhiều đơn độc lập trên cùng một máy.
            // Mỗi lần gửi đơn sẽ sinh order_number và payment_reference riêng.
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
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
                        'order' => 'Sản phẩm "' . $product->name . '" không đủ số lượng trong kho.',
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->selling_price;
                $lineTotal = $unitPrice * $quantity;

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

            $orderNumber = 'CICI-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

            $paymentReference = 'CICI' . str_pad(
                (string) random_int(0, 999999999999),
                12,
                '0',
                STR_PAD_LEFT
            );

            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $orderNumber,
                'pc_id' => $pc->id ?? null,
                'created_by' => null,
                'customer_name' => null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_reference' => $paymentReference,
                'payment_method' => 'mb_qr',
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($orderItems as &$item) {
                $item['order_id'] = $orderId;
            }
            unset($item);

            DB::table('order_items')->insert($orderItems);

            DB::table('payments')->insert([
                'order_id' => $orderId,
                'received_by' => null,
                'amount' => $subtotal,
                'method' => 'mb_qr',
                'status' => 'pending',
                'transaction_code' => null,
                'paid_at' => null,
                'note' => 'Chờ thanh toán qua QR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('orders')
                ->where('id', $orderId)
                ->first();
        });

        $order->qr_url = $this->createQrUrl(
            (float) $order->total,
            $order->payment_reference
        );

        $this->sendTelegramNotification($order);

        /*
         * Chuyển về trang đặt món.
         * index() sẽ lấy lại đơn từ database và hiển thị QR.
         */
        return redirect()
            ->route('customer.order')
            ->with('success', 'Đã gửi đơn hàng ' . $order->order_number);
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
    private function sendTelegramNotification($order): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            return;
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get();

        $message = "🛒 ĐƠN HÀNG MỚI - CICI GAMING\n\n";
        $message .= "Mã đơn: {$order->order_number}\n";
        $message .= "Mã thanh toán: {$order->payment_reference}\n";
        $message .= "Tổng tiền: " . number_format((float) $order->total, 0, ',', '.') . "đ\n\n";
        $message .= "Danh sách món:\n";

        foreach ($items as $item) {
            $message .= "• {$item->product_name} x{$item->quantity}";
            $message .= " - " . number_format((float) $item->subtotal, 0, ',', '.') . "đ\n";
        }

        if ($order->note) {
            $message .= "\nGhi chú: {$order->note}";
        }

        try {
            Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}