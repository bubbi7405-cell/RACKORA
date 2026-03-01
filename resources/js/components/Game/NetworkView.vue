<template>
    <div class="network-view ds-control-room-bg">
        <!-- ─── HEADER ─── -->
        <header class="noc-header">
            <div class="title-block">
                <h1>NETWORK_OPERATIONS_CENTER</h1>
                <p class="noc-subtitle">Infrastructure Connectivity · Bandwidth Management · Threat Intelligence</p>
            </div>
            <div class="noc-status-pill" :class="'severity-' + netStore.saturationSeverity">
                <StatusIndicator :severity="netStore.saturationSeverity" :pulse="netStore.bandwidth.saturation > 80" />
                <span>{{ netStore.saturationLabel }}</span>
            </div>
        </header>

        <!-- ─── PRIMARY KPI STRIP ─── -->
        <div class="noc-kpi-strip">
            <KpiGauge
                label="AVG_LATENCY"
                :value="netStore.metrics.latencyMs"
                unit="ms"
                :thresholds="{ nominal: 30, caution: 60, warning: 100 }"
                :spark-data="latencyHistory"
                :show-sparkline="true"
            />
            <KpiGauge
                label="PACKET_LOSS"
                :value="netStore.metrics.packetLoss * 100"
                unit="%"
                :decimals="3"
                :thresholds="{ nominal: 0.01, caution: 0.1, warning: 1.0 }"
                :spark-data="packetLossHistory"
                :show-sparkline="true"
            />
            <KpiGauge
                label="THROUGHPUT"
                :value="netStore.bandwidth.totalUsedGbps"
                unit="Gbps"
                :show-bar="true"
                :bar-percent="netStore.bandwidth.saturation"
                :bar-label="netStore.bandwidth.totalUsedGbps.toFixed(1)"
                :bar-max-label="netStore.bandwidth.totalCapacityGbps.toFixed(1) + 'G'"
                :thresholds="{ nominal: 70, caution: 85, warning: 95 }"
                :spark-data="throughputHistory"
                :show-sparkline="true"
            />
            <KpiGauge
                label="SLA_COMPLIANCE"
                :value="netStore.metrics.slaCompliance"
                unit="%"
                :decimals="2"
                :thresholds="{ nominal: 99, caution: 97, warning: 95 }"
                :inverted="true"
                :spark-data="slaHistory"
                :show-sparkline="true"
            />
            <KpiGauge
                label="NET_HEALTH"
                :value="netStore.metrics.healthScore"
                unit=""
                :thresholds="{ nominal: 80, caution: 60, warning: 30 }"
                :inverted="true"
                :show-bar="true"
                :bar-percent="netStore.metrics.healthScore"
            />
        </div>

        <!-- ─── TAB NAVIGATION ─── -->
        <nav class="noc-tabs">
            <button
                v-for="tab in tabs" :key="tab.id"
                class="noc-tab" :class="{ 'is-active': activeTab === tab.id }"
                @click="activeTab = tab.id"
            >
                <span class="tab-icon">{{ tab.icon }}</span>
                <span class="tab-label">{{ tab.label }}</span>
                <span class="tab-badge" v-if="tab.badge" :class="'badge-' + tab.badgeType">{{ tab.badge }}</span>
            </button>
        </nav>

        <!-- ════════════════════════════════════════════════
             TAB: OVERVIEW
             ════════════════════════════════════════════════ -->
        <div class="noc-content" v-if="activeTab === 'overview'">
            <div class="noc-grid-2col">
                <!-- LEFT: Resources + ISP -->
                <div class="noc-stack">
                    <!-- Resource Allocation -->
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">RESOURCE_ALLOCATION</h3>
                            <StatusIndicator :severity="netStore.ipv4Severity" :label="netStore.ipv4Severity === 'critical' ? 'EXHAUSTED' : ''" />
                        </div>

                        <div class="resource-block">
                            <div class="resource-meta">
                                <span class="ds-label">IPv4_POOL</span>
                                <span class="ds-value mono">{{ netStore.ips.ipv4.used }}<span class="dim">/{{ netStore.ips.ipv4.total }}</span></span>
                            </div>
                            <div class="noc-progress" :class="'severity-' + netStore.ipv4Severity">
                                <div class="noc-progress-fill" :style="{ width: Math.min(100, netStore.ips.ipv4.percent) + '%' }"></div>
                            </div>
                        </div>

                        <div class="resource-block">
                            <div class="resource-meta">
                                <span class="ds-label">IPv6_POOL</span>
                                <span class="ds-value mono">{{ formatCompact(netStore.ips.ipv6.used) }}<span class="dim">/{{ formatCompact(netStore.ips.ipv6.total) }}</span></span>
                            </div>
                            <div class="noc-progress ipv6">
                                <div class="noc-progress-fill" :style="{ width: Math.min(100, netStore.ips.ipv6.percent) + '%' }"></div>
                            </div>
                        </div>

                        <div class="noc-divider"></div>

                        <div class="resource-block">
                            <div class="resource-meta">
                                <span class="ds-label">BANDWIDTH_UTILIZATION</span>
                                <span class="ds-value mono">{{ netStore.bandwidth.totalUsedGbps.toFixed(2) }}<span class="dim">/{{ netStore.bandwidth.totalCapacityGbps.toFixed(2) }} Gbps</span></span>
                            </div>
                            <div class="noc-progress" :class="'severity-' + netStore.saturationSeverity">
                                <div class="noc-progress-fill" :style="{ width: Math.min(100, netStore.bandwidth.saturation) + '%' }"></div>
                                <div class="noc-peak-marker" style="left: 92%"></div>
                            </div>
                        </div>
                    </section>

                    <!-- ISP & Contract Card -->
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">ISP_CONTRACT</h3>
                            <span class="isp-badge" :class="'tier-' + netStore.isp.tier">{{ netStore.bandwidth.tierLabel }}</span>
                        </div>
                        <div class="isp-info-grid">
                            <div class="isp-stat">
                                <span class="ds-label">PROVIDER</span>
                                <span class="ds-value">{{ netStore.isp.label }}</span>
                            </div>
                            <div class="isp-stat">
                                <span class="ds-label">CONTRACT</span>
                                <span class="ds-value mono">{{ (netStore.bandwidth.contractMbps / 1000).toFixed(0) }} Gbps</span>
                            </div>
                            <div class="isp-stat">
                                <span class="ds-label">MONTHLY_COST</span>
                                <span class="ds-value mono">${{ netStore.isp.monthlyCost.toLocaleString() }}</span>
                            </div>
                            <div class="isp-stat">
                                <span class="ds-label">JITTER</span>
                                <span class="ds-value mono">{{ netStore.metrics.jitterMs.toFixed(1) }}ms</span>
                            </div>
                        </div>
                    </section>

                    <!-- Security -->
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">SECURITY_POSTURE</h3>
                        </div>
                        <div class="security-grid-v2">
                            <div class="sec-card">
                                <div class="sec-icon-wrap" :class="'shield-' + netStore.infrastructure.ddosProtection">🛡️</div>
                                <div class="sec-info">
                                    <span class="ds-label">DDOS_PROTECTION</span>
                                    <span class="ds-value">{{ netStore.infrastructure.ddosProtectionLabel }}</span>
                                    <div class="level-pips">
                                        <div class="pip" v-for="n in 3" :key="n" :class="{ active: n <= netStore.infrastructure.ddosProtection }"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="sec-card">
                                <div class="sec-icon-wrap rep">📡</div>
                                <div class="sec-info">
                                    <span class="ds-label">NET_REPUTATION</span>
                                    <span class="ds-value" :class="netStore.metrics.reputation > 90 ? 'severity-nominal' : 'severity-caution'">{{ netStore.metrics.reputation.toFixed(1) }}%</span>
                                    <span class="sec-tag">{{ netStore.metrics.reputation > 90 ? 'TRUSTED' : 'MONITORING' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT: QoS + BGP + Traffic -->
                <div class="noc-stack">
                    <!-- Quality of Service Ring -->
                    <section class="ds-card qos-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">QUALITY_OF_SERVICE</h3>
                        </div>
                        <div class="qos-ring-wrap">
                            <div class="qos-ring" :style="slaRingStyle">
                                <div class="qos-ring-inner">
                                    <span class="qos-label">SLA</span>
                                    <span class="qos-val mono">{{ netStore.metrics.slaCompliance.toFixed(2) }}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="qos-metrics">
                            <div class="qos-metric">
                                <span class="ds-label">PACKET_LOSS</span>
                                <span class="ds-value mono" :class="netStore.metrics.packetLoss > 0.01 ? 'severity-warning' : ''">{{ (netStore.metrics.packetLoss * 100).toFixed(3) }}%</span>
                            </div>
                            <div class="qos-metric">
                                <span class="ds-label">LATENCY</span>
                                <span class="ds-value mono" :class="netStore.metrics.latencyMs > 60 ? 'severity-caution' : ''">{{ netStore.metrics.latencyMs.toFixed(1) }}ms</span>
                            </div>
                            <div class="qos-metric">
                                <span class="ds-label">JITTER</span>
                                <span class="ds-value mono">{{ netStore.metrics.jitterMs.toFixed(1) }}ms</span>
                            </div>
                        </div>
                    </section>

                    <!-- BGP / ASN -->
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">BGP_ROUTING</h3>
                        </div>
                        <div class="bgp-block">
                            <div class="bgp-asn">
                                <span class="ds-label">AUTONOMOUS_SYSTEM</span>
                                <span class="bgp-asn-val mono">AS{{ netStore.infrastructure.asn || '____' }}</span>
                            </div>
                            <div class="bgp-peering">
                                <StatusIndicator :severity="netStore.hasAsn ? 'nominal' : 'offline'" />
                                <span class="ds-value">{{ netStore.infrastructure.peeringLabel }}</span>
                            </div>
                            <button
                                class="noc-btn"
                                :disabled="netStore.isLoading"
                                @click="netStore.hasAsn ? netStore.upgradePeering() : netStore.establishASN()"
                            >
                                {{ netStore.hasAsn ? 'UPGRADE_PEERING' : 'ESTABLISH_ASN' }}
                            </button>
                        </div>
                    </section>

                    <!-- Traffic Overview -->
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">TRAFFIC_FLOW</h3>
                        </div>
                        <div class="traffic-grid">
                            <div class="traffic-stat">
                                <span class="ds-label">INBOUND</span>
                                <span class="ds-value mono traffic-in">▼ {{ netStore.traffic.inGbps.toFixed(2) }} Gbps</span>
                            </div>
                            <div class="traffic-stat">
                                <span class="ds-label">OUTBOUND</span>
                                <span class="ds-value mono traffic-out">▲ {{ netStore.traffic.outGbps.toFixed(2) }} Gbps</span>
                            </div>
                            <div class="traffic-stat">
                                <span class="ds-label">RATIO (IN:OUT)</span>
                                <span class="ds-value mono">{{ netStore.traffic.ratio }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════
             TAB: IPv4 MARKET
             ════════════════════════════════════════════════ -->
        <div class="noc-content" v-if="activeTab === 'ipv4market'">
            <div class="noc-grid-2col">
                <div class="noc-stack">
                    <section class="ds-card">
                        <div class="ds-card-header">
                            <h3 class="ds-label">IPv4_ADDRESS_MARKET</h3>
                            <div class="market-trend-v2" v-if="netStore.market.loaded">
                                <span class="ds-label">MARKET:</span>
                                <span class="trend-pill" :class="'trend-' + (netStore.market.trend?.priceDirection || 'stable')">
                                    {{ netStore.market.trend?.label || 'LOADING' }}
                                </span>
                            </div>
                        </div>

                        <div class="pool-kpi-row" v-if="netStore.market.loaded">
                            <div class="pool-kpi">
                                <span class="ds-label">TOTAL_POOL</span>
                                <span class="ds-value mono">{{ netStore.market.pool?.total || netStore.ips.ipv4.total }}</span>
                            </div>
                            <div class="pool-kpi">
                                <span class="ds-label">IN_USE</span>
                                <span class="ds-value mono severity-caution">{{ netStore.market.pool?.used || netStore.ips.ipv4.used }}</span>
                            </div>
                            <div class="pool-kpi">
                                <span class="ds-label">AVAILABLE</span>
                                <span class="ds-value mono severity-nominal">{{ netStore.ipv4Available }}</span>
                            </div>
                            <div class="pool-kpi">
                                <span class="ds-label">SCARCITY_IDX</span>
                                <span class="ds-value mono" :class="(netStore.market.trend?.scarcityIndex || 0) > 50 ? 'severity-warning' : ''">{{ netStore.market.trend?.scarcityIndex || 0 }}%</span>
                            </div>
                        </div>

                        <div class="market-loading" v-if="!netStore.market.loaded && netStore.isLoading">
                            <div class="noc-spinner"></div>
                            <span>Fetching market data...</span>
                        </div>

                        <div class="block-grid-v2" v-else-if="netStore.market.loaded">
                            <div
                                class="block-card-v2"
                                v-for="block in netStore.market.blocks"
                                :key="block.size"
                                :class="{ 'is-locked': !block.available }"
                            >
                                <div class="block-cidr mono">{{ block.cidr }}</div>
                                <div class="block-count">{{ block.size }} <span>IPs</span></div>
                                <div class="block-price mono">${{ block.price.toLocaleString() }}</div>
                                <div class="block-locked-label" v-if="!block.available">🔒 REP ≥ {{ block.minRep }}</div>
                                <div class="block-actions" v-else>
                                    <button class="noc-btn noc-btn-success" :disabled="netStore.isLoading" @click="netStore.buyIpv4Block(block.size)">BUY</button>
                                    <button
                                        class="noc-btn noc-btn-danger"
                                        v-if="block.size <= 64"
                                        :disabled="netStore.isLoading || netStore.ipv4Available < block.size"
                                        @click="netStore.sellIpv4Block(block.size)"
                                    >SELL</button>
                                </div>
                            </div>
                        </div>

                        <div class="market-note-v2">
                            <span>ℹ️</span>
                            <span>IPv4 prices increase with your pool size (scarcity model). Sell price = 40% of market value. Min pool: 8 IPs.</span>
                        </div>
                    </section>
                </div>

                <div class="noc-stack">
                    <section class="ds-card">
                        <div class="ds-card-header"><h3 class="ds-label">PRICING_MODEL</h3></div>
                        <div class="pricing-rows">
                            <div class="price-row-v2"><span class="ds-label">MAINTENANCE_COST</span><span class="ds-value mono">${{ (0.05 * netStore.ips.ipv4.total).toFixed(2) }}/hr</span></div>
                            <div class="price-row-v2"><span class="ds-label">PER_IP_COST</span><span class="ds-value mono">$0.05/hr</span></div>
                            <div class="price-row-v2"><span class="ds-label">DAILY_BURN</span><span class="ds-value mono severity-caution">${{ (0.05 * netStore.ips.ipv4.total * 24).toFixed(2) }}</span></div>
                            <div class="noc-divider"></div>
                            <div class="price-row-v2"><span class="ds-label">IPv6_STATUS</span><span class="ds-value mono severity-nominal">FREE_ALLOC</span></div>
                        </div>
                    </section>

                    <section class="ds-card">
                        <div class="ds-card-header"><h3 class="ds-label">STRATEGY_TIPS</h3></div>
                        <div class="tips-v2">
                            <div class="tip-v2" :class="{ 'tip-urgent': netStore.ips.ipv4.percent > 80 }">
                                <span>{{ netStore.ips.ipv4.percent > 80 ? '⚠️' : '✅' }}</span>
                                <span>{{ netStore.ips.ipv4.percent > 80 ? 'IPv4 pool nearing exhaustion — buy more blocks!' : 'IPv4 pool healthy' }}</span>
                            </div>
                            <div class="tip-v2"><span>🔬</span><span>Research "IPv6 Transition" to cut IPv4 costs by 50%</span></div>
                            <div class="tip-v2"><span>📈</span><span>Higher reputation unlocks larger (cheaper per-IP) blocks</span></div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════
             TAB: DDoS PROTECTION
             ════════════════════════════════════════════════ -->
        <div class="noc-content" v-if="activeTab === 'ddos'">
            <div class="noc-wide">
                <section class="ds-card">
                    <div class="ds-card-header">
                        <h3 class="ds-label">DDOS_MITIGATION_CENTER</h3>
                    </div>

                    <div class="ddos-hero">
                        <div class="ddos-shield-large" :class="'shield-level-' + netStore.infrastructure.ddosProtection">
                            <span class="shield-emoji">🛡️</span>
                            <span class="shield-lvl mono">LVL {{ netStore.infrastructure.ddosProtection }}</span>
                        </div>
                        <div class="ddos-hero-info">
                            <div class="ddos-hero-name">{{ netStore.currentDdosTier.label }}</div>
                            <div class="ddos-hero-desc">{{ netStore.currentDdosTier.desc }}</div>
                        </div>
                    </div>

                    <div class="ddos-tiers-row">
                        <div
                            class="ddos-tier-card"
                            v-for="tier in netStore.ddosTiers" :key="tier.level"
                            :class="{
                                'is-current': tier.level === netStore.infrastructure.ddosProtection,
                                'is-unlocked': tier.level < netStore.infrastructure.ddosProtection,
                                'is-next': tier.level === netStore.infrastructure.ddosProtection + 1
                            }"
                        >
                            <div class="tier-head">
                                <span class="ds-label">TIER_{{ tier.level }}</span>
                                <span class="tier-check" v-if="tier.level <= netStore.infrastructure.ddosProtection">✅</span>
                                <span class="tier-lock" v-else-if="tier.level > netStore.infrastructure.ddosProtection + 1">🔒</span>
                            </div>
                            <div class="tier-title">{{ tier.label }}</div>
                            <div class="tier-mit"><span class="ds-label">MITIGATION</span><span class="ds-value mono severity-nominal">{{ tier.mitigation }}</span></div>
                            <div class="tier-price mono" v-if="tier.cost > 0 && tier.level > netStore.infrastructure.ddosProtection">${{ tier.cost.toLocaleString() }}</div>
                            <button
                                class="noc-btn noc-btn-accent"
                                v-if="tier.level === netStore.infrastructure.ddosProtection + 1"
                                :disabled="netStore.isLoading"
                                @click="netStore.upgradeDdos()"
                            >UPGRADE_NOW</button>
                        </div>
                    </div>

                    <div class="ddos-stats-row">
                        <div class="ddos-stat-card">
                            <span class="ds-label">ATTACKS_MITIGATED</span>
                            <span class="ds-value mono">{{ netStore.infrastructure.ddosEventsTotal }}</span>
                        </div>
                        <div class="ddos-stat-card">
                            <span class="ds-label">MITIGATION_CAPACITY</span>
                            <span class="ds-value mono severity-nominal">
                                {{ (netStore.infrastructure.ddosMitigationCapacity * 100).toFixed(0) }}%
                                <span v-if="netStore.infrastructure.firewallBonus > 0" 
                                      class="firewall-bonus-indicator" 
                                      title="Bonus from active VPC firewalls"
                                      style="font-size: 0.65rem; color: #58a6ff; margin-left: 4px; border: 1px solid #58a6ff; padding: 1px 4px; border-radius: 4px;">
                                    +{{ (netStore.infrastructure.firewallBonus * 100).toFixed(0) }}%_VPC
                                </span>
                            </span>
                        </div>
                        <div class="ddos-stat-card">
                            <span class="ds-label">THREAT_LEVEL</span>
                            <span class="ds-value mono" :class="netStore.bandwidth.saturation > 80 ? 'severity-warning' : 'severity-nominal'">
                                {{ netStore.bandwidth.saturation > 80 ? 'ELEVATED' : 'LOW' }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <!-- ════════════════════════════════════════════════
             TAB: PRIVATE NETWORKS (VPC)
             ════════════════════════════════════════════════ -->
        <div class="noc-content" v-if="activeTab === 'vpc'">
             <!-- DETAILED FIREWALL VIEW -->
              <div v-if="selectedNetwork" class="selected-net-details ds-card" style="margin-bottom: 20px;">
                      <div class="selected-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
                          <div class="header-left">
                              <button class="noc-btn" style="margin-bottom: 8px;" @click="selectedNetwork = null">↩ BACK_TO_NETWORKS</button>
                              <h2 class="ds-title" style="margin: 0;">{{ selectedNetwork.name }}</h2>
                              <div class="net-stats-row" style="margin-top: 5px;">
                                  <span class="stat-pill"><span class="label">CIDR</span> {{ selectedNetwork.cidr }}</span>
                                  <span class="stat-pill"><span class="label">VLAN</span> {{ selectedNetwork.vlanTag }}</span>
                                  <span class="stat-pill"><span class="label">SERVERS</span> {{ selectedNetwork.serverCount }}</span>
                              </div>
                          </div>
                          
                          <div class="firewall-metrics" style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                              <div class="ds-label" style="font-size: 0.5rem; opacity: 0.6;">NETWORK_TRAFFIC_STATS</div>
                              <div style="display: flex; gap: 8px;">
                                  <div class="metric-mini" style="background: rgba(63,185,80,0.1); border: 1px solid rgba(63,185,80,0.2); padding: 5px 12px; border-radius: 4px; display: flex; align-items: center;">
                                      <span style="font-size: 0.5rem; color: #3fb950; font-weight: 800; letter-spacing: 0.05em;">PASS</span>
                                      <span style="font-size: 0.75rem; font-weight: 900; margin-left: 8px; color: #3fb950;">{{ selectedNetwork.metrics?.allowed || 0 }}</span>
                                  </div>
                                  <div class="metric-mini" style="background: rgba(248,81,73,0.1); border: 1px solid rgba(248,81,73,0.2); padding: 5px 12px; border-radius: 4px; display: flex; align-items: center;">
                                      <span style="font-size: 0.5rem; color: #f85149; font-weight: 800; letter-spacing: 0.05em;">DROP</span>
                                      <span style="font-size: 0.75rem; font-weight: 900; margin-left: 8px; color: #f85149;">{{ selectedNetwork.metrics?.denied || 0 }}</span>
                                  </div>
                              </div>
                          </div>
                      </div>
              </div>
              
              <div class="noc-grid-2col" v-if="selectedNetwork">
                  <div class="noc-stack">
                      <section class="ds-card">
                          <div class="ds-card-header">
                              <h3 class="ds-label">FIREWALL_RULES_CONFIG</h3>
                          </div>
                         
                         <!-- Add Rule Form -->
                         <div class="fw-form-card" style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                             <h4 class="ds-label" style="margin-bottom: 10px;">ADD_RULE</h4>
                             <div style="display: grid; grid-template-columns: 0.5fr 1fr 1fr 1fr 1.5fr auto; gap: 10px; align-items: end;">
                                 <div class="field">
                                     <label class="ds-label">PRIO</label>
                                     <input v-model="newRule.priority" type="number" class="ds-input">
                                 </div>
                                 <div class="field">
                                     <label class="ds-label">TYPE</label>
                                     <select v-model="newRule.type" class="ds-select">
                                         <option>ALLOW</option>
                                         <option>DENY</option>
                                     </select>
                                 </div>
                                 <div class="field">
                                     <label class="ds-label">PROTO</label>
                                     <select v-model="newRule.protocol" class="ds-select">
                                         <option>TCP</option>
                                         <option>UDP</option>
                                         <option>ICMP</option>
                                         <option>ANY</option>
                                     </select>
                                 </div>
                                 <div class="field">
                                     <label class="ds-label">PORT(S)</label>
                                     <input v-model="newRule.port_range" class="ds-input" placeholder="80, 443">
                                 </div>
                                 <div class="field">
                                     <label class="ds-label">SOURCE CIDR</label>
                                     <input v-model="newRule.source_cidr" class="ds-input">
                                 </div>
                                 <button class="noc-btn noc-btn-success" @click="addRule" :disabled="netStore.isLoading">+</button>
                             </div>
                         </div>

                         <!-- Rules List -->
                         <div class="fw-table">
                             <div class="fw-row header" style="display: grid; grid-template-columns: 0.5fr 1fr 1fr 1fr 1.5fr auto; padding: 10px; font-size: 0.7rem; opacity: 0.7; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                 <span>PRIORITY</span>
                                 <span>TYPE</span>
                                 <span>PROTOCOL</span>
                                 <span>PORT</span>
                                 <span>SOURCE</span>
                                 <span>ACTION</span>
                             </div>
                             <div class="fw-row" v-for="rule in selectedNetwork.firewallRules" :key="rule.id" 
                                  style="display: grid; grid-template-columns: 0.5fr 1fr 1fr 1fr 1.5fr auto; padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); align-items: center;">
                                 <span class="mono">{{ rule.priority }}</span>
                                 <span style="font-weight: 800;" :style="{ color: rule.type === 'ALLOW' ? 'var(--ds-severity-nominal)' : 'var(--ds-severity-critical)' }">{{ rule.type }}</span>
                                 <span>{{ rule.protocol }}</span>
                                 <span class="mono">{{ rule.port || 'ALL' }}</span>
                                 <span class="mono">{{ rule.source }}</span>
                                 <button style="border: none; background: none; color: var(--ds-severity-critical); cursor: pointer;" @click="deleteRule(rule.id)">×</button>
                             </div>
                             <div v-if="!selectedNetwork.firewallRules?.length" class="dim" style="text-align: center; padding: 30px; font-size: 0.8rem;">
                                 No firewall rules defined.
                             </div>
                         </div>
                     </section>
                 </div>
                 
                 <div class="noc-stack">
                     <section class="ds-card">
                        <div class="ds-card-header"><h3 class="ds-label">FIREWALL_HELP</h3></div>
                        <p style="font-size: 0.8rem; line-height: 1.6; color: var(--ds-text-ghost, #8b949e);">
                            Define traffic rules for your Private Network.
                            <br><br>
                            Rules are evaluated in order of <strong>Priority</strong> (smallest number first).
                            <br>
                            Example:
                            <ul style="padding-left: 20px; margin-top: 10px;">
                                <li><strong>10: ALLOW TCP 22</strong> (SSH) from Admin IP</li>
                                <li><strong>20: ALLOW TCP 80</strong> (HTTP) from 0.0.0.0/0</li>
                                <li><strong>999: DENY ANY</strong> from 0.0.0.0/0</li>
                            </ul>
                        </p>
                     </section>

                     <!-- Test Tool -->
                     <section class="ds-card" style="border-color: rgba(88,166,255,0.2);">
                        <div class="ds-card-header"><h3 class="ds-label">TEST_FIREWALL</h3></div>
                        <div class="create-form" style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <select v-model="testRuleData.protocol" class="ds-select">
                                    <option value="TCP">TCP</option>
                                    <option value="UDP">UDP</option>
                                    <option value="ICMP">ICMP</option>
                                    <option value="ANY">ANY</option>
                                </select>
                                <input v-model.number="testRuleData.port" type="number" placeholder="Port" class="ds-input">
                            </div>
                            <input v-model="testRuleData.source_ip" placeholder="Source IP (e.g. 1.2.3.4)" class="ds-input">
                            
                            <button class="noc-btn noc-btn-accent" style="width: 100%; justify-content: center;"
                                    @click="testRule" :disabled="netStore.isLoading">
                                RUN_SIMULATION
                            </button>

                            <div v-if="testResult" :class="['test-result', testResult.allowed ? 'res-allowed' : 'res-denied']"
                                 style="padding: 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-align: center; margin-top: 5px; border: 1px solid;">
                                {{ testResult.message.toUpperCase() }}
                            </div>
                        </div>
                     </section>
                 </div>
             </div>

             <!-- LIST VIEW -->
             <div class="noc-grid-2col" v-else>
                 <div class="noc-stack">
                     <!-- Create New -->
                     <section class="ds-card">
                         <div class="ds-card-header"><h3 class="ds-label">NEW_PRIVATE_NETWORK</h3></div>
                         <div class="create-form" style="display: flex; gap: 10px; flex-direction: column;">
                           <div style="display: flex; gap: 10px;">
                             <input v-model="newNetName" placeholder="Network Name" class="ds-input" style="flex: 2; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 6px;">
                             <select v-model="newNetCidr" class="ds-select" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 6px;">
                                 <option value="10.0.0.0/24">10.0.0.x</option>
                                 <option value="192.168.1.0/24">192.168.1.x</option>
                                 <option value="172.16.0.0/24">172.16.0.x</option>
                             </select>
                           </div>
                           <button class="noc-btn" 
                                   style="background: var(--ds-accent-primary, #58a6ff); color: #0d1117; width: 100%; justify-content: center; font-weight: 800;"
                                   @click="createNetwork" 
                                   :disabled="!newNetName || netStore.isLoading">
                               CREATE_VLAN
                           </button>
                         </div>
                     </section>

                     <!-- List -->
                     <section class="ds-card" v-for="net in netStore.privateNetworks" :key="net.id">
                         <div class="ds-card-header">
                             <div style="display: flex; align-items: center; gap: 10px;">
                                 <h3 class="ds-label" style="font-size: 0.9rem; color: #fff;">{{ net.name }}</h3>
                                 <span class="ds-label" style="background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px;">VLAN {{ net.vlanTag }}</span>
                             </div>
                             <div style="display: flex; gap: 10px;">
                                 <button class="noc-btn" style="font-size: 0.7rem; padding: 4px 8px;" @click="selectedNetwork = net">MANAGE FIREWALL</button>
                                 <button style="background: none; border: none; color: var(--ds-severity-critical, #f85149); font-size: 1.2rem; cursor: pointer; padding: 0 5px;" 
                                         @click="deleteNetwork(net.id)">×</button>
                             </div>
                         </div>
                         <div style="display: flex; gap: 20px; margin-top: 10px;">
                             <div class="resource-block"><span class="ds-label">CIDR</span> <span class="mono">{{ net.cidr }}</span></div>
                             <div class="resource-block"><span class="ds-label">SERVERS</span> <span class="mono">{{ net.serverCount }}</span></div>
                         </div>
                     </section>
                     
                     <div v-if="!netStore.privateNetworks.length" class="ds-card" style="text-align: center; opacity: 0.5; padding: 30px;">
                        <p>No active private networks.</p>
                     </div>
                 </div>
                 
                 <!-- Info Panel -->
                 <div class="noc-stack">
                     <section class="ds-card">
                        <div class="ds-card-header"><h3 class="ds-label">VLAN_TOPOLOGY</h3></div>
                        <p style="font-size: 0.8rem; line-height: 1.6; color: var(--ds-text-ghost, #8b949e);">
                            Private Networks allow your servers to communicate securely with low latency. 
                            Servers in the same VLAN bypass public internet routing, improving cluster performance.
                            <br><br>
                            To assign a server, go to <strong>Server Details > Networking</strong>.
                        </p>
                     </section>
                 </div>
             </div>
        </div>

        <!-- ════════════════════════════════════════════════
             TAB: DARK FIBER LEASE
             ════════════════════════════════════════════════ -->
        <div class="noc-content" v-if="activeTab === 'fiber'">
            <div class="noc-wide">
                <section class="ds-card">
                    <div class="ds-card-header">
                        <h3 class="ds-label">GLOBAL_FIBER_BACKBONE_INVESTMENTS</h3>
                    </div>
                    
                    <div class="fiber-hero">
                        <div class="fiber-stats">
                            <div class="f-stat"><span class="ds-label">ACTIVE_LINK_SETS</span><span class="ds-value">{{ netStore.darkFiberOptions.filter(o => o.isLeased).length }}</span></div>
                            <div class="f-stat"><span class="ds-label">LATENCY_REDUCTION</span><span class="ds-value text-success">-40%</span></div>
                            <div class="f-stat"><span class="ds-label">PROVIDER_TRUST</span><span class="ds-value">PLATINUM</span></div>
                        </div>
                        <div class="fiber-visual" style="flex: 1; display: flex; justify-content: center; align-items: center; background: rgba(0,0,0,0.2); border-radius: 8px; min-height: 150px;">
                            <svg viewBox="0 0 500 200" style="width: 100%; max-width: 400px;">
                                <circle cx="50" cy="100" r="5" fill="#58a6ff" />
                                <circle cx="150" cy="50" r="5" fill="#58a6ff" />
                                <circle cx="150" cy="150" r="5" fill="#58a6ff" />
                                <circle cx="300" cy="100" r="5" fill="#58a6ff" />
                                <circle cx="450" cy="100" r="5" fill="#58a6ff" />
                                
                                <line x1="50" y1="100" x2="150" y2="50" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                                <line x1="150" y1="50" x2="300" y2="100" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                                <line x1="300" y1="100" x2="450" y2="100" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                                
                                <path v-for="opt in netStore.darkFiberOptions.filter(o => o.isLeased)" :key="opt.provider" 
                                      d="M 50 100 Q 250 10 450 100" fill="none" stroke="#a371f7" stroke-width="2" />
                            </svg>
                        </div>
                    </div>

                    <div class="fiber-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-top: 20px;">
                        <div v-for="opt in netStore.darkFiberOptions" :key="opt.provider" 
                             class="fiber-card" :style="{ border: opt.isLeased ? '1px solid var(--ds-accent-secondary)' : '1px solid rgba(255,255,255,0.1)' }"
                             style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px;">
                            <div class="fiber-regions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-weight: 800; color: #fff;">{{ opt.region_a.toUpperCase() }}</span>
                                <span style="opacity: 0.5;">⟷</span>
                                <span style="font-weight: 800; color: #fff;">{{ opt.region_b.toUpperCase() }}</span>
                            </div>
                            <div style="font-size: 0.6rem; color: var(--ds-text-ghost); margin-bottom: 12px;">{{ opt.provider }}</div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                <div style="display: flex; flex-direction: column;">
                                    <span class="ds-label">SETUP</span>
                                    <span class="mono">${{ opt.setup_fee.toLocaleString() }}</span>
                                </div>
                                <div style="display: flex; flex-direction: column; text-align: right;">
                                    <span class="ds-label">MONTHLY</span>
                                    <span class="mono">${{ opt.monthly_cost.toLocaleString() }}</span>
                                </div>
                            </div>
                            <button v-if="!opt.isLeased" class="noc-btn noc-btn-accent" style="width: 100%; justify-content: center;"
                                    @click="netStore.leaseDarkFiber(opt.region_a, opt.region_b)" :disabled="netStore.isLoading">
                                ESTABLISH_LEASE
                            </button>
                            <div v-else style="color: var(--ds-accent-secondary); font-weight: 800; font-size: 0.7rem; text-align: center; background: rgba(163,113,247,0.1); padding: 5px; border-radius: 4px;">ACTIVE_BACKBONE_LINK</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, watch, onUnmounted, reactive } from 'vue';
import { useNetworkStore } from '../../stores/network';
import { useGameStore } from '../../stores/game';
import KpiGauge from '../Feedback/KpiGauge.vue';
import StatusIndicator from '../Feedback/StatusIndicator.vue';
import { formatCompact } from '../../composables/useFormatters';

import { useNetworkMetrics } from '../../composables/useNetworkMetrics';

const netStore = useNetworkStore();
const gameStore = useGameStore();

const activeTab = ref('overview');

const tabs = computed(() => [
    { id: 'overview', label: 'OVERVIEW', icon: '◈' },
    { id: 'vpc', label: 'PRIVATE_NETWORKS', icon: '☁️' },
    { id: 'ipv4market', label: 'IPv4_MARKET', icon: '📦', badge: netStore.ips.ipv4.percent > 80 ? '!' : null, badgeType: 'warning' },
    { id: 'ddos', label: 'DDOS_SHIELD', icon: '🛡️', badge: netStore.infrastructure.ddosProtection === 0 ? '!' : null, badgeType: 'danger' },
    { id: 'fiber', label: 'DARK_FIBER_LEASE', icon: '⚡' },
]);

const { 
    latencyHistory, 
    packetLossHistory, 
    throughputHistory, 
    slaHistory,
    startTracking,
    stopTracking
} = useNetworkMetrics(30, 5000);

onMounted(() => {
    startTracking();
});

onUnmounted(() => {
    stopTracking();
});

// Load market data when switching to that tab
watch(activeTab, (tab) => {
    if (tab === 'ipv4market' && !netStore.market.loaded) {
        netStore.loadMarketData();
    }
    if (tab === 'vpc' && !netStore.networksLoaded) {
        netStore.loadPrivateNetworks();
    }
    if (tab === 'fiber' && !netStore.darkFiberLoaded) {
        netStore.loadDarkFiberOptions();
    }
});

// VPC Logic
const newNetName = ref('');
const newNetCidr = ref('10.0.0.0/24');

const createNetwork = async () => {
    if (!newNetName.value) return;
    const success = await netStore.createPrivateNetwork(newNetName.value, newNetCidr.value);
    if (success) newNetName.value = '';
};

const deleteNetwork = async (id) => {
    if (confirm('Delete this network and detach all servers?')) {
        await netStore.deletePrivateNetwork(id);
        if (selectedNetwork.value && selectedNetwork.value.id === id) {
            selectedNetwork.value = null;
        }
    }
};

const selectedNetwork = ref(null);
const newRule = reactive({
    type: 'ALLOW',
    protocol: 'TCP',
    port_range: '80',
    source_cidr: '0.0.0.0/0',
    priority: 100,
    description: ''
});

const addRule = async () => {
    if (!selectedNetwork.value) return;
    const success = await netStore.addFirewallRule(selectedNetwork.value.id, { ...newRule });
    if (success) {
        newRule.description = '';
        newRule.priority += 10;
    }
};

const deleteRule = async (ruleId) => {
    if (!selectedNetwork.value) return;
    if (confirm('Delete this firewall rule?')) {
        await netStore.deleteFirewallRule(selectedNetwork.value.id, ruleId);
    }
};

const testRuleData = reactive({
    protocol: 'TCP',
    port: 80,
    source_ip: '1.2.3.4'
});
const testResult = ref(null);

const testRule = async () => {
    if (!selectedNetwork.value) return;
    testResult.value = null;
    const res = await netStore.testFirewallRule(selectedNetwork.value.id, { ...testRuleData });
    testResult.value = res;
};

// ─── SLA Ring Style ───
const slaRingStyle = computed(() => {
    const p = netStore.metrics.slaCompliance;
    const color = p > 99 ? 'var(--ds-severity-nominal)' : (p > 95 ? 'var(--ds-severity-caution)' : 'var(--ds-severity-critical)');
    return {
        background: `conic-gradient(${color} ${p * 3.6}deg, rgba(255,255,255,0.04) 0deg)`,
    };
});
</script>

<style scoped>
/* ════════════════════════════════════════════
   NOC – Enterprise Dashboard Styling (v2)
   ════════════════════════════════════════════ */

.network-view {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    color: var(--ds-text-primary, #e6edf3);
    min-height: 100%;
}

/* ─── HEADER ─── */
.noc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.title-block h1 {
    font-size: 1.4rem;
    font-family: var(--ds-font-mono, monospace);
    font-weight: 800;
    letter-spacing: 0.12em;
    margin: 0;
    background: linear-gradient(135deg, var(--ds-accent-primary, #58a6ff), var(--ds-accent-secondary, #a371f7));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.noc-subtitle {
    font-size: 0.65rem;
    color: var(--ds-text-ghost, #484f58);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin: 4px 0 0;
}

.noc-status-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 800;
    font-family: var(--ds-font-mono, monospace);
    letter-spacing: 0.08em;
    backdrop-filter: blur(8px);
}

.noc-status-pill.severity-nominal { border-color: var(--ds-severity-nominal, #3fb950); color: var(--ds-severity-nominal); }
.noc-status-pill.severity-caution { border-color: var(--ds-severity-caution, #d29922); color: var(--ds-severity-caution); }
.noc-status-pill.severity-warning { border-color: var(--ds-severity-warning, #e3b341); color: var(--ds-severity-warning); }
.noc-status-pill.severity-critical { border-color: var(--ds-severity-critical, #f85149); color: var(--ds-severity-critical); animation: ds-pulse-caution 2s ease-in-out infinite; }

/* ─── KPI STRIP ─── */
.noc-kpi-strip {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}

/* ─── TABS ─── */
.noc-tabs {
    display: flex;
    gap: 3px;
    background: rgba(0,0,0,0.25);
    padding: 3px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.04);
}

.noc-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    background: transparent;
    border: 1px solid transparent;
    color: var(--ds-text-ghost, #484f58);
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.25s var(--ds-ease-out, ease);
}

.noc-tab:hover { color: var(--ds-text-primary); background: rgba(255,255,255,0.03); }
.noc-tab.is-active {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.1);
    color: var(--ds-text-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.tab-icon { font-size: 0.9rem; }

.tab-badge {
    font-size: 0.5rem;
    padding: 1px 6px;
    border-radius: 8px;
    font-weight: 900;
}
.tab-badge.badge-warning { background: rgba(210,153,34,0.2); color: var(--ds-severity-caution); }
.tab-badge.badge-danger { background: rgba(248,81,73,0.2); color: var(--ds-severity-critical); }

/* ─── LAYOUT ─── */
.noc-content { animation: ds-fade-in 0.3s ease; }
.noc-grid-2col { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
.noc-stack { display: flex; flex-direction: column; gap: 16px; }
.noc-wide { display: flex; flex-direction: column; gap: 16px; }

@keyframes ds-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

/* ─── DS CARDS ─── */
.ds-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: var(--ds-radius-md, 10px);
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: border-color 0.3s ease;
}

.ds-card:hover { border-color: rgba(255,255,255,0.1); }

.ds-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ds-label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--ds-text-ghost, #484f58);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.ds-value {
    font-size: 0.8rem;
    font-weight: 800;
}

.ds-value.mono, .mono { font-family: var(--ds-font-mono, monospace); }
.dim { opacity: 0.4; font-size: 0.85em; }

/* ─── PROGRESS BARS ─── */
.noc-progress {
    height: 5px;
    background: rgba(255,255,255,0.03);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
}

.noc-progress-fill {
    height: 100%;
    border-radius: 3px;
    background: var(--ds-accent-primary, #58a6ff);
    box-shadow: 0 0 8px rgba(88,166,255,0.3);
    transition: width 1.2s cubic-bezier(0.16,1,0.3,1);
}

.noc-progress.severity-nominal .noc-progress-fill { background: var(--ds-severity-nominal); box-shadow: 0 0 8px rgba(63,185,80,0.3); }
.noc-progress.severity-caution .noc-progress-fill { background: var(--ds-severity-caution); box-shadow: 0 0 8px rgba(210,153,34,0.3); }
.noc-progress.severity-warning .noc-progress-fill { background: var(--ds-severity-warning); box-shadow: 0 0 8px rgba(227,179,65,0.3); }
.noc-progress.severity-critical .noc-progress-fill { background: var(--ds-severity-critical); box-shadow: 0 0 8px rgba(248,81,73,0.3); animation: ds-pulse-caution 1.5s ease-in-out infinite; }
.noc-progress.ipv6 .noc-progress-fill { background: #8257e5; box-shadow: 0 0 8px rgba(130,87,229,0.3); }

.noc-peak-marker {
    position: absolute;
    top: 0; bottom: 0;
    width: 2px;
    background: var(--ds-severity-critical);
    opacity: 0.4;
}

.noc-divider { height: 1px; background: rgba(255,255,255,0.04); }

/* ─── RESOURCE BLOCKS ─── */
.resource-block { display: flex; flex-direction: column; gap: 6px; }
.resource-meta { display: flex; justify-content: space-between; align-items: baseline; }

/* ─── ISP INFO ─── */
.isp-badge {
    font-size: 0.5rem;
    font-weight: 900;
    padding: 3px 10px;
    border-radius: 10px;
    letter-spacing: 0.08em;
}
.isp-badge.tier-standard { background: rgba(88,166,255,0.12); color: #58a6ff; border: 1px solid rgba(88,166,255,0.25); }
.isp-badge.tier-premium { background: rgba(210,153,34,0.12); color: #d29922; border: 1px solid rgba(210,153,34,0.25); }
.isp-badge.tier-enterprise { background: rgba(163,113,247,0.12); color: #a371f7; border: 1px solid rgba(163,113,247,0.25); }

.isp-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.isp-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px;
    background: rgba(255,255,255,0.02);
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.04);
}

/* ─── SECURITY GRID ─── */
.security-grid-v2 { display: flex; flex-direction: column; gap: 12px; }

.sec-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 8px;
}

.sec-icon-wrap {
    width: 48px; height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    border-radius: 12px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}

.sec-info { display: flex; flex-direction: column; gap: 3px; }

.level-pips { display: flex; gap: 3px; margin-top: 2px; }
.pip { width: 18px; height: 3px; background: rgba(255,255,255,0.06); border-radius: 2px; transition: all 0.3s ease; }
.pip.active { background: var(--ds-accent-primary, #58a6ff); box-shadow: 0 0 6px rgba(88,166,255,0.3); }

.sec-tag { font-size: 0.5rem; font-weight: 900; letter-spacing: 0.1em; opacity: 0.6; }

/* ─── QOS RING ─── */
.qos-card { align-items: center; text-align: center; }

.qos-ring-wrap { display: flex; justify-content: center; }

.qos-ring {
    width: 130px; height: 130px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 1s ease;
}

.qos-ring::after {
    content: '';
    position: absolute;
    inset: 10px;
    background: var(--ds-base-900, #0d1117);
    border-radius: 50%;
    z-index: 1;
}

.qos-ring-inner {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
}

.qos-label { font-size: 0.5rem; font-weight: 900; color: var(--ds-text-ghost); letter-spacing: 0.1em; }
.qos-val { font-size: 1.3rem; font-weight: 800; }

.qos-metrics {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.qos-metric {
    display: flex;
    justify-content: space-between;
}

/* ─── BGP ─── */
.bgp-block { display: flex; flex-direction: column; gap: 14px; }
.bgp-asn { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 10px; }
.bgp-asn-val { font-size: 1rem; font-weight: 800; color: var(--ds-accent-primary, #58a6ff); }
.bgp-peering { display: flex; align-items: center; gap: 8px; }

/* ─── TRAFFIC ─── */
.traffic-grid { display: flex; flex-direction: column; gap: 10px; }
.traffic-stat { display: flex; justify-content: space-between; }
.traffic-in { color: var(--ds-severity-nominal, #3fb950); }
.traffic-out { color: var(--ds-accent-primary, #58a6ff); }

/* ─── BUTTONS ─── */
.noc-btn {
    padding: 8px 16px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--ds-text-primary);
    font-size: 0.55rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.noc-btn:hover:not(:disabled) { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }
.noc-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.noc-btn-success { background: rgba(46,160,67,0.12); border-color: rgba(46,160,67,0.3); color: #3fb950; }
.noc-btn-success:hover:not(:disabled) { background: rgba(46,160,67,0.25); }

.noc-btn-danger { background: rgba(248,81,73,0.08); border-color: rgba(248,81,73,0.25); color: #f85149; }
.noc-btn-danger:hover:not(:disabled) { background: rgba(248,81,73,0.2); }

.noc-btn-accent {
    background: linear-gradient(135deg, rgba(88,166,255,0.15), rgba(163,113,247,0.15));
    border: 1px solid rgba(88,166,255,0.3);
    color: #58a6ff;
}
.noc-btn-accent:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(88,166,255,0.3), rgba(163,113,247,0.3));
    box-shadow: 0 0 15px rgba(88,166,255,0.2);
}

/* ─── IPv4 MARKET (v2) ─── */
.market-trend-v2 { display: flex; align-items: center; gap: 8px; }
.trend-pill { font-size: 0.5rem; font-weight: 900; padding: 3px 10px; border-radius: 10px; letter-spacing: 0.08em; }
.trend-stable { background: rgba(88,166,255,0.12); color: #58a6ff; border: 1px solid rgba(88,166,255,0.25); }
.trend-rising { background: rgba(248,81,73,0.12); color: #f85149; border: 1px solid rgba(248,81,73,0.25); }

.pool-kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.pool-kpi { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; text-align: center; display: flex; flex-direction: column; gap: 4px; }

.block-grid-v2 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

.block-card-v2 {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 14px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    transition: all 0.25s ease;
}

.block-card-v2:hover:not(.is-locked) { border-color: var(--ds-accent-primary); transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.block-card-v2.is-locked { opacity: 0.35; filter: grayscale(0.5); }

.block-cidr { font-size: 1.15rem; font-weight: 800; color: var(--ds-accent-primary, #58a6ff); }
.block-count { font-size: 0.6rem; font-weight: 900; color: var(--ds-text-ghost); }
.block-count span { opacity: 0.5; }
.block-price { font-size: 0.85rem; font-weight: 800; color: var(--ds-severity-nominal); margin: 3px 0; }
.block-locked-label { font-size: 0.55rem; color: var(--ds-text-ghost); font-weight: 700; }
.block-actions { display: flex; gap: 6px; margin-top: 4px; }

.market-note-v2 { display: flex; align-items: flex-start; gap: 8px; font-size: 0.6rem; color: var(--ds-text-ghost); padding: 10px; background: rgba(255,255,255,0.015); border-radius: 6px; border: 1px dashed rgba(255,255,255,0.05); }

.market-loading { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 40px; color: var(--ds-text-ghost); }
.noc-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.07); border-top-color: var(--ds-accent-primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Pricing */
.pricing-rows { display: flex; flex-direction: column; gap: 10px; }
.price-row-v2 { display: flex; justify-content: space-between; align-items: baseline; }

/* Tips */
.tips-v2 { display: flex; flex-direction: column; gap: 10px; }
.tip-v2 { display: flex; align-items: flex-start; gap: 8px; font-size: 0.6rem; color: var(--ds-text-secondary, #8b949e); padding: 10px; background: rgba(255,255,255,0.02); border-radius: 6px; border: 1px solid rgba(255,255,255,0.04); }
.tip-v2.tip-urgent { border-color: rgba(210,153,34,0.25); background: rgba(210,153,34,0.04); color: var(--ds-severity-caution); }

/* ─── DDoS (v2) ─── */
.ddos-hero { display: flex; align-items: center; gap: 20px; padding: 18px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; }

.ddos-shield-large {
    width: 76px; height: 76px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
    border: 2px solid rgba(255,255,255,0.08);
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.ddos-shield-large.shield-level-1 { border-color: rgba(88,166,255,0.4); box-shadow: 0 0 18px rgba(88,166,255,0.1); }
.ddos-shield-large.shield-level-2 { border-color: rgba(210,153,34,0.4); box-shadow: 0 0 18px rgba(210,153,34,0.1); }
.ddos-shield-large.shield-level-3 { border-color: rgba(163,113,247,0.45); box-shadow: 0 0 25px rgba(163,113,247,0.15); }

.shield-emoji { font-size: 1.4rem; }
.shield-lvl { font-size: 0.55rem; font-weight: 900; color: var(--ds-text-ghost); margin-top: 2px; }

.ddos-hero-info { flex: 1; }
.ddos-hero-name { font-size: 0.9rem; font-weight: 800; margin-bottom: 5px; }
.ddos-hero-desc { font-size: 0.65rem; color: var(--ds-text-ghost); line-height: 1.6; }

.ddos-tiers-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }

.ddos-tier-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 10px;
    padding: 14px;
    display: flex; flex-direction: column; gap: 6px;
    transition: all 0.25s ease;
}

.ddos-tier-card.is-current { border-color: var(--ds-accent-primary); box-shadow: 0 0 12px rgba(88,166,255,0.08); }
.ddos-tier-card.is-unlocked { opacity: 0.45; }
.ddos-tier-card.is-next { border-color: rgba(255,255,255,0.12); }

.tier-head { display: flex; justify-content: space-between; align-items: center; }
.tier-title { font-size: 0.72rem; font-weight: 800; }
.tier-mit { display: flex; justify-content: space-between; margin-top: 3px; }
.tier-price { font-size: 0.85rem; font-weight: 800; color: var(--ds-severity-caution); text-align: center; margin-top: 3px; }

.ddos-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.ddos-stat-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); padding: 14px; border-radius: 8px; text-align: center; display: flex; flex-direction: column; gap: 5px; }

/* ─── SEVERITY TEXT UTILITIES ─── */
.severity-nominal { color: var(--ds-severity-nominal, #3fb950) !important; }
.severity-caution { color: var(--ds-severity-caution, #d29922) !important; }
.severity-warning { color: var(--ds-severity-warning, #e3b341) !important; }
.severity-critical { color: var(--ds-severity-critical, #f85149) !important; }

.res-allowed { 
    background: rgba(63, 185, 80, 0.1); 
    color: #3fb950; 
    border-color: rgba(63, 185, 80, 0.3);
    box-shadow: 0 0 10px rgba(63, 185, 80, 0.1);
}
.res-denied { 
    background: rgba(248, 81, 73, 0.1); 
    color: #f85149; 
    border-color: rgba(248, 81, 73, 0.3);
    box-shadow: 0 0 10px rgba(248, 81, 73, 0.1);
}
/* ─── DARK FIBER (v2) ─── */
.fiber-hero {
    display: flex; gap: 30px; padding: 20px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 25px; align-items: center;
}
.fiber-stats { display: flex; flex-direction: column; gap: 15px; min-width: 180px; }
.f-stat { display: flex; flex-direction: column; gap: 4px; }
.f-stat .ds-value { font-size: 1.1rem; font-weight: 800; font-family: var(--ds-font-mono, monospace); }

.fiber-visual { position: relative; }
.fiber-map { filter: drop-shadow(0 0 10px rgba(88, 166, 255, 0.2)); }
.fiber-active-line {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: drawFiber 2s forwards ease-in-out;
}
@keyframes drawFiber { to { stroke-dashoffset: 0; } }

.fiber-card {
    transition: all 0.3s ease;
}
.fiber-card:hover { transform: translateY(-3px); background: rgba(255,255,255,0.04) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
.is-active { background: rgba(163,113,247,0.05) !important; border-color: rgba(163,113,247,0.4) !important; }

.fiber-regions { font-family: var(--ds-font-mono, monospace); letter-spacing: 1px; }
.arrow { color: var(--ds-accent-primary); font-weight: 400; }
</style>
