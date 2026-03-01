import { ref, computed } from 'vue';
import { useUiStore } from '../stores/ui';
import { useInfrastructureStore } from '../stores/infrastructure';

/**
 * Drag and Drop Composable
 * Handles drag interactions for rack/server management
 */
export function useDragDrop() {
    const uiStore = useUiStore();
    const infraStore = useInfrastructureStore();

    const isDragging = computed(() => uiStore.isDragging);
    const draggedItem = computed(() => uiStore.draggedServer);

    function onDragStart(event, item) {
        if (!item) return;

        // Set drag image/effect
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.dropEffect = 'move';

        // Use a transparent image to avoid default ghost if desired, 
        // or let default ghost happen. 
        // For now, standard behavior.

        // Update global state
        uiStore.startDrag(item);
    }

    function onDragEnd(event) {
        uiStore.endDrag();
    }

    function onDragOver(event) {
        // Allow drop by preventing default
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    async function onDrop(event, targetRackId, targetSlot) {
        event.preventDefault();
        const item = uiStore.draggedServer;

        if (!item) return;

        // Handle dropping a server into a rack slot
        if (item.type === 'server_catalog_item') {
            // Check if slot is valid?
            // Delegate logic to infrastructure store
            return await infraStore.placeServer(
                targetRackId,
                item.serverType,
                item.modelKey,
                targetSlot
            );
        } else if (item.id) {
            // Moving existing server
            return await infraStore.moveServer(item.id, targetRackId, targetSlot);
        }

        uiStore.endDrag();
    }

    return {
        isDragging,
        draggedItem,
        onDragStart,
        onDragEnd,
        onDragOver,
        onDrop
    };
}
