/**
 * useFormatters — Data formatting utilities for enterprise dashboard.
 *
 * Provides consistent formatting across all display values.
 */

/**
 * Format bytes to human-readable string.
 * @param {number} bytes
 * @param {number} decimals
 * @returns {string}
 */
export function formatBytes(bytes, decimals = 1) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
}

/**
 * Format bandwidth (Mbps/Gbps/Tbps).
 * @param {number} mbps - Value in Mbps
 * @returns {string}
 */
export function formatBandwidth(mbps) {
    if (mbps >= 1000000) return (mbps / 1000000).toFixed(1) + ' Tbps';
    if (mbps >= 1000) return (mbps / 1000).toFixed(1) + ' Gbps';
    return mbps.toFixed(0) + ' Mbps';
}

/**
 * Format currency (dollars).
 * @param {number} amount
 * @param {boolean} compact - Use compact notation for large numbers
 * @returns {string}
 */
export function formatCurrency(amount, compact = false) {
    if (compact && Math.abs(amount) >= 1000000) {
        return '$' + (amount / 1000000).toFixed(1) + 'M';
    }
    if (compact && Math.abs(amount) >= 1000) {
        return '$' + (amount / 1000).toFixed(1) + 'K';
    }
    return '$' + amount.toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

/**
 * Format latency with appropriate unit.
 * @param {number} ms - Latency in milliseconds
 * @returns {string}
 */
export function formatLatency(ms) {
    if (ms < 1) return (ms * 1000).toFixed(0) + 'µs';
    if (ms < 1000) return ms.toFixed(1) + 'ms';
    return (ms / 1000).toFixed(2) + 's';
}

/**
 * Format packet loss as percentage.
 * @param {number} ratio - Packet loss as decimal (0.001 = 0.1%)
 * @returns {string}
 */
export function formatPacketLoss(ratio) {
    if (ratio === 0) return '0%';
    if (ratio < 0.001) return '<0.1%';
    return (ratio * 100).toFixed(2) + '%';
}

/**
 * Format percentage.
 * @param {number} value
 * @param {number} decimals
 * @returns {string}
 */
export function formatPercent(value, decimals = 1) {
    return value.toFixed(decimals) + '%';
}

/**
 * Format power (kW/MW).
 * @param {number} kw
 * @returns {string}
 */
export function formatPower(kw) {
    if (kw >= 1000) return (kw / 1000).toFixed(1) + ' MW';
    return kw.toFixed(kw < 10 ? 2 : 1) + ' kW';
}

/**
 * Format temperature.
 * @param {number} celsius
 * @returns {string}
 */
export function formatTemp(celsius) {
    return celsius.toFixed(1) + '°C';
}

/**
 * Format relative time (e.g., "2m ago", "3h 12m").
 * @param {number|Date|string} timestamp
 * @returns {string}
 */
export function formatRelativeTime(timestamp) {
    const now = Date.now();
    const ts = timestamp instanceof Date ? timestamp.getTime() :
        typeof timestamp === 'string' ? new Date(timestamp).getTime() :
            timestamp;
    const diff = now - ts;

    if (diff < 60000) return 'just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) {
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        return m > 0 ? `${h}h ${m}m ago` : `${h}h ago`;
    }
    return Math.floor(diff / 86400000) + 'd ago';
}

/**
 * Format IP count with appropriate suffix.
 * @param {number} count
 * @returns {string}
 */
export function formatIpCount(count) {
    if (count >= 65536) return (count / 65536).toFixed(0) + ' /16 blocks';
    if (count >= 256) return (count / 256).toFixed(0) + ' /24 blocks';
    return count + ' IPs';
}

/**
 * Compact large numbers with K/M/B suffix.
 * @param {number} value
 * @returns {string}
 */
export function formatCompact(value) {
    if (Math.abs(value) >= 1e9) return (value / 1e9).toFixed(1) + 'B';
    if (Math.abs(value) >= 1e6) return (value / 1e6).toFixed(1) + 'M';
    if (Math.abs(value) >= 1e3) return (value / 1e3).toFixed(1) + 'K';
    return value.toString();
}
