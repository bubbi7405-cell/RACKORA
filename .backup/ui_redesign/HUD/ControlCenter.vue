<template>
    <div class="control-center" ref="menuContainer">
        <button 
            class="menu-trigger" 
            @click="toggleMenu" 
            :class="{ active: isOpen }"
            title="Management Center"
        >
            <div class="trigger-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </div>
            <span class="trigger-label">Management</span>
            <span class="trigger-arrow">▼</span>
        </button>

        <transition name="dropdown">
            <div v-if="isOpen" class="menu-dropdown glass">
                <div class="menu-section">
                    <h4 class="menu-title">Business Operations</h4>
                    <div class="menu-grid">
                        <button class="menu-item" @click="handleAction('openMarketing')">
                            <span class="item-icon">📢</span>
                            <div class="item-text">
                                <span class="item-label">Marketing</span>
                                <span class="item-desc">Brand Growth</span>
                            </div>
                        </button>
                        <button class="menu-item" @click="handleAction('openLeaderboard')">
                            <span class="item-icon">🏆</span>
                            <div class="item-text">
                                <span class="item-label">Global Ranks</span>
                                <span class="item-desc">Competition</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="menu-divider"></div>

                <div class="menu-section">
                    <h4 class="menu-title">Strategy & Analysis</h4>
                    <div class="menu-grid">
                        <button class="menu-item" @click="handleAction('openRoadmap')">
                            <span class="item-icon">🗺️</span>
                            <div class="item-text">
                                <span class="item-label">Roadmap</span>
                                <span class="item-desc">Project Goals</span>
                            </div>
                        </button>
                        <button class="menu-item" @click="handleAction('openPerformance')">
                            <span class="item-icon">📊</span>
                            <div class="item-text">
                                <span class="item-label">Analytics</span>
                                <span class="item-desc">KPI Dashboard</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="menu-divider"></div>

                <div class="menu-section">
                    <h4 class="menu-title">Legacy</h4>
                    <div class="menu-grid">
                        <button class="menu-item" @click="handleAction('openAchievements')">
                            <span class="item-icon">📜</span>
                            <div class="item-text">
                                <span class="item-label">Hall of Fame</span>
                                <span class="item-desc">Achievements</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import SoundManager from '../../services/SoundManager';

const emit = defineEmits(['openMarketing', 'openLeaderboard', 'openRoadmap', 'openPerformance', 'openAchievements']);

const isOpen = ref(false);
const menuContainer = ref(null);

const toggleMenu = () => {
    isOpen.value = !isOpen.value;
    SoundManager.playClick();
};

const handleAction = (action) => {
    emit(action);
    isOpen.value = false;
    SoundManager.playClick();
};

const handleClickOutside = (event) => {
    if (menuContainer.value && !menuContainer.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.control-center {
    position: relative;
    z-index: 1000;
}

.menu-trigger {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-xs) var(--space-md);
    background: rgba(33, 38, 45, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    height: 38px;
}

.menu-trigger:hover, .menu-trigger.active {
    background: rgba(33, 38, 45, 0.9);
    border-color: var(--color-primary);
    box-shadow: 0 0 15px rgba(0, 255, 157, 0.15);
}

.trigger-icon {
    width: 18px;
    height: 18px;
    color: var(--color-primary);
}

.trigger-label {
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.trigger-arrow {
    font-size: 0.6rem;
    opacity: 0.5;
    transition: transform 0.2s ease;
}

.menu-trigger.active .trigger-arrow {
    transform: rotate(180deg);
}

.menu-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 320px;
    background: rgba(13, 17, 23, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-lg);
    padding: var(--space-md);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(12px);
}

.menu-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.menu-title {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin: 0 0 4px 4px;
}

.menu-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 4px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-sm);
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateX(4px);
}

.item-icon {
    font-size: 1.25rem;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
}

.item-text {
    display: flex;
    flex-direction: column;
}

.item-label {
    font-size: 0.9rem;
    font-weight: 600;
}

.item-desc {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.menu-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.05);
    margin: var(--space-md) 0;
}

/* Animations */
.dropdown-enter-active, .dropdown-leave-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-enter-from, .dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
</style>
