<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\JsonResponse;

class RouteController extends Controller
{

    public function index(): JsonResponse
    {
        $routes = Route::query()
            ->with('operator')
            ->orderBy('origin')
            ->orderBy('destination')
            ->get()
            ->map(fn (Route $route) => [
                'id' => $route->id,
                'origin' => $route->origin,
                'destination' => $route->destination,
                'duration_minutes' => $route->duration_minutes,
                'label' => "{$route->origin} → {$route->destination} ({$route->operator->name})",
                'operator' => [
                    'id' => $route->operator->id,
                    'code' => $route->operator->code,
                    'name' => $route->operator->name,
                ],
            ]);

        return response()->json(['data' => $routes]);
    }
}
