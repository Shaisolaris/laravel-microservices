<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Shared\Messages\MessageBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->paginate(20);
        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(['user' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Publish event to message bus
        try {
            $bus = new MessageBus(env('RABBITMQ_HOST', 'rabbitmq'));
            $bus->publish('user.events', 'user.created', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
            $bus->close();
        } catch (\Throwable $e) {
            \Log::warning('Failed to publish user.created event: ' . $e->getMessage());
        }

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update($request->only(['name', 'email']));
        return response()->json(['user' => $user]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['service' => 'user-service', 'status' => 'healthy', 'timestamp' => now()->toIso8601String()]);
    }
}
