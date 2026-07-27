<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_page_renders_successfully(): void
    {
        $response = $this->get(route('privacy-policy'));

        $response->assertOk();
        $response->assertSee('Política de Privacidade');
        $response->assertSee('Seus direitos como titular de dados (LGPD)');
    }

    public function test_cookie_policy_page_renders_successfully(): void
    {
        $response = $this->get(route('cookie-policy'));

        $response->assertOk();
        $response->assertSee('Política de Cookies');
        $response->assertSee('O que são cookies');
    }

    public function test_terms_of_use_page_renders_successfully(): void
    {
        $response = $this->get(route('terms-of-use'));

        $response->assertOk();
        $response->assertSee('Termos de Uso');
        $response->assertSee('Aceitação dos termos');
    }

    public function test_legal_pages_cross_link_each_other(): void
    {
        $this->get(route('privacy-policy'))
            ->assertSee(route('cookie-policy'), false)
            ->assertSee(route('terms-of-use'), false);

        $this->get(route('cookie-policy'))
            ->assertSee(route('privacy-policy'), false);

        $this->get(route('terms-of-use'))
            ->assertSee(route('privacy-policy'), false)
            ->assertSee(route('planos'), false);
    }

    public function test_home_page_shows_the_cookie_consent_banner(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-cookie-consent', false);
        $response->assertSee('data-cookie-consent-accept', false);
        $response->assertSee(route('cookie-policy'), false);
    }
}
