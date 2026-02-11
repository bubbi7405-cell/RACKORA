<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="employees-overlay">
            <div class="overlay-header">
                <h2>Personnel Management</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <!-- Hiring Section -->
                <div class="section hiring-section">
                    <h3>Recruitment</h3>
                    <div class="hiring-grid">
                        <div v-for="(type, key) in availableTypes" :key="key" class="hiring-card">
                            <div class="card-header">
                                <div class="role-icon">
                                    <span v-if="key === 'sys_admin'">💻</span>
                                    <span v-else>🎧</span>
                                </div>
                                <h4>{{ type.name }}</h4>
                            </div>
                            <p class="role-desc">{{ type.description }}</p>
                            
                            <div class="card-footer">
                                <div class="costs">
                                    <div class="cost-row">
                                        <span class="label">Hiring Cost:</span>
                                        <span class="val text-warning">${{ type.hiring_cost }}</span>
                                    </div>
                                    <div class="cost-row">
                                        <span class="label">Salary:</span>
                                        <span class="val text-muted">${{ type.base_salary }}/hr</span>
                                    </div>
                                </div>
                                <button 
                                    class="btn btn-primary w-100" 
                                    @click="hire(key)"
                                    :disabled="loading || economy.balance < type.hiring_cost"
                                >
                                    Hire Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section staff-section">
                    <div class="section-header">
                        <h3>Current Staff ({{ employees.length }})</h3>
                        <div class="total-salary" v-if="totalSalary > 0">
                            Total Payroll: <span class="text-danger">-${{ totalSalary }}/hr</span>
                        </div>
                    </div>

                    <div v-if="employees.length === 0" class="empty-state">
                        <p>No employees hired yet.</p>
                        <small>Hire staff to automate tasks and improve efficiency.</small>
                    </div>

                    <div v-else class="staff-grid">
                        <div v-for="emp in employees" :key="emp.id" class="staff-card">
                            <div class="staff-header">
                                <div class="staff-avatar">{{ emp.name.charAt(0) }}</div>
                                <div class="staff-info">
                                    <div class="staff-name">{{ emp.name }}</div>
                                    <div class="staff-role">{{ formatType(emp.type) }}</div>
                                </div>
                                <button class="btn-icon danger" @click="fire(emp.id)" title="Terminate Contract">
                                    &times;
                                </button>
                            </div>
                            <div class="staff-metrics">
                                <div class="metric">
                                    <span>Efficiency</span>
                                    <div class="progress-bar">
                                        <div class="fill" :style="{ width: (emp.efficiency * 100) + '%' }"></div>
                                    </div>
                                </div>
                                <div class="metric-text">
                                    Salary: ${{ emp.salary }}/hr
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';

const emit = defineEmits(['close']);
const gameStore = useGameStore();
const { employees, availableEmployeeTypes, player } = storeToRefs(gameStore);

const loading = ref(false);
const economy = computed(() => player.value.economy);

const availableTypes = computed(() => availableEmployeeTypes.value || {});

const totalSalary = computed(() => {
    return employees.value.reduce((sum, emp) => sum + parseFloat(emp.salary), 0);
});

onMounted(() => {
    gameStore.loadEmployees();
});

async function hire(type) {
    loading.value = true;
    await gameStore.hireEmployee(type);
    loading.value = false;
}

async function fire(id) {
    loading.value = true;
    await gameStore.fireEmployee(id);
    loading.value = false;
}

function formatType(type) {
    return availableTypes.value[type]?.name || type;
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.employees-overlay {
    width: 900px;
    max-width: 95vw;
    height: 85vh;
    background: #0d1117;
    border: 1px solid #30363d;
    box-shadow: 0 0 50px rgba(0,0,0,0.6);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 20px 25px;
    border-bottom: 1px solid #30363d;
    background: #161b22;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.overlay-header h2 {
    margin: 0;
    color: #e6edf3;
    font-size: 1.5rem;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #fff; }

.overlay-body {
    flex: 1;
    overflow-y: auto;
    padding: 25px;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.section h3 {
    margin: 0 0 20px 0;
    font-size: 1.1rem;
    color: #c9d1d9;
    padding-bottom: 10px;
    border-bottom: 2px solid #21262d;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #21262d;
    padding-bottom: 10px;
}
.section-header h3 { border: none; padding: 0; margin: 0; }

.hiring-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.hiring-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, border-color 0.2s;
}
.hiring-card:hover {
    transform: translateY(-2px);
    border-color: #58a6ff;
}

.card-header {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 15px;
}

.role-icon {
    font-size: 2rem;
    background: #21262d;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.hiring-card h4 { margin: 0; font-size: 1.1rem; color: #fff; }

.role-desc { color: #8b949e; font-size: 0.9rem; line-height: 1.4; flex: 1; margin-bottom: 20px; }

.card-footer {
    background: #21262d;
    margin: -20px;
    margin-top: 0;
    padding: 20px;
    border-top: 1px solid #30363d;
}

.costs { margin-bottom: 15px; }
.cost-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: #8b949e; }
.cost-row .val { font-weight: 600; font-family: monospace; }
.text-warning { color: #e3b341; }
.text-muted { color: #8b949e; }
.text-danger { color: #f85149; }

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}
.btn-primary { background: #238636; color: white; }
.btn-primary:hover:not(:disabled) { background: #2ea043; }
.btn-primary:disabled { background: #30363d; color: #6e7681; cursor: not-allowed; }
.w-100 { width: 100%; }

.staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.staff-card {
    background: #21262d;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 15px;
}

.staff-header {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.staff-avatar {
    width: 40px;
    height: 40px;
    background: #58a6ff;
    color: #0d1117;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.staff-info { flex: 1; overflow: hidden; }
.staff-name { font-weight: 600; color: #c9d1d9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-role { font-size: 0.8rem; color: #8b949e; }

.btn-icon.danger {
    background: none;
    border: none;
    color: #f85149;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0 5px;
    opacity: 0.7;
}
.btn-icon.danger:hover { opacity: 1; }

.staff-metrics {
    font-size: 0.85rem;
    color: #8b949e;
}

.metric { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
.progress-bar { flex: 1; height: 4px; background: #30363d; border-radius: 2px; overflow: hidden; }
.fill { height: 100%; background: #238636; }
.metric-text { font-family: monospace; margin-top: 5px; }

.empty-state { text-align: center; padding: 40px; color: #8b949e; border: 2px dashed #30363d; border-radius: 8px; }
</style>
