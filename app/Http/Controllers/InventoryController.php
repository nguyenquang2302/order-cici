<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    /**
     * Danh sách tồn kho và lịch sử nhập xuất.
     */
    public function index()
    {
        $products = DB::table('products')
            ->where('is_active', true)
            ->orderByRaw("
                CASE
                    WHEN stock_quantity <= 0 THEN 0
                    WHEN stock_quantity <= low_stock_threshold THEN 1
                    ELSE 2
                END ASC
            ")
            ->orderBy('stock_quantity', 'asc')
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'name',
                'sku',
                'unit',
                'units_per_package',
                'stock_quantity',
                'cost_price',
            ]);

        $transactions = DB::table('inventory_transactions')
            ->join('products', 'products.id', '=', 'inventory_transactions.product_id')
            ->leftJoin('users', 'users.id', '=', 'inventory_transactions.created_by')
            ->select(
                'inventory_transactions.*',
                'products.name as product_name',
                'products.unit',
                'users.name as created_by_name'
            )
            ->latest('inventory_transactions.created_at')
            ->paginate(20);

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Nhập kho theo thùng.
     *
     * Ví dụ:
     * Coca-Cola: 24 lon/thùng
     * Nhập 10 thùng
     * Tồn kho tăng 240 lon.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'package_quantity' => ['required', 'integer', 'min:1'],
            'package_cost' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = DB::table('products')
                ->where('id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$product || !$product->is_active) {
                abort(422, 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.');
            }

            // Số lon/chai trong một thùng.
            $unitsPerPackage = max(
                1,
                (int) ($product->units_per_package ?? 1)
            );

            // Số thùng nhập.
            $packageQuantity = (int) $validated['package_quantity'];

            // Tổng số lon/chai thực tế nhập kho.
            $totalQuantity = $packageQuantity * $unitsPerPackage;

            // Giá nhập một thùng.
            $packageCost = (float) $validated['package_cost'];

            // Giá vốn của một lon/chai.
            $unitCost = round(
                $packageCost / $unitsPerPackage,
                2
            );

            // Cập nhật tồn kho.
            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'stock_quantity' => (int) $product->stock_quantity + $totalQuantity,
                    'cost_price' => $unitCost,
                    'updated_at' => now(),
                ]);

            // Nội dung lịch sử nhập kho.
            $note = "Nhập {$packageQuantity} thùng x {$unitsPerPackage} {$product->unit}/thùng";

            if (!empty($validated['note'])) {
                $note .= ' - ' . $validated['note'];
            }

            // Lưu lịch sử theo số lượng đơn vị bán lẻ.
            DB::table('inventory_transactions')->insert([
                'product_id' => $product->id,
                'created_by' => auth()->id(),
                'type' => 'in',
                'quantity' => $totalQuantity,
                'unit_cost' => $unitCost,
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Nhập kho thành công.');
    }
}
