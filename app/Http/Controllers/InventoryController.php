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
     * Giá vốn sản phẩm được tính theo bình quân gia quyền.
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

            // Số đơn vị bán lẻ trong một thùng.
            $unitsPerPackage = max(
                1,
                (int) ($product->units_per_package ?? 1)
            );

            // Số thùng nhập.
            $packageQuantity = (int) $validated['package_quantity'];

            // Tổng số đơn vị bán lẻ nhập kho.
            $totalQuantity = $packageQuantity * $unitsPerPackage;

            // Giá nhập một thùng.
            $packageCost = (float) $validated['package_cost'];

            // Giá nhập thực tế của một đơn vị bán lẻ.
            $unitCost = $packageCost / $unitsPerPackage;

            // Tồn kho và giá vốn cũ.
            $oldQuantity = (int) $product->stock_quantity;
            $oldCost = (float) $product->cost_price;

            // Tồn kho mới.
            $newQuantity = $oldQuantity + $totalQuantity;

            // Tính giá vốn bình quân gia quyền.
            if ($oldQuantity > 0 && $newQuantity > 0) {
                $newAverageCost = (
                    ($oldQuantity * $oldCost)
                    + ($totalQuantity * $unitCost)
                ) / $newQuantity;
            } else {
                // Hết hàng hoặc tồn kho không dương:
                // lấy giá nhập của lô hàng mới làm giá vốn.
                $newAverageCost = $unitCost;
            }

            $newAverageCost = round($newAverageCost, 2);
            $unitCost = round($unitCost, 2);

            // Cập nhật tồn kho và giá vốn bình quân.
            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'stock_quantity' => $newQuantity,
                    'cost_price' => $newAverageCost,
                    'updated_at' => now(),
                ]);

            // Nội dung lịch sử nhập kho.
            $note = "Nhập {$packageQuantity} thùng x {$unitsPerPackage} {$product->unit}/thùng";

            if (!empty($validated['note'])) {
                $note .= ' - ' . $validated['note'];
            }

            // Lưu lịch sử theo giá nhập thực tế của lô hàng,
            // không phải giá vốn bình quân mới.
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
