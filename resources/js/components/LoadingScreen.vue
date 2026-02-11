<template>
    <div class="loading-screen">
        <div class="loading-screen__logo">
            <span class="loading-logo-text">Server</span>
            <span class="loading-logo-accent">Tycoon</span>
        </div>
        <div class="loading-screen__progress">
            <div class="progress-bar">
                <div class="progress-bar__fill" :style="{ width: progress + '%' }"></div>
            </div>
        </div>
        <p class="loading-screen__text">{{ loadingText }}</p>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const progress = ref(0);
const loadingText = ref('Initializing systems...');

const loadingSteps = [
    { progress: 20, text: 'Connecting to server...' },
    { progress: 40, text: 'Loading game data...' },
    { progress: 60, text: 'Initializing renderer...' },
    { progress: 80, text: 'Preparing your data center...' },
    { progress: 100, text: 'Ready!' },
];

onMounted(() => {
    let step = 0;
    const interval = setInterval(() => {
        if (step < loadingSteps.length) {
            progress.value = loadingSteps[step].progress;
            loadingText.value = loadingSteps[step].text;
            step++;
        } else {
            clearInterval(interval);
        }
    }, 300);
});
</script>

<style scoped>
.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(ellipse at center, rgba(0, 212, 255, 0.05) 0%, transparent 50%),
        var(--color-bg-deep);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-screen__logo {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 2rem;
    text-transform: uppercase;
    letter-spacing: 0.2em;
}

.loading-logo-text {
    color: var(--color-text-primary);
}

.loading-logo-accent {
    color: var(--color-primary);
    text-shadow: 0 0 30px var(--color-primary-glow);
}

.loading-screen__progress {
    width: 300px;
    margin-bottom: 1rem;
}

.loading-screen__text {
    color: var(--color-text-muted);
    font-size: 0.875rem;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}
</style>
