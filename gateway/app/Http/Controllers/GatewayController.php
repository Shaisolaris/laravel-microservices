<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Shared\Messages\ServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatewayController
{
    private ServiceClient $userService;
    private ServiceClient $orderService;
    private ServiceClient $inventoryService;

    public function __construct()
    {
        $this->userService = new ServiceClient('user-service', env('USER_SERVICE_URL', 'http://localhost:8001'));
        $this->orderService = new ServiceClient('order-service', env('ORDER_SERVICE_URL', 'http://localhost:8002'));
        $this->inventoryService = new ServiceClient('inventory-service', env('INVENTORY_SERVICE_URL', 'http://localhost:8004'));
    }

    public function listUsers(Request $request): JsonResponse { return response()->json($this->userService->get('/api/users', $request->query())); }
    public function getUser(string $id): JsonResponse { return response()->json($this->userService->get("/api/users/{$id}")); }
    public function createUser(Request $request): JsonResponse { return response()->json($this->userService->post('/api/users', $request->all()), 201); }

    public function listOrders(Request $request): JsonResponse { return response()->json($this->orderService->get('/api/orders', $request->query())); }
    public function getOrder(string $id): JsonResponse { return response()->json($this->orderService->get("/api/orders/{$id}")); }
    public function createOrder(Request $request): JsonResponse { return response()->json($this->orderService->post('/api/orders', $request->all()), 201); }
    public function updateOrderStatus(Request $request, string $id): JsonResponse { return response()->json($this->orderService->put("/api/orders/{$id}/status", $request->all())); }

    public function listProducts(Request $request): JsonResponse { return response()->json($this->inventoryService->get('/api/products', $request->query())); }
    public function getProduct(string $id): JsonResponse { return response()->json($this->inventoryService->get("/api/products/{$id}")); }

    public function health(): JsonResponse
    {
        $services = [];
        foreach (['user-service' => $this->userService, 'order-service' => $this->orderService, 'inventory-service' => $this->inventoryService] as $name => $client) {
            try { $client->get('/api/health'); $services[$name] = 'up'; } catch (\Throwable) { $services[$name] = 'down'; }
        }
        $allHealthy = !in_array('down', $services);
        return response()->json(['status' => $allHealthy ? 'healthy' : 'degraded', 'services' => $services, 'timestamp' => now()->toIso8601String()], $allHealthy ? 200 : 503);
    }
}
