<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class InventoryController extends Controller
{
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
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'unit',
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
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

            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'stock_quantity' => $product->stock_quantity + $validated['quantity'],
                    'cost_price' => $validated['unit_cost'],
                    'updated_at' => now(),
                ]);

            DB::table('inventory_transactions')->insert([
                'product_id' => $product->id,
                'created_by' => auth()->id(),
                'type' => 'in',
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'],
                'note' => $validated['note'] ?? 'Nhập kho',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Nhập kho thành công.');
    }
}
