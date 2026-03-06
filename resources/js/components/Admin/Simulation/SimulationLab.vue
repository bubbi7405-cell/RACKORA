<template>
  <div class="sim-container">
    <!-- HEADER -->
    <div class="sim-header">
      <div class="sim-title-row">
        <div>
          <h2 class="sim-title">Simulation Laboratory</h2>
          <p class="sim-subtitle">Stress-test the game engine — run 24h simulations, spike loads, and analyze projections.</p>
        </div>
        <div class="header-pills">
          <span class="pill" :class="running ? 'pill-running' : 'pill-idle'">
            <span class="status-dot" :class="running ? 'dot-running' : 'dot-idle'"></span>
            {{ running ? 'Simulation Active' : 'Standby' }}
          </span>
        </div>
      </div>
    </div>

    <div class="sim-layout">
      <!-- ═══ LEFT: PROTOCOLS ═══ -->
      <div class="sim-left">

        <!-- SIMULATION PROTOCOLS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-accent accent-blue"></div>
            <h3 class="panel-title">Protocols</h3>
          </div>

          <div class="protocol-list">
            <button @click="runProtocol('economy_24h')" :disabled="running" class="protocol-btn">
              <div class="proto-icon proto-icon-emerald">📊</div>
              <div class="proto-info">
                <span class="proto-name">24h Economy Forecast</span>
                <span class="proto-desc">Standard simulation of current game-loop lattice.</span>
              </div>
            </button>

            <button @click="runProtocol('market_crash')" :disabled="running" class="protocol-btn">
              <div class="proto-icon proto-icon-amber">📉</div>
              <div class="proto-info">
                <span class="proto-name">Market Core Collapse</span>
                <span class="proto-desc">Energy price hyper-inflation + belt-tightening logic.</span>
              </div>
            </button>

            <button @click="runProtocol('network_outage')" :disabled="running" class="protocol-btn">
              <div class="proto-icon proto-icon-red">⚡</div>
              <div class="proto-info">
                <span class="proto-name">Regional Backbone Failure</span>
                <span class="proto-desc">Regional network latency spike + mass churn cascade.</span>
              </div>
            </button>

            <button @click="runProtocol('stress_cascade')" :disabled="running" class="protocol-btn">
              <div class="proto-icon proto-icon-red">🌪️</div>
              <div class="proto-info">
                <span class="proto-name">Stress Cascade (Combined)</span>
                <span class="proto-desc">Mixed chaos: hardware failures + economy instability.</span>
              </div>
            </button>

            <!-- SPIKE TEST -->
            <div class="spike-panel">
              <div class="spike-header">
                <span class="proto-name">Spike Load Test</span>
                <span class="spike-val">{{ (spikeIntensity * 100).toFixed(0) }}%</span>
              </div>
              <input type="range" v-model.number="spikeIntensity" min="0.1" max="1.0" step="0.05" class="spike-range" />
              <button @click="runProtocol('spike')" :disabled="running" class="spike-btn">
                Execute Spike Test
              </button>
            </div>

            <!-- MEGA QA SIMULATION -->
            <div class="spike-panel qa-panel">
              <div class="spike-header">
                <span class="proto-name">QA Mega Simulation</span>
                <span class="spike-val">{{ qaBots }} Bots / {{ qaTicks }} Ticks</span>
              </div>
              <div class="qa-controls">
                 <div class="qa-control-group">
                   <label>Bots</label>
                   <input type="number" v-model.number="qaBots" min="1" max="50" class="qa-input" />
                 </div>
                 <div class="qa-control-group">
                   <label>Ticks</label>
                   <input type="number" v-model.number="qaTicks" min="5" max="100" class="qa-input" />
                 </div>
              </div>
              <label class="clean-check">
                <input type="checkbox" v-model="qaClean" /> Reset existing bots
              </label>
              <button @click="runQaMega" :disabled="running" class="spike-btn qa-btn">
                Launch Mega QA Core
              </button>
            </div>
          </div>
        </div>

        <!-- SYSTEM PARAMS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-accent accent-zinc"></div>
            <h3 class="panel-title">Context Parameters</h3>
          </div>
          <div class="param-list">
            <div v-for="(v, k) in contextParams" :key="k" class="param-row">
              <span class="param-key">{{ k }}</span>
              <span class="param-val">{{ v }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: TERMINAL + RESULTS ═══ -->
      <div class="sim-right">

        <!-- TERMINAL -->
        <div class="terminal-panel">
          <div class="terminal-header">
            <div class="terminal-title-area">
              <div class="terminal-dots">
                <span class="tdot tdot-r"></span>
                <span class="tdot tdot-y"></span>
                <span class="tdot tdot-g"></span>
              </div>
              <span class="terminal-label">RACKORA://sim.kernel.out</span>
            </div>
            <button @click="logLines = []" class="purge-btn">Purge Buffer</button>
          </div>

          <div ref="terminal" class="terminal-body">
            <div v-for="(line, i) in logLines" :key="i" class="log-line">
              <span class="log-time">{{ line.time }}</span>
              <span class="log-text" :class="'log-' + line.type">{{ line.text }}</span>
            </div>
            <div v-if="!logLines.length" class="terminal-empty">
              <span class="terminal-empty-icon">∞</span>
              <p>Initialize a simulation protocol to begin...</p>
            </div>
          </div>
        </div>

        <!-- REPORT -->
        <transition name="report-fade">
          <div v-if="report" class="report-panel">
            <div class="panel-header">
              <div class="panel-accent accent-blue"></div>
              <h3 class="panel-title">Simulation Output</h3>
            </div>
            <div class="report-grid">
              <div class="report-tile" :class="'risk-' + report.risk_level">
                <span class="report-label">Projected Risk Level</span>
                <span class="report-value">{{ report.risk_level.toUpperCase() }}</span>
              </div>
              <div class="report-tile">
                <span class="report-label">Revenue Projection</span>
                <span class="report-value" :class="report.expected_revenue >= 0 ? 'val-emerald' : 'val-red'">${{ report.expected_revenue?.toLocaleString() }}</span>
              </div>
              <div class="report-tile">
                <span class="report-label">Projected Churn</span>
                <div class="report-bar-track">
                  <div class="report-bar-fill fill-amber" :style="{ width: Math.min(100, (report.projected_churn || 0)) + '%' }"></div>
                </div>
                <span class="report-pct">{{ report.projected_churn }}%</span>
              </div>
              <div class="report-tile">
                <span class="report-label">Confidence Index</span>
                <span class="report-value val-blue">{{ report.confidence || 'N/A' }}%</span>
              </div>
              <div class="report-tile span-2">
                <span class="report-label">Analyzed Bottlenecks</span>
                <div class="bottleneck-tags">
                  <span v-for="b in (report.bottlenecks || [])" :key="b" class="bottleneck-tag">{{ b }}</span>
                  <span v-if="!(report.bottlenecks?.length)" class="bottleneck-none">Lattice within nominal operational bounds.</span>
                </div>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, nextTick } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading } = inject('adminContext');

const running = ref(false);
const logLines = ref([]);
const spikeIntensity = ref(0.4);
const qaBots = ref(5);
const qaTicks = ref(10);
const qaClean = ref(false);
const report = ref(null);
const terminal = ref(null);

const contextParams = {
  tick_resolution: '1 MINUTE',
  monte_carlo_samples: '5,000',
  stochastic_seed: 'PRIME-012',
  kernel_access: 'ISOLATED',
};

const addLog = (text, type = 'info') => {
  const now = new Date();
  const time = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.' + now.getMilliseconds().toString().padStart(3, '0').slice(0, 2);
  logLines.value.push({ time, text, type });
  nextTick(() => { if (terminal.value) terminal.value.scrollTop = terminal.value.scrollHeight; });
};

const runProtocol = async (id) => {
  running.value = true;
  report.value = null;
  logLines.value = [];

  addLog(`INITIALIZING PROTOCOL: ${id.toUpperCase()}...`);
  addLog('Acquiring lattice state snapshot...');

  setTimeout(() => addLog('Booting virtual simulation engine...'), 500);
  setTimeout(() => addLog('Injecting stochastic behavioral vectors...'), 1200);
  setTimeout(() => addLog('Calculating convergence coefficients...'), 2000);

  try {
    const res = await api.post('/admin/simulation/run-24h', { type: id, intensity: spikeIntensity.value });
    if (res.success) {
      setTimeout(() => {
        report.value = res.prediction;
        addLog('────────────────────────────────────────');
        addLog('SIMULATION COMPLETE.', 'success');
        addLog(`Projected Revenue: $${res.prediction.expected_revenue}`, 'success');
        if (res.prediction.bottlenecks?.length) {
          addLog(`Bottlenecks: ${res.prediction.bottlenecks.join(', ')}`, 'warning');
        }
        running.value = false;
      }, 3000);
    }
  } catch (e) {
    addLog('FATAL: Kernel panic — ' + e.message, 'error');
    running.value = false;
  }
};

const runQaMega = async () => {
  running.value = true;
  report.value = null;
  logLines.value = [];

  addLog('INITIALIZING MEGA QA CLUSTER...');
  addLog(`Provisioning ${qaBots.value} virtual bot instances...`);
  if (qaClean.value) addLog('Purging existing QA test subjects...', 'warning');

  try {
    const res = await api.post('/admin/simulation/qa-mega', { 
      bots: qaBots.value, 
      ticks: qaTicks.value, 
      clean: qaClean.value 
    });
    
    if (res.success) {
      addLog('Lattice simulation running...', 'info');
      
      // We simulate some delay to make it feel "mega"
      setTimeout(() => {
        if (res.log) {
          const lines = res.log.split('\n');
          lines.forEach(line => {
            if (line.trim()) addLog(line.trim(), line.includes('Action Failed') ? 'warning' : 'info');
          });
        }
        
        addLog('────────────────────────────────────────');
        addLog('MEGA QA SIMULATION COMPLETE.', 'success');
        if (res.report_path) {
          addLog(`Report generated: ${res.report_path}`, 'success');
        }
        running.value = false;
        
        addToast({
          title: 'Simulation Complete',
          message: 'Mega QA run finished. Check terminal for report path.',
          type: 'success'
        });
      }, 2000);
    }
  } catch (e) {
    addLog('CRITICAL FAILURE: QA Core interrupted — ' + e.message, 'error');
    running.value = false;
  }
};
</script>

<style scoped>
.sim-container { display: flex; flex-direction: column; }
.sim-header { margin-bottom: 24px; }
.sim-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.sim-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.sim-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-pills { display: flex; gap: 8px; }
.pill { display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 99px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #18181b; background: #0a0a0c; }
.pill-idle { color: #52525b; }
.pill-running { color: #60a5fa; border-color: #1e3a5f; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; }
.dot-idle { background: #3f3f46; }
.dot-running { background: #3b82f6; animation: pulse-b 2s ease infinite; }
@keyframes pulse-b { 0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.5); } 50% { box-shadow: 0 0 0 6px rgba(59,130,246,0); } }

.sim-layout { display: grid; grid-template-columns: 360px 1fr; gap: 20px; }

/* PANELS */
.panel { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 24px; margin-bottom: 16px; }
.panel-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.panel-accent { width: 3px; height: 18px; border-radius: 99px; }
.accent-blue { background: #3b82f6; }
.accent-zinc { background: #52525b; }
.accent-amber { background: #f59e0b; }
.panel-title { font-size: 0.75rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.08em; font-style: italic; margin: 0; }

/* PROTOCOLS */
.protocol-list { display: flex; flex-direction: column; gap: 10px; }
.protocol-btn {
  width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 18px;
  background: #111; border: 1px solid #1c1c1e; border-radius: 12px; cursor: pointer;
  transition: all 0.2s; text-align: left;
}
.protocol-btn:hover:not(:disabled) { border-color: #333; transform: translateY(-1px); }
.protocol-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.proto-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.proto-icon-emerald { background: #052e16; }
.proto-icon-red { background: #450a0a; }
.proto-name { font-size: 0.72rem; font-weight: 800; color: #e4e4e7; display: block; }
.proto-desc { font-size: 0.55rem; color: #52525b; font-weight: 600; display: block; margin-top: 2px; }

.spike-panel { background: #111; border: 1px solid #1c1c1e; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
.spike-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.spike-val { font-size: 0.7rem; font-weight: 900; color: #3b82f6; font-family: 'JetBrains Mono', monospace; }
.spike-range { -webkit-appearance: none; appearance: none; width: 100%; height: 4px; background: #18181b; border-radius: 99px; outline: none; margin-bottom: 12px; }
.spike-range::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 16px; height: 16px; background: #3b82f6; border-radius: 4px; cursor: pointer; box-shadow: 0 0 10px rgba(59,130,246,0.4); }
.spike-btn { width: 100%; height: 36px; border-radius: 10px; background: #0c1222; border: 1px solid #1e3a5f; color: #60a5fa; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: all 0.2s; }
.spike-btn:hover:not(:disabled) { background: #1e3a5f; }
.spike-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.qa-panel { margin-top: 16px; border-color: #3f3f46; }
.qa-controls { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.qa-control-group { display: flex; flex-direction: column; gap: 4px; }
.qa-control-group label { font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; }
.qa-input { background: #050505; border: 1px solid #1c1c1e; border-radius: 6px; color: #fafafa; padding: 6px 10px; font-size: 0.7rem; font-family: 'JetBrains Mono', monospace; outline: none; }
.qa-input:focus { border-color: #3b82f6; }
.clean-check { display: flex; align-items: center; gap: 8px; font-size: 0.6rem; color: #52525b; font-weight: 700; margin-bottom: 12px; cursor: pointer; }
.clean-check input { accent-color: #3b82f6; }
.qa-btn { background: #1a1a1a; border-color: #52525b; color: #e4e4e7; }
.qa-btn:hover:not(:disabled) { background: #27272a; border-color: #a1a1aa; }

/* PARAMS */
.param-list { display: flex; flex-direction: column; gap: 8px; }
.param-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #111; }
.param-key { font-size: 0.6rem; color: #52525b; font-weight: 700; }
.param-val { font-size: 0.65rem; color: #3b82f6; font-weight: 800; font-family: 'JetBrains Mono', monospace; }

/* TERMINAL */
.terminal-panel { background: #050505; border: 1px solid #18181b; border-radius: 16px; overflow: hidden; margin-bottom: 16px; }
.terminal-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: #0a0a0c; border-bottom: 1px solid #18181b; }
.terminal-title-area { display: flex; align-items: center; gap: 12px; }
.terminal-dots { display: flex; gap: 6px; }
.tdot { width: 8px; height: 8px; border-radius: 50%; }
.tdot-r { background: #ef4444; }
.tdot-y { background: #f59e0b; }
.tdot-g { background: #22c55e; }
.terminal-label { font-size: 0.6rem; font-weight: 800; color: #3f3f46; font-family: 'JetBrains Mono', monospace; }
.purge-btn { background: none; border: none; color: #27272a; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
.purge-btn:hover { color: #a1a1aa; }

.terminal-body { height: 400px; overflow-y: auto; padding: 16px 20px; font-family: 'JetBrains Mono', monospace; }
.terminal-body::-webkit-scrollbar { width: 4px; }
.terminal-body::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }

.log-line { display: flex; gap: 12px; padding: 4px 0; font-size: 0.68rem; font-style: italic; line-height: 1.8; }
.log-time { color: #27272a; flex-shrink: 0; font-weight: 700; }
.log-text { font-weight: 600; }
.log-info { color: #71717a; }
.log-success { color: #4ade80; }
.log-warning { color: #fbbf24; }
.log-error { color: #f87171; }

.terminal-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #27272a; gap: 12px; }
.terminal-empty-icon { font-size: 3rem; font-style: italic; }
.terminal-empty p { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3em; }

/* REPORT */
.report-panel { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 24px; }
.report-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.report-tile { background: #111; border: 1px solid #1c1c1e; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 6px; }
.report-label { font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.report-value { font-size: 1.1rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; font-style: italic; }
.val-emerald { color: #4ade80; }
.val-red { color: #f87171; }
.val-blue { color: #60a5fa; }

.risk-critical .report-value { color: #ef4444; }
.risk-elevated .report-value { color: #f59e0b; }
.risk-low .report-value { color: #4ade80; }

.report-bar-track { height: 6px; background: #18181b; border-radius: 99px; overflow: hidden; }
.report-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s; }
.fill-amber { background: #f59e0b; }
.report-pct { font-size: 0.7rem; font-weight: 900; color: #fbbf24; font-family: 'JetBrains Mono', monospace; }
.bottleneck-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.bottleneck-tag { padding: 3px 10px; background: #422006; border: 1px solid #92400e; border-radius: 6px; font-size: 0.55rem; font-weight: 800; color: #fbbf24; }
.bottleneck-none { font-size: 0.6rem; color: #3f3f46; font-weight: 600; }

.span-2 { grid-column: span 2; }

.report-fade-enter-active { transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
.report-fade-enter-from { opacity: 0; transform: translateY(20px); }
</style>
