<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserLogoCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_keeps_the_logo_on_disk(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['logo' => 'users/logos/logo.png']);
        Storage::disk('public')->put($user->logo, 'conteudo');

        $user->delete();

        Storage::disk('public')->assertExists($user->logo);
    }

    public function test_force_deleting_removes_the_logo_from_disk(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['logo' => 'users/logos/logo.png']);
        Storage::disk('public')->put($user->logo, 'conteudo');

        $user->forceDelete();

        Storage::disk('public')->assertMissing('users/logos/logo.png');
    }

    public function test_force_deleting_a_user_without_a_logo_does_not_error(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['logo' => null]);

        $user->forceDelete();

        $this->assertModelMissing($user);
    }
}
