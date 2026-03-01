<template>
  <div class="eco-container">
    <!-- HEADER -->
    <div class="eco-header">
      <div class="eco-title-row">
        <div>
          <h2 class="eco-title">Macro Economy</h2>
          <p class="eco-subtitle">Financial levers, crisis protocols, and real-time market telemetry.</p>
        </div>
        <div class="header-pills">
          <span class="pill pill-live">
            <span class="pulse-dot"></span>
            Market: Stable
          </span>
        </div>
      </div>
    </div>

    <div class="eco-layout">
      <!-- ═══ LEFT: CONTROLS ═══ -->
      <div class="eco-left">

        <!-- MACRO LEVERS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-accent accent-emerald"></div>
            <h3 class="panel-title">Macro Calibration</h3>
          </div>
          <p class="panel-desc">Adjust global economic multipliers. Changes take effect on next game tick.</p>

          <div class="lever-list">
            <div v-for="lever in levers" :key="lever.key" class="lever-item">
              <div class="lever-meta">
                <span class="lever-label">{{ lever.label }}</span>
                <span class="lever-value" :class="{ changed: lever.value !== lever.original }">{{ lever.value.toFixed(2) }}</span>
              </div>
              <input type="range" v-model.number="lever.value" :min="lever.min" :max="lever.max" :step="lever.step" class="lever-range" />
              <p class="lever-help">{{ lever.help }}</p>
            </div>
          </div>

          <button @click="commitLevers" :disabled="!isDirty || saving" class="commit-btn">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
            {{ saving ? 'Committing...' : 'Commit Calibration' }}
          </button>
        </div>

        <!-- CRISIS PROTOCOLS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-accent accent-red"></div>
            <h3 class="panel-title">Crisis Protocols</h3>
          </div>
          <p class="panel-desc">System-wide volatility injections. Use for stress-testing or balance corrections.</p>

          <div class="crisis-list">
            <button @click="triggerCrisis('inflation')" class="crisis-btn">
              <div class="crisis-icon crisis-icon-red">💸</div>
              <div class="crisis-info">
                <span class="crisis-name">Economic Inflation</span>
                <span class="crisis-desc">+15% global costs, –8% revenue for 2 hours</span>
              </div>
            </button>
            <button @click="triggerCrisis('energy')" class="crisis-btn">
              <div class="crisis-icon crisis-icon-amber">⚡</div>
              <div class="crisis-info">
                <span class="crisis-name">Grid Instability</span>
                <span class="crisis-desc">Energy price spikes, random server faults</span>
              </div>
            </button>
            <button @click="triggerCrisis('demand_crash')" class="crisis-btn">
              <div class="crisis-icon crisis-icon-blue">📉</div>
              <div class="crisis-info">
                <span class="crisis-name">Demand Crash</span>
                <span class="crisis-desc">–50% new orders for 4 hours, rep decay ×2</span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: PROJECTIONS ═══ -->
      <div class="eco-right">

        <!-- CHART PANEL -->
        <div class="panel chart-panel">
          <div class="panel-header">
            <div class="panel-accent accent-emerald"></div>
            <h3 class="panel-title">Revenue Projection</h3>
            <div class="chart-timeframes">
              <button v-for="t in ['1H', '6H', '24H', '7D']" :key="t"
                      :class="['tf-btn', { active: chartTimeframe === t }]"
                      @click="chartTimeframe = t">{{ t }}</button>
            </div>
          </div>

          <div class="chart-area">
            <svg viewBox="0 0 1000 280" class="chart-svg" preserveAspectRatio="none">
              <defs>
                <linearGradient id="eco-areaGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                  <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                </linearGradient>
                <filter id="eco-glow">
                  <feGaussianBlur stdDeviation="3" result="g"/>
                  <feMerge><feMergeNode in="g"/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
              </defs>
              <!-- Grid -->
              <line v-for="y in [0, 70, 140, 210, 280]" :key="'g'+y" x1="0" :y1="y" x2="1000" :y2="y" stroke="#18181b" stroke-width="1" />
              <!-- Area -->
              <path :d="chartArea" fill="url(#eco-areaGrad)" />
              <!-- Line -->
              <path :d="chartLine" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#eco-glow)" />
              <!-- Now boundary -->
              <line x1="600" y1="0" x2="600" y2="280" stroke="#27272a" stroke-width="1" stroke-dasharray="4 4" />
              <text x="610" y="16" fill="#3f3f46" font-size="10" font-weight="900" font-family="monospace">PROJ</text>
            </svg>
            <div class="chart-labels">
              <span>Epoch Start</span>
              <span class="label-now">Now</span>
              <span>Forecast Limit</span>
            </div>
          </div>
        </div>

        <!-- KPI TILES -->
        <div class="kpi-grid">
          <div v-for="kpi in kpis" :key="kpi.label" class="kpi-tile">
            <span class="kpi-label">{{ kpi.label }}</span>
            <span class="kpi-value" :class="kpi.color">{{ kpi.value }}</span>
            <span class="kpi-delta" :class="kpi.deltaColor">{{ kpi.delta }}</span>
          </div>
        </div>

        <!-- MARKET SIGNALS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-accent accent-amber"></div>
            <h3 class="panel-title">Live Market Signals</h3>
          </div>
          <div class="signal-grid">
            <div v-for="sig in signals" :key="sig.label" class="signal-item">
              <span class="signal-label">{{ sig.label }}</span>
              <div class="signal-bar-track">
                <div class="signal-bar-fill" :style="{ width: sig.pct + '%' }" :class="sig.color"></div>
              </div>
              <span class="signal-val">{{ sig.val }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const saving = ref(false);
const isDirty = ref(false);
const chartTimeframe = ref('24H');

const levers = ref([
  { key: 'global_income_scalar', label: 'Income Velocity', value: 1.0, original: 1.0, min: 0.1, max: 2.0, step: 0.05, help: 'Global revenue multiplier for all players.' },
  { key: 'energy_market_multiplier', label: 'Energy Cost Factor', value: 1.25, original: 1.25, min: 0.5, max: 4.0, step: 0.1, help: 'Energy price drift multiplier.' },
  { key: 'inflation_coefficient', label: 'Inflation Index', value: 1.0, original: 1.0, min: 0.5, max: 2.5, step: 0.01, help: 'Hardware and operational cost multiplier.' },
  { key: 'demand_growth_delta', label: 'Demand Growth', value: 1.0, original: 1.0, min: 0.1, max: 3.0, step: 0.1, help: 'Customer volume increase rate.' },
]);

watch(levers, () => isDirty.value = true, { deep: true });

const commitLevers = async () => {
  saving.value = true;
  try {
    for (const l of levers.value) {
      await api.post('/admin/configs/update', { key: l.key, value: Number(l.value) });
      l.original = l.value;
    }
    addToast('Macro calibration committed.', 'success');
    isDirty.value = false;
  } catch (e) { addToast(e.message, 'error'); }
  finally { saving.value = false; }
};

const triggerCrisis = (type) => {
  const titles = { inflation: 'Economic Inflation', energy: 'Grid Instability', demand_crash: 'Demand Crash' };
  requestConfirm(`Initiate ${titles[type]} crisis? This will immediately impact all players.`, async () => {
    setGlobalLoading(true);
    try {
      await api.post('/admin/simulation/spike', { type });
      addToast(`${titles[type]} protocol activated.`, 'warning');
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

// Chart data (sample projection)
const chartPoints = ref([200, 180, 190, 160, 170, 155, 130, 140, 120, 115, 100, 110, 95, 85, 80, 70, 75, 65, 60, 55]);
const chartLine = computed(() => {
  const pts = chartPoints.value;
  const step = 1000 / (pts.length - 1);
  return 'M' + pts.map((y, i) => `${i * step},${y}`).join(' L');
});
const chartArea = computed(() => {
  const pts = chartPoints.value;
  const step = 1000 / (pts.length - 1);
  return 'M0,280 ' + pts.map((y, i) => `L${i * step},${y}`).join(' ') + ` L1000,280 Z`;
});

const kpis = [
  { label: 'Gross Revenue', value: '$142,380', delta: '+12.4%', color: '', deltaColor: 'delta-up' },
  { label: 'Net Profit', value: '$38,291', delta: '+5.1%', color: '', deltaColor: 'delta-up' },
  { label: 'Energy Spend', value: '$24,812', delta: '+18.2%', color: 'val-amber', deltaColor: 'delta-warn' },
  { label: 'Churn Rate', value: '2.4%', delta: '–0.3%', color: '', deltaColor: 'delta-up' },
];

const signals = [
  { label: 'Order Volume', pct: 72, val: '72%', color: 'fill-emerald' },
  { label: 'Server Utilization', pct: 88, val: '88%', color: 'fill-blue' },
  { label: 'Customer Satisfaction', pct: 65, val: '65%', color: 'fill-amber' },
  { label: 'Grid Stability', pct: 94, val: '94%', color: 'fill-emerald' },
];

onMounted(async () => {
  try {
    const res = await api.get('/admin/configs');
    if (res.success) {
      const flat = [].concat(...Object.values(res.configs));
      levers.value.forEach(l => {
        const found = flat.find(c => c.key === l.key);
        if (found) { l.value = Number(found.value); l.original = Number(found.value); }
      });
      setTimeout(() => isDirty.value = false, 50);
    }
  } catch (e) {}
});
</script>

<style scoped>
.eco-container { display: flex; flex-direction: column; gap: 0; }
.eco-header { margin-bottom: 24px; }
.eco-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.eco-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.eco-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-pills { display: flex; gap: 8px; }
.pill { display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 99px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #18181b; background: #0a0a0c; }
.pill-live { color: #4ade80; border-color: #052e16; }
.pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: #4ade80; animation: pulse-glow 2s ease infinite; }
@keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.5); } 50% { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); } }

.eco-layout { display: grid; grid-template-columns: 380px 1fr; gap: 20px; }

/* PANELS */
.panel { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 24px; margin-bottom: 16px; }
.panel-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.panel-accent { width: 3px; height: 18px; border-radius: 99px; }
.accent-emerald { background: #10b981; }
.accent-red { background: #ef4444; }
.accent-amber { background: #f59e0b; }
.panel-title { font-size: 0.75rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.08em; font-style: italic; margin: 0; }
.panel-desc { font-size: 0.65rem; color: #3f3f46; font-weight: 600; margin-bottom: 20px; }

/* LEVERS */
.lever-list { display: flex; flex-direction: column; gap: 18px; margin-bottom: 20px; }
.lever-item { display: flex; flex-direction: column; gap: 6px; }
.lever-meta { display: flex; justify-content: space-between; align-items: center; }
.lever-label { font-size: 0.7rem; font-weight: 700; color: #d4d4d8; }
.lever-value { font-size: 0.75rem; font-weight: 900; color: #10b981; font-family: 'JetBrains Mono', monospace; }
.lever-value.changed { color: #fbbf24; }
.lever-help { font-size: 0.55rem; color: #3f3f46; font-weight: 600; }
.lever-range { -webkit-appearance: none; width: 100%; height: 4px; background: #18181b; border-radius: 99px; outline: none; }
.lever-range::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; background: #10b981; border-radius: 50%; cursor: pointer; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }

.commit-btn {
  width: 100%; height: 44px; border-radius: 12px; border: 1px solid #15803d;
  background: #052e16; color: #4ade80; display: flex; align-items: center; justify-content: center;
  gap: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  cursor: pointer; transition: all 0.2s;
}
.commit-btn:hover:not(:disabled) { background: #14532d; }
.commit-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* CRISIS */
.crisis-list { display: flex; flex-direction: column; gap: 10px; }
.crisis-btn {
  width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 18px;
  background: #111; border: 1px solid #1c1c1e; border-radius: 12px; cursor: pointer;
  transition: all 0.2s; text-align: left;
}
.crisis-btn:hover { border-color: #333; transform: translateY(-1px); }
.crisis-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.crisis-icon-red { background: #450a0a; }
.crisis-icon-amber { background: #422006; }
.crisis-icon-blue { background: #0c1222; }
.crisis-name { font-size: 0.72rem; font-weight: 800; color: #e4e4e7; display: block; }
.crisis-desc { font-size: 0.55rem; color: #52525b; font-weight: 600; display: block; margin-top: 2px; }

/* CHART */
.chart-panel { padding: 0; overflow: hidden; }
.chart-panel .panel-header { padding: 20px 24px 0; }
.chart-timeframes { display: flex; gap: 4px; margin-left: auto; }
.tf-btn { padding: 4px 10px; border-radius: 6px; font-size: 0.55rem; font-weight: 800; color: #52525b; background: transparent; border: none; cursor: pointer; transition: all 0.15s; }
.tf-btn:hover { color: #a1a1aa; }
.tf-btn.active { color: #10b981; background: #052e16; }
.chart-area { padding: 20px 24px 16px; }
.chart-svg { width: 100%; height: 200px; }
.chart-labels { display: flex; justify-content: space-between; font-size: 0.5rem; font-weight: 800; color: #27272a; text-transform: uppercase; letter-spacing: 0.15em; margin-top: 10px; }
.label-now { color: #10b981; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi-tile { background: #0a0a0c; border: 1px solid #18181b; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 4px; }
.kpi-label { font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.kpi-value { font-size: 1.05rem; font-weight: 900; color: #fafafa; font-family: 'JetBrains Mono', monospace; font-style: italic; }
.kpi-value.val-amber { color: #fbbf24; }
.kpi-delta { font-size: 0.6rem; font-weight: 800; }
.delta-up { color: #4ade80; }
.delta-warn { color: #fbbf24; }

/* SIGNALS */
.signal-grid { display: flex; flex-direction: column; gap: 14px; }
.signal-item { display: flex; align-items: center; gap: 14px; }
.signal-label { font-size: 0.6rem; font-weight: 700; color: #71717a; width: 140px; flex-shrink: 0; }
.signal-bar-track { flex: 1; height: 6px; background: #18181b; border-radius: 99px; overflow: hidden; }
.signal-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s ease; }
.fill-emerald { background: #10b981; }
.fill-blue { background: #3b82f6; }
.fill-amber { background: #f59e0b; }
.signal-val { font-size: 0.65rem; font-weight: 900; color: #d4d4d8; font-family: 'JetBrains Mono', monospace; width: 40px; text-align: right; }
</style>
