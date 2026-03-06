# RACKORA by CodePony.de — MASTER DESIGN DOCUMENT & ROADMAP

**Version:** 3.1 — Feature 52 (Profile Control Center) added
**Last Updated:** 2026-02-14
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
- [x] [26] Sabotage — (✅ Done - Proactive Implementation)
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
- [x] UPS research unlock (prevents instant power loss via Energy Optimizer)

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
| Returning customers | ✅ Done | Satisfaction-weighted repeat orders implemented |

**WHY MVP:** Customers are the reason the player does anything. Without them, servers are useless metal.

**COMPLETED:**
- [x] Satisfaction decay when servers degraded/damaged
- [x] Satisfaction recovery when uptime is good (+0.5/tick)
- [x] Churn: Customer leaves when satisfaction < 20% (lose revenue + reputation!)
- [x] Order cancellation endpoint (penalty: reputation -5)
- [x] Happy customers generate more orders (loyalty bonus)

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
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Event model | ✅ Done | `GameEvent` with full metadata |
| Event types | ✅ Done | 6 types defined in `EventType` enum |
| Event generation | ✅ Done | All 6 types have creation logic in `GameEventService` |
| Event escalation | ✅ Done | Status progression + spreading consequences |
| Event resolution | ✅ Done | Actions with cost & success chance, XP + reputation reward |
| Event failure | ✅ Done | `failEvent()` safely loads user, applies reputation/server damage |
| Event overlay UI | ✅ Done | Full-screen crisis card |
| Event API | ✅ Done | Get active, resolve, history |
| Impact on servers | ✅ Done | Restores health on resolve (HW fail + network), damages on failure |

**WHY MVP:** Events are the "pressure" that make this a game, not a dashboard. Without them, there's no challenge.

**COMPLETED:**
- [x] Escalation: Spread to adjacent servers/racks
- [x] Fix `failEvent()`: Uses `User::find()` safely loads user
- [x] Auto-trigger events from simulation (overheat → event, power overload → event)
- [x] Event notification sound (Implemented in CrisisOverlay.vue)
- [x] Cascading deadlines (Feature 34) implemented in GameEventService.tick()

---

#### FEATURE 10: Asynchronous Multiplayer
**Phase:** ✅ Done | **Status:** ✅ Completed

| Component | Status |
|---|---|
| Leaderboards | ✅ Done |
| Market share competition | ✅ Done |
| Player comparison | ✅ Done |
| Shared economy events | ✅ Done |

**WHY Phase 3:** Multiplayer requires all core systems to be stable first. Adding competition before the single-player loop works would be premature.

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
| Bonus application | 🟡 Partial | Power, Security, Energy bonuses used |
| Research UI | ✅ Done | `ResearchOverlay.vue` with tech cards |
| Research button | ✅ Done | Management section in LeftPanel |

**WHY MVP:** Research gives the player long-term goals and passive progression. It's the "hope" mechanic.

**TODO:**
- [x] Apply `provisioning_speed` bonus to Order Provisioning
- [x] Apply `customer_quality` bonus to order price generation & SLA tier
- [x] Gate `unlock_rack_42u` in rack purchase logic
- [x] Add research projects: Security Shield, Auto-Recovery, Energy Optimizer
- [x] Completion notification toast
- [x] Research dependency chains (need Level 2 Cooling before Level 1 Security)

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
| Settings overlay | ✅ Done | Volume, Mute, Theme, Logout |
| Tutorial/onboarding | ✅ Done | Immersive multi-step guide with spotlighting |
| Keyboard shortcuts | ✅ Done | 1-4, R, F, S, C, A, T, Esc |

**WHY MVP:** The UI IS the game for a browser game. Premium feel is non-negotiable.

**TODO:**
- [x] Settings overlay (game speed, sound toggle, notifications)
- [x] Tutorial: First-time player guidance (contextual hints & spotlight)
- [x] Keyboard shortcuts (1-4 for rooms, Space for pause, E for events)

---

#### FEATURE 14: Modern UI / UX Overhaul
**Phase:** 🟢 MVP | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Responsive layout | ✅ Done | Context-based 3-zone layout |
| Sidebar Navigation | ✅ Done | Professional vertical sidebar |
| SaaS Dashboards | ✅ Done | Tabbed Management & Infrastructure views |
| News Ticker | ✅ Done | Integrated in TopBar |
| Slide Panels | ✅ Done | Right-side shop drawer |

**TODO:**
- [x] Hierarchical navigation system
- [x] Informational top status bar
- [x] Slide-out hardware drawer
- [x] Clean incident alert drawer
- [ ] Mobile-responsive adjustment for tablets

---

#### FEATURE 15: Backend Technical Systems
**Phase:** 🟢 MVP | **Status:** ✅ Done

| Component | Status | Info |
|---|---|---|
| Tick system | ✅ Done | Minute-based economy simulation |
| Game speed controls | ✅ Done | 1x, 2x, 5x & Pause support |
| Logging system | ✅ Done | User-facing event log |
| Error handling | ✅ Done | Basic try/catch for loop safety |

**WHY MVP:** Without the backend, nothing works. It IS the game engine.

**TODO:**
- [x] WebSocket via Laravel Reverb for real-time updates
- [x] Game speed controls (1x, 2x, 5x in frontend; logic in backend)
- [x] API rate limiting (prevent abuse)
- [x] Cache `getFullState()` to reduce DB load

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
**Phase:** 🟢 MVP | **Status:** ✅ Done (Infrastructure)

| Component | Status | Details |
|---|---|---|
| Multiple regions (EU, US, Asia) | ✅ Done | US East, US West, EU Central, Asia East |
| Latency per region | ✅ Done | Modifies provisioning and demand |
| Region-specific costs | ✅ Done | Base power costs differ per region |
| Region-specific competition | ✅ Done | Competitors have HQ and regional shares |

**WHY Phase 3:** Regions multiply the entire game. All systems must work in one location first.

**TODO:**
- [x] `regions` config with modifiers (power_cost, customer_demand, latency)
- [x] Region selection when buying a new room
- [x] Market Intelligence: Regional breakdown and ranking
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
| Cooling automation | ✅ Done |
| Garbage Collector (Contract Cleanup) | ✅ Done |
| Automation UI | ✅ Done |

**WHY Phase 2:** Late-game needs automation. Players should transition from "Racking" to "Orchestrating".

**TODO:**
- [x] Automation toggle UI (Scripts Overlay)
- [x] Backend: `auto_reboot` script in GameLoop
- [x] Backend: `auto_provisioning` script for pending orders
- [ ] Research unlocks for advanced scripts

---

#### FEATURE 20: Management Decisions & Specialization
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done (Expanded)

| Component | Status | Details |
|---|---|---|
| Strategic choices (pricing, focus) | ✅ Done | Expanded into 6 distinct Specializations |
| Specialization Hub (Rebranding) | ✅ Done | Modern UI for switching strategic paths |
| Strategic Passives | ✅ Done | Power efficiency, churn reduction, cooling penalties |
| Reputation Impact | ✅ Done | Rebranding affects market perception |

**WHY Phase 2:** Decisions define the player's identity. "What kind of hoster am I?"

**COMPLETED:**
- [x] Specialization Overlay with detailed metrics and passives
- [x] Backend: `SpecializationService` for weighted order generation
- [x] GameLoop integration for passive effects (thermal/power/reputation)
- [x] Global HUD Badge showing current strategic identity

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
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Component-based server assembly | ✅ Done |
| CPU/RAM/Disk selection | ✅ Done |
| Component market | ✅ Done |
| Custom configurations | ✅ Done |

**WHY Phase 2:** Custom builds allow players to optimize for specific customer needs.

**TODO:**
- [x] Complete UI and logic in `AssemblyOverlay.vue`
- [x] Ensure seamless integration with component inventory and server creation/disassembly
- [x] Disassembly logic to recover parts

---

#### FEATURE 25: Reputation Specialization
**Phase:** 🟡 Update 1 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Reputation categories | ✅ Done |
| Specialization bonuses | ✅ Done |
| Customer type attraction | ✅ Done |
| Reputation milestones | ✅ Done |

**WHY Phase 2:** Specialization answers "Who am I as a hoster?" and creates long-term strategy.

**DONE:**
- [x] Reputation categories: Budget, Premium, HPC, Green
- [x] Category grows based on actions, events, and research
- [x] Milestone bonuses: Tax breaks, SLA reductions, Bulk discounts, Heat reduction
- [x] Comprehensive Brand & Reputation UI

---

#### FEATURE 26: Sabotage & Espionage
**Phase:** ✅ Done | **Status:** ✅ Implemented

| Component | Status | Details |
|---|---|---|
| Corporate espionage events | ✅ Done | Integrated into GameLoop & Market Intelligence |
| Sabotage from competitors | ✅ Done | NPC Events (Price Wars, Marketing Pushes) |
| Security investment | ✅ Done | Player Skills (Security: Offense/Defense/Stealth) |
| Counter-espionage | ✅ Done | Detection chance modifiers & Market Intel |
| Sabotage Panel UI | ✅ Done | Target selection, Operation execution, History log |

**WHY Phase 3:** Implemented proactively to enhance competitive depth immediately.

---

#### FEATURE 27: Performance Statistics & Event Log
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| UserStatistics model (Snapshots) | ✅ Done |
| Analytics Dashboard (Charts) | ✅ Done |
| Security & Ops History Log | ✅ Done |
| Financial History Table | ✅ Done |

**TODO:**
- [x] Record snapshot in `GameLoopService` tick
- [x] SVG Sparklines and Revenue Area chart in `StatsOverlay.vue`
- [x] Fetch resolved/failed events and display in 'Security & Ops Log'
- [x] Tabbed UI for switching between analytics and event logs
- [x] Dynamic visual trend indicators (▲/▼) based on historical data

---

#### FEATURE 28: Hardware Lab (Sandbox Mode)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Completed

| Component | Status | Details |
|---|---|---|
| Simulation Engine | ✅ Done | Real-time thermal & power load calculation |
| Catalog Prototyping | ✅ Done | Pick from all available components, even if locked |
| Blueprint Viewer | ✅ Done | High-tech visual representation of the build |
| Cost Calculator | ✅ Done | Precise ROI and purchase price estimation |

**WHY Phase 2:** Hardware is expensive. Players need a way to blueprint "The Perfect Node" without failing the balance sheet.

**COMPLETED:**
- [x] Backend Simulation Controller (`SandboxController`)
- [x] Frontend Component Lab (`SandboxOverlay.vue`)
- [x] Direct purchase integration from valid simulations
- [x] Access via "Hardware Lab" in Management Actions

---

#### FEATURE 29: Replay & Timeline
**Phase:** 🟢 Update 2 | **Status:** ✅ Done (Analytic Timeline)

| Component | Status |
|---|---|
| Action history replay | ✅ Done |
| Timeline scrubbing | ✅ Done |
| Log-synced replay | ✅ Done |

**WHY Phase 3:** Implemented to allow retrospective analysis of management decisions and crisis handling.

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

#### FEATURE 36: Advanced Network Simulation
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Regional Latency Bases | ✅ Done | Different base MS per region (US, EU, Asia, SA) |
| Network Tiers (ISP) | ✅ Done | Tier 3 (Standard) to Tier 1 (Anycast) infrastructure |
| Tier-based Resilience | ✅ Done | Higher tiers resist DDoS and congestion penalties |
| Regional Preferences | ✅ Done | Customers prefer specific regions; mismatched regions penalize satisfaction |

**WHY Phase 3:** Network engineering becomes a strategic differentiator. Location matters.

**COMPLETED:**
- [x] Regional base latency logic in `GameRoom`
- [x] Network infrastructure upgrade path (4 Tiers)
- [x] Regional matching logic in satisfaction loop
- [x] UI: Network Backbone upgrades and Client Region Preference display

---

### 🔴 PHASE 3: LATE GAME / META
*Cascading failures, NPC competition, crisis mastery.*

---

#### FEATURE 31: System Dependencies & Cascades
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Power→Cooling dependency | ✅ Done |
| Rack→Server cascading failures | ✅ Done |
| Room-level blackouts | ✅ Done |
| Chain reaction simulation | ✅ Done |

**WHY Phase 3:** Cascades are "expert mode." They multiply chaos. The base game must feel manageable first.

**COMPLETED:**
- [x] If power fails → cooling stops → all servers overheat
- [x] If rack overheats → all servers in rack degrade
- [x] Cascading event chains: HW failure → DDoS vulnerability → Security breach / Sabotage
- [x] Incident Failures: Late responses trigger secondary catastrophes.

#### FEATURE 32: Technical Debt
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Old server inefficiency | ✅ Done |
| Legacy system costs | ✅ Done |
| Upgrade vs replace decisions | ✅ Done |
| Debt accumulation meter | ✅ Done |

**WHY Phase 3:** Technical debt is a late-game pressure. Early game players shouldn't worry about it.

**COMPLETED:**
- [x] Servers older than X days cost 10% more power
- [x] "Technical Debt Score" visible in stats
- [x] Upgrade path: Pay to modernize old servers (Hardware Modernization)

---

#### FEATURE 33: Experimental Technology
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Experimental server types | ✅ Done |
| Risk/reward unlocks | ✅ Done |
| Beta hardware | ✅ Done |
| Breakthrough events | ✅ Done |

**WHY Phase 3:** Experimental tech is the "gamble" mechanic. Requires a stable economy first.

---

#### FEATURE 34: Time-Pressure Events
**Phase:** 🟢 MVP | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Event deadlines | ✅ Done | `deadline_at` on GameEvent |
| Escalation timers | ✅ Done | Warning → Active → Escalated |
| Cascading time pressure | ✅ Done | Active events speed up deadlines of others (GameEventService) |
| Decision-under-pressure | ✅ Done | Actions with cost & success chance in Resolution UI |

**WHY MVP:** Time pressure IS the core tension. The base mechanic works, and cascading deadlines add expert-level depth.

---

#### FEATURE 19: Energy Hub & Operational Policies
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Spot Price Simulation (Fluctuation) | ✅ Done |
| Fixed Rate Contracts ($100 fee) | ✅ Done |
| Strategic Policies (Eco, Performance, Grid) | ✅ Done |
| Backend Modifier Logic (Draw/Income) | ✅ Done |

**TODO:**
- [x] Energy Market UI with Sparklines
- [x] Contracting logic with expiry
- [x] Operational policies toggle and modifiers
- [x] Satisfaction/Reputation impact integration.

---

#### FEATURE 35: NPC Competitors
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| NPC hosting companies (Macrosoft, NeoNet, etc) | ✅ Done |
| Market share competition simulation | ✅ Done |
| Market Intelligence UI Overlay | ✅ Done |
| NPC behavior patterns (Aggression & Expansion) | ✅ Done |

**WHY Phase 3:** NPC competition is the primary external pressure in the late game.

**COMPLETED:**
- [x] Create Competitors table and Seeder
- [x] Implement MarketService for share calculation
- [x] Add Market Intelligence UI with Pie Chart
- [x] Add random price wars and marketing attacks from NPCs (NPC Behavior Patterns)

**WHY Phase 3:** Complete behavior (attacks) requires the full security and sabotage system.

---

#### FEATURE 36: Logistics & Shipping
**Phase:** 🟡 Phase 2 | **Status:** ✅ Completed

| Component | Status | Details |
|---|---|---|
| Dual Shipping System | ✅ Done | Standard (2-4m) vs Express (30-60s) options |
| Transition Timers | ✅ Done | Asynchronous hardware arrival via GameLoop |
| Shipment HUD | ✅ Done | "Incoming Shipments" list with countdowns & progress |
| Logistic Costs | ✅ Done | Express shipping fee (Base + 5% item value) |

**WHY Phase 2:** Makes planning ahead essential. Express shipping is a "gold sink" for urgent repairs or expansions.

**COMPLETED:**
- [x] Backend Simulation Controller (`SandboxController`)
- [x] Frontend Component Lab (`SandboxOverlay.vue`)
- [x] Choice between Standard/Express in Hardware Shop
- [x] Dynamic visual progress bars for incoming parts

---

#### FEATURE 37: Backup & Recovery Gameplay
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Backup configuration | ✅ Done |
| Data loss events | ✅ Done |
| Recovery procedures | ✅ Done |
| Backup cost vs risk trade-off | ✅ Done |

**WHY Phase 3:** Backups create a "prevention vs reaction" choice. Requires maintenance system first.

---

#### FEATURE 38: Custom Difficulty Modes
**Phase:** 🔴 Update 2 | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Easy / Normal / Hard | ✅ Done | Handled via scalar multipliers for economy, XP, and satisfaction |
| Ironman Mode | ✅ Done | High risk, high reward (XP boost), rapid event frequency |
| Dynamic Modifiers | ✅ Done | Integrated into GameLoopService and GameEventService |

**WHY Phase 3:** Tailors the challenge to different player styles.

---

#### FEATURE 39: Player Skill Specializations
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Skill tree (player, not tech) | ✅ Done |
| Permanent character bonuses | ✅ Done |
| Level-up skill points system | ✅ Done |

**WHY Phase 3:** Player specialization is the "identity" endgame. Requires all systems to branch from.

---

#### FEATURE 40: API & Automation Simulation
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Virtual API endpoints | ✅ Done |
| Traffic simulation (RPM) | ✅ Done |
| Latency/Uptime tracking | ✅ Done |
| Automated revenue stream | ✅ Done |

**WHY Phase 3:** Provides a way for idle high-spec servers to generate value without active customer orders.

**DONE:**
- [x] `api_endpoints` table and model
- [x] `ApiService` for traffic and performance simulation
- [x] Automation & Services UI in expanded `AutomationOverlay`
- [x] Deploy/Decommission workflow for virtual APIs

---

#### FEATURE 41: Crisis Management Score
**Phase:** 🔴 Update 2 | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Grade calculation (S/A/B/C/F) | ✅ Done | Based on resolution time and financial fallout |
| Reputation impact per grade | ✅ Done | Bonus reputation/XP for S-rank performance |
| Crisis history scoring | ✅ Done | Persistent metrics for management performance |

**WHY Phase 3:** Scoring provides the "Competitive" edge for high-level management.

---

#### FEATURE 42: Real-Time vs Idle Decisions
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Offline progress | ✅ Done |
| Idle income calculation | ✅ Done |
| Catch-up mechanics | ✅ Done |
| "What happened while you were away" report | ✅ Done |

**WHY Phase 3:** Idle mechanics change the entire game loop design. Must be intentional.

**DONE:**
- [x] Persistent background simulation in CRON `game:tick`
- [x] `ActivityService` for summary generation
- [x] Login Summary UI Overlay
- [x] Financial and event impact reporting

---

#### FEATURE 43: Smart Contextual Tooltips
**Phase:** 🟢 Phase 2 | **Status:** ✅ Complete

| Component | Status |
|---|---|
| Hover tooltips on UI elements | ✅ Done |
| Contextual help system | ✅ Done |
| Live state-aware tooltips | ✅ Done |
| Tactical advice hint system | ✅ Done |

**WHY Phase 2:** Tooltips replace tutorials. They teach organically. High value, medium effort.

**DONE:**
- [x] `Tooltip.vue` global component
- [x] `tooltip.js` Pinia store for state management
- [x] Integration into `TopBar` metrics (money, servers, customers, orders)
- [x] Hint system for tactical advice
- [x] **Smart Racks:** Detailed power, heat, and space metrics on hover
- [x] **Dynamic Updates:** Tooltips update state while visible
- [x] **Warning Indicators:** Automatic high-heat alerts in tooltip hint

---

#### FEATURE 44: Post-Mortem Reports
**Phase:** 🔴 Update 2 | **Status:** ✅ Complete

| Component | Status | Details |
|---|---|---|
| Event summary after resolution | ✅ Done | Detailed technical audit of what happened |
| Timeline of event progression | ✅ Done | Warning → Active → Resolved sequence |
| Impact analysis | ✅ Done | Direct loss vs. SLA penalties vs. mitigation costs |

**WHY Phase 3:** Post-mortems are "reflection" gameplay. Provides detailed technical audit and improvements for the player.

---

#### FEATURE 45: Story-Driven Achievements
**Phase:** 🟡 Update 1 | **Status:** ✅ Done

| Component | Status |
|---|---|
| Achievement system | ✅ Done |
| Story-flavored unlock text | ✅ Done |
| Milestone rewards | ✅ Done |
| Achievement overlay | ✅ Done |

**WHY Phase 2:** Achievements guide player behavior and add long-term goals. They tell the story.

**DONE:**
- [x] `achievements` table (key, title, description, condition, reward)
- [x] Achievements for Infrastructure, Economy, Crisis Management
- [x] Real-time Toast popup when achieved
- [x] "Hall of Records" UI for browsing history

---

## 🎯 RECOMMENDED NEXT STEPS (Priority Order)

### 🔥 Critical for Playable MVP (Do NOW)

| # | Task | Impact | Effort |
|---|---|---|---|
| 1 | **Fix Economy** — Real power costs | ✅ Done | Low |
| 2 | **Temperature Simulation** — GameLoop updates temp | ✅ Done | Medium |
| 3 | **XP Awards** — Award XP on actions | ✅ Done | Low |
| 4 | **Room Purchase** — Buy Garage/Hall/DC | ✅ Done | Medium |
| 5 | **Customer Churn** — Satisfaction + leaving | ✅ Done | Medium |

### 🟡 High Value Next (Do SOON)

| # | Task | Impact | Effort |
|---|---|---|---|
| 6 | **Fix remaining 3 event types** — Complete | ✅ Done | Low |
| 7 | Apply all research bonuses | ✅ Done | Low |
| 8 | Server maintenance/repair | ✅ Done | Medium |
| 9 | Financial transaction log | ✅ Done | Medium |
| 10 | Sound effects (Howler.js/WebAudio) | ✅ Done | Medium |

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

---

### 🔴 PHASE 4: PROFESSIONAL SIMULATION UPDATE
*Advanced architecture, global business strategy, and hardcore realism.*

---

#### FEATURE 46: Dynamic Energy Markets
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

- **Mechanism:** Hourly price fluctuation based on world events.
- **Decision:** Fixed price contracts (safe) vs. Variable spot market (risky but potentially cheaper).
- **Integration:** Directly affects Feature 8 (Economy) and Feature 4 (Power).

**DONE:**
- [x] `EnergyService` with spot price simulation and history
- [x] Fixed-rate energy contracts (Short/Long term)
- [x] Energy Policies (Eco Mode, Performance, Grid Saver)
- [x] `EnergyOverlay` UI with live sparkline charts

#### FEATURE 47: Cooling Zone Management
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

- **Mechanism:** Airflow simulation (Hot/Cold Ailes). Rack grouping into zones.
- **Integration:** Extends Feature 5 (Cooling). Individual rack temperatures now interact with neighbors.
- **UI:** Aisle-based visualization (Service/Hot/Cryo Aisles).

**DONE:**
- [x] Grid-based heat transfer (Bleeding) between neighboring racks.
- [x] Airflow optimization impact on heat bleed rates.
- [x] Thermodynamic aisle visualization in GameWorld.
- [x] Logic for Row/Aisle neighbor identification (2-row system).

#### FEATURE 48: Redundancy Levels (N+1, 2N)
**Phase:** 🔴 Update 2 | **Status:** ✅ Done

- **Mechanism:** Investment in backup power/cooling. Tiers 1-4 (Standard to Fault Tolerant).
- **Decision:** Pay high costs for HA (Stability) vs. Single path (Risk).
- **Integration:** Reduces failure probability. Mitigates escalation damage. Adds specialized High-Availability event actions.

**DONE:**
- [x] Multi-tier redundancy system (N+1, 2N, 2(N+1)).
- [x] Mitigation logic for Power/Cooling failures.
- [x] Redundancy-based event probability reduction.
- [x] Specialized HA event actions (Seamless failover).
- [x] Infrastructure upgrade UI for HA tiers.

#### FEATURE 49: Contract Negotiation System
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done

- **Mechanism:** Bidding for major clients (Whales).
- **Decision:** Price vs. SLA. Lowering SLA increases profit but risks huge penalties.
- **Integration:** Integrated into OrderOverlay and new ContractNegotiationOverlay.

#### FEATURE 50: Hardware Generations & Depreciation
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done

- **Mechanism:** Servers belong to generations (Gen 1 Legacy, Gen 2 Standard, Gen 3 Next-Gen).
- **Impact:** Newer generations are more expensive but have better power efficiency (-20%) and performance (+30-70%).
- **Depreciation:** Servers lose value over time based on their generation's depreciation rate.
- **Selling:** Players can sell offline servers for their current resale value.
- **Integration:** Shop UI updated with generation selector.

**DONE:**
- [x] `hardware_generations` table and seeded data
- [x] `HardwareDepreciationService` for real-time value updates
- [x] Generation efficiency multipliers for Power/Heat/CPU/RAM
- [x] Resale value calculation logic
- [x] Shop UI generation selector (Gen 1-3)
- [x] Sell button in server details

#### 51-65: Advanced Feature Layer (Coming Soon)
A detailed list including:
- [51] Hardware Diagnostics (Mini-interactions)
- [51] Hardware Diagnostics (Mini-interactions)
- [53] Compliance (ISO/GDPR Audits)
- [58] Reputation Scandals (Data Leaks)
- [61] Executive Business Dashboard
- [65] Global Crisis Scenarios (✅ Done)

#### FEATURE 53: Compliance & ISO Audits
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Certification Model | ✅ | ISO 27001, GDPR, Tier III/IV standards |
| Audit Mechanic | ✅ | Timed verification process with progress tracking |
| Compliance Engine | ✅ | Score-based system (Security/Privacy) linked to research & employees |
| Regulatory Fines | ✅ | Randomized fines for low compliance posture |
| Enterprise Customers | ✅ | High-tier orders (Enterprise/Whale) gated by certifications |
| Compliance Dashboard | ✅ | Dedicated overlay for managing audits and certs |

**WHY Phase 2:** Introduces regulatory pressure. It balances pure profit-seeking by forcing the player to invest in security and quality to access the "whale" customers.


---

#### FEATURE 55: Professional Skill Trees
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Employee XP System | ✅ | XP gain from tasks, leveling up (Lvl 1-20) |
| Skill Points | ✅ | 1 SP per level, spent on perks |
| Role-Specific Trees | ✅ | Unique trees for SysAdmin, Support, Security, etc. |
| Perk Effects | ✅ | Repair speed, energy efficiency, specialized actions |
| Skills UI | ✅ | `EmployeeSkills` overlay interaction |

**WHY Phase 2:** Staff specialization adds depth to the personnel management layer.

---

#### FEATURE 52: User Profile Control Center
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Profile Overview Panel | ✅ | Avatar, username, company name, rank/level badge, reputation, join date, last login, account status, edit/save/cancel flow |
| Account Settings | ✅ | Email, password change, language, timezone, date format, notification preferences. |
| Game Preferences | ✅ | UI theme (Dark/Light/Custom), alert intensity, sound toggle. |
| Company Customization | ✅ | Image upload (Avatar/Banner), company slogan, accent color picker. |
| Security Center | ✅ | IP login history, suspicious activity log. |
| Privacy Settings | ✅ | GDPR data export. |
| Achievements & Stats | ✅ | Revenue earned, crises survived, prestige level, stat history sparklines. |
| Route & Navigation | ✅ | Multi-tab overlay navigation. |

**WHY Phase 2:** The profile area is the player's identity hub. It transforms the experience from "playing a game" to "managing a professional account." Critical for retention, personalization, and GDPR compliance.

**DESIGN REQUIREMENTS:**
- Must feel like a **SaaS control panel** (Stripe, Vercel, Cloudflare dashboard quality)
- Dark themed, structured in tabs or sidebar navigation
- Cards with clear typography, soft accent colors
- Smooth animations, no clutter, no cheap modal stacking
- Professional component library feel

**TECHNICAL REQUIREMENTS:**
- Separate profile route (`/profile`)
- Lazy load sections for performance
- API-based updates with backend validation
- CSRF protection on all mutations
- Rate limiting on critical actions (password, 2FA, deletion)
- Audit logging for security-sensitive changes

**TODO:**
- [x] Create `ProfileController` with CRUD endpoints
- [x] Create `/profile` route in Vue Router (Integrated via Overlay)
- [x] Build `ProfileApp.vue` with tab navigation (ProfileOverlay.vue)
- [x] Panel 1: Profile Overview (avatar, username, company, rank, reputation, join date)
- [x] Panel 2: Account Settings (email, password, language, timezone, 2FA, sessions)
- [x] Panel 3: Game Preferences (theme, alerts, sound, density, live preview)
- [x] Panel 4: Company Customization (logo upload + crop, slogan, accent color, banner)
- [x] Panel 5: Security Center (2FA setup, backup codes, device list, IP history, risk indicator)
- [x] Panel 6: Privacy Settings (visibility toggles, GDPR export, account deletion)
- [x] Panel 7: Achievements & Stats (uptime %, revenue, crises, timeline, charts, export)
- [x] Avatar click in TopBar opens profile panel
- [x] Inline validation on all editable fields
- [x] Confirmation modals for dangerous actions (delete account, disable 2FA)
- [x] Responsive layout for all screen sizes

---

## 🛠 PROFESSIONAL INFRASTRUCTURE & LIVE-OPS

### PART 1: Full Admin Panel System
Separate management interface for:
- **User Management:** Multi-account detection, bans, manual balance adjustments.
- **Economy Control:** Real-time tuning of multipliers (Inflation, demand).
- **Event Orchestration:** Manually triggering "Black Swan" events for all/selected players.
- **Audit Logs:** Every admin action must be reversible and logged.

### PART 2: DEV & QA Tools
- **State Manipulation:** Fast-forward time, reset specific rooms.
- **Simulation Sandbox:** Test stress limits of the energy market.
- **Visual Debug:** Overlays for heat maps and airflow paths.

### PART 3: Community & Social
- **Shared News Feed:** Every major player outage appears in the "Global Crisis Feed".
- **Alliances:** Collaborative infrastructure projects (Sea-Cable).
- **Leaderboards:** Based on "Crisis Resilience Score" and net-worth.

### PART 4: Fair Monetization (Cosmetics Only)
- **Visual Only:** Rack skins, custom LED patterns, HUD themes (Dark/Retro/High-Tech).
- **No Pay-To-Win:** Resources cannot be bought for real money.

---

## 🏛 ADVANCED DESIGN FOUNDATIONS

### 🧠 Hardcore Realism (Simulation Depth)
- **Airflow Simulation:** Fans are not just % cooling; they create thermal zones.
- **Aging:** Old servers draw 10% more power and have 2x failure rate.
- **Cascades:** One rack's fire can spread to neighbors if fire suppression research is missing.

### 🎮 Streamer & Drama Features
- **Crisis Overlays:** Dramatic visuals when a datacenter-wide failure occurs.
- **Public Feed:** Automated Twitch/Discord alerts for high-rank player disasters.
- **Highlights:** Automated event summaries with "What went wrong?" post-mortems.

### 🤖 AI Integration Layer
- **Advisors:** Smart NPCs that suggest optimizations (sometimes risky).
- **Predictive Failure:** AI tools that warn when probability of failure rises (can be wrong).
- **competitors:** AI hosters that undercut your prices or steal your customers.

---

## 📐 UPDATED DESIGN RULES (V4.0)

1. **Depth over Breadth:** Don't add a new window unless it interacts with existing Power/Heat/Cash.
2. **Every feature is a system:** No one-off buttons. Everything has a cost, a delay, and a risk.
3. **Multiplayer Scalability:** Every state change must be ready for real-time sync.
4. **Player Agency:** Realism must increase **Decision Pressure**, not micromanagement.
5. **No Isolated Gimmicks:** Every new feature must connect to at least 2 existing Tier 0-2 systems.

---

## 🧠 BALANCING RISKS (UPDATED)

| Risk | Cause | Fix |
|---|---|---|
| **Micromanagement Trap** | Too many manual fixes | Automation scripts (Feature 19) must scale. |
| **Chaos Fatigue** | Too many random events | Events must be predictable via diagnostics (Feature 21). |
| **Whale Dominance** | Top players block market | IPO system & Regulatory Audits (Feature 53/59) create friction for giants. |
| **Complexity Wall** | New players overwhelmed | Drip-feed features via the Level/Research gate. |

---

### 🚀 PHASE 5: LIVE-OPS & ENGINE CONTROL (THE OBSIDIAN ARCHITECTURE)
*The game is no longer a fixed loop. It is a dynamic, version-controlled platform.*

---

#### 🧱 1. DYNAMIC SYSTEM EDITOR (ENGINE LEVEL)
Fully decouple game logic from code. No hardcoded constants.
- **Infrastructure Module:** Real-time editing of Power/Cooling/Network laws and redundancy multipliers.
- **Server Module:** Adjust aging rates, failure probabilities, and overclocking risk curves.
- **Customer Module:** Edit patience thresholds, churn probability, and demand growth formulas.
- **Event Module:** Configure trigger thresholds, escalation logic, and cascading failure chains.

#### 📊 2. ECONOMIC CONTROL CENTER (GLOBAL TUNING)
Systemic multipliers instead of manual money editing.
- **Global Modifiers:** Income, Event frequency, Energy volatility, Inflation curves.
- **Impact Forecasting:** "Simulate 24h impact" before pushing a balance change to live players.
- **Anomaly Detection:** Automatic alerts if global churn or profit drops below safe thresholds.

#### 🧠 3. DYNAMIC FORMULA EDITOR
Live-edit game mechanics via math formulas (e.g. `churn = base + (downtime * x)`).
- **Sandboxed Validation:** Prevents infinite loops or game-breaking logic.
- **Dependency Tracking:** See which systems are affected by a formula change.

#### 🔄 4. THE REGISTRY (VERSIONING & ROLLBACK)
Enterprise-grade safety for Live-Ops.
- **Full History:** Every change is timestamped, IDed, and versioned.
- **One-Click Rollback:** Instant recovery from "Broken Balance" scenarios.
- **Diff Viewer:** Compare versions before deployment.

#### 🧪 5. ADMIN SIMULATION MODE (ISOLATED SANDBOX)
- **Worst-Case Simulation:** Trigger massive cascades to test server resilience.
- **Event Wave Stress Test:** Can the current player economy survive a global internet collapse?
- **Load Spike Scenarios:** Test AI-driven demand spikes (Feature 75).

---

### Feature 46: Thermal Heatmap (Visual Mode)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Toggle Button | ✅ Done | Button in `InfrastructureView` to switch modes |
| Rack Heatmap | ✅ Done | Visual coloring of racks based on temp (Blue -> Red) |
| Cooling Logic | ✅ Done | Integration with Airflow Types (Cold Aisle) |

**WHY:** Makes invisible simulation data (temperature) instantly readable and rewarding.

### Feature 47: Market Event Notifications (Push)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Ticker System | ✅ Done | "Breaking News" ticker in HUD |
| Event Triggers | ✅ Done | Alerts for Price Spikes (> $0.30) & World Events |
| Sound FX | ✅ Done | Urgent notification sound for critical market shifts |

**WHY:** Creates urgency and connects the abstract market to immediate gameplay actions.

#### FEATURE 48: Corporate Specialization (Level 10)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done

| Component | Status | Details |
|---|---|---|
| Unlock Logic | ✅ Done | Level 10 requirement check + 24h Cooldown |
| Spec Paths | ✅ Done | Eco-Certified vs High-Performance vs Budget Mass |
| Trade-offs | ✅ Done | Passive bonuses/maluses per path |
| UI | ✅ Done | Selection screen in Strategy Dashboard & Overlay |

### 🔋 MANAGEMENT & ENERGY 2.0 (49-53)

#### FEATURE 49: Energy Arbitrage Dashboard
**Phase:** 🟢 Phase 2 | **Status:** 🟡 Concept / Started
- [ ] Custom price thresholds for battery charging/discharging.
- [ ] Real-time ROI visualization for Solar vs. Grid power.
- [ ] Energy contract comparison tool (Variable vs. Fixed).

#### FEATURE 50: Visual Infrastructure Assets (Solar & Battery)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Render Solar Panels on the Facility Architect roof view.
- [x] Visual 1U/2U Battery Modules in the rack view.
- [x] State-based animations (charging/discharging LEDs).

#### FEATURE 51: Environmental System (Weather & Grid)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Completed
- [x] "Cloud Cover" events reducing solar yield by 40-80%.
- [x] "Grid Instability" triggers micro-outages or price spikes.
- [x] Weather forecast ticker in the HUD.

#### FEATURE 52: Advanced Support Doctrines
**Phase:** 🟡 Phase 2 | **Status:** ✅ Completed
- [x] Skill-Tree for Support Employees.
- [x] Doctrines: "Retention Specialist" vs. "High-Speed Resolution".
- [x] Passive bonus: 10% lower churn probability for happy customers.

#### FEATURE 53: Smart Traffic Orchestrator (Auto-Migration)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] AI module that migrates customers to optimal regions automatically.
- [x] Balance "Latency Satisfaction" vs. "Regional Power Cost".
- [x] Requires Level 30 + Anycast Research.

### 🧠 DEEP SIMULATION & REDUNDANCY (54-58)

#### FEATURE 54: Heat Recovery System (District Heating)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Research: "Thermal Grid Integration".
- [x] Mechanic: Sell waste heat to the city to offset cooling costs.
- [x] Bonus: Constant Green Reputation boost.

#### FEATURE 55: AI Operations Advisor (SaaS NPC)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] "Clippy-style" notifications for economic & thermal inefficiencies.
- [x] Contextual tips based on current power market volatility.
- [x] Risk: Can be hacked or provide faulty "Aggressive" advice.

#### FEATURE 56: Energy Futures & Derivatives
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Buy energy packages for future ticks at locked prices.
- [x] Speculate on price spikes during heatwave event forecasts.
- [x] Automatic consumption of hedged energy via EnergyService.
- [x] Includes "Financial Engineering" Research unlock.

#### FEATURE 57: Human Capital Risks (Burnout & Fatigue)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Support agents and Techs have a "Hidden Stress" meter.
- [x] High-intensity periods trigger "Sabbatical" requirements.
- [x] Low morale leads to slow resolution or critical repair errors.

#### FEATURE 58: Orbital Redundancy (Satellite Uplink)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Emergency network link during "Fiber Cut" or ISP outages.
- [x] Mechanic: Extremely high cost-per-GB, but prevents SLA breach.
- [x] Automatic failover logic in NetworkService.
- [x] Requires "Orbital Redundancy" Research.

### 🏛️ SCALING & STRATEGY (59-63)

#### FEATURE 59: Thermal Runaway (The Death Spiral)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Systemic Link: High CPU Heat (>80°C) increases Server Power Draw by up to 25%.
- [x] Consequence: Creates a cascading load that can pop the room circuit breaker.

#### FEATURE 60: Incident Post-Mortems (Retrospectives)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] After major events, players must file a "Report" (selecting causes/actions).
- [x] Success: Recovers 50% of the lost reputation from the event.
- [x] Bonus: Unlocks specific "Lessons Learned" research points.

#### FEATURE 61: Hybrid Cloud Bursting (Surge Capacity)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Rent temporary external capacity from "Global Cloud Providers".
- [x] Use Case: Handle server overload or provision orders without free slots.
- [x] Cost: 5x the normal operational cost (Emergency use only).
- [x] Requires "Hybrid Cloud Bursting" Research.

#### FEATURE 62: Modernization Missions (Legacy Refactor)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Long-term projects to modernize aged racks without replacement.
- [x] Effect: Reset server "Technical Debt" and power inefficiency for a fixed fee (80% life reset).
- [x] Mechanic: Requires "Modernization Protocols" Research.
- [x] Mechanic: Requires 1x Tech and 1x Lead Engineer (Level 10+) assigned for 30 minutes.

#### FEATURE 63: Corporate Academy (Training Lab)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Room upgrade: "Training Room".
- [x] Employees gain passive XP while idle in a room with an Academy.
- [x] Unlocks higher-tier Specialized Skills (Lvl 50+).

### 🛠 MECHANICAL DEPTH & IDLE LOGIC (64-68)

#### FEATURE 64: Fan Wear & Mechanical Fatigue
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Rack fans degrade when temperature exceeds 40°C (stored in pdu_status.fan_health).
- [x] Fan health reduces effective cooling per rack (min 50% passive airflow).
- [x] Fan Replacement action via API ($200/rack) restores fan health to 100%.
- [x] Fan health exposed in rack game state for frontend visualization.

#### FEATURE 65: Idle Strategy: Crypto Mining
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Toggle to use unallocated CPU/GPU power for Mining.
- [x] Trade-off: Generates small income but increases heat and power draw.

#### FEATURE 66: Dynamic Real Estate (Variable Rent)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Regional rent coefficients fluctuate based on world events.
- [x] "Tech Bubble" in US-East increases rent, while "Energy Crisis" in EU reduces it.

#### FEATURE 67: Physical Rack Integrity (Weight Load)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Racks have a max weight capacity. High-density GPU servers add more load.
- [x] Overloading increases hardware failure rate by 25% due to frame warping (MTBF reduction).

#### FEATURE 68: ISP Service Level Agreements (Contracts)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Choose between different upstream providers (Tier 1-3).
- [x] "Budget ISP" ($): Low cost, high failure chance (Micro-Outages).
- [x] "Tier-1 ISP" ($$$): High cost, guaranteed uptime (99.99%+ reliability).
- [x] Mechanic: Micro-outages cause temporary packet loss and latency spikes.

### 👔 HR ETHICS & CORPORATE SCALING (69-73)

#### FEATURE 69: Corporate Headhunting (Staff Retention)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Competitors attempt to poach employees (Lvl 5+, loyalty <40) every 60 ticks.
- [x] Headhunter offers 1.3x-1.8x salary with 10-minute deadline.
- [x] Counter-offer API: match/exceed/reject the competitor's salary.
- [x] Synergizes with F128 (Golden Handcuffs) for retention protection.

#### FEATURE 70: Strategic Doctrine: "The Discount King"
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] New Specialization path for volume-based, low-cost hosting.
- [x] Boosts customer count by 50% via `order_frequency_boost`.
- [x] Satisfaction decay is 2x faster via `satisfaction_penalty_multiplier`.

#### FEATURE 71: Employee Synergy Systems
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Buffs when specific roles work in the same room.
- [x] "DevOps Combo": SysAdmin + Support reduces provisioning time.

#### FEATURE 72: Regulatory Audits: ISO Certification
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Invest in ISO-9001/27001 certifications.
- [ ] Unlocks Government & Banking contracts (unlimited budgets, strict SLAs).

#### FEATURE 73: Circadian Fatigue (Night-Shift Scaling)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Employees working "off-hours" (22:00-06:00 game time) gain stress 1.5x faster.
- [x] Managing shifts becomes a strategy for globally distributed teams.

### 🎨 IMMERSION & ANALYTICS (74-78)

#### FEATURE 74: UI: Accessibility & High-Contrast Modes
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Support for high-contrast palettes and SaaS-grade Dark Mode.

#### FEATURE 75: Datacenter Minimap (Multi-Room Overview)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Compact grid showing all rooms and their thermal/power health at a glance.
- [x] Implemented `DatacenterMinimap.vue` component in the HUD.
- [x] Integrated into `LeftPanel.vue` for continuous oversight in the main overview.

#### FEATURE 76: Adaptive Audio Ecosystem
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Music intensity scales with current incident density and thermal alarms.

#### FEATURE 77: Visual Identity: Procedural Client Branding
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Generated brands include random background color arrays and logo shapes (circle/square/rounded).
- [x] 'brand' array pushed to frontend state. 
- [x] CustomersOverlay displays vibrant visual identites instead of plain lists.

#### FEATURE 78: Incident Retrospectives (Formal Logic)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] After major crises, the player must sign a formal incident report.
- [ ] Restores 25% of reputation if the report accurately identifies the cause.

### 💻 ARCHITECTURE & DESIGN DEPTH (79-84)

#### FEATURE 79: WebSocket-Driven Dashboards
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Implement Laravel Reverb for real-time ticket state transitions.
- [x] UI: Tickets "slide out" on resolution without page refresh.

#### FEATURE 80: Chaos Seeders (Stress Testing)
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Artisan command (`game:chaos`) to generate high-stress scenarios (50+ customers, multiple concurrent events).
- [x] Validate dashboard performance under heavy load using seeded chaos.

#### FEATURE 81: Unified Metadata Strategy
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Standardize the use of JSON `metadata` columns for flexible model extensions.
- [x] Implemented on `servers`, `server_racks`, `game_rooms`, `customers`, and `employees`.
- [x] Use for ticket reasoning, battery health logs, and burnout tracking.

#### FEATURE 82: Contextual Audio Notifications
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Implement urgency-based SFX (e.g., subtle alert for new tickets vs. siren for outages).

#### FEATURE 83: Strategic Greed: Circuit Breaker Logic
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Room circuit breaker trips when power draw exceeds 110% of capacity.
- [x] 5% chance per tick when overloaded; randomly shuts down 1-3 servers.
- [x] Tripped servers marked OFFLINE with 'Circuit Breaker Trip' fault.
- [x] Rack power automatically recalculated after trip.

#### FEATURE 84: Procedural Startup Identities
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] 36 creative company prefixes (Nebula, Vertex, Cipher, CloudSpire, ByteShift, etc.).
- [x] 22 modern suffixes (Analytics, AI, Protocol, Engine, Compute, etc.).
- [x] 20 handcrafted startup fullnames (Quantum Bloom, Neon Wavelength, etc.).
- [x] 3 name formats: 30% fullnames, 40% prefix+suffix combo, 30% modern style (.io, .ai, GmbH).
- [x] Enterprise names (FlixNet, AmazeOn) and Gov names preserved as special tiers.

### 🛡️ REDUNDANCY & FINANCIAL DEPTH (85-89)

#### FEATURE 85: HA-Cluster (Server Redundancy)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Link two identical servers as a High-Availability pair.
- [x] Prevents ticket generation on single-node failure if a healthy failover exists.
- [x] Trade-off: 2x power, 2x slots, 2x costs (dual provisioning required).

#### FEATURE 86: Hardware Leasing (Fixed Opex)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Rent servers/components for a monthly fee instead of upfront CapEx.
- [x] Higher long-term cost, but lower barrier to entry for new players.

#### FEATURE 87: Thermal Heat Map Visualization
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] 2D/3D overlay in Facility Architect showing thermal zones.
- [x] Identify cooling bottlenecks and airflow dead-zones visually.

#### FEATURE 88: Geographic Data Resiliency (Off-Site Backup)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Transfer and store backups in different regions (e.g., US-East to EU-Central).
- [ ] Protection against total datacenter loss events.

#### FEATURE 89: Employee Loyalty & Retention
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] 'loyalty' database column on `employees` table (0-100 scale, default 50).
- [x] Backend Simulation: Loyalty drops due to high stress, rises with low stress. Raises/Sabbaticals give large boosts.
- [x] Attrition Risk: Employees with <15 loyalty have a chance to resign every tick. <5 loyalty triggers instant resignation and deletion from the system.
- [x] Frontend: New purple "LOYALTY" telemetry bar added to staff cards.

### 🏢 CORPORATE STRATEGY & GLOBAL OPS (90-94)

#### FEATURE 90: PR Agency (Reputation Recovery)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] "PR Crisis Response Team" campaign ($25k, 2h) — available only when reputation < 60, +30 rep, 3× recovery speed.
- [x] "PR Brand Ambassador Program" campaign ($50k, 8h) — available at rep ≥ 30, +50 rep, 2× recovery speed.
- [x] max_reputation gate prevents high-rep players from abusing crisis campaigns.
- [x] getReputationRecoverySpeed() multiplier integrated into GameLoopService passive rep recovery.
- [x] Works synergistically with PR Liaison employee specialization.

#### FEATURE 91: Cyber-Insurance Policy
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Subscription-based insurance for DDoS and Ransomware events (3 tiers: Basic/Professional/Enterprise).
- [x] Covers 50-80% of costs/penalties during active coverage.
- [x] Integrated with GlobalCrisisService for automatic cost reduction.
- [x] API endpoints for subscribe/cancel/state.

#### FEATURE 92: Direct Peering Agreements (IXP)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Negotiate with IXPs (DE-CIX, Equinix) for direct routes.
- [ ] Massively reduces latency in specific target regions.

#### FEATURE 93: Regional Power Rationing (Blackouts)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] World events triggering rolling blackouts in specific regions based on energy market stress.
- [x] Regional capacity limits enforced; automated priority-based server shutdowns.

#### FEATURE 94: Scalable Customer Entities
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Successful customers grow over time, requesting additional capacity.
- [x] Opportunity for "Up-selling" or long-term contract expansion.

### ⚖️ COMPLIANCE, EXTREMES & LEGAL (95-99)

#### FEATURE 95: Global Data Compliance (GDPR)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Regional data laws (EU/US/Asia) affecting data storage locations.
- [x] Non-compliance penalties for illegal cross-border data transfers.

#### FEATURE 96: Sector-Specific Certifications
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Lukrative verticals (Healthcare, Finance) require "Biometric Racks" and "Hardware Audits".
- [ ] Failure to maintain certifications triggers contract termination.

#### FEATURE 97: Server Overclocking (Performance Hub)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Push servers beyond spec for +20% compute performance.
- [x] Malus: +50% heat, +100% power draw, and 3x hardware degradation rate.

#### FEATURE 98: Component Quality & MTBF Logic
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Integration of "Mean Time Between Failure" (MTBF) for components.
- [ ] Cheap parts have catastrophic failure events; premium parts provide stability.

#### FEATURE 99: Environmental Risk: Humidity Controls
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Advanced cooling rooms require humidity management.
- [ ] Dry air triggers "Static Discharge" events; damp air leads to "Corrosion/Short-Circuit".

### 👔 STAFF MORALE, FINANCE & BGP (100-104)

#### FEATURE 100: Employee Burnout & Resilience
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Cumulative stress leads to "Burnout" events (unavailability for several days).
- [x] Morale affects work speed and the success chance of complex repairs.

#### FEATURE 101: Facilities & Retention (Loyalty)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Build "Corporate Lounges" or "Coffee Stations" in DC facilities.
- [x] Passively increases employee loyalty and reduces the chance of poaching.

#### FEATURE 102: Labor Union & Strike Events
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Low employee satisfaction triggers strikes.
- [x] All manual/auto maintenance stops for 24h unless demands are met.
- [x] Negotiation mechanics (Bonus vs. Health Plan).

#### FEATURE 103: BGP-Hijacking (Mega-Crisis Event)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Global network event where the player's IP range is "stolen".
- [ ] Instant 100% downtime unless Anycast Routing is active.

#### FEATURE 104: IPO & Shareholder Accountability
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Go public to receive a massive cash injection.
- [ ] Consequence: Quarterly profit targets; failing them increases "Investor Pressure" (Malus on all costs).

### 🛠 META & ADVANCED IMMERSION (105-109)

#### FEATURE 105: Illegal Botnets (Darknet Expansion)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Use idle capacity to host Botnets or C&C nodes for high revenue.
- [ ] Risk: Massive reputation loss and legal raids if detected.

#### FEATURE 106: Rack Hardware Identity (RGB & Aesthetics)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Custom LED patterns and rack colors (Aesthetics affecting "Client Trust" during visits).

#### FEATURE 107: Live Monitoring Wall (Interactive HUD)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Procedural monitors in the Facility Architect showing real-time metrics graphs.

#### FEATURE 108: Dynamic Fan Noise Scaling
**Phase:** 🟢 MVP | **Status:** 🔴 To Do
- [ ] Ambient noise floor scales with total server load and fan intensity.

#### FEATURE 109: Hardcore "Obsidian" Mode
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Permadeath equivalent: Bankruptcy is terminal, no manual saves, high event frequency.

### 🏛️ ENGINE ARCHITECTURE & PHILOSOPHY (110-112)

#### FEATURE 110: Modularized Simulation Engine
**Phase:** 🟢 MVP | **Status:** 🟡 In Progress
- [ ] Split `GameLoopService` into specialized sub-services (`RiskService`, `PerformanceService`, `EthicsService`).
- [ ] Decouple simulation logic from the main tick loop for easier unit testing.

#### FEATURE 111: Progressive Difficulty Gating
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Tie advanced crises (BGP Hijacking, GDPR Compliance) to specific player levels (30+).
- [x] Ensure new players aren't overwhelmed by expert-level mechanics.

#### FEATURE 112: Scarcity-Driven Balancing
**Phase:** 🟢 MVP | **Status:** 🟡 In Progress
- [ ] Fine-tune high-end hardware and HA-cluster costs.
- [ ] Maintain constant "Decision Pressure" by ensuring total cash is always slightly below the next major upgrade.

### 🎮 UX, SECURITY & PROFIT MAXIMIZATION (113-122)

#### FEATURE 113: Hover-Tooltips for Performance Metrics
**Phase:** 🟢 MVP | **Status:** 🔴 To Do
- [ ] Tooltips on "Energy/Heat" HUD elements showing the top 3 consumers.
- [ ] Instant identification of bottlenecks without opening sub-menus.

#### FEATURE 114: Multi-Action "Quick-Fix" Shortcuts
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Keybinding (e.g., ALT+F) to prioritize and dispatch techs to critical failures.

#### FEATURE 115: "Warp-Ten" Speed (Late-Game Unlock)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Advanced time-acceleration (10x speed) for high-level infrastructure phases.

#### FEATURE 116: Ethical Hacking Quests (Bounties)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Story-driven missions to secure NPC government agencies.
- [ ] Rewards: High reputation + one-time cash injections.

#### FEATURE 117: Oscillating Security Patch Cycles
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Security patch level slowly decays on online servers (-0.1 per 10 ticks).
- [x] Low patch level (<30%) triggers vulnerability events (exploit scanners).
- [x] Darknet servers decay 2x faster. Hardened OS research cuts decay by 50%.
- [x] Links to F118 (Vulnerability HUD) for visual feedback.

#### FEATURE 118: Vulnerability HUD Alerts
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Dynamic HUD icons for servers with active security vulnerabilities.
- [x] Backend detection of servers with security_patch_level < 50 in GameStateService.
- [x] VulnerabilityHUD.vue floating component showing vulnerable server list.

#### FEATURE 119: Customer Uptime History Sparklines
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] 24-tick rolling uptime history stored in customer preferences.
- [x] recordUptimeTick() called each game loop tick during satisfaction processing.
- [x] uptimeHistory with percentage and history array exposed via toGameState().

#### FEATURE 120: Client Referrals (Organic Growth)
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] High satisfaction (>95%) for 30 days triggers organic new customer leads.
- [x] Reduces CAC (Customer Acquisition Cost) for the player.

#### FEATURE 121: Energy Price Volatility Indicator
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Volatility detection in GameStateService (current price > 120% of 10-tick average).
- [x] Pulsing amber alert icon on BottomHud Energy button when volatile.
- [x] isEnergyVolatile flag propagated through economy store to frontend.

#### FEATURE 122: Infrastructure Snapshot & Recovery
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Save a "Configuration Snapshot" before risky hardware experiments.
- [ ] Allows rolling back configurations (not cash/time) on failure.

### 🏠 ENVIRONMENT, PHYSICS & ADVANCED STRATEGY (123-132)

#### FEATURE 123: Thermal Pollution (Regulatory Penalties)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Pumping too much heat without "Heat Recovery" (F54) attracts fines.
- [ ] Mechanic: Reputation loss and monthly municipal heat tax.

#### FEATURE 124: Dark Fiber Short-Term Leasing
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Rent dormant fiber capacity from competitors or ISPs.
- [ ] Temporary fix for bandwidth saturation (F198/F203).

#### FEATURE 125: Automated Maintenance Routines
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Auto-schedule maintenance for servers below configurable health threshold.
- [x] Configurable via API: enable/disable, health threshold (20-80%), max cost per tick.
- [x] Stored in player_economy.metadata['auto_maintenance'].
- [x] Processes up to 3 servers per 30-tick cycle.

#### FEATURE 126: Battery State of Health (SOH)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Battery modules (F49) lose maximum capacity over charge/discharge cycles.
- [ ] Requires permanent module replacement after ~500 cycles (80% SOH limit).

#### FEATURE 127: Inert Gas Fire Suppression
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Research: "Argonite/FM-200 Systems".
- [ ] Effect: Extinguishes rack fires (F31) instantly without hardware collateral damage.

#### FEATURE 128: Golden Handcuffs (Retention Bonus)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Offer lump-sum bonuses to critical staff (cost: 5x salary per hour of protection).
- [x] Makes them immune to attrition/poaching for X hours (1-72h configurable).
- [x] API endpoint and controller method added.
- [x] Retention bonus stored in employee metadata['retention_until'].

#### FEATURE 129: Physical Data Sovereignty
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] High-security clients forbid Off-Site Backups (F88) outside their region.
- [ ] Enforced via constraint checks in the data residency logic.

#### FEATURE 130: Hardware Liquidations (Auctions)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Random events where player-insolvencies trigger cheap bulk hardware auctions.
- [x] Get used components (high failure risk, F98) for 40% of the price.

#### FEATURE 131: Reputation Cascade (Market Panic)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Global lack of trust if top-tier players experience catastrophic outages.
- [ ] Increases churn probability for every active player for 10-20 minutes.

#### FEATURE 132: Mobile-First HUD / Tablet Optimization
**Phase:** 🟢 MVP | **Status:** 🔴 To Do
- [ ] Minimalist dashboard layouts focused on Energy, Heat, Cash, and Active Tickets.

### 🚀 GRID PARTNERSHIPS & THE SINGULARITY (133-142)

#### FEATURE 133: Grid Stabilization Partner (Demand Response)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Contract: Reduce power draw during grid peaks (throttling CPUs).
- [ ] Reward: Massive credit from the utility provider for stabilization services.

#### FEATURE 134: Anycast Routing Latency Bonus
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Strategic Buff: Reduces global latency (MS) for all regional customers by 15%.
- [ ] Requires Anycast Research & Tier-1 ISP Contracts (F68).

#### FEATURE 135: Whistleblower Crisis Event
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] High-stress, low-loyalty staff (F89) leak data about "Sub-par Maintenance".
- [ ] Result: Drastic Green Reputation drop unless legal countermeasures are active.

#### FEATURE 136: Diversity & Innovation Bonus
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Diverse staff composition accelerates research point generation by 10%.
- [x] Unlocks "Creative Problem Solving" events in the Research Lab.

#### FEATURE 137: ML-Driven Predictive Analytics
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Advanced Dashboard Feature: Predict hardware failures 10 ticks in advance (90% accuracy).
- [ ] Display as "Predicted Probability" in the room/server view.

#### FEATURE 138: Liquid Cooling Server Tech
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Research: "Direct-to-Chip Liquid Cooling".
- [ ] 3x Hardware Cost but 0% Noise and 80% lower ambient heat dissipation.

#### FEATURE 139: Visual Crisis Center Mode
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Special UI state triggered by 3+ active critical incidents.
- [ ] Hides all non-essential data; focuses purely on incident logs and techs.

#### FEATURE 140: B2B Hardware Alliances (Co-Op)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Form alliances with other players to unlock bulk hardware discounts.
- [ ] Shared reputation goals for the alliance.

#### FEATURE 141: Legacy Hardware Secondary Market
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Sell fully depreciated hardware units to new players for quick cash.
- [x] Facilitates progression for starters while cleaning up "Tech Debt" for veterans.

#### FEATURE 142: The Final Frontier: The Singularity
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Ultimate Endgame Research: Fully autonomous AI-managed datacenter management.
- [ ] Zero manual interaction required for monitoring and balancing (The "Perfect State").

### 🌑 BEYOND THE SINGULARITY (143-152)

#### FEATURE 143: Absolute Zero & Quantum Nodes
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Implement Quantum CPU architecture requires 0.1K temperature.
- [ ] Mechanic: Instant server loss on cooling failure.
- [ ] Revenue: 100x multiplier compared to traditional nodes.

#### FEATURE 144: Undersea Data Pods (Isolated Locations)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Unlock oceanic room types (Project Natick style).
- [ ] Passive: 0 KW cooling cost (Ambient cooling).
- [ ] Risk: High-latency maintenance (Diving missions required).

#### FEATURE 145: Strategic Orbital Constellation
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Launch private satellites to become a global Tier-1 ISP.
- [ ] Advantage: 0 regional peering fees, minimal global latency.

#### FEATURE 146: Corporate Lobbying & Politics
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Influence regional energy/data regulations via political sway.
- [ ] Strategic: Lower energy taxes or reduced GDPR penalties.

#### FEATURE 147: Cyber-Warfare (War-Room Mode)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Survival mode event: Massive coordinated cyber-attack on all nodes.
- [ ] Interactive defense: Manage firewall routing and load balancing in real-time.

#### FEATURE 148: Forensic Data Recovery Mini-Game
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Manual sector repair after critical storage failures.
- [ ] Success: Saves high-value contracts and grants "The Savior" reputation.

#### FEATURE 149: White-Label Reseller Program
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] New 'reseller' product type in CustomerOrderService with specific requirements.
- [x] Benefit: 0 support tickets, Lower revenue but high stability.

#### FEATURE 150: Thermoelectric Energy Recovery
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Convert server heat directly back into electricity (Seebeck effect).
- [ ] Passive: Reduces grid power draw by 5-10% in high-heat scenarios.

#### FEATURE 151: Corporate Espionage (Active Operations)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Saboteur missions to undercut or cripple NPC competitors.
- [ ] Requires High Security/Stealth level research.

#### FEATURE 152: The Heritage Museum (Prestige Reset)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Decommission legacy DC to open a public "Museum of Computing".
- [ ] Result: New Game+ Multiplier on all global XP & Reputation.

### ⚙️ DEEP HARDWARE & GLOBAL MACRO (153-162)

#### FEATURE 153: CPU Instruction Gaps (Software Obsolescence)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Modern contracts (AI/ML) require AVX-512 or higher instruction sets.
- [ ] Legacy nodes become technically incompatible with certain high-revenue orders.

#### FEATURE 154: Hardware Lifecycle Management View
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Financial Forecast showing which racks will reach 0% value/efficiency next.
- [ ] Helps planning the "Modernization Cycle" (F62).

#### FEATURE 155: Trade Wars & Global Tariffs
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] World Events causing 40% price spikes for specific regional hardware.
- [ ] Strategic supply chain management required (stockpiling components).

#### FEATURE 279: Dark Fiber Lease Investments
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Invest in trans-atlantic/pacific undersea cable leases.
- [x] Drastically reduce regional latencies for multi-region customer clusters.

#### FEATURE 280: Automated Network Peering (BGP v2)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Automated BGP convergence simulation with path discovery visuals.
- [x] Real-time hop-optimization bonuses based on partner reliability.

#### FEATURE 267: Hardware Brand Exclusivity Deals
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] 30-day contracts with specific vendors (Gigaparts, Datavault, ServerPro).
- [x] Grants 15-25% discount on all equipment from the selected brand.
- [x] Strictly enforced hardware exclusivity prevents cross-brand sourcing.
- [x] Integrated into Market Overlay (Exclusivity Tab) and Assembly vendor tags.

#### FEATURE 265: Physical Data Destruction Compliance
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Secure shredding process for high-security storage components.
- [x] Added `needs_shredding` flag to sensitive hardware (Diamond/Gov customers).
- [x] Requires physical destruction ($50) before liquidating sensitive system nodes.
- [x] Integrated with Gov-Grade Compliance certificate (Requires 50 lifetime shreds).

#### FEATURE 156: Eco-Audits as Strategic Gate
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Mandatory A-Rating in "Green Reputation" (F123) for Fortune 500 contracts.
- [ ] Revenue multiplier (3x) for sustainable datacenter operations.

#### FEATURE 157: Black Market Gear (The Night Shop)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Hidden shop accessible only during night ticks (00:00 - 04:00).
- [x] Half-price hardware with a 15% chance of pre-installed "Backdoors".
- [x] Tainted hardware reduces security score and triggers unique breach events.

#### FEATURE 168: Mercenary Technicians (Instant Fix)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Complete
- [x] Recruitment option: "Mercenary Recovery Team".
- [x] Instantly repairs all servers globally to 100% health for a high flat fee ($12,500).
- [x] Does not count towards headcount (immediate disappearance after job).

#### FEATURE 159: Quantum Key Distribution (QKD)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Research: "Unbreakable Quantum Links".
- [ ] Immune to Corporate Espionage (F151) but doubles NIC costs.

#### FEATURE 160: ISP Prioritization (Tiered Traffic)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Negotiated QoS with Tier-1 ISPs (F68/F92).
- [ ] Prevents revenue loss during global network instability events (F85).

#### FEATURE 161: Employee Seminars (Efficiency Buffs)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Complete
- [x] Send critical staff to off-site training for 12h.
- [x] Permanent +10% Efficiency multiplier upon return.

#### FEATURE 162: Safety Regulations & DC Audits
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Frequent workplace safety inspections.
- [ ] Failure to comply (after fires/accidents) leads to a total stop of new installations.

### 🎨 UX, IMMERSION & COMMUNITY (163-172)

#### FEATURE 163: Universal Tooltip Coverage (UX Polish)
**Phase:** 🟡 Phase 1 | **Status:** 🟡 In Progress
- [x] All complex stats (SLA, TCO, efficiency) have explanatory tooltips on hover.
- [x] Add subtle "info" icons to section headers that explain the mechanic.
- [ ] Interactive tutorial hints: Highlight a UI element if a player has been idle too long. [PHASE 4]

#### FEATURE 164: "High-Contrast" Dark Theme Variation
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Specific accessibility theme for improved readability in low-light environments.
- [ ] Pure black backgrounds with neon-green/blue accents (Classic Terminal vibe).

#### FEATURE 165: Seasonal World Events (Live-Ops)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Timed visual and economic shifts: 
    - **Christmas:** Snow on DC roof (Architect), Higher traffic for Shop-customers.
    - **Halloween:** Spooky ambient sounds, "Ghost in the Machine" bug event.

#### FEATURE 166: Developer API Hooks (Automation 2.0)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Allow players to write actual JS-Scripts to react to game events.
- [ ] "Webhooks" to external services (Discord/Slack) for outage notifications.

#### FEATURE 167: Hardware Overclocking Stability Curve
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Overclocking (F97) isn't just binary; it's a stability probability.
- [ ] Higher clock = Higher chance of "Fatal OS Crash" requiring manual reboot.

#### FEATURE 168: Liquid Cooling Visuals (Architect)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Visual pipes and blue-lit server units when Liquid Cooling (F138) is installed.
- [ ] Animated flow indicators in the Heat Map view.

#### FEATURE 169: Corporate Lounge & Breakroom (Morale Lab)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Placeable breakroom assets in the Architect.
- [ ] Passively reduces "Staff Burnout" (F100) and increases retention (F89).

#### FEATURE 170: Global Marketplace (Component Exchange)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Trade used components with other real players.
- [ ] Dynamic pricing based on global supply/demand of specific parts.

#### FEATURE 171: Incident Post-Mortem Sharing
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Share your "Crisis Reports" (F60) with the community for peer-review.
- [ ] Bonus: High-rated reports grant extra Reputation to the author.

#### FEATURE 172: The "Hacker-Space" Origins Story
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] A dedicated narrative arc for the start in the "Garage/Basement".
- [ ] Interaction with NPC "Mentor" characters via the News Ticker.

### 🛡️ REDUNDANCY, ETHICS & ADVANCED OPS (173-182)

#### FEATURE 173: RAID Tiering (Storage Redundancy)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Select RAID levels (0, 1, 5, 6, 10) for storage servers.
- [ ] Balance "Capacity" vs. "Fault Tolerance" (Probability of data loss).

#### FEATURE 174: UPS Maintenance & Battery Testing
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Manual/Auto UPS self-tests required every X game months.
- [ ] Failure to test results in unpredictable power loss during grid outages.

#### FEATURE 175: Hot-Swapping Component Tech
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] High-end server models allow component replacement without power-off.
- [ ] Prevents SLA-Breach/Tickets during routine maintenance.

#### FEATURE 176: Advanced Employee Burnout Logic
**Phase:** 🟡 Phase 2 | **Status:** ✅ Complete
- [x] Employees have "Stress Resistance" stats. (Linked to Level/Training)
- [x] Night-calls and heavy incident loads lead to mid-term burnout (Medical Leave).

#### FEATURE 177: Social Engineering Threat Event
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Narrative event: Fraudsters attempt to phish credentials from Support Agents.
- [ ] Outcome based on Staff Level and Social Engineering Defense Research.

#### FEATURE 178: Cloud-Bursting (External Failover)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Bridge local capacity gaps by renting external cloud nodes at 5x cost.
- [ ] Temporary fix to prevent churn during massive demand spikes.

#### FEATURE 179: Negotiable & Variable SLAs
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Offer discounts to customers in exchange for lower Uptime guarantees.
- [ ] Strategic: Focus your limited maintenance resources on VIP-only SLAs.

#### FEATURE 180: Local Market Manipulation
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Use market share dominance to trigger "Price Wars" in specific regions.
- [ ] Drive small NPC competitors into bankruptcy to buy their liquidated assets.

#### FEATURE 181: The NOC-Wall Mode (Full-Screen Ops)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Dedicated full-screen visualization of global network health and incidents.
- [ ] Immersive view for "Headquarters" management style.

#### FEATURE 182: Real-Time Log Pipeline HUD
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Small, animated side-ticker showing granular system logs (Matrix-style).
- [x] Purpose: Immediate visual feedback on every "Tick" action.

### 💰 FISCAL OVERSIGHT, PUE & MARKET RISKS (183-192)

#### FEATURE 183: Corporate Tax Havens (Region Strategy)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Locate certain rooms in regions with 0% corporate tax.
- [ ] Risk: Possible "Trade Embargo" events reducing global bandwidth by 30%.

#### FEATURE 184: Labor Union Renegotiations
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Narrative Event: Negotiate with unions when staff morale (F102) is low.
- [ ] Failure results in prolonged maintenance strikes (F102).

#### FEATURE 185: PUE-Score Optimization (Cooling 3.0)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Power Usage Effectiveness (PUE) as a core room metric.
- [x] Improving PUE reduces overhead energy costs significantly.

#### FEATURE 186: Diesel Generator Backup (Grid Failure)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Install fuel-burning generators for grid outages (F133).
- [x] Mechanic: High fuel cost and negative Reputation impact (pollution).

#### FEATURE 187: Black Market IP Ranges
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Purchase cheap IPv4 blocks on the underground market.
- [x] Risk: Higher chance of "ISP Banning" events or "Blacklisting".

#### FEATURE 188: Dubious Data-Mining Revenue
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Monetize non-PII customer data for quick cash injections.
- [x] Severe Reputation penalty (50%) if the "Privacy Leak" event occurs.

#### FEATURE 189: Headquarters Visual Expansion (Prestige)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Build and upgrade a "Corporate HQ" building.
- [x] High-prestige buildings attract the "Whale-class" customers (F142).

#### FEATURE 190: Market Volatility Predictions
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Use "Predictive Analytics" (F137) to foresee regional demand/market shifts.
- [x] Helps in planning marketing spend and regional expansions.

#### FEATURE 191: Hardware Lifecycle Leasing (OPEX)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Rent server hardware instead of buying (Low CAPEX, High OPEX).
- [x] Ideal for rapid early-game expansion with tight margins.

#### FEATURE 192: Hazardous Waste Disposal Fees
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Broken/Old hardware requires expensive compliant disposal ($75 per unit).
- [x] Offset by researching "E-Waste Recycling Protocols" (F192).

### 🪐 ASTRO-DYNAMICS & THE ULTIMATE SINGULARITY (193-200)

#### FEATURE 193: Planetary Rotation (Solar Sync)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Energy production tied to regional day/night cycles (Offsets per UTC).
- [x] Solar capacity tied to room-level upgrades (15kW per level).
- [x] Strategic need for battery buffering to avoid nighttime price spikes.

#### FEATURE 194: Liquid Nitrogen Overclocking
**Phase:** 🌑 Phase 5 | **Status:** ✅ Done
- [x] Research: "Cryogenic Cooling Solutions" (prerequisite: cooling_v2).
- [x] Effect: +300% CPU performance for 120s, 5% chance of permanent hardware shattering.
- [x] API endpoint: POST /server/{id}/overclock.

#### FEATURE 195: The Board of Directors (AI Oversight)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] NPC Board members issue KPI Missions (4 members: CFO, CTO, COO, CMO).
- [x] 12 distinct KPI types scaled by player level.
- [x] Failure results in "Hostile Takeover" threats (Rep loss, Skill Point loss).
- [x] Success rewards XP + Reputation gains.
- [x] API: GET /management/board.

#### FEATURE 196: Global Peering Wars (Node Control)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] PvP/PvE conflict for control over major internet exchange points (e.g., DE-CIX).
- [ ] Controller sets regional peering fees for all other players.

#### FEATURE 197: "The Silent Outage" (Invisible Regression)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Rare technical bug (0.1% chance/tick/server) that doesn't trigger monitoring.
- [x] Slow revenue leakage (~$2/order/tick) until a manual deep-scan is performed.
- [x] Deep-scan (diagnose endpoint) now detects and fixes silent outages.

#### FEATURE 198: Geopolitical Border Shifting
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] High-impact event (0.05% chance/tick) in unstable regions (APAC, SA, EU).
- [x] 30-minute countdown to evacuate hardware before regional DC seizure.
- [x] Forced server shutdown + reputation penalty on failure.

#### FEATURE 199: Employee Synergy Bonuses
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Pairing specific employee types (Mentor/Apprentice) grants +20% efficiency.
- [ ] Hidden synergy combos discovered through experimentation.

#### FEATURE 200: ASCENSION: The God-Level Singularity
**Phase:** 🌑 Phase 5 | **Status:** 🟡 Concept
- [ ] Transcend typical management: Bend physics of data flow.
- [ ] Result: All nodes are 100% self-healing and 0% latency (The Final Meta-State).
- [ ] *Note: Requires full endgame loop (F196, F199) to be meaningful.*

### 🧬 VIRTUALIZATION, TALENT & EXOTIC OPS (201-210)

#### FEATURE 201: Rack-Level Virtualization (Hypervisors)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Install Hypervisors to run multiple Virtual Machines (VMs) per physical node.
- [ ] Benefit: Major revenue density; Risk: Shared resource failure crashes multiple clients.

#### FEATURE 202: Darknet Operations Marketplace
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Host untraceable/illegal traffic for extreme profit multipliers (1.5x to 5.0x).
- [x] "Federal Heat" mechanic: Risk increases based on hosting intensity.
- [x] Federal Raids: Seizure of hardware, balance fines, and reputation loss.
- [x] API: GET /darknet, POST /server/{id}/darknet/enable.

#### FEATURE 203: Dynamic Weather-Logic Engine
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Regional weather (Clear, Heatwaves, Storms) affects PUE and grid stability.
- [x] Weather-based solar production modifiers (Cloud cover reduces output).
- [x] API: Energy endpoint includes 'regional_weather' and 'solar_factors'.

#### FEATURE 204: Employee Talent Trees (RPG Progress)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Employees gain XP to unlock specializations (Cooling Expert, Security Ninja, etc.).
- [x] Talent prerequisites: Higher tier perks require base skills.
- [x] "Respec" function: Reset talent points for a fee (scales with level).
- [x] Permanent buffs to repair speed or incident prevention via tree nodes.

#### FEATURE 65: Crypto Mining Idle Logic
**Status**: ✅ Done
**Impact**: Medium
**Risk**: Low
**Description**: Users can set unused servers to "mine crypto", generating a small trickle income but maximizing power draw and heat, accelerating wear and tear.

**Implementation**:
1. Add `is_mining` (boolean), `total_mined_crypto` to `Server` model ✅
2. Added `MiningTab.vue` to ServerDetailOverlay to toggle and view status ✅
3. Created `toggleMining` API endpoint in `ServerController` ✅
4. Update GameLoopService to generate passive income (-$0.10/core) for mining servers ✅
5. If `is_mining`, `power_draw` gets +50%, `heat_output` +80% (implemented in Server model accessors) ✅

#### FEATURE 205: Proprietary OS & Kernel Optimization
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Develop in-house Operating Systems (PonyOS) to optimize specific traffic types.
- [x] Extreme performance (1.25x mod) and zero licensing costs.
- [x] Customer Retention bonus: -15% churn risk for clients on PonyOS.
- [x] Catalog integration: Filtered by research status.

#### FEATURE 206: Bribery & Moral Decisions (Client Graft)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] VIP/Dubious clients offer bribes to bypass queues or ignore incidents.
- [x] Impact: Instant cash vs. Moderate long-term reputation & moral damage.
- [x] API: GET /bribery, POST /bribery/accept|decline.

#### FEATURE 207: Project Icarus: Moon-Side Data Nodes
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Construct ultra-low latency nodes for space-based orbital clients (F58 expansion).
- [ ] The absolute pinnacle of prestige and infrastructure costs ($1M+ per rack).

#### FEATURE 208: Comprehensive Hardware Insurance
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Pay premiums to insure expensive liquid-cooled clusters.
- [x] Coverage for failure events or accidental destruction.
- [x] Multiple plans (Basic, Premium, Enterprise) with varying deductibles.

#### FEATURE 209: Direct Carrier Peering Agreements
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Negotiate dedicated "pipes" with Tier-1 carriers (AT&T, Deutsche Telekom).
- [ ] Massively attracts Enterprise-Whale customers.

#### FEATURE 210: Black Market IP Acquisition
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Acquire cheap, "dirty" IPv4 addresses from unlisted providers.
- [x] High financial efficiency but extreme risk of IP Blacklisting.
- [x] Integrated into NetworkService and evaluateComplianceRisk loop.

### 🏛️ MACRO-GRID, EROSION & BIO-COMPUTING (211-220)

#### FEATURE 211: Energy Arbitrage Prediction Engine
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Unlock a 24h (game-time) market forecasting tool for energy prices.
- [ ] Strategic battery charging (F50) during predicted low-price phases.

#### FEATURE 212: BGP Path Optimization (L3 Management)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Manually configure routing paths to prioritize specific client traffic.
- [ ] Drastically reduces latency for VIP/Enterprise customers (F142).

#### FEATURE 213: ISP Banning Event (Network Death)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Transit providers sever peering due to black-market activity.
- [x] Interactive actions: Legal Appeal, Bribing Admins, or Prefix Rotation.
- [x] Severe consequences if not resolved (Dead ASN fine & global blackout).

#### FEATURE 214: Regional Environmental Erosion
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Geography-specific hardware impacts: Dusty regions (desert) or high humidity (coastal).
- [ ] Requires "IP65-Enclosures" research to stop accelerated MTBF degradation (F98).

#### FEATURE 215: Whistleblower-Management (Risk Counter)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Keep employees silent via bribes or NDA-contracts when Darknet hosting (F202) is active.
- [ ] Narrative choices with long-term reputation consequences.

#### FEATURE 216: Community Hacker-Bounties (Sec-Ops)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Fund bug-bounty programs for your "Proprietary OS" (F205).
- [ ] Prevents zero-day exploits during Cyber-Warfare events (F147).

#### FEATURE 217: Corporate Campus & Morale Hub
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Build physical employee amenities (Kicker tables, gyms, high-end coffee).
- [ ] Passively increases Burnout-Resistance (F176) and Loyality (F89).

#### FEATURE 218: Recruitment Expert-Interviews (Minigame)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Interactive dialog session for hiring Tier-3 staff to verify their "Hidden Talent".
- [ ] Higher success chance of hiring "Superstars".

#### FEATURE 219: Historical Artifact Acquisition (Museum Quests)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Random "Barn-Find" or "Auction" events to collect legendary hardware (ENIAC, C64).
- [ ] Populate the Legacy Vault (F210) for maximum Prestige bonuses.

#### FEATURE 220: The Bio-Computing Neural Network
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Final-tier endgame: Directly interface employee brains with the datacenter.
- [ ] Infinite processing speed; Ethically "Gray" reputation penalty; Zero latency.

### 💰 ADVANCED ARBITRAGE, DUST & GLOBAL CRISES (221-230)

#### FEATURE 221: Spot-Market Arbitrage (Reverse Grid)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Sell stored energy from Battery Modules (F50) back to the grid during price peaks.
- [ ] New active revenue stream for energy-focused players.

#### FEATURE 222: Green-Certificate Trading
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Generate "CO2 Credits" when operating on 100% renewable energy.
- [ ] Sell credits to NPC/Players with high carbon footprints for passive income.

#### FEATURE 223: Particle & Dust Buildup Physics
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Slow accumulation of dust in dry regions (F214) reducing cooling efficiency.
- [ ] Requires "Air-Cleaning" maintenance tasks or high-end filtration research.

#### FEATURE 224: Mechanical Vibration Logic (HDD Wear)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] High rack density in unstable buildings (Garage/Basement) causes vibrations.
- [ ] Accelerates failure rates of HDD storage nodes; SSD nodes remain immune.

#### FEATURE 225: Crypto-Laundry Mode (Black Market 2.0)
**Phase:** 🔴 Phase 5 | **Status:** 🔴 To Do
- [ ] Use Crypto-Mining farms (F65) for laundering illicit funds.
- [ ] Extreme daily profit; 2% daily chance of "Total Asset Seizure" (Game Over).

#### FEATURE 226: Client Infrastructure Backdoors
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Secretly use customer idle capacity for internal calculations/mining.
- [ ] Benefit: Free compute; Risk: Massive churn and lawsuits if detected.

#### FEATURE 227: Dynamic Employee Scandal Events
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Administrative choices regarding staff misconduct (e.g., Gaming during shifts).
- [ ] Impact: Team Morale vs. Raw Productivity.

#### FEATURE 228: Team-Building Retreats (Burnout Reset)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Expensive events to completely reset all staff stress/burnout counters (F176).
- [ ] Temporarily removes staff from the roster for 24h.

#### FEATURE 229: Data Sovereignty Crisis Events
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Sudden regulatory shocks requiring all regional citizenship data to stay within borders.
- [ ] Triggers urgent physical expansion missions.

#### FEATURE 230: Regional Internet Kill-Switch (Macro Event)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Total internet blackout in specific countries due to political events.
- [ ] Zero revenue from affected region for the duration of the event.

### 🧊 COOLING, BACKBONES & GALACTIC RESET (231-240)

#### FEATURE 231: Immersion-Cooling (Liquid Submersion)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Submerge racks in non-conductive dielectric fluid.
- [ ] Effect: +500% cooling efficiency (PUE 1.05); Penalty: 4x repair time for "drying" components.

#### FEATURE 232: Hardware-as-a-Service (HaaS)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Subscribe to hardware instead of buying.
- [ ] Fixed monthly fee; Vendor provides instant free replacements for broken parts.

#### FEATURE 233: Quantum-Key Generation (Digital Commodity)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Use Quantum Nodes (F143) to produce tamper-proof encryption keys.
- [ ] Sell keys as a high-value commodity to VIP Financial clients.

#### FEATURE 234: Active Headhunting Protocols
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Hire agencies to poach Level 10+ staff from NPC competitors.
- [ ] High cost and permanent Rivalry penalty with targeted corporations.

#### FEATURE 235: PR-Crisis Management (Spin Agency)
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Pay PR agencies after scandals (F188) to mitigate reputation loss.
- [ ] Choices during narrative "press conferences" affect outcome success.

#### FEATURE 236: Airflow & Heat-Map Visualization
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Living heatmap in FacilityArchitect showing cold/hot aisles and stagnation zones.
- [ ] Prevents hardware failure by visually showing "Heat Pockets".

#### FEATURE 237: Inter-DC Backbone Fiber Rings
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Construct dedicated fiber rings between your global Data Centers.
- [ ] Feature: "Global Load Balancing" allows instant client migration during disasters.

#### FEATURE 238: Disaster-Recovery-as-a-Service (DRaaS)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] High-premium product offering 100% data recovery guarantee.
- [ ] Requires mandatory F88 (Off-Site Backup) and F121 (Snapshots).

#### FEATURE 239: Physical Security & Patrols
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Install biometric gates, cameras, and hire security guards.
- [ ] Prevents active "Industrial Espionage" (F161) mission success from NPCs.

#### FEATURE 240: THE GALACTIC BACKBONE (Prestige Reset 2.0)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Sacrifice your Earth-bound empire to build a "Station-Prime" node.
- [ ] Result: New UI theme, 1000% multiplier, and Sci-Fi era gameplay unlock.

### ⚖️ ETHICS, PHYSICS & THE ARCHITECT'S LEGACY (241-250)

#### FEATURE 241: Deep Packet Inspection (DPI) Surveillance
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Sell data-analysis services to regional government NPCs.
- [ ] Benefit: Extreme daily revenue; Risk: Massive "Ethics-Score" penalty and potential community protests.

#### FEATURE 242: Carbon-Capture Air Filtration
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Massive external units that scrub CO2 from the power grid usage.
- [ ] Necessary to reach "Carbon-Negative" status for the highest eco-awards.

#### FEATURE 243: Employee Wellness Facilities (Physical)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Add nap pods, saunas, and gyms to the FacilityArchitect map.
- [x] Passively resets burnout counters (F176) while employees are on-site.
- [x] Room assignment logic for staff placement bonuses.

#### FEATURE 244: Hardware Benchmarking Labs
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Mini-game: Test new CPU/GPU combos to find optimal Voltage/Clock curves.
- [x] Finding "Secret Settings" grants +10% efficiency to all servers of that model.

#### FEATURE 245: BGP-Hijacking (Aggressive Peering)
**Phase:** 🔴 Phase 5 | **Status:** 🔴 To Do
- [ ] Temporarily steal traffic routes from NPC competitors.
- [ ] Extreme growth spike; 10% chance of "Internet Court" lawsuit and global ban.

#### FEATURE 246: Disaster Drill Simulations
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Run fake outages to train staff. Increases Level/XP (F204) faster.
- [ ] Risk: 5% chance a "Drill" accidentally triggers a real system crash.

#### FEATURE 247: White-Label Datacenter Franchising
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Let NPC startups use your infrastructure under their brand.
- [ ] Passive income stream; Their SLA-Breaches also lower your reputation.

#### FEATURE 248: Structural Load Physics (Floor Weight)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Racks and UPS units have weight stats.
- [ ] Old buildings (Garage/Basement) have lower weight limits; Overloading causes structural catastrophic failure.

#### FEATURE 249: Inter-Planetary Latency Protocol (DTN)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Light-speed delay is a real mechanic for Space-Nodes (F207).
- [ ] Requires "Delay-Tolerant Networking" research to keep clients connected.

#### FEATURE 250: THE ARCHITECT'S LEGACY (The Infinite End)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Create your own "Sub-Simulation" within Rackora.
- [ ] You become the "Provider" for new NPC empires, setting global taxes and energy prices.

### 📊 ADVANCED VISUALIZATION & STRATEGY (251-253)

#### FEATURE 251: Dynamic Floor Stress Heatmap
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Integration into FacilityArchitect: Real-time overlay of structural load.
- [ ] Visual warning system (Green to Red) for safe rack placement (F248).

#### FEATURE 252: Global Employee Wellness Score
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Accumulative metric based on facility quality (F243) and work-life balance.
- [ ] High score attracts "Superstar" talent (F218) and reduces poaching risk.

#### FEATURE 253: BGP-War Tactic Mini-Game
**Phase:** 🔴 Phase 5 | **Status:** 🔴 To Do
- [ ] Interactive map-based strategic layer for BGP-Hijacking (F245).
- [ ] Choose attack/defense patterns to control regional data routes.

### 🏁 THE ROAD TO 300: ULTIMATE SIMULATION DEPTH (254-300)

#### FEATURE 254: Manual Hardware Tuning (Overvolting)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Adjust CPU voltage/clocks manually.
- [x] Risk: Higher performance but exponential heat increase and fire risk (F31).

#### FEATURE 255: The Great Backup Crisis (Critical Event)
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Global corruption of digital snapshots.
- [x] Only players with physical "Tape Libraries" (F88) can recover data without massive client churn.

#### FEATURE 256: Stock Market Short-Selling (Insider Trading)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Short-sell your own company's stock before a "controlled" outage or "bad news" event.
- [x] High profit, but 30% chance of "SEC Audit" and total asset freeze.

#### FEATURE 257: Regional Power Rationing Quotas
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Seasonal events where city grids limit your DC to 50% power.
- [x] Requires strategic shutdown of non-SLA services to keep the grid stable.

#### FEATURE 258: Fiber Cut Redirection Mini-Game
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Mini-game where players must manually reroute traffic during a fiber cut event.
- [x] Failure results in prolonged downtime and massive reputation loss.

#### FEATURE 259: Employee Strike Negotiations (Dialogue Tree)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Interactive negotiation with union leaders (F184).
- [x] Outcome determines new salary levels and work-life balance buffs.

#### FEATURE 260: Secondary Hardware Resale Market
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Sell used/old hardware to NPC "Budget Hosters" instead of paying disposal fees.
- [x] Price depends on the component's remaining "Health/MTBF" and regional demand trends.

#### FEATURE 261: Aggressive Corporate Sabotage (Moles)
**Phase:** 🔴 Phase 5 | **Status:** ✅ Done
- [x] Infiltrate an NPC competitor's facility.
- [x] Plant an operative to subtly misconfigure cooling or delete backups over time.

#### FEATURE 262: AI-Driven Customer Churn Prediction
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Multi-factor churn risk scoring (0-100) based on satisfaction, incidents, uptime, loyalty, and status.
- [x] Risk levels: low/moderate/high/critical with human-readable factor explanations.
- [x] Exposed via customer.churnRisk in toGameState() for frontend HUD display.

#### FEATURE 263: Multi-Tenant Rack Colocation (Colo 1.0)
**Phase:** 🟢 MVP | **Status:** ✅ Done
- [x] Lease single rack units (1U) to many small customers in one rack.
- [x] High administrative overhead (more tickets) but higher profit per U.

#### FEATURE 264: Grid Energy Arbitrage ( neighborhood Feed)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Sell excess energy from your dedicated power plants (F56) back to the local residential grid.

#### FEATURE 265: Physical Data Destruction (Compliance)
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Shredding hard drives on-site to earn "Highest Security" certification (Gov-Grade Secure Destruction).
- [x] Attracts Intelligence Agency and Government clients via the certification system.

#### FEATURE 266: Liquid Immersion Specialists (Staff Type)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Technicians specifically trained for submerged rack maintenance (F231).

#### FEATURE 267: Hardware Brand Exclusivity Deals
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] 30-day contracts with specific vendors, pricing discounts, and competing brand blocks.

#### FEATURE 163: Modular Server Component Leasing
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Individual component-level leasing and buyout options.
- [x] Rent specific high-end parts (CPUs, Motherboards, RAM) with hourly OPEX.
- [x] Return leased components at any time (requires offline server).
- [x] Buyout leased hardware for 75% of original price to convert to CAPEX.

#### FEATURE 268: Thermal Pollution Carbon Taxes
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Regions charge a "Heat Tax" for dumping waste heat into the atmosphere.
- [x] Mitigated by installing "Heat Recovery Systems" ($20,000 per room upgrade, -40% tax).
- [x] Green Reputation reduces carbon tax (up to 50% discount at 100 green rep).
- [x] eco_mode policy grants additional 15% carbon tax discount.
- [x] Carbon tax info visible in Room Upgrades overlay with real-time waste heat data.

#### FEATURE 269: Dynamic IP Address Leasings
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Buy large IPv4 blocks and lease them to NPC/Player startups for passive income.

#### FEATURE 270: Edge-CDN "Pop" Room Micro-Leasing
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Build tiny, 1-rack server closets in 100+ global cities.
- [ ] Extremely high prestige and revenue from "Content Delivery" clients.

#### FEATURE 271: Anti-DDoS Traffic Scrubbing Centers
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Specialized rooms that filter malicious traffic for the whole region.
- [ ] Generate massive income by "Cleaning" the internet during botnet attacks.

#### FEATURE 272: Hardware Forgery & "Grey Market" Parts
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Buy cheap "No-Name" components. 20% chance they are fake and fail in 2 days.

#### FEATURE 273: Employee Legacy Management (VPs)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Promote long-term Level 10 staff to "Region VPs".
- [ ] Grants passive +10% efficiency to all rooms in that region.

#### FEATURE 274: Corporate Mergers & Acquisitions (M&A)
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Buy out a small NPC competitor and immediately own their rooms/customers.

#### FEATURE 275: Disaster Snapshot Rollbacks (Temporal Ops)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Roll back a room's state to a 24h old backup after a total fire (F31).
- [x] Costs XP/Reputation but saves hardware costs.

#### FEATURE 276: Labor Efficiency Cost-Audit Missions
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Hire consultants to cut salaries by 15%.
- [ ] Result: Massive immediate profit; -30% morale and higher strike risk.

#### FEATURE 277: Technician Commute & Traffic Logic
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Response time for alarms depends on office location and city traffic.
- [ ] "On-Site" staff (F243) ignore this delay.

#### FEATURE 278: Ethics Council Philosophical Quests
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] "Good" path: Host free speech sites; "Evil" path: Host state-sponsored surveillance (F241).

#### FEATURE 279: The "Global Downturn" Hardware Liquidators
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Rare world event "Global Market Downturn" with 80% off all hardware (20min window).
- [x] Inverse event "Supply Chain Disruption" with +35% hardware costs (crisis type).
- [x] HUD MarketAlertTicker shows green SALE banner during downturn and orange warning during supply crisis.
- [x] Automatic integration with existing hardware_cost modifier system.

#### FEATURE 280: Quad-Redundant Grid Feeds (Tier 4)
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] Pay double electricity fees for 100% grid-uptime guarantee (Zero outages).

#### FEATURE 281: Biometric Security Failures (ID Theft)
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] NPC Thieves can steal an employee's keycard.
- [ ] Requires "Security Override" to lock down the facility.

#### FEATURE 282: Information-Hardware Exchange Market
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Swap old hardware with "Hackers" in exchange for intel on competitor's PUE or Revenue.

#### FEATURE 283: Social Media Influencer Tech-Boost
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Hire tech-influencers to hype your network.
- [ ] Temporary +200% traffic from "Gen-Z" gaming clients.

#### FEATURE 284: Employee Burnout Sabbaticals
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Send high-level staff (Lvl 5+) with stress >60% on a 2-hour paid sabbatical.
- [x] Cost: 30× hourly salary as a one-time bonus payment.
- [x] Employee stress resets to 0%, energy to 100% upon return.
- [x] Sabbatical prevents permanent burnout (stress >98 → medical leave).
- [x] UI: Sabbatical button appears conditionally, green badge during leave, staff card highlighted.
- [x] Backend: Migration, Model, Service, Controller, Route all implemented.

#### FEATURE 285: Facility Theme & Skin System
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Apply "Industrial", "Minimalist", or "Retro-Vapor" styles to your DC views.

#### FEATURE 286: Regional Data Sovereignty Lawsuits
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Legal battles if customer data from Region A flows through a non-treaty Region B.

#### FEATURE 287: Real-Time Botnet Mitigation Tasks
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Active task: Block IP ranges during a live DDoS attack to save customer SLAs.

#### FEATURE 288: External Undersea Cable Funding
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Invest $50M to build a new undersea fiber link for -80% global latency.

#### FEATURE 289: Political Lobbying & Tax Breaks
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] Pay "Lobbyists" to reduce energy taxes in a specific region for 6 months.

#### FEATURE 290: The "Solar Flare" Global Degradation
**Phase:** 🔴 Phase 3 | **Status:** 🔴 To Do
- [ ] 0.1% chance random event causing -10% health to all non-shielded hardware.

#### FEATURE 291: AI Operator Virtual Assistants
**Phase:** 🔴 Phase 4 | **Status:** 🔴 To Do
- [ ] "Bot Agents" that resolve T1 tickets (F36) instantly but consume CPU heat.

#### FEATURE 292: Global Real-Time Traffic Heatmap
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Visual HUD view showing packet flows between your world nodes (The Matrix View).

#### FEATURE 293: Hardware Aging Stability Curves
**Phase:** 🔴 Phase 3 | **Status:** ✅ Done
- [x] Servers past 80% lifespan: 0.2% chance per 20 ticks of 1-3 health loss.
- [x] Servers past 100% lifespan: 1% chance of 2-5 health loss + 10% chance of named faults.
- [x] Named faults: Capacitor Leak, Solder Joint Fatigue, Memory Bit Rot, PSU Degradation, Thermal Paste Dry-Out.
- [x] Overclocked servers treated as 1.5x lifespan consumed (accelerated aging).

#### FEATURE 294: The Basement Heritage Museum Site
**Phase:** 🟡 Phase 2 | **Status:** 🔴 To Do
- [ ] Convert your starting room into a training facility for +15% XP gain for all staff.

#### FEATURE 295: Inter-Planetary Entanglement Link
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Ultimate Tech: 0ms latency for Martian/Lunar nodes (F249).

#### FEATURE 296: The Board of Directors Coup Attempt
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Boss Fight: Board tries to fire you. Win by "Hostile Stock Buyback" or Reputation.

#### FEATURE 297: Data Center Tourism & PR Tours
**Phase:** 🟡 Phase 2 | **Status:** ✅ Done
- [x] Open facilities for "Investor Tours" to gain massive Reputation.

#### FEATURE 298: The "Omega" Hardware Artifact
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] 0.001% drop rate component that makes a single rack 1000% more efficient.

#### FEATURE 299: Cyber-Insurance Fraud (Evil Path)
**Phase:** 🔴 Phase 4 | **Status:** ✅ Done
- [x] Intentionally cause a hardware fire to claim insurance money.
- [x] Risk: 50% chance of "Fraud Investigation" and massive penalty/loss.

#### FEATURE 300: UNIVERSAL CONSCIOUSNESS: THE LIVING GRID
**Phase:** 🌑 Phase 5 | **Status:** 🔴 To Do
- [ ] Final Milestone: The internet becomes sentient under your command.
- [ ] Outcome: You control all world finance and energy. You are the Architect.

---

### 🌐 FEATURES 66–100: EXPERT & MASTER SYSTEMS

#### 🏢 CORPORATE & HUMAN FACTORS (66-75)
- [66] **Cascade Failures:** Chain reactions (e.g., Power → Heat → HW Fail → SLA Breach).
- [67] **Risk Profile System:** Insurance premiums based on your failure history.
- [69] **Corporate Culture:** Employee burnout rates affecting auto-repair speed.
- [72] **Shadow IT:** Unapproved client workloads increasing risk but also revenue.

#### 🌍 GLOBAL & MACRO-ECONOMY (76-85)
- [76] **Global Energy Rationing:** Mandatory shutdowns during world crises.
- [78] **AI Demand Spikes:** Sudden, massive traffic from procedural "AI Viral" events.
- [82] **Market Saturation:** Revenue drops as too many players compete in one region.
- [85] **Internet Instability:** Global packet loss events affecting all online orders.

#### 🌑 THE UNDERGROUND & CHAOS (86-95)
- [86] **Black Swan Events:** Unpredictable, high-impact disasters (e.g., Solar Storms).
- [88] **Underground Market:** Buying "stolen" hardware for 50% less (High Sabotage risk).
- [90] **Data Trading:** Risky monetization of non-PII data (Reputation risk).
- [92] **Chaos Mode:** A high-volatility seasonal leaderboard with permanent disaster chains.

#### 🏛️ ENDGAME & PRESTIGE (96-100)
- [96] **Historical Archive:** Revisit your first basement room as a digital Museum.
- [98] **Prestige Reset (Node Merge):** Sacrifice your company for permanent "Architecture Points".
- [100] **The Singularity:** Fully automated AI-self-healing datacenter (The final upgrade).

---

## 🛡️ MISSION-CRITICAL SECURITY (ADMIN)
- **RBAC (Role-Based Access):** Support devs cannot change economic formulas.
- **2FA & Audit Trail:** Every single change is traceable to a real person.
- **Dangerous Action Prompts:** "This change will likely cause 40% of small players to go bankrupt. Proceed?"

---

## 📐 UPDATED DESIGN RULES (V5.0)
1. **The System is the Game:** If you can't edit it in the Admin Panel, it shouldn't be in the code.
2. **Reverse Everything:** No change is permanent. Rollback is the first rule of Live-Ops.
3. **Connect the Dots:** Feature 100 must still care about Feature 1 (Space).
4. **Visibility is Power:** Predict cascades before they happen.
