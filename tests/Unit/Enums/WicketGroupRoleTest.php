<?php

namespace Tests\Unit\Enums;

use App\Enums\WicketGroupRole;
use PHPUnit\Framework\TestCase;

class WicketGroupRoleTest extends TestCase
{
    public function test_fines_master_is_the_named_role(): void
    {
        $this->assertSame('fines_master', WicketGroupRole::FinesMaster->value);
        $this->assertSame('Fines Master', WicketGroupRole::FinesMaster->label());
        $this->assertTrue(WicketGroupRole::FinesMaster->isFinesMaster());
        $this->assertFalse(WicketGroupRole::Member->isFinesMaster());
        $this->assertSame('Player', WicketGroupRole::Member->label());
    }
}
