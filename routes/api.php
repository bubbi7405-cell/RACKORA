<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\ApiSimulationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\EconomyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EnergyController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\ManagementController;
use App\Http\Controllers\Api\RackController;
use App\Http\Controllers\Api\ResearchController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\WorldEventController;
use App\Http\Controllers\Api\HardwareController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NegotiationController;
use App\Http\Controllers\Api\NetworkController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SabotageController;
use App\Http\Controllers\Api\PeeringController;
use App\Http\Controllers\Api\MultiplayerController;
use App\Http\Controllers\Api\PrivateNetworkController;
use App\Http\Controllers\Api\HqController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\SoftwareController;
use App\Http\Controllers\Api\BenchmarkingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Rackora
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

    \App\Models\GameLog::log($user, "Successful login from " . $request->ip(), 'info', 'security', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);

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

    \App\Models\GameLog::log($user, "Account created from " . $request->ip(), 'info', 'security', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);

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
Route::middleware(['auth:sanctum', 'activity', 'throttle:api'])->group(function () {
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

    // State-changing actions (Throttled more strictly)
    Route::middleware('throttle:game-actions')->group(function () {
        // Game state
        Route::post('/game/initialize', [GameController::class, 'initialize']);
        Route::post('/game/tutorial', [GameController::class, 'updateTutorial']);
        Route::post('/game/speed', [GameController::class, 'setSpeed']);

        // Rack & Server management
        Route::post('/rack/purchase', [RackController::class, 'purchaseRack']);
        Route::post('/server/place', [RackController::class, 'placeServer']);
        Route::post('/server/move', [RackController::class, 'moveServer']);
        Route::post('/server/power-on', [RackController::class, 'powerOn']);
        Route::post('/server/power-off', [RackController::class, 'powerOff']);
        Route::post('/server/repair', [ServerController::class, 'repair']);
        Route::post('/server/modernize', [RackController::class, 'modernize']);
        Route::post('/rack/clean', [RackController::class, 'clean']);
        Route::post('/rack/{id}/lighting', [RackController::class, 'updateLighting']);
        Route::post('/rack/colocation/toggle', [RackController::class, 'toggleColocation']);
        Route::post('/rack/{id}/replace-fans', [RackController::class, 'replaceFans']); // F64
        
        // Research & Economy
        Route::post('/research/start', [ResearchController::class, 'start']);
        Route::post('/orders/{id}/accept', [CustomerOrderController::class, 'accept']);
        Route::post('/orders/{id}/reject', [CustomerOrderController::class, 'reject']);
        Route::post('/orders/{id}/cancel', [CustomerOrderController::class, 'cancelActive']);
        
        // Hardware & Components
        Route::post('/hardware/purchase', [HardwareController::class, 'purchaseComponent']);
        Route::post('/hardware/simulate', [HardwareController::class, 'simulateBuild']);
        Route::post('/hardware/assemble', [HardwareController::class, 'assembleServer']);
        Route::post('/hardware/{id}/sell', [HardwareController::class, 'sellComponent']);
        Route::post('/hardware/{id}/shred', [HardwareController::class, 'shredComponent']);
        Route::post('/hardware/disassemble/{id}', [HardwareController::class, 'disassembleServer']);
        Route::post('/hardware/install/{server}', [HardwareController::class, 'installComponent']);
        Route::post('/hardware/remove/{server}', [HardwareController::class, 'removeComponent']);
        Route::post('/hardware/components/{id}/sell', [HardwareController::class, 'sellComponent']);
        Route::post('/hardware/components/{id}/buyout', [HardwareController::class, 'buyoutComponent']);
        Route::post('/hardware/components/{id}/return', [HardwareController::class, 'returnLeasedComponent']);
        Route::post('/hardware/spare-parts/purchase', [HardwareController::class, 'purchaseSpareParts']);
        Route::post('/hardware/servers/{id}/maintain', [HardwareController::class, 'maintainServer']);
        Route::post('/hardware/sell', function (\Illuminate\Http\Request $request) {
            $request->validate(['server_id' => 'required|uuid|exists:servers,id']);
            $user = $request->user();
            $server = \App\Models\Server::findOrFail($request->server_id);
            $service = app(\App\Services\Game\HardwareDepreciationService::class);
            $result = $service->sellServer($user, $server);
            return response()->json($result, $result['success'] ? 200 : 400);
        });

        // Benchmarking Labs
        Route::get('/hardware/benchmarks', [BenchmarkingController::class, 'index']);
        Route::post('/hardware/benchmarks/run', [BenchmarkingController::class, 'run']);

        // Network & NOC Actions
        Route::post('/network/purchase-asn', [NetworkController::class, 'purchaseASN']);
        Route::post('/network/upgrade-peering', [NetworkController::class, 'upgradePeering']);
        Route::post('/network/buy-ipv4', [NetworkController::class, 'purchaseIpv4Block']);
        Route::post('/network/buy-black-market-ipv4', [NetworkController::class, 'purchaseBlackMarketBlock']);
        Route::post('/network/sell-ipv4', [NetworkController::class, 'sellIpv4Block']);
        Route::post('/network/upgrade-ddos', [NetworkController::class, 'upgradeDdos']);
        Route::post('/network/switch-isp', [NetworkController::class, 'switchIsp']);
        Route::post('/network/allocate-subnet', [NetworkController::class, 'allocateSubnet']);
        Route::post('/network/peering/propose', [PeeringController::class, 'proposePeering']);
        
        // VPN Actions
        Route::post('/network/private', [PrivateNetworkController::class, 'store']);
        Route::delete('/network/private/{id}', [PrivateNetworkController::class, 'destroy']);
        Route::post('/network/private/{id}/attach', [PrivateNetworkController::class, 'attachServer']);
        Route::post('/network/private/detach', [PrivateNetworkController::class, 'detachServer']);
        Route::post('/network/private/{id}/firewall', [PrivateNetworkController::class, 'storeRule']);
        Route::post('/network/private/{id}/test', [PrivateNetworkController::class, 'testRule']);
        Route::delete('/network/private/firewall/{ruleId}', [PrivateNetworkController::class, 'destroyRule']);

        // Employees
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees/hire', [EmployeeController::class, 'hire']);
        Route::post('/employees/{id}/fire', [EmployeeController::class, 'fire']);
        Route::post('/employees/train', [EmployeeController::class, 'train']);
        Route::post('/employees/raise', [EmployeeController::class, 'giveRaise']);
        Route::post('/employees/unlock-perk', [EmployeeController::class, 'unlockPerk']);
        Route::post('/employees/respec', [EmployeeController::class, 'respec']);
        Route::post('/employees/{id}/sabbatical', [EmployeeController::class, 'sendOnSabbatical']);
        Route::post('/employees/{id}/assign-room', [EmployeeController::class, 'assignToRoom']);
        Route::post('/employees/{id}/retention-bonus', [EmployeeController::class, 'giveRetentionBonus']); // F128
        Route::post('/employees/{id}/counter-headhunter', [EmployeeController::class, 'counterHeadhunter']); // F69
        Route::post('/employees/{id}/seminar', [EmployeeController::class, 'sendToSeminar']); // F161
        Route::post('/employees/{id}/persuade-to-stay', [EmployeeController::class, 'persuadeToStay']); // F232

        // Room Management
        Route::post('/rooms/purchase', [RoomController::class, 'purchase']);
        Route::post('/rooms/upgrade', [RoomController::class, 'upgrade']);
        Route::post('/rooms/customize', [RoomController::class, 'customize']);
        Route::post('/rooms/reset-breaker', [RoomController::class, 'resetCircuitBreaker']);
        Route::post('/rooms/pr-tour', [RoomController::class, 'hostPrTour']);

        // Strategic Actions
        Route::post('/management/decision', [ManagementController::class, 'makeDecision']);
        Route::post('/management/specialization', [ManagementController::class, 'setSpecialization']);
        Route::post('/management/branding', [ManagementController::class, 'updateCompanyBranding']);
        Route::post('/management/skills/unlock', [ManagementController::class, 'unlockSkill']);
        Route::post('/customers/{id}/pr-outreach', [ManagementController::class, 'prOutreach']);
        
        // Corporate HQ
        Route::get('/hq', [HqController::class, 'index']);
        Route::post('/hq/build', [HqController::class, 'build']);
        Route::post('/hq/upgrade', [HqController::class, 'upgrade']);
        
        // Energy Market
        Route::post('/energy/sign', [EnergyController::class, 'sign']);
        Route::post('/energy/policy', [EnergyController::class, 'togglePolicy']);
        Route::post('/energy/refill', [EnergyController::class, 'refill']);
        Route::post('/energy/specialization', [EnergyController::class, 'setSpecialization']);

        // FEATURE 256: Stock Market & Short-Selling
        Route::get('/stock-market', [\App\Http\Controllers\Api\StockMarketController::class, 'index']);
        Route::post('/stock-market/short', [\App\Http\Controllers\Api\StockMarketController::class, 'shortOwnStock']);
        Route::post('/stock-market/close', [\App\Http\Controllers\Api\StockMarketController::class, 'closePosition']);

        // Maintenance & Software
        Route::post('/server/{id}/diagnose', [ServerController::class, 'diagnose']);
        Route::post('/server/{id}/diagnose/complete', [ServerController::class, 'completeDiagnosis']);
        Route::post('/server/{id}/maintenance', [ServerController::class, 'scheduleMaintenance']);
        Route::post('/server/{id}/install-os', [ServerController::class, 'installOs']);
        Route::post('/server/{id}/install-software', [App\Http\Controllers\Api\SoftwareController::class, 'install']);
        Route::post('/server/{id}/uninstall-software', [App\Http\Controllers\Api\SoftwareController::class, 'uninstall']);
        Route::post('/server/{id}/update-software', [App\Http\Controllers\Api\SoftwareController::class, 'update']);
        Route::post('/server/{id}/backup-plan', [ServerController::class, 'updateBackupPlan']);
        Route::post('/server/{id}/rollback', [ServerController::class, 'rollbackBackup']);
        Route::post('/server/{id}/appearance', [ServerController::class, 'updateAppearance']);
        Route::post('/server/{id}/overclock', [ServerController::class, 'overclock']);
        Route::post('/server/{id}/tune', [ServerController::class, 'tune']);
        Route::post('/server/{id}/sell', [ServerController::class, 'sell']);
        Route::post('/server/{id}/shred', [ServerController::class, 'shred']);
        Route::post('/server/{id}/mining/toggle', [ServerController::class, 'toggleMining']);
        Route::post('/server/{id}/stress-test', [ServerController::class, 'stressTest']);
        Route::post('/server/{id}/swap-component', [ServerController::class, 'swapComponent']);
        Route::post('/server/{id}/insure', [ServerController::class, 'insureServer']);
        Route::post('/server/{id}/cancel-insurance', [ServerController::class, 'cancelInsurance']);
        Route::post('/server/{id}/insurance-fraud', [ServerController::class, 'commitInsuranceFraud']);

        // Rollout & Templates
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/templates/create', [TemplateController::class, 'createFromServer']);
        Route::post('/templates/apply', [TemplateController::class, 'applyToServer']);
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);

        // Bestechung & Moral (F206)
        Route::get('/bribery', [ManagementController::class, 'getBriberyState']);
        Route::post('/bribery/accept', [ManagementController::class, 'acceptBribe']);
        Route::post('/bribery/decline', [ManagementController::class, 'declineBribe']);

        // Darknet Operations (F202)
        Route::get('/management/darknet', [ManagementController::class, 'getDarknetState']);
        Route::get('/hardware/insurance/plans', [ServerController::class, 'getInsurancePlans']);
        Route::post('/server/{id}/darknet/enable', [ServerController::class, 'enableDarknet']);
        Route::post('/server/{id}/darknet/disable', [ServerController::class, 'disableDarknet']);

        // Black Market (Night Shop - F157)
        Route::get('/black-market', [\App\Http\Controllers\Api\BlackMarketController::class, 'index']);
        Route::post('/black-market/purchase', [\App\Http\Controllers\Api\BlackMarketController::class, 'purchase']);

        // Auctions (F130)
        Route::get('/auctions', [AuctionController::class, 'index']);
        Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid']);

        // Cyber-Insurance (F91)
        Route::get('/cyber-insurance', function (Request $request) {
            return response()->json(['success' => true, 'data' => app(\App\Services\Game\CyberInsuranceService::class)->getState($request->user())]);
        });
        Route::post('/cyber-insurance/subscribe', function (Request $request) {
            $request->validate(['plan' => 'required|string']);
            $result = app(\App\Services\Game\CyberInsuranceService::class)->subscribe($request->user(), $request->plan);
            return response()->json($result, $result['success'] ? 200 : 400);
        });
        Route::post('/cyber-insurance/cancel', function (Request $request) {
            $result = app(\App\Services\Game\CyberInsuranceService::class)->cancel($request->user());
            return response()->json($result, $result['success'] ? 200 : 400);
        });

        // Sabotage & Espionage
        Route::post('/sabotage/attempt', [SabotageController::class, 'attempt']);

        // Multiplayer / Rentals
        Route::post('/multiplayer/rentals/list', [MultiplayerController::class, 'listServer']);
        Route::post('/multiplayer/rentals/rent', [MultiplayerController::class, 'rentServer']);
        Route::post('/multiplayer/rentals/{id}/terminate', [MultiplayerController::class, 'terminateRental']);

        // Marketing & Analytics
        Route::get('/analytics', [\App\Http\Controllers\Api\AnalyticsController::class, 'index']);
        Route::get('/marketing/predictions', [MarketingController::class, 'predictions']);
        Route::post('/marketing/start', [MarketingController::class, 'start']);

        // Compliance
        Route::post('/compliance/audit', [\App\Http\Controllers\Api\ComplianceController::class, 'startAudit']);

        // Contract Bidding
        Route::post('/negotiation/{id}/bid', [NegotiationController::class, 'submitBid']);

        // Profile Actions
        Route::prefix('profile')->group(function () {
            Route::post('/update', [ProfileController::class, 'update']);
            Route::post('/password', [ProfileController::class, 'updatePassword']);
            Route::post('/preferences', [ProfileController::class, 'updatePreferences']);
            Route::delete('/sessions/{id}', [ProfileController::class, 'revokeSession']);
            Route::post('/upload', [ProfileController::class, 'uploadImage']);
            Route::post('/export', [ProfileController::class, 'exportData']);
            Route::post('/delete', [ProfileController::class, 'deleteAccount']);
        });
        
        // Automation
        Route::post('/automation/toggle', [AutomationController::class, 'toggle']);

        // F125: Automated Maintenance Configuration
        Route::post('/automation/maintenance', function (Request $request) {
            $request->validate([
                'enabled' => 'required|boolean',
                'health_threshold' => 'nullable|integer|min:20|max:80',
                'max_cost_per_tick' => 'nullable|numeric|min:100|max:5000',
            ]);

            $user = $request->user();
            $economy = $user->economy;
            $meta = $economy->metadata ?? [];

            $meta['auto_maintenance'] = [
                'enabled' => $request->boolean('enabled'),
                'health_threshold' => $request->input('health_threshold', $meta['auto_maintenance']['health_threshold'] ?? 50),
                'max_cost_per_tick' => $request->input('max_cost_per_tick', $meta['auto_maintenance']['max_cost_per_tick'] ?? 500),
            ];

            $economy->metadata = $meta;
            $economy->save();

            $status = $meta['auto_maintenance']['enabled'] ? 'enabled' : 'disabled';
            \App\Models\GameLog::log($user, "🔧 AUTO-MAINTENANCE: {$status} (Threshold: {$meta['auto_maintenance']['health_threshold']}%).", 'info', 'infrastructure');

            return response()->json(['success' => true, 'auto_maintenance' => $meta['auto_maintenance']]);
        });
    });

    // General read-only or low-frequency endpoints (Globally throttled by throttle:api)
    Route::get('/game/state', [GameController::class, 'getState']);
    Route::get('/game/logs', [GameController::class, 'getLogs']);
    Route::get('/game/summary', [GameController::class, 'getSummary']);
    Route::get('/game/news', [GameController::class, 'getNews']);
    Route::get('/catalog/servers', [RackController::class, 'getCatalog']);
    Route::get('/catalog/os', [App\Http\Controllers\Api\ServerController::class, 'getOsCatalog']);
    Route::get('/catalog/software', [App\Http\Controllers\Api\SoftwareController::class, 'getCatalog']);
    Route::get('/hardware/catalog', [HardwareController::class, 'getCatalog']);
    Route::get('/hardware/generations', [HardwareController::class, 'getGenerations']);
    Route::get('/server/{id}/details', [ServerController::class, 'details']);
    Route::post('/server/{id}/battery/refurbish', [ServerController::class, 'refurbishBattery']);
    Route::get('/hardware/brand-deals/options', [\App\Http\Controllers\Api\HardwareBrandController::class, 'getOptions']);
    Route::post('/hardware/brand-deals/sign', [\App\Http\Controllers\Api\HardwareBrandController::class, 'signDeal']);
    Route::post('/hardware/brand-deals/terminate', [\App\Http\Controllers\Api\HardwareBrandController::class, 'terminateDeal']);

    Route::get('/hardware/inventory', [HardwareController::class, 'getInventory']);
    Route::get('/hardware/resale-trends', [HardwareController::class, 'getResaleTrends']);
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/events/active', [EventController::class, 'getActive']);
    Route::get('/events/history', [EventController::class, 'getHistory']);
    Route::post('/events/resolve', [EventController::class, 'resolve']);
    Route::post('/events/{id}/post-mortem', [EventController::class, 'postMortem']);
    Route::post('/crisis/action', [EventController::class, 'takeCrisisAction']);
    Route::post('/crisis/fiber-minigame', [EventController::class, 'completeFiberMinigame']);
    Route::post('/crisis/strike-negotiation', [EventController::class, 'completeStrikeNegotiation']);
    Route::get('/research', [ResearchController::class, 'index']);
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/game/support-tickets', [\App\Http\Controllers\Api\SupportController::class, 'index']);
    Route::post('/game/support-tickets/{id}/investigate', [\App\Http\Controllers\Api\SupportController::class, 'investigate']);
    
    // Network & Visibility
    Route::get('/network/ipv4-market', [NetworkController::class, 'getIpv4Market']);
    Route::get('/network/ddos-tiers', [NetworkController::class, 'getDdosTiers']);
    Route::get('/network/isps', [NetworkController::class, 'getAvailableIsps']);
    Route::get('/network/dark-fiber/options', [NetworkController::class, 'getDarkFiberOptions']);
    Route::post('/network/dark-fiber/lease', [NetworkController::class, 'leaseDarkFiber']);
    Route::get('/network/peering/partners', [PeeringController::class, 'getPotentialPartners']);
    Route::get('/network/private', [PrivateNetworkController::class, 'index']);
    
    // Room & Economy Visibility
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/economy/transactions', [EconomyController::class, 'transactions']);
    Route::get('/economy/history', [EconomyController::class, 'history']);
    
    // Employee & Management Visibility
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/management/specializations', [ManagementController::class, 'getSpecializations']);
    Route::get('/management/skills', [ManagementController::class, 'getSkillTree']);
    Route::get('/management/board', [ManagementController::class, 'getBoardState']);
    Route::get('/stats/history', [StatsController::class, 'history']);
    Route::get('/performance/report', [PerformanceController::class, 'getReport']);
    
    // Energy Visibility
    Route::get('/energy', [EnergyController::class, 'index']);
    
    // Simulation & Market Visibility
    Route::get('/api-simulation', [ApiSimulationController::class, 'index']);
    Route::get('/market', [MarketController::class, 'index']);
    Route::get('/market/used', [MarketController::class, 'getUsedListings']);
    Route::get('/market/history', [MarketController::class, 'getDemandHistory']);
    
    // Negotiation visibility
    Route::post('/negotiation/{id}/preview', [NegotiationController::class, 'previewProbability']);

    // Profile & Meta
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::get('/profile/sessions', [ProfileController::class, 'sessions']);
    Route::get('/marketing', [MarketingController::class, 'index']);
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/leaderboard/me', [LeaderboardController::class, 'myRank']);
    Route::get('/leaderboard/weekly', [LeaderboardController::class, 'weeklyHistory']);
    Route::get('/world-events/active', [WorldEventController::class, 'getActive']);
    Route::get('/compliance', [\App\Http\Controllers\Api\ComplianceController::class, 'index']);
    
    // Sabotage & Multiplayer visibility
    Route::get('/sabotage', [SabotageController::class, 'index']);
    Route::prefix('multiplayer')->group(function () {
        Route::get('/rentals/available', [MultiplayerController::class, 'getAvailableRentals']);
        Route::get('/rentals/my', [MultiplayerController::class, 'getMyRentals']);
        Route::get('/leaderboard', [MultiplayerController::class, 'getLeaderboard']);
    });

    // Admin Panel (Obsidian Architecture)
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/configs', [AdminController::class, 'getConfigs']);
        Route::post('/configs/update', [AdminController::class, 'updateConfig']);
        Route::get('/world-news/templates', [AdminController::class, 'getWorldNewsTemplates']);
        Route::post('/world-news/templates', [AdminController::class, 'saveWorldNewsTemplates']);
        Route::post('/world-news/trigger', [AdminController::class, 'triggerWorldEvent']);
        Route::get('/energy/market', [AdminController::class, 'getEnergyMarket']);

        // User Management
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::post('/users/{id}/update', [AdminController::class, 'updateUser']);
        Route::post('/users/{id}/give', [AdminController::class, 'giveResource']);
        Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
        Route::post('/users/{id}/unban', [AdminController::class, 'unbanUser']);

        // Achievements
        Route::get('/achievements', [AdminController::class, 'getAchievements']);
        Route::post('/achievements/save', [AdminController::class, 'saveAchievement']);
        Route::delete('/achievements/{id}', [AdminController::class, 'deleteAchievement']);

        // Live Ops & Audit
        Route::get('/stats', [AdminController::class, 'getLiveOpsStats']);
        Route::get('/logs/global', [AdminController::class, 'getMissionControlLogs']);
        Route::get('/configs/history/{key}', [AdminController::class, 'getConfigHistory']);
        Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);
        Route::post('/configs/rollback', [AdminController::class, 'rollbackConfig']);

        // Simulation
        Route::post('/simulation/spike', [AdminController::class, 'simulateLoadSpike']);
        Route::post('/simulation/broadcast', [AdminController::class, 'simulateBroadcast']);
        Route::post('/simulation/run-24h', [AdminController::class, 'run24hSimulation']);
        Route::post('/simulation/run', [AdminController::class, 'runSimulation']);
        Route::post('/simulation/trigger-crisis', [AdminController::class, 'triggerCrisis']);

        // Game Data Management — Entity CRUD
        Route::get('/entity-counts', [AdminController::class, 'getEntityCounts']);

        // Servers
        Route::get('/servers', [AdminController::class, 'getServers']);
        Route::post('/servers/{id}/update', [AdminController::class, 'updateServer']);
        Route::delete('/servers/{id}', [AdminController::class, 'deleteServer']);

        // Racks
        Route::get('/racks', [AdminController::class, 'getRacks']);
        Route::post('/racks/{id}/update', [AdminController::class, 'updateRack']);
        Route::delete('/racks/{id}', [AdminController::class, 'deleteRack']);

        // Rooms
        Route::get('/rooms', [AdminController::class, 'getRooms']);
        Route::post('/rooms/{id}/update', [AdminController::class, 'updateRoom']);
        Route::delete('/rooms/{id}', [AdminController::class, 'deleteRoom']);

        // Customers
        Route::get('/customers', [AdminController::class, 'getCustomers']);
        Route::post('/customers/{id}/update', [AdminController::class, 'updateCustomer']);
        Route::delete('/customers/{id}', [AdminController::class, 'deleteCustomer']);

        // Orders
        Route::get('/orders', [AdminController::class, 'getOrders']);
        Route::post('/orders/{id}/update', [AdminController::class, 'updateOrder']);
        Route::delete('/orders/{id}', [AdminController::class, 'deleteOrder']);

        // Events
        Route::get('/events', [AdminController::class, 'getEvents']);
        Route::post('/events/{id}/update', [AdminController::class, 'updateEvent']);
        Route::post('/events/{id}/resolve', [AdminController::class, 'resolveEvent']);
        Route::delete('/events/{id}', [AdminController::class, 'deleteEvent']);

        // Economy
        Route::get('/economies', [AdminController::class, 'getEconomies']);
        Route::post('/economies/{id}/update', [AdminController::class, 'updateEconomy']);

        // Game Logs
        Route::get('/game-logs', [AdminController::class, 'getGameLogs']);

        // Auctions
        Route::get('/auctions', [AdminController::class, 'getAuctions']);
        Route::post('/auctions/trigger', [AdminController::class, 'triggerLiquidationAuction']);
        Route::delete('/auctions/{id}', [AdminController::class, 'deleteAuction']);
    });
});
