import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api/client';

interface User {
    id: number;
    name: string;
    email: string;
    balance?: string;
}

interface LoginCredentials {
    email: string;
    password: string;
}

interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(null);

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    async function login(credentials: LoginCredentials) {
        try {
            const response = await api.post('/login', credentials);
            const { user: userData, token: authToken } = response.data;
            
            token.value = authToken;
            user.value = userData;
            
            localStorage.setItem('auth_token', authToken);
            localStorage.setItem('user', JSON.stringify(userData));
            
            return { success: true };
        } catch (error: any) {
            return {
                success: false,
                error: error.response?.data?.message || 'Login failed',
            };
        }
    }

    async function register(data: RegisterData) {
        try {
            const response = await api.post('/register', data);
            const { user: userData, token: authToken } = response.data;
            
            token.value = authToken;
            user.value = userData;
            
            localStorage.setItem('auth_token', authToken);
            localStorage.setItem('user', JSON.stringify(userData));
            
            return { success: true };
        } catch (error: any) {
            return {
                success: false,
                error: error.response?.data?.message || 'Registration failed',
            };
        }
    }

    async function logout() {
        try {
            await api.post('/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
        }
    }

    async function checkAuth() {
        const storedToken = localStorage.getItem('auth_token');
        const storedUser = localStorage.getItem('user');
        
        if (storedToken && storedUser) {
            token.value = storedToken;
            user.value = JSON.parse(storedUser);
            
            // Verify token is still valid by fetching profile
            try {
                const response = await api.get('/profile');
                user.value = response.data.user;
                localStorage.setItem('user', JSON.stringify(user.value));
            } catch (error) {
                // Token invalid, clear auth
                token.value = null;
                user.value = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user');
            }
        }
    }

    return {
        user,
        token,
        isAuthenticated,
        login,
        register,
        logout,
        checkAuth,
    };
});
