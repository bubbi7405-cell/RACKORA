<template>
    <div class="panel-overview">
        <div class="identity-card">
            <div class="banner" @click="$emit('trigger-upload', 'banner')" :style="{ background: profileData?.user.accent_color || 'var(--color-primary)' }">
                <img v-if="profileData?.user.banner" :src="imageUrl(profileData.user.banner)" class="banner-img">
                <div class="banner-edit">
                    <span class="icon">📷</span> Change Cover Image
                </div>
            </div>
            
            <div class="card-details">
                <div class="user-main-info">
                    <div class="avatar-holder" @click="$emit('trigger-upload', 'avatar')" :style="{ backgroundColor: profileData?.user.accent_color || 'var(--color-primary)' }">
                         <img v-if="profileData?.user.avatar" :src="imageUrl(profileData.user.avatar)" class="avatar-img">
                         <span v-else>{{ userInitial }}</span>
                         <div class="avatar-edit">📷</div>
                    </div>
                    <div class="info-text">
                        <h3>{{ profileData?.user.name }}</h3>
                        <p class="company">{{ profileData?.user.company_name }}</p>
                        <div class="badge-row">
                             <span class="rank-badge">RANK {{ profileData?.stats.level }}</span>
                             <span class="status-badge online">SYSTEM ONLINE</span>
                        </div>
                    </div>
                </div>

                <div class="stat-tiles">
                    <div class="stat-tile">
                        <div class="tile-label">REPUTATION</div>
                        <div class="tile-value">{{ profileData?.stats.reputation }}</div>
                        <div class="tile-sub">Market Confidence</div>
                    </div>
                    <div class="stat-tile">
                        <div class="tile-label">JOIN DATE</div>
                        <div class="tile-value">{{ formatDate(profileData?.user.created_at) }}</div>
                        <div class="tile-sub">Tenure</div>
                    </div>
                    <div class="stat-tile">
                        <div class="tile-label">UPTIME</div>
                        <div class="tile-value success">99.98%</div>
                        <div class="tile-sub">SLA Performance</div>
                    </div>
                    <div class="stat-tile">
                        <div class="tile-label">REVENUE</div>
                        <div class="tile-value">${{ formatMoney(profileData?.stats.balance) }}</div>
                        <div class="tile-sub">Current Liquidity</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="experience-section">
            <div class="xp-header">
                <span>XP PROGRESSION</span>
                <span>{{ profileData?.stats.xp }} / {{ profileData?.stats.xp_to_next_level }} XP</span>
            </div>
            <div class="xp-bar-container">
                <div class="xp-bar" :style="{ width: xpPercent + '%' }">
                     <div class="xp-glow"></div>
                </div>
            </div>
        </div>

        <div class="quick-analysis">
             <div class="analysis-card">
                 <h4>Current Focus</h4>
                 <p v-if="profileData?.user.specialization">{{ profileData?.user.specialization.toUpperCase() }} SERVICE PROVIDER</p>
                 <p v-else>GENERAL INFRASTRUCTURE</p>
             </div>
             <div class="analysis-card">
                 <h4>Achievements</h4>
                 <p>{{ profileData?.achievements?.length || 0 }} Milestones Unlocked</p>
             </div>
             <div class="analysis-card">
                 <h4>🔒 Sichere Entsorgung</h4>
                 <p>{{ profileData?.stats.shredCount || 0 }} <span class="sub-info">Einheiten geschreddert</span></p>
                 <span v-if="(profileData?.stats.shredCount || 0) >= 10" class="compliance-badge">GOV_ZERTIFIZIERUNG ✓</span>
                 <span v-else class="compliance-hint">{{ 10 - (profileData?.stats.shredCount || 0) }} weitere für Zertifizierung</span>
             </div>
             <div class="analysis-card">
                 <h4>🏗️ Infrastruktur</h4>
                 <p>{{ profileData?.stats.total_servers || 0 }} <span class="sub-info">aktive Server</span></p>
             </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps(['profileData']);
const emit = defineEmits(['trigger-upload']);

const userInitial = computed(() => props.profileData?.user.name?.charAt(0).toUpperCase() || '?');
const xpPercent = computed(() => {
    if (!props.profileData) return 0;
    const { xp, xp_to_next_level } = props.profileData.stats;
    return Math.min(100, (xp / xp_to_next_level) * 100);
});

const imageUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    const d = new Date(date);
    return isNaN(d.getTime()) ? 'N/A' : d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
};

const formatMoney = (val) => {
    if (val === undefined) return '0';
    return val.toLocaleString();
};
</script>

<style scoped>
.panel-overview {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.identity-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    overflow: hidden;
}

.banner {
    height: 160px;
    width: 100%;
    position: relative;
    cursor: pointer;
    overflow: hidden;
}

.banner-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.banner-edit {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.7);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    opacity: 0;
    transition: 0.3s;
    backdrop-filter: blur(4px);
}

.banner:hover .banner-edit {
    opacity: 1;
}

.card-details {
    padding: 30px;
    position: relative;
}

.user-main-info {
    display: flex;
    gap: 20px;
    margin-top: -60px;
    margin-bottom: 30px;
}

.avatar-holder {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    border: 4px solid #09090b;
    background: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    color: #000;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.avatar-edit {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: 0.2s;
}

.avatar-holder:hover .avatar-edit {
    opacity: 1;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.info-text h3 {
    margin: 35px 0 5px;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.info-text .company {
    color: var(--color-primary);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
}

.badge-row {
    display: flex;
    gap: 10px;
}

.rank-badge {
    background: #18181b;
    border: 1px solid #27272a;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 800;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 800;
}

.status-badge.online {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.stat-tiles {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-tile {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px;
    border-radius: 12px;
}

.tile-label {
    font-size: 0.65rem;
    color: #71717a;
    font-weight: 800;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.tile-value {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.tile-value.success { color: #22c55e; }

.tile-sub {
    font-size: 0.7rem;
    color: #52525b;
}

.experience-section {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 24px;
    border-radius: 16px;
}

.xp-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    font-weight: 800;
    margin-bottom: 12px;
    color: #a1a1aa;
}

.xp-bar-container {
    height: 12px;
    background: #09090b;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #18181b;
}

.xp-bar {
    height: 100%;
    background: linear-gradient(to right, var(--color-primary), #60a5fa);
    position: relative;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.xp-glow {
    position: absolute;
    top: 0; right: 0; bottom: 0;
    width: 20px;
    background: white;
    filter: blur(10px);
    opacity: 0.3;
}

.quick-analysis {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.analysis-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 16px;
}

.analysis-card h4 {
    font-size: 0.75rem;
    color: #71717a;
    margin-bottom: 10px;
    font-weight: 800;
}

.analysis-card p {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
}

.sub-info {
    font-size: 0.7rem;
    color: #71717a;
    font-weight: 500;
}

.compliance-badge {
    display: inline-block;
    margin-top: 8px;
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.2);
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 900;
    letter-spacing: 0.1em;
}

.compliance-hint {
    display: block;
    margin-top: 6px;
    font-size: 0.65rem;
    color: #52525b;
}

@media (max-width: 600px) {
    .stat-tiles {
        grid-template-columns: 1fr 1fr;
    }
    .user-main-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-top: -50px;
    }
    .badge-row {
        justify-content: center;
    }
    .quick-analysis {
        grid-template-columns: 1fr;
    }
}
</style>
