<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_url_redirects_to_login_when_a_guest(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
