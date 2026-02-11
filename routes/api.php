<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\RackController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\ResearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Server Tycoon
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/ping', function() { return 'pong'; });
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!auth()->attempt($request->only('email', 'password'))) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    $user = auth()->user();
    $token = $user->createToken('game-token')->plainTextToken;

    return response()->json([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
});

Route::post('/register', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8|confirmed',
    ]);

    $user = \App\Models\User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ]);

    $token = $user->createToken('game-token')->plainTextToken;

    return response()->json([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
});

// Protected game routes
Route::middleware('auth:sanctum')->group(function () {
    // Current user
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    });

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true]);
    });

    // Game state
    Route::get('/game/state', [GameController::class, 'getState']);
    Route::post('/game/initialize', [GameController::class, 'initialize']);

    // Rack & Server management
    Route::get('/catalog/servers', [RackController::class, 'getCatalog']);
    Route::post('/rack/purchase', [RackController::class, 'purchaseRack']);
    Route::post('/server/place', [RackController::class, 'placeServer']);
    Route::post('/server/move', [RackController::class, 'moveServer']);
    Route::post('/server/power-on', [RackController::class, 'powerOn']);
    Route::post('/server/power-off', [RackController::class, 'powerOff']);
    Route::post('/rack/clean', [RackController::class, 'clean']);

    // Game Events
    Route::get('/events/active', [EventController::class, 'getActive']);
    Route::post('/events/resolve', [EventController::class, 'resolve']);
    Route::get('/events/history', [EventController::class, 'getHistory']);

    // Research
    Route::get('/research/projects', [ResearchController::class, 'index']);
    Route::post('/research/start', [ResearchController::class, 'start']);

    // Customer Orders
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::post('/orders/{id}/accept', [CustomerOrderController::class, 'accept']);
    Route::post('/orders/{id}/reject', [CustomerOrderController::class, 'reject']);
    Route::post('/orders/{id}/cancel', [CustomerOrderController::class, 'cancelActive']);

    // Room Management
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms/purchase', [RoomController::class, 'purchase']);
    Route::post('/rooms/upgrade', [RoomController::class, 'upgrade']);

    // Financial Transactions
    Route::get('/economy/transactions', [\App\Http\Controllers\Api\EconomyController::class, 'transactions']);

    // Server Maintenance
    Route::post('/server/repair', [\App\Http\Controllers\Api\ServerController::class, 'repair']);
    Route::get('/server/{id}/details', [\App\Http\Controllers\Api\ServerController::class, 'details']);
    Route::post('/server/{id}/diagnose', [\App\Http\Controllers\Api\ServerController::class, 'diagnose']);
    Route::post('/server/{id}/maintenance', [\App\Http\Controllers\Api\ServerController::class, 'scheduleMaintenance']);

    // Economy
    Route::get('/economy/history', [\App\Http\Controllers\Api\EconomyController::class, 'history']);

    // Employees
    Route::get('/employees', [\App\Http\Controllers\Api\EmployeeController::class, 'index']);
    Route::post('/employees/hire', [\App\Http\Controllers\Api\EmployeeController::class, 'hire']);
    Route::post('/employees/{id}/fire', [\App\Http\Controllers\Api\EmployeeController::class, 'fire']);

    // Statistics
    Route::get('/stats/history', [\App\Http\Controllers\Api\StatsController::class, 'history']);

    // World Events
    Route::get('/world-events/active', [\App\Http\Controllers\Api\WorldEventController::class, 'getActive']);

    // Automation
    Route::post('/automation/toggle', [\App\Http\Controllers\Api\AutomationController::class, 'toggle']);

    // Strategic Management
    Route::post('/management/decision', [\App\Http\Controllers\Api\ManagementController::class, 'makeDecision']);
});
