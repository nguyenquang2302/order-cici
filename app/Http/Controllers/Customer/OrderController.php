<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'total' => (float) $order->total,

                // Thông tin nạp tiền.
                'topup_amount' => (float) ($order->topup_amount ?? 0),
                'topup_status' => $order->topup_status ?? 'not_required',
                'topup_completed_at' => $order->topup_completed_at ?? null,

                // QR tính theo tổng tiền gồm cả tiền nạp.
                'qr_url' => !empty($order->payment_reference)
                    ? $this->createQrUrl(
                        (float) $order->total,
                        $order->payment_reference
                    )
                    : null,

                'items' => $items,
            ];
        })->values();

        /*
         * Chỉ hiển thị QR gộp nếu nhóm đó vẫn chứa đúng các đơn
         * đang chờ thanh toán hiện tại.
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
         * Kênh realtime riêng theo từng máy.
         */
        $channelId = $pc?->id
            ? (string) $pc->id
            : 'dev';

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

            $orders = $ordersQuery
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orders->count() < 2) {
                throw ValidationException::withMessages([
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

                /*
                 * order.total đã bao gồm tiền món + tiền nạp.
                 * Vì vậy QR gộp cũng thanh toán đủ cả hai khoản.
                 */
                $remainingCents =
                    (int) round((float) $order->total * 100)
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
                throw ValidationException::withMessages([
                    'group_payment' => 'Không đủ đơn hợp lệ để tạo thanh toán gộp.',
                ]);
            }

            /*
             * Mã nhóm dùng định dạng CICI + 12 số
             * để Node MB hiện tại nhận diện được.
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
                'Đã tạo QR thanh toán gộp ' .
                number_format((float) $group->total, 0, ',', '.') .
                'đ.'
            );
    }

    /**
     * Tạo đơn hàng và/hoặc đơn nạp tiền.
     *
     * Có thể:
     * - Chỉ đặt món.
     * - Chỉ nạp tiền.
     * - Vừa đặt món vừa nạp tiền.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
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
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.quantity.min' => 'Số lượng sản phẩm phải lớn hơn 0.',
            'topup_amount.integer' => 'Số tiền nạp phải là số nguyên.',
            'topup_amount.min' => 'Số tiền nạp không được âm.',
        ]);

        $items = $validated['items'] ?? [];
        $topupAmount = (int) ($validated['topup_amount'] ?? 0);

        /*
         * Không cho tạo đơn rỗng.
         */
        if (count($items) === 0 && $topupAmount <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Vui lòng chọn món hoặc nhập số tiền muốn nạp.',
            ]);
        }

        $pc = $request->attributes->get('current_pc');

        $order = DB::transaction(function () use (
            $items,
            $topupAmount,
            $validated,
            $pc
        ) {
            $subtotal = 0;
            $orderItems = [];

            /*
             * Kiểm tra tồn kho và tính tiền sản phẩm.
             */
            foreach ($items as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'order' => 'Một sản phẩm không còn kinh doanh.',
                    ]);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'order' => 'Sản phẩm "' .
                            $product->name .
                            '" không đủ số lượng trong kho.',
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

            /*
             * Tổng thanh toán = tiền sản phẩm + tiền nạp.
             *
             * Lưu ý:
             * subtotal chỉ chứa tiền sản phẩm.
             * topup_amount là tiền nạp riêng.
             * total là tổng tiền khách phải chuyển khoản.
             */
            $total = $subtotal + $topupAmount;

            $orderNumber = 'CICI-' .
                now()->format('YmdHis') .
                '-' .
                strtoupper(Str::random(4));

            $paymentReference = 'CICI' . str_pad(
                (string) random_int(0, 999999999999),
                12,
                '0',
                STR_PAD_LEFT
            );

            $now = now();

            /*
             * Lưu đơn hàng.
             */
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

                // Các cột nạp tiền đã có trong database.
                'topup_amount' => $topupAmount,
                'topup_status' => $topupAmount > 0
                    ? 'pending'
                    : 'not_required',
                'topup_completed_at' => null,
                'topup_completed_by' => null,

                // Tổng tiền QR gồm tiền món + tiền nạp.
                'total' => $total,

                'note' => $validated['note'] ?? null,

                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
             * Chỉ insert order_items khi khách có đặt sản phẩm.
             * Đơn chỉ nạp tiền sẽ không có dòng order_items.
             */
            if (count($orderItems) > 0) {
                foreach ($orderItems as &$item) {
                    $item['order_id'] = $orderId;
                }

                unset($item);

                DB::table('order_items')->insert($orderItems);
            }

            /*
             * Tạo khoản thanh toán theo tổng tiền thực tế.
             */
            DB::table('payments')->insert([
                'order_id' => $orderId,
                'received_by' => null,
                'amount' => $total,
                'method' => 'mb_qr',
                'status' => 'pending',
                'transaction_code' => null,
                'paid_at' => null,
                'note' => 'Chờ thanh toán qua QR',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('orders')
                ->where('id', $orderId)
                ->first();
        });

        /*
         * Phát sự kiện realtime báo admin có đơn hàng mới.
         * Đặt sau transaction để đảm bảo đơn đã được lưu.
         */
        try {
            event(new \App\Events\OrderCreated(
                (int) $order->id,
                (string) $order->order_number,
                $order->pc_id !== null
                    ? (int) $order->pc_id
                    : null
            ));
        } catch (\Throwable $e) {
            Log::warning('Không thể phát sự kiện OrderCreated', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        /*
         * Tạo QR theo tổng tiền món + tiền nạp.
         */
        $order->qr_url = $this->createQrUrl(
            (float) $order->total,
            (string) $order->payment_reference
        );

        /*
         * Gửi Telegram.
         */
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
    private function createQrUrl(
        float $amount,
        string $reference
    ): ?string {
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
    private function sendTelegramNotification(
        $order,
        $pc = null
    ): void {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            return;
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $esc = fn ($value) => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $money = fn ($value) => number_format(
            (float) $value,
            0,
            ',',
            '.'
        ) . 'đ';

        $pcName = $pc->name ?? 'Khách tại quầy';
        $pcCode = $pc->code ?? null;

        $topupAmount = (float) ($order->topup_amount ?? 0);

        /*
         * Tiêu đề.
         */
        $message = "🛒 <b>CICI GAMING</b>\n";
        $message .= "🔔 <b>CÓ ĐƠN HÀNG MỚI</b>\n\n";

        /*
         * STT và mã đơn.
         */
        $message .= "🔢 <b>STT đơn:</b> #" .
            (int) $order->id . "\n";

        $message .= "🧾 <b>Mã đơn:</b>\n<code>" .
            $esc($order->order_number) .
            "</code>\n\n";

        /*
         * Thông tin máy.
         */
        $message .= "🖥 <b>THÔNG TIN MÁY</b>\n";

        if ($pcCode) {
            $message .= "Tên máy: <b>" .
                $esc($pcName) .
                "</b>\n";

            $message .= "Mã máy: <code>" .
                $esc($pcCode) .
                "</code>\n";
        } else {
            $message .= "Địa điểm: <b>" .
                $esc($pcName) .
                "</b>\n";
        }

        $message .= "\n";

        /*
         * Danh sách món.
         */
        if ($items->count() > 0) {
            $message .= "🍽 <b>DANH SÁCH MÓN</b>\n";

            foreach ($items as $index => $item) {
                $message .= "\n<b>" .
                    ($index + 1) .
                    ". " .
                    $esc($item->product_name) .
                    "</b>\n";

                $message .= "   SL: <b>x" .
                    (int) $item->quantity .
                    "</b>";

                $message .= "  |  Giá: " .
                    $money($item->unit_price) .
                    "\n";

                $message .= "   Thành tiền: <b>" .
                    $money($item->subtotal) .
                    "</b>\n";
            }
        }

        /*
         * Thông tin nạp tiền.
         */
        if ($topupAmount > 0) {
            $message .= "\n🎮 <b>NẠP TIỀN TÀI KHOẢN GAME</b>\n";
            $message .= "Số tiền nạp: <b>" .
                $money($topupAmount) .
                "</b>\n";

            $message .= "Trạng thái: <b>Chờ nhân viên nạp</b>\n";
        }

        /*
         * Tổng tiền.
         */
        $message .= "\n💰 <b>TỔNG THANH TOÁN: " .
            $money($order->total) .
            "</b>\n";

        /*
         * Thanh toán và thời gian.
         */
        $message .= "\n💳 Mã thanh toán:\n<code>" .
            $esc($order->payment_reference) .
            "</code>\n";

        $message .= "🕒 Thời gian: <b>" .
            now('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') .
            "</b>\n";

        if ($order->note) {
            $message .= "\n📝 <b>GHI CHÚ</b>\n";
            $message .= $esc($order->note) . "\n";
        }

        $message .= "\n<i>CICI GAMING</i>";

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]
            );

            if (!$response->successful()) {
                Log::warning(
                    'Telegram gửi thông báo đơn hàng thất bại',
                    [
                        'order_id' => $order->id,
                        'response' => $response->body(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error(
                'Telegram gửi thông báo đơn hàng lỗi',
                [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}