<template>
  <div class="ce-container">
    <!-- HEADER -->
    <div class="ce-header">
      <div class="ce-title-row">
        <div>
          <h2 class="ce-title">Content Editor</h2>
          <p class="ce-subtitle">Manage game content blueprints — hardware templates, regions, anomalies, and reward bundles.</p>
        </div>
        <div class="header-actions">
          <div class="type-tabs">
            <button v-for="et in entityTypes" :key="et.id"
                    :class="['type-tab', { active: selectedType === et.id }]"
                    @click="selectedType = et.id">
              <span class="tab-icon">{{ getEntityIcon(et.id) }}</span>
              {{ et.label }}
            </button>
          </div>
          <button @click="openCreate" class="create-btn">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New {{ selectedTypeLabel }}
          </button>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-ring"></div>
      <span>Loading content entities...</span>
    </div>

    <!-- ENTITY GRID -->
    <div v-else class="entity-grid">
      <div v-for="entity in sortedEntities" :key="entity.id" class="entity-card">
        <div class="card-top">
          <div class="card-icon-wrap">
            <span class="card-icon">{{ getEntityIcon(selectedType) }}</span>
          </div>
          <div class="card-actions-top">
            <button @click="openEdit(entity)" class="mini-btn" title="Edit">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button @click="duplicate(entity)" class="mini-btn" title="Duplicate">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <button @click="confirmDelete(entity)" class="mini-btn mini-btn-red" title="Delete">✕</button>
          </div>
        </div>

        <h4 class="entity-name">{{ entity.name }}</h4>
        <span class="entity-id">ID: {{ entity.id }}</span>

        <div class="entity-stats">
          <div v-for="(val, k) in getKeyStats(entity)" :key="k" class="stat-item">
            <span class="stat-key">{{ k.replace(/_/g, ' ') }}</span>
            <span class="stat-val">{{ val }}</span>
          </div>
        </div>
      </div>

      <!-- EMPTY STATE -->
      <div v-if="!sortedEntities.length" class="empty-card">
        <span class="empty-icon">🧊</span>
        <p>No {{ selectedTypeLabel }} entities found. Create one to get started.</p>
      </div>
    </div>

    <!-- MODAL: CREATE / EDIT -->
    <div v-if="modal.show" class="overlay" @click.self="modal.show = false">
      <div class="modal">
        <div class="modal-bar modal-bar-blue"></div>
        <div class="modal-header">
          <h3 class="modal-title">{{ modal.isEdit ? 'Edit' : 'Create' }} {{ selectedTypeLabel }}</h3>
          <button @click="modal.show = false" class="modal-close">✕</button>
        </div>

        <div class="modal-body">
          <div class="modal-fields">
            <div v-for="field in getFieldsForType(selectedType)" :key="field.key" class="field">
              <label>{{ field.label }}</label>
              <textarea v-if="field.type === 'textarea'" v-model="modal.data[field.key]" rows="3"></textarea>
              <input v-else :type="field.type || 'text'" v-model="modal.data[field.key]" />
              <p v-if="field.help" class="field-help">{{ field.help }}</p>
            </div>
          </div>

          <div class="modal-preview">
            <span class="preview-label">Impact Preview</span>
            <div class="preview-box">
              <div class="preview-icon-wrap">
                <span class="preview-icon">{{ getEntityIcon(selectedType) }}</span>
              </div>
              <span class="preview-name">{{ modal.data.name || 'Untitled' }}</span>
              <div class="preview-metrics">
                <div class="metric">
                  <span class="metric-key">Balance Impact</span>
                  <span class="metric-val">~0.02%</span>
                </div>
                <div class="metric">
                  <span class="metric-key">Risk Score</span>
                  <span class="metric-val">Low</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button @click="modal.show = false" class="btn-cancel">Cancel</button>
          <button @click="saveEntity" class="btn-primary">{{ modal.isEdit ? 'Update' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const selectedType = ref('servers');
const entities = ref([]);
const loading = ref(true);

const entityTypes = [
  { id: 'servers', label: 'Hardware' },
  { id: 'regions', label: 'Regions' },
  { id: 'anomalies', label: 'Events' },
  { id: 'rewards', label: 'Rewards' },
];

const selectedTypeLabel = computed(() => entityTypes.find(t => t.id === selectedType.value)?.label || 'Entity');

const fetchEntities = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/content/entities');
    if (res.success) entities.value = res.entities;
  } catch (e) { addToast('Failed to load content entities.', 'error'); }
  finally { loading.value = false; }
};

const sortedEntities = computed(() => entities.value.filter(e => e.type === selectedType.value));
const getEntityIcon = (type) => ({ servers: '🖥️', regions: '🗺️', anomalies: '🌋', rewards: '🎁' }[type] || '📦');

const getFieldsForType = (type) => {
  const common = [{ key: 'name', label: 'Name', type: 'text', help: 'Unique display name for this entity.' }];
  if (type === 'servers') return [...common, { key: 'u_size', label: 'Unit Size (U)', type: 'number' }, { key: 'base_power', label: 'Base Power (W)', type: 'number' }];
  if (type === 'regions') return [...common, { key: 'income_multiplier', label: 'Income Multiplier', type: 'number' }, { key: 'description', label: 'Description', type: 'textarea' }];
  if (type === 'anomalies') return [...common, { key: 'severity', label: 'Severity (1–10)', type: 'number' }, { key: 'duration', label: 'Duration (min)', type: 'number' }];
  if (type === 'rewards') return [...common, { key: 'xp_amount', label: 'XP Amount', type: 'number' }, { key: 'money_amount', label: 'Money Amount', type: 'number' }];
  return common;
};

const getKeyStats = (entity) => {
  if (entity.type === 'servers') return { Unit_Size: (entity.u_size || '?') + 'U', Power: (entity.base_power || '?') + 'W' };
  if (entity.type === 'regions') return { Multiplier: (entity.income_multiplier || 1) + 'x', Status: 'Active' };
  if (entity.type === 'anomalies') return { Severity: entity.severity || 'N/A', Duration: (entity.duration || '?') + 'min' };
  if (entity.type === 'rewards') return { XP: entity.xp_amount || 0, Money: '$' + (entity.money_amount || 0) };
  return { Status: 'OK' };
};

const modal = ref({ show: false, isEdit: false, data: {}, entity: null });
const openCreate = () => { modal.value = { show: true, isEdit: false, data: { name: '', type: selectedType.value }, entity: null }; };
const openEdit = (entity) => { modal.value = { show: true, isEdit: true, data: { ...entity }, entity }; };

const saveEntity = async () => {
  setGlobalLoading(true);
  try {
    await api.post('/admin/content/entities/save', modal.value.data);
    addToast('Entity saved.', 'success');
    modal.value.show = false;
    fetchEntities();
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

const duplicate = (entity) => {
  const copy = { ...entity, name: entity.name + ' (Copy)', id: null };
  modal.value = { show: true, isEdit: false, data: copy, entity: null };
};

const confirmDelete = (entity) => {
  requestConfirm(`Delete "${entity.name}"? This action is irreversible.`, async () => {
    setGlobalLoading(true);
    try {
      await api.post('/admin/content/entities/delete', { id: entity.id });
      addToast('Entity removed.', 'info');
      fetchEntities();
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

onMounted(fetchEntities);
</script>

<style scoped>
.ce-container { display: flex; flex-direction: column; }
.ce-header { margin-bottom: 24px; }
.ce-title-row { display: flex; flex-direction: column; gap: 16px; }
.ce-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.ce-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }

.type-tabs { display: flex; gap: 4px; padding: 4px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 12px; }
.type-tab {
  display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px;
  font-size: 0.65rem; font-weight: 700; color: #52525b; cursor: pointer; border: none;
  background: transparent; transition: all 0.15s; white-space: nowrap;
}
.type-tab:hover { color: #a1a1aa; background: #111; }
.type-tab.active { color: #3b82f6; background: #0c1222; }
.tab-icon { font-size: 0.9rem; }

.create-btn {
  display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px;
  background: #0c1222; border: 1px solid #1e3a5f; color: #60a5fa;
  font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.create-btn:hover { background: #1e3a5f; }

/* LOADER */
.loader-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 80px 0; color: #3f3f46; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; }
.loader-ring { width: 32px; height: 32px; border: 2px solid #18181b; border-top-color: #3b82f6; border-radius: 50%; animation: spin360 0.8s linear infinite; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ENTITY GRID */
.entity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }

.entity-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 20px;
  transition: all 0.2s; position: relative;
}
.entity-card:hover { border-color: #27272a; }

.card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
.card-icon-wrap { width: 40px; height: 40px; background: #111; border: 1px solid #1c1c1e; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.card-icon { font-size: 1.1rem; }
.card-actions-top { display: flex; gap: 4px; }
.mini-btn {
  width: 28px; height: 28px; border-radius: 8px; background: #111; border: 1px solid #1c1c1e;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: all 0.15s; color: #52525b; font-size: 10px; font-weight: 900;
}
.mini-btn svg { color: #52525b; }
.mini-btn:hover { border-color: #333; background: #1a1a1a; }
.mini-btn:hover svg { color: #fafafa; }
.mini-btn-red:hover { border-color: #7f1d1d; background: #450a0a; color: #f87171; }

.entity-name { font-size: 0.85rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0 0 2px 0; }
.entity-id { font-size: 0.55rem; color: #27272a; font-family: 'JetBrains Mono', monospace; display: block; margin-bottom: 14px; }

.entity-stats { display: flex; gap: 16px; padding-top: 12px; border-top: 1px solid #111; }
.stat-item { display: flex; flex-direction: column; gap: 2px; }
.stat-key { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }
.stat-val { font-size: 0.72rem; font-weight: 800; color: #a1a1aa; }

.empty-card {
  grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 60px 20px; background: #0a0a0c; border: 1px dashed #18181b; border-radius: 16px; color: #27272a;
}
.empty-icon { font-size: 2rem; margin-bottom: 10px; }
.empty-card p { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; }

/* OVERLAY + MODAL */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; padding: 32px; width: 720px; position: relative; animation: modalIn 0.3s ease; }
.modal-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 20px 20px 0 0; }
.modal-bar-blue { background: #3b82f6; }

.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.modal-title { font-size: 1.2rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0; }
.modal-close { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: 900; }
.modal-close:hover { color: #fafafa; border-color: #333; }

.modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.modal-fields { display: flex; flex-direction: column; gap: 14px; }

.field label { display: block; font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px; }
.field input, .field select, .field textarea {
  width: 100%; height: 36px; padding: 0 12px; background: #111; border: 1px solid #222;
  border-radius: 8px; color: #fafafa; font-size: 0.72rem; font-weight: 700; outline: none; box-sizing: border-box;
}
.field textarea { height: 80px; padding: 10px 12px; resize: vertical; font-family: inherit; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: #3b82f6; }
.field-help { font-size: 0.5rem; color: #27272a; font-weight: 600; font-style: italic; margin-top: 4px; }

.modal-preview { display: flex; flex-direction: column; gap: 8px; }
.preview-label { font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.preview-box {
  flex: 1; background: #111; border: 1px solid #1c1c1e; border-radius: 14px; padding: 24px;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
}
.preview-icon-wrap { width: 48px; height: 48px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
.preview-icon { font-size: 1.4rem; }
.preview-name { font-size: 0.8rem; font-weight: 900; color: #fafafa; font-style: italic; text-align: center; }
.preview-metrics { display: flex; gap: 20px; margin-top: 8px; }
.metric { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.metric-key { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.metric-val { font-size: 0.72rem; font-weight: 800; color: #71717a; font-family: 'JetBrains Mono', monospace; }

.modal-actions { display: flex; gap: 10px; padding-top: 20px; border-top: 1px solid #18181b; }
.btn-cancel { flex: 1; height: 40px; border-radius: 10px; background: #111; border: 1px solid #222; color: #71717a; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { color: #fafafa; border-color: #333; }
.btn-primary { flex: 1; height: 40px; border-radius: 10px; background: #0c1222; border: 1px solid #1e3a5f; color: #60a5fa; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-primary:hover { background: #1e3a5f; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>
