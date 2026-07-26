<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_hides_the_whole_contato_column_when_no_contact_setting_is_filled(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('footer__contact', false);
    }

    public function test_shows_only_the_contact_fields_that_are_filled(): void
    {
        Setting::set('contact_phone', '(11) 91234-5678');
        Setting::set('contact_email', '');
        Setting::set('contact_address', null);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('(11) 91234-5678');
        $response->assertDontSee('contato@autopecascenter.com.br');
    }

    public function test_hides_the_social_icons_container_when_none_are_filled(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('footer__socials', false);
    }

    public function test_shows_only_the_social_links_that_are_filled(): void
    {
        Setting::set('social_facebook', 'https://facebook.com/autopecascenter');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://facebook.com/autopecascenter', false);
    }

    public function test_shows_a_link_to_the_faq_page_under_para_seu_negocio(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('perguntas-frequentes'), false);
        $response->assertSee('Perguntas frequentes');
    }
}
