<template>
    <div id="game-root">
        <!-- Loading Overlay (Higher z-index) -->
        <LoadingScreen v-if="isLoading" />

        <!-- Auth Screen -->
        <AuthScreen v-if="!isAuthenticated && !isLoading" />

        <!-- Main Game (Persist in background during action loading) -->
        <GameContainer v-if="isAuthenticated" />

        <!-- Toast Notifications -->
        <ToastContainer />
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from './stores/auth';
import { useGameStore } from './stores/game';
import LoadingScreen from './components/LoadingScreen.vue';
import AuthScreen from './components/AuthScreen.vue';
import GameContainer from './components/Game/GameContainer.vue';
import ToastContainer from './components/ToastContainer.vue';

const authStore = useAuthStore();
const gameStore = useGameStore();

const isLoading = computed(() => authStore.isLoading || gameStore.isLoading);
const isAuthenticated = computed(() => authStore.isAuthenticated);

onMounted(async () => {
    // Check for existing token
    await authStore.checkAuth();
    
    // If authenticated, load game state
    if (authStore.isAuthenticated) {
        await gameStore.loadGameState();
    }
});
</script>

<style scoped>
#game-root {
    width: 100vw;
    height: 100vh;
    overflow: hidden;
}
</style>
