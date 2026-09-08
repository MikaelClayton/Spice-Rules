<?php

namespace App\Enums;

enum WicketGroupRole: string
{
    case Member = 'member';
    case FinesMaster = 'fines_master';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Player',
            self::FinesMaster => 'Fines Master',
        };
    }

    public function isFinesMaster(): bool
    {
        return $this === self::FinesMaster;
    }
}
