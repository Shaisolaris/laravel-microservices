<?php
declare(strict_types=1);
namespace App\Listeners;

use Illuminate\Support\Facades\Log;

class OrderEventListener
{
    public function handleOrderPlaced(array $data): void
    {
        Log::info('[NotificationService] Order placed — sending confirmation email', ['order_id' => $data['data']['order_id'] ?? 'unknown', 'customer_id' => $data['data']['customer_id'] ?? 'unknown']);
        // In production: dispatch(new SendOrderConfirmationJob($data));
    }

    public function handleOrderShipped(array $data): void
    {
        Log::info('[NotificationService] Order shipped — sending tracking email', ['order_id' => $data['data']['order_id'] ?? 'unknown']);
        // In production: dispatch(new SendShippingNotificationJob($data));
    }

    public function handleOrderDelivered(array $data): void
    {
        Log::info('[NotificationService] Order delivered — sending review request', ['order_id' => $data['data']['order_id'] ?? 'unknown']);
    }

    public function handleOrderCancelled(array $data): void
    {
        Log::info('[NotificationService] Order cancelled — sending cancellation notice', ['order_id' => $data['data']['order_id'] ?? 'unknown']);
    }

    public function handleLowStock(array $data): void
    {
        Log::info('[NotificationService] Low stock alert', ['product_id' => $data['data']['product_id'] ?? 'unknown', 'stock' => $data['data']['stock'] ?? 0]);
        // In production: dispatch(new SendLowStockAlertJob($data));
    }
}
