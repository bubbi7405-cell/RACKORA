<template>
  <div class="usr-container">
    <!-- HEADER -->
    <div class="usr-header">
      <div class="usr-title-row">
        <div>
          <h2 class="usr-title">User Registry</h2>
          <p class="usr-subtitle">Manage player accounts — roles, bans, resource grants, and forensic analysis.</p>
        </div>
        <div class="header-actions">
          <div class="search-box">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input v-model="search" placeholder="Search by name or email..." />
          </div>
          <button @click="fetchUsers" class="action-btn reload-btn" :class="{ spin: loading }">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Sync
          </button>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-ring"></div>
      <span>Loading user registry...</span>
    </div>

    <!-- TABLE -->
    <div v-else class="table-wrap">
      <table class="usr-table">
        <thead>
          <tr>
            <th>Identity</th>
            <th>Level</th>
            <th>Balance</th>
            <th>Role</th>
            <th>Status</th>
            <th class="th-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filteredUsers" :key="user.id" class="user-row">
            <td>
              <div class="user-identity">
                <div class="avatar" :class="user.banned_at ? 'avatar-banned' : ''">{{ user.name?.charAt(0)?.toUpperCase() }}</div>
                <div class="user-meta">
                  <span class="user-name">{{ user.name }}</span>
                  <span class="user-email">{{ user.email }}</span>
                </div>
              </div>
            </td>
            <td>
              <span class="level-badge">Lv. {{ user.level || 1 }}</span>
            </td>
            <td>
              <span class="money-value">${{ formatCurrency(user.money) }}</span>
              <span class="xp-sub">{{ formatCurrency(user.xp || 0) }} XP</span>
            </td>
            <td>
              <select v-model="user.admin_role" @change="updateRole(user)" class="role-select">
                <option value="">Player</option>
                <option value="mod">Moderator</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Super Admin</option>
              </select>
            </td>
            <td>
              <span class="status-pill" :class="user.banned_at ? 'status-banned' : 'status-active'">
                {{ user.banned_at ? 'Banned' : 'Active' }}
              </span>
            </td>
            <td class="td-actions">
              <button @click="openResources(user)" class="act-btn act-blue" title="Give Resources">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </button>
              <button v-if="!user.banned_at" @click="confirmBan(user)" class="act-btn act-red" title="Ban User">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
              </button>
              <button v-else @click="unban(user)" class="act-btn act-green" title="Unban User">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </button>
              <button @click="viewDetails(user)" class="act-btn act-zinc" title="View Details">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </td>
          </tr>
          <tr v-if="!filteredUsers.length">
            <td colspan="6" class="empty-row">No users matching "{{ search }}"</td>
          </tr>
        </tbody>
      </table>
      <div class="table-footer">
        <span>{{ filteredUsers.length }} of {{ users.length }} users</span>
      </div>
    </div>

    <!-- MODAL: GIVE RESOURCES -->
    <div v-if="resourceModal.show" class="overlay" @click.self="resourceModal.show = false">
      <div class="modal">
        <div class="modal-bar modal-bar-blue"></div>
        <h3 class="modal-title">Grant Resources</h3>
        <p class="modal-sub">Inject assets into <strong>{{ resourceModal.user?.name }}</strong>'s account.</p>

        <div class="field-grid">
          <div class="field">
            <label>Resource Type</label>
            <select v-model="resourceModal.type">
              <option value="money">Money ($)</option>
              <option value="xp">Experience (XP)</option>
              <option value="reputation">Reputation</option>
            </select>
          </div>
          <div class="field">
            <label>Amount</label>
            <input type="number" v-model.number="resourceModal.amount" />
          </div>
        </div>

        <div class="modal-actions">
          <button @click="resourceModal.show = false" class="btn-cancel">Cancel</button>
          <button @click="injectResource" class="btn-primary">Authorize Grant</button>
        </div>
      </div>
    </div>

    <!-- DRAWER: USER DETAILS -->
    <div v-if="details.show" class="overlay" @click.self="details.show = false">
      <div class="drawer">
        <div class="drawer-header">
          <div>
            <h3 class="drawer-title">{{ details.user?.name }}</h3>
            <span class="drawer-sub">{{ details.user?.email }} · ID: {{ details.user?.id }}</span>
          </div>
          <button @click="details.show = false" class="drawer-close">✕</button>
        </div>

        <div class="kpi-row">
          <div v-for="(val, key) in getUserKpis(details.user)" :key="key" class="kpi-mini">
            <span class="kpi-label">{{ key.replace(/_/g, ' ') }}</span>
            <span class="kpi-value">{{ val }}</span>
          </div>
        </div>

        <div class="timeline-section">
          <h4 class="section-title">Activity Timeline</h4>
          <div class="timeline">
            <div v-for="i in 5" :key="i" class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-content">
                <span class="timeline-time">{{ i * 2 }}h ago</span>
                <span class="timeline-desc">Session activity — standard operations</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, requestConfirm, setGlobalLoading } = inject('adminContext');

const loading = ref(true);
const users = ref([]);
const search = ref('');

const fetchUsers = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/users');
    if (res.success) users.value = res.users;
  } catch (e) { addToast('Failed to load user registry.', 'error'); }
  finally { loading.value = false; }
};

const filteredUsers = computed(() => {
  if (!search.value) return users.value;
  const q = search.value.toLowerCase();
  return users.value.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US').format(val);

// RESOURCE MODAL
const resourceModal = ref({ show: false, user: null, type: 'money', amount: 1000 });
const openResources = (user) => { resourceModal.value = { show: true, user, type: 'money', amount: 1000 }; };
const injectResource = async () => {
  setGlobalLoading(true);
  try {
    await api.post(`/admin/users/${resourceModal.value.user.id}/give`, {
      type: resourceModal.value.type,
      amount: resourceModal.value.amount,
    });
    addToast('Resource grant successful.', 'success');
    resourceModal.value.show = false;
    fetchUsers();
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

// BAN
const confirmBan = (user) => {
  requestConfirm(`Ban ${user.name}? They will lose access to the game immediately.`, async () => {
    setGlobalLoading(true);
    try {
      await api.post(`/admin/users/${user.id}/ban`);
      addToast('User banned.', 'success');
      fetchUsers();
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
  });
};

const unban = async (user) => {
  setGlobalLoading(true);
  try {
    await api.post(`/admin/users/${user.id}/unban`);
    addToast('User unbanned.', 'success');
    fetchUsers();
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

const updateRole = async (user) => {
  try {
    await api.post(`/admin/users/${user.id}/update`, { admin_role: user.admin_role });
    addToast(`Role updated for ${user.name}.`, 'info');
  } catch (e) { addToast(e.message, 'error'); }
};

// DETAILS DRAWER
const details = ref({ show: false, user: null });
const viewDetails = (user) => { details.value = { show: true, user }; };
const getUserKpis = (user) => ({
  Account_Age: '42 Days',
  Wealth_Rank: 'Top 15%',
  Risk_Score: '0.12%',
  Total_Runtime: '142 Hours',
  Region: 'EU-West',
  Status: user?.banned_at ? 'Banned' : 'Active',
});

onMounted(fetchUsers);
</script>

<style scoped>
.usr-container { display: flex; flex-direction: column; }
.usr-header { margin-bottom: 24px; }
.usr-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.usr-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.usr-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-actions { display: flex; gap: 10px; align-items: center; }
.search-box {
  display: flex; align-items: center; gap: 10px; padding: 0 14px; height: 40px;
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 10px; min-width: 260px;
}
.search-box svg { color: #3f3f46; flex-shrink: 0; }
.search-box input { background: none; border: none; color: #fafafa; font-size: 0.72rem; font-weight: 600; outline: none; width: 100%; }
.search-box input::placeholder { color: #27272a; }

.action-btn {
  display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px;
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;
  cursor: pointer; transition: all 0.2s; border: 1px solid #222; background: #111; color: #a1a1aa;
}
.action-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.action-btn.spin svg { animation: spin360 0.8s ease; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* LOADER */
.loader-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 80px 0; color: #3f3f46; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; }
.loader-ring { width: 32px; height: 32px; border: 2px solid #18181b; border-top-color: #3b82f6; border-radius: 50%; animation: spin360 0.8s linear infinite; }

/* TABLE */
.table-wrap { background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px; overflow: hidden; }
.usr-table { width: 100%; border-collapse: collapse; }
.usr-table thead tr { background: #050505; }
.usr-table th {
  padding: 14px 20px; font-size: 0.55rem; font-weight: 800; color: #3f3f46;
  text-transform: uppercase; letter-spacing: 0.15em; text-align: left; border-bottom: 1px solid #18181b;
}
.th-right { text-align: right; }
.usr-table td { padding: 14px 20px; border-bottom: 1px solid #0e0e10; }
.user-row { transition: background 0.15s; }
.user-row:hover { background: rgba(255,255,255,0.015); }

.user-identity { display: flex; align-items: center; gap: 12px; }
.avatar {
  width: 36px; height: 36px; border-radius: 10px; background: #18181b;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 900; color: #71717a;
}
.avatar-banned { background: #450a0a; color: #f87171; }
.user-name { font-size: 0.75rem; font-weight: 800; color: #e4e4e7; display: block; }
.user-email { font-size: 0.6rem; color: #3f3f46; font-weight: 600; display: block; }

.level-badge { font-size: 0.65rem; font-weight: 900; color: #a78bfa; background: #1e1b4b; padding: 3px 10px; border-radius: 6px; }
.money-value { font-size: 0.75rem; font-weight: 900; color: #fafafa; font-family: 'JetBrains Mono', monospace; display: block; }
.xp-sub { font-size: 0.55rem; color: #3f3f46; font-weight: 600; display: block; }

.role-select {
  height: 30px; padding: 0 10px; background: #111; border: 1px solid #222; border-radius: 8px;
  color: #a1a1aa; font-size: 0.65rem; font-weight: 700; outline: none; cursor: pointer;
}
.role-select:focus { border-color: #3b82f6; }

.status-pill { padding: 3px 12px; border-radius: 99px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
.status-active { background: #052e16; color: #4ade80; }
.status-banned { background: #450a0a; color: #f87171; }

.td-actions { text-align: right; white-space: nowrap; }
.act-btn {
  width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
  background: #111; border: 1px solid #1c1c1e; cursor: pointer; transition: all 0.15s; margin-left: 4px;
}
.act-btn svg { color: #52525b; }
.act-blue:hover { border-color: #1e3a5f; background: #0c1222; }
.act-blue:hover svg { color: #60a5fa; }
.act-red:hover { border-color: #7f1d1d; background: #450a0a; }
.act-red:hover svg { color: #f87171; }
.act-green:hover { border-color: #15803d; background: #052e16; }
.act-green:hover svg { color: #4ade80; }
.act-zinc:hover { border-color: #333; background: #1a1a1a; }
.act-zinc:hover svg { color: #fafafa; }

.table-footer { padding: 14px 20px; border-top: 1px solid #18181b; font-size: 0.6rem; font-weight: 700; color: #27272a; text-transform: uppercase; letter-spacing: 0.1em; }
.empty-row { text-align: center; padding: 40px 20px; color: #27272a; font-size: 0.7rem; font-weight: 700; }

/* OVERLAY + MODAL */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; padding: 32px; width: 440px; position: relative; animation: modalIn 0.3s ease; }
.modal-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 20px 20px 0 0; }
.modal-bar-blue { background: #3b82f6; }
.modal-title { font-size: 1.1rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0 0 4px 0; }
.modal-sub { font-size: 0.65rem; color: #52525b; font-weight: 600; margin-bottom: 24px; }
.modal-sub strong { color: #d4d4d8; }

.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
.field label { display: block; font-size: 0.55rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 6px; }
.field input, .field select { width: 100%; height: 36px; padding: 0 12px; background: #111; border: 1px solid #222; border-radius: 8px; color: #fafafa; font-size: 0.72rem; font-weight: 700; outline: none; box-sizing: border-box; }
.field input:focus, .field select:focus { border-color: #3b82f6; }

.modal-actions { display: flex; gap: 10px; }
.btn-cancel { flex: 1; height: 40px; border-radius: 10px; background: #111; border: 1px solid #222; color: #71717a; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { color: #fafafa; border-color: #333; }
.btn-primary { flex: 1; height: 40px; border-radius: 10px; background: #0c1222; border: 1px solid #1e3a5f; color: #60a5fa; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; }
.btn-primary:hover { background: #1e3a5f; }

@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* DRAWER */
.drawer { position: fixed; right: 0; top: 0; width: 600px; height: 100vh; background: #0a0a0c; border-left: 1px solid #18181b; padding: 32px; overflow-y: auto; animation: slideLeft 0.4s cubic-bezier(0.16,1,0.3,1); }
.drawer::-webkit-scrollbar { width: 4px; }
.drawer::-webkit-scrollbar-thumb { background: #18181b; border-radius: 10px; }
@keyframes slideLeft { from { transform: translateX(100%); } to { transform: translateX(0); } }

.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #18181b; }
.drawer-title { font-size: 1.2rem; font-weight: 900; color: #fafafa; font-style: italic; margin: 0; }
.drawer-sub { font-size: 0.6rem; color: #52525b; font-weight: 600; }
.drawer-close { width: 32px; height: 32px; border-radius: 8px; background: #111; border: 1px solid #222; color: #52525b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; font-weight: 900; }
.drawer-close:hover { color: #fafafa; border-color: #333; }

.kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 28px; }
.kpi-mini { background: #111; border: 1px solid #1c1c1e; border-radius: 10px; padding: 12px; }
.kpi-label { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: block; }
.kpi-value { font-size: 0.8rem; font-weight: 900; color: #fafafa; font-style: italic; display: block; margin-top: 4px; }

.section-title { font-size: 0.65rem; font-weight: 900; color: #52525b; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 16px 0; }
.timeline { display: flex; flex-direction: column; gap: 0; }
.timeline-item { display: flex; gap: 14px; padding-left: 10px; border-left: 1px solid #18181b; position: relative; padding-bottom: 16px; }
.timeline-dot { position: absolute; left: -4px; top: 4px; width: 7px; height: 7px; border-radius: 50%; background: #27272a; }
.timeline-content { display: flex; flex-direction: column; gap: 2px; }
.timeline-time { font-size: 0.55rem; color: #3f3f46; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.timeline-desc { font-size: 0.68rem; color: #71717a; font-weight: 600; }
</style>
