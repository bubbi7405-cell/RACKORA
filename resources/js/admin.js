import { createApp } from 'vue';
import { createPinia } from 'pinia';
import AdminApp from './AdminApp.vue';
import '../css/app.css';

// Create Vue app for Admin Panel
const app = createApp(AdminApp);

// Install Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Mount app
app.mount('#admin-app');
