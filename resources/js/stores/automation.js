import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';

export const useAutomationStore = defineStore('automation', () => {
    const config = ref({
        auto_scaling: { enabled: false, unlockLevel: 15, cost: 50000, label: 'AUTO_SCALING', desc: 'Automatically deploy new instances when load exceeds 80%.' },
        auto_load_balancing: { enabled: false, unlockLevel: 20, cost: 150000, label: 'LOAD_BALANCING', desc: 'Optimize traffic distribution across active clusters.' },
        auto_hardware_repair: { enabled: false, unlockLevel: 25, cost: 250000, label: 'ROBOTIC_MAINTENANCE', desc: 'Automatic cleaning and basic repair of server racks.' },
        auto_contract_renewal: { enabled: false, unlockLevel: 30, cost: 500000, label: 'PREDICTIVE_RENEWAL', desc: 'AI-driven contract acceptance and legal compliance.' },
    });

    async function toggleAutomation(key) {
        const toast = useToastStore();
        try {
            const response = await api.post('/automation/toggle', { feature: key });
            if (response.success) {
                config.value[key].enabled = !config.value[key].enabled;
                toast.success(`${config.value[key].label} ${config.value[key].enabled ? 'ACTIVATED' : 'DEACTIVATED'}`);
                return true;
            }
        } catch (error) {
            toast.error('Automation toggle failed');
        }
        return false;
    }

    function applyState(data) {
        if (data.automation) {
            Object.assign(config.value, data.automation);
        }
    }

    return {
        config,
        toggleAutomation,
        applyState
    };
});
