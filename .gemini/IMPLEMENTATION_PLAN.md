# RACKORA — Enterprise Evolution Plan

**Version:** 2.0  
**Codename:** CONTROL_ROOM  
**Architecture:** Laravel 11 + Vue 3 Composition API + Reverb WebSocket  
**Design Philosophy:** Bloomberg Terminal meets Datacenter Control Room  

---

## CURRENT STATE AUDIT

### Backend Architecture (Solid Foundation)
```
app/Services/Game/
├── GameLoopService.php        (1394 lines — Core tick engine, processes all systems)
├── NetworkService.php         (225 lines — Basic IP/bandwidth/DDoS)
├── RackManagementService.php  (723 lines — Server placement, movement, power)
├── CustomerOrderService.php   (21K — Order lifecycle)
├── GameEventService.php       (49K — Event system with escalation)
├── ResearchService.php        (17K — Tech tree with unlock effects)
├── EnergyService.php          (6K — Power contracts)
├── ComplianceService.php      (9K — Audit/compliance system)
├── MarketService.php          (12K — IPv4 market, used hardware)
├── HardwareDepreciationService.php
├── AchievementService.php
├── EmployeeService.php
├── GlobalCrisisService.php
├── SabotageService.php
└── ... (25 services total)
```

### Frontend Architecture (Needs Refactoring)
```
resources/js/
├── stores/game.js             (1281 lines — Monolithic store, needs splitting)
├── components/
│   ├── Game/
│   │   ├── NetworkView.vue    (1310 lines — Already has NOC structure)
│   │   ├── GameWorld.vue      (539 lines — Rack layout + room context)
│   │   ├── InfrastructureView.vue (350 lines)
│   │   └── ... 
│   ├── HUD/                   (10 components — Topbar, Sidebar, Panels)
│   ├── Overlay/               (34 overlay components)
│   └── Rack/
│       └── RackComponent.vue  (389 lines — Has drag/drop, thermal, power)
├── services/echo.js           (101 lines — Reverb WebSocket setup)
└── composables/               (empty — needs populating)
```

### Data Layer (Models)
```
app/Models/
├── PlayerNetwork.php          (Has: IPv4/v6 pools, ASN, peering, DDoS, metrics)
├── Server.php                 (Has: specs, health, aging, status, generation)
├── ServerRack.php             (Has: power, temperature, dust, slots)
├── GameRoom.php               (Has: type, power grid, cooling, bandwidth)
├── CustomerOrder.php          (Has: requirements, SLA, assigned IPs)
├── PlayerEconomy.php          (Has: balance, level, XP, reputation, skills)
├── GameEvent.php              (Has: type, severity, escalation, actions)
└── ... (31 models total)
```

### WebSocket Layer (Functional)
- **Backend:** Laravel Reverb (production-ready)  
- **Frontend:** Laravel Echo via Pusher protocol  
- **Channels:** Private `game.{userId}`, Public `world-events`  
- **Events:** 12 broadcast events (tick, rack, server, economy, events, research)

### Existing Network Features
- ✅ IPv4/IPv6 pool tracking
- ✅ ASN system with peering levels (0-2)
- ✅ DDoS protection tiers (0-3)
- ✅ IPv4 Market (buy/sell blocks)
- ✅ Bandwidth saturation calculation
- ✅ Dynamic latency/packet loss simulation
- ✅ SLA compliance scoring
- ✅ Network reputation
- ⚠️ Missing: Subnet allocation, ISP contracts, regional latency, bandwidth contracts
- ⚠️ Missing: Network overload → reputation/churn cascade

---

## PHASE 1: ARCHITECTURE FOUNDATION

**Timeline:** First Priority  
**Goal:** Establish scalable architecture patterns before feature work

### 1.1 Store Decomposition

Split the monolithic `game.js` (1281 lines) into domain stores:

```
resources/js/stores/
├── game.js              → Keep as orchestrator (300 lines max)
├── infrastructure.js    → Rooms, racks, servers, placement
├── network.js           → IP pools, bandwidth, NOC metrics
├── economy.js           → Balance, transactions, contracts
├── events.js            → Incidents, alerts, escalation
├── research.js          → Tech tree, unlocks
├── employees.js         → Staff management
└── ui.js                → Selection state, panels, overlays
```

**Pattern:** Each store follows:
```js
export const useNetworkStore = defineStore('network', () => {
    // State (reactive)
    const state = reactive({ ... });
    
    // Getters (computed)
    const isOverloaded = computed(() => ...);
    
    // Actions (async functions)
    async function purchaseIpBlock() { ... }
    
    // WebSocket handlers
    function handleTick(data) { ... }
    
    return { state, isOverloaded, purchaseIpBlock, handleTick };
});
```

### 1.2 Composables Library

Populate `resources/js/composables/` with reusable logic:

```
composables/
├── useWebSocket.js      → Channel subscription + auto-cleanup
├── useTelemetry.js      → Live value tracking with sparkline data
├── useKpiColor.js       → Threshold-based color system
├── useAnimatedValue.js  → Smooth number transitions (tweening)
├── useIncidentFeed.js   → Real-time incident stream
├── useDragDrop.js       → Server placement drag/drop logic
├── useNetworkMetrics.js → Network-specific calculations
└── useFormatters.js     → Currency, bytes, latency formatting
```

**Key composable: `useTelemetry`**
```js
// Tracks a value over time, generates sparkline data
export function useTelemetry(getter, options = { historyLength: 60 }) {
    const current = computed(getter);
    const history = ref([]);
    const trend = computed(() => /* rising/falling/stable */);
    const sparkData = computed(() => /* normalized 0-1 array */);
    
    watch(current, (val) => {
        history.value.push({ value: val, time: Date.now() });
        if (history.value.length > options.historyLength) history.value.shift();
    });
    
    return { current, history, trend, sparkData };
}
```

### 1.3 WebSocket Event Architecture

Extend `echo.js` with typed event handling:

```
services/
├── echo.js              → Base connection (keep as-is)
├── channels/
│   ├── GameChannel.js   → Private game channel handler
│   ├── WorldChannel.js  → Public world events handler
│   └── NetworkChannel.js → Network-specific events (future)
```

**Backend WebSocket events to add:**
```
app/Events/
├── NetworkMetricsUpdated.php    → Bandwidth, latency, packet loss changes
├── NetworkIncident.php          → DDoS, overload, IP exhaustion
├── KpiThresholdBreached.php     → Critical KPI changes
└── SystemFeedback.php           → Generic alert/warning/info broadcasts
```

### 1.4 Component Architecture

Refactor component tree:

```
components/
├── Layout/                      → Shell components
│   ├── AppShell.vue             → Main layout container
│   ├── CommandBar.vue           → Top bar with KPIs
│   ├── NavigationRail.vue       → Left sidebar navigation
│   └── StatusBar.vue            → Bottom system status
│
├── Dashboard/                   → Main view panels
│   ├── NetworkOperations.vue    → NOC (refactored from NetworkView)
│   ├── InfrastructureOps.vue    → Rack/server management
│   ├── FinancialOps.vue         → Economy dashboard
│   └── SystemIntel.vue          → Analytics/stats
│
├── Network/                     → Network-specific components
│   ├── IpPoolWidget.vue         → IP allocation visualization
│   ├── BandwidthGauge.vue       → Real-time bandwidth meter
│   ├── LatencyMap.vue           → Regional latency heatmap
│   ├── DdosShield.vue           → DDoS protection panel
│   ├── SubnetManager.vue        → Subnet allocation table
│   ├── IspSelector.vue          → ISP provider selection
│   └── TrafficFlow.vue          → Live traffic visualization
│
├── Infrastructure/              → Rack/hardware components
│   ├── RackUnit.vue             → Single rack (enhanced)
│   ├── ServerBlade.vue          → Server in rack slot
│   ├── HeatMap.vue              → Per-slot thermal overlay
│   ├── PowerDraw.vue            → Power gradient visualization
│   └── StressIndicator.vue      → Capacity stress meter
│
├── Feedback/                    → System feedback layer
│   ├── IncidentBanner.vue       → Escalation alerts
│   ├── KpiGauge.vue             → Animated KPI with thresholds
│   ├── AlertPulse.vue           → Subtle alert animations
│   ├── TelemetryLine.vue        → Live sparkline component
│   └── StatusIndicator.vue      → State light (green/amber/red)
│
└── Primitives/                  → Base UI components
    ├── DataCard.vue             → Standard metric card
    ├── ProgressBar.vue          → Segmented progress bar
    ├── NumberDisplay.vue        → Animated number with unit
    ├── MiniChart.vue            → Sparkline/bar chart
    └── GlassPanel.vue          → Panel with glassmorphism
```

---

## PHASE 2: ADVANCED NETWORK SYSTEM

**Timeline:** After Phase 1  
**Goal:** Deep network simulation that directly affects gameplay

### 2.1 Database Migrations

```php
// Migration: expand_player_networks_table
Schema::table('player_networks', function (Blueprint $table) {
    // Subnet Management
    $table->json('ipv4_subnets')->nullable();        // [{cidr: '/24', allocated: 200, total: 256}]
    $table->json('ipv6_subnets')->nullable();
    
    // ISP & Bandwidth Contracts
    $table->string('isp_provider')->default('generic_transit');
    $table->integer('bandwidth_contract_mbps')->default(1000);
    $table->decimal('bandwidth_contract_cost', 10, 2)->default(50.00);
    $table->string('bandwidth_tier')->default('standard');    // standard, premium, enterprise
    
    // Regional Presence
    $table->json('regional_latency')->nullable();     // {eu: 20, us: 80, asia: 150}
    $table->json('regional_presence')->nullable();    // Which regions have PoPs
    
    // Advanced Metrics
    $table->decimal('jitter_ms', 8, 2)->default(0);
    $table->integer('bgp_routes_announced')->default(0);
    $table->decimal('traffic_in_gbps', 10, 2)->default(0);
    $table->decimal('traffic_out_gbps', 10, 2)->default(0);
    $table->timestamp('last_ddos_at')->nullable();
    $table->integer('ddos_events_total')->default(0);
});
```

```php
// Migration: create_bandwidth_contracts_table
Schema::create('bandwidth_contracts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('isp_name');                       // "Tier-1 Global", "Regional Transit"
    $table->integer('capacity_mbps');
    $table->decimal('monthly_cost', 10, 2);
    $table->string('commitment');                     // monthly, annual, multi-year
    $table->decimal('burst_ratio', 4, 2)->default(1.0);
    $table->json('regions')->nullable();              // Which regions this contract covers
    $table->string('status')->default('active');
    $table->timestamps();
});
```

```php
// Migration: create_ip_allocations_table  
Schema::create('ip_allocations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('server_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignUuid('order_id')->nullable();
    $table->string('type');                           // ipv4, ipv6
    $table->string('address');                        // 10.0.1.1
    $table->string('subnet_cidr')->nullable();        // /24
    $table->string('purpose')->default('server');     // server, customer, management
    $table->string('status')->default('allocated');   // allocated, available, reserved
    $table->timestamps();
});
```

### 2.2 Enhanced NetworkService

**File:** `app/Services/Game/NetworkService.php` — Expand significantly

New methods to add:
```php
// Subnet Management
public function allocateSubnet(User $user, string $type, int $size): array
public function releaseSubnet(User $user, string $subnetId): array
public function getSubnetUtilization(User $user): array

// ISP & Bandwidth
public function getAvailableIsps(): array           // List ISP options with pricing
public function switchIsp(User $user, string $isp): array
public function upgradeBandwidth(User $user, string $tier): array
public function getBandwidthContracts(User $user): array

// Regional Latency
public function calculateRegionalLatency(User $user, string $region): float
public function getLatencyMatrix(User $user): array  // All regions

// Traffic Simulation  
public function calculateServerTraffic(Server $server): array
public function getAggregateTraffic(User $user): array

// Network Health
public function getNetworkHealthScore(User $user): float
public function checkForNetworkIncidents(User $user): array
```

**Enhanced tick() method:**
```php
public function tick(User $user): void
{
    $network = $user->network;
    $state = $this->getNetworkGameState($network);
    
    // 1. Calculate real-time traffic from all active servers
    $traffic = $this->getAggregateTraffic($user);
    $network->traffic_in_gbps = $traffic['in'];
    $network->traffic_out_gbps = $traffic['out'];
    
    // 2. Bandwidth overload detection
    $totalTraffic = $traffic['in'] + $traffic['out'];
    $capacity = $network->bandwidth_contract_mbps / 1000;
    $overloadRatio = $totalTraffic / max(0.001, $capacity);
    
    if ($overloadRatio > 1.0) {
        // Network congestion cascade
        $this->triggerCongestionEffects($user, $overloadRatio);
    }
    
    // 3. IP exhaustion check
    if ($network->getIpv4UsagePercent() > 90) {
        $this->triggerIpExhaustionWarning($user);
    }
    
    // 4. DDoS risk calculation (higher with more servers, reputation)
    $this->evaluateDdosRisk($user, $network);
    
    // 5. Regional latency updates
    $this->updateRegionalLatency($user, $network);
    
    // 6. Reputation decay/recovery
    $this->processReputationDrift($user, $network, $state);
    
    $network->save();
}
```

### 2.3 Network Overload → Business Impact Cascade

```php
private function triggerCongestionEffects(User $user, float $overloadRatio): void
{
    $severity = match(true) {
        $overloadRatio > 2.0 => 'critical',
        $overloadRatio > 1.5 => 'warning',
        default => 'info',
    };
    
    // 1. Reputation penalty (proportional to overload)
    $repPenalty = ($overloadRatio - 1.0) * 5.0;
    $user->economy->adjustReputation(-$repPenalty);
    
    // 2. Trigger incident alert
    GameEvent::createNetworkIncident($user, [
        'type' => 'bandwidth_congestion',
        'severity' => $severity,
        'overload_ratio' => $overloadRatio,
    ]);
    
    // 3. Increase churn risk for active customers
    CustomerOrder::where('user_id', $user->id)
        ->where('status', 'active')
        ->update(['churn_risk' => DB::raw("LEAST(churn_risk + {$repPenalty}, 100)")]);
    
    // 4. SLA violations
    if ($overloadRatio > 1.5) {
        $this->processSlaViolations($user, $overloadRatio);
    }
}
```

### 2.4 Server → Network Integration

Every server must participate in the network:

```php
// In GameLoopService::processServers() — add network effects
foreach ($onlineServers as $server) {
    // Each server generates traffic based on active orders
    $orderCount = $server->activeOrders()->count();
    $trafficMbps = $server->bandwidth_mbps * ($orderCount / max(1, $server->vserver_capacity ?: 1));
    
    // Server must have IP allocation
    if (!$server->hasIpAllocation()) {
        $server->status = ServerStatus::ERROR;
        $server->save();
        // Trigger alert: "Server {name} has no IP allocation"
        continue;
    }
    
    // Network stability affects server performance
    $packetLoss = $network->avg_packet_loss;
    if ($packetLoss > 0.01) {
        // Reduce order satisfaction based on packet loss
        $satisfactionPenalty = $packetLoss * 100;
    }
}
```

---

## PHASE 3: UI SYSTEM UPGRADE

**Timeline:** Parallel with Phase 2  
**Goal:** Bloomberg Terminal aesthetic with enterprise telemetry feel

### 3.1 Design Token System

**File:** `resources/css/design-system.css`

```css
:root {
    /* === BASE PALETTE === */
    --ds-bg-void:        hsl(222, 30%, 5%);        /* Deepest background */
    --ds-bg-base:        hsl(222, 25%, 8%);        /* Primary surface */
    --ds-bg-elevated:    hsl(222, 22%, 11%);       /* Cards, panels */
    --ds-bg-overlay:     hsl(222, 20%, 14%);       /* Hover states, modals */
    --ds-bg-subtle:      hsl(222, 18%, 17%);       /* Active states */
    
    /* === TEXT HIERARCHY === */
    --ds-text-primary:   hsl(210, 20%, 92%);       /* Main content */
    --ds-text-secondary: hsl(215, 15%, 60%);       /* Labels, descriptions */
    --ds-text-muted:     hsl(220, 12%, 40%);       /* Disabled, hints */
    --ds-text-ghost:     hsl(220, 10%, 25%);       /* Structural guides */
    
    /* === ACCENT SYSTEM === */
    --ds-accent:         hsl(220, 85%, 60%);       /* Primary blue */
    --ds-accent-soft:    hsla(220, 85%, 60%, 0.08);
    --ds-accent-glow:    hsla(220, 85%, 60%, 0.25);
    
    /* === SEMANTIC COLORS (KPI-driven) === */
    --ds-nominal:        hsl(152, 60%, 50%);       /* Green — all good */
    --ds-caution:        hsl(40, 85%, 55%);        /* Amber — attention */
    --ds-warning:        hsl(28, 90%, 55%);        /* Orange — degraded */
    --ds-critical:       hsl(0, 75%, 55%);         /* Red — failure */
    --ds-info:           hsl(200, 75%, 55%);       /* Cyan — informational */
    
    /* === SEMANTIC GLOWS === */
    --ds-glow-nominal:   hsla(152, 60%, 50%, 0.15);
    --ds-glow-caution:   hsla(40, 85%, 55%, 0.15);
    --ds-glow-critical:  hsla(0, 75%, 55%, 0.15);
    
    /* === BORDERS === */
    --ds-border-subtle:  1px solid hsla(220, 20%, 30%, 0.3);
    --ds-border-strong:  1px solid hsla(220, 20%, 30%, 0.6);
    --ds-border-accent:  1px solid hsla(220, 85%, 60%, 0.3);
    
    /* === TYPOGRAPHY === */
    --ds-font-sans:      'Inter', -apple-system, sans-serif;
    --ds-font-mono:      'JetBrains Mono', 'SF Mono', monospace;
    
    /* === SPACING SCALE === */
    --ds-space-xs:  4px;
    --ds-space-sm:  8px;
    --ds-space-md:  12px;
    --ds-space-lg:  16px;
    --ds-space-xl:  24px;
    --ds-space-2xl: 32px;
    --ds-space-3xl: 48px;
    
    /* === ANIMATION === */
    --ds-ease-out:    cubic-bezier(0.25, 0.46, 0.45, 0.94);
    --ds-ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --ds-duration-fast:   150ms;
    --ds-duration-normal: 300ms;
    --ds-duration-slow:   600ms;
    
    /* === EFFECTS === */
    --ds-shadow-card:    0 2px 8px hsla(0, 0%, 0%, 0.3);
    --ds-shadow-panel:   0 4px 24px hsla(0, 0%, 0%, 0.4);
    --ds-shadow-glow:    0 0 20px var(--ds-accent-glow);
}
```

### 3.2 Live Telemetry Pulse Component

**File:** `components/Feedback/TelemetryLine.vue`

A horizontally scrolling SVG sparkline that receives live data:

```vue
<!-- Renders a live-updating SVG polyline sparkline -->
<!-- Input: :data="sparkData" (array of 0-1 normalized values) -->
<!-- Input: :color="'var(--ds-nominal)'" -->
<!-- Input: :height="32" -->
<!-- Features: Smooth polyline, subtle pulse animation, gradient fill -->
```

### 3.3 KPI Color Shift System

**Composable:** `composables/useKpiColor.js`

```js
/**
 * Returns reactive CSS color based on value thresholds.
 * Used for all metrics: temperature, power, bandwidth, etc.
 * 
 * @param {ComputedRef<number>} value - The current metric value
 * @param {Object} thresholds - { nominal: 70, caution: 85, warning: 95 }
 * @returns {{ color, glow, label, severity }}
 */
export function useKpiColor(value, thresholds = {}) {
    const severity = computed(() => {
        const v = value.value;
        if (v >= (thresholds.warning ?? 95)) return 'critical';
        if (v >= (thresholds.caution ?? 85)) return 'warning';  
        if (v >= (thresholds.nominal ?? 70)) return 'caution';
        return 'nominal';
    });
    
    const color = computed(() => `var(--ds-${severity.value})`);
    const glow = computed(() => `var(--ds-glow-${severity.value})`);
    
    return { severity, color, glow };
}
```

### 3.4 Animated Number Display

**Component:** `components/Primitives/NumberDisplay.vue`

```
Props: value, prefix, suffix, decimals, duration, flashOnChange
Features:
- Smooth tweening between old and new values
- Brief color flash on significant changes (>5%)
- Monospace font for digit stability
- Optional trend arrow (↑↓)
```

### 3.5 Background Grid Effect

Subtle animated dot grid behind the main viewport:

```css
.control-room-bg {
    background-image: 
        radial-gradient(circle at 1px 1px, 
            hsla(220, 20%, 30%, 0.15) 1px, 
            transparent 0);
    background-size: 24px 24px;
    animation: gridPulse 4s ease-in-out infinite alternate;
}

@keyframes gridPulse {
    0% { opacity: 0.3; }
    100% { opacity: 0.5; }
}
```

---

## PHASE 4: RACK SYSTEM V2

**Timeline:** After Phase 2 core  
**Goal:** Visually rich rack visualization with operational intelligence

### 4.1 Enhanced RackComponent

Upgrade `RackComponent.vue` with:

#### Heat Spread Visualization
```
- Each slot gets a thermal overlay color based on adjacent servers
- Heat bleeds upward (hot air rises) from high-power servers
- CSS gradient that shifts from blue→amber→red based on local temperature
- Thermal hotspots pulse subtly when above threshold
```

#### Power Draw Gradient
```
- Vertical gradient bar on rack side showing power distribution
- Color shifts from green (low draw) → amber → red (near capacity)  
- Shows instantaneous vs. peak power
- Animated when server powers on/off
```

#### Slot Capacity Stress Indicator
```
- Visual indicator when rack is >80% full (U slots used)
- Subtle border glow that intensifies with fullness
- "CAPACITY_WARNING" label appears at 90%+
```

#### Hardware Generation Tags
```
- Each server blade shows "GEN_1", "GEN_2", "GEN_3" badge
- Color-coded: Gen1=gray, Gen2=blue, Gen3=gold
- Older generations get a subtle "aged" visual treatment
```

#### Service Tier Indicators
```
- Visual badges on server blades showing assigned service tier
- VPS = blue tag, Dedicated = green, GPU = purple, Storage = orange
```

### 4.2 Drag & Drop Enhancements

Current drag/drop already works. Enhance with:

```
- Swap mode: Drop server on occupied slot → swap positions
- Replace mode: Drag catalog item onto existing server → upgrade dialog
- Visual guides: Show valid drop zones with green highlights
- Size preview: Ghost outline showing how many U the dragged item occupies
- Cross-rack: Allow dragging servers between different racks
```

### 4.3 Inline Hardware Actions

```
- Click server → Right panel shows "DEEP_INSPECTION_MODE"  
- "UPGRADE_GENERATION" button → Pay cost to bump Gen 1→2→3
- "SWAP_COMPONENT" → Replace CPU/RAM without removing server
- "MIGRATE_WORKLOADS" → Move all orders to another server before replacing
```

### 4.4 Rack Voltage Class Upgrade

```
Backend: Add rack_voltage_class to ServerRack model
  - 'standard' (default) → max 3.0 kW per rack
  - 'high_voltage' → max 8.0 kW per rack (unlocked by research)
  - 'enterprise_pdu' → max 15.0 kW per rack (level 15+)

UI: Button on rack panel "UPGRADE_POWER_DELIVERY" with cost
```

---

## PHASE 5: SYSTEM FEEDBACK LAYER

**Timeline:** Continuous refinement  
**Goal:** Professional, realistic system feedback without cartoon-like alerts

### 5.1 Incident Escalation Logic

```
Severity Levels:
  INFO    → Logged, no visual change
  NOTICE  → Brief pulse on status indicator
  WARNING → Amber glow on affected KPIs + ticker message
  CRITICAL → Red pulse animation + incident banner + sound cue
  EMERGENCY → Full-screen overlay + all KPIs flash

Escalation Flow:
  1. Event fires → starts at INFO/NOTICE
  2. If unresolved after 60s → escalates to WARNING
  3. If unresolved after 120s → escalates to CRITICAL  
  4. If unresolved after 300s → triggers EMERGENCY + auto-reputation-loss
  5. Each escalation level broadcasts a WebSocket event
```

### 5.2 KPI-Based Color Shift System

All numeric displays use the `useKpiColor` composable:

```
Metric                  | Nominal   | Caution  | Warning  | Critical
------------------------|-----------|----------|----------|----------
Temperature (°C)        | < 30      | 30-38    | 38-45    | > 45
Power (% capacity)      | < 70%     | 70-85%   | 85-95%   | > 95%
Bandwidth (saturation)  | < 70%     | 70-85%   | 85-95%   | > 95%
Packet Loss             | 0%        | 0.01-0.1%| 0.1-1%   | > 1%
Latency (ms)            | < 30      | 30-60    | 60-100   | > 100
SLA Compliance          | > 99%     | 97-99%   | 95-97%   | < 95%
Server Health           | > 80%     | 60-80%   | 30-60%   | < 30%
Reputation              | > 90      | 70-90    | 50-70    | < 50
IP Utilization          | < 70%     | 70-85%   | 85-95%   | > 95%
```

### 5.3 Alert Pulse Animation

```css
/* Soft pulse for warning-level indicators */
@keyframes alertPulse {
    0%, 100% { box-shadow: 0 0 0 0 var(--ds-glow-caution); }
    50% { box-shadow: 0 0 12px 2px var(--ds-glow-caution); }
}

.kpi-widget.severity-warning {
    animation: alertPulse 2s ease-in-out infinite;
}

/* Critical state: faster, red */
.kpi-widget.severity-critical {
    animation: alertPulse 1s ease-in-out infinite;
    --ds-glow-caution: var(--ds-glow-critical);
}
```

### 5.4 Network Congestion Visual Warning

```
When bandwidth saturation > 80%:
  1. Network KPI card gets amber border
  2. Bandwidth progress bar pulses
  3. "CONGESTION_RISK" label appears

When > 100% (overloaded):
  1. Network KPI card gets red border + pulse
  2. All server blades in affected racks dim slightly
  3. "NETWORK_OVERLOAD" banner at top
  4. Packet loss counter starts incrementing visually
  5. Customer satisfaction indicators start dropping
```

---

## PERFORMANCE OPTIMIZATION PLAN

### For 10,000+ Concurrent Users

#### Backend
```
1. Database:
   - Redis caching for game state (60s TTL)
   - Batch tick processing (process 100 users per job)
   - Database read replicas for game state queries
   - JSON column indexing for frequently queried fields

2. Game Loop:
   - Queue-based tick processing (Laravel Horizon)
   - Tick frequency: 15s intervals (not every second)
   - Differential updates: only broadcast changed values
   - Lazy loading: only process active users (logged in last 5 min)

3. WebSocket:
   - Reverb horizontal scaling with Redis pub/sub
   - Channel-based throttling (max 2 messages/sec per user)
   - Binary message format for large updates
   - Connection pooling for database queries in broadcast handlers
```

#### Frontend
```
1. Rendering:
   - Virtual scrolling for long lists (order list, transactions)
   - Component lazy loading for overlays (dynamic imports)
   - Canvas rendering for rack visualization (not DOM)
   - RequestAnimationFrame for sparkline animations

2. Data:
   - Normalized state (IDs, not nested objects)
   - Computed property memoization
   - Debounced API calls (300ms)
   - Optimistic UI updates for purchases

3. Network:
   - WebSocket message batching (collect 1s, then apply)
   - Differential state updates (patch, not replace)
   - Service Worker for offline caching
   - Compressed WebSocket payloads
```

---

## IMPLEMENTATION ORDER

```
Week 1-2:  Phase 1 — Architecture (store split, composables, design tokens)
Week 3-4:  Phase 3.1-3.3 — Design system + telemetry components
Week 5-6:  Phase 2.1-2.2 — Network database + enhanced NetworkService
Week 7-8:  Phase 2.3-2.4 — Network cascade effects + server integration
Week 9-10: Phase 4.1-4.2 — Rack V2 visual upgrades + drag/drop
Week 11:   Phase 4.3-4.4 — Inline actions + voltage classes
Week 12:   Phase 5 — System feedback layer (polish pass)
Ongoing:   Performance optimization as needed
```

---

## FILE CHANGE SUMMARY

### New Files to Create
```
Backend (PHP):
  database/migrations/XXXX_expand_player_networks.php
  database/migrations/XXXX_create_bandwidth_contracts.php
  database/migrations/XXXX_create_ip_allocations.php
  app/Models/BandwidthContract.php
  app/Models/IpAllocation.php
  app/Events/NetworkMetricsUpdated.php
  app/Events/NetworkIncident.php
  app/Events/KpiThresholdBreached.php

Frontend (Vue/JS):
  resources/css/design-system.css
  resources/js/stores/infrastructure.js
  resources/js/stores/network.js
  resources/js/stores/economy.js
  resources/js/stores/events.js
  resources/js/stores/ui.js
  resources/js/composables/useTelemetry.js
  resources/js/composables/useKpiColor.js
  resources/js/composables/useAnimatedValue.js
  resources/js/composables/useNetworkMetrics.js
  resources/js/composables/useFormatters.js
  resources/js/composables/useDragDrop.js
  resources/js/components/Feedback/TelemetryLine.vue
  resources/js/components/Feedback/KpiGauge.vue
  resources/js/components/Feedback/AlertPulse.vue
  resources/js/components/Feedback/StatusIndicator.vue
  resources/js/components/Primitives/NumberDisplay.vue
  resources/js/components/Primitives/DataCard.vue
  resources/js/components/Primitives/MiniChart.vue
  resources/js/components/Network/SubnetManager.vue
  resources/js/components/Network/IspSelector.vue
  resources/js/components/Network/BandwidthGauge.vue
  resources/js/components/Network/TrafficFlow.vue
```

### Files to Modify
```
Backend:
  app/Services/Game/NetworkService.php       (major expansion)
  app/Services/Game/GameLoopService.php      (network integration in tick)
  app/Services/Game/CustomerOrderService.php (IP requirement enforcement)
  app/Models/PlayerNetwork.php               (new fields, relationships)
  app/Models/Server.php                      (IP allocation relationship)
  app/Http/Controllers/Api/NetworkController.php (new endpoints)
  routes/api.php                             (new routes)

Frontend:
  resources/js/stores/game.js                (decompose into sub-stores)
  resources/js/components/Rack/RackComponent.vue (V2 upgrade)
  resources/js/components/Game/NetworkView.vue    (use new components)
  resources/js/components/Game/GameWorld.vue       (design system migration)
  resources/js/components/HUD/TopBar.vue          (KPI integration)
  resources/js/components/HUD/Sidebar.vue         (navigation update)
  resources/css/app.css                           (import design system)
```
