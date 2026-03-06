<template>
    <div class="control-center" ref="menuContainer">
        <button 
            class="menu-trigger" 
            @click="toggleMenu" 
            :class="{ active: isOpen }"
        >
            <div class="trigger-meta">
                <span class="meta-label">SYSTEM</span>
                <span class="meta-val">CONTROL_CENTER</span>
            </div>
            <span class="trigger-chevron">▼</span>
        </button>

        <transition name="panel-drop">
            <div v-if="isOpen" class="control-panel shadow-2xl" v-click-outside="close">
                <div class="panel-header">
                    <div class="header-main">
                        <span class="panel-label">INTERFACE</span>
                        <h4 class="panel-title">OPERATIONS_HUB</h4>
                    </div>
                </div>

                <div class="control-grid">
                    <button
                        v-for="action in actions"
                        :key="action.id"
                        class="control-action"
                        @click="handleAction(action.id)"
                    >
                        <span class="action-icon">{{ action.icon }}</span>
                        <div class="action-info">
                            <span class="action-label">{{ action.label }}</span>
                            <span class="action-desc">{{ action.desc }}</span>
                        </div>
                    </button>
                </div>

                <div class="panel-footer">
                    <div class="footer-stat">
                        <span class="f-label">AUTH_LEVEL</span>
                        <span class="f-val">ADMIN_01</span>
                    </div>
                    <div class="footer-stat">
                        <span class="f-label">SYS_TIME</span>
                        <span class="f-val">{{ currentTime }}</span>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isOpen = ref(false);
const currentTime = ref(new Date().toLocaleTimeString());

const emit = defineEmits([
    'openNocWall',
    'openMarketing', 
    'openCustomers', 
    'openEmployees', 
    'openFinance', 
    'openLeaderboard', 
    'openSettings',
    'openAnalytics',
    'openAchievements',
    'openRoadmap'
]);

const toggleMenu = () => {
    isOpen.value = !isOpen.value;
};

const close = () => {
    isOpen.value = false;
};

const handleAction = (actionId) => {
    emit(actionId);
    isOpen.value = false;
};

const actions = [
    { id: 'openNocWall', label: 'NOC_WALL', icon: '📺', desc: 'Full-screen Ops View' },
    { id: 'openMarketing', label: 'MARKETING', icon: '📢', desc: 'Campaign Management' },
    { id: 'openCustomers', label: 'CUSTOMERS', icon: '👥', desc: 'SLA & Entity Metrics' },
    { id: 'openEmployees', label: 'WORKFORCE', icon: '👔', desc: 'Human Capital' },
    { id: 'openFinance', label: 'FINANCE', icon: '💰', desc: 'Fiscal Oversight' },
    { id: 'openLeaderboard', label: 'ANALYTICS', icon: '📈', desc: 'Market Position' },
    { id: 'openReplay', label: 'TIMELINE', icon: '⏮️', desc: 'Historical Replay' },
    { id: 'openSettings', label: 'SETTINGS', icon: '⚙️', desc: 'Core Config' },
];

let timer;
onMounted(() => {
    timer = setInterval(() => {
        currentTime.value = new Date().toLocaleTimeString();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>

<style scoped>
.control-center {
    position: relative;
    user-select: none;
}

.menu-trigger {
    height: 40px;
    padding: 0 16px;
    background: var(--color-elevated);
    border: var(--border-ui);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s;
}

.menu-trigger:hover { background: rgba(255,255,255,0.02); border-color: #fff; }
.menu-trigger.active { background: var(--color-accent); color: #fff; border-color: var(--color-accent); }

.trigger-meta { display: flex; flex-direction: column; align-items: flex-start; }
.meta-label { font-size: 0.5rem; font-weight: 800; color: var(--color-muted); opacity: 0.8; }
.meta-val { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; }
.active .meta-label, .active .meta-val { color: #fff; opacity: 1; }

.trigger-chevron { font-size: 0.6rem; opacity: 0.5; }

.control-panel {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: 300px;
    background: var(--color-surface);
    border: var(--border-ui);
    z-index: 1000;
}

.panel-header {
    padding: 16px;
    border-bottom: var(--border-dim);
}

.header-main { display: flex; flex-direction: column; }
.panel-label { font-size: 0.55rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.1em; }
.panel-title { font-size: 0.9rem; font-weight: 800; color: #fff; }

.control-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1px;
    background: var(--border-dim);
}

.control-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--color-surface);
    transition: all 0.2s;
}

.control-action:hover { background: rgba(255,255,255,0.03); }

.action-icon { font-size: 1.1rem; filter: grayscale(1); }
.control-action:hover .action-icon { filter: grayscale(0); }

.action-info { display: flex; flex-direction: column; align-items: flex-start; }
.action-label { font-size: 0.75rem; font-weight: 800; color: #fff; }
.action-desc { font-size: 0.6rem; color: var(--color-muted); }

.panel-footer {
    padding: 12px 16px;
    background: var(--color-bg-deep);
    border-top: var(--border-dim);
    display: flex;
    justify-content: space-between;
}

.footer-stat { display: flex; flex-direction: column; }
.f-label { font-size: 0.5rem; font-weight: 800; color: var(--color-muted); }
.f-val { font-size: 0.6rem; font-family: var(--font-mono); color: var(--color-success); }

.panel-drop-enter-active, .panel-drop-leave-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.panel-drop-enter-from, .panel-drop-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
