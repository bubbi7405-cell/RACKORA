import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';
import SoundManager from '../services/SoundManager';

/**
 * Employees Store
 * Owns: Employee list, available types, skill trees
 * Actions: load, hire, fire, unlock perks
 */
export const useEmployeesStore = defineStore('employees', () => {
    // ─── State ──────────────────────────────────────────
    const isLoading = ref(false);
    const employees = ref([]);
    const availableEmployeeTypes = ref({});
    const skillTrees = ref({});
    const activeBonuses = ref({
        hiring_cost_reduction: 0
    });

    // ─── Getters ────────────────────────────────────────

    const employeeCount = computed(() => employees.value.length);

    const employeesByType = computed(() => {
        const grouped = {};
        for (const emp of employees.value) {
            const type = emp.type || 'unknown';
            if (!grouped[type]) grouped[type] = [];
            grouped[type].push(emp);
        }
        return grouped;
    });

    const totalSalary = computed(() =>
        employees.value.reduce((sum, emp) => sum + (emp.salary || 0), 0)
    );

    // ─── Actions ────────────────────────────────────────

    /**
     * Load employees, available types, and skill trees from API
     */
    async function loadEmployees() {
        try {
            const response = await api.get('/employees');
            if (response.success) {
                employees.value = response.employees;
                availableEmployeeTypes.value = response.available_types;
                skillTrees.value = response.skill_trees || {};
                if (response.active_bonuses) {
                    activeBonuses.value = response.active_bonuses;
                }
            }
        } catch (error) {
            console.error('Failed to load employees', error);
        }
    }

    /**
     * Hire a new employee of specific type
     */
    async function hireEmployee(type) {
        isLoading.value = true;
        try {
            const response = await api.post('/employees/hire', { type });
            if (response.success) {
                useToastStore().success(response.message || 'Employee hired!');
                SoundManager.playSuccess();
                await loadEmployees();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Hire failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fire an employee by ID
     */
    async function fireEmployee(id) {
        if (!confirm('Are you sure you want to fire this employee?')) return;
        isLoading.value = true;
        try {
            const response = await api.post(`/employees/${id}/fire`);
            if (response.success) {
                useToastStore().success(response.message || 'Employee fired.');
                await loadEmployees();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Fire failed');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Unlock a skill perk for an employee
     */
    async function unlockPerk(employeeId, perkId) {
        isLoading.value = true;
        try {
            const response = await api.post('/employees/unlock-perk', {
                employee_id: employeeId,
                perk_id: perkId
            });

            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                await loadEmployees();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Unlock failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * FEATURE 204: Respec Talent points
     */
    async function respec(employeeId) {
        if (!confirm('Möchten Sie alle Talente dieses Mitarbeiters zurücksetzen? Dies kostet eine Gebühr basierend auf seinem Level.')) return;

        isLoading.value = true;
        try {
            const response = await api.post('/employees/respec', {
                employee_id: employeeId
            });

            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                await loadEmployees();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Respec fehlgeschlagen');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * FEATURE 284: Send employee on sabbatical
     */
    async function sendOnSabbatical(employeeId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/employees/${employeeId}/sabbatical`);
            if (response.success) {
                useToastStore().success(response.message || 'Sabbatical gestartet!');
                SoundManager.playSuccess();
                await loadEmployees();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Sabbatical fehlgeschlagen');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        isLoading,
        employees,
        availableEmployeeTypes,
        skillTrees,
        activeBonuses,
        // Getters
        employeeCount,
        employeesByType,
        totalSalary,
        // Actions
        loadEmployees,
        hireEmployee,
        fireEmployee,
        unlockPerk,
        respec,
        sendOnSabbatical,
    };
});
