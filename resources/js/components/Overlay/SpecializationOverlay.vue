<template>
    <div class="v2-overlay-backdrop" @click.self="$emit('close')">
        <div class="v2-overlay-content specialization-overlay animate-slide-up">
            <header class="v2-overlay-header">
                <div class="v2-title l1-priority">FACILITY_SPECIALIZATION // [NODE_OPTIMIZATION]</div>
                <button class="v2-close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="v2-overlay-scroll">
                <p class="v2-desc l3-priority">
                    Select a core focus for <span class="text-white">{{ room?.name }}</span>. 
                    Specialization provides powerful passive bonuses but may increase operating costs or risk.
                </p>

                <div class="specs-grid">
                    <div 
                        v-for="spec in infraStore.specializations.available" 
                        :key="spec.id" 
                        class="spec-card"
                        :class="{ 'is-active': room?.specialization === spec.id }"
                        @click="selectSpec(spec.id)"
                    >
                        <div class="spec-icon">{{ spec.icon }}</div>
                        <div class="spec-info">
                            <div class="spec-label l2-priority">{{ spec.label }}</div>
                            <div class="spec-bonus l1-priority">{{ spec.bonus }}</div>
                        </div>
                        <div v-if="room?.specialization === spec.id" class="active-badge">ACTIVE_PROTOCOL</div>
                    </div>
                </div>

                <div class="v2-divider"></div>

                <div class="spec-actions">
                    <button class="v2-cmd-btn secondary" @click="$emit('close')">ABORT_AUTHORIZATION</button>
                    <button 
                        class="v2-cmd-btn l1-priority" 
                        :disabled="!selectedId || selectedId === room?.specialization"
                        @click="confirmSpec"
                    >
                        RECONFIGURE_NODE
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useInfrastructureStore } from '../../stores/infrastructure';

const props = defineProps({
    room: Object
});

const emit = defineEmits(['close']);
const infraStore = useInfrastructureStore();

const selectedId = ref(props.room?.specialization || null);

const selectSpec = (id) => {
    selectedId.value = id;
};

const confirmSpec = async () => {
    if (!selectedId.value) return;
    const success = await infraStore.setSpecialization(props.room.id, selectedId.value);
    if (success) {
        emit('close');
    }
};
</script>

<style scoped>
.specialization-overlay {
    max-width: 600px;
}

.specs-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 20px;
}

.spec-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 20px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.spec-card:hover {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.1);
}

.spec-card.is-active {
    background: rgba(58, 134, 255, 0.05);
    border-color: var(--color-accent);
}

.spec-icon {
    font-size: 2rem;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
}

.spec-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.spec-label {
    font-size: 0.8rem;
    font-weight: 800;
}

.spec-bonus {
    font-size: 0.7rem;
    color: var(--color-success);
    font-family: monospace;
}

.active-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--color-accent);
    background: rgba(58, 134, 255, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.1em;
}

.spec-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
}
</style>
