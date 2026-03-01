import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useToastStore = defineStore('toast', () => {
    const toasts = ref([]);
    let nextId = 0;

    function addToast(message, type = 'info', duration = 4000) {
        const id = nextId++;

        toasts.value.push({
            id,
            message,
            type,
        });

        // Auto-remove after duration
        setTimeout(() => {
            removeToast(id);
        }, duration);

        return id;
    }

    function removeToast(id) {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    }

    // Convenience methods
    function success(message, duration) {
        return addToast(message, 'success', duration);
    }

    function error(message, duration = 6000) {
        return addToast(message, 'error', duration);
    }

    function warning(message, duration = 5000) {
        return addToast(message, 'warning', duration);
    }

    function info(message, duration) {
        return addToast(message, 'info', duration);
    }

    function achievement(message, duration = 8000) {
        return addToast(message, 'achievement', duration);
    }

    return {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
    };
});
