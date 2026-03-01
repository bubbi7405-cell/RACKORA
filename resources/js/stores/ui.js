import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

/**
 * UI Store
 * Owns: Selection state, drag/drop state, panel visibility
 * Pure client-side state — no API calls.
 */
export const useUiStore = defineStore('ui', () => {
    // ─── Selection State ────────────────────────────────
    const selectedRoomId = ref(null);
    const selectedRackId = ref(null);
    const selectedServerId = ref(null);
    const selectedOrder = ref(null);

    // ─── Drag & Drop ────────────────────────────────────
    const isDragging = ref(false);
    const draggedServer = ref(null);

    // ─── Getters ────────────────────────────────────────

    const hasSelection = computed(() =>
        !!selectedRoomId.value || !!selectedRackId.value || !!selectedServerId.value
    );

    // ─── Actions ────────────────────────────────────────

    function selectRoom(roomId) {
        selectedRoomId.value = roomId;
        selectedRackId.value = null;
        selectedServerId.value = null;
    }

    function selectRack(rackId) {
        selectedRackId.value = rackId;
        selectedServerId.value = null;
        isDragging.value = false;
        draggedServer.value = null;
    }

    function selectServer(serverId) {
        selectedServerId.value = serverId;
    }

    function selectOrder(order) {
        selectedOrder.value = order;
    }

    function clearSelection() {
        selectedRoomId.value = null;
        selectedRackId.value = null;
        selectedServerId.value = null;
        selectedOrder.value = null;
    }

    function startDrag(item) {
        isDragging.value = true;
        draggedServer.value = item;
    }

    function endDrag() {
        isDragging.value = false;
        draggedServer.value = null;
    }

    /**
     * Update the selected order reference when order data changes.
     * Called during applyGameState to keep selected order in sync.
     */
    function syncSelectedOrder(pendingOrders) {
        if (selectedOrder.value && selectedOrder.value.id && pendingOrders) {
            const list = Array.isArray(pendingOrders) ? pendingOrders : [];
            const updated = list.find(o => o && o.id === selectedOrder.value.id);
            if (updated) {
                selectedOrder.value = updated;
            } else {
                selectedOrder.value = null;
            }
        }
    }

    /**
     * Auto-select the first room if none is selected.
     * Called during applyGameState when rooms data arrives.
     */
    function autoSelectFirstRoom(roomIds) {
        if (!selectedRoomId.value && roomIds.length > 0) {
            selectedRoomId.value = roomIds[0];
        }
    }

    // ─── Visibility State ───────────────────────────────
    const showLogTicker = ref(true);

    // ─── Attack State ──────────────────────────────────
    const activeAttack = ref(null);

    function triggerAttackOverlay(data) {
        activeAttack.value = data;
        // Auto-clear after 10 seconds
        setTimeout(() => {
            if (activeAttack.value && activeAttack.value.id === data.id) {
                activeAttack.value = null;
            }
        }, 10000);
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        selectedRoomId,
        selectedRackId,
        selectedServerId,
        selectedOrder,
        isDragging,
        draggedServer,
        activeAttack,
        showLogTicker,
        // Getters
        hasSelection,
        // Actions
        selectRoom,
        selectRack,
        selectServer,
        selectOrder,
        clearSelection,
        startDrag,
        endDrag,
        syncSelectedOrder,
        autoSelectFirstRoom,
        triggerAttackOverlay
    };
});
