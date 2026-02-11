<?php

namespace App\Enums;

enum ServerType: string
{
    case VSERVER_NODE = 'vserver_node';
    case DEDICATED = 'dedicated';
    case GPU_SERVER = 'gpu_server';
    case STORAGE_SERVER = 'storage_server';

    public function label(): string
    {
        return match($this) {
            self::VSERVER_NODE => 'VServer Node',
            self::DEDICATED => 'Dedicated Server',
            self::GPU_SERVER => 'GPU Server',
            self::STORAGE_SERVER => 'Storage Server',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::VSERVER_NODE => 'High-density virtualization node for multiple VPS instances',
            self::DEDICATED => 'Single-tenant bare metal server for dedicated hosting',
            self::GPU_SERVER => 'High-performance compute with GPU acceleration',
            self::STORAGE_SERVER => 'High-capacity storage for backup and object storage',
        };
    }

    public function requiredLevel(): int
    {
        return match($this) {
            self::VSERVER_NODE => 1,
            self::DEDICATED => 2,
            self::STORAGE_SERVER => 5,
            self::GPU_SERVER => 15,
        };
    }
}
