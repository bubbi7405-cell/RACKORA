<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="incident-overlay glass-panel animation-fade-in">
            <div class="header-section">
                <div class="incident-badge">OPERATIONS CENTER</div>
                <h1>Incident Commander</h1>
                <p class="description">Review past crises, analyze response performance, and learn from infrastructure failures.</p>
                <div class="close-btn" @click="$emit('close')">×</div>
            </div>

            <div class="main-layout">
                <!-- INCIDENT LIST -->
                <div class="sidebar">
                    <div class="list-header">
                        <span>Past Incidents</span>
                        <button class="refresh-mini" @click="fetchHistory" :disabled="loading">↻</button>
                    </div>
                    
                    <div v-if="loading" class="mini-loader">
                        <div class="spinner"></div>
                    </div>
                    
                    <div v-else class="incident-list">
                        <div 
                            v-for="event in history" 
                            :key="event.id" 
                            class="incident-item"
                            :class="{ 'active': selectedId === event.id, 'failed': event.status === 'failed' }"
                            @click="selectIncident(event)"
                        >
                            <div class="item-icon">{{ event.typeIcon }}</div>
                            <div class="item-info">
                                <div class="item-title">{{ event.title }}</div>
                                <div class="item-meta">
                                    <span class="grade-pill" :class="'grade--' + event.managementGrade">{{ event.managementGrade || 'F' }}</span>
                                    <span class="item-date">{{ formatDate(event.timing.resolvedAt) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="history.length === 0" class="empty-state">
                            No major incidents recorded in recent history.
                        </div>
                    </div>
                </div>

                <!-- INCIDENT DETAIL / REPORT -->
                <div class="report-view">
                    <div v-if="selected" class="report-content animation-slide-up">
                        <div class="report-header">
                            <div class="report-main">
                                <span class="report-type" :style="{ color: selected.typeColor }">{{ selected.typeLabel }}</span>
                                <h2>{{ selected.title }}</h2>
                                <p class="report-desc">{{ selected.description }}</p>
                            </div>
                            <div class="report-summary-box">
                                <div class="grade-medal" :class="'grade--' + (selected.managementGrade || 'F')">
                                    <div class="medal-label">GRADE</div>
                                    <div class="medal-char">{{ selected.managementGrade || 'F' }}</div>
                                </div>
                                <div class="score-pill">Score: {{ selected.managementScore || 0 }}</div>
                            </div>
                        </div>

                        <div class="report-grid">
                            <!-- STATS -->
                            <div class="report-section">
                                <h3><span class="icon">📊</span> Impact Analysis</h3>
                                <div class="stats-grid">
                                    <div class="stat-box">
                                        <div class="stat-val text-danger">${{ selected.damageCost.toLocaleString() }}</div>
                                        <div class="stat-lab">SLA Penalties</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-val text-warning">{{ selected.affectedCustomersCount }}</div>
                                        <div class="stat-lab">Affected Customers</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-val">${{ (selected.actionCost || 0).toLocaleString() }}</div>
                                        <div class="stat-lab">Mitigation Cost</div>
                                    </div>
                                </div>
                            </div>

                            <!-- TIMELINE -->
                            <div class="report-section">
                                <h3><span class="icon">⏱</span> Timeline</h3>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="t-dot warning"></div>
                                        <div class="t-info">
                                            <div class="t-time">{{ formatFullTime(selected.timing.warningAt) }}</div>
                                            <div class="t-label">Incident Detected (Warning)</div>
                                        </div>
                                    </div>
                                    <div v-if="selected.timing.escalatesAt" class="timeline-item">
                                        <div class="t-dot critical"></div>
                                        <div class="t-info">
                                            <div class="t-time">{{ formatFullTime(selected.timing.escalatesAt) }}</div>
                                            <div class="t-label">Situation Escalated</div>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="t-dot success"></div>
                                        <div class="t-info">
                                            <div class="t-time">{{ formatFullTime(selected.timing.resolvedAt) }}</div>
                                            <div class="t-label">{{ selected.status === 'resolved' ? 'Resolution Achieved' : 'Incident Terminated (Failure)' }}</div>
                                        </div>
                                    </div>
                                    <div class="timeline-duration">
                                        Total Response Time: <span>{{ selected.postMortem?.timeToResolve || 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- LESSONS LEARNED -->
                            <div class="report-section full-width">
                                <h3><span class="icon">🧠</span> Post-Mortem & Lessons</h3>
                                <div class="lessons-container">
                                    <div class="lesson-summary">
                                        {{ selected.postMortem?.summary || 'No analysis available.' }}
                                    </div>
                                    <ul class="lesson-list">
                                        <li v-for="(lesson, i) in selected.postMortem?.lessons" :key="i">
                                            {{ lesson }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="report-placeholder">
                        <div class="placeholder-icon">📂</div>
                        <h3>Select an incident</h3>
                        <p>Select a past event from the list to view a detailed performance audit and technical post-mortem.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

const emit = defineEmits(['close']);

const history = ref([]);
const loading = ref(true);
const selectedId = ref(null);
const selected = ref(null);

const fetchHistory = async () => {
    loading.value = true;
    try {
        const res = await api.get('/events/history');
        if (res.success) {
            history.value = res.data;
            if (history.value.length > 0 && !selectedId.value) {
                selectIncident(history.value[0]);
            }
        }
    } catch (e) {
        console.error("Failed to fetch incident history", e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchHistory);

const selectIncident = (event) => {
    selectedId.value = event.id;
    selected.value = event;
    SoundManager.playClick();
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatFullTime = (dateStr) => {
    if (!dateStr) return '--:--';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
    z-index: 3500;
    display: flex; align-items: center; justify-content: center;
}

.incident-overlay {
    width: 1100px; max-width: 95vw; height: 85vh;
    background: #0a0d14; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 0 40px rgba(0,0,0,0.6);
}

.header-section {
    padding: 30px 40px 20px;
    background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    position: relative;
}

.incident-badge {
    display: inline-block; background: rgba(244, 67, 54, 0.15); color: #f44336;
    padding: 3px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 800;
    letter-spacing: 2px; margin-bottom: 10px; border: 1px solid rgba(244, 67, 54, 0.3);
}

h1 { font-size: 2rem; margin: 0; color: #fff; letter-spacing: -1px; }
.description { color: #8b949e; font-size: 1rem; margin-top: 5px; opacity: 0.8; }

.close-btn { position: absolute; top: 30px; right: 40px; font-size: 2.5rem; cursor: pointer; color: #484f58; transition: 0.2s; }
.close-btn:hover { color: #fff; transform: scale(1.1); }

/* MAIN LAYOUT */
.main-layout { flex: 1; display: flex; overflow: hidden; }

/* SIDEBAR */
.sidebar {
    width: 320px; border-right: 1px solid rgba(255,255,255,0.05);
    display: flex; flex-direction: column; background: rgba(0,0,0,0.2);
}

.list-header {
    padding: 15px 20px; background: rgba(255,255,255,0.02);
    font-size: 0.75rem; font-weight: 800; color: #484f58; letter-spacing: 1px;
    display: flex; justify-content: space-between; align-items: center;
}

.refresh-mini {
    background: none; border: none; color: #484f58; cursor: pointer; font-size: 1rem;
}
.refresh-mini:hover { color: #fff; }

.incident-list { flex: 1; overflow-y: auto; }

.incident-item {
    display: flex; gap: 15px; padding: 16px 20px; cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.03); transition: 0.2s;
}
.incident-item:hover { background: rgba(255,255,255,0.03); }
.incident-item.active { background: rgba(56, 139, 253, 0.1); border-left: 3px solid #388bfd; }
.incident-item.failed { border-left-color: #f44336; }

.item-icon { font-size: 1.5rem; width: 32px; text-align: center; }
.item-info { flex: 1; }
.item-title { font-size: 0.9rem; font-weight: 600; color: #fff; margin-bottom: 4px; }
.item-meta { display: flex; align-items: center; gap: 10px; }

.grade-pill {
    font-size: 0.65rem; font-weight: 800; padding: 1px 6px; border-radius: 3px;
    background: #333; color: #eee;
}

.item-date { font-size: 0.7rem; color: #484f58; }

/* GRADES */
.grade--S { background: #d29922; color: #000; }
.grade--A { background: #3fb950; color: #000; }
.grade--B { background: #388bfd; color: #fff; }
.grade--C { background: #8b949e; color: #000; }
.grade--D { background: #f85149; color: #fff; }
.grade--F { background: #555; color: #ccc; }

/* REPORT VIEW */
.report-view { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background: #07090e; }

.report-content { padding: 40px; }

.report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
.report-main { max-width: 70%; }
.report-type { font-size: 0.75rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px; display: block; }
.report-main h2 { font-size: 2.5rem; margin: 0 0 10px; color: #fff; letter-spacing: -1px; }
.report-desc { font-size: 1.1rem; color: #8b949e; line-height: 1.5; }

.report-summary-box { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 15px; }

.grade-medal {
    width: 100px; height: 100px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 4px solid rgba(255,255,255,0.1); box-shadow: 0 0 30px rgba(0,0,0,0.5);
}
.grade-medal.grade--S { border-color: #d29922; box-shadow: 0 0 30px rgba(210, 153, 34, 0.2); }
.medal-label { font-size: 0.65rem; font-weight: 800; opacity: 0.7; margin-bottom: -5px; }
.medal-char { font-size: 3rem; font-weight: 900; }

.score-pill {
    background: #161b22; border: 1px solid rgba(255,255,255,0.1);
    padding: 5px 15px; border-radius: 100px; font-size: 0.85rem; font-weight: 700; color: #fff;
}

.report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
.report-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 12px; padding: 24px; }
.full-width { grid-column: span 2; }

h3 { margin: 0 0 20px; font-size: 1rem; color: #fff; display: flex; align-items: center; gap: 10px; }
h3 .icon { font-size: 1.3rem; }

/* STATS */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
.stat-box { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center; }
.stat-val { font-size: 1.25rem; font-weight: 800; margin-bottom: 5px; color: #fff; }
.stat-lab { font-size: 0.65rem; color: #484f58; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }

/* TIMELINE */
.timeline { display: flex; flex-direction: column; gap: 15px; position: relative; padding-left: 10px; }
.timeline::before {
    content: ''; position: absolute; left: 16px; top: 10px; bottom: 10px; width: 1px;
    background: linear-gradient(180deg, #f44336 0%, #4caf50 100%); opacity: 0.3;
}
.timeline-item { display: flex; align-items: flex-start; gap: 20px; position: relative; z-index: 1; }
.t-dot { width: 12px; height: 12px; border-radius: 50%; background: #333; margin-top: 5px; border: 2px solid #07090e; }
.t-dot.warning { background: #ff9800; box-shadow: 0 0 10px #ff9800; }
.t-dot.critical { background: #f44336; box-shadow: 0 0 10px #f44336; }
.t-dot.success { background: #4caf50; box-shadow: 0 0 10px #4caf50; }

.t-time { font-family: var(--font-family-mono); font-size: 0.75rem; color: #484f58; margin-bottom: 2px; }
.t-label { font-size: 0.9rem; color: #eee; font-weight: 600; }

.timeline-duration {
    margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 6px;
    font-size: 0.8rem; color: #8b949e; text-align: center;
}
.timeline-duration span { color: #fff; font-weight: 700; }

/* LESSONS */
.lessons-container { }
.lesson-summary {
    background: rgba(56, 139, 253, 0.1); border-left: 4px solid #388bfd;
    padding: 15px; border-radius: 4px; color: #fff; font-weight: 600; margin-bottom: 20px;
}
.lesson-list { padding-left: 20px; margin: 0; }
.lesson-list li { color: #8b949e; line-height: 1.6; margin-bottom: 10px; font-size: 0.95rem; }

/* PLACEHOLDER */
.report-placeholder {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px; text-align: center; opacity: 0.3;
}
.placeholder-icon { font-size: 5rem; margin-bottom: 20px; }
.report-placeholder h3 { font-size: 1.5rem; margin-bottom: 10px; }
.report-placeholder p { max-width: 400px; line-height: 1.5; }

.text-danger { color: #f85149 !important; }
.text-warning { color: #d29922 !important; }

/* UTILS */
.animation-slide-up { animation: slideUp 0.4s ease-out; }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.mini-loader { display: flex; justify-content: center; padding: 40px; }
.spinner { width: 30px; height: 30px; border: 3px solid rgba(255,255,255,0.1); border-top-color: #388bfd; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
