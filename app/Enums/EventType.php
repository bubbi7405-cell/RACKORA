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

    public function label(): string
    {
        return match($this) {
            self::POWER_OUTAGE => 'Power Outage',
            self::OVERHEATING => 'Overheating',
            self::DDOS_ATTACK => 'DDoS Attack',
            self::NETWORK_FAILURE => 'Network Failure',
            self::HARDWARE_FAILURE => 'Hardware Failure',
            self::SECURITY_BREACH => 'Security Breach',
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
        };
    }
}
