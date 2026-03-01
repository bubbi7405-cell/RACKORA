<template>
    <div :class="{ 'v2-overlay-backdrop': !inline }" @click.self="!inline && $emit('close')">
        <div :class="inline ? 'compliance-inline-panel' : 'v2-main-viewport compliance-overlay glass-panel'">
            <header class="overlay-header" v-if="!inline">
                <div class="header-title">
                    <span class="icon">🛡️</span>
                    <h2>REGULATORY_AUTHORITY <small>// COMPLIANCE_CENTER</small></h2>
                </div>
                <button @click="$emit('close')" class="close-btn">×</button>
            </header>

            <div class="content-scroll" v-if="!loading">
                <!-- Global Eligibility Alert -->
                <div class="eligibility-banner" :class="eligibilityClass">
                    <div class="banner-icon">
                        <span v-if="eligibilityClass === 'tier-whale'">🐋</span>
                        <span v-else-if="eligibilityClass === 'tier-enterprise'">🏢</span>
                        <span v-else>🏠</span>
                    </div>
                    <div class="banner-content">
                        <span class="banner-label">MARKET_TIER_ELIGIBILITY</span>
                        <span class="banner-status">{{ eligibilityLabel }}</span>
                        <p class="banner-desc">Your certifications and security posture determine which high-value entities are willing to route traffic through your nodes.</p>
                    </div>
                </div>

                <!-- Score Telemetry -->
                <div class="telemetry-grid">
                    <div class="telemetry-card">
                        <div class="tel-header">
                            <span class="tel-label">SECURITY_POSTURE</span>
                            <span class="tel-value" :class="getScoreClass(ecoState.security_score)">{{ (ecoState.security_score || 0).toFixed(1) }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill security" :style="{ width: (ecoState.security_score || 0) + '%' }"></div>
                        </div>
                    </div>
                    <div class="telemetry-card">
                        <div class="tel-header">
                            <span class="tel-label">PRIVACY_INDEX</span>
                            <span class="tel-value" :class="getScoreClass(ecoState.privacy_score)">{{ (ecoState.privacy_score || 0).toFixed(1) }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill privacy" :style="{ width: (ecoState.privacy_score || 0) + '%' }"></div>
                        </div>
                    </div>
                    <div class="telemetry-card">
                        <div class="tel-header">
                            <span class="tel-label">SUSTAINABILITY_INDEX</span>
                            <span class="tel-value" :class="getScoreClass(greenScore)">{{ greenScore.toFixed(1) }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill green" :style="{ width: greenScore + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Certifications Grid -->
                <div class="section-title">ACTIVE_FRAMEWORKS_&_CERTIFICATIONS</div>
                <div class="cert-grid">
                    <div v-for="cert in certificates" 
                            :key="cert.id" 
                            class="cert-card" 
                            :class="{ 'is-certified': cert.isCertified, 'has-audit': cert.activeAudit }">
                        
                        <div class="cert-header">
                            <div class="cert-info">
                                <div class="cert-badge">{{ cert.category }}</div>
                                <div class="cert-name">{{ cert.name }}</div>
                            </div>
                            <div class="status-indicator" :class="{ 'online': cert.isCertified, 'process': cert.activeAudit }">
                                {{ cert.isCertified ? 'ACTIVE' : (cert.activeAudit ? 'AUDITING' : 'PENDING') }}
                            </div>
                        </div>

                        <p class="cert-desc">{{ cert.description }}</p>

                        <div class="req-section">
                            <div class="req-label">VALIDATION_REQUIREMENTS</div>
                            <div class="req-list">
                                <div v-for="(val, type) in cert.requirements" :key="type" class="req-item" :class="{ 'met': isReqMet(type, val) }">
                                    <span class="req-icon">{{ isReqMet(type, val) ? '✓' : '✖' }}</span>
                                    <span>{{ formatRequirement(type, val) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="cert-footer">
                            <template v-if="cert.activeAudit">
                                <div class="audit-progress">
                                    <div class="progress-track sm">
                                        <div class="progress-fill process" :style="{ width: cert.activeAudit.progress + '%' }"></div>
                                    </div>
                                    <div class="audit-meta">
                                        <span>VERIFYING...</span>
                                        <span class="audit-pct">{{ cert.activeAudit.progress }}%</span>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="cert.isCertified">
                                <div class="validity-badge">
                                    <span>VALID_THRU: {{ formatDate(cert.expiresAt) }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <button class="action-btn" 
                                        @click="startAudit(cert.id)"
                                        :disabled="!canStartAudit(cert)">
                                    INITIATE_AUDIT_[$5,000]
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="loading-screen">
                <div class="spinner"></div>
                <div class="loading-text">SYNCHRONIZING_COMPLIANCE_DATABASE...</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';
import { useToastStore } from '../../stores/toast';

const props = defineProps({
    inline: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close']);
const gameStore = useGameStore();
const toast = useToastStore();

const loading = ref(true);
const certificates = ref([]);
const userResearch = ref([]);
const metaEconomy = ref(null);

const ecoState = computed(() => metaEconomy.value?.compliance || gameStore.player?.economy || {});
const greenScore = computed(() => {
    return gameStore.player?.economy?.specializedReputation?.green || 0;
});

const fetchCertificates = async () => {
    try {
        const res = await api.get('/compliance');
        if (res.success) {
            certificates.value = res.data;
            userResearch.value = res.meta.userResearch;
            metaEconomy.value = res.meta.economy;
        }
    } catch (e) {
        console.error("Failed to fetch certificates", e);
    } finally {
        loading.value = false;
    }
};

const startAudit = async (certId) => {
    try {
        const res = await api.post('/compliance/audit', { certificate_id: certId });
        if (res.success) {
            toast.success(res.message);
            fetchCertificates();
            gameStore.loadGameState();
        } else {
            toast.error(res.message);
        }
    } catch (e) {
        toast.error("Failed to start audit.");
    }
};

const isReqMet = (type, val) => {
    switch (type) {
        case 'min_security': return (ecoState.value.security_score || 0) >= val;
        case 'min_privacy': return (ecoState.value.privacy_score || 0) >= val;
        case 'min_shred_count': return (ecoState.value.shred_count || 0) >= val;
        case 'min_green_rep': return greenScore.value >= val;
        case 'min_uptime': return true; // Backend validation
        case 'research': return userResearch.value.includes(val);
    }
    return false;
};

const formatRequirement = (type, val) => {
    switch (type) {
        case 'min_security': return `SEC_INDEX > ${val}%`;
        case 'min_privacy': return `PRIV_INDEX > ${val}%`;
        case 'min_shred_count': return `SHRED_COUNT > ${val}`;
        case 'min_green_rep': return `SUSTAINABILITY > ${val}%`;
        case 'min_uptime': return `UPTIME > ${val}%`;
        case 'research': return `TECH_UNLOCKED: ${val.toUpperCase()}`;
    }
    return `${type}: ${val}`;
};

const canStartAudit = (cert) => {
    const currentBalance = gameStore.player?.economy?.balance || 0;
    if (currentBalance < 5000) return false;
    for (const [type, val] of Object.entries(cert.requirements)) {
        if (!isReqMet(type, val)) return false;
    }
    return true;
};

const getScoreClass = (score) => {
    if (score >= 80) return 'text-success';
    if (score >= 50) return 'text-warning';
    return 'text-danger';
};

const getScoreColor = (score) => {
    if (score >= 80) return 'var(--v2-success)';
    if (score >= 50) return 'var(--v2-warning)';
    return 'var(--v2-danger)';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '----';
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? '----' : d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' [UTC]';
};

const eligibilityLabel = computed(() => {
    const hasSec = certificates.value.some(c => c.isCertified && (c.key === 'iso_27001' || c.key === 'soc2_type1'));
    const hasPriv = certificates.value.some(c => c.isCertified && c.key === 'gdpr_compliance');
    const hasGov = certificates.value.some(c => c.isCertified && c.key === 'gov_grade_destruction');
    
    if (hasGov) return 'DIAMOND_ELITE_AUTHORIZED';
    if (hasSec && hasPriv) return 'WHALE_ENABLED';
    if (hasSec || hasPriv) return 'ENTERPRISE_READY';
    return 'STANDARD_MARKET_ONLY';
});

const eligibilityClass = computed(() => {
    const label = eligibilityLabel.value;
    if (label === 'DIAMOND_ELITE_AUTHORIZED') return 'tier-whale'; // Re-use whale styling
    if (label === 'WHALE_ENABLED') return 'tier-whale';
    if (label === 'ENTERPRISE_READY') return 'tier-enterprise';
    return 'tier-standard';
});

onMounted(fetchCertificates);
</script>

<style scoped>
.compliance-overlay {
    width: 1100px;
    height: 85vh;
    max-width: 95vw;
    background: var(--v3-bg-surface);
    border: var(--v3-border-heavy);
    box-shadow: 0 50px 100px rgba(0,0,0,0.8);
    position: relative;
    border-radius: var(--v3-radius);
    display: flex;
    flex-direction: column;
}

.overlay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: var(--v3-border-soft);
    background: rgba(0,0,0,0.2);
}

.header-title h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-title h2 small { color: var(--v3-text-ghost); font-size: 0.6em; }

.close-btn {
    background: none;
    border: none;
    color: var(--v3-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    transition: color 0.2s;
}
.close-btn:hover { color: #fff; }

.content-scroll { padding: 32px; overflow-y: auto; flex: 1; }

.eligibility-banner {
    display: flex;
    gap: 24px;
    background: rgba(0,0,0,0.3);
    border: var(--v3-border-soft);
    padding: 24px;
    margin-bottom: 40px;
    border-radius: var(--v3-radius);
    position: relative;
    overflow: hidden;
}

.tier-whale { border-left: 4px solid #ffd700; background: linear-gradient(90deg, rgba(255, 215, 0, 0.05), transparent); }
.tier-enterprise { border-left: 4px solid var(--v3-accent); background: linear-gradient(90deg, rgba(47, 107, 255, 0.05), transparent); }
.tier-standard { border-left: 4px solid var(--v3-text-ghost); }

.banner-icon { font-size: 2rem; display: flex; align-items: center; justify-content: center; width: 60px; opacity: 0.8; }
.banner-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }

.banner-label { font-size: 0.6rem; font-weight: 900; color: var(--v3-text-ghost); letter-spacing: 0.2em; margin-bottom: 4px; }
.banner-status { font-size: 1.2rem; font-weight: 900; color: #fff; letter-spacing: 0.05em; font-family: var(--font-family-mono); }
.trip-whale .banner-status { color: #ffd700; text-shadow: 0 0 15px rgba(255, 215, 0, 0.4); }
.banner-desc { opacity: 0.6; font-size: 0.8rem; margin-top: 8px; max-width: 600px; }

/* TELEMETRY */
.telemetry-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 24px;
    margin-bottom: 40px;
}

.telemetry-card {
    background: var(--v3-bg-elevated);
    border: var(--v3-border-soft);
    padding: 24px;
    border-radius: var(--v3-radius);
}

.tel-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px; }
.tel-label { font-size: 0.6rem; font-weight: 800; color: var(--v3-text-secondary); letter-spacing: 0.1em; }
.tel-value { font-size: 1.5rem; font-weight: 800; color: #fff; font-family: var(--font-family-mono); line-height: 1; }

.progress-track { height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden; }
.progress-fill { height: 100%; transition: width 0.6s cubic-bezier(0.2, 0.8, 0.2, 1); }
.progress-fill.security { background: linear-gradient(90deg, var(--v3-accent-dim), var(--v3-accent)); }
.progress-fill.privacy { background: linear-gradient(90deg, #10b981, #34d399); }
.progress-fill.green { background: linear-gradient(90deg, #34d399, #10b981); }

/* CERTS */
.section-title { 
    font-size: 0.7rem; font-weight: 900; color: var(--v3-text-secondary); 
    letter-spacing: 0.2em; margin-bottom: 24px; padding-bottom: 12px; 
    border-bottom: 1px solid rgba(255,255,255,0.05); 
}

.cert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.cert-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 24px;
    border-radius: var(--v3-radius);
    display: flex;
    flex-direction: column;
    min-height: 300px;
    transition: all 0.2s;
}

.cert-card:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); }
.cert-card.is-certified { border-color: var(--v3-success); background: rgba(16, 185, 129, 0.05); }
.cert-card.has-audit { border-color: var(--v3-warning); box-shadow: 0 0 20px rgba(245, 158, 11, 0.1); animation: pulse-border 2s infinite; }

.cert-header { display: flex; justify-content: space-between; margin-bottom: 16px; }
.cert-badge { font-size: 0.5rem; font-weight: 900; color: var(--v3-text-ghost); text-transform: uppercase; background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 2px; width: fit-content; margin-bottom: 4px; }
.cert-name { font-size: 0.9rem; font-weight: 800; color: #fff; letter-spacing: 0.05em; }

.status-indicator { font-size: 0.55rem; font-weight: 900; padding: 4px 8px; border-radius: 2px; background: rgba(255,255,255,0.1); height: fit-content; }
.status-indicator.online { background: rgba(16, 185, 129, 0.2); color: var(--v3-success); }
.status-indicator.process { background: rgba(245, 158, 11, 0.2); color: var(--v3-warning); }

.cert-desc { font-size: 0.75rem; color: var(--v3-text-secondary); line-height: 1.5; margin-bottom: 24px; flex: 1; opacity: 0.8; }

.req-section { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 4px; margin-bottom: 24px; }
.req-label { font-size: 0.5rem; font-weight: 900; color: var(--v3-text-ghost); margin-bottom: 8px; letter-spacing: 0.1em; }
.req-list { display: flex; flex-direction: column; gap: 6px; }
.req-item { display: flex; align-items: center; gap: 8px; font-size: 0.65rem; color: var(--v3-text-ghost); font-family: var(--font-family-mono); }
.req-item.met { color: var(--v3-text-primary); }
.req-item.met .req-icon { color: var(--v3-success); }

.cert-footer { border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px; margin-top: auto; }

.action-btn {
    width: 100%;
    padding: 12px;
    background: transparent;
    border: 1px solid var(--v3-accent);
    color: var(--v3-accent);
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0.1em;
}
.action-btn:hover:not(:disabled) { background: var(--v3-accent); color: #fff; }
.action-btn:disabled { opacity: 0.3; cursor: not-allowed; border-color: var(--v3-text-ghost); color: var(--v3-text-ghost); }

.audit-progress { display: flex; flex-direction: column; gap: 8px; }
.audit-meta { display: flex; justify-content: space-between; font-size: 0.6rem; font-weight: 800; color: var(--v3-warning); }

.validity-badge { text-align: center; color: var(--v3-success); font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; }

@keyframes pulse-border {
    0%, 100% { border-color: var(--v3-warning); box-shadow: 0 0 10px rgba(245, 158, 11, 0.1); }
    50% { border-color: rgba(245, 158, 11, 0.5); box-shadow: 0 0 20px rgba(245, 158, 11, 0.3); }
}

.text-success { color: var(--v3-success); }
.text-warning { color: var(--v3-warning); }
.text-danger { color: var(--v3-danger); }
</style>
