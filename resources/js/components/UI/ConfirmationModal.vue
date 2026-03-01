<template>
    <div class="v2-modal-backdrop" @click.self="$emit('cancel')">
        <div class="v2-modal">
            <div class="v2-modal-header" :class="type">
                <h3>{{ title }}</h3>
            </div>
            <div class="v2-modal-body">
                <p>{{ message }}</p>
                <div class="warning-box" v-if="warning">
                    ⚠️ {{ warning }}
                </div>
            </div>
            <div class="v2-modal-actions">
                <button class="v2-btn is-ghost" @click="$emit('cancel')">CANCEL</button>
                <button class="v2-btn" :class="confirmClass" @click="$emit('confirm')">{{ confirmLabel }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, required: true },
    warning: { type: String, default: '' },
    confirmLabel: { type: String, default: 'CONFIRM' },
    type: { type: String, default: 'info' } // info, danger, warning
});

defineEmits(['confirm', 'cancel']);

const confirmClass = computed(() => {
    if (props.type === 'danger') return 'is-danger';
    if (props.type === 'warning') return 'is-warning';
    return 'is-primary';
});
</script>

<style scoped>
.v2-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.8);
    display: flex; align-items: center; justify-content: center; z-index: 2000;
    backdrop-filter: blur(4px);
}
.v2-modal {
    width: 400px;
    background: var(--color-panel-bg);
    border: var(--border-ui);
    box-shadow: 0 0 30px rgba(0,0,0,0.5);
    border-radius: 4px;
}
.v2-modal-header {
    padding: 16px;
    border-bottom: var(--border-dim);
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.v2-modal-header.danger { color: var(--color-danger); border-bottom-color: var(--color-danger); }
.v2-modal-header.warning { color: var(--color-warning); border-bottom-color: var(--color-warning); }

.v2-modal-body {
    padding: 24px;
    font-size: 0.9rem;
    color: var(--color-text-secondary);
    line-height: 1.5;
}
.warning-box {
    margin-top: 16px;
    padding: 12px;
    background: rgba(255, 50, 50, 0.1);
    border: 1px solid var(--color-danger);
    color: var(--color-text-highlight);
    font-size: 0.85rem;
    border-radius: 2px;
}
.v2-modal-actions {
    padding: 16px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: rgba(0,0,0,0.2);
    border-top: var(--border-dim);
}
.v2-btn {
    height: 36px;
    padding: 0 16px;
    border: none;
    background: var(--color-surface);
    color: var(--color-text-primary);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
}
.v2-btn:hover { background: var(--color-surface-hover); border-color: var(--color-primary); }
.v2-btn.is-primary { background: var(--color-primary); color: #000; }
.v2-btn.is-primary:hover { background: var(--color-primary-hover); }
.v2-btn.is-danger { background: var(--color-danger); color: #fff; }
.v2-btn.is-danger:hover { background: #ff0000; }
.v2-btn.is-ghost { background: transparent; border: 1px solid var(--border-dim); }
.v2-btn.is-ghost:hover { border-color: var(--color-text-secondary); }
</style>
