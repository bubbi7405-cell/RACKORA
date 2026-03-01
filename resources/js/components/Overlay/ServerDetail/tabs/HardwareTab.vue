<template>
    <div class="tab-content hardware-tab provision-lab">
        <div v-if="server.status !== 'offline'" class="v3-info-box danger" style="margin-bottom: 25px;">
             <label>HW_LOCK_ACTIVE</label>
             <p>Der Server-Knoten befindet sich im geschützten Modus. Schalten Sie die Einheit OFFLINE, um Hardware-Modifikationen am Board vorzunehmen.</p>
             <div class="v3-actions" style="margin-top: 15px;">
                 <button class="btn-danger-v3" @click="$emit('power-toggle')">NOT_AUS_EINLEITEN</button>
             </div>
        </div>
        
        <div v-if="components.find(c => c.type === 'motherboard')" class="hw-layout-v3">
            <!-- Motherboard Info -->
            <div class="v3-info-box">
                <label>MOTHERBOARD_TOPOLOGIE</label>
                <div class="v3-spec-card">
                    <HardwareIcon type="motherboard" size="md" />
                    <div class="v3-details">
                        <strong>{{ components.find(c => c.type === 'motherboard').name }}</strong>
                        <span>{{ components.find(c => c.type === 'motherboard').config.size_u }}U Enterprise-Chassis</span>
                    </div>
                </div>
            </div>

            <!-- CPU Section -->
            <div class="v3-info-box" style="margin-top: 20px;">
                <label>RECHENKERNE ({{ components.filter(c => c.type === 'cpu').length }} / {{ components.find(c => c.type === 'motherboard').config.cpu_slots }})</label>
                <div class="v3-hw-grid">
                    <div v-for="comp in components.filter(c => c.type === 'cpu')" :key="comp.id" class="v3-hw-slot active" :class="{ 'leased-slot': comp.isLeased }">
                        <HardwareIcon type="cpu" size="sm" />
                        <div class="slot-info">
                            <div class="n">
                                {{ comp.name }}
                                <span v-if="comp.isLeased" class="lease-tag">GELEAST</span>
                            </div>
                            <div class="v">{{ comp.config.cores }} Kerne @ {{ comp.config.frequency_ghz }}GHz</div>
                            <div v-if="comp.isLeased" class="lease-actions">
                                <span class="cost">${{ comp.leaseCostPerHour.toFixed(2) }}/h</span>
                                <button class="btn-buyout" @click="buyoutComponent(comp)">BUYOUT (${{ Math.round(comp.config.price * 0.75) }})</button>
                                <button class="btn-return" v-if="server.status === 'offline'" @click="returnComponent(comp)">RETOURE</button>
                            </div>
                        </div>
                        <button v-if="server.status === 'offline' && !comp.isLeased" class="slot-remove" @click="removeComponent(comp.id, 'cpu')">×</button>
                    </div>
                    <div v-for="i in Math.max(0, (components.find(c => c.type === 'motherboard').config.cpu_slots || 0) - components.filter(c => c.type === 'cpu').length)" :key="'empty-cpu-'+i" class="v3-hw-slot empty">
                        <div class="slot-placeholder">
                            <HardwareIcon type="cpu" size="sm" style="opacity: 0.1;" />
                            <span>SOCKEL_LEER</span>
                        </div>
                        <select v-if="server.status === 'offline'" @change="installComponent($event.target.value, 'cpu')" class="v3-select-sm">
                            <option value="" disabled selected>+ CPU_INSTALL</option>
                            <option v-for="item in inventory.filter(c => c.type === 'cpu')" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- RAM Section -->
            <div class="v3-info-box" style="margin-top: 20px;">
                <label>ARBEITSSPEICHER ({{ components.filter(c => c.type === 'ram').length }} / {{ components.find(c => c.type === 'motherboard').config.ram_slots }})</label>
                <div class="v3-hw-grid">
                    <div v-for="comp in components.filter(c => c.type === 'ram')" :key="comp.id" class="v3-hw-slot active" :class="{ 'leased-slot': comp.isLeased }">
                        <HardwareIcon type="ram" size="sm" />
                        <div class="slot-info">
                            <div class="n">
                                {{ comp.name }}
                                <span v-if="comp.isLeased" class="lease-tag">GELEAST</span>
                            </div>
                            <div class="v">{{ comp.config.size_gb }}GB {{ comp.config.type }}</div>
                            <div v-if="comp.isLeased" class="lease-actions">
                                <span class="cost">${{ comp.leaseCostPerHour.toFixed(2) }}/h</span>
                                <button class="btn-buyout" @click="buyoutComponent(comp)">BUYOUT (${{ Math.round(comp.config.price * 0.75) }})</button>
                                <button class="btn-return" v-if="server.status === 'offline'" @click="returnComponent(comp)">RETOURE</button>
                            </div>
                        </div>
                        <button v-if="server.status === 'offline' && !comp.isLeased" class="slot-remove" @click="removeComponent(comp.id, 'ram')">×</button>
                    </div>
                    <div v-for="i in Math.max(0, (components.find(c => c.type === 'motherboard').config.ram_slots || 0) - components.filter(c => c.type === 'ram').length)" :key="'empty-ram-'+i" class="v3-hw-slot empty">
                        <div class="slot-placeholder">
                            <HardwareIcon type="ram" size="sm" style="opacity: 0.1;" />
                            <span>SLOT_LEER</span>
                        </div>
                        <select v-if="server.status === 'offline'" @change="installComponent($event.target.value, 'ram')" class="v3-select-sm">
                            <option value="" disabled selected>+ RAM_INSTALL</option>
                            <option v-for="item in inventory.filter(c => c.type === 'ram')" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Storage Section -->
            <div class="v3-info-box" style="margin-top: 20px;">
                <label>DATENTRÄGER ({{ components.filter(c => c.type === 'storage').length }} / {{ components.find(c => c.type === 'motherboard').config.storage_slots }})</label>
                <div class="v3-hw-grid">
                    <div v-for="comp in components.filter(c => c.type === 'storage')" :key="comp.id" class="v3-hw-slot active" :class="{ 'leased-slot': comp.isLeased }">
                        <HardwareIcon type="storage" size="sm" />
                        <div class="slot-info">
                            <div class="n">
                                {{ comp.name }}
                                <span v-if="comp.isLeased" class="lease-tag">GELEAST</span>
                            </div>
                            <div class="v">{{ comp.config.size_tb }}TB {{ comp.config.type }}</div>
                            <div v-if="comp.isLeased" class="lease-actions">
                                <span class="cost">${{ comp.leaseCostPerHour.toFixed(2) }}/h</span>
                                <button class="btn-buyout" @click="buyoutComponent(comp)">BUYOUT (${{ Math.round(comp.config.price * 0.75) }})</button>
                                <button class="btn-return" v-if="server.status === 'offline'" @click="returnComponent(comp)">RETOURE</button>
                            </div>
                        </div>
                        <button v-if="server.status === 'offline' && !comp.isLeased" class="slot-remove" @click="removeComponent(comp.id, 'storage')">×</button>
                    </div>
                    <div v-for="i in Math.max(0, (components.find(c => c.type === 'motherboard').config.storage_slots || 0) - components.filter(c => c.type === 'storage').length)" :key="'empty-storage-'+i" class="v3-hw-slot empty">
                        <div class="slot-placeholder">
                            <HardwareIcon type="storage" size="sm" style="opacity: 0.1;" />
                            <span>BAY_LEER</span>
                        </div>
                        <select v-if="server.status === 'offline'" @change="installComponent($event.target.value, 'storage')" class="v3-select-sm">
                            <option value="" disabled selected>+ DISK_INSTALL</option>
                            <option v-for="item in inventory.filter(c => c.type === 'storage')" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="v3-prebuilt-config">
            <div class="v3-info-box warning">
                <label>IDENT_SYSTEM: VORKONFIGURIERT</label>
                <p>Dieses System nutzt ein integriertes Board-Layout. Upgrades wandeln die Einheit automatisch in eine CUSTOM-Spezifikation um.</p>
            </div>

            <div class="v3-hw-grid" style="margin-top: 25px;">
                <!-- CPU Swap -->
                <div class="v3-hw-slot active" :class="{ 'leased-slot': server.isLeased }">
                    <HardwareIcon type="cpu" size="sm" />
                    <div class="slot-info">
                        <div class="n">{{ server.specs?.cpuCores || 0 }} Kerne (FEST_VERBAUT) <span v-if="server.isLeased" class="lease-tag">LEASED_UNIT</span></div>
                        <div class="v">Werkseitige Standard-CPU</div>
                    </div>
                    <div class="slot-actions" v-if="server.status === 'offline'">
                        <select @change="swapComponent($event.target.value, 'cpu')" class="v3-select-sm highlight">
                            <option value="" disabled selected>↑ UPGRADE_CPU</option>
                            <option v-for="item in inventory.filter(c => c.type === 'cpu')" :key="item.id" :value="item.id">
                                {{ item.name }} ({{ item.config.cores }} Kerne)
                            </option>
                        </select>
                    </div>
                </div>

                 <!-- RAM Swap -->
                <div class="v3-hw-slot active" :class="{ 'leased-slot': server.isLeased }">
                    <HardwareIcon type="ram" size="sm" />
                    <div class="slot-info">
                        <div class="n">{{ server.specs?.ramGb || 0 }} GB (FEST_VERBAUT) <span v-if="server.isLeased" class="lease-tag">LEASED_UNIT</span></div>
                        <div class="v">Werkseitiger Standard-RAM</div>
                    </div>
                    <div class="slot-actions" v-if="server.status === 'offline'">
                        <select @change="swapComponent($event.target.value, 'ram')" class="v3-select-sm highlight">
                            <option value="" disabled selected>↑ UPGRADE_RAM</option>
                            <option v-for="item in inventory.filter(c => c.type === 'ram')" :key="item.id" :value="item.id">
                                {{ item.name }} ({{ item.config.size_gb }} GB)
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lease-tag {
    background: #a855f7;
    color: white;
    font-size: 10px;
    padding: 2px 4px;
    border-radius: 4px;
    margin-left: 8px;
    font-weight: bold;
}

.leased-slot {
    border-color: #a855f7 !important;
    background: rgba(168, 85, 247, 0.05) !important;
}

.lease-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 5px;
}

.lease-actions .cost {
    font-size: 11px;
    color: #a855f7;
    font-family: 'JetBrains Mono', monospace;
}

.btn-buyout, .btn-return {
    padding: 2px 8px;
    font-size: 10px;
    border-radius: 4px;
    border: 1px solid transparent;
    cursor: pointer;
    text-transform: uppercase;
    font-weight: bold;
}

.btn-buyout {
    background: #10b981;
    color: white;
}

.btn-buyout:hover {
    background: #059669;
}

.btn-return {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: #ef4444;
}

.btn-return:hover {
    background: #ef4444;
    color: white;
}

.v3-hw-slot.active {
    position: relative;
    padding-right: 30px; 
}
</style>

<script setup>
import { computed } from 'vue';
import HardwareIcon from '../../../UI/HardwareIcon.vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';
import { useInfrastructureStore } from '../../../../stores/infrastructure';

const props = defineProps({
    server: { type: Object, required: true },
    components: { type: Array, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['power-toggle', 'processing-start', 'processing-end', 'reload', 'close']);

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();

const inventory = computed(() => gameStore.hardware?.inventory || []);

const installComponent = async (componentId, slotType) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const res = await api.post(`/hardware/install/${props.server.id}`, { component_id: componentId, slot_type: slotType });
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } catch (e) {
        console.error(e);
    } finally {
        emit('processing-end');
    }
};

const swapComponent = async (componentId, slotType) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const res = await infraStore.swapComponent(props.server.id, componentId, 0); // index 0 for now
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const removeComponent = async (componentId, slotType) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const res = await api.post(`/hardware/remove/${props.server.id}`, { component_id: componentId, slot_type: slotType });
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const buyoutComponent = async (comp) => {
    if (props.processing) return;
    const price = Math.round(comp.config.price * 0.75);
    if (!confirm(`Möchten Sie '${comp.name}' wirklich für $${price.toLocaleString()} aus dem Leasingvertrag rauskaufen?`)) return;

    emit('processing-start');
    try {
        const res = await api.post(`/hardware/components/${comp.id}/buyout`);
        if (res.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const returnComponent = async (comp) => {
    if (props.processing) return;
    if (!confirm(`Möchten Sie '${comp.name}' wirklich an den Leasinggeber zurückgeben? Die Komponente wird sofort ausgebaut und der Vertrag beendet.`)) return;

    emit('processing-start');
    try {
        const res = await api.post(`/hardware/components/${comp.id}/return`);
        if (res.success) {
            if (comp.type === 'motherboard') {
                emit('close');
            } else {
                emit('reload');
            }
            gameStore.loadGameState();
        }
    } finally {
        emit('processing-end');
    }
};
</script>
