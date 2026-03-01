<template>
  <div class="sc-container">
    <!-- HEADER -->
    <div class="sc-header">
      <div class="sc-title-row">
        <div>
          <h2 class="sc-title">Support Nexus</h2>
          <p class="sc-subtitle">Customer satisfaction & churn mitigation — manage tickets, analyze friction, and issue reputation grants.</p>
        </div>
        <div class="header-pills">
          <div class="pill sc-pill-emerald">
            <span class="p-label">CSAT Index</span>
            <span class="p-val">84.2%</span>
          </div>
          <div class="pill sc-pill-amber">
            <span class="p-label">Open Tickets</span>
            <span class="p-val">{{ tickets.length }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="sc-layout">
      <!-- LEFT: TICKET QUEUE -->
      <div class="sc-left">
        <div class="panel ticket-panel">
          <div class="panel-header">
             <div class="panel-accent accent-blue"></div>
             <h3 class="panel-title">Active Support Queue</h3>
             <button @click="fetchSupportData" class="refresh-sub-btn">Sync Queue</button>
          </div>

          <div class="ticket-list custom-scrollbar">
             <div v-for="ticket in tickets" :key="ticket.id" class="ticket-card" :class="{ 'ticket-selected': selectedTicket?.id === ticket.id }" @click="selectedTicket = ticket">
                <div class="ticket-top">
                   <span class="t-id">#{{ ticket.id.slice(0, 8) }}</span>
                   <span class="t-sev" :class="'sev-' + ticket.severity">{{ ticket.severity }}</span>
                </div>
                <h4 class="t-subject">{{ ticket.subject }}</h4>
                <div class="t-meta">
                   <span class="t-user">{{ ticket.customer_name }}</span>
                   <span class="t-time">{{ formatTime(ticket.created_at) }}</span>
                </div>
             </div>
             <div v-if="!tickets.length" class="empty-queue">
                <div class="empty-icon">✓</div>
                <p>Support queue is currently void of critical friction.</p>
             </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: CASE ANALYSIS -->
      <div class="sc-right">
         <div v-if="selectedTicket" class="panel detail-panel animate-view">
            <div class="panel-header">
               <div class="panel-accent accent-indigo"></div>
               <h3 class="panel-title">Case Forensics: {{ selectedTicket.id.slice(0, 8) }}</h3>
               <button @click="selectedTicket = null" class="close-detail">✕</button>
            </div>

            <div class="case-body">
               <div class="case-user-card">
                  <div class="u-avatar">{{ selectedTicket.customer_name.charAt(0) }}</div>
                  <div class="u-info">
                     <span class="u-name">{{ selectedTicket.customer_name }}</span>
                     <span class="u-tier">{{ selectedTicket.product_tier || 'Global Entity' }}</span>
                  </div>
                  <div class="u-health">
                     <span class="h-label">Satisfaction</span>
                     <div class="h-bar"><div class="h-fill" :style="{ width: selectedTicket.satisfaction + '%', background: getSatisfColor(selectedTicket.satisfaction) }"></div></div>
                  </div>
               </div>

               <div class="case-content">
                  <span class="c-label">Transmitted Manifesto:</span>
                  <p class="c-text">"{{ selectedTicket.message }}"</p>
               </div>

               <div class="case-actions">
                  <button @click="resolveTicket('compensate')" class="sc-btn sc-btn-emerald">
                     Issue Credit & Close
                  </button>
                  <button @click="resolveTicket('fix')" class="sc-btn sc-btn-blue">
                     Standard Resolution
                  </button>
                  <button @click="resolveTicket('reject')" class="sc-btn sc-btn-red">
                     Dismiss Claim
                  </button>
               </div>
            </div>
         </div>

         <!-- STATS GRID IF NO TICKET SELECTED -->
         <div v-else class="sc-dashboard animate-view">
            <div class="panel dashboard-card">
               <h4 class="dash-label">Avg Response Velocity</h4>
               <span class="dash-val">2.4m</span>
               <div class="dash-graph">
                  <div v-for="i in 20" :key="i" class="g-bar" :style="{ height: (Math.random() * 40 + 20) + '%' }"></div>
               </div>
            </div>
            <div class="panel dashboard-card">
               <h4 class="dash-label">Global Churn Risk</h4>
               <span class="dash-val val-red">ELEVATED</span>
               <p class="dash-desc text-red-500 italic font-bold">12 Customers approaching termination threshold.</p>
            </div>
         </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading } = inject('adminContext');

const tickets = ref([
    { id: 'TKT-88219-X', subject: 'Inconsistent Latency Spike', customer_name: 'Stark Industries', severity: 'critical', message: 'Our ML cluster is experiencing 200ms variance in the EU sector. This violates our Tier 3 SLA.', satisfaction: 42, product_tier: 'Deep Learning Cluster', created_at: new Date(Date.now() - 1000 * 60 * 15) },
    { id: 'TKT-12293-C', subject: 'Billing Discrepancy', customer_name: 'Cyberdyne Systems', severity: 'medium', message: 'Invoice #99288 shows localized power tax twice. Please rectify.', satisfaction: 68, product_tier: 'Web Hosting Pro', created_at: new Date(Date.now() - 1000 * 60 * 120) },
    { id: 'TKT-44102-L', subject: 'Provisioning Timeout', customer_name: 'Weyland-Yutani', severity: 'high', message: 'New node deployment in US-EAST has been pending for 45 minutes.', satisfaction: 12, product_tier: 'Enterprise Cloud', created_at: new Date(Date.now() - 1000 * 60 * 45) },
]);

const selectedTicket = ref(null);

const fetchSupportData = () => {
    // In a real app, we'd fetch from /admin/support or similar
    // For now we use the mock data above
    addToast('Support lattice synchronized.', 'success');
};

const resolveTicket = (type) => {
    if (!selectedTicket.value) return;
    setGlobalLoading(true);
    setTimeout(() => {
        addToast(`Ticket ${selectedTicket.value.id} resolved via ${type}.`, 'success');
        tickets.value = tickets.value.filter(t => t.id !== selectedTicket.value.id);
        selectedTicket.value = null;
        setGlobalLoading(false);
    }, 800);
};

const formatTime = (d) => {
    const elapsed = Math.floor((Date.now() - new Date(d)) / 60000);
    if (elapsed < 1) return 'Just now';
    if (elapsed < 60) return `${elapsed}m ago`;
    return `${Math.floor(elapsed/60)}h ago`;
};

const getSatisfColor = (s) => {
    if (s < 30) return '#ef4444';
    if (s < 60) return '#f59e0b';
    return '#10b981';
};

onMounted(fetchSupportData);
</script>

<style scoped>
.sc-container { display: flex; flex-direction: column; }
.sc-header { margin-bottom: 24px; }
.sc-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.sc-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.sc-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-pills { display: flex; gap: 12px; }
.pill { padding: 8px 16px; border-radius: 12px; border: 1px solid #18181b; background: #0a0a0c; display: flex; flex-direction: column; align-items: flex-end; }
.p-label { font-size: 0.5rem; font-weight: 900; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.1em; }
.p-val { font-size: 0.9rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; font-style: italic; }
.sc-pill-emerald .p-val { color: #10b981; }
.sc-pill-amber .p-val { color: #f59e0b; }

.sc-layout { display: grid; grid-template-columns: 360px 1fr; gap: 24px; height: 600px; }

.panel { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; padding: 24px; display: flex; flex-direction: column; overflow: hidden; }
.panel-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-shrink: 0; }
.panel-accent { width: 3px; height: 18px; border-radius: 99px; }
.accent-blue { background: #3b82f6; }
.accent-indigo { background: #6366f1; }
.panel-title { font-size: 0.75rem; font-weight: 900; color: #fafafa; text-transform: uppercase; letter-spacing: 0.08em; font-style: italic; margin: 0; }

.refresh-sub-btn { margin-left: auto; font-size: 0.55rem; color: #3f3f46; text-transform: uppercase; font-weight: 800; cursor: pointer; background: none; border: none; }
.refresh-sub-btn:hover { color: #3b82f6; }

.ticket-list { flex: 1; display: flex; flex-direction: column; gap: 12px; }
.ticket-card { background: #111; border: 1px solid #1c1c1e; border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s; position: relative; }
.ticket-card:hover { border-color: #333; transform: translateX(4px); }
.ticket-selected { border-color: #3b82f6; background: #0c1222; }

.ticket-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.t-id { font-size: 0.55rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; font-weight: 800; }
.t-sev { font-size: 0.5rem; font-weight: 900; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; }
.sev-critical { background: #450a0a; color: #f87171; }
.sev-high { background: #422006; color: #fbbf24; }
.sev-medium { background: #18181b; color: #a1a1aa; }

.t-subject { font-size: 0.75rem; font-weight: 800; color: #e4e4e7; margin: 0 0 8px 0; font-style: italic; }
.t-meta { display: flex; justify-content: space-between; font-size: 0.55rem; color: #52525b; font-weight: 700; }

.empty-queue { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; color: #27272a; }
.empty-icon { font-size: 3rem; opacity: 0.2; }
.empty-queue p { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; text-align: center; max-width: 200px; line-height: 1.6; }

/* DETAIL VIEW */
.detail-panel { height: 100%; border-color: #1e1e24; }
.close-detail { margin-left: auto; width: 24px; height: 24px; border-radius: 6px; background: #111; border: 1px solid #222; color: #3f3f46; cursor: pointer; }

.case-body { flex: 1; display: flex; flex-direction: column; gap: 32px; }
.case-user-card { display: flex; align-items: center; gap: 20px; background: #050505; border: 1px solid #111; padding: 20px; border-radius: 16px; }
.u-avatar { width: 48px; height: 48px; border-radius: 12px; background: #18181b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 900; color: #3b82f6; }
.u-info { flex: 1; display: flex; flex-direction: column; }
.u-name { font-size: 0.9rem; font-weight: 900; color: #fafafa; font-style: italic; }
.u-tier { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; }
.u-health { width: 140px; }
.h-label { font-size: 0.5rem; font-weight: 900; color: #3f3f46; text-transform: uppercase; display: block; margin-bottom: 6px; }
.h-bar { height: 6px; background: #111; border-radius: 99px; overflow: hidden; }
.h-fill { height: 100%; border-radius: 99px; transition: width 0.5s; }

.case-content { background: #111; border: 1px solid #1c1c1e; border-radius: 14px; padding: 24px; }
.c-label { font-size: 0.6rem; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 12px; }
.c-text { font-size: 0.85rem; color: #a1a1aa; line-height: 1.7; font-style: italic; margin: 0; }

.case-actions { display: flex; gap: 12px; margin-top: auto; }
.sc-btn { flex: 1; padding: 14px; border-radius: 12px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: all 0.2s; border: 1px solid; }
.sc-btn-emerald { background: #052e16; color: #4ade80; border-color: #064e3b; }
.sc-btn-blue { background: #0c1222; color: #60a5fa; border-color: #1e3a5f; }
.sc-btn-red { background: #450a0a; color: #f87171; border-color: #7f1d1d; }

/* DASHBOARD */
.sc-dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; height: 100%; }
.dashboard-card { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
.dash-label { font-size: 0.6rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; margin: 0 0 12px 0; }
.dash-val { font-size: 2.5rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; font-style: italic; color: #fafafa; }
.val-red { color: #ef4444; }
.dash-graph { display: flex; align-items: flex-end; gap: 2px; height: 60px; margin-top: 20px; }
.g-bar { flex: 1; background: #1e3a5f; border-radius: 1px; min-width: 4px; }
.dash-desc { font-size: 0.65rem; margin-top: 12px; }
</style>
