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

        <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Place Limit Order</h2>

                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div>
                        <label for="symbol" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Symbol
                        </label>
                        <select
                            id="symbol"
                            v-model="form.symbol"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">Select Symbol</option>
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                        </select>
                    </div>

                    <div>
                        <label for="side" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Side
                        </label>
                        <select
                            id="side"
                            v-model="form.side"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">Select Side</option>
                            <option value="buy">Buy</option>
                            <option value="sell">Sell</option>
                        </select>
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Price (USD)
                        </label>
                        <input
                            id="price"
                            v-model.number="form.price"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            placeholder="0.00"
                        />
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount
                        </label>
                        <input
                            id="amount"
                            v-model.number="form.amount"
                            type="number"
                            step="0.00000001"
                            min="0.01"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            placeholder="0.00"
                        />
                    </div>

                    <div v-if="form.price && form.amount" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Total Value: <span class="font-semibold">${{ (form.price * form.amount).toFixed(2) }}</span>
                        </p>
                    </div>

                    <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>
                    <div v-if="success" class="text-green-600 text-sm">{{ success }}</div>

                    <div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            <span v-if="loading">Placing Order...</span>
                            <span v-else>Place Order</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/client';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
    symbol: '',
    side: '',
    price: null as number | null,
    amount: null as number | null,
});

const loading = ref(false);
const error = ref('');
const success = ref('');

async function handleSubmit() {
    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await api.post('/orders', form.value);
        success.value = 'Order placed successfully!';
        
        // Clear form
        form.value = {
            symbol: '',
            side: '',
            price: null,
            amount: null,
        };

        setTimeout(() => {
            router.push('/');
        }, 500);
    } catch (err: any) {
        error.value = err.response?.data?.error || err.response?.data?.message || 'Failed to place order';
    } finally {
        loading.value = false;
    }
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}
</script>
