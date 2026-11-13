<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->status === 'active', function ($query) {
                $query->where('is_active', true);
            })
            ->when($request->status === 'inactive', function ($query) {
                $query->where('is_active', false);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->only('search', 'category_id', 'status'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:30'],
            'units_per_package' => ['required', 'integer', 'min:1', 'max:10000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($validated['image']);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')
                ->store('products', 'public');
        }

        $validated['is_active'] = true;

        // forceFill để lưu cả units_per_package mà không phụ thuộc
        // vào danh sách fillable hiện tại của Model.
        $product = new Product();
        $product->forceFill($validated);
        $product->save();

        return back()->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:30'],
            'units_per_package' => ['required', 'integer', 'min:1', 'max:10000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($validated['image']);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $validated['image_path'] = $request->file('image')
                ->store('products', 'public');
        }

        $product->forceFill($validated);
        $product->save();

        return back()->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function toggleStatus(Product $product)
    {
        $product->is_active = !$product->is_active;
        $product->save();

        $message = $product->is_active
            ? 'Đã mở bán lại sản phẩm "' . $product->name . '".'
            : 'Đã ngừng bán sản phẩm "' . $product->name . '".';

        return back()->with('success', $message);
    }

    public function destroy(Product $product)
    {
        if (
            $product->orderItems()->exists() ||
            $product->inventoryTransactions()->exists()
        ) {
            return back()->with(
                'error',
                'Sản phẩm đã phát sinh giao dịch. Hãy ngừng kinh doanh thay vì xóa.'
            );
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return back()->with('success', 'Đã xóa sản phẩm.');
    }
}
