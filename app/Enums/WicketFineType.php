<?php

namespace App\Enums;

enum WicketFineType: string
{
    public const DOWN_DOWN_AT = 8;

    public const MAX_SIP_FINE = 7;

    case Sips = 'sips';
    case DownDown = 'down_down';
    case Funnel = 'funnel';
    case Shoey = 'shoey';

    public function isSip(): bool
    {
        return $this === self::Sips;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sips => 'Sips',
            self::DownDown => 'Down down',
            self::Funnel => 'Funnel',
            self::Shoey => 'Shoey',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Sips => '🍺',
            self::DownDown => '🍻',
            self::Funnel => '🫗',
            self::Shoey => '👟',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sips => 'Sips of beer still owed. Eight sips become a down down.',
            self::DownDown => 'Finish your drink in one go.',
            self::Funnel => 'Drink through a funnel.',
            self::Shoey => 'Drink from a shoe.',
        };
    }

    /**
     * @return list<self>
     */
    public static function specials(): array
    {
        return [
            self::DownDown,
            self::Funnel,
            self::Shoey,
        ];
    }
}
