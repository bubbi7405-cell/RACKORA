<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="roadmap-overlay glass-panel">
            <header class="overlay-header">
                <div class="header-title">
                    <span class="header-icon">🗺️</span>
                    <h2>Project Roadmap</h2>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="overlay-content roadmap-container">
                <div class="roadmap-timeline">
                    <!-- Phase 1: Completed -->
                    <div class="roadmap-phase completed">
                        <div class="phase-header">
                            <span class="status-badge">Completed</span>
                            <h3>Phase 1: Foundation</h3>
                        </div>
                        <ul class="milestones">
                            <li class="done">Core Game Loop & Economy</li>
                            <li class="done">V-Server Infrastructure</li>
                            <li class="done">Customer Satisfaction Engine</li>
                            <li class="done">Employee Management</li>
                        </ul>
                    </div>

                    <!-- Phase 2: Completed -->
                    <div class="roadmap-phase completed">
                        <div class="phase-header">
                            <span class="status-badge">Completed</span>
                            <h3>Phase 2: Live-Ops & Growth</h3>
                        </div>
                        <ul class="milestones">
                            <li class="done">Advanced Analytics Dashboard</li>
                            <li class="done">Admin Operational Control (Obsidian)</li>
                            <li class="done">Hardware Assembly System</li>
                            <li class="done">Marketing Campaigns</li>
                        </ul>
                    </div>

                    <!-- Phase 3: Current -->
                    <div class="roadmap-phase active">
                        <div class="phase-header">
                            <span class="status-badge">Current</span>
                            <h3>Phase 3: The Enterprise Era</h3>
                        </div>
                        <ul class="milestones">
                            <li>Shared Web-Hosting & Databases</li>
                            <li>Dedicated Bare Metal Racks</li>
                            <li>Multi-Region Expansion</li>
                            <li>B2B Enterprise Contracts</li>
                        </ul>
                    </div>

                    <!-- Phase 4: Long-term -->
                    <div class="roadmap-phase future">
                        <div class="phase-header">
                            <span class="status-badge">Legacy</span>
                            <h3>Phase 4: Global Domination</h3>
                        </div>
                        <ul class="milestones">
                            <li>Public Stock Market (IPO)</li>
                            <li>Takeover Simulations</li>
                            <li>Cloud AI Optimization</li>
                            <li>Edge Computing Node Network</li>
                        </ul>
                    </div>
                </div>
            </div>

            <footer class="overlay-footer">
                <p class="roadmap-note">Note: Roadmaps are subject to change based on player feedback and market conditions.</p>
            </footer>
        </div>
    </div>
</template>

<script setup>
defineEmits(['close']);
</script>

<style scoped>
.roadmap-overlay {
    width: 800px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

.roadmap-container {
    padding: var(--space-xl);
    overflow-y: auto;
}

.roadmap-timeline {
    display: flex;
    flex-direction: column;
    gap: var(--space-xl);
}

.roadmap-phase {
    position: relative;
    padding: var(--space-lg);
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    transition: all 0.3s ease;
}

.roadmap-phase.active {
    background: rgba(88, 166, 255, 0.05);
    border-color: rgba(88, 166, 255, 0.3);
    box-shadow: 0 0 30px rgba(88, 166, 255, 0.1);
}

.roadmap-phase.completed {
    opacity: 0.7;
}

.phase-header {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    margin-bottom: var(--space-md);
}

.status-badge {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 4px;
    background: #30363d;
    color: var(--color-text-muted);
}

.active .status-badge {
    background: #1f6feb;
    color: white;
}

.completed .status-badge {
    background: #238636;
    color: white;
}

.milestones {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-sm);
}

.milestones li {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.milestones li::before {
    content: '○';
    color: rgba(255, 255, 255, 0.2);
}

.milestones li.done {
    color: var(--color-text-secondary);
    text-decoration: line-through opacity(0.5);
}

.milestones li.done::before {
    content: '✓';
    color: #3fb950;
    text-decoration: none;
}

.milestones li.in-progress {
    color: var(--color-text-primary);
    font-weight: 500;
}

.milestones li.in-progress::before {
    content: '⚡';
    color: #ffd700;
}

.roadmap-note {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    font-style: italic;
}
</style>
