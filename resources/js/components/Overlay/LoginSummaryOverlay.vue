<template>
    <div class="overlay-backdrop">
        <div class="summary-overlay glass-panel animation-bounce-in">
            <div class="summary-header">
                <div class="header-icon">🕰️</div>
                <h2>While You Were Away</h2>
                <div class="time-away">{{ summary.minutesAway }} minutes of operations</div>
            </div>

            <div class="summary-body">
                <div class="summary-grid">
                    <!-- Finances -->
                    <div class="summary-card finance-card">
                        <h3>Operating Results</h3>
                        <div class="finance-row">
                            <span>Income</span>
                            <span class="text-success">+${{ formatNumber(summary.finances.income) }}</span>
                        </div>
                        <div class="finance-row">
                            <span>Expenses</span>
                            <span class="text-danger">-${{ formatNumber(summary.finances.expenses) }}</span>
                        </div>
                        <div class="divider"></div>
                        <div class="finance-row total">
                            <span>Net Profit</span>
                            <span :class="summary.finances.net >= 0 ? 'text-success' : 'text-danger'">
                                ${{ formatNumber(summary.finances.net) }}
                            </span>
                        </div>
                    </div>

                    <!-- Incidents -->
                    <div class="summary-card">
                        <h3>Infrastructure Events</h3>
                        <div class="event-stat">
                            <span class="val">{{ summary.incidents.new }}</span>
                            <span class="lab">New Incidents</span>
                        </div>
                        <div class="event-stat">
                            <span class="val">{{ summary.incidents.failed }}</span>
                            <span class="lab">Failed Resolutions</span>
                        </div>
                        <p v-if="summary.customers.churned > 0" class="churn-warning">
                            ⚠️ {{ summary.customers.churned }} clients terminated their contracts due to poor service.
                        </p>
                    </div>
                </div>

                <div class="summary-footer">
                    <button class="premium-btn" @click="$emit('close')">Back to Terminal</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    summary: {
        type: Object,
        required: true
    }
});

const formatNumber = (val) => {
    return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(20px);
    z-index: 5000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.summary-overlay {
    width: 600px;
    border-radius: 32px;
    overflow: hidden;
    border: 1px solid rgba(0, 242, 255, 0.2);
    box-shadow: 0 0 100px rgba(0, 242, 255, 0.1);
}

.summary-header {
    background: rgba(255, 255, 255, 0.05);
    padding: 40px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.header-icon {
    font-size: 3rem;
    margin-bottom: 20px;
}

.summary-header h2 {
    margin: 0;
    font-size: 2rem;
    text-transform: uppercase;
    letter-spacing: 4px;
    background: linear-gradient(to right, #00f2ff, #006aff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.time-away {
    margin-top: 10px;
    font-size: 0.9rem;
    color: #8b949e;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.summary-body {
    padding: 40px;
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.summary-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    padding: 25px;
}

.summary-card h3 {
    margin: 0 0 20px 0;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #8b949e;
    letter-spacing: 2px;
}

.finance-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 1.1rem;
    font-family: var(--font-family-mono);
}

.divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 15px 0;
}

.finance-row.total {
    font-weight: 800;
    font-size: 1.3rem;
}

.event-stat {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.event-stat .val {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    font-family: var(--font-family-mono);
}

.event-stat .lab {
    font-size: 0.85rem;
    color: #8b949e;
}

.churn-warning {
    margin-top: 20px;
    font-size: 0.8rem;
    color: #f85149;
    background: rgba(248, 81, 73, 0.1);
    padding: 10px;
    border-radius: 8px;
    line-height: 1.4;
}

.summary-footer {
    text-align: center;
}

.premium-btn {
    background: #00f2ff;
    border: none;
    color: #000;
    padding: 15px 40px;
    border-radius: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    cursor: pointer;
    transition: 0.3s;
}

.premium-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 242, 255, 0.4);
}

.animation-bounce-in {
    animation: bounce-in 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes bounce-in {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
