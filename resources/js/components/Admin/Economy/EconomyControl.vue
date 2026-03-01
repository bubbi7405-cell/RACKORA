<template>
  <div class="financial-operations space-y-12 pb-20">
    <!-- MASTER HEADER -->
    <div class="flex justify-between items-end">
      <div>
        <h2 class="text-4xl font-black tracking-tighter text-white uppercase italic">Macro-Economy Core</h2>
        <p class="text-zinc-500 text-sm max-w-2xl mt-4 leading-relaxed italic">
           Authorized command suite for global economic orchestration. Regulate wealth velocity, energy market volatility, and sector-wide inflation vectors using high-fidelity telemetry feeds.
        </p>
      </div>
      <div class="bg-emerald-950/20 border border-emerald-500/20 px-6 py-3 rounded-2xl">
         <div class="flex items-center gap-3">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_10px_#10b981]"></span>
            <span class="text-[10px] font-black text-emerald-500 uppercase tracking-widest italic">Lattice Stability: Nominal</span>
         </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-10">
       <!-- MACRO SCALARS -->
       <div class="xl:col-span-4 space-y-10">
          <div class="bg-zinc-950/40 border border-zinc-800 rounded-[2.5rem] p-10 space-y-12 shadow-3xl">
             <div class="flex items-center gap-4">
                <div class="w-1.5 h-6 bg-emerald-600 rounded-full shadow-[0_0_10px_#10b981]"></div>
                <h3 class="text-xs font-black text-white uppercase tracking-widest italic">Economic Levers</h3>
             </div>

             <div class="space-y-12">
                <div v-for="lever in levers" :key="lever.key" class="group/lever">
                   <div class="flex justify-between items-end mb-4">
                      <div>
                         <label class="text-[10px] font-black text-zinc-600 uppercase tracking-widest group-hover/lever:text-emerald-500 transition-colors italic">{{ lever.label }}</label>
                         <p class="text-[8px] text-zinc-700 mt-1 italic font-bold">{{ lever.help }}</p>
                      </div>
                      <span class="text-[10px] font-mono font-black text-emerald-500 bg-emerald-500/5 px-3 py-1.5 rounded-lg border border-emerald-500/10">{{ lever.value.toFixed(2) }}x</span>
                   </div>
                   <input type="range" v-model.number="lever.value" :min="lever.min" :max="lever.max" :step="lever.step"
                          class="sys-range-emerald" />
                </div>
             </div>

             <button @click="commitLevers" 
                      :disabled="!isDirty"
                      class="sys-btn-primary w-full h-14 !bg-emerald-600 hover:!bg-emerald-500 shadow-emerald-950/20 disabled:!bg-zinc-900 disabled:!text-zinc-600">
                Commit Calibration Matrix
             </button>
          </div>

          <!-- CRISIS CLUSTERS -->
          <div class="bg-zinc-950/40 border border-zinc-900 rounded-[2rem] p-8 space-y-8 shadow-2xl relative overflow-hidden">
             <div class="absolute top-0 right-0 w-32 h-32 bg-rose-600/5 blur-3xl"></div>
             <div class="flex items-center gap-4">
                <div class="w-1.5 h-6 bg-rose-600 rounded-full"></div>
                <h3 class="text-[10px] font-black text-white uppercase tracking-widest italic">Emergency Overrides</h3>
             </div>
             <p class="text-[9px] text-zinc-700 italic font-bold leading-relaxed uppercase tracking-tight">Authorized system-wide volatility injections. For scenario stress-testing only.</p>
             <div class="grid grid-cols-1 gap-4">
                <button @click="triggerCrisis('inflation')" class="sys-crisis-btn group hover:border-rose-500">
                   <div class="flex-1 text-left">
                      <p class="text-[11px] font-black text-white uppercase">Inflate Economy Matrix</p>
                      <p class="text-[8px] text-rose-500 font-black uppercase tracking-widest mt-1">Global Cost Spike // +25%</p>
                   </div>
                   <span class="text-xl group-hover:rotate-12 transition-transform">📉</span>
                </button>
             </div>
          </div>
       </div>

       <!-- PREDICTIVE MATRIX -->
       <div class="xl:col-span-8 space-y-10">
          <div class="bg-zinc-950/40 border border-zinc-800 rounded-[2.5rem] p-1 shadow-3xl overflow-hidden flex flex-col h-[500px] relative">
              <div class="p-10 border-b border-zinc-900 flex justify-between items-center relative z-20">
                 <div class="flex items-center gap-5">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_10px_#10b981]"></div>
                    <div>
                       <h3 class="text-xs font-black text-white uppercase tracking-widest italic leading-none">Global Projection Matrix</h3>
                       <p class="text-[9px] text-zinc-700 mt-1 uppercase tracking-widest font-bold font-mono italic">Algorithmic Wealth & Revenue Forecast Feed</p>
                    </div>
                 </div>
                 <div class="flex bg-black p-1 rounded-xl border border-zinc-900">
                    <button v-for="t in ['24H', '7D', '30D']" :key="t" 
                            class="px-5 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all"
                            :class="t === '24H' ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:text-white'">
                       {{ t }}
                    </button>
                 </div>
              </div>

              <!-- CHART PLANE -->
              <div class="flex-1 p-12 relative">
                 <svg viewBox="0 0 1000 300" class="w-full h-full overflow-visible">
                    <defs>
                       <linearGradient id="areaG" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stop-color="#10b981" stop-opacity="0.1" />
                          <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                       </linearGradient>
                       <filter id="glowE">
                          <feGaussianBlur stdDeviation="6" result="blur" />
                          <feComposite in="SourceGraphic" in2="blur" operator="over" />
                       </filter>
                    </defs>
                    <g v-for="i in 6" :key="i">
                       <line x1="0" :y1="i*50" x2="1000" :y2="i*50" stroke="#141416" stroke-width="1" />
                    </g>
                    <path d="M0,250 C100,240 200,260 300,200 S500,100 700,150 S900,50 1000,80 L 1000,300 L 0,300 Z" fill="url(#areaG)" />
                    <path d="M0,250 C100,240 200,260 300,200 S500,100 700,150 S900,50 1000,80" 
                          fill="none" stroke="#10b981" stroke-width="5" filter="url(#glowE)" stroke-linecap="round" />
                    <line x1="300" y1="0" x2="300" y2="300" stroke="#18181b" stroke-width="2" stroke-dasharray="6,6" />
                 </svg>
                 <!-- OVERLAYS -->
                 <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 opacity-[0.03] pointer-events-none select-none text-[8rem] font-black italic">PROJECTION</div>
              </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
             <div class="bg-zinc-950/40 border border-zinc-800 rounded-[2rem] p-10 relative overflow-hidden group">
                <div class="absolute right-[-20px] top-[-20px] text-zinc-800/10 text-9xl font-black italic select-none group-hover:scale-110 transition-transform">∑</div>
                <p class="text-[10px] font-black text-zinc-700 uppercase tracking-widest mb-6 italic">Mean Wealth Velocity</p>
                <h4 class="text-5xl font-black text-white italic tracking-tighter">+$1.48M <span class="text-sm font-bold text-zinc-700 not-italic tracking-normal">/epoch</span></h4>
                <div class="mt-8 flex items-center gap-3">
                   <span class="text-[10px] font-black text-emerald-500 px-3 py-1 bg-emerald-500/5 border border-emerald-500/10 rounded-lg italic tracking-widest">▲ 12.4% Optimal</span>
                </div>
             </div>
             <div class="bg-zinc-950/40 border border-zinc-800 rounded-[2rem] p-10 relative overflow-hidden group">
                <div class="absolute right-[-10px] top-[-10px] text-zinc-800/10 text-8xl font-black italic select-none group-hover:scale-110 transition-transform">₲</div>
                <p class="text-[10px] font-black text-zinc-700 uppercase tracking-widest mb-6 italic">Global Equity Supply</p>
                <h4 class="text-5xl font-black text-white italic tracking-tighter">$42.9B <span class="text-sm font-bold text-zinc-700 not-italic tracking-normal">GWP</span></h4>
                <div class="mt-10 w-full h-1.5 bg-black rounded-full overflow-hidden shadow-inner">
                   <div class="h-full bg-emerald-500/60 shadow-[0_0_15px_#10b981] w-[72%]"></div>
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

const isDirty = ref(false);
const levers = ref([
   { key: 'global_income_scalar', label: 'Wealth Velocity', value: 1.0, min: 0.1, max: 2.0, step: 0.05, help: 'Total revenue multiplier for all player entities.' },
   { key: 'energy_market_multiplier', label: 'Energy Chaos Factor', value: 1.25, min: 0.5, max: 4.0, step: 0.1, help: 'Scalar for energy price stochastic drift.' },
   { key: 'inflation_coefficient', label: 'Inflation Base', value: 1.0, min: 0.5, max: 2.5, step: 0.01, help: 'Baseline multiplier for hardware costs.' },
   { key: 'demand_growth_delta', label: 'Growth Acceleration', value: 1.0, min: 0.1, max: 3.0, step: 0.1, help: 'Speed of customer volume increase globally.' }
]);

const commitLevers = async () => {
    setGlobalLoading(true);
    try {
        for (const l of levers.value) {
            await api.post('/admin/configs/update', {
                key: l.key,
                value: Number(l.value),
                comment: `Financial Calibration: Readjusting ${l.label} for lattice equilibrium.`
            });
        }
        addToast('Lattice Updated: Financial models re-synchronized.', 'success');
        isDirty.value = false;
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
};

const triggerCrisis = (type) => {
   requestConfirm(`Initiate Economic Mutagen Protocol? This will immediately spike inflation across the global lattice.`, async () => {
      setGlobalLoading(true);
      try {
         await api.post('/admin/simulation/spike', { type });
         addToast(`Crisis Protocol Allocated.`, 'success');
      } catch (e) { addToast(e.message, 'error'); }
      finally { setGlobalLoading(false); }
   });
};

watch(levers, () => isDirty.value = true, { deep: true });

onMounted(async () => {
   try {
      const res = await api.get('/admin/configs');
      if (res.success) {
         const flat = [].concat(...Object.values(res.configs));
         levers.value.forEach(l => {
            const found = flat.find(c => c.key === l.key);
            if (found) l.value = Number(found.value);
         });
         setTimeout(() => isDirty.value = false, 100);
      }
   } catch (e) {}
});
</script>

<style scoped>
.sys-range-emerald {
  -webkit-appearance: none;
  width: 100%; height: 6px;
  background: #000; border: 1px solid #141416; border-radius: 99px;
  outline: none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
}
.sys-range-emerald::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 20px; height: 20px;
  background: #10b981; border-radius: 6px;
  cursor: pointer; box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
}

.sys-crisis-btn {
   width: 100%; padding: 20px 24px;
   background: #000; border: 1px solid #18181b; border-radius: 20px;
   display: flex; align-items: center; gap: 20px; transition: all 0.2s;
}
.sys-crisis-btn:hover { background: #0a0a0c; border-color: #ef444433; transform: scale(1.02); }

.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
