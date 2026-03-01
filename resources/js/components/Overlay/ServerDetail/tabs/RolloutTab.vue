<template>
    <div class="tab-content rollout-tab provision-lab">
        <div class="rollout-dashboard">
            <div class="proc-header">
                <div class="proc-title">
                    <h3>BLUEPRINT_MANAGEMENT</h3>
                    <p>Sichern Sie die aktuelle Konfiguration (OS + Apps) als Vorlage für Cluster-Deployments.</p>
                </div>
            </div>

            <div class="template-creation-zone">
                <div class="v3-info-box">
                    <label>KONFIGURATION_SICHERN</label>
                    <div class="template-save-card-v3">
                        <input 
                            v-model="localTemplateName" 
                            class="v3-input-sm" 
                            placeholder="Name des Blueprints..."
                            :disabled="processing"
                        >
                        <button class="btn-template-save-v3" @click="createTemplate" :disabled="!localTemplateName || processing">
                            SYSTEM_SNAPSHOT_ERSTELLEN
                        </button>
                    </div>
                </div>
            </div>

            <div class="blueprints-section">
                <label>VERFÜGBARE_BLUEPRINTS (CLUSTER-WEIT)</label>
                <div class="blueprint-grid-v3" v-if="templates.length > 0">
                    <div v-for="tpl in templates" :key="tpl.id" class="blueprint-card-v3">
                        <div class="bp-info">
                            <div class="bp-name">{{ tpl.name }}</div>
                            <div class="bp-specs">
                                <span>{{ getOsName(tpl.os_type) }} v{{ tpl.os_version }}</span>
                                <span class="dot-sep">•</span>
                                <span>{{ tpl.installed_applications?.length || 0 }} Applikationen</span>
                            </div>
                        </div>
                        <div class="bp-actions">
                            <button class="btn-bp-deploy" @click="applyTemplate(tpl.id)" :disabled="processing || server.os?.status === 'installing'">
                                DEPLOY
                            </button>
                            <button class="btn-bp-del" @click="deleteTemplate(tpl.id)">×</button>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-blueprints-v3">
                    <div class="empty-icon">📂</div>
                    <p>Keine Blueprints gefunden. Erstellen Sie einen Snapshot Ihrer aktuellen Konfiguration, um die Skalierung zu automatisieren.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../../utils/api';

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload', 'switch-tab']);

const localTemplateName = ref('');
const templates = ref([]);
const osCatalog = ref({});

const getOsName = (type) => {
    return osCatalog.value[type]?.name || type;
};

const loadTemplates = async () => {
    try {
        const response = await api.get('/templates');
        if (response.success) {
            templates.value = response.templates;
        }
    } catch (e) { }
};

const loadOsCatalog = async () => {
    try {
        const res = await api.get('/catalog/os');
        if (res.success) osCatalog.value = res.catalog || res.data;
    } catch (e) {
        console.error("Failed to load os catalog", e);
    }
};

const createTemplate = async () => {
    if (!localTemplateName.value) return;
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post('/templates/create', {
            server_id: props.server.id,
            name: localTemplateName.value
        });
        if (response.success) {
            localTemplateName.value = '';
            loadTemplates();
        }
    } finally {
        emit('processing-end');
    }
};

const applyTemplate = async (templateId) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post('/templates/apply', {
            server_id: props.server.id,
            template_id: templateId
        });
        if (response.success) {
            emit('switch-tab', 'OS');
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const deleteTemplate = async (id) => {
    try {
        const response = await api.delete(`/templates/${id}`);
        if (response.success) {
            loadTemplates();
        }
    } catch (e) { }
};

onMounted(() => {
    loadTemplates();
    loadOsCatalog();
});
</script>
