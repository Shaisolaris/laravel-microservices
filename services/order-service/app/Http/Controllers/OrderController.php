<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Shared\Messages\MessageBus;
use Shared\Messages\ServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('items')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->latest()
            ->paginate(20);
        return response()->json($orders);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);
        return response()->json(['order' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'shipping_address' => 'required|string',
        ]);

        // Verify stock with inventory service
        $inventoryClient = new ServiceClient('inventory-service', env('INVENTORY_SERVICE_URL', 'http://localhost:8004'));
        foreach ($validated['items'] as $item) {
            $product = $inventoryClient->get("/api/products/{$item['product_id']}");
            if (isset($product['error'])) {
                return response()->json(['error' => "Product {$item['product_id']} not available"], 422);
            }
        }

        $subtotal = collect($validated['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        $tax = round($subtotal * 0.0875, 2);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $validated['customer_id'],
            'status' => 'placed',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'shipping_address' => $validated['shipping_address'],
        ]);

        foreach ($validated['items'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        // Publish order.placed event
        try {
            $bus = new MessageBus(env('RABBITMQ_HOST', 'rabbitmq'));
            $bus->publish('order.events', 'order.placed', [
                'order_id' => $order->id,
                'uuid' => $order->uuid,
                'customer_id' => $order->customer_id,
                'total' => $order->total,
                'items' => $validated['items'],
            ]);
            $bus->close();
        } catch (\Throwable $e) {
            \Log::warning('Failed to publish order.placed: ' . $e->getMessage());
        }

        return response()->json(['order' => $order->load('items')], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,shipped,delivered,cancelled',
            'tracking_number' => 'nullable|string',
            'carrier' => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $order->update(array_filter($validated));

        // Publish status change event
        try {
            $bus = new MessageBus(env('RABBITMQ_HOST', 'rabbitmq'));
            $bus->publish('order.events', "order.{$validated['status']}", [
                'order_id' => $order->id,
                'uuid' => $order->uuid,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'customer_id' => $order->customer_id,
            ]);
            $bus->close();
        } catch (\Throwable $e) {
            \Log::warning('Failed to publish order status event: ' . $e->getMessage());
        }

        return response()->json(['order' => $order]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['service' => 'order-service', 'status' => 'healthy', 'timestamp' => now()->toIso8601String()]);
    }
}
