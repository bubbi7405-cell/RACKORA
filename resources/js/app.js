import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import '../css/app.css';
import './services/ThemeManager';

// Create Vue app
const app = createApp(App);

// Install Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Mount app
app.mount('#app');
