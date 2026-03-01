<template>
    <div :class="{ 'overlay-backdrop': !inline }" @click.self="$emit('close')">
        <div class="leaderboard-overlay" :class="{ 'glass-panel animation-slide-up': !inline, 'inline-panel': inline }">
            <div class="overlay-header" v-if="!inline">
                <div class="header-title">
                    <span class="icon">🏆</span>
                    <h2>Globale Rangliste</h2>
                    <span class="player-count" v-if="totalPlayers">{{ totalPlayers }} CEOs</span>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <!-- View Toggle: Live vs Weekly -->
                <div class="view-toggle">
                    <button 
                        class="view-btn" 
                        :class="{ active: viewMode === 'live' }"
                        @click="viewMode = 'live'; fetchLeaderboard()"
                    >
                        <span class="pulse-dot"></span> Live-Ranking
                    </button>
                    <button 
                        class="view-btn"
                        :class="{ active: viewMode === 'weekly' }"
                        @click="viewMode = 'weekly'; fetchWeeklyHistory()"
                    >
                        📅 Wochen-Historie
                    </button>
                </div>

                <!-- LIVE VIEW -->
                <template v-if="viewMode === 'live'">
                    <div class="ranking-tabs">
                        <button 
                            v-for="tab in tabs" 
                            :key="tab.id"
                            class="tab-btn"
                            :class="{ active: activeTab === tab.id }"
                            @click="changeTab(tab.id)"
                        >
                            <span class="tab-icon">{{ tab.icon }}</span>
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- My Rank Banner -->
                    <div class="my-rank-banner" v-if="myStats">
                        <div class="rank-section">
                            <div class="rank-circle" :class="getRankClass(myStats.ranks[activeTab])">
                                <span class="rank-number">#{{ myStats.ranks[activeTab] || '-' }}</span>
                            </div>
                            <div class="rank-meta">
                                <span class="rank-label">DEIN RANG</span>
                                <span class="percentile" v-if="myStats.percentile">
                                    Top {{ myStats.percentile }}%
                                </span>
                            </div>
                        </div>
                        <div class="stat-section">
                            <span class="stat-label">{{ getActiveTabLabel() }}</span>
                            <span class="stat-value">{{ formatValue(myStats.stats[activeTab], activeTab) }}</span>
                        </div>
                        <div class="badges-section" v-if="myStats.badges && myStats.badges.length > 0">
                            <span 
                                v-for="badge in myStats.badges.slice(0, 3)" 
                                :key="badge.label"
                                class="badge-chip"
                                :title="badge.label"
                            >
                                {{ badge.icon }}
                            </span>
                        </div>
                    </div>

                    <!-- Leaderboard Table -->
                    <div class="leaderboard-list-container">
                        <div class="list-header">
                            <span class="col-rank">#</span>
                            <span class="col-trend"></span>
                            <span class="col-player">CEO / Unternehmen</span>
                            <span class="col-badges">Abzeichen</span>
                            <span class="col-level">Lvl</span>
                            <span class="col-score text-right">{{ getActiveTabLabel() }}</span>
                        </div>

                        <div v-if="loading" class="loading-state">
                            <div class="spinner"></div>
                            <span>Rufe globale Daten ab...</span>
                        </div>

                        <div v-else-if="leaderboard.length > 0" class="leaderboard-list">
                            <transition-group name="list" tag="div">
                                <div 
                                    v-for="player in leaderboard" 
                                    :key="player.rank" 
                                    class="player-row"
                                    :class="{ 
                                        'is-me': player.is_me,
                                        'top-3': player.rank <= 3
                                    }"
                                >
                                    <div class="col-rank">
                                        <span class="rank-badge" :class="getRankClass(player.rank)">
                                            <template v-if="player.rank === 1">👑</template>
                                            <template v-else-if="player.rank === 2">🥈</template>
                                            <template v-else-if="player.rank === 3">🥉</template>
                                            <template v-else>{{ player.rank }}</template>
                                        </span>
                                    </div>
                                    <div class="col-trend">
                                        <span v-if="player.trend === 'up'" class="trend-up" :title="`▲ ${player.trend_diff}`">
                                            ▲{{ player.trend_diff }}
                                        </span>
                                        <span v-else-if="player.trend === 'down'" class="trend-down" :title="`▼ ${player.trend_diff}`">
                                            ▼{{ player.trend_diff }}
                                        </span>
                                        <span v-else-if="player.trend === 'new'" class="trend-new">NEW</span>
                                        <span v-else class="trend-same">—</span>
                                    </div>
                                    <div class="col-player">
                                        <div class="player-name">
                                            {{ player.player_name }}
                                            <span v-if="player.is_me" class="me-tag">YOU</span>
                                        </div>
                                        <div class="company-name">{{ player.company_name }}</div>
                                    </div>
                                    <div class="col-badges">
                                        <span 
                                            v-for="badge in (player.badges || []).slice(0, 2)" 
                                            :key="badge.label"
                                            class="mini-badge"
                                            :title="badge.label"
                                        >{{ badge.icon }}</span>
                                    </div>
                                    <div class="col-level">
                                        <span class="level-badge">{{ player.level }}</span>
                                    </div>
                                    <div class="col-score text-right">
                                        {{ formatValue(player.score, activeTab) }}
                                    </div>
                                </div>
                            </transition-group>
                        </div>

                        <div v-else class="empty-state">
                            <span class="empty-icon">🏜️</span>
                            <p>No data available for this category yet.</p>
                        </div>
                    </div>
                </template>

                <!-- WEEKLY HISTORY VIEW -->
                <template v-if="viewMode === 'weekly'">
                    <!-- Podium -->
                    <div class="podium-section" v-if="podium.length > 0">
                        <h3 class="section-title">🏅 Die Champions dieser Woche</h3>
                        <div class="podium">
                            <div 
                                v-for="(p, i) in sortedPodium" 
                                :key="p.rank" 
                                class="podium-block"
                                :class="[`podium-${p.rank}`, { 'is-me': p.is_me }]"
                            >
                                <div class="podium-medal">
                                    {{ p.rank === 1 ? '👑' : p.rank === 2 ? '🥈' : '🥉' }}
                                </div>
                                <div class="podium-name">{{ p.player_name }}</div>
                                <div class="podium-info">Lvl {{ p.level }}</div>
                                <div class="podium-bar" :style="{ height: podiumHeight(p.rank) }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- History Chart -->
                    <div class="history-section" v-if="weeklyHistory.length > 0">
                        <h3 class="section-title">📊 Dein Rang-Verlauf</h3>
                        <div class="history-chart">
                            <div 
                                v-for="(entry, i) in weeklyHistory" 
                                :key="`${entry.year}-${entry.week}`"
                                class="history-bar"
                            >
                                <div class="bar-rank" :class="getRankClass(entry.rank)">
                                    #{{ entry.rank }}
                                </div>
                                <div 
                                    class="bar-fill"
                                    :style="{ 
                                        height: `${Math.max(10, 100 - (entry.rank * 8))}%`,
                                        background: getRankGradient(entry.rank)
                                    }"
                                ></div>
                                <div class="bar-label">W{{ entry.week }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state">
                        <span class="empty-icon">📅</span>
                        <p>Noch keine Historie vorhanden. Rankings werden jede Woche verarbeitet.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../utils/api';

const emit = defineEmits(['close']);

const props = defineProps({
    inline: { type: Boolean, default: false }
});

const viewMode = ref('live');
const activeTab = ref('composite');
const leaderboard = ref([]);
const myStats = ref(null);
const loading = ref(false);
const totalPlayers = ref(0);
const weeklyHistory = ref([]);
const podium = ref([]);

const tabs = [
    { id: 'composite', label: 'Gesamt', icon: '🏅' },
    { id: 'balance', label: 'Vermögendste', icon: '💰' },
    { id: 'reputation', label: 'Reputation', icon: '⭐' },
    { id: 'global_market_share', label: 'Marktanteil', icon: '📈' },
    { id: 'level', label: 'Ebene', icon: '🎯' }
];

const sortedPodium = computed(() => {
    // Display order: 2nd, 1st, 3rd for visual podium effect
    const sorted = [...podium.value].sort((a, b) => a.rank - b.rank);
    if (sorted.length >= 3) return [sorted[1], sorted[0], sorted[2]];
    return sorted;
});

const getActiveTabLabel = () => {
    switch (activeTab.value) {
        case 'composite': return 'Punktzahl';
        case 'balance': return 'Vermögen';
        case 'reputation': return 'Sterne';
        case 'global_market_share': return 'Marktanteil';
        case 'level': return 'Erfahrung';
        default: return 'Punktzahl';
    }
};

const changeTab = (tabId) => {
    activeTab.value = tabId;
    fetchLeaderboard();
};

const fetchLeaderboard = async () => {
    loading.value = true;
    try {
        const res = await api.get(`/leaderboard?sort_by=${activeTab.value}&limit=50`);
        if (res.success) {
            leaderboard.value = res.leaderboard;
            totalPlayers.value = res.total_players || 0;
        }
    } catch (e) {
        console.error('Failed to load leaderboard', e);
    } finally {
        loading.value = false;
    }
};

const fetchMyStats = async () => {
    try {
        const response = await api.get('/leaderboard/me');
        if (response.success) {
            myStats.value = response;
        }
    } catch (e) {
        console.error('Failed to load my stats', e);
    }
};

const fetchWeeklyHistory = async () => {
    loading.value = true;
    try {
        const res = await api.get('/leaderboard/weekly?weeks=8');
        if (res.success) {
            weeklyHistory.value = res.history || [];
            podium.value = res.podium || [];
        }
    } catch (e) {
        console.error('Failed to load weekly history', e);
    } finally {
        loading.value = false;
    }
};

const formatValue = (val, type) => {
    if (val === undefined || val === null) return '-';
    if (type === 'balance') return '$' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    if (type === 'reputation') return Number(val).toFixed(1);
    if (type === 'global_market_share') return Number(val).toFixed(2) + '%';
    if (type === 'level') return Number(val).toLocaleString() + ' XP';
    if (type === 'composite') return Number(val).toLocaleString();
    return val;
};

const getRankClass = (rank) => {
    if (rank === 1) return 'gold';
    if (rank === 2) return 'silver';
    if (rank === 3) return 'bronze';
    if (rank <= 10) return 'top-ten';
    return 'default';
};

const getRankGradient = (rank) => {
    if (rank === 1) return 'linear-gradient(180deg, #ffd700 0%, #ffaa00 100%)';
    if (rank === 2) return 'linear-gradient(180deg, #e0e0e0 0%, #bdbdbd 100%)';
    if (rank === 3) return 'linear-gradient(180deg, #cd7f32 0%, #8b4513 100%)';
    if (rank <= 10) return 'linear-gradient(180deg, #58a6ff 0%, #1f6feb 100%)';
    return 'linear-gradient(180deg, #484f58 0%, #30363d 100%)';
};

const podiumHeight = (rank) => {
    if (rank === 1) return '120px';
    if (rank === 2) return '90px';
    return '60px';
};

onMounted(() => {
    fetchLeaderboard();
    fetchMyStats();
});
</script>

<style scoped>
.leaderboard-overlay {
    width: 700px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: 16px;
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 85vh;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
}

.leaderboard-overlay.inline-panel {
    width: 100%;
    max-width: none;
    height: auto;
    background: transparent;
    border: none;
    box-shadow: none;
}

.leaderboard-overlay.inline-panel .overlay-body {
    padding: 0;
    height: auto;
    overflow: visible;
}

.leaderboard-overlay.inline-panel .leaderboard-list {
    max-height: 500px;
}

.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 5000;
    display: flex; justify-content: center; align-items: center;
}

.overlay-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, transparent 100%);
}

.header-title { display: flex; align-items: center; gap: 15px; }
.header-title h2 { margin: 0; font-size: 1.4rem; color: #fff; }
.icon { font-size: 1.8rem; }
.player-count { 
    font-size: 0.7rem; background: rgba(88,166,255,0.15); color: #58a6ff; 
    padding: 3px 8px; border-radius: 10px; font-weight: 600; 
}
.close-btn { background: none; border: none; color: #8b949e; font-size: 2rem; cursor: pointer; transition: color 0.2s; }
.close-btn:hover { color: #fff; }

.overlay-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
    padding: 20px;
    gap: 16px;
}

/* ── View Toggle ────────────────────────────── */
.view-toggle {
    display: flex;
    gap: 8px;
    background: rgba(0,0,0,0.35);
    padding: 4px;
    border-radius: 10px;
}

.view-btn {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--color-text-muted);
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.25s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.view-btn:hover { background: rgba(255,255,255,0.05); color: #fff; }
.view-btn.active { 
    background: linear-gradient(135deg, #1f6feb 0%, #58a6ff 100%); 
    color: #fff; 
    box-shadow: 0 4px 15px rgba(31, 111, 235, 0.3); 
}

.pulse-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #3fb950;
    box-shadow: 0 0 6px #3fb950;
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.3); }
}

/* ── Ranking Tabs ────────────────────────────── */
.ranking-tabs {
    display: flex;
    gap: 6px;
    background: rgba(0,0,0,0.25);
    padding: 4px;
    border-radius: 8px;
    overflow-x: auto;
}

.tab-btn {
    flex: 1;
    min-width: 0;
    background: transparent;
    border: none;
    color: var(--color-text-muted);
    padding: 8px 6px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.2s;
    white-space: nowrap;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.tab-icon { font-size: 0.9rem; }
.tab-btn:hover { background: rgba(255,255,255,0.05); color: #fff; }
.tab-btn.active { 
    background: rgba(88, 166, 255, 0.15); 
    color: #58a6ff; 
    border: 1px solid rgba(88, 166, 255, 0.3); 
}

/* ── My Rank Banner ────────────────────────────── */
.my-rank-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(88, 166, 255, 0.08) 0%, rgba(31, 111, 235, 0.05) 100%);
    border: 1px solid rgba(88, 166, 255, 0.15);
    padding: 14px 20px;
    border-radius: 10px;
    gap: 20px;
}

.rank-section { display: flex; align-items: center; gap: 12px; }

.rank-circle {
    width: 50px; height: 50px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.4);
    border: 2px solid rgba(88,166,255,0.3);
}

.rank-circle.gold { border-color: #ffd700; background: rgba(255,215,0,0.1); }
.rank-circle.silver { border-color: #c0c0c0; background: rgba(192,192,192,0.1); }
.rank-circle.bronze { border-color: #cd7f32; background: rgba(205,127,50,0.1); }
.rank-circle.top-ten { border-color: #58a6ff; }

.rank-number { font-size: 1.1rem; font-weight: 800; color: #fff; }
.rank-meta { display: flex; flex-direction: column; gap: 2px; }
.rank-label { font-size: 0.65rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 1px; }
.percentile { font-size: 0.75rem; color: #58a6ff; font-weight: 600; }

.stat-section { display: flex; flex-direction: column; gap: 2px; align-items: flex-end; }
.stat-label { font-size: 0.65rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 600; }
.stat-value { font-size: 1.2rem; font-weight: 700; color: var(--color-success); font-family: 'JetBrains Mono', monospace; }

.badges-section { display: flex; gap: 6px; }
.badge-chip { 
    font-size: 1.2rem; 
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    cursor: help;
}

/* ── Leaderboard Table ────────────────────────────── */
.leaderboard-list-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(0,0,0,0.2);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.05);
    overflow: hidden;
}

.list-header {
    display: grid;
    grid-template-columns: 45px 45px 1fr 70px 50px 110px;
    padding: 10px 15px;
    background: rgba(255,255,255,0.03);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-muted);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    letter-spacing: 0.5px;
}

.leaderboard-list {
    flex: 1;
    overflow-y: auto;
}

.player-row {
    display: grid;
    grid-template-columns: 45px 45px 1fr 70px 50px 110px;
    padding: 10px 15px;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.02);
    transition: all 0.2s;
}

.player-row:hover { background: rgba(255,255,255,0.04); }

.player-row.is-me {
    background: rgba(88, 166, 255, 0.08);
    border-left: 3px solid #58a6ff;
}

.player-row.top-3 {
    background: rgba(255, 215, 0, 0.03);
}

/* Trends */
.col-trend { font-size: 0.7rem; font-weight: 700; text-align: center; }
.trend-up { color: #3fb950; }
.trend-down { color: #f85149; }
.trend-new { color: #d2a8ff; font-size: 0.6rem; font-weight: 800; letter-spacing: 0.5px; }
.trend-same { color: #484f58; }

.col-rank { display: flex; justify-content: center; }
.rank-badge { 
    width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; 
    border-radius: 50%; font-weight: 800; font-size: 0.75rem; color: #8b949e; 
}

.rank-badge.gold { font-size: 1.1rem; }
.rank-badge.silver { font-size: 1.1rem; }
.rank-badge.bronze { font-size: 1.1rem; }
.rank-badge.top-ten { color: #58a6ff; font-weight: 800; }

.player-name { font-weight: 700; color: #e6edf3; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; }
.me-tag { background: #1f6feb; color: #fff; font-size: 0.55rem; padding: 1px 5px; border-radius: 3px; font-weight: 800; letter-spacing: 0.5px; }
.company-name { font-size: 0.7rem; color: #6e7681; margin-top: 1px; }

.col-badges { display: flex; gap: 3px; }
.mini-badge { font-size: 0.85rem; cursor: help; }

.level-badge { background: #21262d; padding: 2px 7px; border-radius: 4px; font-size: 0.75rem; color: #c9d1d9; border: 1px solid #30363d; font-weight: 600; }

.text-right { text-align: right; }
.col-score { font-family: 'JetBrains Mono', monospace; color: var(--color-success); font-weight: 600; font-size: 0.8rem; }

/* ── Podium ────────────────────────────── */
.section-title {
    font-size: 1rem;
    color: #e6edf3;
    margin: 0 0 15px 0;
}

.podium {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 20px;
    padding-bottom: 10px;
}

.podium-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 100px;
}

.podium-block.is-me { filter: drop-shadow(0 0 8px rgba(88, 166, 255, 0.4)); }

.podium-medal { font-size: 2rem; }
.podium-name { font-weight: 700; color: #e6edf3; font-size: 0.85rem; text-align: center; }
.podium-info { font-size: 0.7rem; color: #8b949e; }

.podium-bar {
    width: 80px;
    border-radius: 8px 8px 0 0;
    transition: height 0.5s ease;
}

.podium-1 .podium-bar { background: linear-gradient(180deg, #ffd700 0%, #b8860b 100%); }
.podium-2 .podium-bar { background: linear-gradient(180deg, #e0e0e0 0%, #9e9e9e 100%); }
.podium-3 .podium-bar { background: linear-gradient(180deg, #cd7f32 0%, #8b4513 100%); }

/* ── History Chart ────────────────────────────── */
.history-chart {
    display: flex;
    gap: 8px;
    align-items: flex-end;
    height: 150px;
    padding: 10px;
    background: rgba(0,0,0,0.2);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.05);
}

.history-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    height: 100%;
    justify-content: flex-end;
}

.bar-rank {
    font-size: 0.7rem;
    font-weight: 800;
    color: #c9d1d9;
}

.bar-rank.gold { color: #ffd700; }
.bar-rank.silver { color: #c0c0c0; }
.bar-rank.bronze { color: #cd7f32; }
.bar-rank.top-ten { color: #58a6ff; }

.bar-fill {
    width: 100%;
    border-radius: 4px 4px 0 0;
    min-height: 10px;
    transition: height 0.5s ease;
}

.bar-label {
    font-size: 0.65rem;
    color: #6e7681;
    font-weight: 600;
}

/* ── States ────────────────────────────── */
.loading-state, .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #8b949e;
    gap: 15px;
    padding: 40px;
}

.empty-icon { font-size: 2.5rem; }

.spinner {
    width: 30px; height: 30px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: #58a6ff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* ── Animations ────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }

.list-enter-active { transition: all 0.3s ease; }
.list-leave-active { transition: all 0.2s ease; }
.list-enter-from { opacity: 0; transform: translateX(-15px); }
.list-leave-to { opacity: 0; transform: translateX(15px); }

/* ── Responsive ────────────────────────────── */
@media (max-width: 640px) {
    .leaderboard-overlay { width: 100%; border-radius: 12px 12px 0 0; height: 90vh; }
    .list-header, .player-row { grid-template-columns: 35px 35px 1fr 45px 90px; }
    .col-badges { display: none; }
    .ranking-tabs { overflow-x: auto; }
    .tab-btn { font-size: 0.7rem; padding: 6px 4px; }
    .tab-icon { display: none; }
    .my-rank-banner { flex-wrap: wrap; }
}
</style>
