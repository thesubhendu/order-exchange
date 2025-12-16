import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { usePusherStore } from './stores/pusher';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const authStore = useAuthStore();
authStore.checkAuth().then(() => {
    const pusherStore = usePusherStore();
    if (authStore.isAuthenticated) {
        pusherStore.initialize();
    }
    
    app.mount('#app');
});
