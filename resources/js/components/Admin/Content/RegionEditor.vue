<template>
  <div class="region-editor space-y-8">
    <div class="flex justify-between items-start">
      <div>
        <h2 class="text-3xl font-extrabold tracking-tight text-white mb-2 underline decoration-blue-500/50 underline-offset-8 uppercase">Global Geospatial Hub</h2>
        <p class="text-gray-400 text-sm max-w-2xl">Manage sovereign infrastructure nodes, regional energy markets, and latency corridors. Your geopolitical decisions define the cost efficiency of the network.</p>
      </div>
      <div class="flex gap-4">
         <div class="flex items-center gap-3 bg-black/40 px-4 py-2 rounded-xl border border-white/5 font-mono text-xs">
            <span class="flex h-2 w-2 relative">
               <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-500 opacity-75"></span>
               <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
            </span>
            NODES ACTIVE: {{ regions.length }}
         </div>
         <button @click="addNewRegion" class="btn btn-primary text-[11px] uppercase tracking-widest px-6 shadow-blue-600/20 shadow-lg">
            Deploy New Node
         </button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">
       <!-- INTERACTIVE GLOBAL MAP -->
       <div class="xl:col-span-3 admin-card p-0 overflow-hidden min-h-[600px] bg-[#020408] relative border-white/10 group rounded-3xl group">
          <div class="absolute inset-0 opacity-10 pointer-events-none">
             <div class="w-full h-full" :style="{ backgroundImage: 'radial-gradient(circle, #334155 1px, transparent 1px)', backgroundSize: '32px 32px' }"></div>
          </div>
          
          <div class="absolute top-6 left-6 z-10">
             <div class="bg-black/80 backdrop-blur-md p-4 rounded-2xl border border-white/5 text-[10px] space-y-1.5 shadow-2xl">
                <p class="text-gray-500 font-black uppercase mb-2 tracking-widest border-b border-white/5 pb-2">Operational Overlay</p>
                <div class="flex items-center gap-2 font-bold text-blue-400"><span class="w-2 h-2 rounded-full bg-blue-500 block"></span> LOW LATENCY / HIGH PROFIT</div>
                <div class="flex items-center gap-2 font-bold text-yellow-500"><span class="w-2 h-2 rounded-full bg-yellow-500 block"></span> TRANSITIONAL ZONE</div>
                <div class="flex items-center gap-2 font-bold text-red-500"><span class="w-2 h-2 rounded-full bg-red-500 block"></span> HIGH ENERGY VOLATILITY</div>
             </div>
          </div>

          <!-- SVG MAP INTERFACE -->
          <svg viewBox="0 0 1000 600" class="w-full h-full select-none cursor-crosshair active:cursor-grabbing" @mousemove="onMouseMove" @mouseup="onMouseUp">
             <!-- Geometric Grid Lines -->
             <g v-for="i in 10" :key="'v-'+i">
                <line :x1="i * 100" y1="0" :x2="i * 100" y2="600" stroke="rgba(255,255,255,0.02)" stroke-width="1" />
             </g>
             <g v-for="i in 6" :key="'h-'+i">
                <line x1="0" :y1="i * 100" x2="1000" :y2="i * 100" stroke="rgba(255,255,255,0.02)" stroke-width="1" />
             </g>

             <!-- Node Connections (Lines) -->
             <path v-if="regions.length > 1" :d="getConnectionPath()" fill="none" stroke="rgba(59, 130, 246, 0.05)" stroke-width="1.5" stroke-dasharray="10,5" />

             <!-- Nodes -->
             <g v-for="(r, idx) in regions" :key="idx" 
                class="region-node transition-all group/node"
                :class="{ 'active': activeRegionIndex === idx }"
                @mousedown="onMouseDown($event, idx)"
             >
                <!-- Outer Halo -->
                <circle :cx="r.x" :cy="r.y" :r="activeRegionIndex === idx ? 60 : 25" 
                        class="transition-all duration-500"
                        :fill="activeRegionIndex === idx ? 'rgba(59, 130, 246, 0.03)' : 'transparent'" 
                        :stroke="activeRegionIndex === idx ? 'rgba(59, 130, 246, 0.15)' : 'transparent'" 
                        stroke-dasharray="4,4" />
                
                <!-- Inner Glow Pulse -->
                <circle v-if="activeRegionIndex === idx" :cx="r.x" :cy="r.y" r="1.5" fill="#3b82f6" opacity="0.8" class="animate-ping" />
                
                <!-- Main Core -->
                <circle :cx="r.x" :cy="r.y" r="8" :fill="getNodeColor(r, idx)" class="node-pulse drop-shadow-[0_0_10px_currentColor]" />
                <circle :cx="r.x" :cy="r.y" r="14" fill="none" :stroke="getNodeColor(r, idx)" stroke-width="1" opacity="0.2" />
                
                <!-- Identity Labels -->
                <text :x="r.x" :y="r.y + 35" text-anchor="middle" fill="white" font-size="10" font-weight="900" class="pointer-events-none uppercase tracking-widest font-mono">{{ r.name }}</text>
                <text :x="r.x" :y="r.y + 48" text-anchor="middle" :fill="getNodeColor(r, idx)" font-size="8" font-family="monospace" font-weight="bold" class="pointer-events-none">
                   BASE: {{ (r.energyPrice || 0).toFixed(2) }} | LIVE: {{ (getLivePrice(idx) || r.energyPrice).toFixed(3) }}
                </text>
             </g>
          </svg>

          <div class="absolute bottom-6 right-6 text-[9px] font-mono text-gray-700 pointer-events-none">
             LATENCY DELTA VISUALIZATION v4.2 // SECURITY CLEARANCE: ADMIN
          </div>
       </div>

       <!-- CONTROL PANEL (SIDEBAR) -->
       <div class="xl:col-span-1 space-y-6">
          <transition name="fade-slide" mode="out-in">
             <div v-if="activeRegion" :key="activeRegionIndex" class="admin-card border-blue-500/30 bg-blue-900/5 backdrop-blur-xl space-y-6">
                <div class="flex justify-between items-center mb-2">
                   <div class="flex items-center gap-3">
                      <div class="w-3 h-3 rounded-full" :class="activeRegion.risk > 50 ? 'bg-red-500' : 'bg-green-500'"></div>
                      <h3 class="font-black text-xl uppercase tracking-tighter text-white">{{ activeRegion.name }}</h3>
                   </div>
                   <button @click="deleteRegion" class="text-[10px] font-bold text-red-500/60 hover:text-red-500 uppercase tracking-widest underline underline-offset-4">Terminate</button>
                </div>

                <div class="space-y-6">
                   <div>
                      <label class="text-[10px] uppercase font-black text-gray-500 mb-2 block tracking-widest">Zone Designation</label>
                      <input v-model="activeRegion.name" type="text" class="admin-input w-full" />
                   </div>
                   
                   <div class="grid grid-cols-2 gap-4">
                      <div>
                         <label class="text-[10px] uppercase font-black text-gray-500 mb-2 block tracking-widest">Base Energy ($/kWh)</label>
                         <input v-model.number="activeRegion.energyPrice" type="number" step="0.01" class="admin-input w-full font-mono text-center" />
                      </div>
                      <div>
                         <label class="text-[10px] uppercase font-black text-gray-500 mb-2 block tracking-widest">Live Spot Rate</label>
                         <div class="admin-input w-full font-mono text-center flex items-center justify-center text-blue-400 bg-blue-900/20 border-blue-500/30">
                            {{ (getLivePrice(activeRegion.key || activeRegionIndex) || activeRegion.energyPrice).toFixed(4) }}
                         </div>
                      </div>
                   </div>

                   <div>
                       <div class="flex justify-between mb-2">
                           <label class="text-[10px] uppercase font-black text-gray-500 tracking-widest">Base Latency</label>
                           <span class="text-xs font-mono text-gray-400">{{ activeRegion.latency }}ms</span>
                       </div>
                       <input v-model.number="activeRegion.latency" type="number" class="admin-input w-full font-mono text-center" />
                   </div>

                   <div>
                      <div class="flex justify-between mb-2">
                         <label class="text-[10px] uppercase font-black text-gray-500 tracking-widest">Network Instability / Risk</label>
                         <span class="text-xs font-mono" :class="activeRegion.risk > 50 ? 'text-red-400' : 'text-green-400'">{{ activeRegion.risk }}%</span>
                      </div>
                      <input v-model.number="activeRegion.risk" type="range" min="0" max="100" class="w-full h-1 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-blue-500" />
                   </div>

                   <div class="p-4 bg-black/40 rounded-2xl border border-white/5 space-y-3">
                      <div class="flex justify-between items-center">
                         <span class="text-[10px] text-gray-600 uppercase font-black">Price Trend (1h)</span>
                         <div class="w-32 h-8">
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-full">
                               <path :d="generateSparkline(activeRegion)" fill="none" stroke="#60a5fa" stroke-width="2" />
                            </svg>
                         </div>
                      </div>
                      <div class="flex justify-between items-center">
                         <span class="text-[10px] text-gray-600 uppercase font-black">PUE Efficiency</span>
                         <span class="text-xs font-mono text-white">{{ (1 + (activeRegion.risk / 100) * 0.5).toFixed(2) }}</span>
                      </div>
                      <div class="flex justify-between items-center">
                         <span class="text-[10px] text-gray-600 uppercase font-black">Market Sentiment</span>
                         <span class="text-xs font-mono text-white">{{ activeRegion.risk < 30 ? 'Aggressive Growth' : 'Conservative' }}</span>
                      </div>
                   </div>

                   <div class="pt-6 border-t border-white/5">
                      <button @click="saveRegions" class="btn btn-primary w-full shadow-lg shadow-blue-600/20 py-3 uppercase text-[10px] tracking-[0.2em] font-black">
                         Deploy Configuration
                      </button>
                   </div>
                </div>
             </div>
             
             <div v-else class="admin-card text-center py-24 bg-black/20 border-dashed border-2 flex flex-col items-center justify-center">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mb-6 animate-pulse border border-white/5 text-4xl">📍</div>
                <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.2em] max-w-[200px] leading-relaxed">
                   Select a tactical Node on the global matrix to reconfigure infrastructure parameters.
                </p>
             </div>
          </transition>

          <!-- GLOBAL METRICS CARD -->
          <div class="admin-card bg-gradient-to-br from-black/60 to-transparent">
             <h3 class="font-black text-xs text-gray-500 mb-6 uppercase tracking-[0.2em] flex items-center gap-2">
                <span class="w-2 h-2 bg-blue-500 shadow-[0_0_8px_#3b82f6]"></span> Regional Market Feed
             </h3>
             <div class="space-y-4">
                <div v-for="i in 3" :key="i" class="flex justify-between items-center p-3 rounded-xl hover:bg-white/5 transition-colors group/item">
                   <div class="flex items-center gap-3">
                      <div class="w-2 h-2 rounded-full bg-gray-800 group-hover/item:bg-blue-500 transition-colors"></div>
                      <div>
                         <p class="text-[10px] text-white font-bold uppercase tracking-tight">System Tick #42{{i}}</p>
                         <p class="text-[8px] text-gray-600 uppercase font-black">Anomaly Detected in Hall B</p>
                      </div>
                   </div>
                   <span class="text-[9px] font-mono text-blue-500">LIVE</span>
                </div>
             </div>
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading } = inject('adminContext');

const regions = ref([]);
const marketData = ref({ regional_prices: {}, global_factor: 1.0 });
const activeRegionIndex = ref(null);
const isDragging = ref(false);

const activeRegion = computed(() => {
   if (activeRegionIndex.value === null) return null;
   return regions.value[activeRegionIndex.value];
});

/* Get Live Price if available, else fallback */
const getLivePrice = (key) => {
    if (marketData.value.regional_prices && marketData.value.regional_prices[key] !== undefined) {
        return Number(marketData.value.regional_prices[key]);
    }
    return null;
};

const getNodeColor = (r, idx) => {
   // Use live price volatility to color? Or risk? stick to risk for now.
   if (r.risk > 70) return '#ef4444';
   if (r.risk > 35) return '#f59e0b';
   
   // Check if live price is significantly higher than base
   const live = getLivePrice(r.key || idx);
   if (live && r.energyPrice && live > r.energyPrice * 1.5) return '#ef4444'; // High surge

   return '#3b82f6';
};

const generateSparkline = (region) => {
   const key = region.key || activeRegionIndex.value;
   const history = marketData.value.regional_history?.[key] || [];
   if (history.length < 2) return "M 0 15 L 100 15";

   // Normalize
   const prices = history.map(h => Number(h.price));
   const min = Math.min(...prices) * 0.95;
   const max = Math.max(...prices) * 1.05;
   const range = max - min || 0.01;

   const points = prices.map((p, i) => {
       const x = (i / (prices.length - 1)) * 100;
       const y = 30 - ((p - min) / range) * 30; // 30 is height
       return `${x} ${y}`;
   });

   return `M ${points.join(' L ')}`;
};

const getConnectionPath = () => {
   if (regions.value.length < 2) return '';
   return regions.value.map((r, i) => `${i === 0 ? 'M' : 'L'} ${r.x} ${r.y}`).join(' ');
};

const fetchRegions = async () => {
   try {
       const [resConfig, resMarket] = await Promise.all([
           api.get('/admin/configs'),
           api.get('/admin/energy/market')
       ]);

       // Process Configs
       if (resConfig.success) {
           const db = resConfig.configs.regions || [];
           
           let list = [];
           if (Array.isArray(db)) {
               list = db.map((r, i) => ({ ...r, key: i }));
           } else {
               list = Object.entries(db).map(([k, v]) => ({ ...v, key: k }));
           }
           
           if (list.length === 0) {
               regions.value = [
                   { key: 'north', name: 'Northern Sector', energyPrice: 0.12, latency: 20, risk: 10, x: 200, y: 150 },
                   { key: 'silicon', name: 'Silicon Valley G-Node', energyPrice: 0.28, latency: 12, risk: 5, x: 450, y: 300 },
                   { key: 'seoul', name: 'Seoul APAC Hub', energyPrice: 0.14, latency: 85, risk: 42, x: 750, y: 220 }
               ];
           } else {
               regions.value = list.map(r => ({
                   ...r,
                   x: r.x || Math.random() * 800 + 100,
                   y: r.y || Math.random() * 400 + 100
               }));
           }
       }

       // Process Market Data
       if (resMarket.success) {
           marketData.value = resMarket.market;
       }

   } catch (e) { addToast('Network Error: Sync Failed', 'error'); }
};

const saveRegions = async () => {
   setGlobalLoading(true);
   try {
       await api.post('/admin/configs/update', {
           key: 'regions',
           value: regions.value,
           comment: 'Revised Geospatial Infrastructure & Energy Curves.'
       });
       addToast('Matrix Synchronized Successfully', 'success');
   } catch (e) { addToast(e.message, 'error'); }
   finally { setGlobalLoading(false); }
};

// DRAG AND DROP SIMULATION FOR NODE POSITIONS
const onMouseDown = (e, idx) => {
   activeRegionIndex.value = idx;
   isDragging.value = true;
};

const onMouseMove = (e) => {
   if (!isDragging.value || activeRegionIndex.value === null) return;
   const svg = e.currentTarget;
   const CTM = svg.getScreenCTM();
   const x = (e.clientX - CTM.e) / CTM.a;
   const y = (e.clientY - CTM.f) / CTM.d;
   
   regions.value[activeRegionIndex.value].x = Math.round(x);
   regions.value[activeRegionIndex.value].y = Math.round(y);
};

const onMouseUp = () => {
   isDragging.value = false;
};

const addNewRegion = () => {
   const newR = {
      name: 'NEW SECTOR ' + (regions.value.length + 1),
      energyPrice: 0.15,
      latency: 50,
      risk: 20,
      x: 500,
      y: 300
   };
   regions.value.push(newR);
   activeRegionIndex.value = regions.value.length - 1;
};

const deleteRegion = () => {
    if (!confirm('Permanent De-orbit: Destroy this infrastructure node?')) return;
    regions.value.splice(activeRegionIndex.value, 1);
    activeRegionIndex.value = null;
    saveRegions();
};

onMounted(fetchRegions);
</script>

<style scoped>
.region-node { cursor: grab; }
.region-node.active { cursor: grabbing; }
.region-node:hover circle.node-pulse { filter: brightness(1.5); }

@keyframes circle-pulse {
  0% { r: 8; opacity: 1; }
  50% { r: 11; opacity: 0.8; }
  100% { r: 8; opacity: 1; }
}

.node-pulse { animation: circle-pulse 3s infinite ease-in-out; }

.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-slide-enter-from { opacity: 0; transform: translateX(20px); }
.fade-slide-leave-to { opacity: 0; transform: translateX(-20px); }
</style>
