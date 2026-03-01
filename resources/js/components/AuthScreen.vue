<template>
    <div class="landing-page">
        <!-- Animated Background -->
        <div class="bg-grid"></div>
        <div class="bg-glow bg-glow--1"></div>
        <div class="bg-glow bg-glow--2"></div>
        <div class="bg-glow bg-glow--3"></div>

        <!-- Floating Particles -->
        <div class="particles">
            <div v-for="n in 20" :key="n" class="particle" :style="particleStyle(n)"></div>
        </div>

        <!-- Navigation -->
        <nav class="landing-nav">
            <div class="nav-logo">
                <span class="nav-logo__text">RACK</span><span class="nav-logo__accent">ORA</span>
            </div>
            <div class="nav-links">
                <a href="#features" class="nav-link" @click.prevent="scrollTo('features')">Features</a>
                <a href="#about" class="nav-link" @click.prevent="scrollTo('about')">About</a>
                <button class="nav-cta" @click="showAuthModal = true; isLogin = true">
                    Launch Game
                </button>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-dot"></span>
                    BROWSER-BASED SERVER MANAGEMENT GAME
                </div>
                <h1 class="hero-title">
                    Build Your<br>
                    <span class="hero-gradient">Hosting Empire</span>
                </h1>
                <p class="hero-description">
                    From basement startup to global data center. Buy hardware, manage customers, 
                    survive crises, and rise to the top of the hosting industry — all in real-time.
                </p>
                <div class="hero-actions">
                    <button class="btn-hero-primary" @click="showAuthModal = true; isLogin = false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-icon">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                        Start Playing Free
                    </button>
                    <button class="btn-hero-secondary" @click="showAuthModal = true; isLogin = true">
                        Sign In
                    </button>
                </div>
                <div class="hero-stats-row">
                    <div class="hero-mini-stat">
                        <span class="hms-value">45+</span>
                        <span class="hms-label">Game Systems</span>
                    </div>
                    <div class="hero-mini-stat-divider"></div>
                    <div class="hero-mini-stat">
                        <span class="hms-value">Real-Time</span>
                        <span class="hms-label">Simulation</span>
                    </div>
                    <div class="hero-mini-stat-divider"></div>
                    <div class="hero-mini-stat">
                        <span class="hms-value">100%</span>
                        <span class="hms-label">Free to Play</span>
                    </div>
                </div>
            </div>

            <!-- Hero Visual - Animated Rack -->
            <div class="hero-visual">
                <div class="rack-showcase">
                    <div class="rack-frame">
                        <div class="rack-header">
                            <span class="rack-label">RACK-01</span>
                            <span class="rack-status">● ONLINE</span>
                        </div>
                        <div class="rack-slots">
                            <div v-for="i in 8" :key="i" class="rack-server" :class="'server-delay-' + i">
                                <div class="server-leds">
                                    <span class="led led--green" :style="{ animationDelay: (i * 0.3) + 's' }"></span>
                                    <span class="led led--blue" :style="{ animationDelay: (i * 0.2 + 0.1) + 's' }"></span>
                                </div>
                                <div class="server-label">{{ serverLabels[i - 1] }}</div>
                                <div class="server-activity">
                                    <div class="activity-bar" v-for="j in 5" :key="j" 
                                        :style="{ height: activityHeight(i, j) + '%', animationDelay: (i * 0.15 + j * 0.08) + 's' }">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rack-footer">
                            <span>⚡ 2.4 kW</span>
                            <span>🌡️ 23°C</span>
                            <span>📶 10 Gbps</span>
                        </div>
                    </div>
                    <!-- Glow effect behind rack -->
                    <div class="rack-glow"></div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section" id="features">
            <div class="section-header">
                <div class="section-badge">CORE SYSTEMS</div>
                <h2 class="section-title">Everything You Need to&nbsp;<span class="text-gradient">Dominate</span></h2>
            </div>
            <div class="features-grid">
                <div class="feature-card" v-for="(feature, idx) in features" :key="idx">
                    <div class="feature-icon">{{ feature.icon }}</div>
                    <h3 class="feature-title">{{ feature.title }}</h3>
                    <p class="feature-desc">{{ feature.desc }}</p>
                </div>
            </div>
        </section>

        <!-- About / CTA Section -->
        <section class="cta-section" id="about">
            <div class="cta-inner">
                <div class="cta-badge">by CodePony.de</div>
                <h2 class="cta-title">Ready to Build?</h2>
                <p class="cta-desc">
                    Rackora is a real-time browser simulation game where every decision matters. 
                    No pay-to-win. No ads. Just pure strategy and engineering.
                </p>
                <button class="btn-cta" @click="showAuthModal = true; isLogin = false">
                    Create Free Account
                </button>
                <div class="cta-version">Rackora V.1.0 — Open Alpha</div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="landing-footer">
            <div class="footer-inner">
                <div class="footer-brand">
                    <span class="footer-logo-text">RACK</span><span class="footer-logo-accent">ORA</span>
                    <span class="footer-by">by CodePony.de</span>
                </div>
                <div class="footer-copy">© 2026 CodePony.de — All rights reserved.</div>
            </div>
        </footer>

        <!-- Auth Modal Overlay -->
        <Transition name="modal">
            <div v-if="showAuthModal" class="auth-modal-backdrop" @click.self="showAuthModal = false">
                <div class="auth-modal">
                    <button class="modal-close" @click="showAuthModal = false">×</button>
                    
                    <div class="modal-logo">
                        <span class="ml-text">RACK</span><span class="ml-accent">ORA</span>
                    </div>
                    <div class="modal-branding">by CodePony.de <span class="modal-version">V.1.0</span></div>
                    
                    <h2 class="modal-title">{{ isLogin ? 'Welcome Back' : 'Create Account' }}</h2>
                    <p class="modal-subtitle">{{ isLogin ? 'Sign in to your empire.' : 'Start your hosting journey.' }}</p>

                    <form @submit.prevent="handleSubmit" class="modal-form">
                        <div v-if="!isLogin" class="field">
                            <label>Operator Name</label>
                            <input v-model="form.name" type="text" placeholder="Your name" required>
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input v-model="form.email" type="email" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input v-model="form.password" type="password" placeholder="••••••••" required>
                        </div>
                        <div v-if="!isLogin" class="field">
                            <label>Confirm Password</label>
                            <input v-model="form.passwordConfirmation" type="password" placeholder="••••••••" required>
                        </div>

                        <div v-if="error" class="modal-error">{{ error }}</div>

                        <button type="submit" class="btn-submit" :disabled="isLoading">
                            <span v-if="isLoading" class="spinner"></span>
                            <span v-else>{{ isLogin ? 'Sign In' : 'Create Account' }}</span>
                        </button>
                    </form>

                    <p class="modal-toggle">
                        {{ isLogin ? "No account yet?" : "Already playing?" }}
                        <a href="#" @click.prevent="toggleMode">
                            {{ isLogin ? 'Sign up free' : 'Sign in' }}
                        </a>
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useGameStore } from '../stores/game';

const authStore = useAuthStore();
const gameStore = useGameStore();

const showAuthModal = ref(false);
const isLogin = ref(true);
const isLoading = ref(false);
const error = ref('');

const form = reactive({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
});

const serverLabels = ['WEB-01', 'DB-MASTER', 'GPU-RENDER', 'CACHE-01', 'API-GW', 'STORAGE', 'MONITOR', 'BACKUP'];

const features = [
    { icon: '🖥️', title: 'Build & Manage Racks', desc: 'Buy servers, racks, and scale from a basement to a global data center. Every slot matters.' },
    { icon: '⚡', title: 'Power & Cooling', desc: 'Balance energy costs, heat management, and cooling zones. Overheat and you lose everything.' },
    { icon: '👥', title: 'Customer Contracts', desc: 'Attract VPS, Dedicated, and GPU customers. Negotiate enterprise deals worth millions.' },
    { icon: '🔥', title: 'Crisis Management', desc: 'Hardware failures, DDoS attacks, power outages. React fast or face cascading disasters.' },
    { icon: '📊', title: 'Real-Time Economy', desc: 'Dynamic pricing, energy markets, profit margins. Every dollar counts in your balance sheet.' },
    { icon: '🏆', title: 'Compete & Rise', desc: 'Leaderboards, reputation systems, NPC competitors. Become the #1 hosting provider.' },
];

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
            result = await authStore.register(form.name, form.email, form.password, form.passwordConfirmation);
        }
        if (result.success) {
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

function particleStyle(n) {
    const size = 2 + Math.random() * 3;
    return {
        width: size + 'px',
        height: size + 'px',
        left: Math.random() * 100 + '%',
        top: Math.random() * 100 + '%',
        animationDelay: (n * 0.5) + 's',
        animationDuration: (8 + Math.random() * 12) + 's',
    };
}

function activityHeight(i, j) {
    return 20 + Math.sin(i * 1.5 + j * 0.8) * 30 + Math.random() * 30;
}

function scrollTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<style scoped>
/* ==========================================
   LANDING PAGE — RACKORA
   ========================================== */
.landing-page {
    min-height: 100vh;
    background: #04060b;
    color: #e6edf3;
    font-family: 'Inter', -apple-system, sans-serif;
    overflow-x: hidden;
    position: relative;
}

/* ANIMATED BACKGROUND */
.bg-grid {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background-image: 
        linear-gradient(rgba(88, 166, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(88, 166, 255, 0.03) 1px, transparent 1px);
    background-size: 60px 60px;
    z-index: 0;
    pointer-events: none;
}

.bg-glow {
    position: fixed; border-radius: 50%; filter: blur(100px); z-index: 0; pointer-events: none;
    animation: glowFloat 20s ease-in-out infinite;
}
.bg-glow--1 { width: 600px; height: 600px; top: -10%; left: -10%; background: rgba(88, 166, 255, 0.08); }
.bg-glow--2 { width: 500px; height: 500px; bottom: 10%; right: -5%; background: rgba(56, 139, 253, 0.06); animation-delay: 7s; }
.bg-glow--3 { width: 400px; height: 400px; top: 40%; left: 50%; background: rgba(139, 92, 246, 0.04); animation-delay: 14s; }

@keyframes glowFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -20px) scale(1.1); }
    66% { transform: translate(-20px, 30px) scale(0.9); }
}

/* PARTICLES */
.particles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
.particle {
    position: absolute; border-radius: 50%; background: rgba(88, 166, 255, 0.4);
    animation: particleDrift linear infinite;
}
@keyframes particleDrift {
    0% { transform: translateY(0) scale(1); opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { transform: translateY(-100vh) scale(0.3); opacity: 0; }
}

/* NAVIGATION */
.landing-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 50px;
    background: rgba(4, 6, 11, 0.8); backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.nav-logo { font-size: 1.3rem; font-weight: 900; letter-spacing: 3px; text-transform: uppercase; }
.nav-logo__text { color: #e6edf3; }
.nav-logo__accent { color: #58a6ff; }
.nav-links { display: flex; align-items: center; gap: 32px; }
.nav-link { color: #8b949e; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; text-decoration: none; transition: color 0.2s; }
.nav-link:hover { color: #e6edf3; }
.nav-cta {
    background: linear-gradient(135deg, #58a6ff, #388bfd);
    color: #fff; padding: 10px 24px; border-radius: 8px;
    font-weight: 700; font-size: 0.85rem; cursor: pointer;
    border: none; transition: all 0.3s; letter-spacing: 0.5px;
}
.nav-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(88, 166, 255, 0.3); }

/* HERO */
.hero {
    position: relative; z-index: 1;
    min-height: 100vh; display: flex; align-items: center; justify-content: space-between;
    padding: 120px 80px 80px;
    gap: 60px;
}

.hero-content { flex: 1; max-width: 600px; }

.hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(88, 166, 255, 0.08); border: 1px solid rgba(88, 166, 255, 0.15);
    padding: 6px 16px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 2px; color: #58a6ff;
    margin-bottom: 24px;
}
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: #3fb950; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.hero-title {
    font-size: 4.2rem; font-weight: 900; line-height: 1.05; letter-spacing: -2px;
    margin-bottom: 24px;
}
.hero-gradient {
    background: linear-gradient(135deg, #58a6ff 0%, #a78bfa 50%, #f472b6 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-description { font-size: 1.15rem; color: #8b949e; line-height: 1.7; margin-bottom: 36px; max-width: 500px; }

.hero-actions { display: flex; gap: 16px; margin-bottom: 48px; }

.btn-hero-primary {
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(135deg, #58a6ff, #388bfd);
    color: #fff; padding: 16px 32px; border-radius: 12px;
    font-weight: 800; font-size: 1rem; cursor: pointer;
    border: none; transition: all 0.3s; letter-spacing: 0.3px;
}
.btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(88, 166, 255, 0.35); }
.btn-icon { width: 18px; height: 18px; }

.btn-hero-secondary {
    background: transparent; border: 1px solid rgba(255,255,255,0.12);
    color: #e6edf3; padding: 16px 32px; border-radius: 12px;
    font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s;
}
.btn-hero-secondary:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.2); }

.hero-stats-row { display: flex; align-items: center; gap: 24px; }
.hero-mini-stat { text-align: center; }
.hms-value { display: block; font-size: 1.1rem; font-weight: 900; color: #e6edf3; }
.hms-label { display: block; font-size: 0.65rem; color: #484f58; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
.hero-mini-stat-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.06); }

/* HERO VISUAL — ANIMATED RACK */
.hero-visual { flex: 1; display: flex; justify-content: center; align-items: center; position: relative; }

.rack-showcase { position: relative; }

.rack-frame {
    width: 340px; background: #0a0e17; border: 1px solid rgba(88, 166, 255, 0.15);
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 0 60px rgba(88, 166, 255, 0.08), inset 0 1px 0 rgba(255,255,255,0.04);
}

.rack-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 0.65rem; font-weight: 800; letter-spacing: 2px;
}
.rack-label { color: #484f58; }
.rack-status { color: #3fb950; }

.rack-slots { padding: 8px; display: flex; flex-direction: column; gap: 4px; }

.rack-server {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04);
    border-radius: 6px; padding: 8px 12px;
    animation: serverSlideIn 0.6s ease-out both;
}
@keyframes serverSlideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}
.server-delay-1 { animation-delay: 0.3s; }
.server-delay-2 { animation-delay: 0.5s; }
.server-delay-3 { animation-delay: 0.7s; }
.server-delay-4 { animation-delay: 0.9s; }
.server-delay-5 { animation-delay: 1.1s; }
.server-delay-6 { animation-delay: 1.3s; }
.server-delay-7 { animation-delay: 1.5s; }
.server-delay-8 { animation-delay: 1.7s; }

.server-leds { display: flex; flex-direction: column; gap: 3px; }
.led { width: 4px; height: 4px; border-radius: 50%; animation: ledBlink 3s infinite; }
.led--green { background: #3fb950; box-shadow: 0 0 6px rgba(63, 185, 80, 0.6); }
.led--blue { background: #58a6ff; box-shadow: 0 0 6px rgba(88, 166, 255, 0.6); }
@keyframes ledBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

.server-label { font-size: 0.6rem; font-weight: 700; color: #8b949e; letter-spacing: 1px; width: 80px; font-family: 'JetBrains Mono', 'Courier New', monospace; }

.server-activity { display: flex; align-items: flex-end; gap: 2px; height: 18px; flex: 1; }
.activity-bar {
    flex: 1; min-height: 3px; border-radius: 1px;
    background: linear-gradient(180deg, #58a6ff, #388bfd);
    animation: activityPulse 2s ease-in-out infinite alternate;
    opacity: 0.6;
}
@keyframes activityPulse {
    0% { transform: scaleY(1); opacity: 0.4; }
    100% { transform: scaleY(1.4); opacity: 0.8; }
}

.rack-footer {
    display: flex; justify-content: space-around; padding: 10px 18px;
    border-top: 1px solid rgba(255,255,255,0.05);
    font-size: 0.6rem; color: #484f58; font-weight: 600;
}

.rack-glow {
    position: absolute; top: 50%; left: 50%; width: 400px; height: 400px;
    transform: translate(-50%, -50%); border-radius: 50%;
    background: radial-gradient(circle, rgba(88, 166, 255, 0.12), transparent 70%);
    z-index: -1; pointer-events: none;
}

/* FEATURES */
.features-section {
    position: relative; z-index: 1;
    padding: 100px 80px;
}

.section-header { text-align: center; margin-bottom: 60px; }
.section-badge {
    display: inline-block; background: rgba(88, 166, 255, 0.08);
    border: 1px solid rgba(88, 166, 255, 0.12);
    padding: 4px 14px; border-radius: 4px;
    font-size: 0.6rem; font-weight: 800; letter-spacing: 2.5px; color: #58a6ff;
    margin-bottom: 16px;
}
.section-title { font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; }
.text-gradient {
    background: linear-gradient(135deg, #58a6ff, #a78bfa);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}

.features-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
    max-width: 1100px; margin: 0 auto;
}

.feature-card {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px; padding: 32px;
    transition: all 0.4s;
}
.feature-card:hover {
    border-color: rgba(88, 166, 255, 0.2);
    background: rgba(88, 166, 255, 0.03);
    transform: translateY(-4px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}
.feature-icon { font-size: 2rem; margin-bottom: 16px; }
.feature-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; }
.feature-desc { font-size: 0.85rem; color: #8b949e; line-height: 1.6; }

/* CTA */
.cta-section { position: relative; z-index: 1; padding: 100px 80px; text-align: center; }
.cta-inner { max-width: 600px; margin: 0 auto; }
.cta-badge { font-size: 0.7rem; font-weight: 700; color: #484f58; letter-spacing: 2px; margin-bottom: 16px; }
.cta-title { font-size: 3rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 16px; }
.cta-desc { font-size: 1.05rem; color: #8b949e; line-height: 1.7; margin-bottom: 32px; }
.btn-cta {
    background: linear-gradient(135deg, #58a6ff, #388bfd);
    color: #fff; padding: 18px 48px; border-radius: 12px;
    font-weight: 800; font-size: 1.05rem; cursor: pointer;
    border: none; transition: all 0.3s;
}
.btn-cta:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(88, 166, 255, 0.35); }
.cta-version { margin-top: 24px; font-size: 0.7rem; color: #30363d; letter-spacing: 1px; }

/* FOOTER */
.landing-footer {
    position: relative; z-index: 1;
    border-top: 1px solid rgba(255,255,255,0.04); padding: 30px 80px;
}
.footer-inner { display: flex; justify-content: space-between; align-items: center; }
.footer-brand { display: flex; align-items: center; gap: 12px; }
.footer-logo-text { font-weight: 900; letter-spacing: 2px; color: #e6edf3; font-size: 0.85rem; }
.footer-logo-accent { font-weight: 900; letter-spacing: 2px; color: #58a6ff; font-size: 0.85rem; }
.footer-by { font-size: 0.7rem; color: #30363d; }
.footer-copy { font-size: 0.7rem; color: #30363d; }

/* ==========================================
   AUTH MODAL
   ========================================== */
.auth-modal-backdrop {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(12px);
    z-index: 5000; display: flex; align-items: center; justify-content: center;
}

.auth-modal {
    width: 420px; max-width: 94vw;
    background: #0a0e17; border: 1px solid rgba(88, 166, 255, 0.1);
    border-radius: 20px; padding: 40px;
    box-shadow: 0 0 80px rgba(88, 166, 255, 0.08), 0 0 0 1px rgba(255,255,255,0.03);
    position: relative;
}

.modal-close {
    position: absolute; top: 16px; right: 20px;
    background: none; border: none; color: #30363d;
    font-size: 2rem; cursor: pointer; transition: color 0.2s; line-height: 1;
}
.modal-close:hover { color: #e6edf3; }

.modal-logo { text-align: center; font-size: 1.8rem; font-weight: 900; letter-spacing: 4px; margin-bottom: 4px; }
.ml-text { color: #e6edf3; }
.ml-accent { color: #58a6ff; }

.modal-branding { text-align: center; font-size: 0.65rem; color: #30363d; letter-spacing: 1px; margin-bottom: 24px; }
.modal-version {
    display: inline-block; background: rgba(88, 166, 255, 0.1);
    color: #58a6ff; padding: 1px 6px; border-radius: 3px;
    font-size: 0.55rem; font-weight: 700; margin-left: 3px;
}

.modal-title { text-align: center; font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
.modal-subtitle { text-align: center; font-size: 0.8rem; color: #484f58; margin-bottom: 28px; }

.modal-form { display: flex; flex-direction: column; gap: 16px; }

.field label {
    display: block; font-size: 0.7rem; font-weight: 700; color: #8b949e;
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
}
.field input {
    width: 100%; padding: 12px 16px;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; color: #e6edf3; font-size: 0.9rem;
    transition: all 0.2s; outline: none;
}
.field input:focus {
    border-color: #58a6ff;
    box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.1);
    background: rgba(88, 166, 255, 0.03);
}
.field input::placeholder { color: #30363d; }

.modal-error {
    background: rgba(248, 81, 73, 0.1); border: 1px solid rgba(248, 81, 73, 0.2);
    border-radius: 8px; padding: 10px 14px;
    color: #f85149; font-size: 0.8rem; text-align: center;
}

.btn-submit {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #58a6ff, #388bfd);
    color: #fff; border: none; border-radius: 10px;
    font-weight: 800; font-size: 0.95rem; cursor: pointer;
    transition: all 0.3s; margin-top: 4px;
}
.btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(88, 166, 255, 0.3); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner {
    width: 20px; height: 20px;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

.modal-toggle { text-align: center; margin-top: 20px; font-size: 0.8rem; color: #484f58; }
.modal-toggle a { color: #58a6ff; text-decoration: none; font-weight: 700; }
.modal-toggle a:hover { text-decoration: underline; }

/* MODAL TRANSITIONS */
.modal-enter-active { transition: all 0.3s ease-out; }
.modal-leave-active { transition: all 0.2s ease-in; }
.modal-enter-from { opacity: 0; }
.modal-enter-from .auth-modal { transform: scale(0.95) translateY(20px); }
.modal-leave-to { opacity: 0; }
.modal-leave-to .auth-modal { transform: scale(0.95); }

/* RESPONSIVE */
@media (max-width: 1024px) {
    .hero { flex-direction: column; padding: 120px 40px 60px; text-align: center; }
    .hero-content { max-width: 100%; }
    .hero-description { margin: 0 auto 36px; }
    .hero-actions { justify-content: center; }
    .hero-stats-row { justify-content: center; }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .landing-nav { padding: 16px 24px; }
    .features-section, .cta-section { padding: 80px 24px; }
}

@media (max-width: 640px) {
    .hero-title { font-size: 2.6rem; }
    .hero-actions { flex-direction: column; align-items: center; }
    .features-grid { grid-template-columns: 1fr; }
    .hero-visual { display: none; }
    .nav-links { gap: 16px; }
    .nav-link { display: none; }
    .footer-inner { flex-direction: column; gap: 12px; }
    .landing-footer { padding: 24px; }
}
</style>
