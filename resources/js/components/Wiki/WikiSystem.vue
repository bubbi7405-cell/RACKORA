<template>
    <div class="wiki-view l-page-container">
        <div class="wiki-layout">
            
            <!-- Internal Sidebar Navigation -->
            <aside class="wiki-nav">
                <div class="wiki-nav-header">
                    <h2 class="l1-priority">KNOWLEDGE_DB // SYSTEM_OS v4.1</h2>
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            placeholder="SEARCH_DB..." 
                        />
                    </div>
                </div>

                <div class="wiki-nav-groups scrollbar-hidden">
                    <div v-for="category in categories" :key="category.id" 
                         class="wiki-nav-item"
                         :class="{ 'is-active': activeCategoryId === category.id }"
                         @click="activeCategoryId = category.id">
                        <span class="nav-icon">{{ category.icon }}</span>
                        <span class="nav-label">{{ category.label }}</span>
                    </div>
                </div>

                <div class="wiki-nav-footer l4-priority">
                    <span>SECURITY: LEVEL_4_DIRECTOR</span><br>
                    <span>LAST_UPDATE: {{ new Date().toLocaleDateString() }}</span>
                </div>
            </aside>

            <!-- Content Area -->
            <main class="wiki-main-content custom-scrollbar" ref="contentArea">
                <transition name="wiki-slide" mode="out-in">
                    <div :key="activeCategory.id" class="wiki-page">
                        <header class="page-header">
                            <span class="page-meta l3-priority">// DATA_ENTRY: {{ activeCategoryId.toUpperCase() }}</span>
                            <h1 class="l1-priority">{{ activeCategory.title }}</h1>
                            <p class="summary-text l3-priority">{{ activeCategory.description }}</p>
                        </header>

                        <div class="page-sections">
                            <section v-for="(section, idx) in filteredSections" :key="idx" class="wiki-section">
                                <h2 class="section-title l2-priority">
                                    <span class="title-marker"></span>
                                    {{ section.title }}
                                </h2>
                                
                                <div class="section-container">
                                    <div v-if="section.description" class="info-block">
                                        <p class="l3-priority" v-html="section.description"></p>
                                    </div>

                                    <div v-if="section.howItWorks" class="info-block">
                                        <h3 class="l4-priority">HOW_IT_WORKS</h3>
                                        <p class="l3-priority" v-html="section.howItWorks"></p>
                                    </div>

                                    <div v-if="section.mechanics" class="info-block">
                                        <h3 class="l4-priority">KEY_MECHANICS</h3>
                                        <ul class="mechanic-list l3-priority">
                                            <li v-for="(m, mIdx) in section.mechanics" :key="mIdx">{{ m }}</li>
                                        </ul>
                                    </div>

                                    <div v-if="section.items" class="items-grid">
                                        <div v-for="(item, iIdx) in section.items" :key="iIdx" class="wiki-card">
                                            <div class="card-header">
                                                <span class="card-icon">{{ item.icon || '▣' }}</span>
                                                <h3 class="l2-priority">{{ item.name }}</h3>
                                            </div>
                                            <div class="card-body">
                                                <p class="l3-priority">{{ item.description }}</p>
                                                <div class="card-stats" v-if="item.stats">
                                                    <div v-for="(val, label) in item.stats" :key="label" class="stat-row">
                                                        <span class="stat-label">{{ label.toUpperCase() }}:</span>
                                                        <span class="stat-value l1-priority">{{ val }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="extra-info-row">
                                        <div v-if="section.optimization" class="info-block dark-block">
                                            <h3 class="l4-priority">OPTIMIZATION_TIPS</h3>
                                            <p class="l3-priority" v-html="section.optimization"></p>
                                        </div>
                                        <div v-if="section.examples" class="info-block dark-block">
                                            <h3 class="l4-priority">OPERATIONAL_EXAMPLES</h3>
                                            <p class="l3-priority" v-html="section.examples"></p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </transition>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    initialTab: { type: String, default: 'beginner' }
});

const activeCategoryId = ref(props.initialTab);
const searchQuery = ref('');
const contentArea = ref(null);

watch(() => props.initialTab, (newVal) => {
    if (newVal) activeCategoryId.value = newVal;
});

watch(activeCategoryId, () => {
    if (contentArea.value) contentArea.value.scrollTop = 0;
});

const categories = [
    { id: 'beginner', label: 'Beginner Guide', icon: '👶', title: 'INIT_SEQUENCE', description: 'Mandatory onboarding for new Infrastructure Directors.' },
    { id: 'infrastructure', label: 'Infrastructure', icon: '🏗️', title: 'PLANT_OPERATIONS', description: 'Management of physical space, power grids, and thermal dissipation systems.' },
    { id: 'servers', label: 'Servers', icon: '🌐', title: 'HARDWARE_ASSETS', description: 'Technical specifications for compute, storage, and networking hardware.' },
    { id: 'network', label: 'Network', icon: '📡', title: 'GLOBAL_CONNECTIVITY', description: 'Logistics of data transit, regional topologies, and latency management.' },
    { id: 'economy', label: 'Economy', icon: '💰', title: 'FINANCIAL_MECHANICS', description: 'Revenue generation models and market demand analysis.' },
    { id: 'strategy', label: 'Strategy', icon: '⌬', title: 'EXECUTIVE_STRATEGY', description: 'Advanced growth trajectories and R&D specializations.' },
    { id: 'events', label: 'Events', icon: '🌪️', title: 'VOLATILITY_PROTOCOLS', description: 'Response strategies for global crises and hardware failures.' }
];

const wikiData = {
    beginner: {
        sections: [
            {
                title: 'First Steps',
                description: 'Initialization of your regional presence.',
                howItWorks: 'Lease your first facility room in a region. This provides the physical boundaries for your empire.',
                mechanics: ['Regional selection.', 'Facility leasing.', 'Reputation activation.'],
                optimization: 'Select regions with low energy costs to maximize initial cash flow.',
                examples: 'Leasing a 20m² unit in Norway for its 0.08 $/kWh rate.'
            },
            {
                title: 'First Rack',
                description: 'Deploying the hardware foundation.',
                howItWorks: 'Racks provide the electrical backplane and vertical mounting units (U) required to install servers.',
                mechanics: ['12U / 24U / 42U sizes.', 'Physical footprint management.', 'Power distribution.'],
                optimization: 'Start with a 12U rack to minimize initial capital expenditure.',
                examples: 'Deploying a 12U rack to fill a small basement unit.'
            },
            {
                title: 'First Server',
                description: 'Provisioning active compute assets.',
                howItWorks: 'Servers are individual compute units that consume slots in a rack and execute client workloads.',
                mechanics: ['Slot consumption (U).', 'Hardware provisioning time.', 'Energy consumption.'],
                optimization: 'Match your first server to the requirements of the most profitable pending contract.',
                examples: 'Installing a 1U Web Node to handle a Basic Web Hosting contract.'
            },
            {
                title: 'First Contract',
                description: 'Securing your first revenue stream.',
                howItWorks: 'Accepting an SLA contract triggers active billing. You must ensure the server remains online to get paid.',
                mechanics: ['SLA Tiers.', 'Payment intervals.', 'Duration management.'],
                optimization: 'Avoid "High Priority" contracts until you have automated maintenance.',
                examples: 'Securing a $500/hr contract for a local news blog.'
            },
            {
                title: 'Profit Loop',
                description: 'Sustainable operation cycle.',
                howItWorks: 'The core cycle of Rackora: Build -> Sell -> Collect -> Research -> Expand.',
                mechanics: ['Cash flow management.', 'OpEx balancing.', 'Reputation growth.'],
                optimization: 'Always keep at least 20% of your capital as a "Crisis Buffer".',
                examples: 'Using first-month profits to research "Advanced Cooling" and double your capacity.'
            }
        ]
    },
    infrastructure: {
        sections: [
            {
                title: 'Server Racks',
                description: 'Physical enclosures for hardware.',
                howItWorks: 'Standard 19-inch racks allow high-density equipment mounting.',
                mechanics: ['Maximum U-capacity.', 'Physical room footprint.'],
                optimization: 'Research 42U racks early to maximize the ROI of your room floor space.',
                examples: 'Upgrading three 12U racks to one 42U rack.'
            },
            {
                title: 'Rack Units (U)',
                description: 'The standard measurement of vertical space.',
                howItWorks: 'Each server occupies a specific number of units. 1U = 1.75 inches of height.',
                mechanics: ['Vertical slot allocation.', 'Density management.'],
                optimization: 'Favor 1U servers for web hosting to maximize contract counts.',
                examples: 'Installing two 2U nodes in a server rack, consuming 4U total.'
            },
            {
                title: 'Cooling Systems',
                description: 'Thermal dissipation protocols.',
                howItWorks: 'Active servers generate heat. Cooling systems remove this heat to maintain hardware stable.',
                mechanics: ['Cooling Capacity (kW).', 'Ambient temperature influence.', 'Energy efficiency (PUE).'],
                optimization: 'Research "Cold Aisle Containment" to reduce cooling energy costs by 20%.',
                examples: 'Adding a 5kW AC unit to a room to support more GPU compute clusters.'
            },
            {
                title: 'Power Usage',
                description: 'Electrical load management.',
                howItWorks: 'Each room has a maximum total power throughput. Exceeding this causes a site-wide blackout.',
                mechanics: ['Individual server draw.', 'Ambient draw.', 'Load balancing.'],
                optimization: 'Install "Smart Power Strips" to monitor per-rack usage in real-time.',
                examples: 'Throttling non-essential nodes to keep the room power load below the 50kW limit.'
            },
            {
                title: 'Energy Cost',
                description: 'Operational expenditure on electricity.',
                howItWorks: 'Energy prices fluctuate by region and time. This is often the largest cost in your empire.',
                mechanics: ['Regional base rates.', 'Surge pricing.', 'Dynamic energy markets.'],
                optimization: 'Utilize batteries to store energy during cheap off-peak hours and use it during surges.',
                examples: 'Saving $12,000 monthly by shifting heavy rendering tasks to the night cycle in Tokyo.'
            }
        ]
    },
    servers: {
        sections: [
            {
                title: 'Node Archetypes',
                items: [
                    { name: 'Web Node', icon: '🌐', description: 'Balanced general-purpose node for hosting websites and APIs.', stats: { slots: '1U', cpu: 'High', ram: 'Medium' } },
                    { name: 'Storage Server', icon: '💾', description: 'Massive drive arrays for data-heavy S3 and backup contracts.', stats: { slots: '4U', storage: 'Extreme', pwr: 'Medium' } },
                    { name: 'GPU Compute', icon: '🧠', description: 'Equipped with parallel acceleration units for rendering and simulations.', stats: { slots: '2U', gpu: 'Extreme', pwr: 'High' } },
                    { name: 'AI Processor', icon: '⌬', description: 'Specialized tensor cores optimized for neural network training.', stats: { slots: '2U', flops: 'Extreme', heat: 'Extreme' } },
                    { name: 'Network Switch', icon: '🔌', description: 'Distributes traffic and reduces internal latency between rack assets.', stats: { slots: '1U', speed: '100G', nodes: '48' } }
                ]
            }
        ]
    },
    network: {
        sections: [
            {
                title: 'Datacenter Regions',
                howItWorks: 'The global market is split into regions, each with unique energy costs and customer profiles.',
                mechanics: ['Region-specific demand.', 'Connectivity between regions.'],
                optimization: 'Spread your infrastructure across regions to mitigate local power crises.',
                examples: 'Locating storage in low-cost Canada and compute in high-demand New York.'
            },
            {
                title: 'Traffic Routing',
                howItWorks: 'The path data takes from your servers to the end-user.',
                mechanics: ['Network hops.', 'Uplink saturation.'],
                optimization: 'Research "BGP Optimization" to reduce network overhead by 15%.',
                examples: 'Selecting a premium Tier-1 carrier to ensure high-priority traffic is never dropped.'
            },
            {
                title: 'Latency',
                howItWorks: 'The time delay in data transit. Critical for real-time applications.',
                mechanics: ['Ping (ms).', 'Distance-to-client.'],
                optimization: 'Host gaming/VOIP contracts on servers physically closest to the region center.',
                examples: 'Achieving <10ms latency for a German banking client by using an Berlin edge-node.'
            },
            {
                title: 'Bandwidth',
                howItWorks: 'The total volume of data that can be transmitted per second.',
                mechanics: ['Throughput (Gbps).', 'Peering limits.'],
                optimization: 'Install high-end Network Switches to prevent bandwidth bottlenecks in dense racks.',
                examples: 'Upgrading a 10G uplink to 100G to support a massive video streaming surge.'
            }
        ]
    },
    economy: {
        sections: [
            {
                title: 'Revenue System',
                howItWorks: 'You earn money through SLA (Service Level Agreement) contracts.',
                mechanics: ['Billing cycles.', 'Automatic collection.'],
                optimization: 'Aggressively pursue "Whale" status to unlock high-tier VIP contracts.',
                examples: 'Earning $50,000 hourly from a single Fortune 500 contract.'
            },
            {
                title: 'Hosting Contracts',
                howItWorks: 'Legal agreements to provide resources for a fixed period.',
                mechanics: ['Reliability requirements.', 'Penalties for breach.'],
                optimization: 'Diversify your contracts so that a single node failure doesn\'t ruin your reputation.',
                examples: 'Rejecting a high-pay contract because your current cooling cannot handle the heat load.'
            },
            {
                title: 'Operating Costs',
                howItWorks: 'The ongoing expenses required to keep the lights on.',
                mechanics: ['Electricity, Rent, Bandwidth, Salaries.'],
                optimization: 'Automate maintenance tasks to reduce the count of expensive Tier-3 Engineers.',
                examples: 'Using AI-assisted power management to reduce OpEx by 12%.'
            },
            {
                title: 'Market Demand',
                howItWorks: 'The global need for compute fluctuates based on world events.',
                mechanics: ['Demand multipliers.', 'Tech trends (AI, Crypto, Web).'],
                optimization: 'Pivot your hardware towards high-demand sectors during "Surge" events.',
                examples: 'Converting old storage servers into GPU nodes during the "Generative AI Boom".'
            }
        ]
    },
    strategy: {
        sections: [
            {
                title: 'Scaling Infrastructure',
                howItWorks: 'The art of growing your capacity without becoming insolvent.',
                mechanics: ['Horizontal vs Vertical scaling.'],
                optimization: 'Scaling horizontally (more rooms) is better for low-latency; Vertical (42U racks) is better for high-density compute.',
                examples: 'Doubling revenue by specializing in high-density rack configurations.'
            },
            {
                title: 'R&D Specialization',
                howItWorks: 'Unlocking technologies that change your operational efficiency.',
                mechanics: ['Research trees.', 'Specialization points.'],
                optimization: 'Focus deep into one tree (e.g., Performance) rather than spreading points thin.',
                examples: 'Unlocking "Neural-Cooling" to allow AI Processors to run 40% cooler.'
            },
            {
                title: 'Infrastructure Optimization',
                howItWorks: 'Fine-tuning existing assets to squeeze out every drop of profit.',
                mechanics: ['PUE optimization.', 'Thermal balancing.'],
                optimization: 'Arrange racks in "Hot/Cold aisles" to improve cooling efficiency by 25%.',
                examples: 'Manually adjusting server voltages to reduce energy consumption during off-peak hours.'
            },
            {
                title: 'Market Expansion',
                howItWorks: 'Opening new datacenters in foreign territories.',
                mechanics: ['Global market share.', 'Multi-region redundancy.'],
                optimization: 'Expand into regions with "Green Subsidies" to get cash rebates on new hardware.',
                examples: 'Securing global dominance by holding facilities in every major continent.'
            }
        ]
    },
    events: {
        sections: [
            {
                title: 'Power Crisis',
                howItWorks: 'The regional grid fails or energy prices skyrocket.',
                mechanics: ['Grid instability.', 'Throttled capacity.'],
                optimization: 'Have enough battery backup to sustain critical SLAs for at least 4 hours.',
                examples: 'Staying online while all AI competitors crash during the "Great Tokyo Blackout".'
            },
            {
                title: 'Network Congestion',
                howItWorks: 'Universal internet traffic floods the hubs, causing extreme latency.',
                mechanics: ['Packet loss.', 'Uptime penalties.'],
                optimization: 'Use "Private Peering" to bypass congested public internet backbones.',
                examples: 'Safeguarding high-tier contracts during a global DDoS event.'
            },
            {
                title: 'Cloud Demand Surge',
                howItWorks: 'A sudden global event causes everyone to need more compute.',
                mechanics: ['200% contract payouts.', 'Instant provisioning requirements.'],
                optimization: 'Always keep "Dark Fiber" (unused racks) ready to instantly deploy during a surge.',
                examples: 'Earning 5 million dollars in 24 hours during the "Global VR Launch".'
            },
            {
                title: 'Hardware Failure',
                howItWorks: 'Individual nodes fail due to age or stress.',
                mechanics: ['MTBF (Time before failure).', 'Data loss potential.'],
                optimization: 'Set up "Failover Clusters" so that a hardware crash automatically migrates the contract.',
                examples: 'Automatically replacing a failed GPU node before the client notices any downtime.'
            }
        ]
    }
};

const activeCategory = computed(() => {
    return categories.find(c => c.id === activeCategoryId.value) || categories[0];
});

const filteredSections = computed(() => {
    const data = wikiData[activeCategoryId.value]?.sections || [];
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return data;

    return data.filter(section => {
        const inTitle = section.title.toLowerCase().includes(query);
        const inWorks = section.howItWorks?.toLowerCase().includes(query);
        const inMech = section.mechanics?.some(m => m.toLowerCase().includes(query));
        const inItems = section.items?.some(item => 
            item.name.toLowerCase().includes(query) || 
            item.description.toLowerCase().includes(query)
        );
        return inTitle || inWorks || inMech || inItems;
    });
});
</script>

<style scoped>
.wiki-view {
    height: 100%;
    background: var(--ds-bg-void);
}

.wiki-layout {
    display: flex;
    height: 100%;
    overflow: hidden;
}

/* Internal Sidebar */
.wiki-nav {
    width: 280px;
    background: var(--ds-bg-base);
    border-right: 1px solid var(--ds-sidebar-border);
    display: flex;
    flex-direction: column;
}

.wiki-nav-header {
    padding: 32px 24px;
    border-bottom: 1px solid var(--ds-sidebar-border);
}

.wiki-nav-header h2 {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    margin-bottom: 16px;
    color: var(--ds-accent);
}

.search-box {
    position: relative;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.4;
    font-size: 0.8rem;
}

.search-box input {
    width: 100%;
    background: var(--ds-bg-void);
    border: 1px solid var(--ds-sidebar-border);
    border-radius: var(--ds-radius-md);
    padding: 8px 8px 8px 30px;
    color: #fff;
    font-family: var(--ds-font-mono);
    font-size: 0.75rem;
}

.wiki-nav-groups {
    padding: 16px 12px;
    flex: 1;
    overflow-y: auto;
}

.wiki-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--ds-radius-md);
    cursor: pointer;
    color: var(--ds-sidebar-text);
    transition: all 0.2s;
    margin-bottom: 4px;
}

.wiki-nav-item:hover {
    background: rgba(255, 255, 255, 0.03);
    color: #fff;
}

.wiki-nav-item.is-active {
    background: var(--ds-sidebar-active);
    color: #fff;
    box-shadow: inset 4px 0 0 var(--ds-accent);
}

.nav-label {
    font-size: 0.825rem;
    font-weight: 600;
}

.wiki-nav-footer {
    padding: 20px 24px;
    font-family: var(--ds-font-mono);
    font-size: 0.6rem;
    border-top: 1px solid var(--ds-sidebar-border);
    opacity: 0.4;
}

/* Content Area */
.wiki-main-content {
    flex: 1;
    overflow-y: auto;
    background: var(--ds-bg-void);
}

.wiki-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 48px 64px;
}

.page-header {
    margin-bottom: 56px;
    border-bottom: 1px solid var(--ds-sidebar-border);
    padding-bottom: 32px;
}

.page-meta {
    display: block;
    font-size: 0.65rem;
    font-family: var(--ds-font-mono);
    margin-bottom: 8px;
    opacity: 0.5;
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 12px;
    letter-spacing: -0.02em;
}

.summary-text {
    font-size: 1.125rem;
    color: var(--ds-sidebar-text);
    line-height: 1.5;
}

.wiki-section {
    margin-bottom: 80px;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.title-marker {
    width: 6px;
    height: 28px;
    background: var(--ds-accent);
    border-radius: 3px;
}

.info-block {
    margin-bottom: 32px;
}

.info-block h3 {
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    color: var(--ds-accent);
    margin-bottom: 12px;
    opacity: 0.8;
}

.info-block p, .info-block li {
    font-size: 1rem;
    color: var(--ds-sidebar-text);
    line-height: 1.7;
}

.mechanic-list {
    list-style: none;
    padding: 0;
}

.mechanic-list li::before {
    content: '▶';
    font-size: 0.7rem;
    margin-right: 12px;
    color: var(--ds-accent);
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin: 24px 0 40px;
}

.wiki-card {
    background: var(--ds-bg-base);
    border: 1px solid var(--ds-sidebar-border);
    border-radius: var(--ds-radius-lg);
    padding: 24px;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.card-header h3 {
    font-size: 1rem;
    margin: 0;
}

.card-stats {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.stat-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    font-family: var(--ds-font-mono);
    margin-bottom: 4px;
}

.extra-info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: 40px;
}

.dark-block {
    background: rgba(255, 255, 255, 0.03);
    padding: 24px;
    border-radius: var(--ds-radius-lg);
    border-left: 3px solid var(--ds-accent);
}

.wiki-slide-enter-active,
.wiki-slide-leave-active {
    transition: all 0.25s ease-out;
}

.wiki-slide-enter-from { opacity: 0; transform: translateY(10px); }
.wiki-slide-leave-to { opacity: 0; transform: translateY(-10px); }

.scrollbar-hidden::-webkit-scrollbar { width: 0; height: 0; }
</style>
