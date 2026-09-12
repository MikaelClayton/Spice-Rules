<?php

namespace App\Enums;

enum PubGolfDrinkCategory: string
{
    case Beer = 'beer';
    case Cider = 'cider';
    case Rtd = 'rtd';
    case Spirit = 'spirit';
    case Wine = 'wine';
    case Shooter = 'shooter';

    public function label(): string
    {
        return match ($this) {
            self::Beer => 'Beer',
            self::Cider => 'Cider',
            self::Rtd => 'RTDs',
            self::Spirit => 'Spirits',
            self::Wine => 'Wine',
            self::Shooter => 'Shooters',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Beer => '🍺',
            self::Cider => '🍏',
            self::Rtd => '🧃',
            self::Spirit => '🥃',
            self::Wine => '🍷',
            self::Shooter => '💥',
        };
    }
}
