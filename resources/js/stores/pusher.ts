import { defineStore } from 'pinia';
import { ref } from 'vue';
import Pusher from 'pusher-js';
import { useAuthStore } from './auth';
import api from '../api/client';

export const usePusherStore = defineStore('pusher', () => {
    const pusher = ref<Pusher | null>(null);
    const channel = ref<any>(null);

    function initialize() {
        if (pusher.value) {
            return; // Already initialized
        }

        const authStore = useAuthStore();
        if (!authStore.isAuthenticated || !authStore.user) {
            return;
        }

        // Initialize Pusher
        pusher.value = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY || '', {
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: `Bearer ${authStore.token}`,
                },
            },
        });

        // Subscribe to private user channel
        const userId = authStore.user.id;
        channel.value = pusher.value.subscribe(`private-user.${userId}`);

        // Listen for order matched events
        channel.value.bind('order.matched', (data: any) => {
            // Emit custom event that components can listen to
            window.dispatchEvent(new CustomEvent('order-matched', { detail: data }));
        });
    }

    function disconnect() {
        if (channel.value) {
            channel.value.unbind_all();
            channel.value.unsubscribe();
            channel.value = null;
        }
        if (pusher.value) {
            pusher.value.disconnect();
            pusher.value = null;
        }
    }

    return {
        pusher,
        channel,
        initialize,
        disconnect,
    };
});
