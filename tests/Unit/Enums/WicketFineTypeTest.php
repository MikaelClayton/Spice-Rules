<?php

namespace Tests\Unit\Enums;

use App\Enums\WicketFineType;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class WicketFineTypeTest extends TestCase
{
    public function test_sips_are_sip_fines(): void
    {
        $this->assertTrue(WicketFineType::Sips->isSip());
        $this->assertSame('Sips', WicketFineType::Sips->label());
        $this->assertSame(7, WicketFineType::MAX_SIP_FINE);
        $this->assertSame(8, WicketFineType::DOWN_DOWN_AT);
    }

    #[TestWith([WicketFineType::DownDown, 'Down down'])]
    #[TestWith([WicketFineType::Funnel, 'Funnel'])]
    #[TestWith([WicketFineType::Shoey, 'Shoey'])]
    public function test_special_fines_are_not_sip_fines(WicketFineType $type, string $label): void
    {
        $this->assertFalse($type->isSip());
        $this->assertSame($label, $type->label());
    }
}
