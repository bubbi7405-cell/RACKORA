# SERVER TYCOON — MASTER DESIGN DOCUMENT & ROADMAP

**Version:** 3.0 — Complete 45-Feature Audit
**Last Updated:** 2026-02-10
**Status:** MVP In Progress

---

## 🏗 SYSTEM ARCHITECTURE & DEPENDENCY GRAPH

```
TIER 0: PHYSICAL FOUNDATION
  ┌──────────────────────────────────────────────────┐
  │ [1] Locations → [2] Racks → [3] Servers          │
  │                    ↓              ↓               │
  │              [4] Power      [5] Cooling           │
  │                    ↓              ↓               │
  │              [6] Network ←───────┘                │
  └──────────────────────────────────────────────────┘
                       ↓
TIER 1: ECONOMY & LOGIC
  ┌──────────────────────────────────────────────────┐
  │ [7] Customers → [8] Economy → [12] Research      │
  │       ↓                             ↓             │
  │ [18] SLAs        [11] Employees   [33] Exp. Tech  │
  └──────────────────────────────────────────────────┘
                       ↓
TIER 2: CHAOS & MANAGEMENT
  ┌──────────────────────────────────────────────────┐
  │ [9] Events → [34] Time-Pressure → [31] Cascades  │
  │      ↓                                            │
  │ [23] Maintenance → [32] Tech Debt                 │
  │      ↓                                            │
  │ [21] Diagnostics → [37] Backup/Recovery           │
  │      ↓                                            │
  │ [41] Crisis Score → [44] Post-Mortems             │
  └──────────────────────────────────────────────────┘
                       ↓
TIER 3: MASTERY & META
  ┌──────────────────────────────────────────────────┐
  │ [19] Automation → [40] API Sim → [42] RT vs Idle  │
  │ [25] Reputation → [35] NPC Competitors            │
  │ [22] AI Customers → [36] Logistics                │
  │ [17] Regions → [10] Multiplayer                   │
  │ [16] Story → [45] Achievements                    │
  │ [39] Specializations → [26] Sabotage              │
  └──────────────────────────────────────────────────┘
                       ↓
TIER 4: POLISH & ENDGAME
  ┌──────────────────────────────────────────────────┐
  │ [13] UI/HUD    [14] Theme    [30] Immersive       │
  │ [43] Tooltips  [27] Stats    [20] Decisions       │
  │ [28] Sandbox   [29] Replay   [38] Difficulty      │
  │ [24] Modular Servers                              │
  └──────────────────────────────────────────────────┘
```

**Critical Bottlenecks:**
- Features 1-6 (Physical Layer) block EVERYTHING
- Feature 7+8 (Economy) blocks all progression
- Feature 9 (Events) blocks all depth features
- Feature 12 (Research) gates most unlocks

**MUST NOT add early:**
- [26] Sabotage — requires complete reputation + NPC systems
- [29] Replay — requires complete stats + history logging
- [35] NPC Competitors — requires reputation + regions
- [40] API Simulation — requires automation system

---

## 🟢🟡🔴 ALL 45 FEATURES — STATUS & PHASE

### 🟢 PHASE 1: MVP (Launch Version)
*Core loop, first failures, player feels responsibility.*

---

#### FEATURE 1: Infrastructure Locations (basement → datacenter)
**Phase:** 🟢 MVP | **Status:** 🟡 Mostly Done

| Component | Status | Details |
|---|---|---|
| Basement room (start) | ✅ Done | Created on player init via `GameStateService::initializePlayer` |
| Room types enum | ✅ Done | `RoomType` enum: BASEMENT, GARAGE, SMALL_HALL, DATA_CENTER |
| Room model | ✅ Done | `GameRoom` with power, cooling, bandwidth, rent, upgrades |
| Visual room switching | ✅ Done | `LeftPanel` room list, click to select |
| Locked room display | ✅ Done | Shows locked rooms with level + cost requirement |
| Room purchase API | ✅ Done | `RoomController::purchase` with level & balance validation |
| Room purchase UI | ✅ Done | LeftPanel BUY button on qualifying locked rooms |
| Room upgrade logic | ✅ Done | Upgrade Power, Cooling, Bandwidth up to level 5 |
| Level-gated unlocks | ✅ Done | Level 5 → Garage, Level 15 → Hall, Level 30 → DC enforced |

**WHY MVP:** The core fantasy IS the progression from basement to datacenter. Without multiple locations, there's no growth arc.

**COMPLETED:**
- [x] `POST /api/rooms/purchase` endpoint
- [x] Level gate validation (Level 5 → Garage, Level 15 → Hall, Level 30 → DC)
- [x] Room upgrade system (buy more cooling, more power, more bandwidth)
- [x] Purchase button UI in LeftPanel locked rooms

---

#### FEATURE 2: Rack System with U-Slots
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Rack model | ✅ Done | `ServerRack` with slots, power, heat, temperature |
| Rack types | ✅ Done | `RackType` enum: 12U, 24U, 42U with costs |
| Rack purchase | ✅ Done | `RackController::purchaseRack` |
| Visual slot rendering | ✅ Done | `RackComponent.vue` with slot map |
| Server collision detection | ✅ Done | `canFitServerAt()`, `buildSlotMap()` |
| Drag & drop servers | ✅ Done | Move between slots and racks |
| Power/heat recalculation | ✅ Done | `recalculatePowerAndHeat()` on changes |

**WHY MVP:** The rack is the primary interactive object. Without it, there's no game.

---

#### FEATURE 3: Server Types (VPS, Dedicated, GPU)
**Phase:** 🟢 MVP | **Status:** 🟡 Mostly Done

| Component | Status | Details |
|---|---|---|
| Server model | ✅ Done | CPU, RAM, Storage, Bandwidth, Health, Power, Heat |
| Server types | ✅ Done | `ServerType`: VPS_NODE, DEDICATED, STORAGE, GPU |
| Server catalog/shop | ✅ Done | `RightPanel.vue` server store with specs |
| Server purchase & placement | ✅ Done | `RackController::placeServer` |
| Server provisioning | ✅ Done | Timestamp-based, auto-completes in GameLoop |
| Power on/off | ✅ Done | `RackController::powerOn/powerOff` |
| Server health/degradation | ✅ Done | Natural 0.05%/tick decay, auto-degrades at <30% health |
| Maintenance/repair | ✅ Done | `RoomController::repairServer` — costs 20% of purchase price, restores to 100% |

**WHY MVP:** Server diversity creates the decision: "What do I buy? What fits my customers?"

**TODO:**
- [x] Natural health decay over time (0.05% per tick for online servers)
- [x] Repair action (costs 20% of purchase price, instant restore)
- [x] Visual health indicator on RightPanel server detail (green/yellow/red)

---

#### FEATURE 4: Power System
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Power per server | ✅ Done | `power_draw_kw` on `Server` model |
| Room power budget | ✅ Done | `max_power_kw` on `GameRoom` |
| Power usage tracking | ✅ Done | `getCurrentPowerUsage()` on Room |
| Overload warning | ✅ Done | `isPowerOverloaded()` + UI indicator |
| Power cost in economy | ✅ Done | Real kW × price_per_kwh with research bonus |
| Power outage event | ✅ Done | `createPowerOutage()` with backup/shutdown actions |
| Room power validation | ✅ Done | Prevents placing servers when room power grid would be exceeded |

**WHY MVP:** Power is the first "invisible cost" the player must manage. It creates the tension between profit and capacity.

**COMPLETED:**
- [x] Fix: Use `server.power_draw_kw * economy.power_price_per_kwh` in processEconomy
- [x] Prevent placing servers when room power would exceed capacity
- [x] Power outage event creation in `GameEventService`
- [ ] UPS research unlock (prevents instant power loss)

---

#### FEATURE 5: Cooling System
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Heat per server | ✅ Done | `heat_output_kw` on `Server` |
| Room cooling capacity | ✅ Done | `max_cooling_kw` on `GameRoom` |
| Overheat detection | ✅ Done | `isOverheating()` on Room and Rack |
| Temperature field | ✅ Done | `temperature` on `ServerRack` |
| Temperature simulation | ✅ Done | `processTemperature()` — heat vs cooling per tick, ambient pull |
| Overheat consequences | ✅ Done | ≥45°C: health decay, ≥55°C: emergency shutdown + DAMAGED |
| Cooling upgrade | ✅ Done | Via Room Infrastructure Upgrade system |

**WHY MVP:** Cooling creates the "physical space" pressure. More servers = more heat = more risk.

**COMPLETED:**
- [x] GameLoop: Calculate temperature per rack each tick
- [x] Auto-trigger overheat consequences (health decay at 45°C, shutdown at 55°C)
- [x] Cooling upgrade via room upgrades

---

#### FEATURE 6: Network System
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Bandwidth per server | ✅ Done | `bandwidth_mbps` on `Server` |
| Room bandwidth limit | ✅ Done | `bandwidth_gbps` on `GameRoom` |
| Bandwidth usage tracking | ✅ Done | `getCurrentBandwidthUsage()` sums active order bandwidth |
| Network failure event | ✅ Done | `createNetworkFailure()` with 3 action choices |
| Bandwidth cost | ✅ Done | `bandwidth_cost_per_gbps` included in economy calculation |
| Bandwidth saturation warning | ✅ Done | Room header + LeftPanel warning badge |
| Bandwidth validation | ✅ Done | Server NIC + room uplink checked on order assignment |
| Bandwidth upgrade | ✅ Done | Via Room Infrastructure Upgrade system |

**WHY MVP:** Network is the third leg of the infrastructure triangle (Power-Cooling-Network). Without it, the simulation is incomplete.

**COMPLETED:**
- [x] Track total bandwidth usage per room
- [x] Include bandwidth cost in economy calculation
- [x] Network saturation warning in UI (GameWorld header + LeftPanel)
- [x] Network failure event creation logic
- [x] Bandwidth upgrade via room upgrades
- [x] Bandwidth validation on order assignment (server NIC + room uplink)

---

#### FEATURE 7: Customers & Orders
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Customer model | ✅ Done | Name, company, status, satisfaction, revenue |
| Order generation | ✅ Done | `CustomerOrderService::tick()` auto-generates |
| Order types | ✅ Done | VPS, Dedicated, Storage, GPU |
| Order patience/expiry | ✅ Done | Timer-based, auto-expires |
| Order acceptance | ✅ Done | `CustomerOrderController::accept` |
| Server assignment | ✅ Done | Validates specs + bandwidth match requirements |
| Order overlay | ✅ Done | `OrderOverlay.vue` with details & assign UI |
| Customer satisfaction | ✅ Done | Decays when servers degraded/damaged, recovers when healthy |
| Customer churn | ✅ Done | Churn at <20% satisfaction — orders cancelled, reputation lost |
| Order cancellation | ✅ Done | `cancelActive()` — releases server, reputation -5 |
| Returning customers | 🟡 Partial | 30% chance existing customer places new order |

**WHY MVP:** Customers are the reason the player does anything. Without them, servers are useless metal.

**COMPLETED:**
- [x] Satisfaction decay when servers degraded/damaged
- [x] Satisfaction recovery when uptime is good (+0.5/tick)
- [x] Churn: Customer leaves when satisfaction < 20% (lose revenue + reputation!)
- [x] Order cancellation endpoint (penalty: reputation -5)
- [ ] Happy customers generate more orders (loyalty bonus)

---

#### FEATURE 8: Economy & Finance
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Player balance | ✅ Done | `PlayerEconomy::balance` |
| Debit/Credit | ✅ Done | Methods with validation + transaction logging |
| Hourly income tracking | ✅ Done | Based on active orders |
| Hourly expenses | ✅ Done | Power costs + bandwidth costs + room rent |
| XP / Leveling | ✅ Done | Passive (online servers), order accept (+10), event resolve (+25), research (+50), room purchase (+100), repair (+5) |
| Reputation | ✅ Done | Gains on event resolve (+2), decays on churn (-3), event failure (-5), order cancel (-5) |
| Financial history | ✅ Done | `PaymentTransaction` model + `/economy/transactions` API with filters |
| Profit/Loss display | ✅ Done | TopBar + economy summary stats |

**WHY MVP:** Money is the feedback loop. Without accurate economy, the player can't make informed decisions.

**COMPLETED:**
- [x] Fix expenses: Real power cost = Σ(server.power_kw) × price_per_kwh
- [x] Fix expenses: Room rent included in GameLoop
- [x] Fix expenses: Bandwidth cost included in economy
- [x] Award XP: Accept order (+10), Resolve event (+25), Complete research (+50), passive (1/online server/tick)
- [x] Level gates: Level 5 → Garage, Level 15 → Hall, Level 30 → DC
- [x] Financial transaction log with filters (type, category, time range)

---

#### FEATURE 9: Events & Disasters
**Phase:** 🟢 MVP | **Status:** 🟡 Partial

| Component | Status | Details |
|---|---|---|
| Event model | ✅ Done | `GameEvent` with full metadata |
| Event types | ✅ Done | 6 types defined in `EventType` enum |
| Event generation | ✅ Done | All 6 types have creation logic |
| Event escalation | 🟡 Partial | Status progression works, **no escalation consequences** |
| Event resolution | ✅ Done | Actions with cost & success chance, XP + reputation reward |
| Event failure | ✅ Done | `failEvent()` safely loads user, applies reputation/server damage |
| Event overlay UI | ✅ Done | Full-screen crisis card |
| Event API | ✅ Done | Get active, resolve, history |
| Impact on servers | ✅ Done | Restores health on resolve (HW fail + network), damages on failure |

**WHY MVP:** Events are the "pressure" that make this a game, not a dashboard. Without them, there's no challenge.

**TODO:**
- [x] Creation logic for: `POWER_OUTAGE`, `NETWORK_FAILURE`, `SECURITY_BREACH`
- [ ] Escalation: Spread to adjacent servers/racks
- [x] Fix `failEvent()`: Uses `User::find()` safely loads user
- [ ] Auto-trigger events from simulation (overheat → event, power overload → event)
- [ ] Event notification sound

---

#### FEATURE 10: Asynchronous Multiplayer
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Leaderboards | ❌ |
| Market share competition | ❌ |
| Player comparison | ❌ |
| Shared economy events | ❌ |

**WHY Phase 3:** Multiplayer requires all core systems to be stable first. Adding competition before the single-player loop works would be premature.

**TODO:**
- [ ] Leaderboard table (level, revenue, uptime, reputation)
- [ ] Weekly rankings
- [ ] Shared world events affecting all players

---

#### FEATURE 11: Employees & Staff
**Phase:** 🟢 Phase 2 Complete | **Status:** ✅ Complete

| Component | Status |
|---|---|
| Employee model | ✅ Done |
| Hiring/firing | ✅ Done |
| Auto-repair mechanic | ✅ Done |
| Salary expenses | ✅ Done |
| Employee skill levels | ✅ Done |

**WHY Phase 2:** Employees are the first automation tool. They reduce manual pressure — but cost money. This creates a new decision layer that requires the base game to work first.

**TODO:**
- [x] `employees` table (user_id, name, role, skill, salary, hired_at)
- [x] Roles: Technician (auto-repair), Support (reduce churn), Engineer (faster provisioning)
- [x] Salary included in hourly expenses
- [x] Employee management overlay

---

#### FEATURE 12: Research & Development
**Phase:** 🟢 MVP | **Status:** 🟡 Mostly Done

| Component | Status | Details |
|---|---|---|
| UserResearch model | ✅ Done | UUID, key, level, progress, status |
| Research catalog | ✅ Done | 4 projects in `ResearchService::PROJECTS` |
| Research start | ✅ Done | Cost validation, one-at-a-time |
| Research progress | ✅ Done | `tick()` advances per minute |
| Research completion | ✅ Done | Auto-completes at 100% |
| Bonus application | 🟡 Partial | Only `power_efficiency` bonus used |
| Research UI | ✅ Done | `ResearchOverlay.vue` with tech cards |
| Research button | ✅ Done | Management section in LeftPanel |

**WHY MVP:** Research gives the player long-term goals and passive progression. It's the "hope" mechanic.

**TODO:**
- [x] Apply `provisioning_speed` bonus to Order Provisioning
- [x] Apply `customer_quality` bonus to order price generation & SLA tier
- [x] Gate `unlock_rack_42u` in rack purchase logic
- [ ] Add research projects: Security Shield, Auto-Recovery, Energy Optimizer
- [ ] Completion notification toast
- [ ] Research dependency chains (need Level 2 Cooling before Level 1 Security)

---

#### FEATURE 13: UI/HUD & Immersion
**Phase:** 🟢 MVP | **Status:** ✅ Mostly Done

| Component | Status | Details |
|---|---|---|
| TopBar | ✅ Done | Balance, Level, XP, Income/Expense, Clock |
| LeftPanel | ✅ Done | Rooms, Stats, Orders, Management |
| RightPanel | ✅ Done | Server Shop, Server Details |
| BottomHud | ✅ Done | Quick actions |
| GameWorld | ✅ Done | Rack rendering area |
| Overlays | ✅ Done | Order, Event, Research |
| Toast notifications | ✅ Done | `ToastContainer.vue` |
| CSS design system | ✅ Done | Premium dark theme, CSS variables |
| Loading screen | ✅ Done | `LoadingScreen.vue` |
| Auth screen | ✅ Done | `AuthScreen.vue` |
| Settings overlay | ❌ Missing | No settings UI |
| Tutorial/onboarding | ❌ Missing | No guidance for new players |
| Keyboard shortcuts | ❌ Missing | No hotkeys |

**WHY MVP:** The UI IS the game for a browser game. Premium feel is non-negotiable.

**TODO:**
- [x] Settings overlay (game speed, sound toggle, notifications)
- [ ] Tutorial: First-time player guidance (contextual hints)
- [ ] Keyboard shortcuts (1-4 for rooms, Space for pause, E for events)

---

#### FEATURE 14: Theme & Customization
**Phase:** 🟢 Phase 2 Complete | **Status:** ✅ Complete

| Component | Status |
|---|---|
| UI theme selection | ✅ Done |
| Custom rack colors | 🟡 Partial |
| Server LED color picker | 🟡 Partial |
| Company logo upload | ❌ Missing |
| Room wallpaper/style | ❌ Missing |

**WHY Phase 2:** Cosmetics are "want" not "need." The core game must work before we add vanity.

**TODO:**
- [x] Theme presets (Dark, Light, Cyberpunk, Terminal Green)
- [ ] Custom LED colors per server
- [ ] Company name + logo in TopBar

---

#### FEATURE 15: Backend Technical Systems
**Phase:** 🟢 MVP | **Status:** ✅ Mostly Done

| Component | Status | Details |
|---|---|---|
| Laravel REST API | ✅ Done | All CRUD endpoints |
| Sanctum authentication | ✅ Done | Token-based |
| Game Loop service | ✅ Done | Orders, Events, Research, Economy |
| Game State endpoint | ✅ Done | Single `getFullState()` for all data |
| Frontend polling | ✅ Done | 30s interval |
| MySQL database | ✅ Done | All migrations run |
| Artisan commands | ✅ Done | `game:tick`, `game:trigger-event`, etc. |
| WebSocket support | ❌ Missing | Polling only, no real-time |
| Game speed controls | ❌ Missing | Fixed 1x speed |
| Rate limiting | ❌ Missing | No API rate limits |

**WHY MVP:** Without the backend, nothing works. It IS the game engine.

**TODO:**
- [ ] WebSocket via Laravel Reverb for real-time updates
- [ ] Game speed controls (1x, 2x, 5x in frontend; tick interval in backend)
- [ ] API rate limiting (prevent abuse)
- [ ] Cache `getFullState()` to reduce DB load

---

### 🟡 PHASE 2: DEPTH UPDATE
*More decisions, first automation, more stress, specialization begins.*

---

#### FEATURE 16: Story & World Layer
**Phase:** � Phase 2 Complete | **Status:** ✅ Complete

| Component | Status |
|---|---|
| News ticker UI | ✅ Done |
| World event database | ✅ Done |
| Events affecting gameplay | ✅ Done |
| Flavor text on events | ✅ Done |

**WHY Phase 2:** Story wraps the systems in meaning. Without it, the game feels like a simulation without soul.

**TODO:**
- [x] News ticker component in TopBar or BottomHud
- [x] `world_events` table with news items
- [x] World events: "AI Boom → GPU demand +50%", "Energy Crisis → Power cost +20%"
- [x] Random flavor text on events for variety

---

#### FEATURE 17: Regions & Locations
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Multiple regions (EU, US, Asia) | ❌ |
| Latency per region | ❌ |
| Region-specific costs | ❌ |
| Region-specific customers | ❌ |

**WHY Phase 3:** Regions multiply the entire game. All systems must work in one location first.

**TODO:**
- [ ] `regions` table with modifiers (power_cost, customer_demand, latency)
- [ ] Region selection when buying a new room
- [ ] Customer preference for region (latency-sensitive)

---

#### FEATURE 18: SLA & Contract Depth
**Phase:** � Phase 2 Complete | **Status:** ✅ Complete

| Component | Status |
|---|---|
| SLA tier system | ✅ Done |
| Uptime tracking per order | ✅ Done |
| Automatic penalties for downtime | ✅ Done |
| Uptime history visualization | 🟡 Viewable in Order List |

**WHY Phase 2:** Makes uptime critical. Losing 0.1% uptime on an enterprise contract should HURT your wallet.

**TODO:**
- [x] Add `sla_tier` and `uptime_percent` to `customer_orders`
- [x] Game loop: Increment downtime/total ticks per order
- [x] Penalties: Deduct % of monthly revenue for SLA violations
- [x] Display SLA health in Order List UIprice, bigger risk

---

#### FEATURE 19: Automation & Scripting
**Phase:** � Phase 2 Complete | **Status:** ✅ Complete

| Component | Status |
|---|---|
| Auto-reboot scripts | ✅ Done |
| Auto-provisioning scripts | ✅ Done |
| Script marketplace/UI | ✅ Done |
| Cooling automation | ❌ |

**WHY Phase 2:** Late-game needs automation. Players should transition from "Racking" to "Orchestrating".

**TODO:**
- [x] Automation toggle UI (Scripts Overlay)
- [x] Backend: `auto_reboot` script in GameLoop
- [x] Backend: `auto_provisioning` script for pending orders
- [ ] Research unlocks for advanced scripts

---

#### FEATURE 20: Management Decisions
**Phase:** 🟡 Update 1 | **Status:** ✅ Completed

| Component | Status |
|---|---|
| Strategic choices (pricing, focus) | ✅ |
| Budget/VIP customer focus | ✅ |
| Green energy vs cheap power | ✅ |
| Decision consequences | ✅ |

**WHY Phase 2:** Decisions define the player's identity. "What kind of hoster am I?"

**COMPLETED:**
- [x] Decision overlay at key milestones (Level 5, 10, 20)
- [x] Choices affect modifiers: "Budget Focus" = +orders, -price. "Premium" = -orders, +price
- [x] Permanent consequences (Active via policies system)

---

#### FEATURE 21: Inspection & Diagnostics
**Phase:** 🟡 Update 1 | **Status:** ✅ Completed

| Component | Status |
|---|---|
| Server detail inspection view | ✅ |
| Log viewer per server | ✅ |
| Performance metrics / Charts | ✅ |
| Hidden fault detection | ✅ |

**WHY Phase 2:** Inspection adds depth to event resolution. Instead of "click fix", you investigate.

**COMPLETED:**
- [x] Server detail overlay with Metrics & Logs tabs
- [x] "Diagnose" action: Identify specific hardware fault
- [x] Correct diagnosis → faster/cheaper fix
- [x] Simulated CPU/RAM/Bandwidth history graphs (SVG)

---

#### FEATURE 22: AI Customers & Load Spikes
**Phase:** 🟡 Update 1 | **Status:** ✅ Completed

| Component | Status |
|---|---|
| Customer behavior patterns | ✅ |
| Load spikes at peak hours | ✅ |
| Seasonal demand changes | ✅ |
| VIP/whale customers | ✅ |

**WHY Phase 2:** Predictability is boring. Players must handle sudden changes in demand and scale.

**COMPLETED:**
- [x] Time-of-day load factor implementation (Peak vs Night)
- [x] "Whale" customers with 6x payouts and 99.999% SLA
- [x] Viral events multiplier via World Events system
- [x] Dynamic metric simulation in server inspector

---

#### FEATURE 23: Maintenance & Downtime
**Phase:** 🟢 MVP | **Status:** ✅ Completed

| Component | Status | Details |
|---|---|---|
| Scheduled maintenance windows | ✅ Done | Planned downtime for 80% reduced satisfaction penalty |
| Dust accumulation | ✅ Done | Heat output increases with dust level (up to +10%) |
| Hardware aging | ✅ Done | 0.05%/tick health decay for online servers |
| Maintenance cost/time | ✅ Done | Proactive maintenance (5% cost) vs Reactive repair (20% cost) |

**WHY MVP:** Proactive maintenance adds a layer of strategy. High-end rooms need less cleaning.

**COMPLETED:**
- [x] Health decay: Servers lose 0.05% health per tick when online
- [x] Repair action: Costs 20% of purchase price, restores health instantly
- [x] Scheduled maintenance windows: 2% health recovery per tick, reduced penalty
- [x] Dust mechanic: Dirty racks increase temperature; Cleanable for $50

---

#### FEATURE 24: Modular Server Building
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Component-based server assembly | ❌ |
| CPU/RAM/Disk selection | ❌ |
| Component market | ❌ |
| Custom configurations | ❌ |

**WHY Phase 3:** Modular building multiplies complexity enormously. The base game needs fixed server types first.

---

#### FEATURE 25: Reputation Specialization
**Phase:** 🟡 Update 1 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Reputation categories | ❌ |
| Specialization bonuses | ❌ |
| Customer type attraction | ❌ |
| Reputation milestones | ❌ |

**WHY Phase 2:** Specialization answers "Who am I as a hoster?" and creates long-term strategy.

**TODO:**
- [ ] Reputation categories: Budget, Premium, GPU-Specialist, Green
- [ ] Category grows based on actions (lots of VPS = "Budget" reputation grows)
- [ ] High reputation in a category = more of those customers
- [ ] Mutually exclusive bonuses (can't be Budget AND Premium)

---

#### FEATURE 26: Sabotage & Espionage (Late-Game)
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Corporate espionage events | ❌ |
| Sabotage from competitors | ❌ |
| Security investment | ❌ |
| Counter-espionage | ❌ |

**WHY Phase 3:** Requires NPC competitors and full security system. Too complex for early game.

---

#### FEATURE 27: Statistics & History
**Phase:** 🟡 Update 1 | **Status:** 🟡 Mostly Done

| Component | Status |
|---|---|
| Revenue graphs over time | ✅ Done |
| Uptime history | ❌ |
| Event history log | 🟡 API Done |
| Customer growth chart | ✅ Done |

**WHY Phase 2:** Stats help players understand their trajectory. Important for long-term engagement.

**TODO:**
- [x] `game_statistics` table (daily snapshots)
- [x] Stats overlay with charts (Revenue, Customers, Uptime)
- [ ] Event history UI log

---

#### FEATURE 28: Sandbox / Test Environment
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Free-build mode | ❌ |
| No consequences | ❌ |
| Unlimited money | ❌ |
| Testing configurations | ❌ |

**WHY Phase 3:** Sandbox is a "no-pressure" mode. It contradicts the core design until the core game is proven fun.

---

#### FEATURE 29: Replay & Timeline
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Action replay | ❌ |
| Timeline scrubbing | ❌ |
| "What if" scenarios | ❌ |

**WHY Phase 3:** Requires complete action logging that doesn't exist yet.

---

#### FEATURE 30: Immersive Details (Sound, Day/Night, Alarms)
**Phase:** 🟢 Phase 2 Complete | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Sound effects | ✅ Done | UI clicks, success/fail sounds |
| Background ambient | ✅ Done | Server hum ambience |
| Day/night cycle | ✅ Done | Visual lighting shift + HUD clock |
| Alarm sounds | ✅ Done | Critical event siren |

**WHY Phase 2:** Sound transforms "watching" into "feeling." High impact for immersion.

**COMPLETED:**
- [x] Howler.js or Web Audio API integration
- [x] Sounds: purchase "ka-ching", event alarm, server hum ambient
- [x] Day/night cycle in background (affects lighting)
- [x] Visual: Room lighting changes with time

---

### 🔴 PHASE 3: LATE GAME / META
*Cascading failures, NPC competition, crisis mastery.*

---

#### FEATURE 31: System Dependencies & Cascades
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Power→Cooling dependency | ❌ |
| Rack→Server cascading failures | ❌ |
| Room-level blackouts | ❌ |
| Chain reaction simulation | ❌ |

**WHY Phase 3:** Cascades are "expert mode." They multiply chaos. The base game must feel manageable first.

**TODO:**
- [ ] If power fails → cooling stops → all servers overheat
- [ ] If rack overheats → all servers in rack degrade
- [ ] Cascading event chains: HW failure → DDoS vulnerability → Security breach

---

#### FEATURE 32: Technical Debt
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Old server inefficiency | ❌ |
| Legacy system costs | ❌ |
| Upgrade vs replace decisions | ❌ |
| Debt accumulation meter | ❌ |

**WHY Phase 3:** Technical debt is a late-game pressure. Early game players shouldn't worry about it.

**TODO:**
- [ ] Servers older than X days cost 10% more power
- [ ] "Technical Debt Score" visible in stats
- [ ] Upgrade path: Pay to modernize old servers

---

#### FEATURE 33: Experimental Technology
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Experimental server types | ❌ |
| Risk/reward unlocks | ❌ |
| Beta hardware | ❌ |
| Breakthrough events | ❌ |

**WHY Phase 3:** Experimental tech is the "gamble" mechanic. Requires a stable economy first.

---

#### FEATURE 34: Time-Pressure Events
**Phase:** 🟢 MVP (partially) | **Status:** 🟡 Partial

| Component | Status | Details |
|---|---|---|
| Event deadlines | ✅ Done | `deadline_at` on GameEvent |
| Escalation timers | ✅ Done | Warning → Active → Escalated |
| Cascading time pressure | ❌ Missing | No multi-event cascading |
| Decision-under-pressure | 🟡 Partial | Actions exist but no trade-off choices |

**WHY MVP (partial):** Time pressure IS the core tension. The base mechanic works, but needs depth.

---

#### FEATURE 35: NPC Competitors
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| NPC hosting companies | ❌ |
| Market share competition | ❌ |
| Price wars | ❌ |
| NPC behavior patterns | ❌ |

**WHY Phase 3:** NPCs require reputation, regions, and a complete economy to be meaningful.

---

#### FEATURE 36: Logistics & Supply Chains
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Hardware delivery times | ❌ |
| Supplier selection | ❌ |
| Bulk ordering discounts | ❌ |
| Supply shortages | ❌ |

**WHY Phase 3:** Currently, servers appear instantly. Delivery delay adds realism but requires planning tools first.

---

#### FEATURE 37: Backup & Recovery Gameplay
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Backup configuration | ❌ |
| Data loss events | ❌ |
| Recovery procedures | ❌ |
| Backup cost vs risk trade-off | ❌ |

**WHY Phase 3:** Backups create a "prevention vs reaction" choice. Requires maintenance system first.

---

#### FEATURE 38: Custom Difficulty Modes
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Easy/Normal/Hard presets | ❌ |
| Custom modifier sliders | ❌ |
| Ironman mode | ❌ |

**WHY Phase 3:** Difficulty tuning requires the game to be balanced first.

---

#### FEATURE 39: Player Skill Specializations
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Skill tree (player, not tech) | ❌ |
| Permanent character bonuses | ❌ |
| Prestige/reset mechanics | ❌ |

**WHY Phase 3:** Player specialization is the "identity" endgame. Requires all systems to branch from.

---

#### FEATURE 40: API & Automation Simulation
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| In-game API console | ❌ |
| Script debugging | ❌ |
| Automation reliability | ❌ |

**WHY Phase 3:** Extends Feature 19 (Automation). Requires automation to work first.

---

#### FEATURE 41: Crisis Management Score
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Performance grading per event | ❌ |
| Letter grade (A-F) | ❌ |
| Historical crisis scores | ❌ |
| Rewards for good management | ❌ |

**WHY Phase 3:** Scoring requires complete event resolution with multiple outcome paths.

**TODO:**
- [ ] Grade based on: response time, cost, collateral damage
- [ ] A+ grade = XP bonus + reputation boost

---

#### FEATURE 42: Real-Time vs Idle Decisions
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Offline progress | ❌ |
| Idle income calculation | ❌ |
| Catch-up mechanics | ❌ |
| "What happened while you were away" report | ❌ |

**WHY Phase 3:** Idle mechanics change the entire game loop design. Must be intentional.

---

#### FEATURE 43: Smart Contextual Tooltips
**Phase:** 🟡 Update 1 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Hover tooltips on UI elements | ❌ |
| Contextual help system | ❌ |
| "Why is this happening?" explanations | ❌ |

**WHY Phase 2:** Tooltips replace tutorials. They teach organically. High value, medium effort.

**TODO:**
- [ ] Tooltip component (hover delay, arrow pointing)
- [ ] Contextual tips: "This server uses 0.5 kW, costing $X/hour"
- [ ] Warning tooltips: "This rack is at 85% heat capacity"

---

#### FEATURE 44: Post-Mortem Reports
**Phase:** 🔴 Update 2 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Event summary after resolution | ❌ |
| Timeline of event progression | ❌ |
| Cost analysis | ❌ |
| Lessons learned | ❌ |

**WHY Phase 3:** Post-mortems are "reflection" gameplay. Requires complete event logging.

---

#### FEATURE 45: Story-Driven Achievements
**Phase:** 🟡 Update 1 | **Status:** ❌ Not Started

| Component | Status |
|---|---|
| Achievement system | ❌ |
| Story-flavored unlock text | ❌ |
| Milestone rewards | ❌ |
| Achievement overlay | ❌ |

**WHY Phase 2:** Achievements guide player behavior and add long-term goals. They tell the story.

**TODO:**
- [ ] `achievements` table (key, title, description, condition, reward)
- [ ] Achievements: "First Customer", "Survived a Crisis", "100 Servers", "Going Global"
- [ ] Toast popup when achieved
- [ ] Achievement showcase in profile

---

## 🎯 RECOMMENDED NEXT STEPS (Priority Order)

### 🔥 Critical for Playable MVP (Do NOW)

| # | Task | Impact | Effort |
|---|---|---|---|
| 1 | **Fix Economy** — Real power costs | High | Low |
| 2 | **Temperature Simulation** — GameLoop updates temp | High | Medium |
| 3 | **XP Awards** — Award XP on actions | High | Low |
| 4 | **Room Purchase** — Buy Garage/Hall/DC | High | Medium |
| 5 | **Customer Churn** — Satisfaction + leaving | High | Medium |

### 🟡 High Value Next (Do SOON)

| # | Task | Impact | Effort |
|---|---|---|---|
| 6 | Fix remaining 3 event types | Medium | Low |
| 7 | Apply all research bonuses | Medium | Low |
| 8 | Server maintenance/repair | Medium | Medium |
| 9 | Financial transaction log | Medium | Medium |
| 10 | Sound effects (Howler.js) | High | Medium |

---

## 🎮 PLAYER JOURNEY — FIRST 3 HOURS

### Hour 1: Learning & Hope (Basement)
- **0-15m:** First rack, first server, first customer "Kevin's Blog"
- **15-45m:** 3 customers, money flows, buy GPU server
- **45-60m:** **FIRST CRASH.** Drive fails. Player panics, fixes it. Learns: "I need better gear."

### Hour 2: Pressure & Reality (Garage)
- **60-90m:** Garage purchased. Rent increases. Power bills hurt.
- **90-100m:** **HEATWAVE.** Must turn off servers to save core contracts.
- **100-120m:** Research unlocked. First optimization taste.

### Hour 3: Identity & Choice (Small Hall)
- **120m+:** First Enterprise contract: 99.9% uptime required.
- **Decision:** Big money vs. big risk?
- **Pivot:** Player replaces cheap hardware with quality gear.

---

## 🧠 BALANCING RISKS

| Risk | Cause | Detection | Fix |
|---|---|---|---|
| Infinite Money | Revenue > Costs at scale | Cash >$1M Hour 2 | Exponential power costs; tech debt |
| Boring Automation | Scripts solve all | 0 manual actions/hr | Script failure chance; edge-case events |
| Event Fatigue | Constant pinging | Quit during event | "Eye of Storm": safe time after crisis |
| Spreadsheet Hell | Too many numbers | Time in menus > world | Visual: heatmaps, LEDs, not tables |
| Early Overwhelm | Too much at once | Quit in first 10m | Level-gate features; drip-feed |

---

## 📐 DESIGN RULES (NON-NEGOTIABLE)

1. Every feature creates a **decision**
2. Every decision has **consequences**
3. Every consequence is **visible**
4. Complexity from **interactions**, not numbers
5. If it feels like a **website** → redesign
6. If it feels **safe** → add pressure
