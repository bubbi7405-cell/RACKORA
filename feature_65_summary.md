# Feature 65: Global Crisis Scenarios (Initial Implementation)

This feature introduces unpredictable, high-impact events that affect the entire gameplay loop, requiring active player management or specific defenses.

## Components Implemented

### 1. Backend (`GlobalCrisisService`, `GlobalCrisis` Model)
- **Data Model:** Tracks Crisis Type, Phase (Warning -> Impact -> Resolved), and Severity.
- **Service Logic:**
    - Handled via `GameLoopService` once per minute ("Tick").
    - Triggers crisis start based on probability (currently low, ~1/2000 per tick for Level > 10).
    - **Phases:**
        - **Warning:** A countdown period where players can prepare.
        - **Impact:** Active phase where penalties apply.
    - **Types Implemented:**
        - **Solar Flare:** 50% increase in power costs. Random damage to running servers (5% chance/tick). Counter: Shutdown.
        - **Fiber Cut:** 200ms latency penalty. Increased bandwidth costs. Counter: Reroute (automatic cost increase).
        - **Market Crash:** 30% reduction in contract payouts. New orders paused.

### 2. Frontend (`GlobalCrisisOverlay.vue`)
- **UI:** A floating, dramatic alert bar at the top of the screen.
    - **Yellow (Warning):** Displays countdown timer. "IMPACT IN XX:XX".
    - **Red (Impact):** Pulsing critical alert. Displays live damage stats (e.g., "Servers Damaged: 12").
- **Integration:** Hooked into `GameContainer` and `GameStore` via WebSocket updates.

### 3. Game Loop Integration
- `GlobalCrisisService` injects modifiers into:
    - `processEconomy`: Adjusts power costs and contract income.
    - `processCustomerSatisfaction`: (Planned) Adjusts churn based on crisis handling.
- **Real-time Updates:** Active crisis state is broadcast to frontend via `EconomyUpdated` event.

## How to Test
A manual trigger command was executed via Tinker to verify the system:
```bash
php artisan tinker --execute="\$user = App\Models\User::first(); app(App\Services\Game\GlobalCrisisService::class)->triggerCrisis(\$user, 'solar_flare');"
```
You should see the "SOLAR SUPERSTORM - WARNING" bar appear at the top of the game interface.

## Next Steps
- Implement **Player Actions** during crisis (e.g., "activate_shield", "emergency_shutdown_all").
- Add **Special Research** to mitigate specific crisis types (e.g., "EMP Hardening", "Redundant Backbone").
