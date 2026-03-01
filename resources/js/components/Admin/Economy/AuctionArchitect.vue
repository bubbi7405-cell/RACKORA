<template>
  <div class="ara-container">
    <!-- HEADER -->
    <div class="ara-header">
      <div class="ara-title-row">
        <div>
          <h2 class="ara-title">Auction Architect</h2>
          <p class="ara-subtitle">Liquidation Protocols — Manage high-volatility hardware liquidation events.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchAuctions" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync Matrix
           </button>
           <button @click="triggerLiquidation" class="sys-btn sys-btn-primary" :disabled="loading || triggering">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
             {{ triggering ? 'Injecting...' : 'Force Liquidation' }}
           </button>
        </div>
      </div>
    </div>

    <!-- METRICS -->
    <div class="ara-metrics">
       <div class="metric-card">
          <span class="m-label">Active Lots</span>
          <span class="m-val">{{ activeCount }}</span>
       </div>
       <div class="metric-card">
          <span class="m-label">Total Volume</span>
          <span class="m-val text-emerald-400">${{ formatNumber(totalVolume) }}</span>
       </div>
       <div class="metric-card">
          <span class="m-label">Historical Lots</span>
          <span class="m-val text-zinc-500">{{ totalCount }}</span>
       </div>
    </div>
    <!-- CONFIGURATION PANEL -->
    <div class="ara-config glass-effect" :class="{ 'is-collapsed': configCollapsed }">
      <div class="config-header" @click="configCollapsed = !configCollapsed">
        <div class="header-left">
          <div class="panel-accent accent-indigo"></div>
          <h3 class="panel-title">Auction Protocols</h3>
        </div>
        <div class="header-right">
          <span class="status-indicator" :class="{ 'unsaved': isDirty }">{{ isDirty ? 'Unsaved Mutations' : 'Synced' }}</span>
          <svg class="chevron" :class="{ 'rotated': !configCollapsed }" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <div class="config-content" v-if="!configCollapsed">
        <div class="settings-grid">
          <!-- Probabilities -->
          <div class="settings-group">
            <h4 class="group-title">Lot Probabilities (Σ=100)</h4>
            <div class="input-row">
              <div class="input-field">
                <label>Server %</label>
                <input type="number" v-model.number="settings.probability_server" />
              </div>
              <div class="input-field">
                <label>Rack %</label>
                <input type="number" v-model.number="settings.probability_rack" />
              </div>
              <div class="input-field">
                <label>Comp %</label>
                <input type="number" v-model.number="settings.probability_component" />
              </div>
            </div>
            <p class="error-text" v-if="totalProb !== 100">Total probability must equal 100% (Current: {{ totalProb }}%)</p>
          </div>

          <!-- Condition -->
          <div class="settings-group">
            <h4 class="group-title">Condition Variance (%)</h4>
            <div class="input-row">
              <div class="input-field">
                <label>Minimum</label>
                <input type="number" v-model.number="settings.condition_min" />
              </div>
              <div class="input-field">
                <label>Maximum</label>
                <input type="number" v-model.number="settings.condition_max" />
              </div>
            </div>
          </div>

          <!-- Pricing -->
          <div class="settings-group">
            <h4 class="group-title">Price Multiplier (Base)</h4>
            <div class="input-row">
              <div class="input-field">
                <label>Min (e.g. 0.2)</label>
                <input type="number" step="0.1" v-model.number="settings.price_multiplier_min" />
              </div>
              <div class="input-field">
                <label>Max (e.g. 0.4)</label>
                <input type="number" step="0.1" v-model.number="settings.price_multiplier_max" />
              </div>
            </div>
          </div>

          <!-- Duration -->
          <div class="settings-group">
            <h4 class="group-title">Epoch Duration (m)</h4>
            <div class="input-row">
              <div class="input-field">
                <label>Minimum</label>
                <input type="number" v-model.number="settings.duration_min" />
              </div>
              <div class="input-field">
                <label>Maximum</label>
                <input type="number" v-model.number="settings.duration_max" />
              </div>
            </div>
          </div>
        </div>

        <div class="config-actions">
           <button @click="saveSettings" :disabled="!isDirty || totalProb !== 100 || savingSettings" class="sys-btn sys-btn-indigo">
             {{ savingSettings ? 'Calibrating...' : 'Commit Protocol' }}
           </button>
        </div>
      </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="ara-table-wrap glass-effect">
      <table class="ara-table">
        <thead>
          <tr>
            <th>Identity</th>
            <th>Specification</th>
            <th>Seller</th>
            <th>Price Status</th>
            <th>Logic Duration</th>
            <th class="text-right">Overrides</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="auction in auctions" :key="auction.id" :class="{ 'processed': auction.is_processed }">
            <td>
              <div class="lot-identity">
                <div class="lot-icon" :class="auction.item_type">
                   {{ auction.item_type.charAt(0).toUpperCase() }}
                </div>
                <div class="lot-meta">
                  <span class="lot-id">#{{ auction.id.substr(0,8) }}</span>
                  <span class="lot-name">{{ auction.item_key.replace(/_/g, ' ') }}</span>
                </div>
              </div>
            </td>
            <td>
              <div class="lot-specs">
                <span class="spec-pill" :class="getConditionClass(auction.condition)">COND: {{ auction.condition }}%</span>
                <span class="spec-pill risk">RISK: {{ auction.defect_chance }}%</span>
              </div>
            </td>
            <td>
              <span class="seller-name">{{ auction.seller_name }}</span>
            </td>
            <td>
              <div class="price-stack">
                <span class="current-price">${{ formatNumber(auction.current_bid || auction.starting_price) }}</span>
                <span class="start-price">START: ${{ formatNumber(auction.starting_price) }}</span>
              </div>
            </td>
            <td>
              <div class="time-stack">
                <span class="time-val" :class="{ 'expired': isExpired(auction) }">
                  {{ isExpired(auction) ? 'PROCESSED' : formatRemaining(auction.ends_at) }}
                </span>
                <span class="time-label">{{ isExpired(auction) ? 'Archived' : 'Active Feedback' }}</span>
              </div>
            </td>
            <td class="text-right">
              <button @click="deleteAuction(auction.id)" class="purge-btn" title="Purge Node">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </td>
          </tr>
          <tr v-if="!auctions.length && !loading">
            <td colspan="6" class="empty-row">No liquidation nodes detected in current epoch.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <div class="ara-pagination" v-if="totalPages > 1">
       <button @click="page--" :disabled="page <= 1" class="page-btn">PREV</button>
       <span class="page-info">PAGE {{ page }} OF {{ totalPages }}</span>
       <button @click="page++" :disabled="page >= totalPages" class="page-btn">NEXT</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject, computed, watch } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const loading = ref(true);
const triggering = ref(false);
const auctions = ref([]);
const page = ref(1);
const totalPages = ref(1);
const totalCount = ref(0);

const configCollapsed = ref(true);
const savingSettings = ref(false);
const settings = ref({
  probability_server: 60,
  probability_rack: 30,
  probability_component: 10,
  condition_min: 15,
  condition_max: 80,
  price_multiplier_min: 0.2,
  price_multiplier_max: 0.4,
  duration_min: 5,
  duration_max: 15
});
const originalSettings = ref(null);

const activeCount = computed(() => auctions.value.filter(a => !a.is_processed && new Date(a.ends_at) > new Date()).length);
const totalVolume = computed(() => auctions.value.reduce((acc, a) => acc + (a.current_bid || a.starting_price), 0));

const totalProb = computed(() => settings.value.probability_server + settings.value.probability_rack + settings.value.probability_component);
const isDirty = computed(() => originalSettings.value && JSON.stringify(settings.value) !== originalSettings.value);

const fetchAuctions = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/auctions', { params: { page: page.value } });
    if (res.success) {
      auctions.value = res.data.data;
      totalPages.value = res.data.last_page;
      totalCount.value = res.data.total;
    }
  } catch (e) {
    addToast('Auction matrix link failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchSettings = async () => {
    try {
        const res = await api.get('/admin/configs');
        if (res.success) {
            const flat = [].concat(...Object.values(res.configs));
            const found = flat.find(c => c.key === 'auction_settings');
            if (found) {
                const val = JSON.parse(found.value);
                settings.value = { ...settings.value, ...val };
                originalSettings.value = JSON.stringify(settings.value);
            }
        }
    } catch (e) { console.error(e); }
};

const saveSettings = async () => {
    savingSettings.value = true;
    try {
        await api.post('/admin/configs/update', {
            key: 'auction_settings',
            value: JSON.stringify(settings.value),
            comment: 'Auction protocol calibration updated.'
        });
        addToast('Auction protocols updated.', 'success');
        originalSettings.value = JSON.stringify(settings.value);
    } catch (e) { addToast(e.message, 'error'); }
    finally { savingSettings.value = false; }
};

const triggerLiquidation = async () => {
  triggering.value = true;
  try {
    const res = await api.post('/admin/auctions/trigger');
    if (res.success) {
      addToast('Liquidation sequence initiated.', 'success');
      fetchAuctions();
    }
  } catch (e) {
    addToast('Injection failed: ' + e.message, 'error');
  } finally {
    triggering.value = false;
  }
};

const deleteAuction = (id) => {
  requestConfirm('Purge this auction lot? Active bids will be refunded.', async () => {
    try {
      const res = await api.delete(`/admin/auctions/${id}`);
      if (res.success) {
        addToast('Auction lot purged.', 'info');
        fetchAuctions();
      }
    } catch (e) {
      addToast(e.message, 'error');
    }
  });
};

const isExpired = (auction) => auction.is_processed || new Date(auction.ends_at) <= new Date();

const formatRemaining = (endsAt) => {
  const diff = new Date(endsAt) - new Date();
  if (diff <= 0) return 'ENDING...';
  const m = Math.floor(diff / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  return `${m}m ${s}s`;
};

const getConditionClass = (cond) => {
  if (cond > 70) return 'cond-high';
  if (cond > 40) return 'cond-mid';
  return 'cond-low';
};

const formatNumber = (n) => new Intl.NumberFormat('en-US').format(Math.floor(n));

watch(page, fetchAuctions);

onMounted(() => {
    fetchAuctions();
    fetchSettings();
});
</script>

<style scoped>
.ara-container { display: flex; flex-direction: column; gap: 32px; }

.ara-header { display: flex; flex-direction: column; gap: 24px; }
.ara-title-row { display: flex; justify-content: space-between; align-items: start; }
.ara-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.ara-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }

.ara-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.metric-card { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 4px; }
.m-label { font-size: 0.6rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
.m-val { font-size: 1.25rem; font-weight: 900; color: white; font-family: var(--sys-font-mono); }

.ara-table-wrap { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; overflow: hidden; }
.ara-table { width: 100%; border-collapse: collapse; }
.ara-table th { padding: 16px 24px; font-size: 0.55rem; color: #3f3f46; font-weight: 900; text-transform: uppercase; letter-spacing: 0.15em; text-align: left; border-bottom: 1px solid #18181b; background: #050505; }
.ara-table td { padding: 20px 24px; border-bottom: 1px solid #0e0e10; }
.ara-table tr.processed { opacity: 0.5; }

.lot-identity { display: flex; align-items: center; gap: 16px; }
.lot-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.9rem; }
.lot-icon.server { background: #1e3a5f; color: #3b82f6; }
.lot-icon.rack { background: #3b2006; color: #f59e0b; }
.lot-icon.component { background: #063b3b; color: #10b981; }

.lot-meta { display: flex; flex-direction: column; }
.lot-id { font-size: 0.55rem; color: #3f3f46; font-family: var(--sys-font-mono); font-weight: 700; }
.lot-name { font-size: 0.85rem; font-weight: 800; color: white; text-transform: uppercase; letter-spacing: -0.01em; }

.lot-specs { display: flex; gap: 8px; }
.spec-pill { font-size: 0.5rem; font-weight: 900; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; background: #111; }
.cond-high { color: #10b981; }
.cond-mid { color: #f59e0b; }
.cond-low { color: #ef4444; }
.risk { color: #3f3f46; }

.seller-name { font-size: 0.75rem; color: #a1a1aa; font-weight: 700; }

.price-stack { display: flex; flex-direction: column; }
.current-price { font-size: 0.9rem; font-weight: 900; color: #4ade80; font-family: var(--sys-font-mono); }
.start-price { font-size: 0.55rem; color: #3f3f46; font-weight: 700; }

.time-stack { display: flex; flex-direction: column; }
.time-val { font-size: 0.85rem; font-weight: 800; color: #3b82f6; font-family: var(--sys-font-mono); }
.time-val.expired { color: #3f3f46; }
.time-label { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }

.purge-btn { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #1c1c1e; color: #3f3f46; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.purge-btn:hover { background: #450a0a; color: #ef4444; border-color: #991b1b; }

.ara-pagination { display: flex; align-items: center; justify-content: center; gap: 24px; margin-top: 16px; }
.page-btn { background: #111; border: 1px solid #18181b; color: #52525b; padding: 8px 16px; border-radius: 8px; font-size: 0.6rem; font-weight: 900; cursor: pointer; }
.page-btn:hover:not(:disabled) { color: white; border-color: #3b82f6; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-info { font-size: 0.65rem; color: #3f3f46; font-weight: 800; letter-spacing: 0.05em; }

.empty-row { text-align: center; padding: 60px; color: #3f3f46; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; }

/* CONFIG PANEL */
.ara-config { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; overflow: hidden; transition: all 0.3s; }
.config-header { padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #050505; border-bottom: 1px solid #18181b; }
.config-header:hover { background: #08080a; }

.header-left { display: flex; align-items: center; gap: 12px; }
.header-right { display: flex; align-items: center; gap: 16px; }

.panel-accent { width: 3px; height: 16px; border-radius: 99px; }
.accent-indigo { background: #6366f1; }
.status-indicator { font-size: 0.55rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #3f3f46; }
.status-indicator.unsaved { color: #f59e0b; }

.chevron { color: #3f3f46; transition: transform 0.3s; }
.chevron.rotated { transform: rotate(180deg); }

.config-content { padding: 24px; }
.settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }

.settings-group { display: flex; flex-direction: column; gap: 12px; }
.group-title { font-size: 0.65rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }

.input-row { display: flex; gap: 12px; }
.input-field { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.input-field label { font-size: 0.55rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.input-field input { background: #050505; border: 1px solid #1c1c1e; border-radius: 8px; height: 36px; padding: 0 12px; color: #fff; font-family: var(--sys-font-mono); font-size: 0.72rem; font-weight: 700; outline: none; }
.input-field input:focus { border-color: #6366f1; }

.error-text { font-size: 0.55rem; color: #ef4444; font-weight: 800; margin-top: 4px; }

.config-actions { margin-top: 24px; padding-top: 24px; border-top: 1px solid #18181b; display: flex; justify-content: flex-end; }

/* UTILS */
.sys-btn { height: 40px; padding: 0 20px; border-radius: 10px; font-size: 0.72rem; font-weight: 800; display: flex; align-items: center; gap: 10px; cursor: pointer; border: none; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; }
.sys-btn-primary { background: #1e3a5f; color: #3b82f6; border: 1px solid #1e40af33; }
.sys-btn-primary:hover { background: #1e40af; color: white; }
.sys-btn-secondary { background: #111; color: #a1a1aa; border: 1px solid #18181b; }
.sys-btn-secondary:hover { background: #18181b; color: white; }
.sys-btn-indigo { background: #312e81; color: #818cf8; border: 1px solid #4338ca33; }
.sys-btn-indigo:hover:not(:disabled) { background: #4338ca; color: white; }
.sys-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.text-zinc-500 { color: #71717a; }
.text-emerald-400 { color: #34d399; }
</style>
