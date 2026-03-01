<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="customization-overlay glass-panel animate-fade-in">
            <header class="overlay-header">
                <div class="header-info">
                    <h2>RAUM_KONFIGURATION</h2>
                    <p class="subtitle-desc">Visuelle Anpassung für <strong>{{ room.name }}</strong></p>
                </div>
                <button class="close-button" @click="$emit('close')">&times;</button>
            </header>

            <div class="overlay-body">
                <div class="config-section">
                    <label class="section-label">WANDVERKLEIDUNG</label>
                    <div class="wallpaper-grid">
                        <div 
                            v-for="wp in availableWallpapers" 
                            :key="wp.id"
                            class="wallpaper-card"
                            :class="{ active: currentWallpaper === wp.id }"
                            @click="selectWallpaper(wp.id)"
                        >
                            <div class="wallpaper-preview" :style="{ backgroundColor: wp.bgColor }">
                                <div class="preview-grid" :style="{ borderColor: wp.gridColor }"></div>
                                <div class="wp-check" v-if="currentWallpaper === wp.id">✓</div>
                            </div>
                            <div class="wallpaper-info">
                                <span class="wallpaper-name">{{ wp.name }}</span>
                                <p class="wallpaper-desc">{{ wp.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="config-section">
                    <label class="section-label">RAUM_THEME</label>
                    <p class="section-hint">Verändert die visuelle Atmosphäre und Farb-Signatur der gesamten Anlage.</p>
                    <div class="theme-grid">
                        <div 
                            v-for="th in availableThemes" 
                            :key="th.id"
                            class="theme-card"
                            :class="[th.id, { active: currentTheme === th.id }]"
                            @click="selectTheme(th.id)"
                        >
                            <div class="theme-preview">
                                <div class="preview-color primary"></div>
                                <div class="preview-color secondary"></div>
                            </div>
                            <div class="theme-info">
                                <span class="theme-name">{{ th.name }}</span>
                                <span class="theme-tag">{{ th.tag }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="config-section">
                    <label class="section-label">RACK_BELEUCHTUNG</label>
                    <p class="section-hint">Globale LED-Konfiguration für alle Racks in diesem Raum.</p>
                    <div class="led-config">
                        <div class="led-color-row">
                            <button 
                                v-for="color in ledColors" 
                                :key="color.value"
                                class="led-color-btn"
                                :class="{ active: selectedLedColor === color.value }"
                                :style="{ '--led': color.value }"
                                @click="selectedLedColor = color.value"
                                :title="color.label"
                            >
                                <span class="led-swatch"></span>
                                <span class="led-name">{{ color.label }}</span>
                            </button>
                        </div>
                        <div class="led-mode-row">
                            <label class="mode-label">MODUS:</label>
                            <select v-model="selectedLedMode" class="v3-select-sm">
                                <option value="static">Statisch</option>
                                <option value="pulse">Pulsierend</option>
                                <option value="rainbow">Regenbogen</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overlay-footer">
                <button class="btn btn-secondary" @click="$emit('close')">ABBRECHEN</button>
                <button 
                    class="btn btn-primary" 
                    :disabled="!hasChanged || processing" 
                    @click="saveChanges"
                >
                    {{ processing ? 'WIRD_ANGEWENDET...' : 'KONFIGURATION_SPEICHERN' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { WALLPAPERS } from '../../constants/wallpapers';

const props = defineProps({
    room: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close']);
const gameStore = useGameStore();

const currentWallpaper = ref(props.room.wallpaper || 'default');
const currentTheme = ref(props.room.theme || 'classic');
const selectedLedColor = ref('#00ff00');
const selectedLedMode = ref('static');
const processing = ref(false);

const availableWallpapers = Object.values(WALLPAPERS);
const availableThemes = [
    { id: 'classic', name: 'Classic Dark', tag: 'STANDARD' },
    { id: 'cyberpunk', name: 'Cyberpunk Neon', tag: 'FUTURISTISCH' },
    { id: 'industrial', name: 'Grit Industrial', tag: 'RAUHEIT' },
    { id: 'minimalist', name: 'White Minimal', tag: 'CLEAN' }
];

const ledColors = [
    { value: '#00ff00', label: 'Grün' },
    { value: '#00f0ff', label: 'Cyan' },
    { value: '#2F6BFF', label: 'Blau' },
    { value: '#ff00ff', label: 'Magenta' },
    { value: '#ff5500', label: 'Orange' },
    { value: '#ff0000', label: 'Rot' },
    { value: '#ffffff', label: 'Weiß' },
];

const hasChanged = computed(() => {
    return currentWallpaper.value !== props.room.wallpaper 
        || currentTheme.value !== (props.room.theme || 'classic');
});

const selectWallpaper = (id) => {
    currentWallpaper.value = id;
};

const selectTheme = (id) => {
    currentTheme.value = id;
};

const saveChanges = async () => {
    processing.value = true;
    try {
        const result = await gameStore.customizeRoom(props.room.id, currentWallpaper.value, currentTheme.value);
        if (result.success) {
            // Also update rack LEDs if changed
            await gameStore.updateRoomRackLeds(props.room.id, selectedLedColor.value, selectedLedMode.value);
            emit('close');
        }
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(6px);
    z-index: 4000;
    display: flex; justify-content: center; align-items: center;
}

.customization-overlay {
    width: 650px;
    max-width: 92vw;
    max-height: 85vh;
    background: var(--v3-bg-surface, #11161D);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 20px 24px;
    display: flex; justify-content: space-between; align-items: flex-start;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(0,0,0,0.2);
}

.overlay-header h2 { 
    margin: 0; 
    font-size: 0.85rem; 
    color: var(--v3-accent, #2F6BFF); 
    letter-spacing: 0.15em;
    font-weight: 900;
}

.subtitle-desc {
    margin: 5px 0 0;
    font-size: 0.7rem;
    color: var(--v3-text-ghost, #484F58);
}

.close-button {
    background: none; border: none; color: var(--v3-text-ghost, #484F58); font-size: 1.5rem; cursor: pointer;
    transition: color 0.2s;
}
.close-button:hover { color: #fff; }

.overlay-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.overlay-body::-webkit-scrollbar { width: 4px; }
.overlay-body::-webkit-scrollbar-track { background: transparent; }
.overlay-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.config-section { margin-bottom: 10px; }

.section-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 900;
    color: var(--v3-accent, #2F6BFF);
    letter-spacing: 0.15em;
    margin-bottom: 12px;
}

.section-hint {
    font-size: 0.7rem;
    color: var(--v3-text-ghost, #484F58);
    margin: -4px 0 16px;
}

.section-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.04);
    margin: 24px 0;
}

/* Wallpaper Grid */
.wallpaper-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.wallpaper-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.wallpaper-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
}

.wallpaper-card.active {
    border-color: var(--v3-accent, #2F6BFF);
    background: rgba(47, 107, 255, 0.08);
    box-shadow: 0 0 15px rgba(47, 107, 255, 0.15);
}

.wallpaper-preview {
    height: 50px;
    border-radius: 4px;
    margin-bottom: 8px;
    position: relative;
    overflow: hidden;
}

.wp-check {
    position: absolute;
    top: 4px; right: 4px;
    background: var(--v3-accent, #2F6BFF);
    color: #fff;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem;
    font-weight: 900;
}

.preview-grid {
    position: absolute;
    top: 50%; left: 50%;
    width: 80px; height: 80px;
    transform: translate(-50%, -50%) rotate(45deg);
    border: 1px solid;
    opacity: 0.2;
}

.wallpaper-name {
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
}

.wallpaper-desc {
    font-size: 0.65rem;
    color: var(--v3-text-ghost, #484F58);
    margin: 2px 0 0;
}

/* Theme Grid */
.theme-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.theme-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    padding: 10px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.theme-card:hover { background: rgba(255, 255, 255, 0.04); }

.theme-card.active {
    border-color: var(--v3-accent, #2F6BFF);
    background: rgba(47, 107, 255, 0.08);
}

.theme-preview {
    height: 24px;
    display: flex;
    gap: 4px;
    margin-bottom: 8px;
    justify-content: center;
}

.preview-color { width: 16px; height: 16px; border-radius: 50%; }

.theme-card.classic .primary { background: #00d4ff; }
.theme-card.classic .secondary { background: #0f1419; }
.theme-card.cyberpunk .primary { background: #ff00ff; }
.theme-card.cyberpunk .secondary { background: #00ffff; }
.theme-card.industrial .primary { background: #ff5500; }
.theme-card.industrial .secondary { background: #1a1a1a; }
.theme-card.minimalist .primary { background: #000000; border: 1px solid rgba(255,255,255,0.2); }
.theme-card.minimalist .secondary { background: #ffffff; }

.theme-name {
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    display: block;
}

.theme-tag {
    font-size: 0.5rem;
    color: var(--v3-text-ghost, #484F58);
    letter-spacing: 0.1em;
}

/* LED Config */
.led-config {
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 6px;
    padding: 16px;
}

.led-color-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.led-color-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 6px;
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.led-color-btn:hover { border-color: rgba(255,255,255,0.2); }

.led-color-btn.active {
    border-color: var(--led);
    box-shadow: 0 0 12px color-mix(in srgb, var(--led) 30%, transparent);
}

.led-swatch {
    width: 16px; height: 16px;
    border-radius: 50%;
    background: var(--led);
    box-shadow: 0 0 8px var(--led);
}

.led-name {
    font-size: 0.5rem;
    color: var(--v3-text-ghost, #484F58);
    font-weight: 700;
}

.led-mode-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mode-label {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-text-ghost, #484F58);
    letter-spacing: 0.1em;
}

.v3-select-sm {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}

/* Footer */
.overlay-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    background: rgba(0, 0, 0, 0.3);
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--v3-text-secondary, #8FA1B3);
    border: 1px solid rgba(255,255,255,0.08);
}

.btn-primary {
    background: var(--v3-accent, #2F6BFF);
    color: #fff;
}

.btn-primary:hover { filter: brightness(1.2); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; filter: none; }

/* Responsive */
@media (max-width: 640px) {
    .theme-grid { grid-template-columns: repeat(2, 1fr); }
    .led-color-row { gap: 6px; }
}
</style>
