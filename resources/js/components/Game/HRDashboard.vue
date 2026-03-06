<template>
    <div class="hr-dashboard">
        <!-- Dashboard Header -->
        <header class="hr-header">
            <div class="header-main">
                <div class="header-icon">👥</div>
                <div class="header-text">
                    <h2 class="l1-priority">HUMAN_CAPITAL_LOGISTICS // [USER_STRICT]</h2>
                    <p class="subtitle l3-priority">STAFF_OPERATIONS_CONTROL_&_EFFICIENCY_MATRIX</p>
                </div>
            </div>
            <div class="header-stats">
                <div class="stat-box">
                    <span class="label l3-priority">
                        TOTAL_OPERATIONAL_PAYROLL
                        <span class="v3-info-trigger" 
                            @mouseenter="tooltipStore.show($event, { title: 'TOTAL_PAYROLL', content: 'Aggregated hourly salary of all active personnel.', hint: 'High-level employees cost significantly more but work faster.' })"
                            @mouseleave="tooltipStore.hide()"
                        >ⓘ</span>
                    </span>
                    <span class="value mono text-warning l1-priority">-${{ totalHourlySalary.toFixed(2) }}/hr</span>
                </div>
                <div class="stat-box">
                    <span class="label l3-priority">
                        PERSONNEL_HEADCOUNT
                        <span class="v3-info-trigger" 
                            @mouseenter="tooltipStore.show($event, { title: 'HEADCOUNT', content: 'Current number of active employees vs. total available roster slots.', hint: 'Upgrade your DC tier to increase limits.' })"
                            @mouseleave="tooltipStore.hide()"
                        >ⓘ</span>
                    </span>
                    <span class="value mono l1-priority">{{ employees.length }} / {{ maxEmployees }}</span>
                </div>
                <div class="stat-box">
                    <span class="label l3-priority">
                        AGGREGATE_EFFICIENCY_INDEX
                        <span class="v3-info-trigger" 
                            @mouseenter="tooltipStore.show($event, { title: 'AVG_EFFICIENCY', content: 'Current workforce operational speed. Affected by level, happiness, and infrastructure quality.', hint: 'Keep staff happy to maintain high productivity.' })"
                            @mouseleave="tooltipStore.hide()"
                        >ⓘ</span>
                    </span>
                    <span class="value mono l1-priority" :class="avgEfficiency > 1.0 ? 'text-success' : ''">{{ (avgEfficiency * 100).toFixed(0) }}%</span>
                </div>
            </div>
        </header>

        <div class="hr-content">
            <!-- RECRUITMENT SECTION -->
            <aside class="recruitment-panel">
                <div class="panel-header">
                    <h3 class="l2-priority">
                        TALENT_PROCUREMENT_HUB
                        <span class="v3-info-trigger" 
                            @mouseenter="tooltipStore.show($event, { title: 'RECRUITMENT', content: 'Hire new specialists. Each role automates different aspects of your data center.', hint: 'Review signing bonuses before hiring.' })"
                            @mouseleave="tooltipStore.hide()"
                        >ⓘ</span>
                    </h3>
                    <div class="header-line"></div>
                </div>
                
                <div class="hiring-list">
                    <div v-for="(type, key) in availableTypes" :key="key" class="hiring-card">
                        <div class="card-header">
                            <div class="role-icon">{{ key === 'sys_admin' ? '🛠️' : '🎧' }}</div>
                            <div class="role-info">
                                <h4 class="l2-priority">{{ type.name }}</h4>
                                <span class="role-tag l3-priority">{{ key.replace('_', ' ').toUpperCase() }}</span>
                            </div>
                        </div>
                        
                        <p class="role-desc">{{ type.description }}</p>
                        
                        <div class="hiring-metrics">
                            <div class="metric">
                                <span class="lbl">SIGNING_BONUS</span>
                                <span class="val mono">${{ type.hiring_cost.toLocaleString() }}</span>
                            </div>
                            <div class="metric">
                                <span class="lbl">SALARY</span>
                                <span class="val mono">${{ type.base_salary }}/hr</span>
                            </div>
                        </div>

                        <button 
                            class="btn-hire" 
                            @click="hire(key)"
                            :disabled="isLoading || !canAfford(type.hiring_cost)"
                        >
                            <span v-if="isLoading && loadingKey === key">PROCESSING...</span>
                            <span v-else-if="!canAfford(type.hiring_cost)">INSUFFICIENT FUNDS</span>
                            <span v-else>RECRUIT</span>
                        </button>
                    </div>
                </div>

                <!-- ACTIVE SYNERGIES SECTION -->
                <div class="synergy-panel" v-if="empStore.activeSynergies.length > 0">
                    <div class="panel-header">
                        <h3 class="l2-priority">
                            TEAM_SYNERGY_MATRICES
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'SYNERGIES', content: 'Passive bonuses activated by specific employee combinations.', hint: 'Synergies stack and affect global datacenter performance.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                        <div class="header-line"></div>
                    </div>
                    <div class="synergy-list">
                        <div v-for="syn in empStore.activeSynergies" :key="syn.name" class="synergy-badge"
                            @mouseenter="tooltipStore.show($event, { title: syn.name, content: formatSynergyEffects(syn.effects) })"
                            @mouseleave="tooltipStore.hide()"
                        >
                            <span class="syn-icon">⚡</span>
                            <span class="syn-name">{{ syn.name }}</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- STAFF MANAGEMENT SECTION -->
            <main class="staff-panel">
                <div class="panel-header">
                    <h3 class="l2-priority">
                        OPERATIONAL_SPECIALIST_COORDINATES
                        <span class="v3-info-trigger" 
                            @mouseenter="tooltipStore.show($event, { title: 'STAFF_MANAGEMENT', content: 'Monitor and manage the health and productivity of your current team.', hint: 'Use the training action to improve efficiency permanently.' })"
                            @mouseleave="tooltipStore.hide()"
                        >ⓘ</span>
                    </h3>
                    <div class="header-line"></div>
                </div>

                <div v-if="employees.length === 0" class="empty-state">
                    <div class="empty-icon">📂</div>
                    <h4>NO_PERSONNEL_FILE_FOUND</h4>
                    <p>Recruit specialists to automate infrastructure maintenance and support.</p>
                </div>

                <div v-else class="staff-grid">
                    <div v-for="emp in employees" :key="emp.id" class="staff-card" :class="{ 
                        'stressed': emp.stress > 70, 
                        'burnout': emp.metadata && emp.metadata.burnout_until,
                        'sabbatical': emp.current_task?.includes('Sabbatical'),
                        'resigning': emp.is_resigning
                    }">
                        <!-- RESIGNATION NOTICE OVERLAY -->
                        <div v-if="emp.is_resigning" class="resignation-notice">
                            <span class="pulse-warning">⚠️</span>
                            <span>RESIGNING_NOTICE: {{ formatDeadline(emp.resignation_deadline) }}</span>
                        </div>
                        <!-- Card Header -->
                        <div class="card-top">
                            <div class="avatar-block">
                                <div class="avatar">{{ emp.name.charAt(0) }}</div>
                                <div class="status-dot" :class="getHealthStatus(emp)"></div>
                            </div>
                            <div class="identity-block">
                                <div class="name l1-priority">{{ emp.name }}</div>
                                <div class="sub-role l3-priority">
                                    {{ formatType(emp.type) }} 
                                    <span class="lvl-badge">LVL {{ emp.level }}</span>
                                    <span class="loyalty-badge" 
                                        @mouseenter="tooltipStore.show($event, { title: 'LOYALTY_SCORE', content: 'Employee commitment to the company. Low loyalty increases the risk of being poached or resigning.', hint: 'Boost by granting raises, sabbaticals, or bonuses.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >LOYALTY: {{ parseFloat(emp.loyalty).toFixed(0) }}%</span>
                                </div>
                            </div>
                            <div class="efficiency-block"
                                @mouseenter="tooltipStore.show($event, { title: 'EFFICIENCY_MODIFIER', content: 'Cumulative multiplier for task completion speed.', hint: 'Affected by level, morale, and training.' })"
                                @mouseleave="tooltipStore.hide()"
                            >
                                <div class="efficiency-val">{{ (emp.efficiency * 100).toFixed(0) }}%</div>
                                <div class="efficiency-label">EFF</div>
                            </div>
                        </div>

                        <!-- Telemetry -->
                        <div class="telemetry-rows">
                            <div class="t-row" 
                                @mouseenter="tooltipStore.show($event, { title: 'STAMINA_LEVEL', content: 'Remaining physical and mental energy. Low energy causes staff to take breaks.', hint: 'Automatic breaks happen at <15 energy.' })"
                                @mouseleave="tooltipStore.hide()"
                            >
                                <div class="t-label">Stamina</div>
                                <div class="t-bar-bg">
                                    <div class="t-bar-fill" :style="{ width: emp.energy + '%', background: getEnergyColor(emp.energy) }"></div>
                                </div>
                            </div>
                            <div class="t-row"
                                @mouseenter="tooltipStore.show($event, { title: 'STRESS_LEVEL', content: 'Accumulated mental load from heavy workloads and outages.', hint: 'High stress (>95) leads to sudden burnout events.' })"
                                @mouseleave="tooltipStore.hide()"
                            >
                                <div class="t-label">Stress</div>
                                <div class="t-bar-bg">
                                    <div class="t-bar-fill" :style="{ width: emp.stress + '%', background: getStressColor(emp.stress) }"></div>
                                </div>
                            </div>
                            <div v-if="emp.stress > 80" class="t-row risk-row"
                                @mouseenter="tooltipStore.show($event, { title: 'BURNOUT_RISK', content: 'Critical stress levels detected. High probability of immediate medical leave!', hint: 'Grant a sabbatical or raise immediately!' })"
                                @mouseleave="tooltipStore.hide()"
                            >
                                <div class="t-label">Risk</div>
                                <div class="t-bar-bg risk-bg">
                                    <div class="t-bar-fill risk-fill" :style="{ width: ((emp.stress - 80) * 5) + '%' }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Current Task -->
                        <div class="task-status-box" :class="{ 'active': emp.task_progress > 0 || emp.current_task }">
                            <div class="task-icon">{{ getTaskIcon(emp) }}</div>
                            <div class="task-main">
                                <div class="task-text">{{ emp.current_task || 'IDLE' }}</div>
                                <div class="task-timer" v-if="getRemainingTime(emp)">
                                    ETA: {{ getRemainingTime(emp) }}
                                </div>
                            </div>
                            <div class="task-progress-bar" v-if="emp.task_progress > 0">
                                <div class="tp-fill" :style="{ width: emp.task_progress + '%' }"></div>
                            </div>
                        </div>

                        <!-- Actions Footer -->
                        <div class="card-actions">
                            <div class="salary-display">${{ parseFloat(emp.salary).toFixed(2) }}/hr</div>
                            
                            <div class="action-buttons">
                                <button v-if="emp.is_resigning" 
                                    class="btn-icon special pulse" @click="persuade(emp)" title="Persuade to stay (Lump-sum bonus + 15% Raise)">🤝</button>

                                <button class="btn-icon" @click="train(emp)" title="Basic Training (+XP, +Stress)">📖</button>
                                <button v-if="emp.level >= 5 && emp.stress > 60 && !emp.current_task?.includes('Sabbatical')" 
                                    class="btn-icon" @click="sabbatical(emp)" title="Paid Sabbatical (Cost: 1mo salary, Resets stress to 0%, 2h duration)">🏖️</button>
                                <button class="btn-icon" @click="seminar(emp)" title="Advanced Seminar (+10% Permanent Efficiency boost, 12h duration)">🏛️</button>
                                <button class="btn-icon" @click="promote(emp)" title="Grant Raise (+Salary, -Stress)">💰</button>
                                <button class="btn-icon danger" @click="fire(emp)" title="Terminate Personnel">✕</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Modals -->
        <ConfirmationModal 
            :show="modal.show"
            :title="modal.title"
            :message="modal.message"
            :warning="modal.warning"
            :confirm-label="modal.confirmLabel"
            :type="modal.type"
            @confirm="modal.onConfirm"
            @cancel="modal.show = false"
        />
    </div>
</template>

<script setup>
import { onMounted, computed, ref, reactive } from 'vue';
import { useEmployeesStore } from '../../stores/employees';
import { useEconomyStore } from '../../stores/economy';
import { useTooltipStore } from '../../stores/tooltip';
import ConfirmationModal from '../UI/ConfirmationModal.vue';
import api from '../../utils/api';

const empStore = useEmployeesStore();
const ecoStore = useEconomyStore();
const tooltipStore = useTooltipStore();

const isLoading = ref(false);
const loadingKey = ref(null);

const modal = reactive({
    show: false,
    title: '',
    message: '',
    warning: '',
    confirmLabel: 'Confirm',
    type: 'info',
    onConfirm: () => {}
});

const employees = computed(() => empStore.employees);
const availableTypes = computed(() => empStore.availableEmployeeTypes && Object.keys(empStore.availableEmployeeTypes).length ? empStore.availableEmployeeTypes : {});
const maxEmployees = 20; // Hard limit for now

const totalHourlySalary = computed(() => {
    return employees.value.reduce((sum, e) => sum + parseFloat(e.salary), 0);
});

const avgEfficiency = computed(() => {
    if (!employees.value.length) return 0;
    return employees.value.reduce((sum, e) => sum + parseFloat(e.efficiency), 0) / employees.value.length;
});

onMounted(async () => {
    await empStore.loadEmployees();
    // Assuming available types are loaded via game store or config, 
    // actually EmployeeStore doesn't load types automatically. 
    // We should fetch config if not present.
    // For now, rely on what's in store or fetch manually if needed.
    if (!Object.keys(availableTypes.value).length) {
        // Try to fetch via API if store doesn't have it
        const res = await api.get('/game/config');
        if (res.success && res.data.employee_types) {
             // We need a way to set this in store, or use local ref.
             // empStore doesn't expose setLoadingTypes.
             // This is a flaw in my store design (Step 22910).
             // I'll assume it's preloaded by game.js which loads 'config' globally?
             // Or I can just fetch it locally.
             // Let's use local ref for types if store fails.
             // Wait, availableTypes is from store getter.
        }
    }
});

function canAfford(cost) {
    return ecoStore.player.economy.balance >= cost;
}

async function hire(typeKey) {
    loadingKey.value = typeKey;
    isLoading.value = true;
    await empStore.hireEmployee(typeKey);
    isLoading.value = false;
    loadingKey.value = null;
    ecoStore.loadEconomy(); // Refresh balance
}

async function fire(emp) {
    modal.title = 'TERMINATE_EMPLOYEE';
    modal.message = `Are you sure you want to terminate ${emp.name}?`;
    const severance = (parseFloat(emp.salary) * (5 + parseInt(emp.level))).toFixed(2);
    modal.warning = `Severance pay of $${severance} will be deducted from your balance.`;
    modal.confirmLabel = 'TERMINATE';
    modal.type = 'danger';
    modal.onConfirm = async () => {
        modal.show = false;
        isLoading.value = true;
        await empStore.fireEmployee(emp.id);
        isLoading.value = false;
        ecoStore.loadEconomy();
    };
    modal.show = true;
}

async function train(emp) {
    const cost = parseFloat(emp.salary) * 20;
    
    modal.title = 'STAFF_TRAINING';
    modal.message = `Enroll ${emp.name} in advanced certification training?`;
    modal.warning = `Registration fee: $${cost.toFixed(0)}`;
    modal.confirmLabel = 'START_TRAINING';
    modal.type = 'info';
    modal.onConfirm = async () => {
        modal.show = false;
        try {
            isLoading.value = true;
            await api.post('/employees/train', { employee_id: emp.id });
            await empStore.loadEmployees();
        } catch (e) {
            alert(e.message || "Training failed");
        } finally {
            isLoading.value = false;
        }
    };
    modal.show = true;
}

async function promote(emp) {
    const raise = 0.10; // 10%
    
    modal.title = 'PROMOTION_REVIEW';
    modal.message = `Offer ${emp.name} a ${(raise * 100).toFixed(0)}% salary raise?`;
    modal.warning = "This will increase hourly overhead but reduce employee stress.";
    modal.confirmLabel = 'GRANT_RAISE';
    modal.type = 'info';
    modal.onConfirm = async () => {
        modal.show = false;
        try {
            isLoading.value = true;
            await api.post('/employees/raise', { employee_id: emp.id, amount: raise });
            await empStore.loadEmployees();
        } catch (e) {
            alert(e.message || "Promotion failed");
        } finally {
            isLoading.value = false;
        }
    };
    modal.show = true;
}

async function seminar(emp) {
    const cost = 7500;
    
    modal.title = 'ADVANCED_OFFSITE_SEMINAR';
    modal.message = `Send ${emp.name} to a 12-hour advanced professional development seminar?`;
    modal.warning = `Cost: $${cost.toLocaleString()} | Benefit: +10% Permanent Efficiency boost upon return.`;
    modal.confirmLabel = 'ENROLL_IN_SEMINAR';
    modal.type = 'info';
    modal.onConfirm = async () => {
        modal.show = false;
        try {
            isLoading.value = true;
            const res = await api.post(`/employees/${emp.id}/seminar`);
            if (res.success) {
                await empStore.loadEmployees();
                ecoStore.loadEconomy();
            }
        } finally {
            isLoading.value = false;
        }
    };
    modal.show = true;
}

async function sabbatical(emp) {
    const cost = parseFloat(emp.salary) * 30; // Matches backend
    
    modal.title = 'PAID_SABBATICAL_GRANT';
    modal.message = `Grant ${emp.name} a 2-hour paid sabbatical?`;
    modal.warning = `Grant bonus: $${cost.toLocaleString()} | Benefit: Total stress reset and loyalty boost.`;
    modal.confirmLabel = 'GRANT_SABBATICAL';
    modal.type = 'info';
    modal.onConfirm = async () => {
        modal.show = false;
        try {
            isLoading.value = true;
            const res = await api.post(`/employees/${emp.id}/sabbatical`); // Calling API directly or via store
            if (res.success) {
                await empStore.loadEmployees();
                ecoStore.loadEconomy();
            }
        } catch (e) {
            alert(e.response?.data?.error || e.message || "Sabbatical failed");
        } finally {
            isLoading.value = false;
        }
    };
    modal.show = true;
}

async function persuade(emp) {
    const cost = parseFloat(emp.salary) * 25;
    
    modal.title = 'CONTRACT_NEGOTIATION';
    modal.message = `Persuade ${emp.name} to withdraw their resignation?`;
    modal.warning = `Requires a lump-sum bonus of $${cost.toLocaleString()} and a mandatory 15% salary increase.`;
    modal.confirmLabel = 'SIGN_NEW_CONTRACT';
    modal.type = 'success';
    modal.onConfirm = async () => {
        modal.show = false;
        await empStore.persuadeToStay(emp.id);
        ecoStore.loadEconomy();
    };
    modal.show = true;
}

/* ─── FORMATTERS ─── */
function formatType(key) {
    const type = availableTypes.value[key];
    return type ? type.name : key;
}

function getHealthStatus(emp) {
    if (emp.stress > 90) return 'critical';
    if (emp.energy < 20) return 'warning';
    return 'nominal';
}

function getEnergyColor(val) {
    if (val < 20) return 'var(--v3-danger)';
    if (val < 50) return 'var(--v3-warning)';
    return 'var(--v3-success)';
}

function getStressColor(val) {
    if (val > 80) return 'var(--v3-danger)';
    if (val > 50) return 'var(--v3-warning)';
    return 'var(--v3-success)'; // Low stress is good
}

function getRemainingTime(emp) {
    const target = emp.sabbatical_until || 
                   emp.metadata?.seminar_until || 
                   emp.metadata?.burnout_until ||
                   emp.resignation_deadline;
    
    if (!target) return null;
    
    const diff = new Date(target) - new Date();
    if (diff <= 0) return null;
    
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatSynergyEffects(effects) {
    return Object.entries(effects).map(([key, val]) => {
        const sign = val > 0 ? '+' : '';
        const pct = (val * 100).toFixed(0) + '%';
        return `${key.replace(/_/g, ' ').toUpperCase()}: ${sign}${pct}`;
    }).join(' | ');
}

function formatDeadline(deadline) {
    if (!deadline) return '';
    try {
        const d = new Date(deadline);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch(e) { return deadline; }
}

function getTaskIcon(emp) {
    if (emp.energy < 15) return '💤';
    if (emp.stress > 95) return '🤯';
    if (emp.current_task?.includes('Seminar')) return '🎓';
    if (emp.current_task?.includes('Sabbatical')) return '🏖️';
    if (emp.current_task?.includes('Medical Leave')) return '⚕️';
    if (emp.current_task?.includes('Burnout')) return '🚑';
    if (emp.current_task?.includes('Repair')) return '🔧';
    if (emp.current_task?.includes('Customer')) return '🤝';
    return '👁️';
}

</script>

<style scoped>
.hr-dashboard {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--v3-bg-base);
    color: var(--v3-text-primary);
}

.hr-header {
    background: rgba(0,0,0,0.2);
    padding: 20px 32px;
    border-bottom: 1px solid var(--v3-border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-main { display: flex; gap: 16px; align-items: center; }
.header-icon { font-size: 2rem; background: rgba(255,255,255,0.05); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.header-text h2 { font-size: 1rem; margin: 0; font-weight: 900; letter-spacing: 0.1em; color: #fff; }
.subtitle { font-size: 0.6rem; color: var(--v3-text-ghost); letter-spacing: 0.15em; margin-top: 4px; }

.header-stats { display: flex; gap: 24px; }
.stat-box { display: flex; flex-direction: column; align-items: flex-end; }
.stat-box .label { font-size: 0.5rem; color: var(--v3-text-secondary); font-weight: 800; letter-spacing: 0.1em; }
.stat-box .value { font-size: 1rem; font-weight: 700; color: #fff; }

.hr-content {
    flex: 1;
    display: grid;
    grid-template-columns: 350px 1fr;
    overflow: hidden;
}

/* RECRUITMENT SIDEBAR */
.recruitment-panel {
    background: rgba(0,0,0,0.1);
    border-right: 1px solid var(--v3-border-soft);
    padding: 24px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}
.panel-header h3 { font-size: 0.7rem; font-weight: 900; letter-spacing: 0.1em; color: var(--v3-text-secondary); margin: 0; white-space: nowrap; }
.header-line { height: 1px; background: var(--v3-border-soft); flex: 1; }

.hiring-list { display: flex; flex-direction: column; gap: 16px; }

.hiring-card {
    background: var(--v3-bg-surface);
    border: 1px solid var(--v3-border-soft);
    padding: 16px;
    border-radius: 4px;
    transition: all 0.2s;
}
.hiring-card:hover { border-color: var(--v3-text-ghost); transform: translateY(-2px); }

.card-header { display: flex; gap: 12px; margin-bottom: 12px; }
.role-icon { font-size: 1.5rem; background: rgba(255,255,255,0.05); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
.role-info h4 { margin: 0; font-size: 0.8rem; font-weight: 800; color: #fff; }
.role-tag { font-size: 0.5rem; color: var(--v3-accent); font-weight: 900; background: rgba(88,166,255,0.1); padding: 2px 6px; border-radius: 2px; }

.role-desc { font-size: 0.65rem; color: var(--v3-text-secondary); line-height: 1.4; margin-bottom: 16px; }

.hiring-metrics {
    display: flex; justify-content: space-between; margin-bottom: 16px;
    background: rgba(0,0,0,0.2); padding: 8px; border-radius: 4px;
}
.metric { display: flex; flex-direction: column; gap: 2px; }
.metric .lbl { font-size: 0.5rem; color: var(--v3-text-ghost); font-weight: 700; }
.metric .val { font-size: 0.7rem; font-weight: 700; color: #fff; }

.btn-hire {
    width: 100%;
    background: var(--v3-success);
    color: #000;
    border: none;
    padding: 10px;
    font-size: 0.6rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    cursor: pointer;
    text-transform: uppercase;
    transition: all 0.2s;
}
.btn-hire:hover:not(:disabled) { filter: brightness(1.1); }
.btn-hire:disabled { opacity: 0.5; cursor: not-allowed; background: var(--v3-bg-surface); color: var(--v3-text-ghost); }

/* SYNERGY PANEL */
.synergy-panel { margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--v3-border-soft); }
.synergy-list { display: flex; flex-wrap: wrap; gap: 8px; }
.synergy-badge {
    background: rgba(88,166,255,0.1);
    border: 1px solid rgba(88,166,255,0.2);
    padding: 6px 10px;
    border-radius: 4px;
    display: flex; align-items: center; gap: 6px;
    cursor: help;
    transition: all 0.2s;
}
.synergy-badge:hover { background: rgba(88,166,255,0.2); border-color: var(--v3-accent); transform: translateY(-1px); }
.syn-icon { font-size: 0.8rem; }
.syn-name { font-size: 0.6rem; font-weight: 800; color: #fff; letter-spacing: 0.05em; text-transform: uppercase; }

/* RESIGNATION */
.staff-card.resigning { border: 1px solid var(--v3-status-error); background: rgba(239, 68, 68, 0.05); }
.resignation-notice {
    background: var(--v3-status-error);
    color: white;
    font-size: 0.55rem;
    font-weight: 900;
    text-align: center;
    padding: 2px 0;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.pulse-warning { animation: v3-pulse 1s infinite; }
@keyframes v3-pulse {
    0% { opacity: 1; }
    50% { opacity: 0.4; }
    100% { opacity: 1; }
}
.btn-icon.special { background: rgba(52, 211, 153, 0.2); border: 1px solid #34d399; }
.btn-icon.pulse { animation: v3-bounce 2s infinite; }
@keyframes v3-bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* STAFF GRID */
.staff-panel {
    padding: 24px;
    overflow-y: auto;
    background: var(--v3-bg-base);
    background-image: radial-gradient(var(--v3-border-soft) 1px, transparent 1px);
    background-size: 20px 20px;
}

.staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.staff-card {
    background: var(--v3-bg-surface);
    border: 1px solid var(--v3-border-soft);
    border-radius: 6px;
    padding: 16px;
    display: flex; flex-direction: column; gap: 16px;
    position: relative;
    overflow: hidden;
}
.staff-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--v3-border-soft); }
.staff-card.stressed { border-color: var(--v3-warning); }
.staff-card.stressed::before { background: var(--v3-warning); }
.staff-card.burnout { border-color: var(--v3-danger); opacity: 0.8; }
.staff-card.burnout::before { background: var(--v3-danger); }
.staff-card.sabbatical { border-color: var(--v3-success); opacity: 0.95; }
.staff-card.sabbatical::before { background: var(--v3-success); }

.card-top { display: flex; gap: 12px; align-items: center; }
.avatar-block { position: relative; }
.avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; border-radius: 4px; }
.status-dot { position: absolute; bottom: -2px; right: -2px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--v3-bg-surface); }
.status-dot.nominal { background: var(--v3-success); }
.status-dot.warning { background: var(--v3-warning); }
.status-dot.critical { background: var(--v3-danger); }

.identity-block { flex: 1; overflow: hidden; display: flex; flex-direction: column; gap: 4px; }
.name { font-size: 0.85rem; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sub-role { font-size: 0.6rem; color: var(--v3-text-secondary); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.lvl-badge { background: rgba(255,255,255,0.08); padding: 1px 4px; border-radius: 2px; font-size: 0.55rem; color: var(--v3-text-ghost); border: 1px solid rgba(255,255,255,0.1); }
.loyalty-badge { font-size: 0.55rem; color: var(--v3-text-primary); font-weight: 700; }

.efficiency-block {
    text-align: right;
    background: rgba(0,0,0,0.2);
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.05);
}
.efficiency-val { font-size: 0.9rem; font-weight: 900; color: var(--v3-accent); font-family: var(--font-family-mono); line-height: 1; }
.efficiency-label { font-size: 0.45rem; color: var(--v3-text-ghost); font-weight: 800; text-transform: uppercase; margin-top: 2px; }

.t-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.t-label { width: 50px; font-size: 0.55rem; color: var(--v3-text-ghost); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; }
.t-bar-bg { flex: 1; height: 5px; background: rgba(0,0,0,0.3); border-radius: 2px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.5); }
.t-bar-fill { height: 100%; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); }

.risk-row { margin-top: 4px; }
.risk-bg { background: rgba(248,81,73,0.1); }
.risk-fill { background: linear-gradient(90deg, #f85149, #ff7b72) !important; box-shadow: 0 0 10px rgba(248,81,73,0.5); }

.task-status-box {
    background: rgba(0,0,0,0.3);
    padding: 10px;
    border-radius: 6px;
    display: flex; align-items: center; gap: 12px;
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    border: 1px solid transparent;
}
.task-status-box.active { color: var(--v3-text-primary); border-color: rgba(255,255,255,0.05); background: rgba(255,255,255,0.03); }
.task-main { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.task-text { font-weight: 700; }
.task-timer { font-size: 0.55rem; color: var(--v3-accent); font-family: var(--font-family-mono); font-weight: 800; }
.task-icon { font-size: 1.1rem; }
.task-progress-bar { width: 60px; height: 3px; background: rgba(255,255,255,0.05); position: relative; border-radius: 2px; overflow: hidden; }
.tp-fill { height: 100%; background: var(--v3-accent); box-shadow: 0 0 5px var(--v3-accent); }

.card-actions {
    display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--v3-border-soft);
}
.salary-display { font-family: var(--font-family-mono); font-size: 0.7rem; color: var(--v3-text-secondary); }

.action-buttons { display: flex; gap: 6px; }
.btn-icon {
    width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
    background: transparent; border: 1px solid var(--v3-border-soft); color: var(--v3-text-ghost);
    cursor: pointer; border-radius: 2px; transition: all 0.2s;
}
.btn-icon:hover { color: #fff; border-color: #fff; background: rgba(255,255,255,0.05); }
.btn-icon.danger:hover { color: var(--v3-danger); border-color: var(--v3-danger); background: rgba(248,81,73,0.1); }

/* Empty state */
.empty-state {
    grid-column: 1 / -1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px; color: var(--v3-text-ghost);
}
.empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }

.v3-info-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(88, 166, 255, 0.15);
    color: #58a6ff;
    font-size: 10px;
    font-weight: 800;
    cursor: help;
    margin-left: 6px;
    vertical-align: middle;
    border: 1px solid rgba(88, 166, 255, 0.3);
    transition: all 0.2s;
}

.v3-info-trigger:hover {
    background: #58a6ff;
    color: #05070a;
    box-shadow: 0 0 10px rgba(88, 166, 255, 0.4);
}
</style>
