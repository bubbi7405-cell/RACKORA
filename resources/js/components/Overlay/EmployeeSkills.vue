<template>
    <div class="skills-panel">
        <div class="panel-header">
            <h4>{{ formatType(employee.type) }} Specialization</h4>
            <div class="points-badge">
                <span class="val">{{ employee.skill_points }}</span>
                <span class="label">SP Available</span>
            </div>
        </div>

        <div class="skill-tree-container">
            <div v-if="!skillTree || Object.keys(skillTree).length === 0" class="empty-tree">
                No specialization tree available for this role.
            </div>
            
            <div v-else class="tree-nodes">
                <div v-for="(perk, id) in skillTree" :key="id" 
                     class="perk-node" 
                     :class="getPerkStatus(id, perk)">
                    
                    <div class="node-connector" v-if="perk.level_req > 2"></div>
                    
                    <div class="perk-card" @click="selectPerk(id, perk)">
                        <div class="perk-icon">
                            <span v-if="isUnlocked(id)">✅</span>
                            <span v-else-if="canUnlock(id, perk)">🔓</span>
                            <span v-else>🔒</span>
                        </div>
                        <div class="perk-info">
                            <div class="perk-header">
                                <span class="perk-name">{{ perk.name }}</span>
                                <span class="perk-cost">{{ perk.cost }} SP</span>
                            </div>
                            <div class="perk-req">Req: Lvl {{ perk.level_req }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail/Action Panel -->
        <div class="perk-detail" v-if="selectedPerk">
            <h5 class="detail-title">{{ selectedPerk.name }}</h5>
            <p class="detail-desc">{{ selectedPerk.description }}</p>
            
            <div class="detail-meta">
                <div class="meta-item">
                    <span class="label">Level Req</span>
                    <span class="val">{{ selectedPerk.level_req }}</span>
                </div>
                <div class="meta-item">
                    <span class="label">Cost</span>
                    <span class="val">{{ selectedPerk.cost }} SP</span>
                </div>
                <div class="meta-item" v-if="selectedPerk.prerequisites?.length">
                    <span class="label">Prerequisites</span>
                    <span class="val">{{ selectedPerk.prerequisites.join(', ') }}</span>
                </div>
            </div>

            <button 
                class="unlock-btn" 
                :disabled="!canUnlock(selectedId, selectedPerk) || processing"
                @click="unlock(selectedId)"
                v-if="!isUnlocked(selectedId)"
            >
                <span v-if="processing">Unlocking...</span>
                <span v-else>Unlock Ability</span>
            </button>
            <div class="unlocked-badge" v-else>
                ABILITY ACQUIRED
            </div>
        </div>

        <!-- FEATURE 204: Respec Footer -->
        <div class="panel-footer" v-if="employee.perks?.length > 0">
            <button class="respec-btn" @click="handleRespec" :disabled="empStore.isLoading">
                🔄 Talente zurücksetzen
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    employee: { type: Object, required: true },
    skillTree: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['unlock']);

import { useEmployeesStore } from '../../stores/employees';
const empStore = useEmployeesStore();

const selectedId = ref(null);
const selectedPerk = ref(null);
const processing = ref(false);

const availableTypes = {
    sys_admin: 'System Administrator',
    support_agent: 'Support Agent',
    security_engineer: 'Security Engineer',
    compliance_officer: 'Compliance Officer',
    network_engineer: 'Network Engineer',
    manager: 'IT Manager'
};

function formatType(type) {
    return availableTypes[type] || type;
}

function selectPerk(id, perk) {
    selectedId.value = id;
    selectedPerk.value = perk;
}

function isUnlocked(id) {
    return (props.employee.perks || []).includes(id);
}

function canUnlock(id, perk) {
    if (isUnlocked(id)) return false;
    if (props.employee.level < perk.level_req) return false;
    if (props.employee.skill_points < perk.cost) return false;
    
    // Check prerequisites
    if (perk.prerequisites && perk.prerequisites.length > 0) {
        return perk.prerequisites.every(preId => isUnlocked(preId));
    }

    return true;
}

function getPerkStatus(id, perk) {
    if (isUnlocked(id)) return 'status-unlocked';
    if (canUnlock(id, perk)) return 'status-available';
    return 'status-locked';
}

function unlock(id) {
    if (!canUnlock(id, selectedPerk.value)) return;
    processing.value = true;
    emit('unlock', { 
        employeeId: props.employee.id, 
        perkId: id 
    }, () => {
        processing.value = false;
    });
}

function handleRespec() {
    empStore.respec(props.employee.id);
}
</script>

<style scoped>
.skills-panel {
    background: rgba(0,0,0,0.3);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    height: 100%;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 15px;
}

.panel-header h4 { margin: 0; color: #fff; font-size: 1rem; }

.points-badge {
    background: rgba(88, 166, 255, 0.2);
    border: 1px solid #58a6ff;
    padding: 4px 12px;
    border-radius: 20px;
    display: flex; gap: 8px; align-items: center;
}
.points-badge .val { color: #fff; font-weight: 800; }
.points-badge .label { color: #58a6ff; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; }

.skill-tree-container {
    flex: 1;
    overflow-y: auto;
    min-height: 200px;
}

.tree-nodes {
    display: flex;
    flex-direction: column;
    gap: 15px;
    position: relative;
}

.perk-node {
    display: flex;
    position: relative;
}

.perk-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    padding: 12px;
    border-radius: 8px;
    width: 100%;
    cursor: pointer;
    transition: all 0.2s;
}

.perk-card:hover { background: rgba(255,255,255,0.1); }

.status-unlocked .perk-card { border-color: #3fb950; background: rgba(63, 185, 80, 0.1); }
.status-available .perk-card { border-color: #e3b341; box-shadow: 0 0 10px rgba(227, 179, 65, 0.1); }
.status-locked .perk-card { opacity: 0.5; filter: grayscale(1); }

.perk-icon { font-size: 1.2rem; min-width: 30px; text-align: center; }
.perk-info { flex: 1; }

.perk-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.perk-name { color: #fff; font-weight: 700; font-size: 0.9rem; }
.perk-cost { color: #e3b341; font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }
.perk-req { font-size: 0.7rem; color: #8b949e; }

.perk-detail {
    background: rgba(0,0,0,0.4);
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 20px;
}

.detail-title { color: #fff; margin: 0 0 8px 0; font-size: 1.1rem; }
.detail-desc { color: #8b949e; font-size: 0.9rem; line-height: 1.5; margin-bottom: 15px; }

.detail-meta {
    display: flex; gap: 20px; margin-bottom: 20px;
}
.meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-item .label { font-size: 0.65rem; color: #8b949e; text-transform: uppercase; }
.meta-item .val { color: #fff; font-family: 'JetBrains Mono', monospace; }

.unlock-btn {
    width: 100%;
    padding: 12px;
    background: #238636;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.unlock-btn:hover:not(:disabled) { background: #2ea043; }
.unlock-btn:disabled { background: #30363d; opacity: 0.5; cursor: not-allowed; }

.unlocked-badge {
    text-align: center;
    padding: 12px;
    background: rgba(63, 185, 80, 0.1);
    border: 1px solid #3fb950;
    color: #3fb950;
    border-radius: 6px;
    font-weight: 800;
    letter-spacing: 1px;
}

.panel-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 15px;
    display: flex;
    justify-content: center;
}

.respec-btn {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.4);
    color: #f85149;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.respec-btn:hover:not(:disabled) {
    background: rgba(248, 81, 73, 0.2);
    border-color: #f85149;
}

.respec-btn:disabled {
    opacity: 0.5;
    cursor: wait;
}
</style>
