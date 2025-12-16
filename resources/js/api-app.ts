import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { useEchoStore } from './stores/echo';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const authStore = useAuthStore();
authStore.checkAuth().then(() => {
    const echoStore = useEchoStore();
    if (authStore.isAuthenticated) {
        echoStore.initialize();
    }
    
    app.mount('#app');
});
