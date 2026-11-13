<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $pc = $request->attributes->get('current_pc');

        try {
            $order = DB::transaction(function () use ($validated, $pc) {
                $productIds = collect($validated['items'])
                    ->pluck('product_id')
                    ->unique()
                    ->values();

                $products = DB::table('products')
                    ->whereIn('id', $productIds)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw new \RuntimeException('Một hoặc nhiều sản phẩm không còn kinh doanh.');
                }

                $subtotal = 0;
                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    $quantity = (int) $item['quantity'];

                    if ($product->stock_quantity < $quantity) {
                        throw new \RuntimeException(
                            "Sản phẩm {$product->name} không đủ số lượng trong kho."
                        );
                    }

                    $unitPrice = (float) $product->selling_price;
                    $costPrice = (float) $product->cost_price;
                    $itemSubtotal = $unitPrice * $quantity;

                    $subtotal += $itemSubtotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'cost_price' => $costPrice,
                        'subtotal' => $itemSubtotal,
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $orderNumber = 'CICI-' . now()->format('ymdHis')
                    . '-' . Str::upper(Str::random(5));

                $orderId = DB::table('orders')->insertGetId([
                    'order_number' => $orderNumber,
                    'pc_id' => $pc->id ?? null,
                    'created_by' => null,
                    'customer_name' => null,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $subtotal,
                    'note' => $validated['note'] ?? null,
                    'completed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($orderItems as &$orderItem) {
                    $orderItem['order_id'] = $orderId;
                }
                unset($orderItem);

                DB::table('order_items')->insert($orderItems);

                return [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'pc_name' => $pc->name ?? 'Máy kiểm tra (DEV)',
                    'pc_code' => $pc->code ?? 'DEV',
                    'total' => $subtotal,
                    'items' => $orderItems,
                ];
            });

            // Gửi Telegram sau khi đơn hàng đã được lưu thành công.
            $this->sendTelegramNotification($order);

            return back()->with('success', 'Đã gửi đơn hàng ' . $order['order_number']);

        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'order' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('CiCi order creation failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'order' => 'Không thể tạo đơn hàng. Vui lòng thử lại.',
            ]);
        }
    }

    private function sendTelegramNotification(array $order): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            Log::warning('CiCi Telegram chưa được cấu hình.');
            return;
        }

        $lines = [
            '<b>🔔 CICI GAMING - ĐƠN HÀNG MỚI</b>',
            '',
            '<b>Mã đơn:</b> ' . e($order['order_number']),
            '<b>Máy:</b> ' . e($order['pc_code'] . ' - ' . $order['pc_name']),
            '',
            '<b>Danh sách món:</b>',
        ];

        foreach ($order['items'] as $item) {
            $lines[] = '• ' . e($item['product_name'])
                . ' x ' . $item['quantity']
                . ' — ' . number_format($item['subtotal'], 0, ',', '.') . 'đ';
        }

        $lines[] = '';
        $lines[] = '<b>Tổng tiền:</b> ' . number_format($order['total'], 0, ',', '.') . 'đ';
        $lines[] = '<b>Thời gian:</b> ' . now()->format('d/m/Y H:i:s');

        $message = implode("\n", $lines);

        try {
            Http::asForm()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
        } catch (Throwable $e) {
            Log::warning('CiCi Telegram gửi thông báo thất bại', [
                'error' => $e->getMessage(),
                'order_number' => $order['order_number'],
            ]);
        }
    }
}