<?php

namespace App\Enums;

enum EventType: string
{
    case POWER_OUTAGE = 'power_outage';
    case OVERHEATING = 'overheating';
    case DDOS_ATTACK = 'ddos_attack';
    case NETWORK_FAILURE = 'network_failure';
    case HARDWARE_FAILURE = 'hardware_failure';
    case SECURITY_BREACH = 'security_breach';
    case BGP_HIJACKING = 'bgp_hijacking';
    case DATA_LEAK = 'data_leak';
    case ZERO_DAY_EXPLOIT = 'zero_day_exploit';
    case STORAGE_FAILURE = 'storage_failure';
    case PATENT_LAWSUIT = 'patent_lawsuit';
    case HIRING_RAID = 'hiring_raid';
    case PRICE_WAR = 'price_war';
    case ISP_BANNING = 'isp_banning';
    case FIBER_CUT = 'fiber_cut';


    public function label(): string
    {
        return match($this) {
            self::POWER_OUTAGE => 'Power Outage',
            self::OVERHEATING => 'Overheating',
            self::DDOS_ATTACK => 'DDoS Attack',
            self::NETWORK_FAILURE => 'Network Failure',
            self::HARDWARE_FAILURE => 'Hardware Failure',
            self::SECURITY_BREACH => 'Security Breach',
            self::BGP_HIJACKING => 'BGP Hijacking',
            self::DATA_LEAK => 'Data Leak',
            self::ZERO_DAY_EXPLOIT => 'Zero-Day Exploit',
            self::STORAGE_FAILURE => 'Storage Corruption',
            self::PATENT_LAWSUIT => 'Patent Infringement Lawsuit',
            self::HIRING_RAID => 'Headhunting Raid',
            self::PRICE_WAR => 'Price War',
            self::ISP_BANNING => 'ISP Network Blacklist',
            self::FIBER_CUT => 'Backhaul Fiber Cut',

        };
    }

    public function description(): string
    {
        return match($this) {
            self::POWER_OUTAGE => 'Main power supply has failed. Emergency generators are running.',
            self::OVERHEATING => 'Cooling system is struggling. Server temperatures are rising.',
            self::DDOS_ATTACK => 'Massive traffic spike detected. Network is being overwhelmed.',
            self::NETWORK_FAILURE => 'Upstream connectivity lost. Customers cannot reach their services.',
            self::HARDWARE_FAILURE => 'Critical hardware component has failed.',
            self::SECURITY_BREACH => 'Unauthorized access attempt detected.',
            self::BGP_HIJACKING => 'Your network prefixes are being announced by an unauthorized ASN.',
            self::DATA_LEAK => 'Sensitive customer data has been exposed online!',
            self::ZERO_DAY_EXPLOIT => 'A critical vulnerability has been discovered in your OS stack.',
            self::STORAGE_FAILURE => 'Storage array corruption detected. Data loss imminent without backups.',
            self::PATENT_LAWSUIT => 'A competitor claims your latest hardware setup infringes on their proprietary patents.',
            self::HIRING_RAID => 'A competitor is aggressively headhunting your top engineers with massive bonuses.',
            self::PRICE_WAR => 'A competitor has slashed prices to unsustainable levels to capture your market share.',
            self::ISP_BANNING => 'Your IP ranges have been blacklisted by major transit providers due to suspicious activity.',
            self::FIBER_CUT => 'A backhoe has severed a major fiber optic trunk line. Regional connectivity is degraded.',

        };
    }

    public function color(): string
    {
        return match($this) {
            self::POWER_OUTAGE => '#f97316',
            self::OVERHEATING => '#ef4444',
            self::DDOS_ATTACK => '#8b5cf6',
            self::NETWORK_FAILURE => '#3b82f6',
            self::HARDWARE_FAILURE => '#f59e0b',
            self::SECURITY_BREACH => '#ec4899',
            self::BGP_HIJACKING => '#10b981',
            self::DATA_LEAK => '#e11d48',
            self::ZERO_DAY_EXPLOIT => '#be123c',
            self::STORAGE_FAILURE => '#7f1d1d',
            self::PATENT_LAWSUIT => '#fbbf24', // Amber
            self::HIRING_RAID => '#10b981',    // Success green/HR
            self::PRICE_WAR => '#facc15',      // Yellow/Warning
            self::ISP_BANNING => '#4b5563',    // Gray/Dead
            self::FIBER_CUT => '#06b6d4',      // Cyan/Network

        };
    }

    public function icon(): string
    {
        return match($this) {
            self::POWER_OUTAGE => 'zap-off',
            self::OVERHEATING => 'thermometer',
            self::DDOS_ATTACK => 'shield-alert',
            self::NETWORK_FAILURE => 'wifi-off',
            self::HARDWARE_FAILURE => 'hard-drive',
            self::SECURITY_BREACH => 'lock',
            self::BGP_HIJACKING => 'globe',
            self::DATA_LEAK => 'eye-off',
            self::ZERO_DAY_EXPLOIT => 'alert-triangle',
            self::STORAGE_FAILURE => 'database',
            self::PATENT_LAWSUIT => 'briefcase',
            self::HIRING_RAID => 'users',
            self::PRICE_WAR => 'trending-down',
            self::ISP_BANNING => 'slash',
            self::FIBER_CUT => 'scissors',

        };
    }

    public function warningSeconds(): int
    {
        return match($this) {
            self::POWER_OUTAGE => 30,
            self::OVERHEATING => 120,
            self::DDOS_ATTACK => 60,
            self::NETWORK_FAILURE => 15,
            self::HARDWARE_FAILURE => 300,
            self::SECURITY_BREACH => 45,
            self::BGP_HIJACKING => 20,
            self::DATA_LEAK => 240,
            self::ZERO_DAY_EXPLOIT => 60,
            self::STORAGE_FAILURE => 0,
            self::PATENT_LAWSUIT => 300,
            self::HIRING_RAID => 60,
            self::PRICE_WAR => 30,
            self::ISP_BANNING => 45,
            self::FIBER_CUT => 20,

        };
    }

    public function escalationSeconds(): int
    {
        return match($this) {
            self::POWER_OUTAGE => 120,
            self::OVERHEATING => 300,
            self::DDOS_ATTACK => 180,
            self::NETWORK_FAILURE => 60,
            self::HARDWARE_FAILURE => 600,
            self::SECURITY_BREACH => 180,
            self::BGP_HIJACKING => 90,
            self::DATA_LEAK => 480,
            self::ZERO_DAY_EXPLOIT => 300,
            self::STORAGE_FAILURE => 600,
            self::PATENT_LAWSUIT => 900,
            self::HIRING_RAID => 1200,
            self::PRICE_WAR => 900,
            self::ISP_BANNING => 300,
            self::FIBER_CUT => 120,

        };
    }

    public function deadlineSeconds(): int
    {
        return match($this) {
            self::POWER_OUTAGE => 300,
            self::OVERHEATING => 600,
            self::DDOS_ATTACK => 600,
            self::NETWORK_FAILURE => 180,
            self::HARDWARE_FAILURE => 1800,
            self::SECURITY_BREACH => 300,
            self::BGP_HIJACKING => 240,
            self::DATA_LEAK => 1200,
            self::ZERO_DAY_EXPLOIT => 600,
            self::STORAGE_FAILURE => 3600,
            self::HIRING_RAID => 2400,
            self::PRICE_WAR => 1800,
            self::ISP_BANNING => 900,
            self::FIBER_CUT => 600,

        };
    }
}
