<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'pcs.code as pc_code',
                'pcs.name as pc_name',
            ])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('orders.status', $request->status);
            })
            ->when($request->filled('payment_status'), function ($query) use ($request) {
                $query->where('orders.payment_status', $request->payment_status);
            })
            ->orderByDesc('orders.created_at')
            ->paginate(20)
            ->withQueryString();

        $orderIds = $orders->getCollection()->pluck('id');

        // Lấy danh sách món của từng đơn.
        $items = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        // Lấy lịch sử thanh toán.
        $payments = DB::table('payments')
            ->whereIn('order_id', $orderIds)
            ->where('status', 'completed')
            ->orderBy('paid_at')
            ->get()
            ->groupBy('order_id');

        // Bổ sung dữ liệu cho từng đơn hàng.
        $orders->getCollection()->transform(function ($order) use ($items, $payments) {
            $order->items = $items->get($order->id, collect());

            $order->payments = $payments->get($order->id, collect());

            $order->paid_total = $order->payments->sum('amount');

            $order->remaining = max(
                0,
                (float) $order->total - (float) $order->paid_total
            );

            return $order;
        });

        // Tổng hợp sản phẩm trong tất cả đơn mới, chưa xác nhận.
        // Hiển thị tên khách hàng đối với đơn tại quầy.
        // Hiển thị mã máy đối với đơn đặt từ PC.
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

        // Tổng số đơn mới.
        $pendingOrderCount = DB::table('orders')
            ->where('status', 'pending')
            ->count();

        // Danh sách sản phẩm đang kinh doanh để tạo đơn tại quầy.
        $products = DB::table('products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'selling_price',
                'stock_quantity',
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
        ]);
    }

    /**
     * Nhân viên tạo đơn tại quầy.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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

            // Tạo đơn hàng tại quầy.
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $orderNumber,
                'pc_id' => null,
                'created_by' => auth()->id(),
                'customer_name' => !empty(trim($validated['customer_name'] ?? ''))
                    ? trim($validated['customer_name'])
                    : null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lưu các sản phẩm trong đơn.
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

                // Trừ tồn kho.
                DB::table('products')
                    ->where('id', $product->id)
                    ->decrement('stock_quantity', $item->quantity);

                // Ghi lịch sử xuất kho.
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

            // Chuyển trạng thái khi toàn bộ sản phẩm đã trừ kho thành công.
            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Đã xác nhận đơn hàng và cập nhật tồn kho.');
    }

    /**
     * Hoàn thành đơn hàng trực tiếp hoặc từ trạng thái đang xử lý.
     */
    public function complete(int $id)
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
                    'order' => 'Đơn hàng đã hoàn thành hoặc đã bị hủy.',
                ]);
            }

            // Hoàn thành trực tiếp từ đơn mới: phải trừ kho trước.
            if ($order->status === 'pending') {
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

                    // Trừ kho.
                    DB::table('products')
                        ->where('id', $product->id)
                        ->decrement('stock_quantity', $item->quantity);

                    // Ghi lịch sử xuất kho.
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

            // Đơn processing đã trừ kho nên không trừ thêm lần nữa.
            DB::table('orders')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Đơn hàng đã hoàn thành và tồn kho đã được cập nhật.');
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

            // Nếu đơn đang xử lý thì đã trừ kho, cần cộng trả lại.
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
        });

        return back()->with('success', 'Đã hủy đơn hàng và cập nhật tồn kho.');
    }
}
