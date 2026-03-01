<template>
    <div v-if="currentWeather" class="v2-stat-item is-weather" v-tooltip="{
        title: 'Region: ' + regionName,
        content: weatherDescription,
        hint: 'Weather affects Solar Yield and Cooling Efficiency (PUE).'
    }">
        <span class="v2-stat-label">ENV_{{ regionCode }}</span>
        <div class="weather-display">
            <span class="weather-icon">{{ weatherIcon }}</span>
            <span class="v2-stat-value">{{ weatherLabel }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useInfrastructureStore } from '../../stores/infrastructure';
import { useUiStore } from '../../stores/ui';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();
const uiStore = useUiStore();

const selectedRoom = computed(() => {
    return infraStore.rooms[uiStore.selectedRoomId] || Object.values(infraStore.rooms)[0];
});

const regionCode = computed(() => selectedRoom.value?.region?.toUpperCase() || 'GLO');

const regionName = computed(() => {
    const reg = gameStore.regions[selectedRoom.value?.region];
    return reg?.name || selectedRoom.value?.region || 'Global';
});

const currentWeather = computed(() => {
    const regionKey = selectedRoom.value?.region;
    return gameStore.weather?.[regionKey] || null;
});

const weatherIcon = computed(() => {
    if (!currentWeather.value) return '❓';
    const type = currentWeather.value.type;
    return {
        'clear': '☀️',
        'cloudy': '☁️',
        'heatwave': '🔥',
        'storm': '⛈️',
        'blizzard': '❄️'
    }[type] || '☀️';
});

const weatherLabel = computed(() => {
    if (!currentWeather.value) return 'UNKNOWN';
    return currentWeather.value.type.toUpperCase();
});

const weatherDescription = computed(() => {
    if (!currentWeather.value) return 'No weather data available.';

    const mods = currentWeather.value.modifiers || {};
    let text = `Current conditions: ${currentWeather.value.type}. `;

    if (mods.solar_mod !== 1) {
        const pct = Math.round(mods.solar_mod * 100);
        text += `Solar: ${pct}%. `;
    }

    if (mods.pue_mod !== 1) {
        const pct = Math.round((mods.pue_mod - 1) * 100);
        const direction = pct > 0 ? 'higher' : 'lower';
        text += `Cooling cost is ${Math.abs(pct)}% ${direction}. `;
    }

    if (mods.grid_stability < 1.0) {
        text += `GRID INSTABILITY detected! Risk of outages. `;
    }

    return text;
});
</script>

<style scoped>
.is-weather {
    cursor: help;
    border-left: 1px solid rgba(255, 255, 255, 0.05);
    padding-left: var(--space-md);
}

.weather-display {
    display: flex;
    align-items: center;
    gap: 6px;
}

.weather-icon {
    font-size: 1.1rem;
    filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.2));
}

.v2-stat-value {
    letter-spacing: 0.05em;
}
</style>
