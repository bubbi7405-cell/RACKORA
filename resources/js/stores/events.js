import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';
import SoundManager from '../services/SoundManager';

/**
 * Events Store
 * Owns: Game events (incidents), world events, crisis state
 * Actions: resolve events, close summaries
 * WS handlers: event started/escalated/resolved/failed
 */
export const useEventsStore = defineStore('events', () => {
    // ─── State ──────────────────────────────────────────

    const events = ref({
        active: [],
        hasWarnings: false,
        hasCritical: false,
        resolvedSummary: null,
        vulnerabilities: [], // F118
        isEnergyVolatile: false, // F121
    });

    const worldEvents = ref({
        active: [],
        history: [],
    });

    const activeCrisis = ref(null);

    // ─── Getters ────────────────────────────────────────

    const activeEvents = computed(() => events.value.active);
    const activeEventCount = computed(() => events.value.active.length);
    const hasCriticalEvent = computed(() => events.value.hasCritical);
    const hasWarnings = computed(() => events.value.hasWarnings);

    const criticalEvents = computed(() =>
        events.value.active.filter(e => e.severity === 'critical' || e.severity === 'emergency')
    );

    const warningEvents = computed(() =>
        events.value.active.filter(e => e.severity === 'warning')
    );

    // ─── State Application ──────────────────────────────

    /**
     * Apply events-related data from the game state payload.
     * Called by the orchestrator (game.js) during applyGameState.
     */
    function applyState(data) {
        if (!data) return;

        if (data.events) {
            Object.assign(events.value, data.events);
            if (!Array.isArray(events.value.active)) events.value.active = [];
        }

        if (data.world_events) {
            Object.assign(worldEvents.value, data.world_events);
            if (!Array.isArray(worldEvents.value.active)) worldEvents.value.active = [];
            if (!Array.isArray(worldEvents.value.history)) worldEvents.value.history = [];
        }

        if (data.vulnerabilities !== undefined) {
            events.value.vulnerabilities = data.vulnerabilities;
        }

        if (data.isEnergyVolatile !== undefined) {
            events.value.isEnergyVolatile = data.isEnergyVolatile;
        }

        if (data.activeCrisis !== undefined) {
            activeCrisis.value = data.activeCrisis;
        }
    }

    // ─── Actions ────────────────────────────────────────

    async function resolveEvent(eventId, actionId) {
        const toast = useToastStore();
        try {
            const response = await api.post('/events/resolve', {
                event_id: eventId,
                action_id: actionId,
            });

            if (response.success) {
                // Remove event from active list
                const index = events.value.active.findIndex(e => e.id === eventId);
                if (index !== -1) {
                    events.value.active.splice(index, 1);
                }

                if (response.data.outcome === 'success') {
                    events.value.resolvedSummary = response.data.event;
                    toast.success('Crisis resolved! Management Grade: ' + response.data.event.managementGrade);
                } else {
                    toast.warning('Action failed! The situation has escalated.');
                }

                return { success: true, resolution: response.data.resolution };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to resolve event');
            return { success: false, error: error.message };
        }
    }

    function closeResolvedSummary() {
        events.value.resolvedSummary = null;
    }

    async function takeCrisisAction(actionId) {
        const toast = useToastStore();
        try {
            const response = await api.post('/crisis/action', {
                action: actionId,
            });

            if (response.success) {
                toast.success('Action executed successfully!');
                return true;
            } else {
                toast.error(response.error);
                return false;
            }
        } catch (error) {
            toast.error('Failed to execute crisis action');
            return false;
        }
    }

    async function submitFiberMinigame(success) {
        const toast = useToastStore();
        try {
            const response = await api.post('/crisis/fiber-minigame', { success });
            if (response.success) {
                if (success) {
                    toast.success(response.message || 'Fiber rerouted!');
                } else {
                    toast.error(response.message || 'Redirection failed.');
                }
                return true;
            }
        } catch (error) {
            toast.error('Failed to submit minigame result');
        }
        return false;
    }

    async function submitStrikeNegotiation(outcome, success) {
        const toast = useToastStore();
        try {
            const response = await api.post('/crisis/strike-negotiation', { outcome, success });
            if (response.success) {
                return true;
            } else {
                toast.error(response.error);
                return false;
            }
        } catch (error) {
            toast.error('Negotiation failed.');
            return false;
        }
    }

    // ─── WebSocket Handlers ─────────────────────────────

    function handleEventStarted(data) {
        const toast = useToastStore();
        if (data.event) {
            const exists = events.value.active.find(e => e.id === data.event.id);
            if (!exists) {
                events.value.active.push(data.event);
            }
            events.value.hasWarnings = true;
            SoundManager.playAlarm();
            toast.warning(`⚠️ New incident: ${data.event.title}`);
        }
    }

    function handleEventEscalated(data) {
        const toast = useToastStore();
        if (data.event) {
            const idx = events.value.active.findIndex(e => e.id === data.event.id);
            if (idx !== -1) {
                events.value.active[idx] = data.event;
            }
            events.value.hasCritical = true;
            SoundManager.playAlarm();
            toast.error(`🔴 Event escalated: ${data.event.title}`);
        }
    }

    function handleEventResolved(data) {
        if (data.event) {
            events.value.active = events.value.active.filter(e => e.id !== data.event.id);
            if (data.consequences) {
                events.value.resolvedSummary = {
                    event: data.event,
                    consequences: data.consequences,
                };
            }
            SoundManager.playSuccess();
        }
    }

    function handleEventFailed(data) {
        const toast = useToastStore();
        if (data.event) {
            events.value.active = events.value.active.filter(e => e.id !== data.event.id);
            SoundManager.playError();
            toast.error(`💥 Event failed: ${data.event.title}`);
        }
    }

    function handleWorldEvent(data) {
        const toast = useToastStore();
        if (data.event) {
            // Add to active list if not exists
            const exists = worldEvents.value.active.find(e => e.id === data.event.id);
            if (!exists) {
                worldEvents.value.active.push(data.event);
            }

            // Add to history
            worldEvents.value.history.unshift(data.event);
            if (worldEvents.value.history.length > 50) worldEvents.value.history.pop();

            // Sound FX based on severity
            if (data.event.severity === 'critical') {
                SoundManager.playAlarm();
                toast.error(`🌍 GLOBAL EVENT: ${data.event.title}`);
            } else if (data.event.severity === 'high') {
                SoundManager.playNotification();
                toast.warning(`🌍 MARKET ALERT: ${data.event.title}`);
            } else {
                SoundManager.playNotification();
                toast.info(`🌍 NEWS: ${data.event.title}`);
            }
        }
    }

    function handleEconomyTickEvents(data) {
        if (data.activeEvents !== undefined) {
            events.value.hasCritical = data.activeEvents > 0;
        }
        if (data.activeCrisis !== undefined) {
            activeCrisis.value = data.activeCrisis;
        }
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        events,
        worldEvents,
        activeCrisis,
        // Getters
        activeEvents,
        activeEventCount,
        hasCriticalEvent,
        hasWarnings,
        criticalEvents,
        warningEvents,
        // State application
        applyState,
        // Actions
        resolveEvent,
        closeResolvedSummary,
        takeCrisisAction,
        submitFiberMinigame,
        submitStrikeNegotiation,
        // WS handlers
        handleEventStarted,
        handleEventEscalated,
        handleEventResolved,
        handleEventFailed,
        handleEconomyTickEvents,
        handleWorldEvent,
    };
});
