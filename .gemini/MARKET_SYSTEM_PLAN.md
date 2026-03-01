# RACKORA — Complete Dynamic Market System Blueprint

**Version:** 1.0
**Codename:** HEGEMONY
**Scope:** Full economic simulation engine with AI competitors, regional markets, demand/capacity matching, and business cycle modeling.

---

## ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────┐
│                    MARKET SIMULATION                     │
│                                                          │
│  ┌──────────────────┐    ┌──────────────────────────┐   │
│  │  EconomicCycleEng│    │  GlobalMarketService     │   │
│  │  • GDP Growth    │◄──►│  • Region Index          │   │
│  │  • Inflation     │    │  • Sector Demand         │   │
│  │  • Energy Costs  │    │  • Market Share Calc     │   │
│  │  • State Machine │    │  • IP/Bandwidth Econ     │   │
│  └──────────┬───────┘    └──────────┬───────────────┘   │
│             │                       │                    │
│  ┌──────────▼───────┐    ┌──────────▼───────────────┐   │
│  │  DemandEngine    │    │  CompetitorAIService     │   │
│  │  • Per-Region    │◄──►│  • Personality AI        │   │
│  │  • Per-Sector    │    │  • Decision Engine       │   │
│  │  • Capacity Gate │    │  • Strategic Reactions    │   │
│  │  • Churn Cascade │    │  • Score Evaluation      │   │
│  └──────────┬───────┘    └──────────┬───────────────┘   │
│             │                       │                    │
│  ┌──────────▼───────────────────────▼───────────────┐   │
│  │          INTEGRATION LAYER                        │   │
│  │  GameLoopService.processUserLogic()               │   │
│  │  → Step 25: marketSimulation->tick()              │   │
│  │  → Step 26: competitorAI->tick()                  │   │
│  │  → Step 27: economicCycle->tick()                 │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

## DATABASE CHANGES

### 1. `market_state` (Global Singleton per server — GameConfig entries)
Stored as GameConfig key-value pairs for flexibility:
- `market.economic_state` → Growth|Expansion|Peak|Recession|Crisis|Recovery
- `market.economic_cycle_tick` → int (ticks in current state)
- `market.gdp_growth_rate` → float
- `market.inflation_rate` → float
- `market.energy_cost_index` → float
- `market.global_demand_index` → float
- `market.last_drift_tick` → int

### 2. Migration: `enhance_competitors_market_v3`
Add to `competitors`:
- `sector_shares` JSON → per-sector market share
- `capacity_score` float → simulated infra capacity
- `uptime_score` float → 0-100 simulated
- `latency_score` float → ms
- `price_modifier` float → 0.5-2.0 multiplier
- `marketing_budget` float
- `innovation_index` float → 0-100
- `archetype` string → expander|stability|budget|innovator|regional
- `last_decision_tick` int
- `decision_cooldown` int

### 3. Migration: `create_market_regions_table`
- `id`, `key` (string, unique: eu, na, apac, emerging)
- `label`, `gdp_growth`, `political_stability`, `infra_saturation`
- `energy_cost_multiplier`, `regulation_level`
- `demand_base`, `demand_growth_factor`
- `ip_pool_capacity`, `ip_pool_used`
- `ix_available` (bool), `ix_latency_bonus`
- timestamps

### 4. Migration: `create_market_demand_log_table`
- `id`, `tick`, `region`, `sector`, `demand_generated`, `demand_served`
- `player_served`, `competitor_served_json`, `unmet_demand`
- timestamps

### 5. Migration: `add_market_fields_to_player_economy`
- `global_market_share` float
- `regional_shares` JSON
- `sector_shares` JSON
- `arpu` float
- `innovation_index` float
- `risk_exposure` float
- `marketing_budget` float
- `marketing_effectiveness` float
- `customer_acquisition_cost` float

## SERVICE ARCHITECTURE

### File: `app/Services/Market/EconomicCycleEngine.php`
Manages the global economic state machine.

### File: `app/Services/Market/GlobalMarketService.php`
Calculates demand pools, distributes demand, scores participants.

### File: `app/Services/Market/CompetitorAIService.php`
AI-driven competitor decision engine.

### File: `app/Services/Market/DemandEngine.php`
Per-region, per-sector demand calculation and distribution.

### File: `app/Services/Market/MarketSimulationService.php`
Orchestrator — calls all sub-services in order per tick.

## IMPLEMENTATION ORDER

1. Migrations (database foundation)
2. EconomicCycleEngine (state machine)
3. DemandEngine (demand generation + distribution)
4. CompetitorAIService (AI behaviors)
5. GlobalMarketService (scoring + market share calculation)
6. MarketSimulationService (orchestrator)
7. Integration into GameLoopService
8. MarketRegion seeder
9. API endpoints + frontend store
