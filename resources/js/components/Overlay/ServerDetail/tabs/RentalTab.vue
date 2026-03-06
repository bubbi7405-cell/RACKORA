<template>
    <div class="tab-content rental-tab provision-lab">


        <div v-if="server.tenantId" class="v3-rental-status">
            <div class="v3-info-box blue">
                <label>AKTIVER_MIETVERTRAG</label>
                <p v-if="isOwner">
                    Diese Einheit ist derzeit an einen anderen Executive vermietet.
                </p>
                <p v-else>
                    Dies ist eine geleaste Einheit von einem externen Provider.
                </p>
                <div class="v3-spec-grid">
                    <div class="v3-spec"><span>Status</span> <strong class="text-success">AKTIV</strong></div>
                    <div class="v3-spec"><span>Vertrags-ID</span> <strong class="mono">#RENT-{{ server.id.substring(0,
                        6)
                            }}</strong></div>
                </div>
                <div class="v3-actions" style="margin-top: 20px;">
                    <button @click="terminateRental" class="btn-danger-v3">MIETVERHÄLTNIS_KÜNDIGEN</button>
                </div>
            </div>
        </div>

        <div v-else class="v3-rental-empty-container">
            <div class="v3-rental-empty">
                <div class="v3-info-box">
                    <label>NODE_BEREITSTELLUNG</label>
                    <p>Diese Einheit ist derzeit im Eigenbesitz und generiert keinen externen Mietumsatz.</p>
                </div>
            </div>

            <div class="v3-rental-form">
                <div class="v3-info-box warning">
                    <label>PROVISION_FOR_MARKET</label>
                    <div class="info-group">
                        <p class="setting-desc">List this hardware on the global exchange to earn passive rental income.
                        </p>

                        <div v-if="server.activeOrdersCount > 0" class="hw-alert">
                            ⚠️ DEPLOYMENT_ERROR: Unit has active client workloads. Terminate orders before listing.
                        </div>
                        <div v-else>
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>HOURLY_RATE ($)</label>
                                <input type="number" v-model="localPrice" step="0.01" class="v3-input"
                                    placeholder="0.00">
                                <small class="hint">Recommended: ${{ (server.purchaseCost / 1000).toFixed(2) }} - ${{
                                    (server.purchaseCost / 500).toFixed(2) }} /h</small>
                            </div>

                            <button @click="handleList" :disabled="processing || !localPrice"
                                class="btn-primary-v3 w-100">
                                {{ processing ? 'LISTING...' : 'INITIALIZE_LISTING' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="showTerminateConfirm" title="VERTRAGS_KÜNDIGUNG"
            message="Sind Sie sicher, dass Sie diesen Mietvertrag kündigen wollen?"
            warning="Der Server wird sofort aus dem Listing entfernt bzw. der Zugang wird entzogen."
            confirm-label="VERTRAG_BEENDEN" type="danger" @confirm="executeTerminate"
            @cancel="showTerminateConfirm = false" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useMultiplayerStore } from '../../../../stores/multiplayer';
import { useAuthStore } from '../../../../stores/auth';
import ConfirmationModal from '../../../UI/ConfirmationModal.vue';

const showTerminateConfirm = ref(false);

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

const multiplayerStore = useMultiplayerStore();
const authStore = useAuthStore();

const localPrice = ref(0);
const isOwner = computed(() => props.server.rack?.room?.user_id === authStore.user?.id);

const handleList = async () => {
    if (!localPrice.value || props.processing) return;
    emit('processing-start');
    try {
        const success = await multiplayerStore.listServerForRent(props.server.id, localPrice.value);
        if (success) {
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const terminateRental = () => {
    if (props.processing) return;
    showTerminateConfirm.value = true;
};

const executeTerminate = async () => {
    showTerminateConfirm.value = false;
    emit('processing-start');
    try {
        const rental = [...multiplayerStore.myRentalsAsProvider, ...multiplayerStore.myRentalsAsTenant]
            .find(r => r.server_id === props.server.id);

        if (rental) {
            await multiplayerStore.terminateRental(rental.id);
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};
</script>
