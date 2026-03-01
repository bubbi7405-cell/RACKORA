<template>
    <div :class="{ 'overlay-backdrop': !inline }" @click.self="$emit('close')">
        <div class="employees-overlay" :class="{ 'glass-panel animation-fade-in': !inline, 'inline-panel': inline }">
            <!-- Header section -->
            <div class="overlay-header" v-if="!inline">
                <div class="header-main">
                    <div class="header-icon">👥</div>
                    <div class="header-text">
                        <h2>Personnel Management</h2>
                        <p class="subtitle">Global Staff Operations & Human Resources</p>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="payroll-badge" v-if="totalSalary > 0">
                        <span class="label">Total Payroll</span>
                        <span class="value">-${{ totalSalary.toFixed(2) }}/hr</span>
                    </div>
                    <button class="buffs-btn" @click="showBuffsModal = true">
                        <span>✨</span> Global Buffs
                    </button>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </div>
            </div>

            <div class="overlay-content">
                <!-- LEFT SIDE: RECRUITMENT -->
                <div class="sidebar">
                    <div class="sidebar-header">
                        <h3>Available Talent</h3>
                        <p>Expand your operational capacity</p>
                    </div>
                    
                    <div class="hiring-list">
                        <div v-for="(type, key) in availableTypes" :key="key" class="hiring-card">
                            <div class="hiring-card-inner">
                                <div class="role-preview">
                                    <div class="role-icon-box">
                                        <span v-if="key === 'sys_admin'">💻</span>
                                        <span v-else-if="key === 'support_agent'">🎧</span>
                                        <span v-else-if="key === 'security_engineer'">🛡️</span>
                                        <span v-else-if="key === 'compliance_officer'">📋</span>
                                        <span v-else-if="key === 'network_engineer'">📡</span>
                                        <span v-else-if="key === 'manager'">👔</span>
                                        <span v-else>👥</span>
                                    </div>
                                    <div class="role-info">
                                        <h4>{{ type.name }}</h4>
                                        <div class="tag">{{ key.replace('_', ' ') }}</div>
                                    </div>
                                </div>
                                <p class="role-desc">{{ type.description }}</p>
                                
                                <div class="hiring-stats">
                                    <div class="stat-item">
                                        <span class="label">Hiring Cost</span>
                                        <div class="cost-container">
                                            <span v-if="activeBonuses.hiring_cost_reduction > 0" class="original-cost">${{ type.hiring_cost }}</span>
                                            <span class="val text-primary">${{ calculateHiringCost(type.hiring_cost) }}</span>
                                        </div>
                                    </div>
                                    <div class="stat-item">
                                        <span class="label">Base Salary</span>
                                        <span class="val text-warning">${{ type.base_salary }}/hr</span>
                                    </div>
                                </div>

                                <button 
                                    class="hire-button" 
                                    @click="hire(key)"
                                    :disabled="loading || economy.balance < calculateHiringCost(type.hiring_cost)"
                                >
                                    <span v-if="loading">Processing...</span>
                                    <span v-else>Hire Professional</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: ACTIVE STAFF -->
                <div class="main-stats">
                    <div class="section-top">
                        <h3>Active Fleet ({{ employees.length }})</h3>
                        <div class="status-summary">
                            <div class="status-pill healthy">
                                <span class="dot"></span> {{ activeStaffCount }} Active
                            </div>
                        </div>
                    </div>

                    <div v-if="employees.length === 0" class="empty-state">
                        <div class="empty-icon">📂</div>
                        <h4>No Personnel Active</h4>
                        <p>Recruit specialists to automate your infrastructure and reduce management pressure.</p>
                    </div>

                    <div v-else class="staff-grid">
                        <div v-for="emp in employees" :key="emp.id" class="staff-card" :class="{ 'stressed': emp.stress > 70, 'burnout': emp.stress >= 98, 'on-sabbatical': emp.sabbatical_until && new Date(emp.sabbatical_until) > new Date() }">
                            <div class="staff-card-header">
                                <div class="staff-avatar-box">
                                    <div class="staff-avatar">{{ emp.name.charAt(0) }}</div>
                                    <div class="status-indicator" :class="getHealthStatus(emp)"></div>
                                </div>
                                <div class="staff-identity">
                                    <div class="name-row">
                                        <span class="name">{{ emp.name }}</span>
                                        <span class="lvl">Lvl {{ emp.level }}</span>
                                    </div>
                                    <div class="role">{{ formatType(emp.type) }}</div>
                                    <div v-if="emp.specialization" class="spec-badge">
                                        <span class="spec-icon">⭐</span> {{ formatSpec(emp.type, emp.specialization) }}
                                    </div>
                                </div>
                                <button class="fire-btn" @click="fire(emp.id)" title="Terminate Contract">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>

                            <div class="staff-telemetry">
                                <div class="telemetry-item">
                                    <div class="telemetery-label">
                                        <span>MORALE / STRESS</span>
                                        <span :class="getStatusColor(emp.stress, true)">{{ Math.round(emp.stress) }}%</span>
                                    </div>
                                    <div class="telemetry-bar">
                                        <div class="bar-fill stress" :style="{ width: emp.stress + '%', backgroundColor: getStatusColor(emp.stress, true, true) }"></div>
                                    </div>
                                </div>
                                <div class="telemetry-item">
                                    <div class="telemetery-label">
                                        <span>ENERGY RESERVE</span>
                                        <span :class="getStatusColor(emp.energy)">{{ Math.round(emp.energy) }}%</span>
                                    </div>
                                    <div class="telemetry-bar">
                                        <div class="bar-fill energy" :style="{ width: emp.energy + '%', backgroundColor: getStatusColor(emp.energy, false, true) }"></div>
                                    </div>
                                </div>
                                <div class="telemetry-item">
                                    <div class="telemetery-label" :title="emp.loyalty < 30 ? 'Risk of resignation!' : 'Loyal employee'">
                                        <span>LOYALTY</span>
                                        <span :style="{ color: emp.loyalty < 30 ? '#ef4444' : '#a855f7' }">{{ Math.round(emp.loyalty ?? 50) }}%</span>
                                    </div>
                                    <div class="telemetry-bar">
                                        <div class="bar-fill loyalty" :style="{ width: (emp.loyalty ?? 50) + '%', backgroundColor: emp.loyalty < 30 ? '#ef4444' : '#a855f7' }"></div>
                                    </div>
                                </div>
                                <div class="telemetry-item">
                                    <div class="telemetery-label">
                                        <span>XP PROGRESS (Lvl {{ emp.level }})</span>
                                        <span class="text-primary">{{ emp.xp }} / {{ emp.level * 500 }}</span>
                                    </div>
                                    <div class="telemetry-bar">
                                        <div class="bar-fill xp" :style="{ width: Math.min(100, (emp.xp / (emp.level * 500)) * 100) + '%', backgroundColor: '#58a6ff' }"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Task Badge -->
                            <div class="task-viewer">
                                <div class="task-label">Current Operation:</div>
                                <div class="task-status" :class="{ 'working': emp.task_progress > 0 }">
                                    <div class="task-icon" v-if="emp.energy < 15">💤</div>
                                    <div class="task-icon" v-else-if="emp.stress > 95">🧨</div>
                                    <div class="task-icon" v-else-if="emp.type === 'sys_admin'">🛠️</div>
                                    <div class="task-icon" v-else-if="emp.type === 'support_agent'">📞</div>
                                    <div class="task-icon" v-else-if="emp.type === 'security_engineer'">🛡️</div>
                                    <div class="task-icon" v-else-if="emp.type === 'compliance_officer'">📋</div>
                                    <div class="task-icon" v-else-if="emp.type === 'network_engineer'">📡</div>
                                    <div class="task-icon" v-else-if="emp.type === 'manager'">👔</div>
                                    <div class="task-icon" v-else>👥</div>
                                    <div class="task-name">{{ emp.current_task || 'Idle / Standby' }}</div>
                                </div>
                                <div class="task-progress" v-if="emp.task_progress > 0">
                                    <div class="progress-track">
                                        <div class="progress-fill" :style="{ width: emp.task_progress + '%' }"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="staff-footer">
                                <div class="f-item">
                                    <span class="f-label">Efficiency</span>
                                    <span class="f-val">{{ (emp.efficiency * 100).toFixed(0) }}%</span>
                                </div>
                                <div class="footer-actions">
                                    <button 
                                        v-if="emp.level >= 5 && emp.stress >= 60 && (!emp.sabbatical_until || new Date(emp.sabbatical_until) <= new Date())"
                                        class="sabbatical-btn" 
                                        @click="handleSabbatical(emp.id)"
                                        :disabled="loading"
                                        title="Send on paid sabbatical to prevent burnout"
                                    >
                                        🏖️ Sabbatical
                                    </button>
                                    <span 
                                        v-else-if="emp.sabbatical_until && new Date(emp.sabbatical_until) > new Date()"
                                        class="sabbatical-badge"
                                    >
                                        🏖️ On Leave
                                    </span>
                                    <button class="skills-btn" @click="selectedEmployee = emp">
                                        <span class="icon">⚡</span> Skills
                                        <span class="notification-dot" v-if="emp.skill_points > 0"></span>
                                    </button>
                                </div>
                                <div class="f-item">
                                    <span class="f-label">Hourly</span>
                                    <span class="f-val text-muted">${{ emp.salary }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Global Buffs Modal -->
        <div v-if="showBuffsModal" class="skills-modal-backdrop" @click.self="showBuffsModal = false">
            <div class="skills-modal-content animation-fade-in buffs-modal">
                <div class="skills-header">
                    <h3>Active Global Buffs</h3>
                    <button class="close-sub" @click="showBuffsModal = false">&times;</button>
                </div>
                <div class="buffs-list">
                    <div v-if="Object.keys(activeBonuses).length === 0" class="empty-buffs">
                        <p>No active buffs. Hire specialists and unlock perks to gain passive bonuses!</p>
                    </div>
                    <div v-else class="buff-grid">
                        <div v-for="(value, key) in activeBonuses" :key="key" class="buff-item">
                            <div class="buff-icon" :title="key">✨</div>
                            <div class="buff-details">
                                <span class="buff-name">{{ formatBuffName(key) }}</span>
                                <span class="buff-value text-success" v-if="typeof value === 'boolean'">Active</span>
                                <span class="buff-value text-success" v-else-if="Array.isArray(value)">
                                    {{ value.map(v => v.replace(/_/g, ' ').toUpperCase()).join(', ') }}
                                </span>
                                <span class="buff-value text-success" v-else-if="shouldFormatPercentage(key)">
                                    {{ (value > 0 ? '+' : '') + Math.round(value * 100) }}%
                                </span>
                                <span class="buff-value text-success" v-else>
                                    {{ value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skills Modal Overlay -->
        <div v-if="selectedEmployee" class="skills-modal-backdrop" @click.self="selectedEmployee = null">
            <div class="skills-modal-content animation-fade-in">
                <div class="skills-header">
                    <h3>{{ selectedEmployee.name }} - Skills</h3>
                    <button class="close-sub" @click="selectedEmployee = null">&times;</button>
                </div>
                <EmployeeSkills 
                    :employee="selectedEmployee"
                    :skillTree="skillTrees[selectedEmployee.type]"
                    @unlock="handleUnlock"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import { useEmployeesStore } from '../../stores/employees';
import EmployeeSkills from './EmployeeSkills.vue';

const emit = defineEmits(['close']);

const props = defineProps({
    inline: { type: Boolean, default: false }
});

const gameStore = useGameStore();
const employeeStore = useEmployeesStore();

// Replace storeToRefs
const employees = computed(() => employeeStore.employees);
const availableEmployeeTypes = computed(() => employeeStore.availableEmployeeTypes || {});
const skillTrees = computed(() => employeeStore.skillTrees || {});
const activeBonuses = computed(() => employeeStore.activeBonuses);
const player = computed(() => gameStore.player);

const loading = ref(false);
const selectedEmployee = ref(null);
const showBuffsModal = ref(false);
const economy = computed(() => player.value.economy);
const availableTypes = computed(() => availableEmployeeTypes.value || {});

const totalSalary = computed(() => {
    return employees.value.reduce((sum, emp) => sum + parseFloat(emp.salary), 0);
});

const activeStaffCount = computed(() => {
    return employees.value.filter(e => e.energy >= 15 && e.stress < 98).length;
});

onMounted(() => {
    employeeStore.loadEmployees();
});

async function hire(type) {
    loading.value = true;
    await employeeStore.hireEmployee(type);
    loading.value = false;
}

async function fire(id) {
    if (!confirm("Are you sure you want to terminate this contract? Severance pay may apply.")) return;
    loading.value = true;
    await employeeStore.fireEmployee(id);
    loading.value = false;
}

function calculateHiringCost(baseCost) {
    const reduction = activeBonuses.value.hiring_cost_reduction || 0;
    return Math.floor(baseCost * (1 - Math.min(0.9, reduction)));
}

async function handleUnlock({ employeeId, perkId }, callback) {
    await employeeStore.unlockPerk(employeeId, perkId);
    if (callback) callback();
}

async function handleSabbatical(empId) {
    if (!confirm('Diesen Mitarbeiter in ein 2-stündiges Sabbatical schicken? Kosten: 30× Stundenlohn als Bonus.')) return;
    loading.value = true;
    await employeeStore.sendOnSabbatical(empId);
    loading.value = false;
}

function formatType(type) {
    return availableTypes.value[type]?.name || type;
}

function formatSpec(type, specKey) {
    // This ideally would come from the backend config, but for now we can hardcode or map
    const specs = {
        'hardware_expert': 'Hardware Pro',
        'ops_automation': 'DevOps Guru',
        'retention_specialist': 'Retention Expert',
        'pr_guru': 'PR Liaison',
        'penetration_tester': 'Pen-Tester',
        'cryptographer': 'Cryptographer',
        'bgp_optimizer': 'BGP Optimizer',
        'network_guru': 'Cisco Legend',
        'talent_scout': 'Talent Scout',
        'morale_booster': 'Morale Booster'
    };
    return specs[specKey] || specKey.replace('_', ' ');
}

function formatBuffName(key) {
    const customNames = {
        'hiring_cost_reduction': 'Hiring Efficiency',
        'attrition_reduction': 'Employee Loyalty',
        'global_efficiency_bonus': 'Operational Excellence',
        'rep_gain_multiplier': 'Brand Growth',
        'power_efficiency': 'Cooling Optimization',
        'lifespan_bonus': 'Hardware Longevity',
        'provisioning_speed': 'Deployment Speed',
        'ddos_resilience': 'Shielding Strength'
    };
    return customNames[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function shouldFormatPercentage(key) {
    const percentageKeys = ['reduction', 'bonus', 'speed', 'efficiency', 'multiplier', 'chance', 'resilience', 'gain'];
    return percentageKeys.some(pk => key.toLowerCase().includes(pk));
}

function getHealthStatus(emp) {
    if (emp.stress > 90) return 'critical';
    if (emp.energy < 20) return 'warning';
    return 'online';
}

function getStatusColor(val, isStress = false, getHex = false) {
    if (isStress) {
        if (val > 80) return getHex ? '#f85149' : 'text-danger';
        if (val > 50) return getHex ? '#e3b341' : 'text-warning';
        return getHex ? '#3fb950' : 'text-success';
    } else {
        if (val < 20) return getHex ? '#f85149' : 'text-danger';
        if (val < 50) return getHex ? '#e3b341' : 'text-warning';
        return getHex ? '#3fb950' : 'text-success';
    }
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    z-index: 2500;
    display: flex; justify-content: center; align-items: center;
    padding: 20px;
}

.glass-panel {
    background: linear-gradient(135deg, rgba(20, 25, 35, 0.95) 0%, rgba(10, 15, 25, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
    border-radius: 20px;
}

.employees-overlay {
    width: 1200px;
    max-width: 95vw;
    height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.employees-overlay.inline-panel {
    width: 100%;
    max-width: none;
    height: auto;
    background: transparent;
    border: none;
    box-shadow: none;
}

.employees-overlay.inline-panel .overlay-content {
    height: auto;
    overflow: visible;
}

.employees-overlay.inline-panel .sidebar,
.employees-overlay.inline-panel .main-stats {
    height: auto;
    overflow: visible;
}

.overlay-header {
    padding: 30px 40px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-main { display: flex; align-items: center; gap: 20px; }
.header-icon { font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(88, 166, 255, 0.4)); }
.header-text h2 { margin: 0; font-size: 1.8rem; letter-spacing: -0.5px; color: #fff; }
.subtitle { margin: 5px 0 0 0; color: #8b949e; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }

.header-actions { display: flex; align-items: center; gap: 30px; }
.payroll-badge {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.2);
    padding: 10px 18px;
    border-radius: 12px;
    display: flex; flex-direction: column; align-items: flex-end;
}
.payroll-badge .label { font-size: 0.7rem; color: #f85149; font-weight: 700; text-transform: uppercase; }
.payroll-badge .value { font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; color: #fff; font-weight: 600; }

.close-btn {
    background: rgba(255,255,255,0.05); border: none; color: #8b949e;
    font-size: 2rem; cursor: pointer; width: 45px; height: 45px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.close-btn:hover { background: #f85149; color: white; transform: rotate(90deg); }

.overlay-content {
    flex: 1; display: grid; grid-template-columns: 320px 1fr;
    overflow: hidden;
}

/* SIDEBAR RECRUITMENT */
.sidebar {
    background: rgba(0, 0, 0, 0.2);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    padding: 30px;
    overflow-y: auto;
}
.sidebar-header { margin-bottom: 25px; }
.sidebar-header h3 { margin: 0; color: #fff; font-size: 1.2rem; }
.sidebar-header p { margin: 5px 0 0 0; font-size: 0.85rem; color: #8b949e; }

.hiring-list { display: flex; flex-direction: column; gap: 20px; }
.hiring-card-inner {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 15px;
    padding: 20px; padding-bottom: 0;
    transition: all 0.3s;
}
.hiring-card-inner:hover { border-color: rgba(88, 166, 255, 0.5); background: rgba(255, 255, 255, 0.05); }

.role-preview { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; }
.role-icon-box {
    width: 48px; height: 48px; background: rgba(88, 166, 255, 0.1);
    border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.role-info h4 { margin: 0; font-size: 1rem; color: #fff; }
.tag { font-size: 0.65rem; color: #58a6ff; font-weight: 800; text-transform: uppercase; background: rgba(88, 166, 255, 0.1); padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block; }

.role-desc { font-size: 0.85rem; color: #8b949e; line-height: 1.4; margin-bottom: 15px; }

.hiring-stats { border-top: 1px solid rgba(255, 255, 255, 0.05); padding: 15px 0; display: flex; flex-direction: column; gap: 8px; }
.stat-item { display: flex; justify-content: space-between; font-size: 0.85rem; }
.stat-item .label { color: #8b949e; }
.stat-item .val { font-weight: 700; font-family: 'JetBrains Mono', monospace; }

.cost-container { display: flex; gap: 8px; align-items: center; }
.original-cost { 
    font-size: 0.8rem; 
    color: #8b949e; 
    text-decoration: line-through; 
    font-family: 'JetBrains Mono', monospace;
}

.hire-button {
    width: calc(100% + 40px); margin: 0 -20px;
    background: #238636; color: white; border: none; padding: 12px;
    font-weight: 700; cursor: pointer; border-radius: 0 0 15px 15px;
    transition: all 0.2s;
}
.hire-button:hover:not(:disabled) { background: #2ea043; letter-spacing: 0.5px; }
.hire-button:disabled { background: #30363d; opacity: 0.6; cursor: not-allowed; }

/* MAIN STAFF SECTION */
.main-stats { padding: 40px; overflow-y: auto; flex: 1; }
.section-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.section-top h3 { margin: 0; font-size: 1.4rem; color: #fff; }

.status-summary { display: flex; gap: 15px; }
.status-pill {
    padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
}
.status-pill.healthy { background: rgba(63, 185, 80, 0.1); color: #3fb950; border: 1px solid rgba(63, 185, 80, 0.2); }
.status-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 10px currentColor; }

.staff-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;
}

.staff-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 18px;
    padding: 24px;
    display: flex; flex-direction: column; gap: 20px;
    position: relative; overflow: hidden;
    transition: all 0.3s;
}
.staff-card:hover { transform: translateY(-3px); border-color: rgba(88, 166, 255, 0.3); background: rgba(255, 255, 255, 0.05); }
.staff-card.stressed { border-color: rgba(227, 179, 65, 0.4); box-shadow: 0 0 20px rgba(227, 179, 65, 0.05); }
.staff-card.burnout { border-color: rgba(248, 81, 73, 0.5); opacity: 0.8; }

.staff-card-header { display: flex; gap: 15px; align-items: center; }
.staff-avatar-box { position: relative; }
.staff-avatar {
    width: 50px; height: 50px; border-radius: 12px; background: #58a6ff;
    color: #0d1117; font-size: 1.5rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
}
.status-indicator {
    position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px;
    border-radius: 50%; border: 3px solid #141923;
}
.status-indicator.online { background: #3fb950; }
.status-indicator.warning { background: #e3b341; }
.status-indicator.critical { background: #f85149; }

.staff-identity { flex: 1; }
.name-row { display: flex; align-items: center; gap: 8px; }
.name { font-weight: 700; color: #fff; font-size: 1.1rem; }
.lvl { font-size: 0.7rem; color: #8b949e; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); }
.role { font-size: 0.85rem; color: #58a6ff; font-weight: 500; }
.spec-badge {
    font-size: 0.75rem;
    background: rgba(227, 179, 65, 0.15);
    color: #e3b341;
    padding: 2px 8px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    border: 1px solid rgba(227, 179, 65, 0.3);
    font-weight: 600;
}
.spec-icon { font-size: 0.7rem; }

.fire-btn {
    background: rgba(248, 81, 73, 0.1); color: #f85149; border: 1px solid rgba(248, 81, 73, 0.1);
    width: 32px; height: 32px; border-radius: 8px; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.fire-btn:hover { background: #f85149; color: white; }

.staff-telemetry { display: flex; flex-direction: column; gap: 12px; }
.telemetery-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: #8b949e; font-weight: 700; margin-bottom: 5px; }
.telemetry-bar { height: 6px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 10px; transition: width 0.5s ease-out, background-color 0.5s; }

.task-viewer {
    background: rgba(0, 0, 0, 0.2); border-radius: 12px; padding: 15px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.task-label { font-size: 0.7rem; color: #8b949e; font-weight: 700; margin-bottom: 8px; }
.task-status { display: flex; align-items: center; gap: 10px; color: #fff; font-size: 0.95rem; font-weight: 600; }
.task-status.working { color: #58a6ff; }
.task-icon { font-size: 1.1rem; }

.task-progress { margin-top: 10px; }
.progress-track { height: 3px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; overflow: hidden; }
.progress-fill { height: 100%; background: #58a6ff; transition: width 0.3s linear; }

.staff-footer {
    display: flex; justify-content: space-between; align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 15px;
}
.f-item { display: flex; flex-direction: column; }
.f-label { font-size: 0.7rem; color: #8b949e; font-weight: 700; text-transform: uppercase; }
.f-val { font-size: 1rem; color: #fff; font-family: 'JetBrains Mono', monospace; font-weight: 600; }

.empty-state { text-align: center; padding: 60px 40px; background: rgba(0, 0, 0, 0.2); border-radius: 30px; border: 2px dashed rgba(255, 255, 255, 0.05); }
.empty-icon { font-size: 4rem; opacity: 0.3; margin-bottom: 20px; }
.empty-state h4 { color: #fff; font-size: 1.4rem; margin-bottom: 15px; }
.empty-state p { color: #8b949e; max-width: 400px; margin: 0 auto; line-height: 1.6; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animation-fade-in { animation: fadeIn 0.4s ease-out; }

.skills-btn {
    background: rgba(88, 166, 255, 0.1);
    border: 1px solid rgba(88, 166, 255, 0.3);
    color: #58a6ff;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex; align-items: center; gap: 6px;
    position: relative;
    transition: all 0.2s;
}
.skills-btn:hover { background: rgba(88, 166, 255, 0.2); transform: translateY(-1px); }
.notification-dot {
    position: absolute; top: -3px; right: -3px;
    width: 8px; height: 8px; background: #e3b341; border-radius: 50%;
    box-shadow: 0 0 5px #e3b341;
}

.skills-modal-backdrop {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
    z-index: 100;
    display: flex; justify-content: center; align-items: center;
}
.skills-modal-content {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 12px;
    width: 600px; max-width: 90%;
    max-height: 90%;
    display: flex; flex-direction: column;
    padding: 20px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
}
.skills-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px;
}
.skills-header h3 { margin: 0; color: #fff; }
.close-sub { background: none; border: none; color: #8b949e; font-size: 1.5rem; cursor: pointer; }
.close-sub:hover { color: #fff; }

.buffs-btn {
    background: rgba(227, 179, 65, 0.1);
    border: 1px solid rgba(227, 179, 65, 0.3);
    color: #e3b341;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    display: flex; align-items: center; gap: 8px;
    transition: all 0.2s;
}
.buffs-btn:hover { background: rgba(227, 179, 65, 0.2); transform: translateY(-1px); }
.buffs-modal { width: 500px; }
.buff-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 10px; max-height: 400px; overflow-y: auto; padding-right: 10px; }
.buff-item { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 10px; display: flex; align-items: center; gap: 15px; }
.buff-icon { font-size: 1.5rem; background: rgba(227, 179, 65, 0.1); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.buff-details { display: flex; flex-direction: column; }
.buff-name { font-weight: 700; color: #fff; font-size: 0.9rem; }
.buff-value { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; padding-top: 4px; }
.empty-buffs { text-align: center; padding: 40px 20px; color: #8b949e; }

/* FEATURE 284: Sabbatical */
.footer-actions { display: flex; gap: 8px; align-items: center; }
.sabbatical-btn {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
    padding: 5px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.75rem;
    transition: all 0.2s;
}
.sabbatical-btn:hover:not(:disabled) { background: rgba(16, 185, 129, 0.2); transform: translateY(-1px); }
.sabbatical-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.sabbatical-badge {
    background: rgba(88, 166, 255, 0.1);
    border: 1px solid rgba(88, 166, 255, 0.3);
    color: #58a6ff;
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.75rem;
    animation: pulse-badge 2s infinite;
}
.staff-card.on-sabbatical {
    border-color: rgba(16, 185, 129, 0.3) !important;
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.03) 0%, transparent 100%) !important;
}
@keyframes pulse-badge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
</style>
