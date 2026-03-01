<template>
    <div class="panel-account">
        <div class="account-header">
            <h3>Account Authentication</h3>
            <p>Manage your account credentials and system access.</p>
        </div>

        <div class="account-grid">
            <!-- Profile Info -->
            <form @submit.prevent="updateProfile" class="account-card">
                <div class="card-title">
                    <span class="icon">👤</span>
                    <h4>Personal Details</h4>
                </div>
                
                <div class="form-group">
                    <label>Professional Name</label>
                    <input type="text" v-model="profileForm.name" required>
                </div>

                <div class="form-group">
                    <label>Registry Email (Communication Hub)</label>
                    <input type="email" v-model="profileForm.email" required>
                    <p class="hint">Your email is used for account recovery and official system alerts.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="premium-btn" :disabled="processing">
                        {{ processing ? 'SYNCHRONIZING...' : 'UPDATE PROFILE' }}
                    </button>
                </div>
            </form>

            <!-- Password Change -->
            <form @submit.prevent="updatePassword" class="account-card">
                <div class="card-title">
                    <span class="icon">🔑</span>
                    <h4>Security Credentials</h4>
                </div>

                <div class="form-group">
                    <label>Current Access Key</label>
                    <input type="password" v-model="passwordForm.current_password" required>
                </div>

                <div class="form-group">
                    <label>New Access Key</label>
                    <input type="password" v-model="passwordForm.password" required>
                </div>

                <div class="form-group">
                    <label>Confirm Access Key</label>
                    <input type="password" v-model="passwordForm.password_confirmation" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="secondary-btn" :disabled="pwProcessing">
                        {{ pwProcessing ? 'ENCRYPTING...' : 'ROTATE KEYS' }}
                    </button>
                </div>
            </form>

            <!-- Metadata Info -->
            <div class="account-card metadata-card">
                <div class="card-title">
                    <span class="icon">ℹ️</span>
                    <h4>System Metadata</h4>
                </div>
                <div class="meta-row">
                    <span>Account ID</span>
                    <code>{{ user?.id }}</code>
                </div>
                <div class="meta-row">
                    <span>Node Initialized</span>
                    <span>{{ formatDate(user?.created_at) }}</span>
                </div>
                <div class="meta-row">
                    <span>Authentication Method</span>
                    <span>Password / Sanctum</span>
                </div>
                <div class="meta-row">
                    <span>Access Status</span>
                    <span class="status online">VERIFIED</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../../../utils/api';
import { useAuthStore } from '../../../stores/auth';
import { useToastStore } from '../../../stores/toast';

const props = defineProps(['user']);
const authStore = useAuthStore();
const toast = useToastStore();
const processing = ref(false);
const pwProcessing = ref(false);

const profileForm = ref({
    name: props.user?.name || '',
    email: props.user?.email || '',
});

const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: ''
});

watch(() => props.user, (u) => {
    if (u) {
        profileForm.value.name = u.name;
        profileForm.value.email = u.email;
    }
}, { immediate: true });

async function updateProfile() {
    processing.value = true;
    try {
        const res = await api.post('/profile/update', profileForm.value);
        if (res.success) {
            toast.success('System profile synchronized.');
            authStore.checkAuth();
        }
    } catch (e) {
        toast.error('Sync failed: ' + e.message);
    } finally {
        processing.value = false;
    }
}

async function updatePassword() {
    pwProcessing.value = true;
    try {
        const res = await api.post('/profile/password', passwordForm.value);
        if (res.success) {
            toast.success('Access keys rotated successfully.');
            passwordForm.value = { current_password: '', password: '', password_confirmation: '' };
        }
    } catch (e) {
        toast.error('Rotation rejected: ' + e.message);
    } finally {
        pwProcessing.value = false;
    }
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
</script>

<style scoped>
.panel-account {
    animation: fadeIn 0.4s ease-out;
}

.account-header { margin-bottom: 30px; }
.account-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 5px; }
.account-header p { color: #71717a; font-size: 0.9rem; }

.account-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.account-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
}

.card-title h4 {
    margin: 0;
    font-weight: 800;
    font-size: 0.9rem;
    color: #fff;
    letter-spacing: 0.05em;
}

.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: #71717a;
    margin-bottom: 8px;
    text-transform: uppercase;
}

input {
    width: 100%;
    background: #09090b;
    border: 1px solid #27272a;
    padding: 12px;
    border-radius: 8px;
    color: #fff;
    font-size: 0.9rem;
}

input:focus {
    border-color: var(--color-primary);
    outline: none;
}

.hint { font-size: 0.7rem; color: #52525b; margin-top: 8px; line-height: 1.4; }

.form-actions { margin-top: auto; padding-top: 20px; }

.premium-btn {
    width: 100%;
    background: #fff;
    color: #000;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}

.secondary-btn {
    width: 100%;
    background: #27272a;
    color: #fff;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}

.metadata-card {
    grid-column: span 2;
    background: linear-gradient(to right, rgba(59, 130, 246, 0.03), transparent);
}

.meta-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    font-size: 0.85rem;
}

.meta-row:last-child { border: none; }
.meta-row span:first-child { color: #71717a; font-weight: 600; }
.meta-row code { color: var(--color-primary); background: rgba(59, 130, 246, 0.1); padding: 2px 6px; border-radius: 4px; }
.meta-row .status.online { color: #22c55e; font-weight: 800; }

@media (max-width: 850px) {
    .account-grid { grid-template-columns: 1fr; }
    .metadata-card { grid-column: auto; }
}
</style>
