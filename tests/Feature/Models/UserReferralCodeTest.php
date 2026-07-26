<?php

namespace Tests\Feature\Models;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserReferralCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    public function test_every_seller_gets_a_referral_code_automatically(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);

        $this->assertNotNull($seller->referral_code);
    }

    public function test_non_seller_accounts_do_not_get_a_referral_code(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertNull($client->referral_code);
        $this->assertNull($superAdmin->referral_code);
    }

    public function test_referral_codes_are_unique_across_many_sellers(): void
    {
        $codes = User::factory()->count(50)->create(['role' => Role::Seller])->pluck('referral_code');

        $this->assertCount(50, $codes->unique());
    }

    /**
     * Não dá pra provar unicidade só testando o resultado (uma colisão aleatória é
     * praticamente impossível de forçar em teste) — em vez disso, força a "sorte" via
     * Str::createRandomStringsUsingSequence() pra garantir que o retry em
     * User::generateUniqueReferralCode() realmente descarta um código já em uso.
     */
    public function test_generate_unique_referral_code_retries_on_a_collision(): void
    {
        $taken = User::factory()->create(['role' => Role::Seller])->referral_code;

        Str::createRandomStringsUsingSequence([
            strtolower($taken),
            'freshcode',
        ]);

        $code = User::generateUniqueReferralCode();

        $this->assertSame('FRESHCODE', $code);
        $this->assertNotSame($taken, $code);
    }
}
