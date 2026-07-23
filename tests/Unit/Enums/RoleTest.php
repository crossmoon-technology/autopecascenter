<?php

namespace Tests\Unit\Enums;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_panel_id_matches_each_panels_registered_id(): void
    {
        $this->assertSame('super-admin', Role::SuperAdmin->panelId());
        $this->assertSame('admin', Role::Admin->panelId());
        $this->assertSame('client', Role::Client->panelId());
    }
}
