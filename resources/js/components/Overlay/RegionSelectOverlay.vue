<template>
    <div class="overlay-backdrop">
        <div class="overlay-modal">
            <div class="modal-header">
                <h2>Region Auswählen</h2>
                <div class="room-type-badge">{{ roomTypeLabel }}</div>
                <button class="close-btn" @click="$emit('close')">×</button>
            </div>

            <div class="modal-content">
                <p class="helper-text">Wähle einen globalen Standort für dein neues Rechenzentrum. Regionen beeinflussen Stromkosten, Latenz, Wetter und regionale Ereignisse.</p>

                <div class="region-grid" v-if="!loading">
                    <div 
                        v-for="(region, key) in regions" 
                        :key="key"
                        class="region-card"
                        :class="{ 
                            'selected': selectedRegion === key,
                            'locked': playerLevel < (region.level_required || 1)
                        }"
                        @click="selectRegion(key)"
                    >
                        <div class="region-header">
                            <span class="region-flag">{{ region.flag }}</span>
                            <div class="region-header-meta">
                                <span class="region-name">{{ region.name }}</span>
                                <span class="region-slug">{{ key.toUpperCase() }}</span>
                            </div>
                            <span v-if="(region.level_required || 1) > 1" class="level-req-badge"
                                  :class="{ 'met': playerLevel >= region.level_required }">
                                {{ playerLevel >= region.level_required ? '✓' : '🔒' }} LVL {{ region.level_required }}
                            </span>
                        </div>

                        <!-- Live Telemetrie -->
                        <div class="live-strip">
                            <div class="live-item">
                                <span class="live-label">⚡ SPOT</span>
                                <span class="live-val" :class="getPriceClass(key)">${{ getSpotPrice(key) }}</span>
                            </div>
                            <div class="live-item">
                                <span class="live-label">🌤️ WETTER</span>
                                <span class="live-val">{{ getWeatherIcon(key) }} {{ getWeatherLabel(key) }}</span>
                            </div>
                            <div class="live-item">
                                <span class="live-label">☀️ SOLAR</span>
                                <span class="live-val">{{ getSolarPct(key) }}%</span>
                                <div class="mini-bar"><div class="mini-fill" :style="{ width: getSolarPct(key) + '%' }"></div></div>
                            </div>
                        </div>

                        <!-- Wetter-Effekt -->
                        <div class="weather-fx" v-if="getWeatherEffect(key)">
                            {{ getWeatherEffectIcon(key) }} {{ getWeatherEffect(key) }}
                        </div>
                        
                        <div class="region-stats">
                            <div class="stat-row">
                                <span class="stat-label">BASIS_PREIS</span>
                                <span class="stat-value" :class="getCostColor(region.base_power_cost)">
                                    ${{ region.base_power_cost }}/kWh
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">LATENZ</span>
                                <span class="stat-value">
                                    {{ (50 * (region.latency_modifier || 1)).toFixed(0) }}ms
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">STEUER</span>
                                <span class="stat-value">{{ Math.round((region.tax_rate || 0) * 100) }}%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">CO₂_ABGABE</span>
                                <span class="stat-value">${{ region.carbon_tax_per_kw || 0 }}/kW</span>
                            </div>
                        </div>

                        <div class="region-desc">{{ region.description }}</div>

                        <!-- Markt-Tags -->
                        <div class="market-tags">
                            <span v-if="region.preferences?.is_privacy_focused" class="tag tag-blue">🛡️ DSGVO</span>
                            <span v-if="region.preferences?.is_performance_focused" class="tag tag-orange">⚡ PERF</span>
                            <span v-if="region.preferences?.is_eco_focused" class="tag tag-green">🌿 ÖKO</span>
                            <span v-if="getActiveEventsCount(key) > 0" class="tag tag-yellow">📡 {{ getActiveEventsCount(key) }} Events</span>
                        </div>

                        <div class="selection-indicator" v-if="selectedRegion === key">
                            ✓ AUSGEWÄHLT
                        </div>

                        <div class="lock-overlay" v-if="playerLevel < (region.level_required || 1)">
                            <span>🔒 Level {{ region.level_required }} erforderlich</span>
                        </div>
                    </div>
                </div>

                <div v-else class="loading">Regionen werden geladen...</div>
            </div>

            <div class="modal-footer">
                <div class="cost-summary">
                    <span>FREISCHALT-KOSTEN:</span>
                    <span class="cost-value">${{ formatMoney(roomCost) }}</span>
                </div>
                <button 
                    class="confirm-btn" 
                    :disabled="!selectedRegion || processing || isLocked(selectedRegion)"
                    @click="confirmPurchase"
                >
                    {{ processing ? 'Deploying...' : '🚀 Rechenzentrum Deployen' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const props = defineProps({
    roomType: { type: String, required: true },
    roomCost: { type: Number, required: true }
});

const emit = defineEmits(['close', 'purchased']);

const gameStore = useGameStore();
const regions = ref({});
const loading = ref(true);
const selectedRegion = ref('us_east');
const processing = ref(false);
const error = ref(null);

const playerLevel = computed(() => gameStore.player.economy.level);

const roomTypeLabel = computed(() => {
    return props.roomType.replace('_', ' ').toUpperCase();
});

onMounted(async () => {
    try {
        const res = await api.get('/rooms');
        if (res.success && res.regions) {
            regions.value = res.regions;
        }
    } catch (e) {
        console.error("Failed to load regions", e);
    } finally {
        loading.value = false;
    }
});

const selectRegion = (key) => {
    if (isLocked(key)) return;
    selectedRegion.value = key;
};

const isLocked = (key) => {
    const r = regions.value[key];
    if (!r) return true;
    return playerLevel.value < (r.level_required || 1);
};

const getCostColor = (cost) => {
    if (cost < 0.10) return 'text-success';
    if (cost > 0.20) return 'text-danger';
    return 'text-warning';
};

// ── Live Data Helpers ─────────────────────────

const getSpotPrice = (key) => {
    const prices = gameStore.energy?.regional_prices || {};
    return (prices[key] || gameStore.energy?.spotPrice || 0.12).toFixed(4);
};

const getPriceClass = (key) => {
    const p = parseFloat(getSpotPrice(key));
    if (p > 0.30) return 'price-crit';
    if (p > 0.18) return 'price-high';
    if (p < 0.08) return 'price-low';
    return '';
};

const weatherMap = {
    clear:    { icon: '☀️', label: 'Klar',       effect: null,                                    effectIcon: null },
    cloudy:   { icon: '☁️', label: 'Bewölkt',    effect: 'Solar -60%',                            effectIcon: '🌥️' },
    heatwave: { icon: '🔥', label: 'Hitzewelle', effect: 'Solar +20%, Kühlung +35%',              effectIcon: '⚠️' },
    storm:    { icon: '⛈️', label: 'Sturm',      effect: 'Solar -90%, Netz -30%',                 effectIcon: '🚨' },
    blizzard: { icon: '❄️', label: 'Blizzard',   effect: 'Solar -95%, Kühlung -20%, Netz -40%',   effectIcon: '🚨' },
};

const getWeatherType = (key) => gameStore.weather?.[key]?.type || 'clear';
const getWeatherIcon = (key) => weatherMap[getWeatherType(key)]?.icon || '☀️';
const getWeatherLabel = (key) => weatherMap[getWeatherType(key)]?.label || 'Unbekannt';
const getWeatherEffect = (key) => weatherMap[getWeatherType(key)]?.effect || null;
const getWeatherEffectIcon = (key) => weatherMap[getWeatherType(key)]?.effectIcon || 'ℹ️';

const getSolarPct = (key) => {
    const solar = gameStore.energy?.regional_solar || {};
    return Math.round((solar[key] || 0) * 100);
};

const getActiveEventsCount = (key) => {
    const events = gameStore.worldEvents?.active || [];
    return events.filter(ev => {
        if (!ev.affected_regions || ev.affected_regions.length === 0) return true; // global
        return ev.affected_regions.includes(key);
    }).length;
};

const confirmPurchase = async () => {
    if (!selectedRegion.value) return;
    
    processing.value = true;
    try {
        const result = await gameStore.purchaseRoom(props.roomType, { region: selectedRegion.value });
        if (result) {
            emit('purchased');
            emit('close');
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        processing.value = false;
    }
};

const formatMoney = (val) => val.toLocaleString();

</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.88);
    backdrop-filter: blur(10px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.overlay-modal {
    width: 1100px;
    max-width: 94vw;
    height: 85vh;
    display: flex;
    flex-direction: column;
    background: var(--v3-bg-base);
    border: var(--v3-border-heavy);
    border-radius: var(--v3-radius);
    box-shadow: 0 50px 100px rgba(0,0,0,0.6);
    overflow: hidden;
}

.modal-header {
    padding: 20px 32px;
    background: rgba(0,0,0,0.25);
    border-bottom: var(--v3-border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 0.8rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header h2::before {
    content: '';
    width: 3px;
    height: 12px;
    background: var(--v3-accent);
}

.room-type-badge {
    background: rgba(255,255,255,0.03);
    color: var(--v3-text-ghost);
    font-weight: 800;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 0.55rem;
    letter-spacing: 0.1em;
    border: var(--v3-border-soft);
    text-transform: uppercase;
}

.close-btn {
    font-size: 1.5rem;
    color: var(--v3-text-ghost);
    background: transparent;
    border: none;
    cursor: pointer;
}
.close-btn:hover { color: #fff; }

.modal-content {
    flex: 1;
    padding: 24px 32px;
    overflow-y: auto;
}

.helper-text {
    font-size: 0.63rem;
    color: var(--v3-text-secondary);
    margin-bottom: 24px;
    line-height: 1.6;
    max-width: 700px;
}

.region-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
}

.region-card {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    padding: 18px;
    cursor: pointer;
    position: relative;
    transition: all 0.15s ease;
    overflow: hidden;
    border-radius: var(--v3-radius);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.region-card:hover:not(.locked) {
    border-color: var(--v3-text-ghost);
    background: var(--v3-bg-overlay);
    transform: translateY(-1px);
}

.region-card.selected {
    border-color: var(--v3-accent);
    background: var(--v3-accent-soft);
    box-shadow: 0 0 20px rgba(56, 97, 251, 0.1);
}

.region-card.locked {
    opacity: 0.35;
    filter: grayscale(1);
    cursor: not-allowed;
}

.region-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.region-header-meta {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.region-flag { font-size: 1.1rem; }
.region-name { font-weight: 800; font-size: 0.72rem; color: #fff; letter-spacing: 0.03em; }
.region-slug { font-size: 0.5rem; font-weight: 700; color: rgba(255,255,255,0.25); font-family: var(--font-family-mono); }

.level-req-badge {
    margin-left: auto;
    font-size: 0.55rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 3px;
    background: rgba(255,255,255,0.06);
    color: #d29922;
    border: 1px solid rgba(210,169,34,0.15);
}
.level-req-badge.met {
    color: #3fb950;
    border-color: rgba(63,185,80,0.2);
}

/* ── Live Telemetry Strip ────── */
.live-strip {
    display: flex;
    gap: 12px;
    padding: 8px 10px;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 4px;
}

.live-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.live-label {
    font-size: 0.48rem;
    font-weight: 800;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.live-val {
    font-size: 0.72rem;
    font-weight: 700;
    font-family: var(--font-family-mono);
    color: #e6edf3;
}

.price-low { color: #3fb950; }
.price-high { color: #d29922; }
.price-crit { color: #f85149; text-shadow: 0 0 4px rgba(248,81,73,0.3); }

.mini-bar {
    height: 2px;
    background: rgba(255,255,255,0.06);
    border-radius: 1px;
    overflow: hidden;
    margin-top: 2px;
}

.mini-fill {
    height: 100%;
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
    border-radius: 1px;
    transition: width 0.4s ease;
}

/* ── Weather Effect ──── */
.weather-fx {
    font-size: 0.58rem;
    font-weight: 700;
    color: #d29922;
    padding: 4px 8px;
    background: rgba(210,169,34,0.06);
    border: 1px solid rgba(210,169,34,0.12);
    border-radius: 3px;
    font-family: var(--font-family-mono);
}

.region-stats {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.58rem;
}

.stat-label { color: var(--v3-text-ghost); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-family: var(--font-family-mono); font-weight: 800; color: #fff; }

.text-success { color: var(--v3-success); }
.text-warning { color: var(--v3-warning); }
.text-danger { color: var(--v3-danger); }

.region-desc {
    font-size: 0.6rem;
    color: var(--v3-text-secondary);
    line-height: 1.5;
    padding-top: 10px;
    border-top: var(--v3-border-soft);
}

/* ── Market Tags ──── */
.market-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.tag {
    font-size: 0.48rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.tag-blue  { background: rgba(56,97,251,0.15); color: #58a6ff; }
.tag-orange { background: rgba(249,115,22,0.15); color: #f97316; }
.tag-green { background: rgba(63,185,80,0.12); color: #3fb950; }
.tag-yellow { background: rgba(210,169,34,0.12); color: #d29922; }

.selection-indicator {
    position: absolute;
    top: 10px; right: 10px;
    background: var(--v3-accent);
    color: #fff;
    font-weight: 900;
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.lock-overlay {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    color: #fff;
    font-weight: 800;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    backdrop-filter: blur(2px);
}

.modal-footer {
    padding: 18px 32px;
    background: rgba(0,0,0,0.25);
    border-top: var(--v3-border-soft);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 28px;
}

.cost-summary {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.cost-summary span:first-child {
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.cost-value {
    color: var(--v3-success);
    font-weight: 900;
    font-family: var(--font-family-mono);
    font-size: 1rem;
}

.confirm-btn {
    background: var(--v3-accent);
    color: #fff;
    border: none;
    padding: 12px 28px;
    border-radius: var(--v3-radius);
    font-weight: 900;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    cursor: pointer;
    transition: all 0.15s ease;
}

.confirm-btn:hover:not(:disabled) {
    background: #477fff;
    box-shadow: 0 10px 30px var(--v3-accent-glow);
    transform: translateY(-2px);
}

.confirm-btn:disabled {
    opacity: 0.2;
    cursor: not-allowed;
}

.loading {
    text-align: center;
    padding: 60px;
    color: var(--v3-text-ghost);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}
</style>
