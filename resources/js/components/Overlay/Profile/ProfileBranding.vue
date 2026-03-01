<template>
    <div class="panel-branding">
        <div class="branding-header">
            <h3>Corporate Identity</h3>
            <p>Customize how your company appears across the network.</p>
        </div>

        <div class="branding-grid">
            <div class="form-section">
                <div class="form-group">
                    <label>Public Professional Name</label>
                    <div class="input-wrapper">
                         <span class="input-icon">👤</span>
                         <input type="text" v-model="form.name" placeholder="Executive Name">
                    </div>
                </div>

                <div class="form-group">
                    <label>Company Legal Name</label>
                    <div class="input-wrapper">
                         <span class="input-icon">🏢</span>
                         <input type="text" v-model="form.company_name" placeholder="Infrastructure Inc.">
                    </div>
                </div>

                <div class="form-group">
                    <label>Corporate Slogan (Tagline)</label>
                    <div class="input-wrapper">
                         <span class="input-icon">💬</span>
                         <input type="text" v-model="form.slogan" placeholder="Defining the Digital Frontier.">
                    </div>
                </div>
            </div>

            <div class="visual-section">
                <div class="form-group">
                    <label>Corporate Color Palette</label>
                    <div class="color-control">
                        <div class="color-preview" :style="{ backgroundColor: form.accent_color }"></div>
                        <div class="color-inputs">
                             <input type="color" v-model="form.accent_color" class="color-picker">
                             <input type="text" v-model="form.accent_color" class="color-text">
                        </div>
                    </div>
                    <p class="hint">This color is used for UI accents, avatars, and your network banner.</p>
                </div>

                <div class="brand-preview">
                     <label>Corporate Mark (Logo)</label>
                     <div class="logo-upload-box" @click="$emit('trigger-upload', 'company_logo')">
                         <img v-if="user?.company_logo" :src="imageUrl(user.company_logo)" class="logo-img">
                         <div v-else class="logo-placeholder">
                             <span>UPLOAD LOGO</span>
                             <small>Recommended: 512x512 PNG</small>
                         </div>
                         <div class="logo-hover">📷 Change Logo</div>
                     </div>

                     <label style="margin-top: 20px;">Brand Preview</label>
                     <div class="preview-card" :style="{ borderLeftColor: form.accent_color }">
                         <div class="preview-avatar" :style="{ backgroundColor: form.accent_color }">
                             <img v-if="user?.company_logo" :src="imageUrl(user.company_logo)" class="avatar-logo-img">
                             <span v-else>{{ form.name?.charAt(0) || '?' }}</span>
                         </div>
                         <div class="preview-text">
                             <div class="preview-name">{{ form.company_name || 'System Identity' }}</div>
                             <div class="preview-slogan">{{ form.slogan || 'Global Infrastructure' }}</div>
                         </div>
                     </div>
                </div>
            </div>
        </div>

        <div class="form-footer">
            <button class="save-btn" @click="save" :disabled="processing">
                <span v-if="!processing">SAVE CORPORATE IDENTITY</span>
                <span v-else class="loader"></span>
            </button>
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

const form = ref({
    name: props.user?.name || '',
    company_name: props.user?.company_name || '',
    slogan: props.user?.slogan || '',
    accent_color: props.user?.accent_color || '#3b82f6'
});

watch(() => props.user, (u) => {
    if (u) {
        form.value.name = u.name;
        form.value.company_name = u.company_name;
        form.value.slogan = u.slogan;
        form.value.accent_color = u.accent_color || '#3b82f6';
    }
}, { immediate: true });

async function save() {
    processing.value = true;
    try {
        const res = await api.post('/profile/update', form.value);
        if (res.success) {
            toast.success('Branding updated successfully.');
            authStore.checkAuth();
        }
    } catch (e) {
        toast.error('Update failed: ' + e.message);
    } finally {
        processing.value = false;
    }
}
</script>

<style scoped>
.panel-branding {
    animation: slideIn 0.4s ease-out;
}

.branding-header {
    margin-bottom: 30px;
}

.branding-header h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 5px;
}

.branding-header p {
    color: #71717a;
    font-size: 0.9rem;
}

.branding-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: #a1a1aa;
    margin-bottom: 8px;
    letter-spacing: 0.05em;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 12px;
    font-size: 1rem;
    opacity: 0.5;
}

input[type="text"] {
    width: 100%;
    background: #09090b;
    border: 1px solid #27272a;
    padding: 12px 12px 12px 40px;
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

input[type="text"]:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    outline: none;
}

.color-control {
    display: flex;
    gap: 15px;
    align-items: center;
    background: #09090b;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid #27272a;
}

.color-preview {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.1);
}

.color-inputs {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.color-picker {
    appearance: none;
    background: none;
    border: none;
    width: 100px;
    height: 30px;
    padding: 0;
    cursor: pointer;
}

.color-text {
    padding: 4px 8px !important;
    font-family: monospace;
    font-size: 0.8rem !important;
}

.hint {
    font-size: 0.75rem;
    color: #52525b;
    margin-top: 10px;
    line-height: 1.4;
}

.brand-preview {
    margin-top: 30px;
}

.preview-card {
    background: #09090b;
    border: 1px solid #27272a;
    border-left: 4px solid #3b82f6;
    padding: 15px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.preview-avatar {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #000;
    overflow: hidden;
}

.avatar-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.logo-upload-box {
    width: 100%;
    height: 120px;
    background: #09090b;
    border: 1px dashed #27272a;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: 0.2s;
}

.logo-upload-box:hover {
    border-color: var(--color-primary);
    background: rgba(255,255,255,0.02);
}

.logo-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #52525b;
}

.logo-placeholder span { font-weight: 800; font-size: 0.8rem; }
.logo-placeholder small { font-size: 0.65rem; }

.logo-img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
}

.logo-hover {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: 0.2s;
    font-size: 0.8rem;
    font-weight: 800;
}

.logo-upload-box:hover .logo-hover {
    opacity: 1;
}

.preview-name {
    font-weight: 700;
    font-size: 0.9rem;
}

.preview-slogan {
    font-size: 0.75rem;
    color: #71717a;
}

.form-footer {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #27272a;
}

.save-btn {
    background: #fff;
    color: #000;
    border: none;
    padding: 14px 28px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}

.save-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,255,255,0.1);
}

.save-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 800px) {
    .branding-grid {
        grid-template-columns: 1fr;
    }
}
</style>
