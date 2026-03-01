<template>
  <div class="logic-architect space-y-12 pb-20">
    <!-- MASTER HEADER -->
    <div class="flex justify-between items-end">
      <div>
        <h2 class="text-4xl font-black tracking-tighter text-white uppercase italic">Logic & Formula Architect</h2>
        <p class="text-zinc-500 text-sm max-w-2xl mt-4 leading-relaxed italic">
           Authoritative environment for engine logic injection. Redefine mathematical constants and dynamic equations within the live simulation loop with real-time sandbox verification.
        </p>
      </div>
      <div class="flex flex-col items-end gap-2">
         <div class="flex items-center gap-3 bg-blue-950/20 text-blue-400 border border-blue-500/30 px-6 py-2 rounded-2xl text-[10px] font-black tracking-[0.2em] shadow-[0_0_15px_rgba(59,130,246,0.1)]">
            <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse shadow-[0_0_10px_#3b82f6]"></span>
            <span class="italic font-black">KERNEL: DYNAMIC_INJECTION_MODE</span>
         </div>
      </div>
    </div>

    <!-- FORMULA CLUSTERS -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-10">
      <div v-for="f in formulas" :key="f.key" 
           class="bg-zinc-950/40 border border-zinc-800 rounded-[2.5rem] p-10 space-y-8 group hover:border-blue-500/20 transition-all shadow-3xl relative overflow-hidden">
         
         <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-600 transition-all duration-500 opacity-20" :class="{ 'opacity-100 shadow-[0_0_15px_#3b82f6]': f.isDirty }"></div>

         <div class="flex justify-between items-start relative z-10">
            <div class="flex items-center gap-5">
               <div class="w-12 h-12 bg-blue-600/10 rounded-2xl border border-blue-500/20 flex items-center justify-center text-xl text-blue-400 font-mono">ƒ</div>
               <div>
                  <h3 class="text-sm font-black text-white uppercase tracking-tight italic">{{ f.label }}</h3>
                  <p class="text-[9px] font-mono text-zinc-600 uppercase tracking-widest mt-1">LatticeID: {{ f.key }}</p>
               </div>
            </div>
            <button @click="saveFormula(f)" 
                    :disabled="!f.isDirty"
                    class="sys-btn-primary h-10 px-6 rounded-xl text-[9px] font-black uppercase tracking-widest italic disabled:opacity-20">
               Sync Logic
            </button>
         </div>

         <!-- SIMULATED CODE EDITOR -->
         <div class="relative group/editor">
            <div class="absolute right-6 top-6 flex gap-3 z-20">
               <span v-if="f.error" class="text-[9px] font-black text-rose-500 uppercase bg-rose-500/10 px-2 py-1 rounded border border-rose-500/20 italic animate-pulse">Syntax Collision</span>
               <span v-else class="text-[9px] font-black text-emerald-500 uppercase bg-emerald-500/10 px-2 py-1 rounded border border-emerald-500/20 italic">Validated</span>
            </div>
            
            <div class="bg-black/80 rounded-3xl border border-zinc-900 overflow-hidden shadow-inner flex">
               <div class="w-12 bg-zinc-950/40 border-r border-zinc-900 flex flex-col items-center pt-6 text-[9px] font-mono text-zinc-800 select-none">
                  <span v-for="n in 6" :key="n" class="block h-6 leading-none">{{ n }}</span>
               </div>
               <textarea 
                  v-model="f.value" 
                  @input="f.isDirty = true; validate(f)"
                  class="flex-1 min-h-[160px] bg-transparent p-6 outline-none font-mono text-[11px] leading-relaxed text-blue-400 placeholder-zinc-800 custom-scrollbar resize-none"
                  placeholder="/* Define algorithmic logic here */"
               ></textarea>
            </div>
         </div>

         <!-- SANDBOX REAL-TIME EVALUATOR -->
         <div class="bg-zinc-900/40 rounded-[2rem] p-8 border border-zinc-800/50 relative overflow-hidden shadow-inner">
            <div class="flex justify-between items-center mb-8">
               <h4 class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.3em] italic">Lattice Variable Injection</h4>
               <span class="text-[8px] font-mono text-blue-500/60 uppercase italic tracking-widest font-black">Isolated_Sand</span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
               <div v-for="(val, vName) in f.sampleVars" :key="vName" class="space-y-2">
                  <label class="text-[9px] text-zinc-700 uppercase font-black tracking-tight italic">{{ vName.replace('_',' ') }}</label>
                  <input v-model.number="f.sampleVars[vName]" type="number" step="0.1" class="w-full h-9 bg-black/60 border border-zinc-800 rounded-lg text-[10px] font-mono text-center text-white outline-none focus:border-blue-500 transition-all" />
               </div>
            </div>

            <div class="flex items-center gap-8 p-8 bg-black rounded-2xl border border-zinc-800/50 shadow-2xl relative overflow-hidden">
               <div class="absolute right-0 bottom-0 top-0 w-32 bg-gradient-to-l from-blue-600/5 to-transparent"></div>
               <div class="flex flex-col relative z-10">
                  <span class="text-[9px] font-black text-blue-500 uppercase tracking-widest mb-2 italic">Result Vector</span>
                  <span class="font-mono text-5xl font-black text-white italic tracking-tighter transition-all" :class="{ 'opacity-20': f.error }">
                     {{ calculateResult(f) }}
                  </span>
               </div>
               <div class="flex-1 opacity-10 pointer-events-none px-4">
                  <svg viewBox="0 0 200 40" class="w-full h-10">
                     <path :d="simulateWave()" fill="none" stroke="#3b82f6" stroke-width="2" stroke-dasharray="4,4" class="animate-pulse"></path>
                  </svg>
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

const { addToast, setGlobalLoading } = inject('adminContext');

const formulas = ref([
  {
    key: 'formula_churn',
    label: 'Retention Algorithm',
    value: 'base + (power_failures * 0.05) + (utilization * 0.01)',
    original: '',
    isDirty: false,
    error: null,
    variables: ['base', 'power_failures', 'utilization'],
    sampleVars: { base: 0.1, power_failures: 2, utilization: 80 }
  },
  {
    key: 'formula_power_cost',
    label: 'Lattice Energy Pricing',
    value: 'base_rate * (demand_factor / volatility)',
    original: '',
    isDirty: false,
    error: null,
    variables: ['base_rate', 'demand_factor', 'volatility'],
    sampleVars: { base_rate: 0.15, demand_factor: 1.2, volatility: 0.9 }
  },
  {
      key: 'formula_repair_success',
      label: 'Success Delta Matrix',
      value: 'skill_level * (1 - wear_and_tear)',
      original: '',
      isDirty: false,
      error: null,
      variables: ['skill_level', 'wear_and_tear'],
      sampleVars: { skill_level: 0.8, wear_and_tear: 0.3 }
  }
]);

const validate = (f) => {
   try {
      const expr = f.value;
      if (!expr) throw new Error('EMPTY_MANIFEST');
      let testExpr = expr;
      f.variables.forEach(v => { testExpr = testExpr.replace(new RegExp(v, 'g'), '1'); });
      if (/[^0-9\.\+\-\*\/\(\)\s]/.test(testExpr)) throw new Error('UNAUTHORIZED_CHAR');
      new Function(`return ${testExpr}`)();
      f.error = null;
   } catch (e) { f.error = e.message; }
};

const calculateResult = (f) => {
    if (f.error) return 'COLLISION';
    try {
        let expr = f.value;
        for (const [v, val] of Object.entries(f.sampleVars)) {
            expr = expr.replace(new RegExp(v, 'g'), val);
        }
        const res = new Function(`return ${expr}`)();
        return isNaN(res) ? 'NaN' : Number(res).toFixed(4);
    } catch (e) { return 'EVAL_ERR'; }
};

const simulateWave = () => {
   let p = [];
   for (let i = 0; i < 200; i += 10) p.push(`${i},${20 + Math.sin(i / 15) * 8}`);
   return `M ${p.join(' L ')}`;
}

const saveFormula = async (f) => {
   if (f.error) { addToast('Logic Collision: Cannot deploy corrupted syntax.', 'error'); return; }
   setGlobalLoading(true);
   try {
       await api.post('/admin/configs/update', {
           key: f.key,
           value: f.value,
           comment: `Dynamic Logic Compilation: Mutating ${f.label} vector.`
       });
       addToast('Logic Injected & Compiled Successfully', 'success');
       f.isDirty = false;
    } catch (e) { addToast(e.message, 'error'); } 
    finally { setGlobalLoading(false); }
};

onMounted(async () => {
   try {
       const res = await api.get('/admin/configs');
       if (res.success) {
           const dbConfigs = res.configs.simulation || [];
           formulas.value.forEach(f => {
               const found = dbConfigs.find(c => c.key === f.key);
               if (found) { f.value = found.value; f.original = found.value; }
           });
       }
   } catch (e) {}
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }
</style>
