<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Danh sách đơn hàng.
     */
    public function index(Request $request): Response
    {
        $orders = DB::table('orders')
            ->leftJoin('pcs', 'orders.pc_id', '=', 'pcs.id')
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.pc_id',
                'orders.customer_name',
                'orders.status',
                'orders.payment_status',
                'orders.subtotal',
                'orders.discount',
                'orders.total',
                'orders.note',
                'orders.created_at',
                'orders.completed_at',

                // Thông tin nạp tiền
                'orders.topup_amount',
                'orders.topup_status',
                'orders.topup_completed_at',
                'orders.topup_completed_by',

                'pcs.code as pc_code',
                'pcs.name as pc_name',
            ])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('orders.status', $request->status);
            })
            ->when($request->filled('payment_status'), function ($query) use ($request) {
                $query->where('orders.payment_status', $request->payment_status);

                if ($request->payment_status === 'unpaid') {
                    $query->where('orders.status', '!=', 'cancelled');
                }
            })
            ->orderByDesc('orders.created_at')
            ->paginate(20)
            ->withQueryString();

        $orderIds = $orders->getCollection()->pluck('id');

        // Chi tiết sản phẩm
        $items = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        // Lịch sử thanh toán
        $payments = DB::table('payments')
            ->whereIn('order_id', $orderIds)
            ->where('status', 'completed')
            ->orderBy('paid_at')
            ->get()
            ->groupBy('order_id');

        // Lấy tên nhân viên đã xác nhận nạp tiền
        $topupStaffIds = $orders->getCollection()
            ->pluck('topup_completed_by')
            ->filter()
            ->unique()
            ->values();

        $topupStaff = DB::table('users')
            ->whereIn('id', $topupStaffIds)
            ->pluck('name', 'id');

        $orders->getCollection()->transform(function ($order) use (
            $items,
            $payments,
            $topupStaff
        ) {
            $order->items = $items->get($order->id, collect());
            $order->payments = $payments->get($order->id, collect());

            $order->paid_total = $order->payments->sum('amount');

            $order->remaining = max(
                0,
                (float) $order->total - (float) $order->paid_total
            );

            $order->topup_amount = $order->topup_amount ?? 0;

            $order->topup_completed_by_name = $order->topup_completed_by
                ? $topupStaff->get($order->topup_completed_by)
                : null;

            return $order;
        });

        // Tổng hợp món cần chuẩn bị
        $pendingItems = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('pcs', 'orders.pc_id', '=', 'pcs.id')
            ->where('orders.status', 'pending')
            ->select([
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT COALESCE(
                            NULLIF(TRIM(orders.customer_name), ''),
                            pcs.code,
                            'Chưa nhập tên khách'
                        )
                        ORDER BY orders.created_at
                        SEPARATOR ', '
                    ) as customer_names
                "),
            ])
            ->groupBy(
                'order_items.product_id',
                'order_items.product_name'
            )
            ->orderBy('order_items.product_name')
            ->get();

        $pendingOrderCount = DB::table('orders')
            ->where('status', 'pending')
            ->count();

        $products = DB::table('products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'selling_price',
                'stock_quantity',
            ]);

        // Danh sách máy
        $pcs = DB::table('pcs')
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
            ]);

        return Inertia::render('Orders/Index', [
            'pendingItems' => $pendingItems,
            'pendingOrderCount' => $pendingOrderCount,
            'orders' => $orders,
            'filters' => [
                'status' => $request->status,
                'payment_status' => $request->payment_status,
            ],
            'products' => $products,
            'pcs' => $pcs,
        ]);
    }

    /**
     * Nhân viên tạo đơn tại quầy.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pc_id' => ['nullable', 'integer', 'exists:pcs,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:999',
            ],

            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Vui lòng chọn ít nhất một sản phẩm.',
            'items.min' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.*.product_id.distinct' => 'Sản phẩm bị trùng trong danh sách.',
            'items.*.quantity.min' => 'Số lượng sản phẩm phải lớn hơn 0.',
        ]);

        DB::transaction(function () use ($validated) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'Có sản phẩm không còn kinh doanh.',
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->selling_price;
                $itemSubtotal = $unitPrice * $quantity;

                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost_price' => $product->cost_price,
                    'subtotal' => $itemSubtotal,
                    'note' => $item['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $orderNumber = 'QUAY-'
                . now()->format('YmdHis')
                . '-'
                . strtoupper(\Illuminate\Support\Str::random(4));

            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $orderNumber,

                'pc_id' => !empty($validated['pc_id'])
                    ? (int) $validated['pc_id']
                    : null,

                'created_by' => auth()->id(),

                'customer_name' => !empty(trim($validated['customer_name'] ?? ''))
                    ? trim($validated['customer_name'])
                    : null,

                'status' => 'pending',
                'payment_status' => 'unpaid',

                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,

                // Đơn tại quầy hiện tại không có khoản nạp
                'topup_amount' => 0,
                'topup_status' => 'not_required',
                'topup_completed_at' => null,
                'topup_completed_by' => null,

                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($orderItems as &$item) {
                $item['order_id'] = $orderId;
            }

            unset($item);

            DB::table('order_items')->insert($orderItems);
        });

        return redirect()
            ->route('orders.index')
            ->with('success', 'Đã tạo đơn hàng tại quầy thành công.');
    }

    /**
     * Xác nhận đơn và trừ kho đúng một lần.
     */
    public function confirm(int $id)
    {
        DB::transaction(function () use ($id) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Không tìm thấy đơn hàng.');
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'order' => 'Đơn hàng này đã được xử lý hoặc không còn ở trạng thái chờ xác nhận.',
                ]);
            }

            $items = DB::table('order_items')
                ->where('order_id', $id)
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'Đơn hàng không có sản phẩm.',
                ]);
            }

            foreach ($items as $item) {
                $product = DB::table('products')
                    ->where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'order' => "Sản phẩm {$item->product_name} không còn kinh doanh.",
                    ]);
                }

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'order' => "Sản phẩm {$item->product_name} không đủ tồn kho. Hiện còn {$product->stock_quantity}.",
                    ]);
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->decrement('stock_quantity', $item->quantity);

                DB::table('inventory_transactions')->insert([
                    'product_id' => $product->id,
                    'created_by' => auth()->id(),
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'unit_cost' => $product->cost_price,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => 'Xuất kho cho đơn hàng ' . $order->order_number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            $this->scheduleRealtimeUpdate(
                $order->pc_id !== null ? (int) $order->pc_id : null,
                (int) $order->id
            );
        });

        return back()->with('success', 'Đã xác nhận đơn hàng và cập nhật tồn kho.');
    }

    /**
     * Hoàn thành đơn hàng trực tiếp hoặc từ trạng thái đang xử lý.
     */
    /**
 * Hoàn thành đơn hàng trực tiếp hoặc từ trạng thái đang xử lý.
 *
 * Hỗ trợ:
 * - Đơn có sản phẩm.
 * - Đơn chỉ có yêu cầu nạp tiền.
 * - Đơn vừa có sản phẩm vừa có yêu cầu nạp tiền.
 *
 * Không tự động xác nhận đã cộng tiền vào tài khoản máy.
 */
    public function complete(int $id)
    {
        $hasProducts = false;

        DB::transaction(function () use ($id, &$hasProducts) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Không tìm thấy đơn hàng.');
            }

            if (!in_array($order->status, ['pending', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Đơn hàng đã hoàn thành hoặc đã bị hủy.',
                ]);
            }

            $topupAmount = (float) ($order->topup_amount ?? 0);

            // Chỉ xử lý kho khi đơn đang pending.
            if ($order->status === 'pending') {
                $items = DB::table('order_items')
                    ->where('order_id', $id)
                    ->get();

                $hasProducts = $items->isNotEmpty();

                // Không có sản phẩm và cũng không có khoản nạp.
                if (!$hasProducts && $topupAmount <= 0) {
                    throw ValidationException::withMessages([
                        'order' => 'Đơn hàng không có sản phẩm hoặc khoản nạp tiền.',
                    ]);
                }

                // Có sản phẩm thì mới kiểm tra và trừ kho.
                foreach ($items as $item) {
                    $product = DB::table('products')
                        ->where('id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || !$product->is_active) {
                        throw ValidationException::withMessages([
                            'order' => "Sản phẩm {$item->product_name} không còn kinh doanh.",
                        ]);
                    }

                    if ($product->stock_quantity < $item->quantity) {
                        throw ValidationException::withMessages([
                            'order' => "Sản phẩm {$item->product_name} không đủ tồn kho. Hiện còn {$product->stock_quantity}.",
                        ]);
                    }

                    DB::table('products')
                        ->where('id', $product->id)
                        ->decrement('stock_quantity', $item->quantity);

                    DB::table('inventory_transactions')->insert([
                        'product_id' => $product->id,
                        'created_by' => auth()->id(),
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'unit_cost' => $product->cost_price,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => 'Xuất kho hoàn thành trực tiếp đơn ' . $order->order_number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Hoàn thành đơn hàng.
            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->scheduleRealtimeUpdate(
                $order->pc_id !== null ? (int) $order->pc_id : null,
                (int) $order->id
            );
        });

        $message = $hasProducts
            ? 'Đơn hàng đã hoàn thành và tồn kho đã được cập nhật.'
            : 'Đơn hàng đã hoàn thành. Không phát sinh cập nhật tồn kho.';

        return back()->with('success', $message);
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancel(int $id)
    {
        DB::transaction(function () use ($id) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Không tìm thấy đơn hàng.');
            }

            if (!in_array($order->status, ['pending', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Không thể hủy đơn hàng đã hoàn thành hoặc đã hủy.',
                ]);
            }

            if ($order->status === 'processing') {
                $items = DB::table('order_items')
                    ->where('order_id', $id)
                    ->get();

                foreach ($items as $item) {
                    $product = DB::table('products')
                        ->where('id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw ValidationException::withMessages([
                            'order' => "Không tìm thấy sản phẩm {$item->product_name}.",
                        ]);
                    }

                    DB::table('products')
                        ->where('id', $product->id)
                        ->increment('stock_quantity', $item->quantity);

                    DB::table('inventory_transactions')->insert([
                        'product_id' => $product->id,
                        'created_by' => auth()->id(),
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'unit_cost' => $product->cost_price,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => 'Hoàn kho do hủy đơn ' . $order->order_number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('orders')->where('id', $id)->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            $this->scheduleRealtimeUpdate(
                $order->pc_id !== null ? (int) $order->pc_id : null,
                (int) $order->id
            );
        });

        return back()->with('success', 'Đã hủy đơn hàng và cập nhật tồn kho.');
    }

    /**
     * Xác nhận đã nạp tiền thủ công cho khách.
     *
     * Chỉ ghi nhận trạng thái, nhân viên và thời gian.
     * KHÔNG tự động cộng tiền vào tài khoản máy.
     */
    public function confirmTopup(int $id)
    {
        DB::transaction(function () use ($id) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Không tìm thấy đơn hàng.');
            }

            $topupAmount = (float) ($order->topup_amount ?? 0);

            if ($topupAmount <= 0) {
                throw ValidationException::withMessages([
                    'topup' => 'Đơn hàng này không có khoản nạp tiền.',
                ]);
            }

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'topup' => 'Không thể xác nhận nạp tiền cho đơn đã hủy.',
                ]);
            }

            if ($order->topup_status === 'completed') {
                throw ValidationException::withMessages([
                    'topup' => 'Khoản nạp này đã được xác nhận trước đó.',
                ]);
            }

            // Kiểm tra lại số tiền thực thu từ các giao dịch thành công
            $paidTotal = DB::table('payments')
                ->where('order_id', $id)
                ->where('status', 'completed')
                ->sum('amount');

            if (
                $order->payment_status !== 'paid' ||
                (float) $paidTotal < (float) $order->total
            ) {
                throw ValidationException::withMessages([
                    'topup' => 'Đơn hàng chưa được thanh toán đủ. Không thể xác nhận nạp tiền.',
                ]);
            }

            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'topup_status' => 'completed',
                    'topup_completed_at' => now(),
                    'topup_completed_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $this->scheduleRealtimeUpdate(
                $order->pc_id !== null ? (int) $order->pc_id : null,
                (int) $order->id
            );
        });

        return back()->with(
            'success',
            'Đã ghi nhận nhân viên xác nhận nạp tiền thành công. Hệ thống không tự động cộng tiền vào tài khoản máy.'
        );
    }

    /**
     * Phát sự kiện realtime sau khi transaction commit.
     */
    private function scheduleRealtimeUpdate(?int $pcId, int $orderId): void
    {
        $channelId = $pcId !== null ? (string) $pcId : 'dev';
        $channelKey = $pcId !== null ? 'pc:' . $pcId : 'pc:dev';

        $channelToken = hash_hmac(
            'sha256',
            $channelKey,
            (string) config('app.key')
        );

        $channelName = "pc.{$channelId}.{$channelToken}";

        DB::afterCommit(function () use ($channelName, $orderId): void {
            try {
                event(new \App\Events\OrderPaymentUpdated(
                    $channelName,
                    [$orderId]
                ));
            } catch (\Throwable $e) {
                Log::warning('CiCi admin order realtime failed', [
                    'order_id' => $orderId,
                    'channel' => $channelName,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}