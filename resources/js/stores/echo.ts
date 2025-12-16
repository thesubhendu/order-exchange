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
    const userChannel = ref<any>(null);
    const orderbookChannel = ref<any>(null);

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
        userChannel.value = echo.value.private(`user.${userId}`);

        // Listen for order matched events
        userChannel.value.listen('.order.matched', (data: any) => {
            window.dispatchEvent(new CustomEvent('order-matched', { detail: data }));
        });

        // Listen for order cancelled events
        userChannel.value.listen('.order.cancelled', (data: any) => {
            window.dispatchEvent(new CustomEvent('order-cancelled', { detail: data }));
        });

        // Subscribe to public orderbook channel for real-time updates
        orderbookChannel.value = echo.value.channel('orderbook');

        // Listen for order created events (affects orderbook)
        orderbookChannel.value.listen('.order.created', (data: any) => {
            window.dispatchEvent(new CustomEvent('orderbook-updated', { detail: data }));
        });

        // Listen for order cancelled events (affects orderbook)
        orderbookChannel.value.listen('.order.cancelled', (data: any) => {
            window.dispatchEvent(new CustomEvent('orderbook-updated', { detail: data }));
        });

        // Listen for order matched events (affects orderbook)
        orderbookChannel.value.listen('.order.matched', (data: any) => {
            window.dispatchEvent(new CustomEvent('orderbook-updated', { detail: data }));
        });
    }

    function disconnect() {
        if (userChannel.value) {
            userChannel.value.stopListening('.order.matched');
            userChannel.value.stopListening('.order.cancelled');
            const authStore = useAuthStore();
            if (authStore.user) {
                echo.value?.leave(`user.${authStore.user.id}`);
            }
            userChannel.value = null;
        }
        if (orderbookChannel.value) {
            orderbookChannel.value.stopListening('.order.created');
            orderbookChannel.value.stopListening('.order.cancelled');
            orderbookChannel.value.stopListening('.order.matched');
            echo.value?.leave('orderbook');
            orderbookChannel.value = null;
        }
        if (echo.value) {
            echo.value.disconnect();
            echo.value = null;
        }
    }

    return {
        echo,
        userChannel,
        orderbookChannel,
        initialize,
        disconnect,
    };
});
