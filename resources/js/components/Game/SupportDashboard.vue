<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '@/stores/game';
import api from '@/utils/api';

const gameStore = useGameStore();
const tickets = ref([]);
const isLoading = ref(false);

async function loadTickets() {
    isLoading.value = true;
    try {
        const response = await api.get('/game/support-tickets');
        tickets.value = response.data;
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

async function investigate(ticket) {
    if (ticket.status === 'analyzing') return;
    try {
        const response = await api.post(`/game/support-tickets/${ticket.id}/investigate`);
        const index = tickets.value.findIndex(t => t.id === ticket.id);
        if (index !== -1) {
            tickets.value[index] = response.data.ticket;
        }
    } catch (e) {
        console.error(e);
    }
}

const activeTickets = computed(() => tickets.value.filter(t => t.status !== 'resolved'));
const resolvedTickets = computed(() => tickets.value.filter(t => t.status === 'resolved'));

function getPriorityClass(prio) {
    return {
        'prio-low': prio === 'low',
        'prio-medium': prio === 'medium',
        'prio-high': prio === 'high',
        'prio-critical': prio === 'critical'
    };
}

onMounted(() => {
    loadTickets();
});
</script>

<template>
    <div class="support-dashboard">
        <header class="dashboard-header">
            <div class="header-main">
                <h2>SUPPORT_CENTER</h2>
                <div class="stats">
                    <div class="stat-item" v-tooltip="{ title: 'Aktive Tickets', content: 'Anzahl der aktuell offenen oder in Bearbeitung befindlichen Probleme.' }">
                        <span class="label">OFFENE_TICKETS</span>
                        <span class="value">{{ activeTickets.length }}</span>
                    </div>
                </div>
            </div>
            <button class="btn-refresh" @click="loadTickets" :disabled="isLoading" v-tooltip="'Aktualisiert die Ticket-Liste vom Server.'">
                {{ isLoading ? 'LADEN...' : 'AKTUALISIEREN' }}
            </button>
        </header>

        <div class="ticket-grid">
            <div v-for="ticket in activeTickets" :key="ticket.id" class="ticket-card" :class="getPriorityClass(ticket.priority)">
                <div class="ticket-header">
                    <span class="priority-badge" v-tooltip="{ title: 'Priorität', content: 'Bestimmt die Dringlichkeit der Bearbeitung durch Agenten.' }">{{ ticket.priority.toUpperCase() }}</span>
                    <span class="ticket-id">#{{ ticket.id.substring(0, 8) }}</span>
                </div>
                <h3>{{ ticket.subject }}</h3>
                <p class="customer">{{ ticket.customerName }}</p>
                <div class="progress-container" v-tooltip="{ title: 'Fortschritt', content: 'Zeigt wie nah das Ticket an einer Lösung ist.' }">
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: ticket.progress + '%' }"></div>
                    </div>
                    <span class="progress-text">{{ ticket.progress }}%</span>
                </div>
                <div class="ticket-footer">
                    <span class="status">{{ ticket.status.replace('_', ' ').toUpperCase() }}</span>
                    <button 
                        v-if="ticket.status === 'open' || ticket.status === 'in_progress'" 
                        class="btn-action" 
                        @click="investigate(ticket)"
                        :disabled="ticket.status === 'analyzing'"
                        v-tooltip="{ title: 'Support-Analyse', content: 'Steigere den Lösungsfortschritt sofort um 25%. Verbraucht keine Mitarbeiter-Energie!', hint: 'Nutze dies für kritische Ausfälle!' }"
                    >
                        ANALYSIEREN (+25%)
                    </button>
                    <span v-if="ticket.assignedEmployee" class="agent">Agent: {{ ticket.assignedEmployee }}</span>
                    <span v-else class="agent unassigned" v-tooltip="'Wartet auf einen freien Support-Mitarbeiter.'">WARTEND_AUF_AGENT</span>
                </div>
            </div>

            <div v-if="activeTickets.length === 0" class="empty-state">
                <div class="icon">✅</div>
                <p>KEINE_OFFENEN_TICKETS</p>
                <span>Alle Kunden sind aktuell zufrieden.</span>
            </div>
        </div>

        <section v-if="resolvedTickets.length > 0" class="history-section">
            <h3>KÜRZLICH_GELÖST</h3>
            <div class="history-list">
                <div v-for="ticket in resolvedTickets.slice(0, 5)" :key="ticket.id" class="history-item">
                    <span class="time">{{ new Date(ticket.resolvedAt).toLocaleTimeString() }}</span>
                    <span class="title">{{ ticket.subject }}</span>
                    <span class="customer">{{ ticket.customerName }}</span>
                    <span class="tag">GELÖST</span>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.support-dashboard {
    padding: 24px;
    color: #fff;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.header-main h2 {
    margin: 0 0 8px 0;
    font-family: 'Outfit', sans-serif;
    letter-spacing: 2px;
    color: var(--primary-color, #00d4ff);
}

.stats {
    display: flex;
    gap: 24px;
}

.stat-item .label {
    display: block;
    font-size: 0.75rem;
    color: #888;
    margin-bottom: 4px;
}

.stat-item .value {
    font-size: 1.5rem;
    font-weight: 700;
}

.btn-refresh {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-refresh:hover:not(:disabled) {
    background: rgba(255,255,255,0.1);
}

.ticket-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.ticket-card {
    background: rgba(30, 30, 45, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s;
}

.ticket-card:hover {
    transform: translateY(-4px);
    border-color: rgba(255, 255, 255, 0.15);
}

.ticket-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.prio-low::before { background: #4caf50; }
.prio-medium::before { background: #ff9800; }
.prio-high::before { background: #f44336; }
.prio-critical::before { 
    background: #ff0000;
    box-shadow: 0 0 10px #ff0000;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.priority-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(255,255,255,0.1);
    font-weight: 700;
}

.ticket-id {
    font-size: 0.75rem;
    color: #666;
    font-family: monospace;
}

.ticket-card h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
}

.customer {
    font-size: 0.85rem;
    color: #aaa;
    margin-bottom: 16px;
}

.progress-container {
    margin-bottom: 16px;
}

.progress-bar {
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: var(--primary-color, #00d4ff);
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.75rem;
    color: #888;
}

.ticket-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 12px;
}

.status {
    color: var(--primary-color, #00d4ff);
    font-weight: 600;
}

.btn-action {
    background: var(--primary-color, #00d4ff);
    color: #000;
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-action:hover {
    background: #fff;
    transform: scale(1.05);
}

.btn-action:disabled {
    background: #444;
    cursor: not-allowed;
}

.agent.unassigned {
    color: #ff9800;
    font-style: italic;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px;
    background: rgba(255,255,255,0.02);
    border-radius: 20px;
    border: 2px dashed rgba(255,255,255,0.05);
}

.empty-state .icon {
    font-size: 3rem;
    margin-bottom: 16px;
}

.history-section h3 {
    font-size: 1rem;
    color: #666;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.history-list {
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
    overflow: hidden;
}

.history-item {
    display: grid;
    grid-template-columns: 100px 1fr 200px 100px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    align-items: center;
    font-size: 0.9rem;
}

.history-item:last-child { border-bottom: none; }

.history-item .time { color: #666; }
.history-item .customer { margin: 0; color: #888; }
.history-item .tag {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    text-align: center;
}
</style>
