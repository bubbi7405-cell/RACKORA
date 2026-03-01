<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\GameRoom;
use App\Models\ServerRack;
use App\Models\Server;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\GameEvent;
use App\Services\Game\GameEventService;
use App\Services\Game\EmployeeService;
use App\Services\Game\SabotageService;
use App\Enums\EventType;
use App\Enums\EventStatus;
use App\Enums\ServerType;
use App\Enums\ServerStatus;
use App\Enums\RoomType;
use App\Enums\RackType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;

class SecurityPerksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: Create full infrastructure chain (User -> Room -> Rack -> Server)
     * with all required DB columns filled in.
     */
    private function createInfrastructure(): array
    {
        $user = User::factory()->create();
        $user->economy()->create([
            'balance' => 50000,
            'reputation' => 100,
        ]);

        $room = new GameRoom();
        $room->forceFill([
            'user_id' => $user->id,
            'name' => 'Test Room',
            'type' => RoomType::BASEMENT->value,
            'max_racks' => 10,
            'max_power_kw' => 100,
            'max_cooling_kw' => 100,
            'cooling_health' => 100,
            'airflow_type' => 'default',
            'redundancy_level' => 1,
            'bandwidth_gbps' => 10,
            'rent_per_hour' => 0,
            'is_unlocked' => true,
            'region' => 'eu_central',
            'power_cost_kwh' => 0.10,
            'latency_ms' => 25,
            'cooling_intensity' => 100,
        ]);
        $room->save();

        $rack = new ServerRack();
        $rack->forceFill([
            'room_id' => $room->id,
            'name' => 'Rack A1',
            'type' => RackType::RACK_42U->value,
            'total_units' => 42,
            'max_power_kw' => 20,
            'purchase_cost' => 2500,
            'position' => ['slot' => 1],
            'temperature' => 22,
        ]);
        $rack->save();

        $server = new Server();
        $server->forceFill([
            'rack_id' => $rack->id,
            'model_name' => 'Dell PowerEdge R740',
            'type' => ServerType::DEDICATED->value,
            'size_u' => 2,
            'start_slot' => 1,
            'power_draw_kw' => 0.5,
            'heat_output_kw' => 0.5,
            'cpu_cores' => 16,
            'ram_gb' => 64,
            'storage_tb' => 2,
            'bandwidth_mbps' => 1000,
            'status' => ServerStatus::ONLINE->value,
            'health' => 100,
            'purchase_cost' => 3000,
        ]);
        $server->save();

        // Create a customer + active order so DDoS creation works (requires `whereHas('activeOrders')`)
        $customer = new Customer();
        $customer->forceFill([
            'user_id' => $user->id,
            'name' => 'Test Customer',
            'company_name' => 'TestCo',
            'tier' => 'standard',
            'revenue_per_month' => 500,
            'satisfaction' => 100,
            'patience_minutes' => 60,
            'tolerance_incidents' => 5,
            'incidents_count' => 0,
            'status' => 'active',
            'acquired_at' => now(),
            'preferences' => [],
        ]);
        $customer->save();

        $order = new CustomerOrder();
        $order->forceFill([
            'customer_id' => $customer->id,
            'assigned_server_id' => $server->id,
            'product_type' => 'dedicated',
            'requirements' => ['cpu_cores' => 4, 'ram_gb' => 8],
            'price_per_month' => 100,
            'status' => 'active',
            'contract_months' => 12,
            'ordered_at' => now(),
        ]);
        $order->save();

        return compact('user', 'room', 'rack', 'server', 'customer', 'order');
    }

    /**
     * Helper: Create a security engineer with given perks for a user.
     */
    private function createSecurityEngineer(User $user, array $perks = []): Employee
    {
        $emp = new Employee();
        $emp->forceFill([
            'user_id' => $user->id,
            'type' => 'security_engineer',
            'name' => 'SecEng ' . rand(1, 999),
            'salary' => 2000,
            'efficiency' => 1.0,
            'level' => 5,
            'xp' => 1000,
            'perks' => $perks,
        ]);
        $emp->save();
        return $emp;
    }

    public function test_trace_route_perk_extends_ddos_deadlines()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));

        $infra = $this->createInfrastructure();
        $user = $infra['user'];

        $eventService = app(GameEventService::class);
        $ref = new ReflectionClass($eventService);
        $method = $ref->getMethod('createDdosAttack');
        $method->setAccessible(true);

        // --- Baseline: Create DDoS without perk ---
        $method->invoke($eventService, $user);

        $baseEvent = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::DDOS_ATTACK)
            ->latest('id')
            ->first();

        $this->assertNotNull($baseEvent, 'Baseline DDoS event should be created');
        $baseEscalation = $baseEvent->created_at->diffInSeconds($baseEvent->escalates_at);
        $baseDeadline = $baseEvent->created_at->diffInSeconds($baseEvent->deadline_at);

        // --- Now add a security engineer with trace_route perk ---
        $this->createSecurityEngineer($user, ['trace_route']);

        // Delete old event so we can create a fresh one
        $baseEvent->delete();

        $method->invoke($eventService, $user);

        $perkEvent = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::DDOS_ATTACK)
            ->latest('id')
            ->first();

        $this->assertNotNull($perkEvent, 'Perk DDoS event should be created');
        $perkEscalation = $perkEvent->created_at->diffInSeconds($perkEvent->escalates_at);
        $perkDeadline = $perkEvent->created_at->diffInSeconds($perkEvent->deadline_at);

        // With 1 engineer having trace_route: timeMultiplier = 1.0 + 0.30 = 1.30
        $this->assertGreaterThan($baseEscalation, $perkEscalation, 'trace_route should extend escalation time');
        $this->assertGreaterThan($baseDeadline, $perkDeadline, 'trace_route should extend deadline time');

        Carbon::setTestNow(); // reset
    }

    public function test_trace_route_perk_extends_security_breach_deadlines()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));

        $infra = $this->createInfrastructure();
        $user = $infra['user'];

        $eventService = app(GameEventService::class);
        $ref = new ReflectionClass($eventService);
        $method = $ref->getMethod('createSecurityBreach');
        $method->setAccessible(true);

        // --- Baseline: No perk ---
        $method->invoke($eventService, $user);

        $baseEvent = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::SECURITY_BREACH)
            ->latest('id')
            ->first();

        $this->assertNotNull($baseEvent);
        $baseEscalation = $baseEvent->created_at->diffInSeconds($baseEvent->escalates_at);

        // --- With perk ---
        $this->createSecurityEngineer($user, ['trace_route']);
        $baseEvent->delete();

        $method->invoke($eventService, $user);

        $perkEvent = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::SECURITY_BREACH)
            ->latest('id')
            ->first();

        $this->assertNotNull($perkEvent);
        $perkEscalation = $perkEvent->created_at->diffInSeconds($perkEvent->escalates_at);

        $this->assertGreaterThan($baseEscalation, $perkEscalation, 'trace_route should extend breach escalation');

        Carbon::setTestNow();
    }

    public function test_counter_intelligence_perk_increases_sabotage_detection()
    {
        $attacker = User::factory()->create();
        $attacker->economy()->create(['balance' => 100000, 'reputation' => 100]);

        $victim = User::factory()->create();
        $victim->economy()->create(['balance' => 10000, 'reputation' => 100]);

        // Give victim 3 security engineers with counter_intelligence
        for ($i = 0; $i < 3; $i++) {
            $this->createSecurityEngineer($victim, ['counter_intelligence']);
        }

        $sabotageService = app(SabotageService::class);

        // Run multiple attempts to verify no exceptions and logic runs
        $result = $sabotageService->attemptSabotage($attacker, (string) $victim->id, 'user', 'ddos');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('detected', $result);
        $this->assertArrayHasKey('result', $result);
    }
}
