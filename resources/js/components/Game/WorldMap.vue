<template>
    <div class="world-map-container">
        <div class="map-wrapper" ref="mapWrapper">
            <!-- SVG Map Layer -->
            <svg viewBox="0 0 1000 500" class="world-svg">
                <!-- Background Map -->
                <path d="M150,150 L200,100 L300,120 L350,200 L300,300 L200,350 L100,250 Z" class="land" /> <!-- North America Simpl -->
                <path d="M450,100 L550,80 L650,120 L600,200 L500,250 L450,180 Z" class="land" /> <!-- Europe Simpl -->
                <path d="M650,100 L850,80 L950,150 L900,300 L700,350 L650,250 Z" class="land" /> <!-- Asia Simpl -->
                <path d="M250,350 L350,380 L400,500 L300,550 L200,450 Z" class="land" /> <!-- SA Simpl -->
                <path d="M450,250 L550,260 L600,450 L500,500 L400,400 Z" class="land" /> <!-- Africa Simpl -->
                <path d="M750,400 L850,420 L900,500 L800,520 Z" class="land" /> <!-- Australia Simpl -->

                <!-- Connection Lines (Network Fiber) -->
                <g v-if="showFiber">
                    <line 
                        v-for="link in fiberLinks" 
                        :key="link.id" 
                        :x1="link.from.x" :y1="link.from.y" 
                        :x2="link.to.x" :y2="link.to.y" 
                        class="fiber-line"
                    />
                    <!-- Traffic Animations -->
                    <circle v-for="link in fiberLinks" :key="'packet-' + link.id" r="2" class="traffic-packet">
                        <animateMotion 
                            :path="`M${link.from.x},${link.from.y} L${link.to.x},${link.to.y}`" 
                            :dur="link.speed + 's'" 
                            repeatCount="indefinite" 
                        />
                    </circle>
                </g>

                <!-- Region Markers -->
                <g v-for="(coord, key) in regionCoords" :key="key">
                    <circle 
                        :cx="coord.x" :cy="coord.y" r="8" 
                        class="region-marker" 
                        :class="{ 'is-active': hasDatacenter(key) }"
                        @click="selectRegion(key)"
                    />
                    <text :x="coord.x" :y="coord.y + 20" class="region-label">{{ getRegionName(key) }}</text>
                </g>

                <!-- Datacenter Pointers -->
                <g v-for="room in playerRooms" :key="room.id">
                    <g transform="translate(-10, -25)">
                        <path 
                            :d="`M ${getCoord(room.region).x} ${getCoord(room.region).y} l 10 -25 l 10 25 z`" 
                            class="dc-pointer" 
                            :class="room.specialization || 'default'"
                        />
                        <text :x="getCoord(room.region).x + 15" :y="getCoord(room.region).y - 15" class="dc-label">{{ room.name }}</text>
                    </g>
                </g>
            </svg>

            <!-- Region Overlay Tooltip -->
            <div v-if="hoveredRegion" class="region-tooltip" :style="tooltipStyle">
                <div class="tooltip-header">
                    <span class="flag">{{ hoveredRegionData.flag }}</span>
                    <span class="name">{{ hoveredRegionData.name }}</span>
                </div>
                <div class="tooltip-body">
                    <div class="stat"><span>DEMAND:</span> <span class="val">{{ getDemand(hoveredRegionKey) }}%</span></div>
                    <div class="stat"><span>LATENCY:</span> <span class="val">{{ hoveredRegionData.latencyMs }}ms</span></div>
                    <div class="stat"><span>ENERGY:</span> <span class="val">${{ hoveredRegionData.base_power_cost }}/kWh</span></div>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="map-sidebar">
            <div class="v2-title l2-priority">GLOBAL_COMMAND_SUMMARY</div>
            
            <div class="global-stats-grid">
                <div class="g-stat">
                    <span class="label">LIVE_CONNS</span>
                    <span class="val">{{ fiberLinks.length }}</span>
                </div>
                <div class="g-stat">
                    <span class="label">ACTIVE_NODES</span>
                    <span class="val">{{ playerRooms.length }}</span>
                </div>
                <div class="g-stat">
                    <span class="label">TRAFFIC_LOAD</span>
                    <span class="val">{{ totalTraffic }} Gbps</span>
                </div>
            </div>

            <div class="market-shifts">
                <div class="v2-title small">MARKET_SHIFTS</div>
                <div class="shift-item" v-for="shift in activeShifts" :key="shift.id">
                    <span class="shift-icon">{{ shift.icon }}</span>
                    <div class="shift-details">
                        <span class="shift-label">{{ shift.label }}</span>
                        <span class="shift-impact">{{ shift.impact }}</span>
                    </div>
                </div>
            </div>

            <div class="region-list">
                <div class="v2-title small">REGIONAL_DEMAND</div>
                <div 
                    v-for="(data, key) in gameStore.regions" 
                    :key="key" 
                    class="region-item" 
                    @mouseenter="hoverRegion(key)"
                    @mouseleave="hoverRegion(null)"
                >
                    <span class="r-flag">{{ data.flag }}</span>
                    <span class="r-name">{{ data.name }}</span>
                    <span class="r-load" :style="{ color: getLoadColor(key) }">{{ getDemand(key) }}%</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { useGameStore } from '../../stores/game';
import { useInfrastructureStore } from '../../stores/infrastructure';
import { useEconomyStore } from '../../stores/economy';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();
const economyStore = useEconomyStore();

const showFiber = ref(true);
const hoveredRegionKey = ref(null);

const activeShifts = [
    { id: 1, icon: '📈', label: 'AI_SURGE', impact: 'GPU_REVENUE +20%' },
    { id: 2, icon: '🌩️', label: 'POWER_VOLATILITY', impact: 'ENERGY_COST +15%' },
    { id: 3, icon: '🛡️', label: 'GDPR_STRICT', impact: 'EU_PROFIT -10%' },
];

const regionCoords = {
    'us_east': { x: 280, y: 160 },
    'us_west': { x: 180, y: 170 },
    'eu_west': { x: 480, y: 120 },
    'eu_central': { x: 520, y: 130 },
    'eu_east': { x: 580, y: 110 },
    'asia_east': { x: 850, y: 160 },
    'asia_south': { x: 750, y: 220 },
    'sa_east': { x: 320, y: 420 },
    'africa_south': { x: 530, y: 450 },
};

const fiberLinks = computed(() => {
    const links = [];
    const activeRegions = playerRooms.value.map(r => r.region);
    
    // Create links between active regions
    for (let i = 0; i < activeRegions.length; i++) {
        for (let j = i + 1; j < activeRegions.length; j++) {
            const regA = activeRegions[i];
            const regB = activeRegions[j];
            if (regionCoords[regA] && regionCoords[regB]) {
                links.push({
                    id: `${regA}-${regB}`,
                    from: regionCoords[regA],
                    to: regionCoords[regB],
                    speed: Math.random() * 2 + 1
                });
            }
        }
    }
    return links;
});

const playerRooms = computed(() => Object.values(infraStore.rooms));

const getCoord = (region) => regionCoords[region] || { x: 0, y: 0 };

const hasDatacenter = (region) => playerRooms.value.some(r => r.region === region);

const getRegionName = (key) => gameStore.regions?.[key]?.name || key.toUpperCase();

const selectRegion = (key) => {
    // Logic to focus region in analytics or deploy
};

const hoveredRegionData = computed(() => gameStore.regions?.[hoveredRegionKey.value] || {});

const getDemand = (key) => {
    // Use the new globalMarket demand if available
    const market = economyStore.globalMarket;
    if (market && market.regionalDemand?.[key]) {
        return market.regionalDemand[key].total || 50;
    }
    return Math.floor(Math.random() * 40 + 30); // Fallback
};

const totalTraffic = computed(() => (gameStore.network?.traffic?.totalGbps || 0).toFixed(1));

const getLoadColor = (key) => {
    const d = getDemand(key);
    if (d > 80) return '#f85149';
    if (d > 50) return '#d29922';
    return '#3fb950';
};

const hoverRegion = (key) => {
    hoveredRegionKey.value = key;
};

const tooltipStyle = computed(() => {
    if (!hoveredRegionKey.value) return {};
    const coord = regionCoords[hoveredRegionKey.value];
    return {
        left: (coord.x / 10) + '%',
        top: (coord.y / 5) + '%'
    };
});
</script>

<style scoped>
.world-map-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    height: 100%;
    background: #010409;
    overflow: hidden;
}

.map-wrapper {
    position: relative;
    padding: 20px;
}

.world-svg {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 0 20px rgba(58, 134, 255, 0.1));
}

.land {
    fill: #0d1117;
    stroke: #30363d;
    stroke-width: 2;
    transition: fill 0.3s;
}

.land:hover {
    fill: #161b22;
}

.fiber-line {
    stroke: rgba(58, 134, 255, 0.3);
    stroke-width: 1;
    stroke-dasharray: 4;
}

.traffic-packet {
    fill: #58a6ff;
    filter: blur(1px);
}

.region-marker {
    fill: #30363d;
    stroke: #8b949e;
    stroke-width: 2;
    cursor: pointer;
    transition: all 0.3s;
}

.region-marker.is-active {
    fill: #238636;
    stroke: #3fb950;
    box-shadow: 0 0 10px #3fb950;
}

.region-marker:hover {
    r: 10;
    stroke: #fff;
}

.region-label {
    fill: #8b949e;
    font-size: 10px;
    font-weight: 800;
    text-anchor: middle;
    pointer-events: none;
    text-transform: uppercase;
}

.dc-pointer {
    fill: #58a6ff;
    stroke: #fff;
    stroke-width: 1;
    filter: drop-shadow(0 0 5px rgba(88, 166, 255, 0.5));
}

.dc-pointer.ai { fill: #bc8cff; }
.dc-pointer.storage { fill: #79c0ff; }
.dc-pointer.streaming { fill: #ffa657; }

.dc-label {
    fill: #fff;
    font-size: 9px;
    font-weight: 800;
}

.map-sidebar {
    background: #0d1117;
    border-left: 1px solid #30363d;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.global-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.g-stat {
    background: rgba(255,255,255,0.02);
    padding: 10px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.05);
    display: flex;
    flex-direction: column;
}

.g-stat .label { font-size: 0.6rem; color: #8b949e; font-weight: 800; }
.g-stat .val { font-size: 1.1rem; font-weight: 900; color: #fff; }

.market-shifts {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: rgba(0,0,0,0.2);
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.03);
}

.shift-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.75rem;
}

.shift-icon { font-size: 1.2rem; }
.shift-details { display: flex; flex-direction: column; }
.shift-label { font-weight: 800; color: #fff; }
.shift-impact { font-size: 0.6rem; color: var(--color-success); font-family: monospace; }

.region-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow-y: auto;
}

.region-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    background: rgba(255,255,255,0.02);
    border-radius: 6px;
    font-size: 0.8rem;
}

.region-item:hover {
    background: rgba(255,255,255,0.05);
}

.r-load { margin-left: auto; font-family: monospace; font-weight: 800; }

.region-tooltip {
    position: absolute;
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 12px;
    z-index: 100;
    pointer-events: none;
    transform: translate(20px, 20px);
    width: 200px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.tooltip-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    padding-bottom: 5px;
}

.tooltip-body {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.stat {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #8b949e;
}

.stat .val { color: #fff; font-weight: 800; }
</style>
