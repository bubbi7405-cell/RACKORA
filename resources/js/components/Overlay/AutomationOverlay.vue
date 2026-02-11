<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="automation-overlay glass-panel">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">🤖</span>
                    <h2>Automation & Scripting</h2>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>
            
            <div class="overlay-body">
                <div class="automation-grid">
                    <!-- Auto Reboot -->
                    <div class="automation-card" :class="{ active: settings.auto_reboot }">
                        <div class="card-content">
                            <div class="card-icon">⚡</div>
                            <div class="card-text">
                                <h3>Auto-Reboot</h3>
                                <p>Monitors servers and automatically reboots them if they go offline while hosting active clients.</p>
                                <span class="status-badge" v-if="settings.auto_reboot">System Active</span>
                            </div>
                        </div>
                        <div class="card-action">
                            <label class="switch">
                                <input type="checkbox" :checked="settings.auto_reboot" @change="toggle('auto_reboot', $event.target.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Auto Provisioning -->
                    <div class="automation-card" :class="{ active: settings.auto_provisioning }">
                        <div class="card-content">
                            <div class="card-icon">📦</div>
                            <div class="card-text">
                                <h3>Smart Provisioning</h3>
                                <p>Automatically scans pending orders and assigns them to the first available server that meets requirements.</p>
                                <span class="status-badge" v-if="settings.auto_provisioning">System Active</span>
                            </div>
                        </div>
                        <div class="card-action">
                            <label class="switch">
                                <input type="checkbox" :checked="settings.auto_provisioning" @change="toggle('auto_provisioning', $event.target.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Future Expansion Placeholder -->
                    <div class="automation-card disabled">
                        <div class="card-content">
                            <div class="card-icon">🧹</div>
                            <div class="card-text">
                                <h3>Auto-Cleanup</h3>
                                <p>Automatically cancels orders that are overdue by more than 12 hours (Coming soon).</p>
                            </div>
                        </div>
                        <div class="card-action">
                            <span class="locked">LOCKED</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overlay-footer">
                <p class="hint">Scripts run once per game minute. Active automation reduces micromanagement but costs nothing extra.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const gameStore = useGameStore();
const settings = computed(() => gameStore.player?.economy?.automation || {});

const toggle = async (key, value) => {
    try {
        const response = await api.post('/automation/toggle', { key, value });
        if (response.success) {
            // Update local state via store if possible, or just wait for next poll
            gameStore.player.economy.automation = response.settings;
        }
    } catch (e) {
        console.error('Failed to toggle automation', e);
    }
};
</script>

<style scoped>
.automation-overlay {
    width: 700px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: 12px;
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 20px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-title h2 { margin: 0; font-size: 1.4rem; }

.overlay-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.automation-grid {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.automation-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.automation-card.active {
    border-color: var(--color-primary);
    background: rgba(var(--color-primary-rgb), 0.05);
}

.automation-card.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.card-content {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.card-icon {
    font-size: 2rem;
    background: rgba(255,255,255,0.05);
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.card-text h3 { margin: 0 0 5px 0; font-size: 1.1rem; }
.card-text p { margin: 0; font-size: 0.9rem; color: var(--color-text-muted); line-height: 1.4; }

.status-badge {
    display: inline-block;
    margin-top: 10px;
    font-size: 0.75rem;
    color: var(--color-success);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Switch UI */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input { opacity: 0; width: 0; height: 0; }

.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #333;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider { background-color: var(--color-primary); }
input:checked + .slider:before { transform: translateX(26px); }

.overlay-footer {
    padding: 15px 20px;
    background: rgba(0,0,0,0.1);
    border-top: 1px solid var(--color-border);
    border-radius: 0 0 12px 12px;
}

.hint { margin: 0; font-size: 0.8rem; color: var(--color-text-muted); font-style: italic; }

.locked {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--color-text-muted);
}
</style>
