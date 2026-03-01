<template>
    <div class="v2-incident-log">
        <div class="v2-title">ASSET_INCIDENT_REPORTS</div>
        
        <div class="v2-table">
            <div class="v2-table-header">
                <span class="v2-th">TIMESTAMP</span>
                <span class="v2-th">TYPE</span>
                <span class="v2-th">GRADE</span>
                <span class="v2-th">IMPACT</span>
                <span class="v2-th">ACTIONS</span>
            </div>
            
            <div v-for="event in history" :key="event.id" class="v2-table-row">
                <span class="v2-td text-mono">{{ formatDate(event.timing.resolvedAt) }}</span>
                <span class="v2-td font-bold">{{ event.typeLabel }}</span>
                <span class="v2-td">
                    <span class="v2-grade-pip" :class="'grade-' + event.managementGrade">
                        {{ event.managementGrade }}
                    </span>
                </span>
                <span class="v2-td text-danger">-${{ event.damageCost.toLocaleString() }}</span>
                <span class="v2-td">
                    <button @click="openReplay(event)" class="v2-action-link" v-if="event.replay_data?.length > 0">
                        ANALYZE_BLACKBOX
                    </button>
                    <span v-else class="text-dim mr-2">NO_DATA</span>
                    <button class="v2-action-link text-warning ml-2" v-if="!event.hasPostMortem" @click="openPostMortem(event)">
                        FILE_POST_MORTEM
                    </button>
                    <span class="v2-badge bg-success text-xs ml-2" v-else>
                        REPORT_FILED (+{{ event.reputationRecovered }} REP)
                    </span>
                </span>
            </div>
            
            <div v-if="history.length === 0" class="v2-empty-state">
                NO_INCIDENTS_RECORDED_FOR_CURRENT_EPOCH
            </div>
        </div>

        <IncidentReplay 
            v-if="selectedReplayEvent" 
            :event="selectedReplayEvent" 
            @close="selectedReplayEvent = null" 
        />

        <!-- Post-Mortem Modal -->
        <div class="v2-modal-overlay" v-if="postMortemEvent">
            <div class="v2-modal">
                <div class="v2-modal-header">
                    <h3>FILE POST-MORTEM REPORT</h3>
                    <button class="v2-close-btn" @click="postMortemEvent = null">×</button>
                </div>
                <div class="v2-modal-body">
                    <p class="text-sm text-muted mb-4">Filing a post-mortem report reflects well on company transparency and recovers a portion of lost reputation.</p>
                    
                    <div class="form-group">
                        <label>IDENTIFIED ROOT CAUSE</label>
                        <select v-model="pmForm.root_cause" class="v2-input w-full">
                            <option value="">-- Select Root Cause --</option>
                            <option value="hardware">Hardware Degradation / End-of-Life</option>
                            <option value="network">External Network Anomaly (e.g. DDoS)</option>
                            <option value="cooling">Thermal Mismanagement / Overheating</option>
                            <option value="software">Software Bug / Unpatched Vulnerability</option>
                            <option value="human">Human Error / Sabotage</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label>PREVENTATIVE ACTION PLAN</label>
                        <select v-model="pmForm.preventative_action" class="v2-input w-full">
                            <option value="">-- Select Action Plan --</option>
                            <option value="redundancy">Increase Hardware Redundancy</option>
                            <option value="training">Enhance Staff Training & Protocols</option>
                            <option value="firewall">Upgrade Network Appliances & Firewalls</option>
                            <option value="cooling_upgrade">Install Advanced Cooling Systems</option>
                            <option value="vendor">Switch Vendors / Escalate SLA</option>
                        </select>
                    </div>
                </div>
                <div class="v2-modal-footer mt-4">
                    <button class="v2-action-btn w-full" :disabled="!isPmFormValid || isSubmitting" @click="submitPostMortem">
                        {{ isSubmitting ? 'SUBMITTING...' : 'SUBMIT REPORT VIA SECURE CHANNEL' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../utils/api';
import IncidentReplay from '../HUD/IncidentReplay.vue';

const history = ref([]);
const selectedReplayEvent = ref(null);
const postMortemEvent = ref(null);
const isSubmitting = ref(false);

const pmForm = ref({
    root_cause: '',
    preventative_action: ''
});

const isPmFormValid = computed(() => pmForm.value.root_cause && pmForm.value.preventative_action);

const loadHistory = async () => {
    try {
        const response = await api.get('/events/history');
        if (response.success) {
            history.value = response.data;
        }
    } catch (error) {
        console.error('Failed to load event history', error);
    }
};

const openReplay = (event) => {
    selectedReplayEvent.value = event;
};

const openPostMortem = (event) => {
    postMortemEvent.value = event;
    pmForm.value.root_cause = '';
    pmForm.value.preventative_action = '';
};

const submitPostMortem = async () => {
    if (!isPmFormValid.value || !postMortemEvent.value) return;
    isSubmitting.value = true;
    try {
        const response = await api.post(`/events/${postMortemEvent.value.id}/post-mortem`, pmForm.value);
        if (response.success) {
            // Update the event locally
            const eventIndex = history.value.findIndex(e => e.id === postMortemEvent.value.id);
            if (eventIndex !== -1) {
                history.value[eventIndex].hasPostMortem = true;
                history.value[eventIndex].reputationRecovered = response.data.reputationRecovered;
            }
            postMortemEvent.value = null;
        }
    } catch (error) {
        console.error('Failed to submit post-mortem', error);
    } finally {
        isSubmitting.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    const d = new Date(date);
    if (isNaN(d.getTime())) return 'Invalid';
    return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
};

onMounted(loadHistory);
</script>

<style scoped>
.v2-incident-log {
    padding: 20px 0;
}

.v2-grade-pip {
    display: inline-flex;
    width: 24px;
    height: 24px;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-weight: 900;
    font-size: 0.75rem;
    border: 1px solid currentColor;
}

.grade-S { color: gold; background: rgba(255,215,0,0.1); }
.grade-A { color: silver; background: rgba(192,192,192,0.1); }
.grade-F { color: var(--v2-danger); background: rgba(239, 68, 68, 0.1); }

.v2-action-link {
    background: none;
    border: none;
    color: var(--v2-accent);
    text-decoration: underline;
    font-family: var(--font-family-mono);
    font-size: 0.65rem;
    font-weight: 800;
    cursor: pointer;
    text-transform: uppercase;
}

.v2-action-link:hover {
    color: #fff;
}

.text-warning {
    color: var(--v2-warning, #fbbf24);
}

.text-warning:hover {
    color: #fcd34d;
}

.ml-2 { margin-left: 0.5rem; }
.mr-2 { margin-right: 0.5rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-4 { margin-top: 1rem; }
.mb-4 { margin-bottom: 1rem; }
.w-full { width: 100%; }

.form-group label {
    display: block;
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost, #a0a0a0);
    letter-spacing: 0.1em;
    font-weight: 800;
    margin-bottom: 4px;
}

.v2-input {
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.v2-input:focus {
    outline: none;
    border-color: var(--v2-accent, #3b82f6);
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
}
</style>
