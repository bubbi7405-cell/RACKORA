<template>
  <div class="admin-control-system">
    <!-- TOP PROGRESS BAR (IMMERSIVE) -->
    <div v-if="globalLoading" class="global-progress-bar"></div>

    <div class="system-layout">
      <!-- NAVIGATION SIDEBAR (Rigid Industrial) -->
      <aside class="system-sidebar">
        <div class="sidebar-header">
            <div class="brand-container">
               <div class="brand-mark">
                  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="3">
                     <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                  </svg>
               </div>
               <div class="brand-text">
                  <h1 class="system-title italic font-black tracking-tighter">RACKORA</h1>
                  <span class="system-subtitle font-black tracking-[0.3em]">LIVE OPS CORE</span>
               </div>
            </div>
        </div>

        <nav class="sidebar-navigation custom-scrollbar">
            <div v-for="group in menuGroups" :key="group.title" class="nav-group">
               <span class="group-title">{{ group.title }}</span>
               <ul class="nav-links">
                  <li v-for="item in group.items" :key="item.id" 
                      :class="['nav-link', { 'active': currentTab === item.id }]"
                      @click="currentTab = item.id">
                     <span class="nav-link-icon" v-html="item.icon"></span>
                     <span class="nav-link-label">{{ item.label }}</span>
                     <div v-if="currentTab === item.id" class="active-indicator"></div>
                  </li>
               </ul>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="auth-card">
               <div class="auth-details">
                  <span class="auth-name font-black italic">{{ user?.name }}</span>
                  <span class="auth-badge text-[9px] uppercase tracking-widest font-bold">{{ userRoleLabel }}</span>
               </div>
               <button @click="logout" class="logout-btn" title="Terminate Link">✕</button>
            </div>
            <div class="sidebar-branding">
               <span class="branding-version">v.1.0</span>
               <span class="branding-sep">·</span>
               <span class="branding-author">By Codepony.de</span>
            </div>
        </div>
      </aside>

      <!-- MAIN OPERATION CENTER -->
      <main class="operation-center">
        <!-- GLOBAL ACTION BAR (High Fidelity) -->
        <header class="action-bar">
           <div class="action-bar-left">
              <div class="nav-breadcrumb">
                 <span class="crumb-root opacity-40 font-black uppercase tracking-widest text-[10px]">Lattice</span>
                 <span class="mx-3 text-zinc-800">/</span>
                 <span class="crumb-current font-black uppercase tracking-widest text-[10px] text-zinc-400 italic">Sector: {{ currentTabLabel }}</span>
              </div>
           </div>
           <div class="action-bar-right">
              <div class="system-health">
                 <span class="health-dot animate-pulse"></span>
                 <span class="health-text text-mono uppercase font-black text-[9px] tracking-widest">Sys_Status: Nominal</span>
              </div>
              <div class="vertical-divider"></div>
              <div class="flex items-center gap-2">
                <button @click="refreshCurrent" class="nav-icon-btn" title="Re-sync Matrix">
                   <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
              </div>
           </div>
        </header>

        <!-- OPERATION VIEWPORT -->
        <div class="operation-viewport custom-scrollbar">
            <transition name="view-fade" mode="out-in">
               <div v-if="user" :key="currentTab" class="view-content animate-view">
                  <!-- 1. ANALYTICS & DASHBOARD -->
                  <LiveOpsDashboard v-if="currentTab === 'liveops'" />
                  <SupportCenter v-if="currentTab === 'support'" />

                  <!-- 2. SYSTEM ARCHITECTURE -->
                  <ConfigEngine v-if="currentTab === 'configs'" />
                  
                  <!-- 3. ECONOMIC MODELS -->
                  <EconomyCenter v-if="currentTab === 'economy'" />
                  <AuctionArchitect v-if="currentTab === 'auctions'" />

                  <!-- 4. PERSONNEL & ASSET REGISTRY -->
                  <UserManager v-if="currentTab === 'users'" />
                  <AssetManager v-if="currentTab === 'assets'" />
                  <FormulaArchitect v-if="currentTab === 'logic'" />
                  <WorldNewsStudio v-if="currentTab === 'news'" />

                  <!-- 5. SECURITY & AUDIT -->
                  <AuditForensics v-if="currentTab === 'audit'" />

                  <!-- 6. EXPERIMENTAL LABS -->
                  <SimulationLab v-if="currentTab === 'simulation'" />

                  <!-- 7. GAME DATA MANAGER (per-entity views) -->
                  <GameDataManager v-if="currentTab === 'gd-servers'" initialEntity="servers" />
                  <GameDataManager v-if="currentTab === 'gd-racks'" initialEntity="racks" />
                  <GameDataManager v-if="currentTab === 'gd-rooms'" initialEntity="rooms" />
                  <GameDataManager v-if="currentTab === 'gd-customers'" initialEntity="customers" />
                  <GameDataManager v-if="currentTab === 'gd-orders'" initialEntity="orders" />
                  <GameDataManager v-if="currentTab === 'gd-events'" initialEntity="events" />
                  <GameDataManager v-if="currentTab === 'gd-economies'" initialEntity="economies" />

                  <!-- 8. SERVER CATALOG (Shop Products) -->
                  <ServerCatalog v-if="currentTab === 'gd-catalog'" />

                  <!-- 9. CONFIG EDITORS -->
                  <RegionEditor v-if="currentTab === 'cfg-locations'" />
                  <ResearchTreeEditor v-if="currentTab === 'cfg-research'" />
                  <MarketingArchitect v-if="currentTab === 'cfg-marketing'" />
                  <ProductArchitect v-if="currentTab === 'cfg-products'" />
                  <PersonnelArchitect v-if="currentTab === 'cfg-employees'" />
                  <EngineArchitect v-if="currentTab === 'cfg-engine'" />
                  <HardwareArchitect v-if="currentTab === 'cfg-components'" />
                  <FacilityArchitect v-if="currentTab === 'cfg-facilities'" />
               </div>

               <!-- AUTHENTICATION SKELETON -->
               <div v-else class="viewport-loader">
                  <div class="loader-ring"></div>
                  <span class="loader-text font-black uppercase tracking-[0.4em] text-[10px] mt-8">Verifying Cipher Credentials...</span>
               </div>
            </transition>
        </div>
      </main>
    </div>

    <!-- OVERLAYS & MODALS (Premium Feel) -->
    <div v-if="confirmDialog.show" class="system-overlay" @click.self="confirmDialog.show = false">
       <div class="confirm-modal glass-effect animate-popup">
          <div class="modal-header border-b border-white/[0.05] pb-6 mb-6">
             <h3 class="modal-title font-black italic tracking-tighter uppercase text-white">Authorization Override</h3>
             <p class="modal-subtitle text-[10px] font-bold text-amber-500 uppercase tracking-widest mt-1">High Intensity Mutation Detected</p>
          </div>
          <p class="modal-description text-zinc-400 text-sm leading-relaxed mb-8 italic">"{{ confirmDialog.message }}"</p>
          <div class="modal-actions gap-4">
             <button @click="confirmDialog.show = false" class="sys-btn sys-btn-secondary flex-1">Abort</button>
             <button @click="executeConfirmedAction" class="sys-btn sys-btn-primary flex-1">Authorize Change</button>
          </div>
       </div>
    </div>

    <!-- NOTIFICATION STACK -->
    <div class="notification-stack">
       <transition-group name="toast">
          <div v-for="toast in toasts" :key="toast.id" :class="['sys-toast', toast.type]">
             <div class="toast-indicator"></div>
             <div class="flex flex-col">
                <span class="text-[8px] font-black uppercase tracking-widest opacity-40 mb-1">Node_Broadcast</span>
                <span class="toast-message font-bold text-[11px] leading-tight">{{ toast.message }}</span>
             </div>
          </div>
       </transition-group>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import { useAuthStore } from './stores/auth';
import api from './utils/api';

// REBUILT COMPONENTS
import LiveOpsDashboard from './components/Admin/LiveOps/Dashboard.vue';
import SupportCenter from './components/Admin/LiveOps/SupportCenter.vue';
import ConfigEngine from './components/Admin/Core/ConfigEngine.vue';
import EconomyCenter from './components/Admin/Economy/EconomyCenter.vue';
import UserManager from './components/Admin/Management/UserManager.vue';
import AssetManager from './components/Admin/Content/ContentEditor.vue';
import FormulaArchitect from './components/Admin/Economy/FormulaArchitect.vue';
import AuditForensics from './components/Admin/Management/AuditLogs.vue';
import SimulationLab from './components/Admin/Simulation/SimulationLab.vue';
import GameDataManager from './components/Admin/Data/GameDataManager.vue';
import ServerCatalog from './components/Admin/Data/ServerCatalog.vue';
import ConfigEditor from './components/Admin/Data/ConfigEditor.vue';
import ResearchTreeEditor from './components/Admin/Content/ResearchTreeEditor.vue';
import RegionEditor from './components/Admin/Content/RegionEditor.vue';
import PersonnelArchitect from './components/Admin/Content/PersonnelArchitect.vue';
import MarketingArchitect from './components/Admin/Content/MarketingArchitect.vue';
import ProductArchitect from './components/Admin/Content/ProductArchitect.vue';
import HardwareArchitect from './components/Admin/Content/HardwareArchitect.vue';
import FacilityArchitect from './components/Admin/Content/FacilityArchitect.vue';
import EngineArchitect from './components/Admin/Core/EngineArchitect.vue';
import WorldNewsStudio from './components/Admin/Content/WorldNewsStudio.vue';
import AuctionArchitect from './components/Admin/Economy/AuctionArchitect.vue';

const authStore = useAuthStore();
const globalLoading = ref(false);
const currentTab = ref('liveops');
const toasts = ref([]);
const user = computed(() => authStore.user);

const menuGroups = [
  {
    title: 'Operations',
    items: [
      { id: 'liveops', label: 'Command Hub', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>' },
      { id: 'support', label: 'Support Center', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
      { id: 'audit', label: 'Audit Logs', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-11.7A8.38 8.38 0 0 1 16 4.5"/><path d="M18 2v4"/><path d="M22 2v4"/><path d="M14 2v4"/><path d="M10 2v4"/></svg>' },
    ]
  },
  {
    title: 'Architecture',
    items: [
      { id: 'configs', label: 'Infrastructure', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M6.34 17.66l-1.41 1.41"/><path d="M19.07 4.93l-1.41 1.41"/></svg>' },
      { id: 'logic', label: 'Logic Architect', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>' },
      { id: 'economy', label: 'Macro Economy', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>' },
      { id: 'auctions', label: 'Auction Architect', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v2"/><path d="M12 20v2"/><path d="M16 4h2a2 2 0 0 1 2 2v1"/><path d="M20 11v2"/><path d="M20 17v1a2 2 0 0 1-2 2h-2"/><path d="M8 20H6a2 2 0 0 1-2-2v-1"/><path d="M4 13v-2"/><path d="M4 7V6a2 2 0 0 1 2-2h2"/></svg>' },
      { id: 'simulation', label: 'Simulation Lab', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 18l2-2 2 2"/><path d="M8 18l2-2 2 2"/><path d="M12 12l2-2 2 2"/></svg>' },
    ]
  },
  {
    title: 'Registries',
    items: [
      { id: 'users', label: 'User Registry', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' },
      { id: 'assets', label: 'Content Editor', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>' },
      { id: 'news', label: 'World News', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>' },
    ]
  },
  {
    title: 'Game Data',
    items: [
      { id: 'gd-servers', label: 'Servers', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>' },
      { id: 'gd-racks', label: 'Racks', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>' },
      { id: 'gd-rooms', label: 'Rooms', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' },
      { id: 'gd-customers', label: 'Customers', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' },
      { id: 'gd-orders', label: 'Orders', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' },
      { id: 'gd-events', label: 'Events', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
      { id: 'gd-economies', label: 'Economy', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
      { id: 'gd-catalog', label: 'Shop Catalog', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>' },
    ]
  },
  {
    title: 'Configuration',
    items: [
      { id: 'cfg-locations', label: 'Locations', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' },
      { id: 'cfg-research', label: 'Research Tree', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>' },
      { id: 'cfg-marketing', label: 'Marketing', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>' },
      { id: 'cfg-products', label: 'Products', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>' },
      { id: 'cfg-employees', label: 'Employees', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>' },
      { id: 'cfg-engine', label: 'Engine Tuning', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>' },
      { id: 'cfg-components', label: 'Components', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>' },
      { id: 'cfg-facilities', label: 'Facilities (Nodes)', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>' },
    ]
  }
];

const currentTabLabel = computed(() => {
  for (const group of menuGroups) {
    const item = group.items.find(i => i.id === currentTab.value);
    if (item) return item.label;
  }
  return 'Dashboard';
});

const userInitial = computed(() => user.value?.name?.charAt(0) || 'A');
const userRoleLabel = computed(() => (user.value?.admin_role || 'ADMIN').toUpperCase());

const addToast = (message, type = 'info') => {
  const id = Date.now();
  toasts.value.push({ id, message, type });
  setTimeout(() => {
    toasts.value = toasts.value.filter(t => t.id !== id);
  }, 4000);
};

const confirmDialog = ref({ show: false, message: '', action: null });
const requestConfirm = (message, action) => {
  confirmDialog.value = { show: true, message, action };
};

const executeConfirmedAction = () => {
  if (confirmDialog.value.action) confirmDialog.value.action();
  confirmDialog.value.show = false;
};

const logout = async () => {
   await api.post('/logout');
   window.location.href = '/';
};

const refreshCurrent = () => {
   addToast('Re-synchronizing operational lattice...', 'info');
};

provide('adminContext', {
  addToast,
  requestConfirm,
  setGlobalLoading: (val) => globalLoading.value = val
});

onMounted(async () => {
  const valid = await authStore.checkAuth();
  if (!valid || !authStore.isAdmin) {
    window.location.href = '/';
  }
});
</script>

<style>
/* 
RACKORA SYSTEM DESIGN v2 (Industrial Command Center)
--------------------------------------------------
Deep tones, Glass surfaces, Precise layout.
*/

:root {
  --sys-bg: #000000;
  --sys-sidebar: #050505;
  --sys-surface: #0a0a0c;
  --sys-border: #141416;
  --sys-primary: #3b82f6;
  --sys-text: #fafafa;
  --sys-text-muted: #52525b;
  --sys-font-ui: 'Inter', -apple-system, sans-serif;
  --sys-font-mono: 'JetBrains Mono', monospace;
}

body {
  background: var(--sys-bg);
  color: var(--sys-text);
  font-family: var(--sys-font-ui);
  margin: 0;
  overflow: hidden;
  -webkit-font-smoothing: antialiased;
}

.admin-control-system {
  height: 100vh;
  display: flex;
  flex-direction: column;
}

.system-layout {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* SIDEBAR - RIGID INDUSTRIAL */
.system-sidebar {
  width: 260px;
  background: var(--sys-sidebar);
  border-right: 1px solid var(--sys-border);
  display: flex;
  flex-direction: column;
  z-index: 100;
}

.sidebar-header {
  padding: 40px 24px;
}

.brand-container {
  display: flex;
  align-items: center;
  gap: 16px;
}

.brand-mark {
  width: 44px;
  height: 44px;
  background: #111;
  border: 1px solid #222;
  color: var(--sys-primary);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,0.5);
}

.brand-text .system-title {
  font-size: 1.25rem;
  line-height: 1;
  letter-spacing: -0.04em;
}

.brand-text .system-subtitle {
  font-size: 0.55rem;
  color: var(--sys-text-muted);
  display: block;
  margin-top: 4px;
}

.sidebar-navigation {
  flex: 1;
  padding: 0 16px;
  overflow-y: auto;
}

.nav-group {
  margin-bottom: 32px;
}

.group-title {
  padding: 0 12px;
  font-size: 0.6rem;
  font-weight: 900;
  color: var(--sys-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.2em;
  margin-bottom: 12px;
  display: block;
}

.nav-links { list-style: none; }

.nav-link {
  position: relative;
  height: 40px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-radius: 10px;
  cursor: pointer;
  color: var(--sys-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-link:hover {
  background: #0f0f11;
  color: var(--sys-text);
  transform: translateX(4px);
}

.nav-link.active {
  background: #111;
  color: var(--sys-primary);
}

.nav-link-icon {
  width: 16px;
  height: 16px;
  opacity: 0.6;
}

.nav-link.active .nav-link-icon { opacity: 1; }

.active-indicator {
  position: absolute;
  left: 0;
  top: 10px;
  bottom: 10px;
  width: 2px;
  background: var(--sys-primary);
  border-radius: 99px;
  box-shadow: 0 0 10px var(--sys-primary);
}

.sidebar-footer {
  padding: 24px;
  border-top: 1px solid var(--sys-border);
}

.sidebar-branding {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 14px;
  padding-top: 12px;
}
.branding-version {
  font-size: 0.55rem;
  font-weight: 900;
  color: #27272a;
  font-style: italic;
  letter-spacing: 0.05em;
}
.branding-sep {
  font-size: 0.6rem;
  color: #18181b;
}
.branding-author {
  font-size: 0.55rem;
  font-weight: 700;
  color: #27272a;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.auth-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: #0f0f11;
  border: 1px solid #18181b;
  border-radius: 16px;
}

.auth-details { display: flex; flex-direction: column; }
.auth-name { font-size: 0.8rem; }
.auth-badge { color: var(--sys-text-muted); }

.logout-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid #27272a;
  color: #71717a;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.logout-btn:hover { background: #ef4444; color: white; border-color: transparent; }

/* OPERATION CENTER */
.operation-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--sys-bg);
}

.action-bar {
  height: 64px;
  padding: 0 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #000;
}

.action-bar-right {
  display: flex;
  align-items: center;
  gap: 24px;
}

.system-health {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 16px;
  background: #022c22;
  border: 1px solid #064e3b;
  border-radius: 99px;
}

.health-dot {
  width: 5px;
  height: 5px;
  background: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 12px #10b981;
}

.health-text { color: #10b981; font-size: 0.65rem; }

.nav-icon-btn {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3f3f46;
  border: 1px solid #18181b;
  transition: all 0.2s;
}
.nav-icon-btn:hover { background: #111; color: white; border-color: #27272a; }

.operation-viewport {
  flex: 1;
  padding: 48px;
  overflow-y: auto;
}

.view-content {
  max-width: 1600px;
  margin: 0 auto;
}

/* GLOBAL UI COMPONENTS (USED IN CHILDREN) */
.glass-effect {
  background: rgba(10, 10, 12, 0.7);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.05);
}

.sys-btn {
  height: 44px;
  padding: 0 24px;
  border-radius: 12px;
  font-weight: 800;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
}

.sys-btn-primary { background: var(--sys-primary); color: white; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2); }
.sys-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(59, 130, 246, 0.3); }

.sys-btn-secondary { background: #111; color: #a1a1aa; border: 1px solid #18181b; }
.sys-btn-secondary:hover { background: #18181b; color: white; }

/* MODALS */
.confirm-modal {
  width: 480px;
  padding: 40px;
  border-radius: 32px;
}

/* TOASTS */
.notification-stack {
  position: fixed;
  bottom: 40px; right: 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  z-index: 1000;
}

.sys-toast {
  width: 340px;
  background: #0f0f11;
  border: 1px solid #18181b;
  border-radius: 20px;
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.6);
}

.sys-toast.success .toast-indicator { background: #10b981; box-shadow: 0 0 10px #10b981; }
.sys-toast.error .toast-indicator { background: #ef4444; box-shadow: 0 0 10px #ef4444; }
.toast-indicator { width: 4px; height: 16px; border-radius: 4px; background: var(--sys-primary); margin-top: 4px; }

/* SCROLLBAR */
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }

.view-fade-enter-active, .view-fade-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.view-fade-enter-from, .view-fade-leave-to { opacity: 0; transform: translateY(10px); }

.animate-view { animation: viewIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes viewIn { from { opacity: 0; transform: scale(0.99) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.animate-modal { animation: modalIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(40px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.global-progress-bar {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--sys-primary);
  z-index: 1000;
  animation: shunt 2s infinite;
}

@keyframes shunt {
  0% { transform: scaleX(0); transform-origin: left; }
  50% { transform: scaleX(1); transform-origin: left; }
  51% { transform: scaleX(1); transform-origin: right; }
  100% { transform: scaleX(0); transform-origin: right; }
}
</style>
