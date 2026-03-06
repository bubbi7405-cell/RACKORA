<template>
    <div class="tab-content logs-tab provision-lab">

        <div class="v3-info-box no-padding">
            <div class="terminal">
                <div v-for="(log, i) in logs" :key="i" class="log-line" :class="log.level">
                    <span class="ts mono">[{{ log.timestamp.substring(11, 19) }}]</span>
                    <span class="msg">{{ log.message }}</span>
                </div>
                <div v-if="logs.length === 0" class="log-empty">Initiale Sequenz wird geladen...</div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    logs: { type: Array, required: true }
});
</script>
