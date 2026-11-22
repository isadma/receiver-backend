<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->getJson('/');

        $response->assertOk()->assertJson([
            'status' => 'ok',
        ]);
    }

    public function test_the_api_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api');

        $response->assertOk()->assertJson([
            'status' => 'ok',
        ]);
    }
}
