<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the admin SPA entry point.
     */
    public function test_the_application_redirects_root_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
