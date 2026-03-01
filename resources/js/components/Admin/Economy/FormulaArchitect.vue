<template>
  <div class="la-container">
    <!-- HEADER -->
    <div class="la-header">
      <div class="la-title-row">
        <div>
          <h2 class="la-title">Logic Architect</h2>
          <p class="la-subtitle">Dynamic formula injection — redefine mathematical models and behavioral heuristics in real-time.</p>
        </div>
        <span class="compiler-pill">
          <span class="compiler-dot"></span>
          Compiler v2.4.9
        </span>
      </div>
    </div>

    <!-- FORMULA CARDS -->
    <div class="formula-list">
      <div v-for="f in formulas" :key="f.key" class="formula-card">
        <div class="formula-layout">

          <!-- LEFT: CODE EDITOR -->
          <div class="editor-side">
            <div class="editor-header">
              <div class="editor-identity">
                <div class="fn-icon">ƒ(x)</div>
                <div>
                  <span class="fn-label">{{ f.label }}</span>
                  <span class="fn-key">{{ f.key }}</span>
                </div>
              </div>
              <button @click="saveFormula(f)" :disabled="!f.isDirty || f.error" class="deploy-btn" :class="{ active: f.isDirty && !f.error }">
                Deploy Logic
              </button>
            </div>

            <!-- CODE AREA -->
            <div class="code-area">
              <div class="line-numbers">
                <span v-for="n in 8" :key="n">{{ n }}</span>
              </div>
              <textarea v-model="f.value" @input="onInput(f)" spellcheck="false" class="code-input" placeholder="/* Implement logic module */"></textarea>

              <!-- STATUS BADGE -->
              <div class="status-badge" :class="f.error ? 'status-error' : 'status-valid'">
                <span class="status-dot"></span>
                {{ f.error ? 'Syntax Error' : 'Valid' }}
              </div>
            </div>

            <!-- REGISTERED VARIABLES -->
            <div class="var-row">
              <span class="var-label">Variables:</span>
              <span v-for="v in f.variables" :key="v" class="var-tag">${{ v }}</span>
            </div>
          </div>

          <!-- RIGHT: SANDBOX -->
          <div class="sandbox-side">
            <div class="sandbox-header">
              <span class="sandbox-title">Sandbox</span>
              <span class="sandbox-env">LAB</span>
            </div>

            <!-- SAMPLE INPUTS -->
            <div class="sample-grid">
              <div v-for="(val, vName) in f.sampleVars" :key="vName" class="sample-field">
                <label>{{ vName }}</label>
                <input v-model.number="f.sampleVars[vName]" type="number" step="0.1" />
              </div>
            </div>

            <!-- RESULT -->
            <div class="result-box">
              <span class="result-label">Output</span>
              <span class="result-value">{{ calculate(f) }}</span>
              <div v-if="f.error" class="result-error">{{ f.error }}</div>
            </div>

            <!-- IMPACT METRICS -->
            <div class="impact-row">
              <div class="impact-tile">
                <span class="impact-key">Baseline Diff</span>
                <span class="impact-val impact-emerald">+12.4%</span>
              </div>
              <div class="impact-tile">
                <span class="impact-key">24h Projection</span>
                <span class="impact-val impact-indigo">Nominal</span>
              </div>
            </div>

            <div class="sandbox-footer">
              <span class="recalc-dot"></span>
              <span>Auto-recalculating on vector drift</span>
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
    key: 'formula_churn', label: 'Customer Retention Logic',
    value: 'base + (power_failures * 0.05) + (utilization * 0.01)',
    original: '', isDirty: false, error: null,
    variables: ['base', 'power_failures', 'utilization'],
    sampleVars: { base: 0.1, power_failures: 2, utilization: 80 }
  },
  {
    key: 'formula_power_cost', label: 'Regional Energy Matrix',
    value: 'base_rate * (demand_factor / volatility)',
    original: '', isDirty: false, error: null,
    variables: ['base_rate', 'demand_factor', 'volatility'],
    sampleVars: { base_rate: 0.15, demand_factor: 1.2, volatility: 0.9 }
  },
  {
    key: 'formula_revenue_efficiency', label: 'Revenue Yield Factor',
    value: 'uptime * (1 - hardware_age) * multiplier',
    original: '', isDirty: false, error: null,
    variables: ['uptime', 'hardware_age', 'multiplier'],
    sampleVars: { uptime: 0.99, hardware_age: 0.2, multiplier: 1.5 }
  }
]);

const onInput = (f) => { f.isDirty = true; validate(f); };

const validate = (f) => {
  try {
    if (!f.value) throw new Error('EMPTY');
    let test = f.value;
    f.variables.forEach(v => { test = test.replace(new RegExp(v, 'g'), '1'); });
    if (/[^0-9.\+\-\*\/\(\)\s]/.test(test)) throw new Error('UNAUTHORIZED_SYMBOLS');
    new Function(`return ${test}`)();
    f.error = null;
  } catch (e) { f.error = e.message; }
};

const calculate = (f) => {
  if (f.error) return '–––';
  try {
    let expr = f.value;
    for (const [v, val] of Object.entries(f.sampleVars)) {
      expr = expr.replace(new RegExp(v, 'g'), val);
    }
    const res = new Function(`return ${expr}`)();
    return isNaN(res) ? 'NAN' : Number(res).toFixed(4);
  } catch { return 'ERR'; }
};

const saveFormula = async (f) => {
  setGlobalLoading(true);
  try {
    await api.post('/admin/configs/update', { key: f.key, value: f.value, comment: `Logic recalibration: ${f.label}` });
    addToast('Logic module deployed.', 'success');
    f.isDirty = false;
    f.original = f.value;
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

onMounted(async () => {
  try {
    const res = await api.get('/admin/configs');
    if (res.success) {
      const flat = [].concat(...Object.values(res.configs));
      formulas.value.forEach(f => {
        const found = flat.find(c => c.key === f.key);
        if (found) { f.value = found.value; f.original = found.value; }
      });
    }
  } catch {}
});
</script>

<style scoped>
.la-container { display: flex; flex-direction: column; }
.la-header { margin-bottom: 24px; }
.la-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.la-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.la-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.compiler-pill { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 99px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border: 1px solid #312e81; background: #0a0a0c; color: #818cf8; }
.compiler-dot { width: 6px; height: 6px; border-radius: 50%; background: #6366f1; }

/* FORMULA LIST */
.formula-list { display: flex; flex-direction: column; gap: 20px; }

.formula-card { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; overflow: hidden; transition: border-color 0.2s; }
.formula-card:hover { border-color: #27272a; }

.formula-layout { display: grid; grid-template-columns: 1fr 360px; }

/* EDITOR SIDE */
.editor-side { padding: 24px; border-right: 1px solid #18181b; display: flex; flex-direction: column; gap: 16px; }

.editor-header { display: flex; justify-content: space-between; align-items: center; }
.editor-identity { display: flex; align-items: center; gap: 12px; }
.fn-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 900; color: #818cf8; font-family: 'JetBrains Mono', monospace; }
.fn-label { display: block; font-size: 0.72rem; font-weight: 800; color: #e4e4e7; text-transform: uppercase; letter-spacing: 0.05em; }
.fn-key { display: block; font-size: 0.55rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; }

.deploy-btn { height: 34px; padding: 0 18px; border-radius: 10px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: all 0.2s; background: #18181b; border: 1px solid #27272a; color: #52525b; }
.deploy-btn:disabled { cursor: not-allowed; opacity: 0.4; }
.deploy-btn.active { background: #4338ca; border-color: #6366f1; color: #fff; box-shadow: 0 0 20px rgba(99,102,241,0.3); }

/* CODE AREA */
.code-area { position: relative; display: flex; height: 200px; background: #050505; border: 1px solid #18181b; border-radius: 12px; overflow: hidden; }
.line-numbers { width: 40px; background: rgba(24,24,27,0.5); border-right: 1px solid #18181b; padding-top: 14px; display: flex; flex-direction: column; align-items: center; gap: 3px; font-size: 0.6rem; font-family: 'JetBrains Mono', monospace; color: #27272a; user-select: none; }
.code-input { flex: 1; background: transparent; border: none; padding: 14px 16px; color: #c4b5fd; font-family: 'JetBrains Mono', monospace; font-size: 0.72rem; line-height: 1.8; outline: none; resize: none; }
.code-input::placeholder { color: #27272a; }
.code-input::-webkit-scrollbar { width: 3px; }
.code-input::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }

.status-badge { position: absolute; right: 12px; bottom: 12px; display: flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 8px; font-size: 0.5rem; font-weight: 800; text-transform: uppercase; }
.status-valid { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #4ade80; }
.status-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #f87171; }
.status-dot { width: 5px; height: 5px; border-radius: 50%; }
.status-valid .status-dot { background: #10b981; }
.status-error .status-dot { background: #ef4444; animation: pulse-err 1.5s infinite; }
@keyframes pulse-err { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.var-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.var-label { font-size: 0.55rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.1em; margin-right: 4px; }
.var-tag { padding: 2px 8px; background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.15); border-radius: 6px; font-size: 0.55rem; font-family: 'JetBrains Mono', monospace; color: #818cf8; transition: all 0.15s; cursor: help; }
.var-tag:hover { background: #4338ca; color: #fff; }

/* SANDBOX SIDE */
.sandbox-side { padding: 24px; background: rgba(24,24,27,0.3); display: flex; flex-direction: column; gap: 16px; }
.sandbox-header { display: flex; justify-content: space-between; align-items: center; }
.sandbox-title { font-size: 0.65rem; font-weight: 800; color: #52525b; text-transform: uppercase; letter-spacing: 0.12em; }
.sandbox-env { font-size: 0.5rem; font-family: 'JetBrains Mono', monospace; color: rgba(99,102,241,0.4); text-transform: uppercase; }

.sample-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.sample-field label { display: block; font-size: 0.5rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
.sample-field input { width: 100%; height: 32px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 8px; padding: 0 10px; font-family: 'JetBrains Mono', monospace; font-size: 0.65rem; color: #fafafa; outline: none; box-sizing: border-box; -moz-appearance: textfield; }
.sample-field input:focus { border-color: #6366f1; }
.sample-field input::-webkit-outer-spin-button,
.sample-field input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

/* RESULT */
.result-box { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #050505; border: 1px solid #18181b; border-radius: 14px; padding: 24px; text-align: center; min-height: 100px; }
.result-label { font-size: 0.5rem; font-weight: 800; color: #6366f1; text-transform: uppercase; letter-spacing: 0.2em; margin-bottom: 8px; }
.result-value { font-size: 2rem; font-weight: 900; color: #fafafa; font-style: italic; font-family: 'JetBrains Mono', monospace; filter: drop-shadow(0 0 15px rgba(99,102,241,0.4)); }
.result-error { font-size: 0.55rem; color: #f87171; font-weight: 800; text-transform: uppercase; margin-top: 6px; }

.impact-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.impact-tile { background: #111; border: 1px solid #1c1c1e; border-radius: 10px; padding: 10px; text-align: center; }
.impact-key { display: block; font-size: 0.45rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
.impact-val { display: block; font-size: 0.72rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; }
.impact-emerald { color: #4ade80; }
.impact-indigo { color: #818cf8; }

.sandbox-footer { display: flex; align-items: center; gap: 8px; font-size: 0.55rem; color: #3f3f46; font-weight: 600; font-style: italic; }
.recalc-dot { width: 5px; height: 5px; border-radius: 50%; background: #27272a; }
</style>
