import { defineStore } from 'pinia';
import { ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useAuthStore } from './auth';

// Configure Pusher for Laravel Echo (Reverb compatible)
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo;
    }
}

window.Pusher = Pusher;

export const useEchoStore = defineStore('echo', () => {
    const echo = ref<Echo | null>(null);
    const channel = ref<any>(null);

    function initialize() {
        if (echo.value) {
            return; // Already initialized
        }

        const authStore = useAuthStore();
        if (!authStore.isAuthenticated || !authStore.user) {
            return;
        }

        // Initialize Laravel Echo with Reverb
        echo.value = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY || '',
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
            wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: `Bearer ${authStore.token}`,
                },
            },
        });

        // Subscribe to private user channel
        const userId = authStore.user.id;
        channel.value = echo.value.private(`user.${userId}`);

        // Listen for order matched events
        channel.value.listen('.order.matched', (data: any) => {
            // Emit custom event that components can listen to
            window.dispatchEvent(new CustomEvent('order-matched', { detail: data }));
        });
    }

    function disconnect() {
        if (channel.value) {
            channel.value.stopListening('.order.matched');
            const authStore = useAuthStore();
            if (authStore.user) {
                echo.value?.leave(`user.${authStore.user.id}`);
            }
            channel.value = null;
        }
        if (echo.value) {
            echo.value.disconnect();
            echo.value = null;
        }
    }

    return {
        echo,
        channel,
        initialize,
        disconnect,
    };
});
