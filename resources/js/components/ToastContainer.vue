<template>
    <div class="toast-container">
        <TransitionGroup name="toast">
            <div 
                v-for="toast in toasts" 
                :key="toast.id"
                :class="['toast', `toast--${toast.type}`]"
            >
                <div class="toast__icon">
                    <svg v-if="toast.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <svg v-else-if="toast.type === 'error'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <svg v-else-if="toast.type === 'warning'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                </div>
                <span class="toast__message">{{ toast.message }}</span>
                <button class="toast__close" @click="toastStore.removeToast(toast.id)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useToastStore } from '../stores/toast';
import { storeToRefs } from 'pinia';

const toastStore = useToastStore();
const { toasts } = storeToRefs(toastStore);
</script>

<style scoped>
.toast-container {
    position: fixed;
    top: 70px;
    right: 1rem;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.toast__close {
    width: 16px;
    height: 16px;
    opacity: 0.5;
    transition: opacity 0.15s;
}

.toast__close:hover {
    opacity: 1;
}

.toast__close svg {
    width: 100%;
    height: 100%;
}

.toast--success .toast__icon { color: var(--color-success); }
.toast--error .toast__icon { color: var(--color-danger); }
.toast--warning .toast__icon { color: var(--color-warning); }
.toast--info .toast__icon { color: var(--color-info); }

/* Transition animations */
.toast-enter-active {
    animation: toast-slide-in 0.3s ease;
}

.toast-leave-active {
    animation: toast-slide-out 0.3s ease;
}

@keyframes toast-slide-in {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes toast-slide-out {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}
</style>
