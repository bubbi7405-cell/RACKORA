<?php

namespace App\Enums;

enum ServerType: string
{
    case VSERVER_NODE = 'vserver_node';
    case SHARED_NODE = 'shared_node';
    case DEDICATED = 'dedicated';
    case GPU_SERVER = 'gpu_server';
    case STORAGE_SERVER = 'storage_server';
    case EXPERIMENTAL = 'experimental';
    case CUSTOM = 'custom';
    case BATTERY = 'battery';

    public function label(): string
    {
        return match($this) {
            self::VSERVER_NODE => 'VServer Node',
            self::SHARED_NODE => 'Shared Hosting Node',
            self::DEDICATED => 'Dedicated Server',
            self::GPU_SERVER => 'GPU Server',
            self::STORAGE_SERVER => 'Storage Server',
            self::EXPERIMENTAL => 'Experimental Prototype',
            self::CUSTOM => 'Modular Server',
            self::BATTERY => 'UPS Battery Module',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::VSERVER_NODE => 'High-density virtualization node for multiple VPS instances',
            self::SHARED_NODE => 'Overprovisioned node for hundreds of small Web & DB hosting contracts',
            self::DEDICATED => 'Single-tenant bare metal server for dedicated hosting',
            self::GPU_SERVER => 'High-performance compute with GPU acceleration',
            self::STORAGE_SERVER => 'High-capacity storage for backup and object storage',
            self::EXPERIMENTAL => 'Cutting-edge hardware prototypes with high risk and reward',
            self::CUSTOM => 'User-assembled server with custom components',
            self::BATTERY => 'High-capacity battery module for grid buffering and backup',
        };
    }

    public function requiredLevel(): int
    {
        return match($this) {
            self::SHARED_NODE => 1,
            self::VSERVER_NODE => 1,
            self::DEDICATED => 2,
            self::STORAGE_SERVER => 5,
            self::GPU_SERVER => 15,
            self::EXPERIMENTAL => 20,
            self::CUSTOM => 1,
            self::BATTERY => 10,
        };
    }
}
