<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_the_default_when_the_key_does_not_exist(): void
    {
        $this->assertNull(Setting::get('contact_email'));
        $this->assertSame('fallback', Setting::get('contact_email', 'fallback'));
    }

    public function test_set_creates_a_new_row_and_get_returns_it(): void
    {
        Setting::set('contact_email', 'contato@example.com');

        $this->assertSame('contato@example.com', Setting::get('contact_email'));
    }

    public function test_set_updates_an_existing_row_instead_of_duplicating_it(): void
    {
        Setting::set('contact_email', 'first@example.com');
        Setting::set('contact_email', 'second@example.com');

        $this->assertSame('second@example.com', Setting::get('contact_email'));
        $this->assertSame(1, Setting::query()->where('key', 'contact_email')->count());
    }

    public function test_get_returns_null_instead_of_the_default_when_the_row_exists_but_was_cleared(): void
    {
        Setting::set('contact_email', 'contato@example.com');
        Setting::set('contact_email', null);

        $this->assertNull(Setting::get('contact_email', 'fallback'));
    }
}
