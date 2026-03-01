<template>
  <div class="config-editor-view space-y-8 pb-12">
    <div class="flex justify-between items-start">
      <div>
        <h2 class="text-3xl font-extrabold tracking-tighter text-white uppercase italic underline decoration-blue-500/50 underline-offset-8">Core Engine Matrix</h2>
        <p class="text-gray-400 text-sm max-w-2xl mt-4">Direct access to low-level engine parameters and global constants. Every modification here is cryptographically versioned and affects the entire simulation fabric.</p>
      </div>
      <div class="flex gap-4">
         <div class="relative group">
            <input v-model="searchQuery" type="text" 
                   class="admin-input w-72 pl-10 h-12 rounded-xl border-white/5 bg-black/40 focus:ring-2 ring-blue-500/20" 
                   placeholder="SEARCH PARAMS..." />
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-500">🔍</span>
         </div>
         <button @click="fetchConfigs" class="btn btn-secondary px-6 uppercase text-[10px] font-black tracking-widest border-white/5 hover:bg-white/10">
            Re-Sync Repository
         </button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-32">
       <div class="flex flex-col items-center gap-6">
          <div class="w-16 h-16 border-4 border-blue-500/10 border-t-blue-500 rounded-full animate-spin"></div>
          <span class="text-[10px] font-mono text-blue-500 uppercase tracking-[0.3em] animate-pulse">Scanning Engine Fabric...</span>
       </div>
    </div>

    <!-- CONFIG GROUPS (Prime Styling) -->
    <div v-else class="space-y-12">
       <div v-for="(groupItems, groupName) in filteredConfigs" :key="groupName" class="admin-card border-white/5 bg-black/20 overflow-hidden group/groupbox">
          <div class="flex items-center justify-between mb-8 pb-4 border-b border-white/5 relative">
             <div class="flex items-center gap-4">
                <div class="w-2 h-8 bg-blue-600 rounded-full shadow-[0_0_12px_#2563eb]"></div>
                <h3 class="text-lg font-black uppercase tracking-[0.3em] text-white">{{ groupName }}</h3>
             </div>
             <div class="text-[9px] font-mono text-gray-600 bg-black/40 px-3 py-1 rounded-lg border border-white/5">
                INDEX_BLOCK: {{ groupItems.length }}
             </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-16 gap-y-10">
             <div v-for="config in groupItems" :key="config.id" class="config-field group/field relative">
                <!-- Field context and labeling -->
                <div class="flex justify-between items-center mb-3">
                   <label class="text-[10px] font-black text-gray-400 group-hover/field:text-blue-400 transition-colors flex items-center gap-2 uppercase tracking-widest">
                      <span class="text-blue-600 opacity-40">#</span> {{ config.key }}
                      <span class="group relative inline-flex items-center">
                         <span class="cursor-help text-gray-700 hover:text-blue-500 transition-colors text-[10px]">ⓘ</span>
                         <div class="invisible group-hover:visible absolute z-50 w-72 p-4 bg-black/95 backdrop-blur-xl border border-white/10 rounded-2xl text-[10px] font-bold text-gray-400 -top-2 left-6 shadow-2xl leading-relaxed italic">
                            {{ config.description || 'System-critical engine constant. Proceed with extreme caution.' }}
                         </div>
                      </span>
                   </label>
                   <span class="text-[8px] font-mono text-gray-700 uppercase">Ver: 1.0.{{ config.id }}</span>
                </div>

                <div class="flex gap-4 items-start">
                   <div class="flex-1 relative group/input">
                      <!-- JSON EDITOR for Objects/Arrays -->
                      <div v-if="isComplex(config.value)" class="relative">
                         <textarea v-model="config.editValue"
                                   class="admin-input w-full font-mono text-[11px] h-32 resize-y p-4 bg-black/60 border-white/5 focus:border-blue-500/40 custom-scrollbar leading-relaxed"
                                   placeholder="{}"
                         ></textarea>
                         <div class="absolute right-4 bottom-4 text-[9px] font-bold text-purple-500 pointer-events-none opacity-40">JSON_BLOB</div>
                      </div>
                      
                      <!-- SIMPLE INPUT for Strings/Numbers -->
                      <div v-else class="relative">
                         <input v-model="config.editValue"
                                class="admin-input w-full h-12 px-5 bg-black/60 border-white/5 focus:border-blue-500/40 font-mono text-sm text-blue-100"
                                :type="isNumber(config.value) ? 'number' : 'text'"
                                :step="isNumber(config.value) ? '0.01' : '1'"
                         />
                         <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black text-gray-700 pointer-events-none uppercase">
                            {{ isNumber(config.value) ? 'Scalar' : 'String' }}
                         </div>
                      </div>
                   </div>

                   <button @click="saveConfig(config)" 
                           class="h-12 px-6 rounded-xl flex items-center justify-center gap-3 transition-all font-black uppercase text-[10px] tracking-widest"
                           :class="hasChanged(config) ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20 active:scale-95' : 'bg-white/5 text-gray-600 cursor-not-allowed border border-white/5'"
                           :disabled="!hasChanged(config)"
                   >
                      <span v-if="savingKey === config.key" class="animate-spin text-xl">◌</span>
                      <span v-else class="text-lg">💾</span>
                      Commit
                   </button>
                </div>

                <!-- METADATA & HISTORY -->
                <div class="mt-4 flex justify-between items-center px-2">
                   <div class="flex items-center gap-3">
                      <div class="flex h-1.5 w-1.5 relative">
                         <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-gray-600 opacity-20"></span>
                         <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-gray-700"></span>
                      </div>
                      <p class="text-[8px] text-gray-600 font-bold uppercase tracking-widest italic">Last Synced: {{ formatTime(config.updated_at) }}</p>
                   </div>
                   <button class="text-[9px] font-black text-blue-500 hover:text-white transition-colors uppercase tracking-[0.2em] border-b border-blue-500/20" @click="showHistory(config.key)">
                      History Logs
                   </button>
                </div>
             </div>
          </div>
       </div>

       <div v-if="Object.keys(filteredConfigs).length === 0" class="admin-card border-dashed border-2 py-32 flex flex-col items-center justify-center opacity-30">
          <span class="text-4xl mb-4">🔍</span>
          <p class="text-[10px] font-black uppercase tracking-widest">Repository scan complete. No matches found for "{{ searchQuery }}".</p>
       </div>
    </div>

    <!-- MODAL: CONFIG HISTORY (Prime Styling) -->
    <div v-if="historyModal.show" class="admin-modal-overlay">
       <div class="admin-card w-[900px] max-h-[85vh] flex flex-col glass-effect p-8 border-white/10 shadow-3xl overflow-hidden">
          <div class="flex justify-between items-start mb-10">
             <div class="flex gap-4 items-center">
                <div class="p-4 bg-blue-600/10 rounded-2xl border border-blue-500/30 text-3xl">📜</div>
                <div>
                   <span class="text-[10px] uppercase font-bold text-blue-500 tracking-widest">Version Repository</span>
                   <h3 class="text-2xl font-black text-white uppercase tracking-tighter">{{ historyModal.key }}</h3>
                </div>
             </div>
             <button @click="historyModal.show = false" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 hover:bg-red-500/20 hover:text-red-400 transition-all text-xl">✕</button>
          </div>
          
          <div class="flex-1 overflow-y-auto custom-scrollbar space-y-6 pr-4">
             <div v-for="h in historyModal.data" :key="h.id" class="p-6 rounded-3xl border border-white/5 bg-black/40 group/hist hover:bg-white/[0.02] transition-colors relative h-fit">
                <div class="absolute left-0 top-0 w-1 h-full bg-gray-800 group-hover/hist:bg-blue-600 transition-colors"></div>
                <div class="flex justify-between mb-6">
                   <div class="flex items-center gap-4">
                      <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-lg border border-blue-500/20 uppercase">Version v{{ h.version }}.0</span>
                      <span class="text-[10px] text-gray-500 font-mono tracking-tighter border-l border-white/10 pl-4 italic">{{ formatTime(h.created_at) }}</span>
                   </div>
                   <button @click="rollback(h)" class="btn btn-secondary px-6 py-2 text-[9px] font-black uppercase tracking-widest bg-blue-900/10 hover:bg-blue-600 hover:text-white transition-all">Revert to Version</button>
                </div>
                <div class="grid grid-cols-2 gap-8">
                   <div class="space-y-2">
                      <p class="text-[9px] text-gray-600 uppercase font-black tracking-widest flex justify-between">Previous State <span class="text-red-500 opacity-40">DEPRECATED</span></p>
                      <pre class="text-[10px] p-4 bg-red-950/10 rounded-2xl overflow-hidden max-h-32 text-red-500/60 font-mono italic">{{ h.old_value }}</pre>
                   </div>
                   <div class="space-y-2">
                      <p class="text-[9px] text-gray-600 uppercase font-black tracking-widest flex justify-between">Successor State <span class="text-green-500 opacity-40">COMMITTED</span></p>
                      <pre class="text-[10px] p-4 bg-green-950/10 rounded-2xl overflow-hidden max-h-32 text-green-500/60 font-mono italic">{{ h.new_value }}</pre>
                   </div>
                </div>
                <div class="mt-6 flex items-center gap-4 pt-4 border-t border-white/5">
                   <div class="w-2 h-2 rounded-full bg-blue-500/20"></div>
                   <p class="text-[11px] font-bold text-gray-400 italic">Auth_Comment: "{{ h.comment || 'System Automated Revision' }}"</p>
                </div>
             </div>
             
             <!-- Empty History State -->
             <div v-if="!historyModal.data.length" class="text-center py-20 opacity-20 italic">
                Scanning historical archives... No previous versions detected in this sector.
             </div>
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, requestConfirm, request2FA, setGlobalLoading } = inject('adminContext');

const loading = ref(true);
const configs = ref([]);
const searchQuery = ref('');
const savingKey = ref(null);

const fetchConfigs = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    if (res.success) {
      const grouped = res.configs;
      for (const group in grouped) {
        grouped[group].forEach(c => {
          c.originalValue = JSON.stringify(c.value);
          c.editValue = isComplex(c.value) ? JSON.stringify(c.value, null, 2) : c.value;
        });
      }
      configs.value = grouped;
    }
  } catch (e) {
    addToast('Security Failure: Could not synchronize configuration repository', 'error');
  } finally {
    loading.value = false;
  }
};

const filteredConfigs = computed(() => {
  if (!searchQuery.value) return configs.value;
  const q = searchQuery.value.toLowerCase();
  const filtered = {};
  
  for (const group in configs.value) {
    const items = configs.value[group].filter(c => 
      c.key.toLowerCase().includes(q) || 
      (c.description && c.description.toLowerCase().includes(q))
    );
    if (items.length > 0) filtered[group] = items;
  }
  return filtered;
});

const isComplex = (val) => typeof val === 'object' && val !== null;
const isNumber = (val) => typeof val === 'number';

const hasChanged = (config) => {
  let currentVal = config.editValue;
  if (isComplex(config.value)) {
     try {
       currentVal = JSON.stringify(JSON.parse(config.editValue));
     } catch (e) { return false; }
  } else if (isNumber(config.value)) {
     currentVal = Number(currentVal);
  }
  return currentVal !== JSON.parse(config.originalValue);
};

const saveConfig = async (config) => {
    let valueToSend = config.editValue;
    if (isComplex(config.value)) {
        try {
            valueToSend = JSON.parse(config.editValue);
        } catch (err) {
            addToast('Protocol Error: Corrupted JSON block detected.', 'error');
            return;
        }
    } else if (isNumber(config.value)) {
        valueToSend = Number(valueToSend);
    }

    const comment = prompt('Mission Log: Document rationale for this engine re-calibration:');
    if (comment === null) return; 

    savingKey.value = config.key;
    try {
        await api.post('/admin/configs/update', {
            key: config.key,
            value: valueToSend,
            comment: comment || 'Manual Engine Re-calibration'
        });
        addToast(`Core Parameter ${config.key} Synchronized.`, 'success');
        config.originalValue = JSON.stringify(valueToSend);
    } catch (e) {
        addToast(e.message, 'error');
    } finally {
        savingKey.value = null;
    }
};

const formatTime = (time) => {
  if (!time) return '---- -- -- --:--:--';
  const date = new Date(time);
  return date.toISOString().replace('T', ' ').split('.')[0];
};

// HISTORY LOGIC
const historyModal = ref({ show: false, key: '', data: [] });
const showHistory = async (key) => {
   // In a real app, call endpoint. Simulation for now.
   historyModal.value = { show: true, key, data: [] };
};

const rollback = (history) => {
   requestConfirm(`Operational Reversion: Are you certain you want to roll back ${history.config_key} to Version #${history.version}?`, async () => {
      setGlobalLoading(true);
      try {
         await api.post('/admin/configs/rollback', { history_id: history.id });
         addToast('Successor Reversion Complete.', 'success');
         fetchConfigs();
      } catch (e) {
         addToast(e.message, 'error');
      } finally {
         setGlobalLoading(false);
         historyModal.value.show = false;
      }
   });
};

onMounted(fetchConfigs);
</script>

<style scoped>
.admin-card { cursor: default; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(59, 130, 246, 0.2); border-radius: 10px; }

pre {
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
</style>
