<template>
    <div class="tab-content appearance-tab provision-lab">
        <div class="v3-info-box">
            <label>ASSET_IDENTIFIZIERUNG</label>
            <p class="setting-desc">Vergeben Sie einen eindeutigen Alias für diese Hardware, um sie in Ihrer Infrastruktur leichter zu verwalten.</p>
            <div class="nickname-form">
                <input 
                    type="text" 
                    v-model="localNickname" 
                    class="v3-input" 
                    placeholder="Asset-Name eingeben..."
                    maxlength="50"
                >
                <button 
                    @click="updateAppearance({ nickname: localNickname })" 
                    :disabled="processing || localNickname === server.nickname"
                    class="btn-primary-v3"
                >
                    ALIAS_SPEICHERN
                </button>
            </div>
        </div>

        <div class="v3-info-box" style="margin-top: 25px;">
            <label>LED_KONFIGURATION</label>
            <p class="setting-desc">Modifikation der Chassis-Indikatoren zur visuellen Kategorisierung.</p>
            <div class="color-presets">
                <button 
                    v-for="color in ledPresets" 
                    :key="color.value"
                    class="color-btn"
                    :class="{ active: server.ledColor === color.value }"
                    :style="{ '--preset-color': color.value }"
                    @click="updateAppearance({ led_color: color.value })"
                    :title="color.label"
                >
                    <span class="color-swatch"></span>
                    <span class="color-label">{{ color.label }}</span>
                </button>
                <button class="color-btn btn-reset" @click="updateAppearance({ led_color: null })">
                    DEFAULTS
                </button>
            </div>
        </div>

        <div class="v3-info-box" v-if="server.type === 'gpu_server'" style="margin-top: 25px;">
            <label>RGB_MUSTER (SILIZIUM_FX)</label>
            <p class="setting-desc">Wenden Sie fortgeschrittene Lichteffekte auf das Lüfter-Array an.</p>
            <div class="rgb-presets" v-if="server.customRgb">
                <select v-model="server.customRgb.pattern" @change="updateAppearance({ custom_rgb: server.customRgb })" class="v3-select-sm">
                    <option value="rainbow">Regenbogen-Fluss</option>
                    <option value="pulse">Atmendes Pulsieren</option>
                    <option value="static">Statische Farbe</option>
                    <option value="off">Licht aus</option>
                </select>
            </div>
            <div v-else>
                <button class="btn-primary-v3" @click="updateAppearance({ custom_rgb: { pattern: 'rainbow' } })">
                    RGB_CONTROLLER_INITIALISIEREN
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

const gameStore = useGameStore();

const updateAppearance = async (data) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/appearance`, data);
        if (response.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const localNickname = ref(props.server.nickname || '');

// Sync when external nickname changes
watch(() => props.server.nickname, (val) => {
    if (val !== undefined) localNickname.value = val || '';
});

const ledPresets = [
    { label: 'Green', value: '#2ecc71' },
    { label: 'Blue', value: '#3498db' },
    { label: 'Purple', value: '#9b59b6' },
    { label: 'Amber', value: '#f1c40f' },
    { label: 'Ice', value: '#00f2ff' },
    { label: 'Red', value: '#e74c3c' }
];
</script>
