<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz lleva al panel administrativo.
     */
    public function test_the_application_redirects_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
