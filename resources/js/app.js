import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import '../css/app.css';
import '../css/v2-ui.css';
import './services/ThemeManager';

// Create Vue app
const app = createApp(App);

// Install Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Register Tooltip Directive
import { useTooltipStore } from './stores/tooltip';
app.directive('tooltip', {
    mounted(el, binding) {
        el._tooltipValue = binding.value;
        const tooltipStore = useTooltipStore(); // Use imported store inside

        // Store listeners to remove them later
        el._tooltipEnter = (e) => {
            const val = el._tooltipValue;
            if (!val) return;
            const options = typeof val === 'string' ? { content: val } : val;
            tooltipStore.show(e, options);
        };
        el._tooltipLeave = () => {
            tooltipStore.hide();
        };

        el.addEventListener('mouseenter', el._tooltipEnter);
        el.addEventListener('mouseleave', el._tooltipLeave);
    },
    updated(el, binding) {
        el._tooltipValue = binding.value;
    },
    unmounted(el) {
        if (el._tooltipEnter) el.removeEventListener('mouseenter', el._tooltipEnter);
        if (el._tooltipLeave) el.removeEventListener('mouseleave', el._tooltipLeave);
    }
});

// Mount app
app.mount('#app');

// Handle Audio Context Autoplay Policy
import SoundManager from './services/SoundManager';
const resumeAudio = () => {
    SoundManager.resume();
    window.removeEventListener('click', resumeAudio);
    window.removeEventListener('keydown', resumeAudio);
};
window.addEventListener('click', resumeAudio);
window.addEventListener('keydown', resumeAudio);
