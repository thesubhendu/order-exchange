<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <nav class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <router-link to="/" class="flex items-center px-2 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            Orders & Wallet
                        </router-link>
                        <router-link to="/orders/new" class="flex items-center px-2 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            New Order
                        </router-link>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-700 dark:text-gray-300 mr-4">{{ authStore.user?.name }}</span>
                        <button
                            @click="handleLogout"
                            class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Wallet Overview -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Wallet Overview</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                        <p class="text-sm text-gray-600 dark:text-gray-400">USD Balance</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            ${{ profile?.usd_balance || '0.00' }}
                        </p>
                    </div>
                    
                    <div v-for="asset in profile?.assets" :key="asset.symbol" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ asset.symbol }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ asset.available_amount }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Locked: {{ asset.locked_amount }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Orderbook -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Orderbook</h2>
                        <select
                            v-model="selectedSymbol"
                            @change="loadOrderbook"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">All Symbols</option>
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                        </select>
                    </div>

                    <div class="space-y-4">
                        <!-- Buy Orders -->
                        <div>
                            <h3 class="text-sm font-semibold text-green-600 dark:text-green-400 mb-2">Buy Orders</h3>
                            <div class="space-y-1 max-h-64 overflow-y-auto">
                                <div
                                    v-for="order in buyOrders"
                                    :key="order.id"
                                    class="flex justify-between text-sm p-2 bg-green-50 dark:bg-green-900/20 rounded"
                                >
                                    <span class="text-gray-700 dark:text-gray-300">{{ order.amount }}</span>
                                    <span class="text-gray-700 dark:text-gray-300">@ ${{ order.price }}</span>
                                </div>
                                <div v-if="buyOrders.length === 0" class="text-gray-500 text-sm text-center py-4">
                                    No buy orders
                                </div>
                            </div>
                        </div>

                        <!-- Sell Orders -->
                        <div>
                            <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 mb-2">Sell Orders</h3>
                            <div class="space-y-1 max-h-64 overflow-y-auto">
                                <div
                                    v-for="order in sellOrders"
                                    :key="order.id"
                                    class="flex justify-between text-sm p-2 bg-red-50 dark:bg-red-900/20 rounded"
                                >
                                    <span class="text-gray-700 dark:text-gray-300">{{ order.amount }}</span>
                                    <span class="text-gray-700 dark:text-gray-300">@ ${{ order.price }}</span>
                                </div>
                                <div v-if="sellOrders.length === 0" class="text-gray-500 text-sm text-center py-4">
                                    No sell orders
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Orders -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">My Orders</h2>

                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div
                            v-for="order in myOrders"
                            :key="order.id"
                            class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-md"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            order.side === 'buy' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'px-2 py-1 rounded text-xs font-semibold'
                                        ]"
                                    >
                                        {{ order.side.toUpperCase() }}
                                    </span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ order.symbol }}</span>
                                    <span
                                        :class="[
                                            order.status === 2 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                            order.status === 3 ? 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200' :
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'px-2 py-1 rounded text-xs font-semibold'
                                        ]"
                                    >
                                        {{ order.status_label }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ order.amount }} @ ${{ order.price }}
                                </div>
                            </div>
                            <button
                                v-if="order.status === 1"
                                @click="cancelOrder(order.id)"
                                :disabled="cancellingOrderId === order.id"
                                class="ml-4 px-3 py-1 text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <span v-if="cancellingOrderId === order.id">Cancelling...</span>
                                <span v-else>Cancel</span>
                            </button>
                        </div>
                        <div v-if="myOrders.length === 0" class="text-gray-500 text-sm text-center py-4">
                            No orders yet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useEchoStore } from '../stores/echo';
import api from '../api/client';

const router = useRouter();
const authStore = useAuthStore();
const echoStore = useEchoStore();

const profile = ref<any>(null);
const orderbook = ref<any[]>([]);
const myOrders = ref<any[]>([]);
const selectedSymbol = ref('');
const cancellingOrderId = ref<number | null>(null);

const buyOrders = computed(() => {
    return orderbook.value.filter(o => o.side === 'buy').sort((a, b) => b.price - a.price);
});

const sellOrders = computed(() => {
    return orderbook.value.filter(o => o.side === 'sell').sort((a, b) => a.price - b.price);
});

async function loadProfile() {
    try {
        const response = await api.get('/profile');
        profile.value = response.data.user;
    } catch (error) {
        console.error('Failed to load profile:', error);
    }
}

async function loadOrderbook() {
    try {
        const params = selectedSymbol.value ? { symbol: selectedSymbol.value } : {};
        const response = await api.get('/orders', { params });
        orderbook.value = response.data.orders || [];
    } catch (error) {
        console.error('Failed to load orderbook:', error);
    }
}

async function loadMyOrders() {
    try {
        const response = await api.get('/orders/my');
        myOrders.value = response.data.orders || [];
    } catch (error) {
        console.error('Failed to load my orders:', error);
    }
}

async function cancelOrder(orderId: number) {
    if (!confirm('Are you sure you want to cancel this order?')) {
        return;
    }

    cancellingOrderId.value = orderId;
    try {
        await api.post(`/orders/${orderId}/cancel`);
        // Real-time updates will handle the refresh via events
        await Promise.all([loadProfile(), loadOrderbook(), loadMyOrders()]);
    } catch (error: any) {
        alert(error.response?.data?.error || 'Failed to cancel order');
    } finally {
        cancellingOrderId.value = null;
    }
}

function handleOrderMatched(event: CustomEvent) {
    console.log('handleOrderMatched called with data:', event.detail);
    // Refresh data when order is matched
    loadProfile();
    loadOrderbook();
    loadMyOrders();
}

function handleOrderCancelled(event: CustomEvent) {
    // Refresh data when order is cancelled
    loadProfile();
    loadOrderbook();
    loadMyOrders();
}

function handleOrderbookUpdated(event: CustomEvent) {
    // Refresh orderbook when it's updated
    loadOrderbook();
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}

onMounted(async () => {
    // Initialize Echo store for real-time updates
    echoStore.initialize();
    
    // Load initial data
    await Promise.all([loadProfile(), loadOrderbook(), loadMyOrders()]);
    
    // Listen for real-time events
    window.addEventListener('order-matched', handleOrderMatched as EventListener);
    window.addEventListener('order-cancelled', handleOrderCancelled as EventListener);
    window.addEventListener('orderbook-updated', handleOrderbookUpdated as EventListener);
});

onUnmounted(() => {
    // Remove event listeners
    window.removeEventListener('order-matched', handleOrderMatched as EventListener);
    window.removeEventListener('order-cancelled', handleOrderCancelled as EventListener);
    window.removeEventListener('orderbook-updated', handleOrderbookUpdated as EventListener);
});
</script>
