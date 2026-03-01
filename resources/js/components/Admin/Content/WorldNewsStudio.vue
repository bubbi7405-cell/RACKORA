<template>
  <div class="studio-container">
    <!-- HEADER -->
    <div class="studio-header">
      <div class="studio-title-row">
        <div>
          <h2 class="studio-title">World Events Zentrale</h2>
          <p class="studio-subtitle">Globale und regionale Ereignisse erstellen, auslösen und überwachen.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchAll" class="sys-btn sys-btn-secondary" :disabled="loading">
             <i class="fas fa-sync-alt"></i> Daten aktualisieren
           </button>
           <button @click="openEditor()" class="sys-btn sys-btn-primary">
             <i class="fas fa-plus"></i> Neues Event erstellen
           </button>
        </div>
      </div>
    </div>

    <!-- ACTIVE EVENTS DASHBOARD -->
    <div class="active-events-section">
      <div class="section-label">
        <span class="pulse-dot"></span>
        AKTIVE EREIGNISSE ({{ activeEvents.length }})
      </div>
      <div class="active-events-grid" v-if="activeEvents.length > 0">
        <div v-for="event in activeEvents" :key="event.id" class="active-event-card" :class="event.type">
          <div class="ae-header">
            <span class="ae-type-badge" :class="event.type">
              {{ event.type === 'crisis' ? '⚠️' : event.type === 'boom' ? '🚀' : 'ℹ️' }}
              {{ event.type.toUpperCase() }}
            </span>
            <span class="ae-scope" :class="{ global: event.is_global }">
              {{ event.is_global ? '🌍 GLOBAL' : '📍 REGIONAL' }}
            </span>
          </div>
          <div class="ae-title">{{ event.title }}</div>
          <div class="ae-desc">{{ event.description }}</div>
          <div class="ae-footer">
            <span class="ae-modifier">{{ formatModifier(event.modifier_type) }}: 
              <span :class="event.modifier_value > 0 ? 'text-green' : 'text-red'">
                {{ event.modifier_value > 0 ? '+' : '' }}{{ Math.round(event.modifier_value * 100) }}%
              </span>
            </span>
            <span class="ae-timer" v-if="event.remaining_minutes != null">
              ⏱ {{ event.remaining_minutes }} Min.
            </span>
          </div>
          <div class="ae-regions" v-if="!event.is_global && event.affected_regions?.length">
            <span v-for="r in event.affected_regions" :key="r" class="region-chip">{{ getRegionFlag(r) }} {{ r.toUpperCase() }}</span>
          </div>
        </div>
      </div>
      <div v-else class="active-empty">Keine aktiven Ereignisse — der Markt ist ruhig.</div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="studio-loading">
       <div class="studio-ring"></div>
       <span>Lade Event-Archiv...</span>
    </div>

    <!-- TEMPLATE GRID -->
    <div v-else>
      <div class="section-label" style="margin-bottom: 16px;">
        📋 EVENT-VORLAGEN ({{ templates.length }})
      </div>
      <div class="studio-grid">
         <div v-for="(template, index) in templates" :key="index" class="news-card" :class="'type-' + template.type">
            <div class="card-header">
               <span class="news-badge">{{ template.type.toUpperCase() }}</span>
               <div class="card-meta-badges">
                  <span v-if="template.affected_regions?.length" class="scope-badge regional">📍 {{ template.affected_regions.length }} Regionen</span>
                  <span v-else class="scope-badge global">🌍 GLOBAL</span>
               </div>
               <div class="card-actions">
                  <button @click="triggerEvent(index)" class="icon-btn text-green" title="Jetzt auslösen"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>
                  <button @click="openEditor(template, index)" class="icon-btn"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>
                  <button @click="confirmDelete(index)" class="icon-btn text-red"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
               </div>
            </div>
            
            <h3 class="news-headline">{{ template.title }}</h3>
            <p class="news-body">{{ template.description }}</p>

            <!-- Region Chips -->
            <div class="template-regions" v-if="template.affected_regions?.length">
              <span v-for="r in template.affected_regions" :key="r" class="region-chip small">{{ getRegionFlag(r) }} {{ r }}</span>
            </div>

            <div class="news-meta">
               <div class="meta-item">
                  <span class="meta-label">Modifier</span>
                  <span class="meta-val">{{ formatModifier(template.modifier_type) }}</span>
               </div>
               <div class="meta-item">
                  <span class="meta-label">Auswirkung</span>
                  <span class="meta-val" :class="getImpactColor(template.modifier_value)">{{ formatImpact(template.modifier_value) }}</span>
               </div>
               <div class="meta-item">
                  <span class="meta-label">Dauer</span>
                  <span class="meta-val">{{ template.duration_minutes }}m</span>
               </div>
            </div>
         </div>

         <!-- EMPTY STATE -->
         <div v-if="templates.length === 0" class="empty-state">
            <i class="fas fa-newspaper"></i>
            <p>Keine Event-Vorlagen im Archiv gefunden.</p>
         </div>
      </div>
    </div>

    <!-- EDITOR MODAL -->
    <div v-if="showEditor" class="modal-overlay" @click.self="closeEditor">
       <div class="modal-content">
          <div class="modal-header">
             <h2>{{ isEditing ? 'Event bearbeiten' : 'Neues Event erstellen' }}</h2>
             <button @click="closeEditor" class="close-btn">&times;</button>
          </div>
          
          <div class="modal-body">
             <div class="form-group">
                <label>Titel</label>
                <input v-model="form.title" type="text" placeholder="z.B. Energiekrise in Europa" class="sys-input" />
             </div>
             
             <div class="form-group">
                <label>Beschreibung</label>
                <textarea v-model="form.description" rows="3" placeholder="Beschreibung des Events..." class="sys-input"></textarea>
             </div>

             <div class="form-row">
                <div class="form-group">
                   <label>Event-Typ</label>
                   <select v-model="form.type" class="sys-select">
                      <option value="news">Neutrale Nachricht</option>
                      <option value="crisis">Krise</option>
                      <option value="boom">Wirtschaftsboom</option>
                      <option value="info">Info / Regulierung</option>
                      <option value="scandal">Skandal</option>
                   </select>
                </div>
                <div class="form-group">
                   <label>Dauer (Minuten)</label>
                   <input v-model.number="form.duration_minutes" type="number" min="5" max="1440" class="sys-input" />
                </div>
             </div>

             <div class="form-row">
                <div class="form-group">
                   <label>Zielvektor</label>
                   <select v-model="form.modifier_type" class="sys-select">
                      <option value="power_cost">Energiekosten</option>
                      <option value="order_frequency">Auftrags-Nachfrage</option>
                      <option value="order_value">Auftragswert</option>
                      <option value="repair_cost">Hardware-Kosten</option>
                      <option value="hardware_cost">Hardware-Einkauf</option>
                      <option value="satisfaction_decay">Abwanderungsrate</option>
                      <option value="security_risk">Cyber-Bedrohung</option>
                      <option value="compliance_demand">DSGVO-Nachfrage</option>
                      <option value="failure_rate">Ausfallrisiko</option>
                      <option value="tax_reduction">Steuererleichterung</option>
                   </select>
                </div>
                <div class="form-group">
                   <label>Auswirkung (Modifier)</label>
                   <div class="input-with-hint">
                      <input v-model.number="form.modifier_value" type="number" step="0.05" class="sys-input" />
                      <span class="hint" :class="form.modifier_value > 0 ? 'hint-green' : 'hint-red'">
                        {{ form.modifier_value > 0 ? '+' : '' }}{{ Math.round(form.modifier_value * 100) }}%
                      </span>
                   </div>
                </div>
             </div>

             <!-- REGION SELECTOR -->
             <div class="form-group">
                <label>Betroffene Regionen <span class="label-hint">(leer = Global)</span></label>
                <div class="region-selector">
                   <button 
                     v-for="(data, key) in availableRegions" 
                     :key="key"
                     class="region-select-btn"
                     :class="{ active: form.affected_regions?.includes(key) }"
                     @click="toggleRegion(key)"
                   >
                     {{ data.flag }} {{ key.toUpperCase() }}
                   </button>
                   <button class="region-select-btn clear-btn" @click="form.affected_regions = []" v-if="form.affected_regions?.length">
                     ✕ Alle entfernen (= Global)
                   </button>
                </div>
                <span class="form-hint" v-if="!form.affected_regions?.length">🌍 Event betrifft ALLE Regionen</span>
                <span class="form-hint" v-else>📍 Event betrifft nur: {{ form.affected_regions.join(', ') }}</span>
             </div>
          </div>

          <div class="modal-footer">
             <button @click="closeEditor" class="sys-btn sys-btn-ghost">Abbrechen</button>
             <button @click="saveTemplate" class="sys-btn sys-btn-primary">
                {{ isEditing ? 'Aktualisieren' : 'Veröffentlichen' }}
             </button>
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
const templates = ref([]);
const activeEvents = ref([]);
const availableRegions = ref({});
const showEditor = ref(false);
const editingIndex = ref(-1);

const form = ref({
    title: '',
    description: '',
    type: 'news',
    modifier_type: 'power_cost',
    modifier_value: 0.20,
    duration_minutes: 60,
    affected_regions: []
});

const isEditing = computed(() => editingIndex.value !== -1);

const fetchAll = async () => {
    loading.value = true;
    try {
        const [resConfig, resEvents] = await Promise.all([
            api.get('/admin/configs'),
            api.get('/world-events/active')
        ]);

        // Templates from config
        const groups = resConfig.configs || resConfig.data?.configs;
        let found = null;
        for (const items of Object.values(groups)) {
            const match = items.find(i => i.key === 'world_event_templates');
            if (match) { found = match.value; break; }
        }
        templates.value = found || [];

        // Regions from config
        for (const items of Object.values(groups)) {
            const match = items.find(i => i.key === 'regions');
            if (match) { 
                availableRegions.value = match.value || {}; 
                break; 
            }
        }

        // Active events
        if (resEvents.success) {
            activeEvents.value = resEvents.events || [];
        }
    } catch (e) {
        addToast('Fehler beim Laden der Event-Daten.', 'error');
    } finally {
        loading.value = false;
    }
};

const saveConfigs = async (newTemplates) => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'world_event_templates',
            value: newTemplates,
            comment: 'Aktualisiert via World Events Zentrale'
        });
        templates.value = newTemplates;
        addToast('Event-Vorlagen aktualisiert.', 'success');
        closeEditor();
    } catch (e) {
        addToast(e.message, 'error');
    } finally {
        setGlobalLoading(false);
    }
};

const openEditor = (template = null, index = -1) => {
    if (template) {
        form.value = { 
            ...template,
            affected_regions: template.affected_regions ? [...template.affected_regions] : []
        };
        editingIndex.value = index;
    } else {
        form.value = {
            title: '',
            description: '',
            type: 'news',
            modifier_type: 'power_cost',
            modifier_value: 0.20,
            duration_minutes: 60,
            affected_regions: []
        };
        editingIndex.value = -1;
    }
    showEditor.value = true;
};

const closeEditor = () => {
    showEditor.value = false;
};

const toggleRegion = (key) => {
    if (!form.value.affected_regions) form.value.affected_regions = [];
    const idx = form.value.affected_regions.indexOf(key);
    if (idx >= 0) {
        form.value.affected_regions.splice(idx, 1);
    } else {
        form.value.affected_regions.push(key);
    }
};

const saveTemplate = () => {
    if (!form.value.title || !form.value.description) {
        addToast('Titel und Beschreibung sind Pflichtfelder.', 'warning');
        return;
    }

    const templateData = { ...form.value };
    // Clean: empty array = global event (no regions key needed)
    if (!templateData.affected_regions?.length) {
        delete templateData.affected_regions;
    }

    const newTemplates = [...templates.value];
    if (isEditing.value) {
        newTemplates[editingIndex.value] = templateData;
    } else {
        newTemplates.push(templateData);
    }

    saveConfigs(newTemplates);
};

const confirmDelete = async (index) => {
    const confirmed = await requestConfirm({
        title: 'Vorlage löschen?',
        message: 'Diese Event-Vorlage wird aus dem globalen Pool entfernt.',
        confirmText: 'Löschen',
        confirmColor: 'red'
    });

    if (confirmed) {
        const newTemplates = [...templates.value];
        newTemplates.splice(index, 1);
        saveConfigs(newTemplates);
    }
};

const triggerEvent = async (index) => {
    requestConfirm('Dieses Event sofort live auslösen? Alle Spieler werden betroffen.', async () => {
        setGlobalLoading(true);
        try {
            await api.post('/admin/world-news/trigger', { template_index: index });
            addToast('Event wurde ausgelöst!', 'success');
            // Reload active events
            const resEvents = await api.get('/world-events/active');
            if (resEvents.success) activeEvents.value = resEvents.events || [];
        } catch(e) {
            addToast(e.message, 'error');
        } finally {
            setGlobalLoading(false);
        }
    });
};

// Helpers
const formatModifier = (key) => {
    const map = {
        power_cost: 'Energiekosten',
        order_frequency: 'Auftrags-Nachfrage',
        order_value: 'Auftragswert',
        repair_cost: 'Reparaturkosten',
        hardware_cost: 'Hardware-Kosten',
        satisfaction_decay: 'Abwanderungsrate',
        security_risk: 'Cyber-Risiko',
        compliance_demand: 'DSGVO-Nachfrage',
        failure_rate: 'Ausfallrisiko',
        tax_reduction: 'Steuersenkung'
    };
    return map[key] || key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
};

const formatImpact = (val) => {
    const pct = Math.round(val * 100);
    return pct > 0 ? `+${pct}%` : `${pct}%`;
};

const getImpactColor = (val) => {
    if (val > 0) return 'text-red';
    if (val < 0) return 'text-green';
    return 'text-zinc';
};

const getRegionFlag = (key) => {
    const flags = {
        us_east: '🇺🇸', us_west: '🇺🇸', eu_central: '🇩🇪', 
        asia_east: '🇯🇵', nordics: '🇸🇪', asia_south: '🇸🇬', south_america: '🇧🇷'
    };
    return flags[key] || '🌍';
};

onMounted(fetchAll);
</script>

<style scoped>
.studio-container { display: flex; flex-direction: column; gap: 28px; }

.studio-header { display: flex; flex-direction: column; gap: 24px; }
.studio-title-row { display: flex; justify-content: space-between; align-items: start; }
.studio-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.studio-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 4px; }

.header-actions { display: flex; gap: 10px; }

/* ── ACTIVE EVENTS DASHBOARD ────── */
.active-events-section {
    background: #09090b;
    border: 1px solid #27272a;
    border-radius: 16px;
    padding: 20px;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #a1a1aa;
    margin-bottom: 16px;
}

.pulse-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #3fb950;
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(63, 185, 80, 0.4); }
    50% { box-shadow: 0 0 0 6px rgba(63, 185, 80, 0); }
}

.active-events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.active-event-card {
    background: #18181b;
    border: 1px solid #27272a;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-left: 3px solid #71717a;
}

.active-event-card.crisis { border-left-color: #ef4444; }
.active-event-card.boom { border-left-color: #10b981; }
.active-event-card.info { border-left-color: #3b82f6; }

.ae-header { display: flex; justify-content: space-between; align-items: center; }

.ae-type-badge {
    font-size: 0.55rem; font-weight: 800; padding: 2px 6px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.ae-type-badge.crisis { background: rgba(239,68,68,0.15); color: #ef4444; }
.ae-type-badge.boom { background: rgba(16,185,129,0.15); color: #10b981; }
.ae-type-badge.info { background: rgba(59,130,246,0.15); color: #3b82f6; }

.ae-scope { font-size: 0.6rem; font-weight: 700; color: #71717a; }
.ae-scope.global { color: #c084fc; }

.ae-title { font-weight: 800; font-size: 0.95rem; color: white; }
.ae-desc { font-size: 0.75rem; color: #71717a; line-height: 1.4; }
.ae-footer { display: flex; justify-content: space-between; font-size: 0.7rem; font-family: monospace; color: #52525b; padding-top: 8px; border-top: 1px solid #27272a; }
.ae-modifier { font-weight: 600; }
.ae-timer { color: #d29922; font-weight: 700; }

.ae-regions { display: flex; flex-wrap: wrap; gap: 4px; }

.active-empty { color: #3f3f46; font-size: 0.8rem; text-align: center; padding: 20px; font-style: italic; }

/* ── TEMPLATE GRID ────── */
.studio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

.news-card {
    background: #09090b; border: 1px solid #27272a; border-radius: 16px; padding: 24px;
    display: flex; flex-direction: column; gap: 14px; position: relative; overflow: hidden;
    transition: all 0.2s ease;
}
.news-card:hover { transform: translateY(-2px); border-color: #3f3f46; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); }

.news-card.type-crisis { border-left: 4px solid #ef4444; }
.news-card.type-boom { border-left: 4px solid #10b981; }
.news-card.type-news { border-left: 4px solid #3b82f6; }
.news-card.type-info { border-left: 4px solid #8b5cf6; }
.news-card.type-scandal { border-left: 4px solid #f59e0b; }

.card-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.news-badge { 
    font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; 
    padding: 4px 8px; border-radius: 6px; background: #18181b; color: #a1a1aa; flex-shrink: 0;
}

.card-meta-badges { display: flex; gap: 6px; }
.scope-badge {
    font-size: 0.55rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;
}
.scope-badge.global { background: rgba(192, 132, 252, 0.1); color: #c084fc; }
.scope-badge.regional { background: rgba(250, 204, 21, 0.1); color: #facc15; }

.card-actions { display: flex; gap: 8px; opacity: 0; transition: opacity 0.2s; }
.news-card:hover .card-actions { opacity: 1; }

.icon-btn { background: none; border: none; color: #71717a; cursor: pointer; transition: color 0.2s; }
.icon-btn:hover { color: white; }

.news-headline { font-size: 1.05rem; font-weight: 800; color: white; margin: 0; line-height: 1.2; font-family: 'JetBrains Mono', monospace; }
.news-body { font-size: 0.8rem; color: #a1a1aa; margin: 0; line-height: 1.5; flex-grow: 1; }

.template-regions { display: flex; flex-wrap: wrap; gap: 4px; }

.region-chip {
    font-size: 0.6rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;
    background: rgba(255,255,255,0.05); color: #a1a1aa; border: 1px solid #27272a;
}
.region-chip.small { font-size: 0.55rem; padding: 1px 6px; }

.news-meta { 
    display: flex; gap: 16px; padding-top: 14px; border-top: 1px solid #18181b; 
    font-size: 0.7rem; 
}
.meta-item { display: flex; flex-direction: column; gap: 2px; }
.meta-label { color: #52525b; font-weight: 700; text-transform: uppercase; font-size: 0.55rem; }
.meta-val { color: #e4e4e7; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.text-red { color: #ef4444; }
.text-green { color: #10b981; }

.empty-state {
    grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 64px; color: #27272a; gap: 16px; border: 2px dashed #18181b; border-radius: 24px;
}
.empty-state i { font-size: 32px; }

/* ── MODAL ────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center; z-index: 50; animation: fadeIn 0.2s;
}
.modal-content {
    background: #09090b; border: 1px solid #27272a; width: 600px; max-width: 95vw; max-height: 90vh; border-radius: 24px;
    display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.25s; overflow-y: auto;
}
.modal-header { padding: 24px; border-bottom: 1px solid #18181b; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 1.1rem; color: white; font-weight: 800; }
.close-btn { background: none; border: none; color: #52525b; font-size: 1.5rem; cursor: pointer; }
.close-btn:hover { color: white; }

.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
.modal-footer { padding: 20px 24px; border-top: 1px solid #18181b; display: flex; justify-content: flex-end; gap: 12px; }

.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 0.7rem; color: #a1a1aa; font-weight: 700; text-transform: uppercase; }
.label-hint { font-weight: 400; color: #52525b; text-transform: none; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.sys-input, .sys-select, textarea {
    background: #18181b; border: 1px solid #27272a; color: white; padding: 10px 12px;
    border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.9rem; outline: none;
    transition: border-color 0.2s; width: 100%; box-sizing: border-box;
}
.sys-input:focus, textarea:focus, .sys-select:focus { border-color: #3b82f6; }

.input-with-hint { position: relative; }
.hint { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #71717a; font-family: monospace; font-weight: 700; }
.hint-green { color: #10b981; }
.hint-red { color: #ef4444; }

.form-hint { font-size: 0.7rem; color: #52525b; font-style: italic; }

/* ── REGION SELECTOR ────── */
.region-selector {
    display: flex; flex-wrap: wrap; gap: 8px;
}

.region-select-btn {
    padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 700;
    background: #18181b; border: 1px solid #27272a; color: #a1a1aa; cursor: pointer;
    transition: all 0.2s;
}
.region-select-btn:hover { border-color: #3b82f6; color: #e4e4e7; }
.region-select-btn.active { background: rgba(59, 130, 246, 0.15); border-color: #3b82f6; color: #60a5fa; }
.region-select-btn.clear-btn { border-color: #ef4444; color: #ef4444; background: rgba(239,68,68,0.05); font-size: 0.6rem; }
.region-select-btn.clear-btn:hover { background: rgba(239,68,68,0.15); }

/* ── BUTTONS ────── */
.sys-btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
.sys-btn-primary { background: white; color: black; }
.sys-btn-primary:hover { background: #e4e4e7; }
.sys-btn-secondary { background: #18181b; color: #e4e4e7; border: 1px solid #27272a; }
.sys-btn-secondary:hover { border-color: #52525b; }
.sys-btn-ghost { background: transparent; color: #a1a1aa; }
.sys-btn-ghost:hover { color: white; background: #18181b; }

.studio-loading { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 60px; color: #52525b; }
.studio-ring { width: 32px; height: 32px; border: 2px solid #27272a; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
