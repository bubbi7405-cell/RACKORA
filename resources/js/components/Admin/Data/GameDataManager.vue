<template>
  <div class="gdm-container">
    <!-- HEADER with counts -->
    <div class="gdm-header">
      <div class="gdm-title-row">
        <div>
          <h2 class="gdm-title">{{ entityTitle }}</h2>
          <p class="gdm-subtitle">{{ entitySubtitle }}</p>
        </div>
        <button @click="loadCounts" class="gdm-refresh-btn" :class="{ 'spin': countsLoading }">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Refresh
        </button>
      </div>

      <!-- ENTITY STATS GRID -->
      <div class="stats-grid" v-if="counts">
        <div v-for="stat in statCards" :key="stat.key" class="stat-card" :class="{ 'active': activeEntity === stat.key }" @click="switchEntity(stat.key)">
          <span class="stat-icon" v-html="stat.icon"></span>
          <div class="stat-info">
            <span class="stat-value">{{ formatNumber(counts[stat.countKey] ?? 0) }}</span>
            <span class="stat-label">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ENTITY TAB BAR (only shown without initialEntity prop) -->
    <div v-if="!initialEntity" class="entity-tabs">
      <button v-for="tab in entityTabs" :key="tab.id"
              :class="['entity-tab', { 'active': activeEntity === tab.id }]"
              @click="switchEntity(tab.id)">
        <span class="tab-icon" v-html="tab.icon"></span>
        {{ tab.label }}
      </button>
    </div>

    <!-- FILTERS BAR -->
    <div class="filter-bar">
      <div class="filter-group">
        <div class="search-box">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input v-model="searchQuery" @input="debouncedLoad" placeholder="Search records..." class="search-input" />
        </div>

        <!-- Dynamic Filters -->
        <select v-if="activeEntity === 'servers'" v-model="filters.status" @change="loadData" class="filter-select">
          <option value="">All Status</option>
          <option v-for="s in ['online','offline','degraded','damaged','provisioning','maintenance','locked']" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-if="activeEntity === 'servers'" v-model="filters.type" @change="loadData" class="filter-select">
          <option value="">All Types</option>
          <option v-for="t in ['vserver_node','dedicated','gpu_server','storage_server','experimental','custom']" :key="t" :value="t">{{ t }}</option>
        </select>

        <select v-if="activeEntity === 'orders'" v-model="filters.status" @change="loadData" class="filter-select">
          <option value="">All Status</option>
          <option v-for="s in ['pending','provisioning','active','cancelled','completed','expired']" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-if="activeEntity === 'orders'" v-model="filters.sla_tier" @change="loadData" class="filter-select">
          <option value="">All SLA</option>
          <option v-for="t in ['standard','premium','enterprise','whale']" :key="t" :value="t">{{ t }}</option>
        </select>

        <select v-if="activeEntity === 'events'" v-model="filters.status" @change="loadData" class="filter-select">
          <option value="">All Status</option>
          <option v-for="s in ['warning','active','escalated','resolved','failed']" :key="s" :value="s">{{ s }}</option>
        </select>

        <select v-if="activeEntity === 'customers'" v-model="filters.status" @change="loadData" class="filter-select">
          <option value="">All Status</option>
          <option v-for="s in ['active','unhappy','churning','churned']" :key="s" :value="s">{{ s }}</option>
        </select>

        <select v-if="activeEntity === 'rooms'" v-model="filters.type" @change="loadData" class="filter-select">
          <option value="">All Types</option>
          <option v-for="t in ['basement','garage','small_hall','data_center']" :key="t" :value="t">{{ t }}</option>
        </select>

        <select v-if="activeEntity === 'economies'" v-model="filters.difficulty" @change="loadData" class="filter-select">
          <option value="">All Difficulty</option>
          <option v-for="d in ['easy','normal','hard','ironman']" :key="d" :value="d">{{ d }}</option>
        </select>

        <!-- User Filter (global) -->
        <select v-model="filters.user_id" @change="loadData" class="filter-select" v-if="users.length">
          <option value="">All Players</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-meta">
        <span class="record-count">{{ totalRecords }} records</span>
      </div>
    </div>

    <!-- DATA TABLE -->
    <div class="data-table-wrapper custom-scrollbar">
      <div v-if="loading" class="table-loader">
        <div class="loader-pulse"></div>
        <span>Loading matrix data...</span>
      </div>

      <table v-else-if="items.length" class="data-table">
        <thead>
          <tr>
            <th v-for="col in activeColumns" :key="col.key" :style="{ width: col.width || 'auto', textAlign: col.align || 'left' }">
              {{ col.label }}
            </th>
            <th style="width: 120px; text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" :class="{ 'editing-row': editingId === item.id }">
            <td v-for="col in activeColumns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
              <!-- Editing Mode -->
              <template v-if="editingId === item.id && col.editable">
                <select v-if="col.options" v-model="editForm[col.key]" class="cell-input">
                  <option v-for="opt in col.options" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input v-else v-model="editForm[col.key]" :type="col.inputType || 'text'" class="cell-input" step="any" />
              </template>
              <!-- View Mode -->
              <template v-else>
                <span v-if="col.format === 'status'" :class="['status-badge', getStatusClass(getNestedValue(item, col.key))]">
                  {{ getNestedValue(item, col.key) }}
                </span>
                <span v-else-if="col.format === 'money'" class="money-value">${{ formatNumber(getNestedValue(item, col.key)) }}</span>
                <span v-else-if="col.format === 'percent'" class="percent-value">{{ Number(getNestedValue(item, col.key)).toFixed(1) }}%</span>
                <span v-else-if="col.format === 'date'" class="date-value">{{ formatDate(getNestedValue(item, col.key)) }}</span>
                <span v-else-if="col.format === 'id'" class="id-value" :title="getNestedValue(item, col.key)">{{ truncateId(getNestedValue(item, col.key)) }}</span>
                <span v-else-if="col.format === 'temp'" :class="['temp-value', getTempClass(getNestedValue(item, col.key))]">{{ Number(getNestedValue(item, col.key)).toFixed(1) }}°C</span>
                <span v-else>{{ getNestedValue(item, col.key) ?? '—' }}</span>
              </template>
            </td>
            <td class="action-cell">
              <template v-if="editingId === item.id">
                <button @click="saveEdit(item)" class="action-btn save-btn" title="Save">✓</button>
                <button @click="cancelEdit()" class="action-btn cancel-btn" title="Cancel">✕</button>
              </template>
              <template v-else>
                <button @click="startEdit(item)" class="action-btn edit-btn" title="Edit">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button v-if="activeEntity === 'events' && isEventActive(item)" @click="resolveEventAdmin(item)" class="action-btn resolve-btn" title="Force Resolve">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </button>
                <button @click="confirmDelete(item)" class="action-btn delete-btn" title="Delete">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        <p>No records found in this sector.</p>
      </div>
    </div>

    <!-- PAGINATION -->
    <div v-if="pagination.lastPage > 1" class="pagination-bar">
      <button @click="goToPage(pagination.currentPage - 1)" :disabled="pagination.currentPage <= 1" class="page-btn">← Prev</button>
      <span class="page-info">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
      <button @click="goToPage(pagination.currentPage + 1)" :disabled="pagination.currentPage >= pagination.lastPage" class="page-btn">Next →</button>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div v-if="deleteTarget" class="delete-overlay" @click.self="deleteTarget = null">
      <div class="delete-modal">
        <div class="delete-icon">⚠</div>
        <h3>Confirm Purge</h3>
        <p>Permanently remove this {{ activeEntity.slice(0, -1) }} record? This action is irreversible and will cascade to related data.</p>
        <p class="delete-id">ID: {{ truncateId(deleteTarget.id) }}</p>
        <div class="delete-actions">
          <button @click="deleteTarget = null" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="executeDelete" class="sys-btn sys-btn-danger">Purge Record</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue';
import api from '../../../utils/api';

const props = defineProps({
  initialEntity: { type: String, default: '' }
});

const { addToast, requestConfirm } = inject('adminContext');

const activeEntity = ref(props.initialEntity || 'servers');
const items = ref([]);
const loading = ref(false);
const countsLoading = ref(false);
const counts = ref(null);
const searchQuery = ref('');
const editingId = ref(null);
const editForm = ref({});
const deleteTarget = ref(null);
const users = ref([]);
const filters = ref({ status: '', type: '', sla_tier: '', user_id: '', difficulty: '' });
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });
const totalRecords = computed(() => pagination.value.total);

let debounceTimer = null;
const debouncedLoad = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => loadData(), 300);
};

// ─── ENTITY DEFINITIONS ───

const statCards = [
  { key: 'servers', countKey: 'servers', label: 'Servers', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>' },
  { key: 'racks', countKey: 'racks', label: 'Racks', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>' },
  { key: 'rooms', countKey: 'rooms', label: 'Rooms', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>' },
  { key: 'customers', countKey: 'customers', label: 'Customers', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>' },
  { key: 'orders', countKey: 'orders', label: 'Orders', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' },
  { key: 'events', countKey: 'active_events', label: 'Active Events', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
  { key: 'economies', countKey: 'users', label: 'Economies', icon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
];

const entityTabs = [
  { id: 'servers', label: 'Servers', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>' },
  { id: 'racks', label: 'Racks', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="2" width="16" height="20" rx="2"/></svg>' },
  { id: 'rooms', label: 'Rooms', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>' },
  { id: 'customers', label: 'Customers', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>' },
  { id: 'orders', label: 'Orders', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>' },
  { id: 'events', label: 'Events', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
  { id: 'economies', label: 'Economy', icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
];

// ─── ENTITY TITLES ───

const entityTitles = {
  servers: { title: 'Server Registry', subtitle: 'All server hardware — view, edit status, health, specs, and costs.' },
  racks: { title: 'Rack Infrastructure', subtitle: 'Rack units, temperature, dust, power draw, and slot management.' },
  rooms: { title: 'Room Facilities', subtitle: 'Rooms, data centers, power capacity, cooling, bandwidth, and regional config.' },
  customers: { title: 'Customer Database', subtitle: 'Customer profiles — satisfaction, tier, incidents, and churn management.' },
  orders: { title: 'Order Ledger', subtitle: 'Customer orders — pricing, SLA tiers, uptime tracking, and contract details.' },
  events: { title: 'Event Incidents', subtitle: 'Game events — severity, status, damage costs. Force-resolve active incidents.' },
  economies: { title: 'Player Economy', subtitle: 'Player balances, XP, levels, reputation, difficulty settings, and revenue.' },
};

const entityTitle = computed(() => entityTitles[activeEntity.value]?.title || 'Game Data Manager');
const entitySubtitle = computed(() => entityTitles[activeEntity.value]?.subtitle || 'Full entity access.');

// ─── COLUMN DEFINITIONS PER ENTITY ───

const columnDefs = {
  servers: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'model_name', label: 'Model', editable: true },
    { key: 'type', label: 'Type', editable: true, options: ['vserver_node','dedicated','gpu_server','storage_server','experimental','custom'] },
    { key: 'status', label: 'Status', format: 'status', editable: true, options: ['online','offline','degraded','damaged','provisioning','maintenance','locked'] },
    { key: 'health', label: 'Health', format: 'percent', editable: true, inputType: 'number', width: '80px' },
    { key: 'cpu_cores', label: 'CPU', editable: true, inputType: 'number', width: '60px' },
    { key: 'ram_gb', label: 'RAM (GB)', editable: true, inputType: 'number', width: '80px' },
    { key: 'storage_tb', label: 'Storage (TB)', editable: true, inputType: 'number', width: '90px' },
    { key: 'power_draw_kw', label: 'Power (kW)', editable: true, inputType: 'number', width: '90px' },
    { key: 'purchase_cost', label: 'Cost', format: 'money', editable: true, inputType: 'number', width: '90px' },
    { key: 'rack.room.user_id', label: 'Owner', format: 'id', width: '90px' },
  ],
  racks: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'name', label: 'Name', editable: true },
    { key: 'type', label: 'Type', editable: true, options: ['rack_12u','rack_24u','rack_42u','rack_42u_hd','cryo_rack'] },
    { key: 'total_units', label: 'Units', editable: true, inputType: 'number', width: '60px' },
    { key: 'used_units', label: 'Used', width: '60px' },
    { key: 'temperature', label: 'Temp', format: 'temp', editable: true, inputType: 'number', width: '80px' },
    { key: 'dust_level', label: 'Dust', format: 'percent', editable: true, inputType: 'number', width: '70px' },
    { key: 'max_power_kw', label: 'Max Power (kW)', editable: true, inputType: 'number', width: '110px' },
    { key: 'status', label: 'Status', format: 'status', editable: true, options: ['active','overheating','offline'] },
    { key: 'room.user_id', label: 'Owner', format: 'id', width: '90px' },
  ],
  rooms: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'name', label: 'Name', editable: true },
    { key: 'type', label: 'Type', editable: true, options: ['basement','garage','small_hall','data_center'] },
    { key: 'level', label: 'Level', editable: true, inputType: 'number', width: '60px' },
    { key: 'max_racks', label: 'Max Racks', editable: true, inputType: 'number', width: '80px' },
    { key: 'max_power_kw', label: 'Power (kW)', editable: true, inputType: 'number', width: '90px' },
    { key: 'max_cooling_kw', label: 'Cooling (kW)', editable: true, inputType: 'number', width: '100px' },
    { key: 'bandwidth_gbps', label: 'BW (Gbps)', editable: true, inputType: 'number', width: '90px' },
    { key: 'rent_per_hour', label: 'Rent/hr', format: 'money', editable: true, inputType: 'number', width: '90px' },
    { key: 'region', label: 'Region', editable: true, options: ['us_east','us_west','eu_central','asia_east','sa_east'] },
    { key: 'is_unlocked', label: 'Unlocked', width: '80px' },
    { key: 'user_id', label: 'Owner', format: 'id', width: '90px' },
  ],
  customers: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'name', label: 'Contact', editable: true },
    { key: 'company_name', label: 'Company', editable: true },
    { key: 'tier', label: 'Tier', editable: true, options: ['bronze','silver','gold','platinum'] },
    { key: 'satisfaction', label: 'Satisfaction', format: 'percent', editable: true, inputType: 'number', width: '100px' },
    { key: 'status', label: 'Status', format: 'status', editable: true, options: ['active','unhappy','churning','churned'] },
    { key: 'incidents_count', label: 'Incidents', editable: true, inputType: 'number', width: '80px' },
    { key: 'tolerance_incidents', label: 'Tolerance', editable: true, inputType: 'number', width: '80px' },
    { key: 'user.name', label: 'Player' },
    { key: 'created_at', label: 'Created', format: 'date', width: '120px' },
  ],
  orders: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'product_type', label: 'Product', editable: true, options: ['web_hosting','dedicated','game_server','database_hosting','gpu_rental','ml_training','storage'] },
    { key: 'status', label: 'Status', format: 'status', editable: true, options: ['pending','provisioning','active','cancelled','completed','expired'] },
    { key: 'price_per_month', label: 'Price/mo', format: 'money', editable: true, inputType: 'number', width: '100px' },
    { key: 'sla_tier', label: 'SLA', format: 'status', editable: true, options: ['standard','premium','enterprise','whale'] },
    { key: 'uptime_percent', label: 'Uptime', format: 'percent', editable: true, inputType: 'number', width: '80px' },
    { key: 'contract_months', label: 'Months', editable: true, inputType: 'number', width: '70px' },
    { key: 'customer.company_name', label: 'Customer' },
    { key: 'customer.user.name', label: 'Player' },
    { key: 'created_at', label: 'Created', format: 'date', width: '120px' },
  ],
  events: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'title', label: 'Title', editable: true },
    { key: 'type', label: 'Type', format: 'status', editable: true, options: ['overheating','hardware_failure','power_outage','network_failure','security_breach','ddos_attack'] },
    { key: 'severity', label: 'Severity', format: 'status', editable: true, options: ['low','medium','high','critical'] },
    { key: 'status', label: 'Status', format: 'status', editable: true, options: ['warning','active','escalated','resolved','failed'] },
    { key: 'management_grade', label: 'Grade', width: '60px' },
    { key: 'damage_cost', label: 'Damage', format: 'money', editable: true, inputType: 'number', width: '90px' },
    { key: 'user.name', label: 'Player' },
    { key: 'created_at', label: 'Created', format: 'date', width: '120px' },
  ],
  economies: [
    { key: 'id', label: 'ID', format: 'id', width: '90px' },
    { key: 'user.name', label: 'Player' },
    { key: 'balance', label: 'Balance', format: 'money', editable: true, inputType: 'number' },
    { key: 'level', label: 'Level', editable: true, inputType: 'number', width: '60px' },
    { key: 'experience_points', label: 'XP', editable: true, inputType: 'number', width: '80px' },
    { key: 'reputation', label: 'Reputation', editable: true, inputType: 'number', width: '90px' },
    { key: 'difficulty', label: 'Difficulty', format: 'status', editable: true, options: ['easy','normal','hard','ironman'] },
    { key: 'hourly_income', label: 'Income/hr', format: 'money', editable: true, inputType: 'number', width: '100px' },
    { key: 'hourly_expenses', label: 'Expenses/hr', format: 'money', editable: true, inputType: 'number', width: '110px' },
    { key: 'skill_points', label: 'SP', editable: true, inputType: 'number', width: '50px' },
  ],
};

const activeColumns = computed(() => columnDefs[activeEntity.value] || []);

// ─── API ENDPOINTS MAP ───

const apiEndpoints = {
  servers: { list: '/admin/servers', update: '/admin/servers/{id}/update', delete: '/admin/servers/{id}' },
  racks: { list: '/admin/racks', update: '/admin/racks/{id}/update', delete: '/admin/racks/{id}' },
  rooms: { list: '/admin/rooms', update: '/admin/rooms/{id}/update', delete: '/admin/rooms/{id}' },
  customers: { list: '/admin/customers', update: '/admin/customers/{id}/update', delete: '/admin/customers/{id}' },
  orders: { list: '/admin/orders', update: '/admin/orders/{id}/update', delete: '/admin/orders/{id}' },
  events: { list: '/admin/events', update: '/admin/events/{id}/update', delete: '/admin/events/{id}' },
  economies: { list: '/admin/economies', update: '/admin/economies/{id}/update', delete: null },
};

// ─── DATA LOADING ───

async function loadCounts() {
  countsLoading.value = true;
  try {
    const res = await api.get('/admin/entity-counts');
    counts.value = res.data.counts;
  } catch (e) { console.error(e); }
  countsLoading.value = false;
}

async function loadUsers() {
  try {
    const res = await api.get('/admin/users', { params: { per_page: 100 } });
    users.value = (res.data.users?.data || res.data.users || []);
  } catch(e) { console.error(e); }
}

async function loadData() {
  loading.value = true;
  editingId.value = null;

  const endpoint = apiEndpoints[activeEntity.value];
  if (!endpoint) return;

  const params = { page: pagination.value.currentPage, per_page: 25 };
  if (searchQuery.value) params.search = searchQuery.value;
  if (filters.value.status) params.status = filters.value.status;
  if (filters.value.type) params.type = filters.value.type;
  if (filters.value.sla_tier) params.sla_tier = filters.value.sla_tier;
  if (filters.value.user_id) params.user_id = filters.value.user_id;
  if (filters.value.difficulty) params.difficulty = filters.value.difficulty;

  try {
    const res = await api.get(endpoint.list, { params });
    const data = res.data.data;
    items.value = data?.data || data || [];
    pagination.value = {
      currentPage: data?.current_page || 1,
      lastPage: data?.last_page || 1,
      total: data?.total || items.value.length,
    };
  } catch (e) {
    console.error(e);
    addToast('Failed to load data: ' + (e.response?.data?.message || e.message), 'error');
  }
  loading.value = false;
}

function switchEntity(entity) {
  activeEntity.value = entity;
  pagination.value.currentPage = 1;
  searchQuery.value = '';
  filters.value = { status: '', type: '', sla_tier: '', user_id: filters.value.user_id, difficulty: '' };
  loadData();
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.lastPage) return;
  pagination.value.currentPage = page;
  loadData();
}

// ─── INLINE EDITING ───

function startEdit(item) {
  editingId.value = item.id;
  editForm.value = {};
  for (const col of activeColumns.value) {
    if (col.editable) {
      editForm.value[col.key] = getNestedValue(item, col.key);
    }
  }
}

function cancelEdit() {
  editingId.value = null;
  editForm.value = {};
}

async function saveEdit(item) {
  const endpoint = apiEndpoints[activeEntity.value];
  if (!endpoint?.update) return;

  const url = endpoint.update.replace('{id}', item.id);
  try {
    await api.post(url, editForm.value);
    addToast('Record updated successfully.', 'success');
    editingId.value = null;
    loadData();
    loadCounts();
  } catch (e) {
    addToast('Update failed: ' + (e.response?.data?.message || e.message), 'error');
  }
}

// ─── DELETION ───

function confirmDelete(item) {
  deleteTarget.value = item;
}

async function executeDelete() {
  const endpoint = apiEndpoints[activeEntity.value];
  if (!endpoint?.delete || !deleteTarget.value) return;

  const url = endpoint.delete.replace('{id}', deleteTarget.value.id);
  try {
    await api.delete(url);
    addToast('Record purged permanently.', 'success');
    deleteTarget.value = null;
    loadData();
    loadCounts();
  } catch (e) {
    addToast('Delete failed: ' + (e.response?.data?.message || e.message), 'error');
  }
}

// ─── EVENT ACTIONS ───

function isEventActive(item) {
  return ['warning', 'active', 'escalated'].includes(item.status);
}

async function resolveEventAdmin(item) {
  try {
    await api.post(`/admin/events/${item.id}/resolve`, { action: 'admin_override' });
    addToast('Event force-resolved with S-rank.', 'success');
    loadData();
    loadCounts();
  } catch (e) {
    addToast('Resolve failed: ' + (e.response?.data?.message || e.message), 'error');
  }
}

// ─── UTILITIES ───

function getNestedValue(obj, path) {
  return path.split('.').reduce((o, k) => o?.[k], obj);
}

function formatNumber(n) {
  if (n === null || n === undefined) return '0';
  return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function truncateId(id) {
  if (!id) return '—';
  return String(id).substring(0, 8) + '…';
}

function getStatusClass(status) {
  const s = String(status).toLowerCase();
  if (['online','active','resolved','easy','standard'].includes(s)) return 'status-green';
  if (['pending','provisioning','warning','normal','premium','medium'].includes(s)) return 'status-yellow';
  if (['degraded','unhappy','escalated','hard','enterprise','high'].includes(s)) return 'status-orange';
  if (['offline','damaged','failed','churned','cancelled','expired','critical','ironman','whale'].includes(s)) return 'status-red';
  if (['maintenance','locked','churning'].includes(s)) return 'status-blue';
  return '';
}

function getTempClass(temp) {
  const t = Number(temp);
  if (t > 55) return 'temp-critical';
  if (t > 45) return 'temp-hot';
  if (t > 35) return 'temp-warm';
  return 'temp-cool';
}

// ─── INIT ───

onMounted(() => {
  loadCounts();
  loadUsers();
  loadData();
});
</script>

<style scoped>
.gdm-container {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* HEADER */
.gdm-header {
  margin-bottom: 32px;
}

.gdm-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}

.gdm-title {
  font-size: 1.5rem;
  font-weight: 900;
  font-style: italic;
  letter-spacing: -0.03em;
  color: #fafafa;
  margin: 0;
}

.gdm-subtitle {
  font-size: 0.7rem;
  color: #52525b;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-top: 4px;
}

.gdm-refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #111;
  border: 1px solid #222;
  border-radius: 10px;
  color: #a1a1aa;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  cursor: pointer;
  transition: all 0.2s;
}
.gdm-refresh-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.gdm-refresh-btn.spin svg { animation: spin360 0.8s ease; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* STATS GRID */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.stat-card:hover { background: #111; border-color: #27272a; transform: translateY(-2px); }
.stat-card.active { background: #0c1222; border-color: #1e3a5f; box-shadow: 0 0 20px rgba(59, 130, 246, 0.08); }

.stat-icon { color: #3f3f46; flex-shrink: 0; }
.stat-card.active .stat-icon { color: #3b82f6; }

.stat-value { font-size: 1.1rem; font-weight: 900; color: #fafafa; display: block; }
.stat-label { font-size: 0.6rem; color: #52525b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; }

/* ENTITY TABS */
.entity-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 14px;
  margin-bottom: 16px;
  overflow-x: auto;
}

.entity-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #52525b;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
  border: none;
  background: transparent;
}
.entity-tab:hover { color: #a1a1aa; background: #111; }
.entity-tab.active { color: #3b82f6; background: #0c1222; }

.tab-icon { opacity: 0.5; flex-shrink: 0; }
.entity-tab.active .tab-icon { opacity: 1; }

/* FILTER BAR */
.filter-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 16px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 14px;
  margin-bottom: 16px;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 36px;
  background: #111;
  border: 1px solid #222;
  border-radius: 10px;
  color: #52525b;
  min-width: 200px;
}
.search-input {
  background: transparent;
  border: none;
  color: #fafafa;
  font-size: 0.75rem;
  font-weight: 600;
  width: 100%;
  outline: none;
}
.search-input::placeholder { color: #3f3f46; }

.filter-select {
  height: 36px;
  padding: 0 12px;
  background: #111;
  border: 1px solid #222;
  border-radius: 10px;
  color: #a1a1aa;
  font-size: 0.7rem;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  appearance: auto;
}
.filter-select:focus { border-color: #3b82f6; }

.record-count {
  font-size: 0.65rem;
  font-weight: 800;
  color: #3f3f46;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  white-space: nowrap;
}

/* DATA TABLE */
.data-table-wrapper {
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 14px;
  overflow: auto;
  max-height: 60vh;
}

.table-loader {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 80px 0;
  color: #3f3f46;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.15em;
}

.loader-pulse {
  width: 32px;
  height: 32px;
  border: 2px solid #18181b;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: pulseSpin 0.8s ease infinite;
}
@keyframes pulseSpin { to { transform: rotate(360deg); } }

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.75rem;
}

.data-table thead {
  position: sticky;
  top: 0;
  z-index: 10;
}

.data-table th {
  padding: 12px 14px;
  background: #0f0f11;
  color: #52525b;
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  border-bottom: 1px solid #18181b;
  white-space: nowrap;
}

.data-table td {
  padding: 10px 14px;
  color: #d4d4d8;
  font-weight: 500;
  border-bottom: 1px solid #0f0f11;
  white-space: nowrap;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.data-table tr:hover { background: rgba(59, 130, 246, 0.03); }
.data-table tr.editing-row { background: rgba(59, 130, 246, 0.06); }

/* CELL INPUTS */
.cell-input {
  width: 100%;
  height: 30px;
  padding: 0 8px;
  background: #111;
  border: 1px solid #3b82f6;
  border-radius: 6px;
  color: #fafafa;
  font-size: 0.72rem;
  font-weight: 600;
  outline: none;
  box-sizing: border-box;
}
.cell-input:focus { box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }

/* STATUS BADGES */
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.status-green { background: #052e16; color: #4ade80; }
.status-yellow { background: #422006; color: #fbbf24; }
.status-orange { background: #431407; color: #fb923c; }
.status-red { background: #450a0a; color: #f87171; }
.status-blue { background: #0c1222; color: #60a5fa; }

.money-value { color: #4ade80; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; }
.percent-value { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; }
.date-value { color: #71717a; font-size: 0.65rem; }
.id-value { color: #3f3f46; font-family: 'JetBrains Mono', monospace; font-size: 0.6rem; cursor: help; }

.temp-cool { color: #60a5fa; }
.temp-warm { color: #fbbf24; }
.temp-hot { color: #fb923c; }
.temp-critical { color: #ef4444; font-weight: 800; }

/* ACTION BUTTONS */
.action-cell {
  text-align: right;
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  align-items: center;
}

.action-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
  border: 1px solid #222;
  background: #111;
  color: #71717a;
}
.action-btn:hover { transform: translateY(-1px); }

.edit-btn:hover { background: #1e3a5f; color: #60a5fa; border-color: #2563eb; }
.delete-btn:hover { background: #450a0a; color: #f87171; border-color: #dc2626; }
.save-btn { background: #052e16; color: #4ade80; border-color: #15803d; font-weight: 900; font-size: 14px; }
.save-btn:hover { background: #14532d; }
.cancel-btn { background: #1c1917; color: #a8a29e; border-color: #44403c; font-weight: 900; font-size: 12px; }
.cancel-btn:hover { background: #292524; }
.resolve-btn:hover { background: #052e16; color: #4ade80; border-color: #15803d; }

/* EMPTY STATE */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 80px 0;
  color: #27272a;
}
.empty-state p { color: #3f3f46; font-size: 0.75rem; font-weight: 600; }

/* PAGINATION */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px 0;
}

.page-btn {
  padding: 8px 16px;
  background: #111;
  border: 1px solid #222;
  border-radius: 10px;
  color: #a1a1aa;
  font-size: 0.7rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) { background: #1a1a1a; color: white; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.page-info {
  font-size: 0.65rem;
  color: #52525b;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

/* DELETE MODAL */
.delete-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.delete-modal {
  width: 440px;
  padding: 40px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 24px;
  text-align: center;
  animation: modalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.delete-icon { font-size: 2.5rem; margin-bottom: 16px; }

.delete-modal h3 {
  font-size: 1.1rem;
  font-weight: 900;
  font-style: italic;
  color: #f87171;
  margin-bottom: 12px;
}

.delete-modal p {
  font-size: 0.8rem;
  color: #71717a;
  line-height: 1.6;
  margin-bottom: 8px;
}

.delete-id {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.65rem;
  color: #3f3f46;
  margin-bottom: 24px;
}

.delete-actions {
  display: flex;
  gap: 12px;
}

.sys-btn-danger {
  flex: 1;
  height: 44px;
  background: #7f1d1d;
  color: #fca5a5;
  border: 1px solid #991b1b;
  border-radius: 12px;
  font-weight: 800;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
  transition: all 0.2s;
}
.sys-btn-danger:hover { background: #991b1b; color: white; }

.sys-btn-secondary {
  flex: 1;
  height: 44px;
  background: #111;
  color: #a1a1aa;
  border: 1px solid #222;
  border-radius: 12px;
  font-weight: 800;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
  transition: all 0.2s;
}
.sys-btn-secondary:hover { background: #1a1a1a; color: white; }

/* SCROLLBAR */
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #222; border-radius: 10px; }
</style>
