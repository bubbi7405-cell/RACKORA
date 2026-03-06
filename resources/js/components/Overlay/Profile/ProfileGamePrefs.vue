<template>
    <div class="panel-game-prefs">
        <div class="prefs-header">
            <h3>Simulation Environment</h3>
            <p>Adjust how the world is rendered and how alerts are delivered.</p>
        </div>

        <div class="prefs-grid">
            <!-- Visual Settings -->
            <div class="pref-card">
                <div class="card-title">
                    <span class="icon">🎨</span>
                    <h4>Visual Theme</h4>
                </div>
                <div class="theme-grid">
                    <div 
                        v-for="theme in themes" 
                        :key="theme.id"
                        class="theme-option"
                        :class="{ active: currentTheme === theme.id }"
                        @click="setTheme(theme.id)"
                    >
                        <div class="theme-preview" :class="theme.id"></div>
                        <div class="theme-label">{{ theme.name }}</div>
                    </div>
                </div>
            </div>

            <!-- Audio Settings -->
            <div class="pref-card">
                <div class="card-title">
                    <span class="icon">🔊</span>
                    <h4>Audio & Feedback</h4>
                </div>
                
                <div class="control-group">
                    <div class="label-row">
                        <label>Master Volume</label>
                        <span>{{ Math.round(volume * 100) }}%</span>
                    </div>
                    <input type="range" min="0" max="1" step="0.01" v-model="volume" @input="updateVolume" class="premium-slider">
                </div>

                <div class="control-row">
                    <label>Sound Effects</label>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="soundEnabled" @change="toggleSound">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="control-row">
                    <label>Ambient Server Hum</label>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="ambientEnabled" @change="toggleAmbient">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Alert Settings -->
            <div class="pref-card">
                <div class="card-title">
                    <span class="icon">🚨</span>
                    <h4>Alert Intensity</h4>
                </div>
                <p class="desc">Configure how critical events grabbed your attention.</p>
                <div class="intensity-options">
                    <button 
                        v-for="opt in intensities" 
                        :key="opt.id"
                        class="intensity-btn"
                        :class="{ active: alertIntensity === opt.id }"
                        @click="setIntensitiy(opt.id)"
                    >
                        <div class="i-icon">{{ opt.icon }}</div>
                        <div class="i-info">
                            <div class="i-name">{{ opt.name }}</div>
                            <div class="i-desc">{{ opt.desc }}</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="pref-card">
                <div class="card-title">
                    <span class="icon">✉️</span>
                    <h4>Notifications</h4>
                </div>
                <div class="control-row">
                    <label>In-game Toasts</label>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="notifsEnabled" @change="toggleNotifs">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="control-row">
                    <label>News Ticker Updates</label>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="newsEnabled" @change="toggleNews">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-footer">
            <button class="reset-btn" @click="resetDefaults">RESTORE DEFAULTS</button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../utils/api';
import SoundManager from '../../../services/SoundManager';
import { useToastStore } from '../../../stores/toast';

const toast = useToastStore();

const currentTheme = ref('dark');
const volume = ref(0.5);
const soundEnabled = ref(true);
const ambientEnabled = ref(true);
const alertIntensity = ref('medium');
const notifsEnabled = ref(true);
const newsEnabled = ref(true);

const themes = [
    { id: 'dark', name: 'Obsidian (Default)' },
    { id: 'light', name: 'Crystal' },
    { id: 'cyberpunk', name: 'Neon City' },
    { id: 'terminal', name: 'Terminal (High Contrast)' },
];

const intensities = [
    { id: 'low', name: 'SUBTLE', desc: 'Visual markers only', icon: '🔹' },
    { id: 'medium', name: 'STANDARD', desc: 'Sound + UI pulses', icon: '🟡' },
    { id: 'high', name: 'OVERLOAD', desc: 'Full sirens + Screen flash', icon: '🔥' },
];

async function loadSettings() {
    try {
        const res = await api.get('/profile');
        if (res.success) {
            const prefs = res.data.preferences;
            currentTheme.value = prefs.theme || 'dark';
            volume.value = prefs.volume ?? 0.5;
            soundEnabled.value = prefs.sound_enabled !== false;
            ambientEnabled.value = prefs.ambient_enabled !== false;
            alertIntensity.value = prefs.alert_intensity || 'medium';
            notifsEnabled.value = prefs.notifications !== false;
            newsEnabled.value = prefs.news_ticker !== false;
        }
    } catch (e) {
        console.error(e);
    }
}

async function savePref(key, value) {
    try {
        const payload = {};
        payload[key] = value;
        await api.post('/profile/preferences', payload);
    } catch (e) {
        toast.error('Failed to sync settings');
    }
}

function setTheme(id) {
    currentTheme.value = id;
    document.documentElement.setAttribute('data-theme', id);
    savePref('theme', id);
    toast.info(`Interface theme: ${id.toUpperCase()} engaged.`);
}

function updateVolume() {
    SoundManager.setVolume(volume.value);
    savePref('volume', volume.value);
}

function toggleSound() {
    SoundManager.setMute(!soundEnabled.value);
    savePref('sound_enabled', soundEnabled.value);
}

function toggleAmbient() {
    savePref('ambient_enabled', ambientEnabled.value);
}

function setIntensitiy(id) {
    alertIntensity.value = id;
    savePref('alert_intensity', id);
}

function toggleNotifs() {
    savePref('notifications', notifsEnabled.value);
}

function toggleNews() {
    savePref('news_ticker', newsEnabled.value);
}

function resetDefaults() {
    if (!confirm('Reset all preferences?')) return;
    setTheme('dark');
    volume.value = 0.5;
    updateVolume();
    soundEnabled.value = true;
    toggleSound();
    setIntensitiy('medium');
}

onMounted(loadSettings);
</script>

<style scoped>
.panel-game-prefs {
    animation: fadeIn 0.4s ease-out;
}

.prefs-header {
    margin-bottom: 30px;
}

.prefs-header h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 5px;
}

.prefs-header p {
    color: #71717a;
    font-size: 0.9rem;
}

.prefs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.pref-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-title h4 {
    margin: 0;
    font-weight: 800;
    font-size: 0.9rem;
    color: #fff;
}

.theme-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.theme-option {
    border: 1px solid #27272a;
    border-radius: 10px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.theme-option.active {
    border-color: #fff;
    background: rgba(255, 255, 255, 0.05);
}

.theme-preview {
    height: 40px;
    border-radius: 6px;
    margin-bottom: 8px;
}

.theme-preview.dark { background: #000; }
.theme-preview.light { background: #fff; }
.theme-preview.cyberpunk { background: linear-gradient(45deg, #f0f, #0ff); }
.theme-preview.terminal { background: #000; border: 1px solid #fff; position: relative; }
.theme-preview.terminal::after { content: '>_'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-size: 1rem; font-weight: 800; }

.theme-label { font-size: 0.75rem; font-weight: 700; }

.control-group { margin-bottom: 20px; }

.label-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    font-weight: 800;
    color: #a1a1aa;
    margin-bottom: 10px;
}

.control-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.control-row label:first-child {
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
}

.intensity-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.intensity-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #09090b;
    border: 1px solid #18181b;
    padding: 12px;
    border-radius: 10px;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s;
}

.intensity-btn.active {
    border-color: var(--color-primary);
    background: rgba(59, 130, 246, 0.05);
}

.i-name { font-weight: 800; font-size: 0.8rem; }
.i-desc { font-size: 0.7rem; color: #52525b; }

.desc { font-size: 0.8rem; color: #71717a; margin-bottom: 15px; }

.premium-slider {
    width: 100%;
    accent-color: var(--color-primary);
}

.reset-btn {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 0.75rem;
    font-weight: 800;
    cursor: pointer;
    text-decoration: underline;
}

@media (max-width: 700px) {
    .prefs-grid {
        grid-template-columns: 1fr;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
