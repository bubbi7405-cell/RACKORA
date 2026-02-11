<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="settings-overlay">
            <div class="overlay-header">
                <h2>Game Settings</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>
            <div class="settings-body">
                <div class="setting-group">
                    <h3>Audio</h3>
                    <div class="setting-item">
                        <label>Master Volume: {{ Math.round(volume * 100) }}%</label>
                        <input 
                            type="range" 
                            min="0" 
                            max="1" 
                            step="0.05" 
                            :value="volume" 
                            @input="updateVolume"
                            class="volume-slider"
                        >
                    </div>
                    
                    <div class="setting-actions">
                        <button 
                            class="btn" 
                            :class="isMuted ? 'btn-danger' : 'btn-primary'" 
                            @click="toggleMute"
                        >
                            {{ isMuted ? 'Unmute Audio' : 'Mute Audio' }}
                        </button>
                        <button class="btn btn-secondary" @click="testSound">
                            Test Sound 🔊
                        </button>
                    </div>
                </div>

                <hr class="separator">

                <div class="setting-group">
                    <h3>Appearance</h3>
                    <div class="setting-item">
                        <label>Interface Theme</label>
                        <div class="theme-grid">
                            <button 
                                v-for="theme in themes" 
                                :key="theme.id"
                                class="btn btn-sm theme-btn" 
                                :class="currentTheme === theme.id ? 'btn-primary' : 'btn-secondary'" 
                                @click="setTheme(theme.id)"
                            >
                                {{ theme.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="separator">

                <div class="setting-group">
                    <h3>Account</h3>
                    <p class="text-muted">Player: {{ user?.name }}</p>
                    <button class="btn btn-danger w-100" @click="logout">Log Out</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import SoundManager from '../../services/SoundManager';
import themeManager from '../../services/ThemeManager';

const emit = defineEmits(['close']);
const authStore = useAuthStore();
const user = authStore.user;

const volume = ref(SoundManager.volume);
const isMuted = ref(SoundManager.isMuted);

const themes = themeManager.getAvailableThemes();
const currentTheme = ref(themeManager.getCurrentTheme());

function updateVolume(e) {
    const val = parseFloat(e.target.value);
    volume.value = val;
    SoundManager.setVolume(val);
}

function setTheme(id) {
    themeManager.setTheme(id);
    currentTheme.value = id;
    SoundManager.playClick();
}

function toggleMute() {
    isMuted.value = SoundManager.toggleMute();
}

function testSound() {
    SoundManager.playSuccess();
}

async function logout() {
    SoundManager.playClick();
    await authStore.logout();
    window.location.reload();
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.settings-overlay {
    width: 400px;
    max-width: 90vw;
    background: #0d1117;
    border: 1px solid #30363d;
    box-shadow: 0 0 50px rgba(0,0,0,0.5);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 15px 20px;
    border-bottom: 1px solid #30363d;
    background: #161b22;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.overlay-header h2 {
    margin: 0;
    color: #e6edf3;
    font-size: 1.2rem;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #fff; }

.settings-body {
    padding: 20px;
}

.setting-group {
    margin-bottom: 20px;
}

.setting-group h3 {
    margin: 0 0 15px 0;
    font-size: 0.9rem;
    text-transform: uppercase;
    color: #8b949e;
    letter-spacing: 0.05em;
}

.setting-item {
    margin-bottom: 15px;
}

.setting-item label {
    display: block;
    margin-bottom: 8px;
    color: #c9d1d9;
}

.volume-slider {
    width: 100%;
    cursor: pointer;
}

.setting-actions {
    display: flex;
    gap: 10px;
}

.separator {
    border: 0;
    border-top: 1px solid #30363d;
    margin: 20px 0;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    border: 1px solid #30363d;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
    background: #21262d;
    color: #c9d1d9;
    flex: 1;
}

.btn:hover {
    background: #30363d;
}

.btn-primary {
    background: #238636;
    color: white;
    border-color: #238636;
}
.btn-primary:hover {
    background: #2ea043;
}

.btn-danger {
    background: #da3633;
    color: white;
    border-color: #da3633;
}
.btn-danger:hover {
    background: #f85149;
}

.btn-secondary {
    background: #1f6feb;
    color: white;
    border-color: #1f6feb;
}

.w-100 { width: 100%; }
.text-muted { color: #8b949e; margin-bottom: 15px; font-size: 0.9rem; }

.theme-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.theme-btn {
    text-align: center;
    font-size: 0.8rem;
    padding: 10px;
}
</style>
