<template>
    <div class="auth-screen">
        <div class="auth-card">
            <h1 class="auth-card__title">Server Tycoon</h1>
            <p class="auth-card__subtitle">
                {{ isLogin ? 'Welcome back, operator.' : 'Begin your hosting empire.' }}
            </p>

            <form @submit.prevent="handleSubmit">
                <!-- Name field (register only) -->
                <div v-if="!isLogin" class="form-group">
                    <label class="form-label">Operator Name</label>
                    <input 
                        v-model="form.name"
                        type="text" 
                        class="form-input" 
                        placeholder="Enter your name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input 
                        v-model="form.email"
                        type="email" 
                        class="form-input" 
                        placeholder="operator@example.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input 
                        v-model="form.password"
                        type="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                    >
                </div>

                <!-- Confirm password (register only) -->
                <div v-if="!isLogin" class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input 
                        v-model="form.passwordConfirmation"
                        type="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                    >
                </div>

                <!-- Error message -->
                <div v-if="error" class="auth-error">
                    {{ error }}
                </div>

                <button 
                    type="submit" 
                    class="btn btn--primary"
                    :disabled="isLoading"
                >
                    <span v-if="isLoading" class="loading-spinner"></span>
                    <span v-else>{{ isLogin ? 'Log In' : 'Create Account' }}</span>
                </button>
            </form>

            <p class="auth-link">
                {{ isLogin ? "Don't have an account?" : "Already have an account?" }}
                <a href="#" @click.prevent="toggleMode">
                    {{ isLogin ? 'Sign up' : 'Log in' }}
                </a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useGameStore } from '../stores/game';

const authStore = useAuthStore();
const gameStore = useGameStore();

const isLogin = ref(true);
const isLoading = ref(false);
const error = ref('');

const form = reactive({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
});

function toggleMode() {
    isLogin.value = !isLogin.value;
    error.value = '';
}

async function handleSubmit() {
    isLoading.value = true;
    error.value = '';

    try {
        let result;
        
        if (isLogin.value) {
            result = await authStore.login(form.email, form.password);
        } else {
            result = await authStore.register(
                form.name,
                form.email,
                form.password,
                form.passwordConfirmation
            );
        }

        if (result.success) {
            // Load game state after successful auth
            await gameStore.loadGameState();
        } else {
            error.value = result.error;
        }
    } catch (err) {
        error.value = err.message || 'An error occurred';
    } finally {
        isLoading.value = false;
    }
}
</script>

<style scoped>
.auth-error {
    background: var(--color-danger-dim);
    border: 1px solid var(--color-danger);
    border-radius: var(--radius-md);
    padding: var(--space-sm) var(--space-md);
    margin-bottom: var(--space-md);
    color: var(--color-danger);
    font-size: var(--font-size-sm);
}

.loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
