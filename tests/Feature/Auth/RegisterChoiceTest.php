<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegisterChoiceTest extends TestCase
{
    public function test_shows_a_link_to_each_registration_form(): void
    {
        $response = $this->get('/registrar');

        $response->assertOk();
        $response->assertSee('Sou Cliente');
        $response->assertSee('Sou Vendedor');
        $response->assertSee(route('register.client'), false);
        $response->assertSee(route('register.seller'), false);
    }
}
