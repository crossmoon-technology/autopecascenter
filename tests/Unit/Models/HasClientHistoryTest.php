<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_true_for_a_seller_that_used_to_be_a_client(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::Seller]);
        $seller = User::factory()->approvedSeller()->create();
        $seller->linkToSeller($otherSeller);

        $this->assertTrue($seller->hasClientHistory());
    }

    public function test_false_for_a_seller_that_was_never_a_client(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertFalse($seller->hasClientHistory());
    }

    public function test_true_for_a_plain_client_account(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();

        $this->assertTrue($client->hasClientHistory());
    }
}
