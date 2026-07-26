<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_successfully(): void
    {
        $response = $this->get(route('perguntas-frequentes'));

        $response->assertOk();
        $response->assertSee('Perguntas frequentes');
        $response->assertSee('Como funciona a avaliação gratuita?');
    }

    public function test_links_to_the_planos_and_contato_pages(): void
    {
        $response = $this->get(route('perguntas-frequentes'));

        $response->assertOk();
        $response->assertSee(route('planos'), false);
        $response->assertSee(route('contato'), false);
    }
}
