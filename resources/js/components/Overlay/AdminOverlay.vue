<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="admin-overlay glass-panel animation-fade-in">
            <div class="overlay-header">
                <div class="header-main">
                    <div class="header-icon">🛡️</div>
                    <div class="header-text">
                        <h2>Obsidian Architecture</h2>
                        <p class="subtitle">Live-Ops & Engine Control Center</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="save-all-btn" @click="saveChanges" :disabled="!hasChanges || loading">
                        {{ loading ? 'Saving...' : 'Commit Changes' }}
                    </button>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </div>
            </div>

            <div class="admin-content">
                <!-- Navigation Sidebar -->
                <div class="admin-sidebar">
                    <button 
                        v-for="(label, key) in groups" 
                        :key="key"
                        class="nav-item"
                        :class="{ active: activeGroup === key }"
                        @click="activeGroup = key"
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Main Config Editor -->
                <div class="editor-main">
                    
                    <!-- USER MANAGEMENT -->
                    <div v-if="activeGroup === 'users'" class="admin-section">
                        <div class="section-header">
                            <h3>User Database</h3>
                            <div class="search-box">
                                <input v-model="userSearch" @keyup.enter="fetchUsers" placeholder="Search users..." class="admin-input-text" />
                                <button @click="fetchUsers" class="search-btn">🔍</button>
                            </div>
                        </div>

                        <div class="data-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name / Email</th>
                                        <th>Balance</th>
                                        <th>Level / XP</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="mono-text">{{ user.id }}</td>
                                        <td>
                                            <div v-if="!user.isEditing">
                                                <div class="row-title">{{ user.name }}</div>
                                                <div class="row-sub">{{ user.email }}</div>
                                            </div>
                                            <div v-else class="edit-mode">
                                                <input v-model="user.name" class="admin-input-sm" />
                                                <input v-model="user.email" class="admin-input-sm" />
                                            </div>
                                        </td>
                                        <td>
                                            <span v-if="!user.isEditing" :class="{ 'text-danger': Number(user.economy?.balance) < 0 }">
                                                ${{ Number(user.economy?.balance || 0).toFixed(2) }}
                                            </span>
                                            <input v-else type="number" v-model.number="user.economy.balance" class="admin-input-sm" />
                                        </td>
                                        <td>
                                            <div v-if="!user.isEditing">Lvl {{ user.economy?.level }} ({{ user.economy?.experience }} XP)</div>
                                            <div v-else class="edit-cols">
                                                <input type="number" v-model.number="user.economy.level" class="admin-input-xs" placeholder="Lvl" />
                                                <input type="number" v-model.number="user.economy.experience" class="admin-input-xs" placeholder="XP" />
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <template v-if="!user.isEditing">
                                                <button class="icon-btn" @click="user.isEditing = true" title="Edit">✏️</button>
                                                <button class="icon-btn" @click="inspectUserServers(user.id)" title="View Servers">🖥️</button>
                                                <button class="icon-btn" @click="openGrantModal(user)" title="Grant Resource">🎁</button>
                                            </template>
                                            <template v-else>
                                                <button class="icon-btn success" @click="updateUser(user)">💾</button>
                                                <button class="icon-btn" @click="user.isEditing = false">❌</button>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SERVER INSPECTOR -->
                    <div v-else-if="activeGroup === 'servers'" class="admin-section">
                         <div class="section-header">
                            <h3>Server Inspector {{ serverSearchUser ? '(Filtered)' : '' }}</h3>
                            <button v-if="serverSearchUser" @click="serverSearchUser = null; servers = []" class="filter-btn">Clear Filter</button>
                        </div>

                        <div class="data-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Status</th>
                                        <th>Health</th>
                                        <th>Rack/Room</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="server in servers" :key="server.id">
                                        <td class="mono-text">{{ server.id }}</td>
                                        <td>
                                            <select v-if="server.isEditing" v-model="server.status" class="admin-select-sm">
                                                <option value="online">Online</option>
                                                <option value="offline">Offline</option>
                                                <option value="provisioning">Provisioning</option>
                                                <option value="maintenance">Maintenance</option>
                                                <option value="degraded">Degraded</option>
                                                <option value="damaged">Damaged</option>
                                            </select>
                                            <span v-else class="status-badge" :class="server.status">{{ server.status }}</span>
                                        </td>
                                        <td>
                                            <input v-if="server.isEditing" type="number" v-model.number="server.health" class="admin-input-sm" />
                                            <div v-else class="health-bar">
                                                <div class="fill" :style="{ width: server.health + '%', background: server.health > 50 ? '#2ea043' : '#d73a49' }"></div>
                                                <span>{{ server.health }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="row-sub">{{ server.rack?.room?.name || 'Unknown' }}</div>
                                            <div class="row-sub">Rack {{ server.rack_id?.substr(0,4) }}...</div>
                                        </td>
                                        <td class="actions-cell">
                                            <button v-if="!server.isEditing" class="icon-btn" @click="server.isEditing = true">✏️</button>
                                            <template v-else>
                                                <button class="icon-btn success" @click="updateServer(server)">💾</button>
                                                <button class="icon-btn" @click="server.isEditing = false">❌</button>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- GRANT MODAL -->
                    <div v-if="resourceModal.show" class="modal-overlay">
                        <div class="modal-card">
                            <h4>Grant to {{ resourceModal.user?.name }}</h4>
                            <div class="field-group">
                                <label>Type</label>
                                <select v-model="resourceModal.type">
                                    <option value="money">Money ($)</option>
                                    <option value="xp">Experience (XP)</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Amount</label>
                                <input type="number" v-model.number="resourceModal.amount" />
                            </div>
                            <div class="modal-actions">
                                <button @click="submitGrant" class="save-all-btn">Grant</button>
                                <button @click="resourceModal.show = false" class="close-btn-sm">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeGroup === 'world_news'" class="news-templates-editor">
                        <div class="section-header">
                            <h3>World News Templates</h3>
                            <button class="add-btn" @click="addNewsTemplate">+ New Event</button>
                        </div>
                        
                        <div class="templates-list">
                            <div v-for="(template, index) in localConfigs.world_event_templates" :key="index" class="template-card">
                                <div class="card-header">
                                    <input v-model="template.title" placeholder="Event Title" class="admin-input-h4" />
                                    <div class="card-actions">
                                        <button class="trigger-btn" @click="triggerEvent(index)" title="Force Launch Now">🚀</button>
                                        <button class="remove-btn" @click="removeNewsTemplate(index)">&times;</button>
                                    </div>
                                </div>
                                <textarea v-model="template.description" placeholder="Description shown in ticker..." class="admin-textarea"></textarea>
                                
                                <div class="inline-stats">
                                    <div class="stat-box">
                                        <label>Type</label>
                                        <select v-model="template.type">
                                            <option value="crisis">Crisis</option>
                                            <option value="boom">Boom</option>
                                            <option value="news">News</option>
                                        </select>
                                    </div>
                                    <div class="stat-box">
                                        <label>Modifier</label>
                                        <select v-model="template.modifier_type">
                                            <option value="power_cost">Power Cost</option>
                                            <option value="order_frequency">Order Frequency</option>
                                            <option value="repair_cost">Repair Cost</option>
                                            <option value="satisfaction_decay">Satisfaction Decay</option>
                                            <option value="order_value">Order Value</option>
                                        </select>
                                    </div>
                                    <div class="stat-box">
                                        <label>Value</label>
                                        <input type="number" step="0.1" v-model.number="template.modifier_value" />
                                    </div>
                                    <div class="stat-box">
                                        <label>Duration (m)</label>
                                        <input type="number" v-model.number="template.duration_minutes" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeGroup === 'engine'" class="engine-settings">
                        <h3>Core Engine Constants</h3>
                        <div class="config-grid">
                            <div v-for="(val, key) in localConfigs.engine_constants" :key="key" class="config-row">
                                <div class="c-label">
                                    <span class="key-name">{{ key.replace(/_/g, ' ') }}</span>
                                    <span class="key-raw">{{ key }}</span>
                                </div>
                                <input type="number" step="0.01" v-model.number="localConfigs.engine_constants[key]" class="admin-input" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeGroup === 'energy'" class="energy-settings">
                        <h3>Economy: Energy Parameters</h3>
                        <div class="config-grid">
                            <div v-for="(val, key) in localConfigs.energy_market_settings" :key="key" class="config-row">
                                <div class="c-label">
                                    <span class="key-name">{{ key.replace(/_/g, ' ') }}</span>
                                    <span class="key-raw">{{ key }}</span>
                                </div>
                                <input type="number" step="0.0001" v-model.number="localConfigs.energy_market_settings[key]" class="admin-input" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeGroup === 'employees'" class="employee-settings">
                        <h3>Personnel Management Config</h3>
                        <div class="role-grid">
                            <div v-for="(role, id) in localConfigs.employee_types" :key="id" class="role-editor-card">
                                <h4>{{ role.name }} ({{ id }})</h4>
                                <div class="field-group">
                                    <label>Base Salary (/hr)</label>
                                    <input type="number" v-model.number="role.base_salary" />
                                </div>
                                <div class="field-group">
                                    <label>Hiring Cost</label>
                                    <input type="number" v-model.number="role.hiring_cost" />
                                </div>
                                <div class="field-group">
                                    <label>Description</label>
                                    <textarea v-model="role.description"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HARDWARE SHOP -->
                    <div v-else-if="activeGroup === 'hardware' && localConfigs.server_catalog" class="hardware-settings">
                        <div class="section-header">
                            <h3>Hardware Shop Catalog</h3>
                        </div>
                        
                        <div v-for="(models, category) in localConfigs.server_catalog" :key="category" class="category-block">
                            <h4 class="category-title">{{ category.replace(/_/g, ' ').toUpperCase() }}</h4>
                            <div class="hardware-grid">
                                <div v-for="(model, modelKey) in models" :key="modelKey" class="hardware-card">
                                    <div class="hw-header">
                                        <input v-model="model.modelName" class="hw-name-input" />
                                        <button class="remove-btn-sm" @click="deleteHardwareModel(category, modelKey)" title="Delete Item">&times;</button>
                                    </div>
                                    <div class="hw-key">{{ modelKey }}</div>
                                    
                                    <div class="hw-specs-grid">
                                        <div class="spec-field">
                                            <label>Price ($)</label>
                                            <input type="number" v-model.number="model.purchaseCost" />
                                        </div>
                                        <div class="spec-field">
                                            <label>Size (U)</label>
                                            <input type="number" v-model.number="model.sizeU" />
                                        </div>
                                        <div class="spec-field">
                                            <label>CPU (C)</label>
                                            <input type="number" v-model.number="model.cpuCores" />
                                        </div>
                                        <div class="spec-field">
                                            <label>RAM (GB)</label>
                                            <input type="number" v-model.number="model.ramGb" />
                                        </div>
                                        <div class="spec-field">
                                            <label>Storage (TB)</label>
                                            <input type="number" v-model.number="model.storageTb" />
                                        </div>
                                        <div class="spec-field">
                                            <label>Net (Mbps)</label>
                                            <input type="number" v-model.number="model.bandwidthMbps" />
                                        </div>
                                        <div class="spec-field">
                                            <label>Power (kW)</label>
                                            <input type="number" step="0.1" v-model.number="model.powerDrawKw" />
                                        </div>
                                        <div class="spec-field">
                                            <label>Heat (kW)</label>
                                            <input type="number" step="0.1" v-model.number="model.heatOutputKw" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Add New Model Card -->
                                <div class="hardware-card add-card" @click="addHardwareModel(category)">
                                    <div class="add-icon">+</div>
                                    <div>Add Model</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LOCATIONS -->
                    <div v-else-if="activeGroup === 'locations' && localConfigs.location_definitions" class="locations-settings">
                        <div class="section-header">
                            <h3>Properties Management</h3>
                        </div>
                        <div class="locations-grid">
                            <div v-for="(loc, key) in localConfigs.location_definitions" :key="key" class="location-card">
                                <h4>{{ loc.name }} ({{ key }})</h4>
                                <div class="field-group">
                                    <label>Max Racks</label>
                                    <input type="number" v-model.number="loc.max_racks" />
                                </div>
                                <div class="field-group">
                                    <label>Max Power (kW)</label>
                                    <input type="number" v-model.number="loc.max_power_kw" />
                                </div>
                                <div class="field-group">
                                    <label>Rent ($/hr)</label>
                                    <input type="number" v-model.number="loc.rent_per_hour" />
                                </div>
                                <div class="field-group">
                                    <label>Unlock Cost ($)</label>
                                    <input type="number" v-model.number="loc.unlock_cost" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MARKETING CAMPAIGNS -->
                    <div v-else-if="activeGroup === 'marketing' && localConfigs.marketing_campaigns" class="marketing-settings">
                        <div class="section-header">
                            <h3>Marketing Campaigns</h3>
                        </div>
                        <div class="grid-2-col">
                            <div v-for="(camp, key) in localConfigs.marketing_campaigns" :key="key" class="role-editor-card">
                                <h4>{{ camp.name }} ({{ key }})</h4>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label>Cost ($)</label>
                                        <input type="number" v-model.number="camp.cost" />
                                    </div>
                                    <div class="field-group">
                                        <label>Time (m)</label>
                                        <input type="number" v-model.number="camp.duration" />
                                    </div>
                                </div>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label>Effectiveness</label>
                                        <input type="number" step="0.1" v-model.number="camp.effectiveness" />
                                    </div>
                                    <div class="field-group">
                                        <label>Rep Gain</label>
                                        <input type="number" step="0.1" v-model.number="camp.reputation_gain" />
                                    </div>
                                </div>
                                <div class="field-group">
                                    <label>Min Reputation</label>
                                    <input type="number" v-model.number="camp.min_reputation" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PRODUCT DEFINITIONS (Next Gen Scripts) -->
                    <div v-else-if="activeGroup === 'products' && localConfigs.product_definitions" class="products-settings">
                        <div class="section-header">
                            <h3>Product / Order Scripts</h3>
                        </div>
                        <div class="grid-2-col">
                            <div v-for="(prod, key) in localConfigs.product_definitions" :key="key" class="role-editor-card">
                                <h4>{{ prod.name || key }} ({{ key }})</h4>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label>Min Level</label>
                                        <input type="number" v-model.number="prod.min_level" />
                                    </div>
                                    <div class="field-group">
                                        <label>Base Price</label>
                                        <input type="number" v-model.number="prod.base_price" />
                                    </div>
                                </div>
                                <h5>Resource Requirements (Ranges)</h5>
                                <div class="req-grid" v-if="prod.requirements">
                                    <div v-for="(range, rKey) in prod.requirements" :key="rKey" class="req-box">
                                        <label>{{ rKey }}</label>
                                        <div class="range-inputs" v-if="Array.isArray(range)">
                                            <input type="number" v-model.number="range[0]" class="short-input" />
                                            <span>-</span>
                                            <input type="number" v-model.number="range[1]" class="short-input" />
                                        </div>
                                        <input v-else type="number" v-model.number="prod.requirements[rKey]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESEARCH TREE -->
                    <div v-else-if="activeGroup === 'research' && localConfigs.research_tree" class="research-settings">
                        <div class="section-header">
                            <h3>Research Tree Config</h3>
                            <button class="btn btn-sm btn-primary" @click="addResearchTech">+ Add Tech</button>
                        </div>
                        <div class="research-list">
                            <div v-for="(tech, id) in localConfigs.research_tree" :key="id" class="tech-card">
                                <div class="tech-header">
                                    <input v-model="tech.name" class="tech-name-input" />
                                    <div class="tech-meta">
                                        <span class="tech-id">{{ id }}</span>
                                        <button class="remove-btn-sm" @click="deleteResearchTech(id)">&times;</button>
                                    </div>
                                </div>
                                <textarea v-model="tech.description" class="tech-desc-input" rows="2"></textarea>
                                
                                <div class="tech-params">
                                    <div class="param">
                                        <label>Cost ($)</label>
                                        <input type="number" v-model.number="tech.cost" />
                                    </div>
                                    <div class="param">
                                        <label>Time (s)</label>
                                        <input type="number" v-model.number="tech.duration" />
                                    </div>
                                    <div class="param">
                                        <label>Effect Type</label>
                                        <select v-model="tech.effect.type">
                                            <option value="power_efficiency">Power Eff.</option>
                                            <option value="provisioning_speed">Setup Speed</option>
                                            <option value="lifespan_bonus">Lifespan</option>
                                            <option value="rep_gain_multiplier">Rep Gain</option>
                                            <option value="unlock">Unlock Feature</option>
                                        </select>
                                    </div>
                                    <div class="param">
                                        <label>Effect Val</label>
                                        <input v-model="tech.effect.value" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AUCTION MANAGER -->
                    <div v-else-if="activeGroup === 'auctions'" class="admin-section">
                        <AuctionArchitect />
                    </div>

                    <div v-else class="empty-state">
                        <div class="icon">⚙️</div>
                        <p>Select a category to modify engine parameters.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, provide } from 'vue';
import api from '../../utils/api';
import { useToastStore } from '../../stores/toast';
import AuctionArchitect from '../Admin/Economy/AuctionArchitect.vue';

const emit = defineEmits(['close']);
const toast = useToastStore();
const loading = ref(false);
const activeGroup = ref('world_news');
const hasChanges = ref(false);

provide('adminContext', {
    addToast: (msg, type) => toast[type](msg),
    setGlobalLoading: (val) => loading.value = val,
    requestConfirm: (msg, cb) => { if (confirm(msg)) cb(); }
});

const groups = {
    users: '👤 User Management',
    servers: '🖥️ Server Inspector',
    world_news: '🌍 World News',
    engine: '⚙️ Engine Constants',
    energy: '⚡ Energy Market',
    employees: '👥 Personnel',
    hardware: '🖥️ Hardware Shop',
    research: '🔬 Research Tree',
    locations: 'buildings Locations',
    marketing: '📣 Marketing',
    products: '📦 Products / Scripts',
    auctions: '🔨 Auction Manager'
};

const localConfigs = ref({
    world_event_templates: [],
    energy_market_settings: {},
    engine_constants: {},
    employee_types: {}
});

// User Management State
const users = ref([]);
const userSearch = ref('');
const userPage = ref(1);
const selectedUser = ref(null); // For editing
const resourceModal = ref({ show: false, user: null, type: 'money', amount: 1000 });

// Server Management State
const servers = ref([]);
const serverSearchUser = ref(null); // Filter by user ID

const originalConfigs = ref({});

onMounted(async () => {
    await fetchConfigs();
});

// Watch triggers fetch when tab changes
watch(activeGroup, async (newGroup) => {
    if (newGroup === 'users') {
        await fetchUsers();
    } else if (newGroup === 'servers' && serverSearchUser.value) {
        await fetchServers(serverSearchUser.value);
    }
});

async function fetchConfigs() {
    loading.value = true;
    try {
        const response = await api.get('/admin/configs');
        if (response.success) {
            const all = {};
            Object.values(response.configs).forEach(group => {
                group.forEach(cfg => {
                    localConfigs.value[cfg.key] = JSON.parse(JSON.stringify(cfg.value));
                    all[cfg.key] = JSON.parse(JSON.stringify(cfg.value));
                });
            });
            originalConfigs.value = JSON.parse(JSON.stringify(all));
            hasChanges.value = false;
        }
    } catch (error) {
        toast.error('Failed to fetch admin configs');
    } finally {
        loading.value = false;
    }
}

// ─────────────────────────────────────────────────────────
// USER ACTIONS
// ─────────────────────────────────────────────────────────
async function fetchUsers() {
    loading.value = true;
    try {
        const response = await api.get('/admin/users', { params: { search: userSearch.value, page: userPage.value } });
        if (response.success) {
            users.value = response.users.data;
        }
    } catch (e) { toast.error('Failed to load users'); }
    finally { loading.value = false; }
}

async function updateUser(user) {
    if (!confirm(`Save changes for ${user.name}?`)) return;
    loading.value = true;
    try {
        const response = await api.post(`/admin/users/${user.id}/update`, {
            name: user.name,
            email: user.email,
            balance: user.economy?.balance,
            level: user.economy?.level,
            xp: user.economy?.experience
        });
        if (response.success) {
            toast.success('User updated');
            user.isEditing = false;
        }
    } catch (e) { toast.error('Update failed'); }
    finally { loading.value = false; }
}

function openGrantModal(user) {
    resourceModal.value = { show: true, user: user, type: 'money', amount: 1000 };
}

async function submitGrant() {
    loading.value = true;
    try {
        const { user, type, amount } = resourceModal.value;
        await api.post(`/admin/users/${user.id}/give`, { type, amount });
        toast.success(`Granted ${amount} ${type} to ${user.name}`);
        resourceModal.value.show = false;
        // Refresh user list to show new balance
        await fetchUsers();
    } catch (e) { toast.error('Grant failed'); }
    finally { loading.value = false; }
}

function inspectUserServers(userId) {
    serverSearchUser.value = userId;
    activeGroup.value = 'servers';
    fetchServers(userId);
}

// ─────────────────────────────────────────────────────────
// SERVER ACTIONS
// ─────────────────────────────────────────────────────────
async function fetchServers(userId) {
    loading.value = true;
    try {
        const response = await api.get(`/admin/users/${userId}/servers`);
        if (response.success) {
            servers.value = response.servers.data;
        }
    } catch (e) { toast.error('Failed to load servers'); }
    finally { loading.value = false; }
}

async function updateServer(server) {
    try {
        await api.post(`/admin/servers/${server.id}/update`, {
            status: server.status,
            health: server.health
        });
        toast.success('Server updated');
        server.isEditing = false;
    } catch (e) { toast.error('Update failed'); }
}

// ─────────────────────────────────────────────────────────
// CONFIG ACTIONS
// ─────────────────────────────────────────────────────────
watch(localConfigs, () => {
    hasChanges.value = true;
}, { deep: true });

async function saveChanges() {
    loading.value = true;
    try {
        for (const key in localConfigs.value) {
            if (JSON.stringify(localConfigs.value[key]) !== JSON.stringify(originalConfigs.value[key])) {
                await api.post('/admin/configs/update', {
                    key: key,
                    value: localConfigs.value[key]
                });
            }
        }
        toast.success('Configs saved.');
        originalConfigs.value = JSON.parse(JSON.stringify(localConfigs.value));
        hasChanges.value = false;
    } catch (error) {
        toast.error('Failed to save changes');
    } finally {
        loading.value = false;
    }
}

function addNewsTemplate() {
    localConfigs.value.world_event_templates.unshift({
        title: 'New Event',
        description: 'New Description',
        type: 'news',
        modifier_type: 'power_cost',
        modifier_value: 1.0,
        duration_minutes: 60
    });
}

function removeNewsTemplate(index) {
    localConfigs.value.world_event_templates.splice(index, 1);
}

async function triggerEvent(index) {
    if (!confirm('LAUNCH EVENT: Are you sure?')) return;
    loading.value = true;
    try {
        const response = await api.post('/admin/world-news/trigger', { template_index: index });
        if (response.success) toast.success(`Event '${response.event.title}' launched!`);
    } catch (error) {
        toast.error('Failed to launch event');
    } finally {
        loading.value = false;
    }
}

function addHardwareModel(category) {
    const key = prompt('Enter UNIQUE model key (e.g. vs_ultimate):');
    if (!key) return;
    
    // safe check
    if (localConfigs.value.server_catalog[category][key]) {
        toast.error('Key already exists!');
        return;
    }

    localConfigs.value.server_catalog[category][key] = {
        modelName: 'New Model',
        sizeU: 1,
        purchaseCost: 1000,
        cpuCores: 4,
        ramGb: 16,
        storageTb: 1,
        bandwidthMbps: 1000,
        powerDrawKw: 0.5,
        heatOutputKw: 0.4,
        vserverCapacity: 0
    };
    // Force reactivity update if needed, though Vue3 proxy usually handles it
    hasChanges.value = true;
}

function deleteHardwareModel(category, key) {
    if (!confirm(`Delete hardware model '${key}'? This may break existing servers using this model.`)) return;
    delete localConfigs.value.server_catalog[category][key];
    hasChanges.value = true;
}

function addResearchTech() {
    const id = prompt('Enter UNIQUE Tech ID (e.g. data_analytics_v1):');
    if (!id) return;
    
    if (localConfigs.value.research_tree[id]) {
        toast.error('ID already exists!');
        return;
    }

    localConfigs.value.research_tree[id] = {
        name: 'New Technology',
        description: 'Description here...',
        cost: 1000,
        duration: 300,
        category: 'infrastructure',
        prerequisites: [],
        effect: { type: 'power_efficiency', value: 0.05 }
    };
    hasChanges.value = true;
}

function deleteResearchTech(id) {
    if (!confirm(`Delete tech '${id}'?`)) return;
    delete localConfigs.value.research_tree[id];
    hasChanges.value = true;
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(20px);
    z-index: 5000;
    display: flex; justify-content: center; align-items: center;
    padding: 40px;
}

.admin-overlay {
    width: 1200px;
    height: 800px;
    max-width: 95vw;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
}

.overlay-header {
    padding: 25px 40px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex; justify-content: space-between; align-items: center;
    background: rgba(255, 255, 255, 0.02);
}

.header-main { display: flex; align-items: center; gap: 20px; }
.header-icon { font-size: 2rem; }
.header-text h2 { margin: 0; font-size: 1.5rem; color: #fff; letter-spacing: 1px; }
.subtitle { margin: 4px 0 0 0; color: #58a6ff; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; }

.header-actions { display: flex; gap: 20px; align-items: center; }
.save-all-btn {
    background: #238636; color: white; border: none; padding: 10px 24px;
    border-radius: 8px; font-weight: 800; cursor: pointer; transition: all 0.2s;
}
.save-all-btn:hover:not(:disabled) { background: #2ea043; box-shadow: 0 0 20px rgba(46, 160, 67, 0.4); }
.save-all-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.close-btn { background: none; border: none; color: #8b949e; font-size: 2rem; cursor: pointer; }

.admin-content { display: flex; flex: 1; overflow: hidden; }

.admin-sidebar {
    width: 240px;
    background: rgba(0,0,0,0.2);
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    display: flex; flex-direction: column; padding: 20px; gap: 8px;
}

.nav-item {
    background: none; border: none; color: #8b949e; padding: 12px 16px;
    border-radius: 8px; text-align: left; cursor: pointer; transition: all 0.2s;
    font-weight: 600;
}
.nav-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
.nav-item.active { background: rgba(88, 166, 255, 0.15); color: #58a6ff; }

.editor-main { flex: 1; padding: 40px; overflow-y: auto; }

.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.section-header h3 { margin: 0; color: #fff; }
.add-btn { background: transparent; color: #58a6ff; border: 1px solid #58a6ff; padding: 6px 14px; border-radius: 6px; cursor: pointer; }

.templates-list { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.template-card {
    background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 15px;
}

.card-header { display: flex; justify-content: space-between; gap: 15px; align-items: flex-start; }
.card-actions { display: flex; gap: 8px; }
.trigger-btn { background: rgba(88, 166, 255, 0.2); border: 1px solid rgba(88, 166, 255, 0.4); border-radius: 4px; cursor: pointer; padding: 4px 8px; }
.trigger-btn:hover { background: rgba(88, 166, 255, 0.5); }
.admin-input-h4 {
    background: transparent; border: none; border-bottom: 1px solid transparent;
    color: #fff; font-size: 1rem; font-weight: 700; width: 100%;
}
.admin-input-h4:focus { border-bottom-color: #58a6ff; outline: none; }

.remove-btn { background: none; border: none; color: #f85149; cursor: pointer; font-size: 1.2rem; }

.admin-textarea {
    background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #8b949e; padding: 10px; font-size: 0.85rem; height: 80px; resize: none;
}

.inline-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.stat-box { display: flex; flex-direction: column; gap: 4px; }
.stat-box label { font-size: 0.65rem; color: #484f58; text-transform: uppercase; font-weight: 800; }
.stat-box select, .stat-box input {
    background: #0d1117; color: #fff; border: 1px solid #30363d; padding: 6px; border-radius: 4px;
}

.config-grid { display: flex; flex-direction: column; gap: 15px; }
.config-row {
    display: flex; justify-content: space-between; align-items: center;
    background: rgba(255,255,255,0.02); padding: 15px 20px; border-radius: 10px;
}
.c-label { display: flex; flex-direction: column; }
.key-name { color: #fff; font-weight: 600; text-transform: capitalize; }
.key-raw { color: #484f58; font-size: 0.7rem; font-family: monospace; }
.admin-input {
    background: #0d1117; border: 1px solid #30363d; color: #58a6ff;
    padding: 8px 12px; border-radius: 6px; font-family: 'JetBrains Mono', monospace; width: 120px; text-align: right;
}

.role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.role-editor-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; padding: 20px;
}
.field-group { margin-top: 15px; display: flex; flex-direction: column; gap: 5px; }
.field-group label { font-size: 0.7rem; color: #8b949e; }
.field-group input, .field-group textarea {
    background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 8px; border-radius: 6px;
}

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #484f58; }
.empty-state .icon { font-size: 4rem; margin-bottom: 20px; opacity: 0.2; }

@keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

/* NEW ADMIN STYLES */
.admin-section { height: 100%; display: flex; flex-direction: column; }
.search-box { display: flex; gap: 10px; }
.admin-input-text { background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 6px 12px; border-radius: 6px; width: 200px; }
.search-btn { background: #30363d; border: none; cursor: pointer; border-radius: 6px; padding: 0 10px; }

.data-table-container { flex: 1; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); }
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th { text-align: left; padding: 12px; background: rgba(255,255,255,0.05); color: #8b949e; font-size: 0.75rem; text-transform: uppercase; position: sticky; top: 0; }
.admin-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #c9d1d9; vertical-align: middle; }
.admin-table tr:hover { background: rgba(255,255,255,0.02); }

.mono-text { font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #8b949e; }
.row-title { font-weight: 600; color: #fff; }
.row-sub { font-size: 0.75rem; color: #8b949e; }
.text-danger { color: #f85149; }

.icon-btn { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 4px; border-radius: 4px; transition: background 0.2s; }
.icon-btn:hover { background: rgba(255,255,255,0.1); }
.icon-btn.success { color: #2ea043; }

.admin-input-sm { background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 4px 8px; border-radius: 4px; width: 100%; }
.admin-input-xs { width: 40px; background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 4px; margin-right: 4px; border-radius: 4px; }
.admin-select-sm { background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 4px; border-radius: 4px; }

.status-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; background: #333; }
.status-badge.online { background: rgba(46, 160, 67, 0.2); color: #3fb950; }
.status-badge.offline { background: rgba(248, 81, 73, 0.2); color: #f85149; }
.status-badge.provisioning { background: rgba(219, 171, 9, 0.2); color: #eac54f; }

.health-bar { width: 100%; height: 16px; background: #21262d; border-radius: 4px; position: relative; overflow: hidden; }
.health-bar .fill { height: 100%; }
.health-bar span { position: absolute; top: 0; left: 0; width: 100%; text-align: center; font-size: 0.7rem; line-height: 16px; color: #fff; text-shadow: 0 0 2px black; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 6000; }
.modal-card { background: #161b22; border: 1px solid #30363d; padding: 30px; border-radius: 12px; width: 400px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 0 50px rgba(0,0,0,0.5); }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
.close-btn-sm { background: none; border: 1px solid #30363d; color: #c9d1d9; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
.close-btn-sm:hover { background: #21262d; }

/* Hardware Shop Styles */
.hardware-settings .category-block { margin-bottom: 30px; }
.category-title { color: #58a6ff; border-bottom: 1px solid rgba(88,166,255,0.2); padding-bottom: 8px; margin-bottom: 15px; font-weight: 800; letter-spacing: 1px; font-size: 0.9rem; }
.hardware-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
.hardware-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; gap: 10px; transition: border-color 0.2s; }
.hardware-card:hover { border-color: rgba(255,255,255,0.2); }
.add-card { border-style: dashed; align-items: center; justify-content: center; cursor: pointer; opacity: 0.6; min-height: 200px; transition: all 0.2s; }
.add-card:hover { opacity: 1; border-color: #58a6ff; background: rgba(88,166,255,0.05); }
.add-icon { font-size: 2rem; color: #58a6ff; }
.hw-header { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.hw-name-input { background: transparent; border: none; font-weight: 700; color: #fff; width: 100%; border-bottom: 1px solid transparent; font-size: 0.95rem; }
.hw-name-input:focus { border-bottom-color: #58a6ff; outline: none; }
.hw-key { font-size: 0.7rem; color: #8b949e; font-family: monospace; user-select: text; margin-top: -5px; }
.hw-specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 5px; }
.spec-field { display: flex; flex-direction: column; gap: 2px; }
.spec-field label { font-size: 0.65rem; color: #484f58; text-transform: uppercase; font-weight: 700; }
.spec-field input { background: #0d1117; border: 1px solid #30363d; color: #c9d1d9; padding: 4px 6px; border-radius: 4px; width: 100%; font-family: monospace; font-size: 0.8rem; }
.spec-field input:focus { border-color: #58a6ff; color: #fff; }
.remove-btn-sm { background: none; border: none; color: #f85149; font-size: 1.2rem; cursor: pointer; padding: 0 4px; line-height: 1; }
.remove-btn-sm:hover { color: #ff6a6a; background: rgba(248,81,73,0.1); border-radius: 4px; }

/* Research & Locations */
.research-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 15px; }
.tech-card { background: rgba(13,17,23,0.5); border: 1px solid #30363d; padding: 15px; border-radius: 6px; }
.tech-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.tech-name-input { background: none; border: none; font-weight: 700; color: #7ee787; font-size: 1rem; width: 60%; }
.tech-meta { display: flex; align-items: center; gap: 8px; }
.tech-id { font-family: monospace; font-size: 0.7rem; color: #8b949e; }
.tech-desc-input { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid #30363d; color: #c9d1d9; font-size: 0.8rem; padding: 6px; border-radius: 4px; margin-bottom: 10px; resize: vertical; }
.tech-params { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; }
.tech-params .param { display: flex; flex-direction: column; }
.tech-params label { font-size: 0.65rem; color: #8b949e; text-transform: uppercase; }
.tech-params input, .tech-params select { background: #0d1117; border: 1px solid #30363d; color: #c9d1d9; padding: 4px; font-size: 0.8rem; border-radius: 4px; width: 100%; }

.locations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
.location-card { background: rgba(13,17,23,0.5); border: 1px solid #30363d; padding: 15px; border-radius: 6px; }
.location-card h4 { color: #d2a8ff; margin-bottom: 15px; border-bottom: 1px solid rgba(210,168,255,0.2); padding-bottom: 5px; }

/* Marketing & Products */
.grid-2-col { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 15px; }
.field-row { display: flex; gap: 15px; margin-bottom: 10px; }
.field-row .field-group { flex: 1; margin-top: 0; }
.req-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; }
.req-box { display: flex; flex-direction: column; }
.req-box label { font-size: 0.65rem; color: #8b949e; text-transform: uppercase; }
.req-box input { background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 4px; border-radius: 4px; width: 100%; font-size: 0.8rem; }
.range-inputs { display: flex; align-items: center; gap: 4px; }
.short-input { width: 50px !important; text-align: center; }
</style>
