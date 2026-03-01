import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../utils/api';

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref(null);
    const token = ref(localStorage.getItem('game_token') || null);
    const isLoading = ref(true);

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isAdmin = computed(() => user.value?.is_admin === true);

    // Actions
    async function checkAuth() {
        isLoading.value = true;

        if (!token.value) {
            isLoading.value = false;
            return false;
        }

        try {
            api.setToken(token.value);
            const response = await api.get('/user');

            if (response.success) {
                user.value = response.user;
                return true;
            } else {
                logout();
                return false;
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            logout();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function login(email, password) {
        // Do not set global isLoading to prevent AuthScreen unmount
        try {
            const response = await api.post('/login', { email, password });

            if (response.success) {
                token.value = response.token;
                user.value = response.user;
                localStorage.setItem('game_token', response.token);
                api.setToken(response.token);
                return { success: true };
            } else {
                return { success: false, error: response.error };
            }
        } catch (error) {
            return { success: false, error: error.message || 'Login failed' };
        }
    }

    async function register(name, email, password, passwordConfirmation) {
        // Do not set global isLoading to prevent AuthScreen unmount
        try {
            const response = await api.post('/register', {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });

            if (response.success) {
                token.value = response.token;
                user.value = response.user;
                localStorage.setItem('game_token', response.token);
                api.setToken(response.token);
                return { success: true };
            } else {
                return { success: false, error: response.error };
            }
        } catch (error) {
            return { success: false, error: error.message || 'Registration failed' };
        }
    }

    function logout() {
        user.value = null;
        token.value = null;
        localStorage.removeItem('game_token');
        api.setToken(null);
    }

    return {
        // State
        user,
        token,
        isLoading,
        // Getters
        isAuthenticated,
        isAdmin,
        // Actions
        checkAuth,
        login,
        register,
        logout,
    };
});
