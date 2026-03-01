import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useTooltipStore = defineStore('tooltip', () => {
    const visible = ref(false);
    const title = ref('');
    const content = ref('');
    const hint = ref('');
    const x = ref(0);
    const y = ref(0);
    const timeout = ref(null);

    function show(event, options) {
        if (timeout.value) clearTimeout(timeout.value);

        timeout.value = setTimeout(() => {
            title.value = options.title || '';
            content.value = options.content || '';
            hint.value = options.hint || '';

            // Position
            const rect = event.target.getBoundingClientRect();
            x.value = rect.left + rect.width / 2;
            y.value = rect.top;

            visible.value = true;
        }, 300); // Small delay to avoid flickering
    }

    function hide() {
        if (timeout.value) clearTimeout(timeout.value);
        visible.value = false;
    }

    return { visible, title, content, hint, x, y, show, hide };
});
