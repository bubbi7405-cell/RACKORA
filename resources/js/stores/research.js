import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';

/**
 * Research Store
 * Owns: Tech tree projects, active research
 * Actions: load research, start research
 */
export const useResearchStore = defineStore('research', () => {
    // ─── State ──────────────────────────────────────────
    const isLoading = ref(false);

    const research = ref({
        projects: [],
        active: null,
    });

    // ─── Getters ────────────────────────────────────────

    const isResearching = computed(() => !!research.value.active);

    const completedProjects = computed(() =>
        research.value.projects.filter(p => p.status === 'completed')
    );

    const availableProjects = computed(() =>
        research.value.projects.filter(p => p.status === 'available')
    );

    const researchProgress = computed(() => {
        if (!research.value.active) return 0;
        return research.value.active.progress || 0;
    });

    // ─── State Application ──────────────────────────────

    /**
     * Apply research data from the game state payload.
     * Called by the orchestrator (game.js) during applyGameState.
     */
    function applyState(data) {
        if (!data) return;

        if (data.research) {
            research.value.projects = Array.isArray(data.research) ? data.research : [];
            research.value.active = research.value.projects.find(r => r.status === 'researching') || null;
        }
    }

    // ─── Actions ────────────────────────────────────────

    async function loadResearch() {
        try {
            const response = await api.get('/research');
            if (response.success) {
                research.value.projects = response.data;
                research.value.active = response.data.find(p => p.status === 'researching') || null;
            }
        } catch (error) {
            console.error('Failed to load research', error);
        }
    }

    async function startResearch(key) {
        isLoading.value = true;
        try {
            const response = await api.post('/research/start', { tech_id: key });
            if (response.success) {
                useToastStore().success('Research started');
                await loadResearch();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.message || 'Failed to start research');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    function isResearched(techId) {
        return research.value.projects.some(p => p.id === techId && p.status === 'completed');
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        isLoading,
        research,
        // Getters
        isResearching,
        completedProjects,
        availableProjects,
        researchProgress,
        // State application
        applyState,
        // Actions
        loadResearch,
        startResearch,
        isResearched,
    };
});
