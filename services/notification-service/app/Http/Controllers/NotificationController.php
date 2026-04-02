<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;

class NotificationController
{
    public function health(): JsonResponse
    {
        return response()->json(['service' => 'notification-service', 'status' => 'healthy', 'timestamp' => now()->toIso8601String()]);
    }
}
