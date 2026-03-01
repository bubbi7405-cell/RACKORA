<template>
  <div class="simulation-tools space-y-8 pb-12">
    <div class="flex justify-between items-start">
      <div>
        <h2 class="text-3xl font-extrabold tracking-tighter text-white uppercase italic underline decoration-blue-500/50 underline-offset-8">Predictive Analytics Cluster</h2>
        <p class="text-gray-400 text-sm max-w-2xl mt-4">Execute high-fidelity Monte Carlo simulations to forecast game stability and economic trends. All tests run in an isolated virtual kernel without affecting production synchronization.</p>
      </div>
      <div class="flex flex-col items-end gap-2">
         <div class="px-4 py-1.5 bg-blue-500/10 text-blue-500 rounded-xl border border-blue-500/20 text-[10px] font-black uppercase tracking-[0.2em] italic">
            VIRTUAL SIMULATOR v4.4.2_LATEST
         </div>
         <span class="text-[9px] font-mono text-gray-700 uppercase">Status: Isolated / Ready</span>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-10">
       <!-- CONTROL PANEL -->
       <div class="xl:col-span-4 space-y-8">
          <div class="admin-card border-white/5 bg-black/20 overflow-hidden relative group/ctrl">
             <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-transparent opacity-40"></div>
             <h3 class="font-black text-xs text-white mb-8 flex items-center gap-3 uppercase tracking-[0.3em]">
                <span class="p-2 bg-blue-600/10 rounded-lg text-blue-500">🧪</span> ORCHESTRATION PROTOCOLS
             </h3>
             
             <div class="space-y-6">
                <!-- Protocol 1 -->
                <div class="group/p1 relative">
                   <div class="absolute -left-7 top-1 w-1 h-12 bg-blue-600 rounded-full opacity-0 group-hover/p1:opacity-100 transition-all"></div>
                   <div class="protocol-card p-5 rounded-2xl bg-white/[0.02] border border-white/5 hover:border-blue-500/30 transition-all cursor-pointer">
                      <div class="flex justify-between items-center mb-2">
                         <h4 class="text-xs font-black text-blue-400 uppercase tracking-widest italic">24h Alpha Projection</h4>
                         <span class="text-[8px] font-mono text-gray-700">1440_TICKS</span>
                      </div>
                      <p class="text-[10px] text-gray-500 italic mb-6 leading-relaxed">Simulates 24h cycle using stochastic player behavior and current market volatility indices.</p>
                      <button @click="runSim" class="btn btn-primary w-full h-10 uppercase text-[10px] font-black tracking-widest bg-blue-600 shadow-blue-600/10 shadow-lg" :disabled="running">
                         <span v-if="running" class="animate-spin mr-2">◌</span>
                         {{ running ? 'Simulating Matrix...' : 'Execute Alpha Protocol' }}
                      </button>
                   </div>
                </div>

                <!-- Protocol 2 -->
                <div class="group/p2 relative opacity-50 hover:opacity-100 transition-opacity">
                   <div class="protocol-card p-5 rounded-2xl bg-white/[0.02] border border-white/5 border-dashed">
                      <div class="flex justify-between items-center mb-2">
                         <h4 class="text-xs font-black text-red-500 uppercase tracking-widest italic">Cascade Failure Test</h4>
                         <span class="text-[8px] font-mono text-red-900 font-black">STRESS_LOCK</span>
                      </div>
                      <p class="text-[10px] text-gray-500 italic mb-6">Simulates coordinate power failure across multiple regions to test churn survival thresholds.</p>
                      <button @click="addToast('Security Failure: Superadmin Clearance Required for Chaos-Tests', 'error')" class="btn border border-red-500/20 text-red-500/60 w-full h-10 uppercase text-[10px] font-black tracking-widest bg-red-500/5 hover:bg-red-500 text-red-400 hover:text-white transition-all">Execute Sigma-9</button>
                   </div>
                </div>

                <!-- Protocol 3 (Interactive) -->
                <div class="protocol-card p-5 rounded-2xl bg-white/[0.02] border border-indigo-500/10 shadow-[inner_0_0_20px_rgba(99,102,241,0.02)]">
                   <div class="flex justify-between items-center mb-6">
                      <h4 class="text-xs font-black text-indigo-400 uppercase tracking-widest italic">Node Stress Injection</h4>
                      <span class="text-[10px] font-mono text-indigo-400/40 bg-indigo-500/5 px-2 rounded">{{ (spikeIntensity * 100).toFixed(0) }}%</span>
                   </div>
                   <input v-model.number="spikeIntensity" type="range" min="0.1" max="1.0" step="0.1" 
                          class="w-full h-1.5 bg-gray-900 rounded-lg appearance-none cursor-pointer accent-indigo-500 mb-6" />
                   <button @click="injectSpike" class="btn border border-indigo-500/30 text-indigo-400 w-full h-10 uppercase text-[10px] font-black tracking-widest hover:bg-indigo-600 hover:text-white transition-all">Apply Artificial Load Spike</button>
                </div>
             </div>
          </div>

          <div class="admin-card border-white/5 bg-black/20">
             <h3 class="font-black text-xs text-gray-500 mb-6 uppercase tracking-[0.2em]">Active Matrix Parameters</h3>
             <div class="space-y-4">
                <div v-for="(val, key) in simParams" :key="key" class="flex justify-between items-center p-3 bg-white/[0.02] rounded-xl border border-white/5">
                   <span class="text-[9px] text-gray-600 uppercase font-black italic tracking-widest">{{ key.replace('_', ' ') }}</span>
                   <span class="font-mono text-[10px] text-blue-400 font-bold tracking-tighter">{{ val }}</span>
                </div>
             </div>
          </div>
       </div>

       <!-- SIMULATION OUTPUT (TERMINAL STYLE) -->
       <div class="xl:col-span-8 flex flex-col space-y-8">
          <div class="admin-card bg-black/60 border-white/5 flex-1 min-h-[550px] flex flex-col font-mono relative overflow-hidden rounded-[2.5rem] shadow-2xl">
             <!-- VIRTUAL HUD -->
             <div class="absolute top-0 left-0 right-0 p-5 bg-black/40 backdrop-blur-xl border-b border-white/5 flex justify-between items-center z-20">
                <div class="flex items-center gap-3">
                   <div class="flex gap-1">
                      <div class="w-1.5 h-1.5 rounded-full bg-red-500/40"></div>
                      <div class="w-1.5 h-1.5 rounded-full bg-yellow-500/40"></div>
                      <div class="w-1.5 h-1.5 rounded-full bg-green-500/40"></div>
                   </div>
                   <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] italic ml-2">SIM_PRIMARY_LOG // CLUSTER_01</span>
                </div>
                <div class="flex items-center gap-4">
                   <span class="text-[8px] font-mono text-blue-500 uppercase flex items-center gap-2">
                      <span class="w-1 h-1 rounded-full bg-blue-500 animate-ping"></span> Real-time Streaming
                   </span>
                   <button @click="logLines = []" class="text-[8px] text-gray-700 hover:text-white uppercase font-black underline underline-offset-4">Purge Buffer</button>
                </div>
             </div>

             <!-- Terminal Content -->
             <div ref="terminal" class="flex-1 p-8 pt-24 overflow-y-auto custom-scrollbar text-[11px] leading-7 font-mono italic">
                <div v-for="(line, idx) in logLines" :key="idx" class="mb-2 group/line flex items-start gap-4">
                   <span class="text-gray-700 group-hover/line:text-blue-500 transition-colors whitespace-nowrap">[{{ line.time }}]</span>
                   <span class="px-2 text-white/10 group-hover/line:text-white/20">|</span>
                   <span :class="{
                      'text-red-500 font-black tracking-widest': line.type === 'error',
                      'text-yellow-500': line.type === 'warning',
                      'text-green-400 font-bold': line.type === 'success',
                      'text-gray-400': line.type === 'info'
                   }">
                      {{ line.text }}
                   </span>
                </div>
                <div v-if="running" class="inline-block w-2 h-4 bg-blue-500 animate-pulse mt-4 ml-2"></div>
                
                <div v-if="!logLines.length && !running" class="h-full flex flex-col items-center justify-center opacity-10">
                   <div class="text-6xl mb-4">🌀</div>
                   <p class="text-[10px] font-black uppercase tracking-[0.4em]">Awaiting Initialization...</p>
                </div>
             </div>
          </div>

          <!-- SUMMARY REPORT -->
          <transition name="fade-slide">
             <div v-if="prediction" class="admin-card bg-blue-600/5 border-blue-500/20 rounded-[2.5rem] p-10 relative overflow-hidden">
                 <div class="absolute -right-10 -bottom-10 text-[10rem] font-black italic text-blue-500/[0.02] pointer-events-none">REPORT</div>
                 <h3 class="font-black text-xs text-blue-500 mb-8 flex items-center gap-3 uppercase tracking-[0.4em]">
                    <span>📊</span> CONSOLIDATED PROJECTION OUTPUT
                 </h3>
                 <div class="grid grid-cols-2 lg:grid-cols-4 gap-12">
                    <div class="space-y-2">
                       <label class="text-[9px] uppercase text-gray-600 font-black tracking-[0.2em] italic">System Criticality</label>
                       <div class="flex items-center gap-3">
                          <p class="text-3xl font-black italic tracking-tighter" :class="prediction.risk_level === 'low' ? 'text-green-500 underline decoration-green-500/40' : 'text-red-500 underline decoration-red-500/40'">
                             {{ prediction.risk_level.toUpperCase() }}
                          </p>
                          <span v-if="prediction.risk_level === 'low'" class="text-[10px] text-green-500/60 font-black uppercase mt-2 italic shadow-[0_0_15px_rgba(34,197,94,0.1)]">Secure</span>
                       </div>
                    </div>
                    <div class="space-y-2">
                       <label class="text-[9px] uppercase text-gray-600 font-black tracking-[0.2em] italic">GWP Prediction</label>
                       <p class="text-3xl font-black text-white italic tracking-tighter tabular-nums underline decoration-white/10 underline-offset-8">${{ prediction.expected_revenue.toLocaleString() }}</p>
                       <span class="text-[9px] text-gray-600 font-mono italic uppercase">Gross World Profit</span>
                    </div>
                    <div class="space-y-2">
                       <label class="text-[9px] uppercase text-gray-600 font-black tracking-[0.2em] italic">Volatility Churn</label>
                       <p class="text-3xl font-black text-blue-400 italic tracking-tighter">{{ prediction.projected_churn }}%</p>
                       <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden mt-2">
                          <div class="h-full bg-blue-500" :style="{ width: prediction.projected_churn + '%' }"></div>
                       </div>
                    </div>
                    <div class="space-y-2">
                       <label class="text-[9px] uppercase text-gray-600 font-black tracking-[0.2em] italic">Structural Constraints</label>
                       <div class="flex flex-wrap gap-2 mt-2">
                          <span v-for="b in prediction.bottlenecks" :key="b" class="px-3 py-1 bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 rounded-lg text-[9px] font-black uppercase italic tracking-widest">
                             {{ b }}
                          </span>
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
import { ref, inject, onMounted, nextTick } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading } = inject('adminContext');

const running = ref(false);
const logLines = ref([]);
const spikeIntensity = ref(0.4);
const prediction = ref(null);
const terminal = ref(null);

const simParams = {
  tick_resolution: '1 minute (60,000ms)',
  sample_size: 'Total Player Population (Real-time Scan)',
  simulation_kernel: 'OB_PRIME-KERN_v4.2',
  virtual_seed: 'SEC-8123-ALPHA-99'
};

const addLog = (text, type = 'info') => {
   const now = new Date();
   const time = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.' + now.getMilliseconds().toString().padStart(3, '0');
   logLines.value.push({
      time,
      text,
      type
   });
   nextTick(() => {
      if (terminal.value) terminal.value.scrollTop = terminal.value.scrollHeight;
   });
};

const runSim = async () => {
   running.value = true;
   logLines.value = [];
   prediction.value = null;
   
   addLog('INITIALIZING PREDICTIVE KERNEL...', 'info');
   addLog('ACQUIRING GLOBAL STATE SNAPSHOT FROM LIVE CLUSTERS...', 'info');
   
   setTimeout(() => addLog('LOAD BALANCERS: ANALYZING NETWORK TOPOLOGY...', 'info'), 800);
   setTimeout(() => addLog('ECONOMY ENGINE: PROCESSING GROWTH VECTORS...', 'info'), 1500);
   setTimeout(() => addLog('USER BEHAVIOR: CALCULATING SATISFACTION DECAY PROFILES...', 'info'), 2400);
   setTimeout(() => addLog('VALIDATING MONTE CARLO SAMPLE CONVERGENCE...', 'info'), 3200);

   try {
       const res = await api.post('/admin/simulation/run-24h');
       setTimeout(() => {
          if (res.success) {
              prediction.value = res.prediction;
              addLog('----------------------------------------------------');
              addLog('SYNCHRONIZATION COMPLETE. REPORT GENERATED.', 'success');
              addLog(`PROJECTED GWP: $${res.prediction.expected_revenue}`, 'success');
              addLog(`CRITICAL BOTTLENECK DETECTED: ${res.prediction.bottlenecks[0]}`, 'warning');
          }
          running.value = false;
        }, 4000);
   } catch (e) {
       addLog('FATAL: KERNEL PANIC - ' + e.message, 'error');
       running.value = false;
   }
};

const injectSpike = async () => {
    try {
        await api.post('/admin/simulation/spike', { intensity: spikeIntensity.value });
        addToast('Warning: Load spike injected into production clusters', 'warning');
        addLog(`MANUAL OVERRIDE: INJECTED SYNSHTETIC LOAD SPIKE (${(spikeIntensity.value * 100).toFixed(0)}%)`, 'warning');
    } catch (e) {
        addToast(e.message, 'error');
    }
};
</script>

<style scoped>
.simulation-tools { height: 100%; }
.protocol-card { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.protocol-card:hover { transform: translateY(-2px); }

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(59, 130, 246, 0.2); border-radius: 10px; }

input[type=range] { -webkit-appearance: none; background: transparent; }
input[type=range]:focus { outline: none; }
input[type=range]::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 20px; width: 20px;
  border-radius: 5px;
  background: #6366f1;
  cursor: pointer;
  margin-top: -8px;
  box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
  border: 4px solid #000;
}

.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-slide-enter-from { opacity: 0; transform: translateY(40px) scale(0.98); }
</style>
