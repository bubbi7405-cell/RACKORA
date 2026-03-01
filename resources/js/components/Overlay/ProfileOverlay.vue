<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="profile-overlay glass-panel animation-slide-up">
            <!-- Sidebar Navigation -->
            <aside class="profile-sidebar" :class="{ 'sidebar--collapsed': mobileMenuOpen }">
                <div class="user-brief">
                    <div class="avatar-large" @click="handleTabClick('overview')" :style="{ backgroundColor: user?.accent_color || 'var(--color-primary)' }">
                        <img v-if="user?.avatar" :src="imageUrl(user.avatar)" class="avatar-img">
                        <span v-else>{{ userInitial }}</span>
                        <div class="status-marker online"></div>
                    </div>
                    <div class="user-meta">
                         <div class="user-name">{{ user?.name }}</div>
                         <div class="user-company">{{ user?.company_name || 'Individual Developer' }}</div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        class="nav-btn"
                        :class="{ active: activeTab === tab.id }"
                        @click="handleTabClick(tab.id)"
                    >
                        <span class="nav-icon">{{ tab.icon }}</span>
                        <span class="nav-label">{{ tab.name }}</span>
                    </button>
                </nav>

                <div class="sidebar-footer">
                    <button class="logout-btn" @click="logout">
                         <span class="icon">🔌</span>
                         <span class="label">SECURE LOGOUT</span>
                    </button>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="profile-content">
                <header class="content-header">
                    <div class="header-left">
                         <button class="mobile-toggle" @click="mobileMenuOpen = !mobileMenuOpen">☰</button>
                         <h2>{{ currentTabName }}</h2>
                    </div>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </header>

                <div class="content-body v-scrollbar">
                    <div v-if="loading" class="panel-loading">
                         <div class="loader"></div>
                         <p>Retrieving node identity...</p>
                    </div>
                    
                    <component 
                        v-else
                        :is="activePanelComponent" 
                        :user="user" 
                        :profileData="profileData"
                        @trigger-upload="triggerUpload"
                    />
                </div>
            </main>
        </div>

        <!-- Hidden File Input -->
        <input 
            type="file" 
            ref="fileInput" 
            @change="handleFileUpload" 
            style="display: none" 
            accept="image/*"
        >
    </div>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

import ProfileOverview from './Profile/ProfileOverview.vue';
import ProfileSecurity from './Profile/ProfileSecurity.vue';
import ProfilePrivacy from './Profile/ProfilePrivacy.vue';
import ProfileSettings from './Profile/ProfileSettings.vue';
import ProfileGamePrefs from './Profile/ProfileGamePrefs.vue';
import ProfileBranding from './Profile/ProfileBranding.vue';
import ProfileAchievements from './Profile/ProfileAchievements.vue';

const emit = defineEmits(['close']);
const authStore = useAuthStore();
const gameStore = useGameStore();

const loading = ref(true);
const activeTab = ref('overview');
const mobileMenuOpen = ref(false);
const fileInput = ref(null);
const uploadType = ref('avatar');
const profileData = ref(null);

const user = computed(() => authStore.user);
const userInitial = computed(() => user.value?.name?.charAt(0).toUpperCase() || '?');

const tabs = [
    { id: 'overview', name: 'Identity Hub', icon: '👤' },
    { id: 'account', name: 'Account Setings', icon: '⚙️' },
    { id: 'preferences', name: 'Preferences', icon: '🎮' },
    { id: 'branding', name: 'Branding', icon: '🏢' },
    { id: 'security', name: 'Security', icon: '🛡️' },
    { id: 'privacy', name: 'Privacy', icon: '👁️' },
    { id: 'stats', name: 'Achievements', icon: '🏆' },
];

const currentTabName = computed(() => tabs.find(t => t.id === activeTab.value)?.name.toUpperCase() || 'PROFILE');

const activePanelComponent = computed(() => {
    switch (activeTab.value) {
        case 'overview': return markRaw(ProfileOverview);
        case 'account': return markRaw(ProfileSettings);
        case 'preferences': return markRaw(ProfileGamePrefs);
        case 'branding': return markRaw(ProfileBranding);
        case 'security': return markRaw(ProfileSecurity);
        case 'privacy': return markRaw(ProfilePrivacy);
        case 'stats': return markRaw(ProfileAchievements);
        default: return markRaw(ProfileOverview);
    }
});

async function fetchProfile() {
    loading.value = true;
    try {
        const response = await api.get('/profile');
        if (response.success) {
            profileData.value = response.data;
        }
    } catch (e) {
        console.error("Profile load error", e);
    } finally {
        loading.value = false;
    }
}

function handleTabClick(tabId) {
    activeTab.value = tabId;
    mobileMenuOpen.value = false;
    if (SoundManager) SoundManager.playClick();
}

function triggerUpload(type) {
    uploadType.value = type;
    fileInput.value.click();
}

async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', uploadType.value);

    try {
        const response = await api.post('/profile/upload', formData);
        if (response.success) {
            authStore.checkAuth();
            fetchProfile();
        }
    } catch (e) {
        alert("Upload failed: " + e.message);
    } finally {
        event.target.value = '';
    }
}

async function logout() {
    await authStore.logout();
    window.location.reload();
}

const imageUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
};

onMounted(fetchProfile);
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(12px);
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.profile-overlay {
    width: 1000px;
    height: 750px;
    max-width: 100%;
    max-height: 100%;
    background: #09090b;
    border: 1px solid #18181b;
    border-radius: 24px;
    display: flex;
    overflow: hidden;
    box-shadow: 0 50px 100px -20px rgba(0,0,0,0.5);
}

/* Sidebar */
.profile-sidebar {
    width: 260px;
    background: #0f0f12;
    border-right: 1px solid #18181b;
    display: flex;
    flex-direction: column;
    padding: 40px 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.user-brief {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 40px;
}

.avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    color: #000;
    margin-bottom: 15px;
    cursor: pointer;
    position: relative;
    box-shadow: 0 10px 20px rgba(0,0,0,0.3);
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 20px;
}

.status-marker {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #22c55e;
    border: 3px solid #0f0f12;
}

.user-name {
    font-weight: 800;
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 4px;
}

.user-company {
    font-size: 0.75rem;
    color: #71717a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.nav-btn {
    background: none;
    border: none;
    padding: 12px 16px;
    border-radius: 12px;
    color: #71717a;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.nav-btn:hover {
    background: rgba(255,255,255,0.03);
    color: #fff;
}

.nav-btn.active {
    background: var(--color-primary);
    color: #000;
}

.sidebar-footer {
    padding-top: 20px;
    border-top: 1px solid #18181b;
}

.logout-btn {
    width: 100%;
    background: #18181b;
    border: 1px solid #27272a;
    padding: 12px;
    border-radius: 12px;
    color: #ef4444;
    font-weight: 800;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}

/* Content */
.profile-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #09090b;
}

.content-header {
    padding: 30px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #18181b;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.mobile-toggle {
    display: none;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
}

.content-header h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #a1a1aa;
}

.close-btn {
    background: #18181b;
    border: 1px solid #27272a;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
}

.content-body {
    padding: 40px;
    overflow-y: auto;
    flex: 1;
}

.panel-loading {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20px;
    color: #71717a;
}

.loader {
    width: 40px;
    height: 40px;
    border: 3px solid #18181b;
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Responsiveness */
@media (max-width: 900px) {
    .profile-sidebar {
        position: absolute;
        left: 0; top: 0; bottom: 0;
        z-index: 10;
        transform: translateX(-100%);
    }
    .profile-sidebar.sidebar--collapsed {
        transform: translateX(0);
        box-shadow: 20px 0 50px rgba(0,0,0,0.5);
    }
    .mobile-toggle { display: block; }
    .content-header { padding: 20px; }
    .content-body { padding: 20px; }
}

@media (max-width: 600px) {
    .profile-overlay {
        border-radius: 0;
    }
    .overlay-backdrop {
        padding: 0;
    }
}
</style>
