# WebSocket Infrastructure (Laravel Reverb)

Rackora v2 uses **Laravel Reverb** for real-time game state updates. This replaces the legacy polling mechanism, significantly reducing server load and providing instant feedback for economy, events, and server status changes.

## 1. Quick Start

To enable real-time features, you must run two background processes in addition to the web server:

```bash
# Terminal 1: Application (or via Valet/Docker)
npm run dev

# Terminal 2: Reverb Server (WebSockets)
php artisan reverb:start

# Terminal 3: Queue Worker (Event Broadcasting)
php artisan queue:work
```

## 2. Configuration

Ensure your `.env` file contains the generated Reverb credentials. These are typically managed automatically, but check for:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Note:** If running via Nginx Proxy (Docker), ensure `REVERB_HOST` matches your public domain (e.g., `dev.codepony.de`) and `REVERB_PORT` is exposed or proxied correctly.

## 3. Architecture

- **Events**:
  - `EconomyUpdated`: Implements `ShouldBroadcastNow`. Sent every tick via `ProcessPlayerTick` job.
  - `ServerStatusChanged`: Implements `ShouldBroadcast`. Triggered on specific actions.
  - `GameEventStarted`, `GameEventResolved`: Incident updates.

- **Frontend**:
  - `resources/js/services/echo.js`: Configures Laravel Echo.
  - `resources/js/stores/game.js`: Manages the connection and listens for events.
  - **Fallback**: If WebSocket connection fails, the frontend automatically falls back to polling stats every 15-30 seconds.

## 4. Troubleshooting

**Common Issues:**

1.  **"Connection Refused" in Console:**
    - Is `php artisan reverb:start` running?
    - Is the port (8080 or 443) accessible?

2.  **Updates not appearing (but connected):**
    - Is the **Queue Worker** running? (`php artisan queue:work`)
    - Most events are queued. If the queue is paused, updates won't broadcast.

3.  **Authentication Error (401/403):**
    - Check `sanctum` authentication cookies/tokens.
    - Ensure `BroadcastServiceProvider` is registered.

## 5. Development

To broadcast a test event manually:

```bash
php artisan tinker
> \App\Events\EconomyUpdated::dispatch(\App\Models\User::find(1), ['test' => true]);
```
