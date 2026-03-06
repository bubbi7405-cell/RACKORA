<template>
    <div class="tab-content rollout-tab provision-lab">
        <div class="rollout-dashboard">
            <div class="summary-group-v3 mb-4">
                <label class="section-label-industrial">SYSTEM_SNAPSHOT_ERSTELLEN</label>
                <div class="template-creation-zone" style="margin-top: 10px;">
                    <div class="template-save-card-v3">
                        <div class="input-group">
                            <input v-model="localTemplateName" class="v3-input" placeholder="Name des Blueprints..."
                                :disabled="processing">
                            <button class="btn-deployment" @click="createTemplate"
                                :disabled="!localTemplateName || processing">
                                SPEICHERN
                            </button>
                        </div>
                        <p class="mt-2 text-muted" style="font-size: 0.8rem;">
                            Speichern Sie den aktuellen Zustand (OS & Apps) als wiederverwendbares Blueprint für
                            1-Klick-Deployments in Ihrem Cluster.
                        </p>
                    </div>
                </div>
            </div>

            <div class="summary-group-v3">
                <label class="section-label-industrial">VERFÜGBARE_BLUEPRINTS (CLUSTER-WEIT)</label>
                <div class="blueprint-grid-v3" v-if="templates.length > 0" style="margin-top: 10px;">
                    <div v-for="tpl in templates" :key="tpl.id" class="blueprint-card-v3">
                        <div class="bp-info">
                            <div class="bp-name">
                                <span class="os-icon">💾</span> {{ tpl.name }}
                            </div>
                            <div class="bp-specs">
                                <span class="badge version">{{ getOsName(tpl.os_type) }} v{{ tpl.os_version }}</span>
                                <span class="badge arch">{{ tpl.installed_applications?.length || 0 }} Apps</span>
                            </div>
                        </div>
                        <div class="bp-actions">
                            <button class="btn-on btn-xs" @click="applyTemplate(tpl.id)"
                                :disabled="processing || server.os?.status === 'installing'"
                                title="Blueprint auf diesen Server anwenden">
                                DEPLOY
                            </button>
                            <button class="btn-off btn-xs" @click="deleteTemplate(tpl.id)"
                                title="Blueprint löschen">×</button>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-blueprints-v3"
                    style="text-align: center; padding: 30px; border: 1px dashed var(--ds-border); border-radius: 8px; margin-top: 10px;">
                    <div class="empty-icon" style="font-size: 2rem; opacity: 0.5;">📂</div>
                    <p style="color: var(--ds-text-muted); font-size: 0.9rem; margin-top: 10px;">
                        Keine Blueprints gefunden.<br />Erstellen Sie zuerst einen Snapshot der aktuellen Konfiguration.
                    </p>
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
