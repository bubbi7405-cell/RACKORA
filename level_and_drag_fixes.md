# Level and Drag-and-Drop Fixes

## 1. Level Requirement Check
- **Issue:** Users were able to click component purchase buttons for higher-level items, leading to a "Level too low" API error because the backend correctly rejected the request.
- **Fix:** Added a visual indicator (`locked` class) and a click handler guard in `RightPanel.vue`. Now, if `player.economy.level < item.level_required`, the purchase button is disabled.

## 2. Drag-and-Drop TypeError
- **Issue:** Dragging a server into a rack sometimes caused a `TypeError: Cannot read properties of undefined (reading 'length')`. This is likely due to the `rack.slots` array being accessed in `AssemblyOverlay.vue` before it was fully populated.
- **Fix:** Added a explicit check `if (!rack || !rack.slots) return [];` in the `availableSlots` computed property in `AssemblyOverlay.vue`. This ensures the loop condition is safe even if rack data is momentarily incomplete.

These changes prevent both the client-side crash and the confusing API error message.
