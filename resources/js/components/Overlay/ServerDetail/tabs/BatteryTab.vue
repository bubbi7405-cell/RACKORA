<template>
    <div class="battery-tab-v3 glass-panel">
        <div class="battery-hero">
            <div class="battery-visual">
                <svg viewBox="0 0 100 200" class="battery-svg">
                    <defs>
                        <linearGradient id="batGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fbbf24" />
                            <stop offset="100%" stop-color="#d97706" />
                        </linearGradient>
                        <filter id="batGlow">
                            <feGaussianBlur stdDeviation="2" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>
                    <!-- Body -->
                    <rect x="10" y="20" width="80" height="170" rx="8" fill="rgba(0,0,0,0.4)"
                        stroke="rgba(255,255,255,0.1)" stroke-width="2" />
                    <!-- Tip -->
                    <rect x="35" y="10" width="30" height="10" rx="2" fill="rgba(255,255,255,0.2)" />
                    <!-- Fill -->
                    <rect x="15" :y="185 - (160 * ((server.battery?.percent || 0) / 100))" width="70"
                        :height="160 * ((server.battery?.percent || 0) / 100)" rx="4" fill="url(#batGradient)"
                        filter="url(#batGlow)" style="transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1)" />
                </svg>
                <div class="charge-percentage">
                    <span class="val">{{ Math.round(server.battery?.percent || 0) }}</span>
                    <span class="unit">%</span>
                </div>
            </div>

            <div class="battery-meta">
                <div class="meta-row main-stat">
                    <label>AKTUELLER STAND</label>
                    <div class="value">
                        <strong>{{ server.battery?.level?.toFixed(2) }}</strong>
                        <small>kWh</small>
                    </div>
                </div>
                <div class="meta-row">
                    <label>GESAMTKAPAZITÄT</label>
                    <div class="value">
                        <strong>{{ server.battery?.capacity?.toFixed(1) }}</strong>
                        <small>kWh</small>
                    </div>
                </div>
                <div class="meta-row">
                    <label>ZUSTAND (SoH)</label>
                    <div class="value" :class="getHealthClass(server.health)">
                        <strong>{{ Math.round(server.health) }}</strong>
                        <small>%</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="battery-details-grid">
            <div class="detail-card">
                <div class="card-icon">⚡</div>
                <div class="card-content">
                    <label>ENTLADUNGSRATE (MAX)</label>
                    <div class="val">{{ ((server.battery?.capacity || 0) * 2).toFixed(1) }} kW</div>
                    <div class="desc">Unterstützt bis zu {{ Math.floor((server.battery?.capacity || 0) * 200) }}
                        durchschnittliche Server.</div>
                </div>
            </div>
            <div class="detail-card">
                <div class="card-icon">🔌</div>
                <div class="card-content">
                    <label>LADERATE (MAX)</label>
                    <div class="val">{{ ((server.battery?.capacity || 0) * 1).toFixed(1) }} kW</div>
                    <div class="desc">Volle Aufladung in ca. 60 Minuten bei vollem Durchsatz.</div>
                </div>
            </div>
            <div class="detail-card">
                <div class="card-icon">🛡️</div>
                <div class="card-content">
                    <label>USV PRIORITÄT</label>
                    <div class="val">KRITISCH</div>
                    <div class="desc">Übernimmt automatisch bei Netzausfall in {{ server.rack?.room?.name ||
                        'Rechenzentrum' }}.</div>
                </div>
            </div>
        </div>

        <div class="battery-actions">
            <button class="btn-refurbish" @click="handleRefurbish" :disabled="processing || server.health > 95">
                💎 ZELLEN REFURBISHMENT ($1,200)
            </button>
            <p class="action-note">Erhöht den Gesundheitszustand (SoH) auf 100% durch Austausch chemischer Komponenten.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const gameStore = useGameStore();

function getHealthClass(health) {
    if (health > 80) return 'health-good';
    if (health > 40) return 'health-warning';
    return 'health-danger';
}

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

async function handleRefurbish() {
    if (props.processing || props.server.health > 95) return;
    if (!confirm('Sollen die Batteriezellen für $1,200 generalüberholt werden?')) return;

    emit('processing-start');
    try {
        const res = await api.post(`/server/${props.server.id}/battery/refurbish`);
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } catch (e) {
        console.error(e);
    } finally {
        emit('processing-end');
    }
}
</script>

<style scoped>
.battery-tab-v3 {
    padding: 30px;
}

.battery-hero {
    display: flex;
    gap: 40px;
    align-items: center;
    margin-bottom: 40px;
}

.battery-visual {
    position: relative;
    width: 120px;
    flex-shrink: 0;
}

.charge-percentage {
    position: absolute;
    top: 55%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    width: 100%;
    pointer-events: none;
}

.charge-percentage .val {
    display: block;
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1;
    color: #fff;
    font-family: var(--font-family-mono);
    text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
}

.charge-percentage .unit {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
}

.battery-meta {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.meta-row {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px 25px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.meta-row.main-stat {
    background: rgba(251, 191, 36, 0.05);
    border-color: rgba(251, 191, 36, 0.1);
}

.meta-row label {
    margin: 0;
}

.meta-row .value {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.meta-row .value strong {
    font-size: 1.4rem;
    color: #fff;
    font-family: var(--font-family-mono);
}

.meta-row .value small {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    font-weight: 700;
}

.health-good strong {
    color: var(--v3-success) !important;
}

.health-warning strong {
    color: var(--v3-warning) !important;
}

.health-danger strong {
    color: var(--v3-danger) !important;
}

.battery-details-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.detail-card {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    display: flex;
    gap: 15px;
}

.card-icon {
    font-size: 1.5rem;
}

.card-content label {
    margin-bottom: 10px;
    font-size: 0.5rem;
}

.card-content .val {
    font-size: 1.1rem;
    font-weight: 900;
    color: #fff;
    font-family: var(--font-family-mono);
    margin-bottom: 5px;
}

.card-content .desc {
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    line-height: 1.4;
}

.battery-actions {
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 30px;
    text-align: center;
}

.btn-refurbish {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    color: #fff;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 900;
    font-size: 0.8rem;
    letter-spacing: 0.05em;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
    transition: all 0.2s;
}

.btn-refurbish:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
}

.btn-refurbish:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    filter: grayscale(1);
}

.action-note {
    margin-top: 15px;
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    font-style: italic;
}
</style>
