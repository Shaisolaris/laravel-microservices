<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\Product;
use Shared\Messages\MessageBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->paginate(20);
        return response()->json($products);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        return response()->json(['product' => $product]);
    }

    public function updateStock(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['quantity' => 'required|integer', 'action' => 'required|in:reserve,release,adjust']);
        $product = Product::findOrFail($id);

        match ($validated['action']) {
            'reserve' => $product->decrement('stock', $validated['quantity']),
            'release' => $product->increment('stock', $validated['quantity']),
            'adjust' => $product->update(['stock' => $validated['quantity']]),
        };

        if ($product->fresh()->stock <= $product->low_stock_threshold) {
            try {
                $bus = new MessageBus(env('RABBITMQ_HOST', 'rabbitmq'));
                $bus->publish('inventory.events', 'inventory.low_stock', ['product_id' => $product->id, 'stock' => $product->stock, 'name' => $product->name]);
                $bus->close();
            } catch (\Throwable) {}
        }

        return response()->json(['product' => $product->fresh()]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['service' => 'inventory-service', 'status' => 'healthy', 'timestamp' => now()->toIso8601String()]);
    }
}
