<template>
  <div class="mcc-container">
    <!-- HEADER -->
    <div class="mcc-header">
      <div class="mcc-title-row">
        <div>
          <h2 class="mcc-title">Mission Control</h2>
          <p class="mcc-subtitle">Global infrastructure overview — KPIs, live traffic, tactical overrides, and anomaly alerts.</p>
        </div>
        <div class="header-actions">
          <span class="pill pill-live">
            <span class="pulse-dot"></span>
            Network Synced
          </span>
          <button @click="fetchStats" class="action-btn" :class="{ spin: loading }">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-ring"></div>
      <span>Establishing uplink...</span>
    </div>

    <div v-else class="mcc-content">
      <!-- KPI GRID -->
      <div class="kpi-grid">
        <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card">
          <span class="kpi-label">{{ kpi.label }}</span>
          <span class="kpi-value">{{ kpi.value }}</span>
          <div class="kpi-footer">
            <span class="kpi-delta" :class="kpi.deltaClass">{{ kpi.delta }}</span>
            <span class="kpi-range">{{ kpi.range }}</span>
          </div>
        </div>
      </div>

      <div class="mcc-layout">
        <!-- LEFT: TRAFFIC + ALERTS -->
        <div class="mcc-left">

          <!-- GLOBAL TRAFFIC MAP -->
          <div class="panel map-panel">
            <div class="panel-header">
              <div class="panel-accent accent-blue"></div>
              <h3 class="panel-title">Global Traffic Lattice</h3>
              <div class="map-legend">
                 <span class="legend-item"><span class="l-dot l-emerald"></span> Optimal</span>
                 <span class="legend-item"><span class="l-dot l-amber"></span> Peak</span>
                 <span class="legend-item"><span class="l-dot l-red"></span> Congested</span>
              </div>
            </div>
            <div class="world-map">
               <svg viewBox="0 0 1000 500" class="map-svg">
                  <!-- Simplified World Outline -->
                  <path d="M150,150 Q200,100 250,150 T350,150 T450,200 T550,150 T650,200 T750,150 T850,250 T900,350 T800,450 T700,400 T600,450 T500,400 T400,450 T300,400 T200,450 T100,350 Z" 
                        fill="#050505" stroke="#18181b" stroke-width="1" />
                  
                  <!-- ORBITAL PINGS (Animated Points) -->
                  <g v-for="node in trafficNodes" :key="node.id">
                     <circle :cx="node.x" :cy="node.y" r="3" :fill="node.color" class="ping-core" />
                     <circle :cx="node.x" :cy="node.y" r="10" :stroke="node.color" fill="none" class="ping-ring" />
                     <text :x="node.x + 10" :y="node.y + 4" class="node-label">{{ node.label }}</text>
                  </g>

                  <!-- INFRASTRUCTURE LINKS -->
                  <line v-for="(link, i) in trafficLinks" :key="i"
                        :x1="link.from.x" :y1="link.from.y" :x2="link.to.x" :y2="link.to.y"
                        stroke="rgba(59,130,246,0.15)" stroke-width="1" stroke-dasharray="4" class="link-line" />
               </svg>
            </div>
          </div>

          <!-- ALERTS TABLE -->
          <div class="panel" v-if="alerts.length">
            <div class="panel-header">
              <div class="panel-accent accent-amber"></div>
              <h3 class="panel-title">Active Anomalies</h3>
              <span class="alert-count">{{ alerts.length }}</span>
            </div>
            <div class="alert-list">
              <div v-for="alert in alerts" :key="alert.id" class="alert-row">
                <span class="alert-sev" :class="alert.type === 'critical' ? 'sev-critical' : 'sev-warning'">{{ alert.type }}</span>
                <span class="alert-msg">{{ alert.message }}</span>
                <button class="alert-ack">Ack</button>
              </div>
            </div>
          </div>
          <div v-else class="panel empty-alerts">
            <div class="panel-accent accent-emerald"></div>
            <span class="empty-text">No anomalies detected — all systems nominal.</span>
          </div>
        </div>

        <!-- RIGHT: OVERRIDES + LEDGER -->
        <div class="mcc-right">

          <!-- TACTICAL OVERRIDES -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-accent accent-indigo"></div>
              <h3 class="panel-title">Tactical Overrides</h3>
            </div>
            <div class="override-list">
              <button @click="triggerSpike" class="override-btn">
                <div class="ov-icon ov-icon-amber">📉</div>
                <div class="ov-info">
                  <span class="ov-name">Market Anomaly</span>
                  <span class="ov-desc">Inject energy price volatility</span>
                </div>
              </button>
              <button @click="triggerBroadcast" class="override-btn">
                <div class="ov-icon ov-icon-blue">📢</div>
                <div class="ov-info">
                  <span class="ov-name">Mass Broadcast</span>
                  <span class="ov-desc">Send global notification to all players</span>
                </div>
              </button>
              <button class="override-btn">
                <div class="ov-icon ov-icon-emerald">🛡️</div>
                <div class="ov-info">
                  <span class="ov-name">Emergency Maintenance</span>
                  <span class="ov-desc">Pause all game ticks for maintenance</span>
                </div>
              </button>
              <button @click="triggerLiquidation" class="override-btn">
                <div class="ov-icon ov-icon-red">🔨</div>
                <div class="ov-info">
                  <span class="ov-name">Trigger Auction</span>
                  <span class="ov-desc">Force manual hardware liquidation</span>
                </div>
              </button>
            </div>
          </div>

          <!-- EVENT LEDGER -->
          <div class="panel ledger-panel">
            <div class="panel-header">
              <div class="panel-accent accent-zinc"></div>
              <h3 class="panel-title">Event Ledger</h3>
              <span class="ledger-live">Live</span>
            </div>
            <div class="ledger-list">
              <div v-for="log in ledgerLogs" :key="log.id" class="ledger-item">
                <span class="ledger-time">{{ new Date(log.created_at).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</span>
                <span class="ledger-msg">{{ log.message }}</span>
              </div>
              <div v-if="!ledgerLogs.length" class="ledger-empty">No active logs in buffer.</div>
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

const { addToast, requestConfirm, setGlobalLoading } = inject('adminContext');

const loading = ref(true);
const alerts = ref([]);
const kpis = ref([]);
const ledgerLogs = ref([]);

const trafficNodes = [
  { id: 1, x: 200, y: 180, label: 'US-EAST-1', color: '#4ade80' },
  { id: 2, x: 480, y: 160, label: 'EU-CENTRAL-1', color: '#3b82f6' },
  { id: 3, x: 780, y: 220, label: 'AP-NORTHEAST-1', color: '#fbbf24' },
  { id: 4, x: 350, y: 380, label: 'SA-EAST-1', color: '#f87171' },
  { id: 5, x: 820, y: 400, label: 'AU-SOUTHEAST-2', color: '#4ade80' },
];

const trafficLinks = [
  { from: trafficNodes[0], to: trafficNodes[1] },
  { from: trafficNodes[1], to: trafficNodes[2] },
  { from: trafficNodes[0], to: trafficNodes[3] },
  { from: trafficNodes[2], to: trafficNodes[4] },
];

const fetchStats = async () => {
  loading.value = true;
  try {
    const [statsRes, logsRes] = await Promise.all([
      api.get('/admin/stats'),
      api.get('/admin/logs/global')
    ]);

    if (statsRes.success) {
      alerts.value = statsRes.stats?.alerts || [];
      kpis.value = [
        { label: 'Active Players', value: (statsRes.stats?.total_players || 0).toLocaleString(), delta: '▲ 14.2%', deltaClass: 'delta-up', range: 'In Range' },
        { label: 'Live Servers', value: (statsRes.stats?.active_servers || 0).toLocaleString(), delta: '▲ 8.1%', deltaClass: 'delta-up', range: 'Nominal' },
        { label: 'Revenue (24h)', value: '$' + (statsRes.stats?.revenue_24h || 0).toLocaleString(), delta: '▲ 5.3%', deltaClass: 'delta-up', range: 'Growing' },
        { label: 'Churn Rate', value: (statsRes.stats?.churn_rate || 0) + '%', delta: '▼ 0.3%', deltaClass: 'delta-up', range: 'Stable' },
      ];
    }

    if (logsRes.success) {
      ledgerLogs.value = logsRes.logs || [];
    }
  } catch (e) { addToast('Failed to fetch telemetry.', 'error'); }
  finally { loading.value = false; }
};

const triggerSpike = () => {
  requestConfirm('Inject market anomaly? This will immediately fluctuate energy costs.', async () => {
    setGlobalLoading(true);
    try {
      await api.post('/admin/simulation/spike', { intensity: 0.85 });
      addToast('Market anomaly injected.', 'success');
      fetchStats();
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

const triggerBroadcast = () => {
  const text = prompt('Enter global broadcast message:');
  if (!text) return;
  requestConfirm('Send global SYS_BROADCAST to all active players?', async () => {
    setGlobalLoading(true);
    try {
      await api.post('/admin/simulation/broadcast', { message: text });
      addToast('Global broadcast transmitted.', 'success');
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

const triggerLiquidation = () => {
    requestConfirm('DANGER: Force trigger a global hardware liquidation auction?', async () => {
        setGlobalLoading(true);
        try {
            await api.post('/admin/auctions/trigger');
            addToast('Liquidation auction triggered!', 'success');
            fetchStats();
        } catch (e) { addToast(e.message, 'error'); }
        finally { setGlobalLoading(false); }
    });
};

onMounted(fetchStats);
</script>

<style scoped>
.mcc-container { display: flex; flex-direction: column; }
.mcc-header { margin-bottom: 24px; }
.mcc-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.mcc-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.mcc-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.pill { display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 99px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #052e16; background: #0a0a0c; }
.pill-live { color: #4ade80; }
.pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: #4ade80; animation: pg 2s ease infinite; }
@keyframes pg { 0%,100% { box-shadow: 0 0 0 0 rgba(74,222,128,0.5); } 50% { box-shadow: 0 0 0 6px rgba(74,222,128,0); } }
.action-btn { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: all 0.2s; border: 1px solid #222; background: #111; color: #a1a1aa; }
.action-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.action-btn.spin svg { animation: spin360 0.8s ease; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.loader-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 80px 0; color: #3f3f46; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; }
.loader-ring { width: 32px; height: 32px; border: 2px solid #18181b; border-top-color: #3b82f6; border-radius: 50%; animation: spin360 0.8s linear infinite; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.kpi-card { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 20px; transition: all 0.2s; }
.kpi-card:hover { border-color: #27272a; }
.kpi-label { display: block; font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 8px; }
.kpi-value { display: block; font-size: 1.4rem; font-weight: 900; color: #fafafa; font-style: italic; font-family: 'JetBrains Mono', monospace; }
.kpi-footer { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.kpi-delta { font-size: 0.6rem; font-weight: 800; }
.delta-up { color: #4ade80; }
.kpi-range { font-size: 0.5rem; color: #27272a; font-weight: 700; text-transform: uppercase; }

.mcc-layout { display: grid; grid-template-columns: 1fr 360px; gap: 20px; }

/* PANELS */
.panel { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 24px; margin-bottom: 16px; }
.panel-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.panel-accent { width: 3px; height: 18px; border-radius: 99px; }
.accent-blue { background: #3b82f6; }
.accent-amber { background: #f59e0b; }
.accent-emerald { background: #10b981; }
.accent-indigo { background: #6366f1; }
.accent-zinc { background: #52525b; }
.panel-title { font-size: 0.75rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.08em; font-style: italic; margin: 0; }

/* MAP */
.map-panel { padding: 24px; position: relative; overflow: hidden; }
.map-legend { margin-left: auto; display: flex; gap: 12px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.55rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; }
.l-dot { width: 6px; height: 6px; border-radius: 50%; }
.l-emerald { background: #10b981; }
.l-amber { background: #f59e0b; }
.l-red { background: #ef4444; }

.world-map { background: #050505; border: 1px solid #18181b; border-radius: 12px; padding: 20px; position: relative; aspect-ratio: 2/1; }
.map-svg { width: 100%; height: 100%; }

.ping-core { filter: drop-shadow(0 0 4px currentColor); }
.ping-ring { transform-origin: center; animation: ringPulse 3s infinite; opacity: 0; }
@keyframes ringPulse {
  0% { transform: scale(0.5); opacity: 0; }
  50% { opacity: 0.5; }
  100% { transform: scale(3); opacity: 0; }
}

.node-label { font-size: 10px; font-family: 'JetBrains Mono', monospace; font-weight: 800; fill: #52525b; text-transform: uppercase; letter-spacing: 0.05em; pointer-events: none; }
.link-line { stroke-dasharray: 4; animation: lineFlow 30s linear infinite; }
@keyframes lineFlow { from { stroke-dashoffset: 100; } to { stroke-dashoffset: 0; } }

.mcc-left { display: flex; flex-direction: column; gap: 16px; }

/* ALERTS */
.alert-count { margin-left: auto; background: #422006; color: #fbbf24; padding: 2px 10px; border-radius: 99px; font-size: 0.55rem; font-weight: 900; }
.alert-list { display: flex; flex-direction: column; gap: 8px; }
.alert-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #111; border: 1px solid #1c1c1e; border-radius: 10px; }
.alert-sev { padding: 2px 10px; border-radius: 6px; font-size: 0.5rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; flex-shrink: 0; }
.sev-critical { background: #450a0a; color: #f87171; border: 1px solid #7f1d1d; }
.sev-warning { background: #422006; color: #fbbf24; border: 1px solid #92400e; }
.alert-msg { flex: 1; font-size: 0.7rem; font-weight: 700; color: #d4d4d8; font-style: italic; }
.alert-ack { padding: 4px 12px; border-radius: 6px; background: #111; border: 1px solid #222; color: #52525b; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; cursor: pointer; transition: all 0.15s; }
.alert-ack:hover { color: #fafafa; border-color: #333; }

.empty-alerts { display: flex; align-items: center; gap: 10px; }
.empty-text { font-size: 0.65rem; color: #3f3f46; font-weight: 600; }

/* OVERRIDES */
.override-list { display: flex; flex-direction: column; gap: 8px; }
.override-btn { width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: #111; border: 1px solid #1c1c1e; border-radius: 12px; cursor: pointer; transition: all 0.2s; text-align: left; }
.override-btn:hover { border-color: #333; transform: translateY(-1px); }
.ov-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.ov-icon-amber { background: #422006; }
.ov-icon-blue { background: #0c1222; }
.ov-icon-emerald { background: #052e16; }
.ov-name { font-size: 0.7rem; font-weight: 800; color: #e4e4e7; display: block; }
.ov-desc { font-size: 0.55rem; color: #52525b; font-weight: 600; display: block; margin-top: 2px; }

/* LEDGER */
.ledger-panel { max-height: 340px; display: flex; flex-direction: column; }
.ledger-live { margin-left: auto; font-size: 0.5rem; font-weight: 900; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.15em; }
.ledger-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; }
.ledger-list::-webkit-scrollbar { width: 3px; }
.ledger-list::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }
.ledger-item { display: flex; gap: 12px; padding: 6px 0; border-left: 1px solid #18181b; padding-left: 12px; }
.ledger-time { font-size: 0.55rem; color: #27272a; font-family: 'JetBrains Mono', monospace; font-weight: 700; flex-shrink: 0; }
.ledger-msg { font-size: 0.62rem; color: #52525b; font-weight: 600; font-style: italic; }
.ledger-item:hover .ledger-msg { color: #a1a1aa; }
</style>
