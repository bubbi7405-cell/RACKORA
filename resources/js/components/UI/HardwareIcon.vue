<template>
    <div class="hw-icon-wrapper" :class="[type, size]">
        <!-- SERVER/NODE -->
        <svg v-if="type === 'server'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
            <line x1="6" y1="8" x2="6.01" y2="8"></line>
            <line x1="10" y1="8" x2="10.01" y2="8"></line>
            <line x1="14" y1="8" x2="18" y2="8"></line>
            <line x1="6" y1="12" x2="6.01" y2="12"></line>
            <line x1="10" y1="12" x2="10.01" y2="12"></line>
            <line x1="14" y1="12" x2="18" y2="12"></line>
            <line x1="6" y1="16" x2="6.01" y2="16"></line>
            <line x1="10" y1="16" x2="10.01" y2="16"></line>
            <line x1="14" y1="16" x2="18" y2="16"></line>
            <path d="M22 12h-2"></path>
            <path d="M4 12H2"></path>
        </svg>

        <!-- RACK -->
        <svg v-else-if="type === 'rack'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
            <line x1="5" y1="6" x2="19" y2="6"></line>
            <line x1="5" y1="10" x2="19" y2="10"></line>
            <line x1="5" y1="14" x2="19" y2="14"></line>
            <line x1="5" y1="18" x2="19" y2="18"></line>
            <circle cx="9" cy="8" r="1"></circle>
            <circle cx="9" cy="12" r="1"></circle>
            <circle cx="9" cy="16" r="1"></circle>
        </svg>

        <!-- MOTHERBOARD -->
        <svg v-else-if="type === 'motherboard'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <path d="M7 7h4v4H7z"></path>
            <path d="M15 7h2v6h-2z"></path>
            <path d="M7 15h6v2H7z"></path>
            <path d="M21 9h-2"></path>
            <path d="M21 15h-2"></path>
            <path d="M9 3v2"></path>
            <path d="M15 3v2"></path>
        </svg>

        <!-- CPU -->
        <svg v-else-if="type === 'cpu'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
            <rect x="9" y="9" width="6" height="6"></rect>
            <line x1="9" y1="1" x2="9" y2="4"></line>
            <line x1="15" y1="1" x2="15" y2="4"></line>
            <line x1="9" y1="20" x2="9" y2="23"></line>
            <line x1="15" y1="20" x2="15" y2="23"></line>
            <line x1="20" y1="9" x2="23" y2="9"></line>
            <line x1="20" y1="14" x2="23" y2="14"></line>
            <line x1="1" y1="9" x2="4" y2="9"></line>
            <line x1="1" y1="14" x2="4" y2="14"></line>
        </svg>

        <!-- RAM -->
        <svg v-else-if="type === 'ram'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 8h20v8H2z"></path>
            <path d="M6 16v2"></path>
            <path d="M10 16v2"></path>
            <path d="M14 16v2"></path>
            <path d="M18 16v2"></path>
            <path d="M4 12h2"></path>
            <path d="M10 12h8"></path>
            <path d="M4 8v-2"></path>
            <path d="M20 8v-2"></path>
        </svg>

        <!-- STORAGE -->
        <svg v-else-if="type === 'storage'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 12H2"></path>
            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
            <line x1="6" y1="16" x2="6.01" y2="16"></line>
            <line x1="10" y1="16" x2="14" y2="16"></line>
        </svg>

        <!-- DEFAULT / COMPONENT -->
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
            <polyline points="2 17 12 22 22 17"></polyline>
            <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
    </div>
</template>

<script setup>
defineProps({
    type: {
        type: String,
        required: true,
        // server, rack, component, cpu, ram, storage, motherboard
    },
    size: {
        type: String,
        default: 'md',
        // sm, md, lg
    }
});
</script>

<style scoped>
.hw-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--v3-text-secondary);
    transition: all var(--v3-transition-fast);
}

.hw-icon-wrapper svg {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 0 4px rgba(var(--v3-accent-rgb), 0.3));
}

.sm { width: 20px; height: 20px; }
.md { width: 32px; height: 32px; }
.lg { width: 48px; height: 48px; }

/* Type-specific colors */
.server svg { color: #58a6ff; filter: drop-shadow(0 0 4px rgba(88, 166, 255, 0.4)); }
.rack svg { color: #8b949e; filter: drop-shadow(0 0 4px rgba(139, 148, 158, 0.4)); }
.cpu svg { color: #ff7b72; filter: drop-shadow(0 0 4px rgba(255, 123, 114, 0.4)); }
.ram svg { color: #3fb950; filter: drop-shadow(0 0 4px rgba(63, 185, 80, 0.4)); }
.storage svg { color: #a371f7; filter: drop-shadow(0 0 4px rgba(163, 113, 247, 0.4)); }
.motherboard svg { color: #d2a8ff; filter: drop-shadow(0 0 4px rgba(210, 168, 255, 0.4)); }
</style>
