<template>
    <div class="diagnostic-task-wrapper">
        <DiagnosticPuzzle 
            v-if="type === 'signal'"
            @complete="$emit('complete')"
            @fail="$emit('fail', $event)"
        />
        <DiagnosticLogs 
            v-else-if="type === 'logs'"
            @complete="$emit('complete')"
            @fail="$emit('fail', $event)"
        />
        <HardwareProbe 
            v-else-if="type === 'probe'"
            @complete="$emit('complete')"
            @fail="$emit('fail', $event)"
        />
        <div v-else class="error">
            UNKNOWN_DIAGNOSTIC_TYPE: {{ type }}
        </div>
    </div>
</template>

<script setup>
import DiagnosticPuzzle from './DiagnosticPuzzle.vue';
import DiagnosticLogs from './DiagnosticLogs.vue';
import HardwareProbe from './HardwareProbe.vue';

const props = defineProps({
    type: { type: String, required: true },
    hint: { type: String, default: '' }
});

const emit = defineEmits(['complete', 'fail']);
</script>

<style scoped>
.diagnostic-task-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}
.error {
    color: var(--v3-danger);
    font-family: var(--font-mono);
    font-weight: 800;
}
</style>
