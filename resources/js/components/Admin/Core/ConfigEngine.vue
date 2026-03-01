<template>
  <div class="ce-container">
    <!-- HEADER -->
    <div class="ce-header">
      <div class="ce-title-row">
        <div>
          <h2 class="ce-title">Infrastructure Core</h2>
          <p class="ce-subtitle">Manage engine parameters, operational variables, and versioned configuration history.</p>
        </div>
        <div class="search-box">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input v-model="search" placeholder="Search configs..." />
        </div>
      </div>
    </div>

    <!-- CONFIG GROUPS -->
    <div class="config-groups">
      <div v-for="(group, name) in configGroups" :key="name" class="group-section">
        <div class="group-header">
          <div class="panel-accent accent-blue"></div>
          <h3 class="group-name">{{ name }}</h3>
          <span class="group-count">{{ group.length }}</span>
        </div>

        <div class="config-grid">
          <div v-for="config in group" :key="config.key" class="config-card">
            <div class="config-top">
              <div>
                <span class="config-label">{{ config.label || config.key }}</span>
                <span class="config-key">{{ config.key }}</span>
              </div>
              <button @click="viewHistory(config.key)" class="history-btn" title="View History">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              </button>
            </div>

            <!-- JSON values → click to edit -->
            <div v-if="isJson(config)" class="json-preview" @click="openEditor(config)">
              <pre class="json-text">{{ config.value }}</pre>
              <div class="json-overlay">
                <span>Edit Manifest</span>
              </div>
            </div>

            <!-- Scalar values → inline edit -->
            <div v-else class="scalar-input">
              <input v-model="config.value" @change="saveConfig(config)" />
            </div>

            <p class="config-desc">{{ config.description || 'Operational parameter.' }}</p>
          </div>
        </div>
      </div>

      <div v-if="!Object.keys(configGroups).length" class="empty-state">
        <span class="empty-icon">🔍</span>
        <p>No configs matching "{{ search }}"</p>
      </div>
    </div>

    <!-- MODAL: JSON EDITOR -->
    <div v-if="editor.show" class="overlay" @click.self="editor.show = false">
      <div class="modal">
        <div class="modal-bar modal-bar-blue"></div>
        <div class="modal-header">
          <div>
            <h3 class="modal-title">{{ editor.config?.key }}</h3>
            <p class="modal-sub">Edit the JSON manifest below. Ensure valid JSON before committing.</p>
          </div>
          <button @click="editor.show = false" class="modal-close">✕</button>
        </div>

        <div class="editor-wrap">
          <textarea v-model="editor.tempValue" class="editor-textarea" spellcheck="false"></textarea>
        </div>

        <div class="modal-actions">
          <button @click="editor.show = false" class="btn-cancel">Discard</button>
          <button @click="commitEditor" class="btn-primary">Commit</button>
        </div>
      </div>
    </div>

    <!-- DRAWER: VERSION HISTORY -->
    <div v-if="history.show" class="overlay" @click.self="history.show = false">
      <div class="drawer">
        <div class="drawer-header">
          <div>
            <h3 class="drawer-title">{{ history.key }}</h3>
            <span class="drawer-sub">Version history and rollback</span>
          </div>
          <button @click="history.show = false" class="drawer-close">✕</button>
        </div>

        <div class="history-list">
          <div v-for="log in history.logs" :key="log.id" class="history-entry">
            <div class="history-meta">
              <div class="history-user">
                <div class="history-avatar">{{ log.user?.name?.charAt(0)?.toUpperCase() || 'S' }}</div>
                <div>
                  <span class="history-name">{{ log.user?.name || 'SYSTEM' }}</span>
                  <span class="history-time">{{ log.created_at }}</span>
                </div>
              </div>
              <button @click="rollback(log)" class="rollback-btn">Restore</button>
            </div>

            <div class="diff-box">
              <div class="diff-col">
                <span class="diff-label">Previous</span>
                <span class="diff-val diff-old">{{ log.old_value }}</span>
              </div>
              <div class="diff-col">
                <span class="diff-label">Committed</span>
                <span class="diff-val diff-new">{{ log.new_value }}</span>
              </div>
            </div>

            <p class="history-comment">"{{ log.comment || 'System calibration.' }}"</p>
          </div>

          <div v-if="!history.logs.length" class="empty-history">No version history available.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, requestConfirm, setGlobalLoading } = inject('adminContext');

const configs = ref({});
const search = ref('');
const loading = ref(true);
const editor = ref({ show: false, config: null, tempValue: '' });
const history = ref({ show: false, key: '', logs: [] });

const fetchConfigs = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    if (res.success) configs.value = res.configs;
  } catch (e) { addToast('Failed to sync configuration.', 'error'); }
  finally { loading.value = false; }
};

const configGroups = computed(() => {
  const result = {};
  for (const [group, items] of Object.entries(configs.value)) {
    const filtered = items.filter(item =>
      item.key.toLowerCase().includes(search.value.toLowerCase()) ||
      (item.label && item.label.toLowerCase().includes(search.value.toLowerCase()))
    );
    if (filtered.length > 0) result[group] = filtered;
  }
  return result;
});

const isJson = (config) => {
  try { return typeof JSON.parse(config.value) === 'object'; } catch { return false; }
};

const saveConfig = async (config) => {
  setGlobalLoading(true);
  try {
    await api.post('/admin/configs/update', { key: config.key, value: config.value, comment: 'Direct mutation via Infrastructure Core.' });
    addToast(`Updated: ${config.key}`, 'success');
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

const openEditor = (config) => {
  editor.value.config = config;
  try { editor.value.tempValue = JSON.stringify(JSON.parse(config.value), null, 4); }
  catch { editor.value.tempValue = config.value; }
  editor.value.show = true;
};

const commitEditor = async () => {
  try {
    JSON.parse(editor.value.tempValue);
    editor.value.config.value = JSON.stringify(JSON.parse(editor.value.tempValue));
    await saveConfig(editor.value.config);
    editor.value.show = false;
  } catch { addToast('Invalid JSON syntax.', 'error'); }
};

const viewHistory = async (key) => {
  setGlobalLoading(true);
  try {
    const res = await api.get(`/admin/configs/history/${key}`);
    if (res.success) {
      history.value = { show: true, key, logs: res.history || [] };
    }
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

const rollback = (log) => {
  requestConfirm(`Restore to version from ${log.created_at}? This will overwrite the current value.`, async () => {
    setGlobalLoading(true);
    try {
      await api.post('/admin/configs/rollback', { history_id: log.id });
      addToast('Rollback successful.', 'success');
      history.value.show = false;
      fetchConfigs();
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

onMounted(fetchConfigs);
</script>

<style scoped>
.ce-container { display: flex; flex-direction: column; }
.ce-header { margin-bottom: 24px; }
.ce-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.ce-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.ce-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.search-box { display: flex; align-items: center; gap: 10px; padding: 0 14px; height: 40px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 10px; min-width: 260px; }
.search-box svg { color: #3f3f46; flex-shrink: 0; }
.search-box input { background: none; border: none; color: #fafafa; font-size: 0.72rem; font-weight: 600; outline: none; width: 100%; }
.search-box input::placeholder { color: #27272a; }

/* GROUPS */
.config-groups { display: flex; flex-direction: column; gap: 28px; }
.group-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.panel-accent { width: 3px; height: 18px; border-radius: 99px; }
.accent-blue { background: #3b82f6; }
.group-name { font-size: 0.75rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.08em; font-style: italic; margin: 0; }
.group-count { margin-left: auto; font-size: 0.55rem; font-weight: 900; color: #3f3f46; background: #111; padding: 2px 10px; border-radius: 6px; }

.config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }

.config-card { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 20px; transition: all 0.2s; }
.config-card:hover { border-color: #27272a; }

.config-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
.config-label { display: block; font-size: 0.72rem; font-weight: 800; color: #e4e4e7; }
.config-key { display: block; font-size: 0.55rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
.history-btn { width: 28px; height: 28px; border-radius: 8px; background: #111; border: 1px solid #1c1c1e; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; }
.history-btn svg { color: #3f3f46; }
.history-btn:hover { border-color: #1e3a5f; background: #0c1222; }
.history-btn:hover svg { color: #60a5fa; }

/* JSON PREVIEW */
.json-preview { position: relative; height: 80px; background: #050505; border: 1px solid #18181b; border-radius: 10px; overflow: hidden; cursor: pointer; margin-bottom: 10px; transition: border-color 0.15s; }
.json-preview:hover { border-color: #1e3a5f; }
.json-text { font-size: 0.55rem; font-family: 'JetBrains Mono', monospace; color: #3b82f6; opacity: 0.5; padding: 10px; margin: 0; overflow: hidden; height: 100%; white-space: pre-wrap; }
.json-overlay { position: absolute; inset-x: 0; bottom: 0; height: 28px; background: linear-gradient(to top, #050505, transparent); display: flex; align-items: center; justify-content: center; }
.json-overlay span { font-size: 0.5rem; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.15em; }

/* SCALAR INPUT */
.scalar-input { margin-bottom: 10px; }
.scalar-input input { width: 100%; height: 36px; padding: 0 12px; background: #111; border: 1px solid #1c1c1e; border-radius: 8px; color: #fafafa; font-size: 0.72rem; font-family: 'JetBrains Mono', monospace; font-weight: 700; outline: none; box-sizing: border-box; }
.scalar-input input:focus { border-color: #3b82f6; }

.config-desc { font-size: 0.55rem; color: #3f3f46; font-weight: 600; font-style: italic; margin: 0; }

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 60px; color: #27272a; }
.empty-icon { font-size: 2rem; }
.empty-state p { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; }

/* OVERLAY + MODAL */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; padding: 32px; width: 640px; position: relative; animation: modalIn 0.3s ease; }
.modal-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 20px 20px 0 0; }
.modal-bar-blue { background: #3b82f6; }
.modal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.modal-title { font-size: 1.1rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0; font-family: 'JetBrains Mono', monospace; }
.modal-sub { font-size: 0.6rem; color: #52525b; font-weight: 600; margin-top: 4px; }
.modal-close { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: 900; }
.modal-close:hover { color: #fafafa; border-color: #333; }

.editor-wrap { margin-bottom: 20px; }
.editor-textarea { width: 100%; height: 350px; background: #050505; border: 1px solid #18181b; border-radius: 12px; padding: 16px; color: #3b82f6; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; line-height: 1.8; outline: none; resize: vertical; box-sizing: border-box; }
.editor-textarea:focus { border-color: #1e3a5f; }
.editor-textarea::-webkit-scrollbar { width: 3px; }
.editor-textarea::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }

.modal-actions { display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid #18181b; }
.btn-cancel { flex: 1; height: 40px; border-radius: 10px; background: #111; border: 1px solid #222; color: #71717a; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { color: #fafafa; border-color: #333; }
.btn-primary { flex: 1; height: 40px; border-radius: 10px; background: #0c1222; border: 1px solid #1e3a5f; color: #60a5fa; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-primary:hover { background: #1e3a5f; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* DRAWER */
.drawer { position: fixed; right: 0; top: 0; width: 560px; height: 100vh; background: #0a0a0c; border-left: 1px solid #18181b; padding: 32px; overflow-y: auto; animation: slideLeft 0.4s cubic-bezier(0.16,1,0.3,1); }
.drawer::-webkit-scrollbar { width: 3px; }
.drawer::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }
@keyframes slideLeft { from { transform: translateX(100%); } to { transform: translateX(0); } }

.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #18181b; }
.drawer-title { font-size: 1.1rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0; font-family: 'JetBrains Mono', monospace; }
.drawer-sub { font-size: 0.6rem; color: #52525b; font-weight: 600; display: block; margin-top: 4px; }
.drawer-close { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: 900; }
.drawer-close:hover { color: #fafafa; border-color: #333; }

.history-list { display: flex; flex-direction: column; gap: 14px; }
.history-entry { background: #111; border: 1px solid #1c1c1e; border-radius: 14px; padding: 18px; }
.history-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.history-user { display: flex; align-items: center; gap: 10px; }
.history-avatar { width: 30px; height: 30px; border-radius: 8px; background: #18181b; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 900; color: #52525b; }
.history-name { display: block; font-size: 0.7rem; font-weight: 800; color: #e4e4e7; }
.history-time { display: block; font-size: 0.5rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; }

.rollback-btn { padding: 5px 14px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.2s; }
.rollback-btn:hover { background: #0c1222; border-color: #1e3a5f; color: #60a5fa; }

.diff-box { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
.diff-col { display: flex; flex-direction: column; gap: 4px; }
.diff-label { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.diff-val { font-size: 0.6rem; font-family: 'JetBrains Mono', monospace; overflow: hidden; text-overflow: ellipsis; }
.diff-old { color: #52525b; text-decoration: line-through; }
.diff-new { color: #4ade80; }

.history-comment { font-size: 0.6rem; color: #3f3f46; font-style: italic; margin: 0; }
.empty-history { text-align: center; padding: 40px 0; color: #27272a; font-size: 0.65rem; font-weight: 700; }
</style>
