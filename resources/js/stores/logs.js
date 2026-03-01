import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useLogStore = defineStore('logs', () => {
    const logs = ref([]);
    const maxLogs = 50;

    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString([], { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        logs.value.unshift({
            id: Date.now() + Math.random(),
            timestamp,
            message,
            type
        });

        if (logs.value.length > maxLogs) {
            logs.value.pop();
        }
    }

    function clearLogs() {
        logs.value = [];
    }

    return {
        logs,
        addLog,
        clearLogs
    };
});
