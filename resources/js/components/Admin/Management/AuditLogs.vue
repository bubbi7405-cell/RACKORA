<template>
  <div class="al-container">
    <!-- HEADER -->
    <div class="al-header">
      <div class="al-title-row">
        <div>
          <h2 class="al-title">Audit Ledger</h2>
          <p class="al-subtitle">Immutable record of all administrative operations — config mutations, bans, resource grants.</p>
        </div>
        <button @click="fetchLogs" class="action-btn" :class="{ spin: loading }">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Sync
        </button>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-ring"></div>
      <span>Scanning archive ledger...</span>
    </div>

    <!-- TABLE -->
    <div v-else class="table-wrap">
      <table class="al-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Action</th>
            <th>Detail</th>
            <th>Operator</th>
            <th class="th-right">Forensics</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="log-row">
            <td>
              <div class="time-cell">
                <span class="time-main">{{ formatTime(log.created_at) }}</span>
                <span class="time-date">{{ formatDate(log.created_at) }}</span>
              </div>
            </td>
            <td>
              <div class="action-cell">
                <div class="action-icon" :class="getActionColor(log.action)">{{ getActionEmoji(log.action) }}</div>
                <div class="action-meta">
                  <span class="action-name">{{ formatAction(log.action) }}</span>
                  <span class="action-target">{{ log.target_type || '—' }}</span>
                </div>
              </div>
            </td>
            <td>
              <span class="detail-text">{{ log.message || 'Administrative operation committed.' }}</span>
            </td>
            <td>
              <div class="operator-cell">
                <div class="operator-avatar">{{ log.user?.name?.charAt(0)?.toUpperCase() || 'R' }}</div>
                <span class="operator-name">{{ log.user?.name || 'SYSTEM' }}</span>
              </div>
            </td>
            <td class="td-right">
              <button @click="viewDetail(log)" class="forensic-btn">View</button>
            </td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="5" class="empty-row">No entries in archive buffer.</td>
          </tr>
        </tbody>
      </table>
      <div class="table-footer">
        <span>{{ logs.length }} entries loaded</span>
      </div>
    </div>

    <!-- FORENSIC DETAIL MODAL -->
    <div v-if="detailModal.show" class="overlay" @click.self="detailModal.show = false">
      <div class="modal">
        <div class="modal-bar modal-bar-blue"></div>
        <div class="modal-header">
          <div>
            <h3 class="modal-title">Event Forensics</h3>
            <p class="modal-sub">Deep metadata analysis for audit entry.</p>
          </div>
          <button @click="detailModal.show = false" class="modal-close">✕</button>
        </div>

        <div class="forensic-layout">
          <!-- CORE META -->
          <div class="forensic-section meta-panel">
            <h4 class="section-label">Lattice Meta</h4>
            <div class="meta-list">
              <div v-for="(v, k) in getDisplayMeta(detailModal.log)" :key="k" class="meta-row">
                <span class="meta-key">{{ k }}</span>
                <span class="meta-val">{{ v }}</span>
              </div>
            </div>
            
            <div class="user-agent-box">
              <span class="ua-label">Uplink Signature</span>
              <p class="ua-text">{{ detailModal.log?.user_agent }}</p>
              <div class="ua-footer">
                <span class="ua-ip">IP: {{ detailModal.log?.ip_address }}</span>
              </div>
            </div>
          </div>

          <!-- DIFF VIEWER -->
          <div class="forensic-section diff-panel">
             <h4 class="section-label">Mutation Analysis (Old vs New)</h4>
             <div v-if="detailModal.log?.changes?.old !== undefined" class="diff-container custom-scrollbar">
                <div v-for="(change, key) in calculateDiff(detailModal.log.changes.old, detailModal.log.changes.new)" :key="key" class="diff-row">
                   <div class="diff-key">{{ key }}</div>
                   <div class="diff-split">
                      <div class="diff-old">{{ change.old }}</div>
                      <div class="diff-arrow">→</div>
                      <div class="diff-new">{{ change.new }}</div>
                   </div>
                </div>
                <div v-if="Object.keys(calculateDiff(detailModal.log.changes.old, detailModal.log.changes.new)).length === 0" class="diff-empty">
                   No scalar mutations detected. System states may be identical or complex nested objects.
                </div>
             </div>
             <div v-else class="payload-box custom-scrollbar">
                <pre class="payload-text">{{ JSON.stringify(detailModal.log?.changes || {}, null, 4) }}</pre>
             </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast } = inject('adminContext');

const logs = ref([]);
const loading = ref(true);
const detailModal = ref({ show: false, log: null });

const fetchLogs = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/audit-logs');
    if (res.success) logs.value = res.logs || [];
  } catch (e) { addToast('Failed to sync audit ledger.', 'error'); }
  finally { loading.value = false; }
};

const formatTime = (d) => {
  if (!d) return '--:--:--';
  const date = new Date(d);
  return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};
const formatDate = (d) => {
  if (!d) return '-- --- ----';
  const date = new Date(d);
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
const formatAction = (a) => (a || '').replace(/_/g, ' ');

const getActionEmoji = (a) => {
  if (!a) return '👁️';
  if (a.includes('ban')) return '🚫';
  if (a.includes('update')) return '📝';
  if (a.includes('resource') || a.includes('give')) return '💎';
  if (a.includes('config')) return '⚙️';
  if (a.includes('rollback')) return '⏪';
  if (a.includes('delete')) return '🗑️';
  return '👁️';
};

const getActionColor = (a) => {
  if (!a) return 'icon-zinc';
  if (a.includes('ban') || a.includes('delete')) return 'icon-red';
  if (a.includes('resource') || a.includes('give')) return 'icon-emerald';
  if (a.includes('rollback')) return 'icon-blue';
  return 'icon-zinc';
};

const viewDetail = (log) => {
  detailModal.value = { show: true, log };
};

const getDisplayMeta = (log) => {
  if (!log) return {};
  return {
    Action: log.action,
    Target: log.target_type ? `${log.target_type.split('\\').pop()} [${log.target_id}]` : 'CORE',
    Operator: log.user?.name || 'SYSTEM',
    Timestamp: new Date(log.created_at).toLocaleString(),
  };
};

const calculateDiff = (oldVal, newVal) => {
    if (!oldVal || !newVal || typeof oldVal !== 'object' || typeof newVal !== 'object') return {};
    const diff = {};
    const allKeys = new Set([...Object.keys(oldVal), ...Object.keys(newVal)]);
    
    allKeys.forEach(key => {
        const o = oldVal[key];
        const n = newVal[key];
        
        // Simple scalar diff
        if (JSON.stringify(o) !== JSON.stringify(n)) {
            if (typeof o !== 'object' && typeof n !== 'object') {
                diff[key] = { old: o ?? 'NULL', new: n ?? 'NULL' };
            } else {
                diff[key] = { old: '(Object)', new: '(Object)' };
            }
        }
    });
    return diff;
};

onMounted(fetchLogs);
</script>

<style scoped>
.al-container { display: flex; flex-direction: column; }
.al-header { margin-bottom: 24px; }
.al-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.al-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.al-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.action-btn { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: all 0.2s; border: 1px solid #222; background: #111; color: #a1a1aa; }
.action-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.action-btn.spin svg { animation: spin360 0.8s ease; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.loader-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 80px 0; color: #3f3f46; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; }
.loader-ring { width: 32px; height: 32px; border: 2px solid #18181b; border-top-color: #3b82f6; border-radius: 50%; animation: spin360 0.8s linear infinite; }

/* TABLE */
.table-wrap { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; overflow: hidden; }
.al-table { width: 100%; border-collapse: collapse; }
.al-table thead tr { background: #050505; }
.al-table th { padding: 14px 20px; font-size: 0.55rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.15em; text-align: left; border-bottom: 1px solid #18181b; }
.th-right { text-align: right; }
.al-table td { padding: 14px 20px; border-bottom: 1px solid #0e0e10; }
.log-row { transition: background 0.15s; }
.log-row:hover { background: rgba(255,255,255,0.015); }

.time-cell { display: flex; flex-direction: column; gap: 2px; }
.time-main { font-size: 0.72rem; font-weight: 800; color: #a1a1aa; font-family: 'JetBrains Mono', monospace; font-style: italic; }
.time-date { font-size: 0.55rem; color: #27272a; font-family: 'JetBrains Mono', monospace; }

.action-cell { display: flex; align-items: center; gap: 12px; }
.action-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.icon-red { background: #450a0a; }
.icon-emerald { background: #052e16; }
.icon-blue { background: #0c1222; }
.icon-zinc { background: #18181b; }
.action-name { font-size: 0.7rem; font-weight: 800; color: #e4e4e7; text-transform: uppercase; display: block; }
.action-target { font-size: 0.55rem; color: #3f3f46; font-weight: 700; display: block; }

.detail-text { font-size: 0.7rem; font-weight: 700; color: #71717a; font-style: italic; }

.operator-cell { display: flex; align-items: center; gap: 10px; }
.operator-avatar { width: 28px; height: 28px; border-radius: 8px; background: #18181b; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 900; color: #52525b; }
.operator-name { font-size: 0.68rem; font-weight: 700; color: #a1a1aa; }

.td-right { text-align: right; }
.forensic-btn { padding: 5px 14px; border-radius: 8px; background: #111; border: 1px solid #1c1c1e; color: #52525b; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.forensic-btn:hover { color: #3b82f6; border-color: #1e3a5f; background: #0c1222; }

.table-footer { padding: 14px 20px; border-top: 1px solid #18181b; font-size: 0.6rem; font-weight: 700; color: #27272a; text-transform: uppercase; letter-spacing: 0.1em; }
.empty-row { text-align: center; padding: 40px 20px; color: #27272a; font-size: 0.7rem; font-weight: 700; }

/* OVERLAY + MODAL */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; padding: 32px; width: 700px; position: relative; animation: modalIn 0.3s ease; }
.modal-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 20px 20px 0 0; }
.modal-bar-blue { background: #3b82f6; }
.modal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.modal-title { font-size: 1.2rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0; }
.modal-sub { font-size: 0.6rem; color: #52525b; font-weight: 600; margin-top: 4px; }
.modal-close { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: 900; }
.modal-close:hover { color: #fafafa; border-color: #333; }

.forensic-layout { display: grid; grid-template-columns: 260px 1fr; gap: 24px; }
.section-label { font-size: 0.6rem; font-weight: 800; color: #52525b; text-transform: uppercase; letter-spacing: 0.12em; margin: 0 0 12px 0; }
.meta-list { background: #111; border: 1px solid #1c1c1e; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.meta-row { display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid #111; }
.meta-row:last-child { border-bottom: none; padding-bottom: 0; }
.meta-key { font-size: 0.55rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.meta-val { font-size: 0.6rem; color: #a1a1aa; font-weight: 700; font-family: 'JetBrains Mono', monospace; text-align: right; }

.user-agent-box { margin-top: 16px; background: #050505; border: 1px solid #18181b; border-radius: 10px; padding: 12px; }
.ua-label { display: block; font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
.ua-text { font-size: 0.55rem; color: #52525b; line-height: 1.4; margin: 0; font-family: 'JetBrains Mono', monospace; }
.ua-footer { margin-top: 8px; padding-top: 8px; border-top: 1px solid #111; }
.ua-ip { font-size: 0.55rem; font-weight: 900; color: #3b82f6; font-family: 'JetBrains Mono', monospace; }

.diff-panel { display: flex; flex-direction: column; max-height: 400px; }
.diff-container { background: #050505; border: 1px solid #18181b; border-radius: 12px; overflow: auto; flex: 1; }
.diff-row { display: flex; flex-direction: column; gap: 4px; padding: 12px 16px; border-bottom: 1px solid #111; }
.diff-row:last-child { border-bottom: none; }
.diff-key { font-size: 0.55rem; font-weight: 900; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.05em; }
.diff-split { display: flex; align-items: center; gap: 12px; }
.diff-old { font-size: 0.65rem; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; text-decoration: line-through; }
.diff-arrow { font-size: 0.7rem; color: #3f3f46; }
.diff-new { font-size: 0.65rem; color: #22c55e; background: rgba(34, 197, 94, 0.1); padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700; }
.diff-empty { padding: 40px; text-align: center; color: #27272a; font-size: 0.65rem; font-style: italic; font-weight: 700; }

.payload-box { background: #050505; border: 1px solid #18181b; border-radius: 12px; padding: 16px; height: 100%; min-height: 200px; overflow: auto; }
.payload-text { font-size: 0.6rem; font-family: 'JetBrains Mono', monospace; color: #3b82f6; line-height: 1.8; white-space: pre-wrap; margin: 0; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>
