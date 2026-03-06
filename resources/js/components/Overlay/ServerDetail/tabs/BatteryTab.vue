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

        <ConfirmationModal :show="showRefurbishConfirm" title="ZELLEN_REFURBISHMENT_ORDER"
            message="Sollen die Batteriezellen für $1,200 generalüberholt werden?"
            warning="Dieser chemische Prozess setzt den Gesundheitszustand (SoH) auf 100% zurück."
            confirm-label="REFURBISH_STARTEN" type="info" @confirm="executeRefurbish"
            @cancel="showRefurbishConfirm = false" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';
import ConfirmationModal from '../../../UI/ConfirmationModal.vue';

const showRefurbishConfirm = ref(false);

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

function handleRefurbish() {
    if (props.processing || props.server.health > 95) return;
    showRefurbishConfirm.value = true;
}

async function executeRefurbish() {
    showRefurbishConfirm.value = false;
    emit('processing-start');
    try {
        const res = await api.post(`/server/${props.server.id}/battery/refurbish`);
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
}
</script>
