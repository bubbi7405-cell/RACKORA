import { computed } from 'vue';
import { useEventsStore } from '../stores/events';

/**
 * Enhanced Incident Feed Composable
 * Filters, sorts, and formats incident data for feeds and widgets.
 */
export function useIncidentFeed() {
    const eventStore = useEventsStore();

    // Derived Lists
    const activeIncidents = computed(() => {
        return eventStore.events.active
            .slice()
            .sort((a, b) => new Date(b.created_at || Date.now()) - new Date(a.created_at || Date.now()));
    });

    const criticalIncidents = computed(() =>
        activeIncidents.value.filter(e => e.severity === 'critical' || e.severity === 'emergency')
    );

    const warnings = computed(() =>
        activeIncidents.value.filter(e => e.severity === 'warning')
    );

    const hasCritical = computed(() => criticalIncidents.value.length > 0);
    const hasWarnings = computed(() => warnings.value.length > 0);
    const hasAny = computed(() => activeIncidents.value.length > 0);

    const latestIncident = computed(() => {
        if (activeIncidents.value.length === 0) return null;
        return activeIncidents.value[0];
    });

    /**
     * Get incident count by severity
     */
    function getCountBySeverity(sev) {
        return activeIncidents.value.filter(e => e.severity === sev).length;
    }

    /**
     * Resolve an incident (proxy to store action)
     */
    async function resolve(incidentId, actionId) {
        return await eventStore.resolveEvent(incidentId, actionId);
    }

    /* ──── Human Readable Helpers ──── */
    function getSeverityLabel(sev) {
        switch (sev) {
            case 'emergency': return 'CRITICAL FAILURE';
            case 'critical': return 'SYSTEM ERROR';
            case 'warning': return 'WARNING';
            default: return 'INFO';
        }
    }

    function getSeverityColor(sev) {
        switch (sev) {
            case 'emergency': return 'var(--ds-severity-critical)';
            case 'critical': return 'var(--ds-severity-critical)';
            case 'warning': return 'var(--ds-severity-warning)';
            default: return 'var(--ds-severity-nominal)';
        }
    }

    return {
        // State
        all: activeIncidents,
        critical: criticalIncidents,
        warnings,
        latest: latestIncident,

        // Status checks
        hasCritical,
        hasWarnings,
        hasAny,

        // Actions/Helpers
        getCountBySeverity,
        resolve,
        getSeverityLabel,
        getSeverityColor
    };
}
