/**
 * Rackora — Laravel Echo WebSocket Integration
 * 
 * Initializes Echo with Reverb as the WebSocket backend.
 * Provides real-time updates to the game without polling.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally (required by Echo)
window.Pusher = Pusher;

let echoInstance = null;

/**
 * Initialize Laravel Echo — connects to Reverb WebSocket server.
 * Call this once after the user is authenticated.
 */
export function initEcho() {
    if (echoInstance) return echoInstance;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Auth endpoint for private channels
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('game_token')}`,
                'Accept': 'application/json',
            },
        },
    });

    // Debug logging (dev only)
    if (import.meta.env.DEV) {
        echoInstance.connector.pusher.connection.bind('connected', () => {
            console.log('🔌 [Rackora WS] Connected to Reverb');
        });
        echoInstance.connector.pusher.connection.bind('disconnected', () => {
            console.log('🔌 [Rackora WS] Disconnected from Reverb');
        });
        echoInstance.connector.pusher.connection.bind('error', (err) => {
            console.warn('🔌 [Rackora WS] Connection error:', err);
        });
    }

    return echoInstance;
}

/**
 * Get the current Echo instance (or null if not initialized).
 */
export function getEcho() {
    return echoInstance;
}

/**
 * Disconnect Echo and clean up.
 */
export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

/**
 * Subscribe to the user's private game channel for real-time updates.
 * Returns the channel so the caller can listen for specific events.
 */
export function subscribeToGameChannel(userId) {
    const echo = initEcho();
    return echo.private(`game.${userId}`);
}

/**
 * Subscribe to the public world-events channel.
 */
export function subscribeToWorldEvents() {
    const echo = initEcho();
    return echo.channel('world-events');
}

/**
 * Helper to get a cookie value (needed for XSRF token auth).
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return decodeURIComponent(parts.pop().split(';').shift());
    }
    return '';
}
