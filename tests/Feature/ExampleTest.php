<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->asAdmin()->get('/')->assertOk();
    }

    public function test_the_api_returns_a_successful_response(): void
    {
        $this->asAdmin()->getJson('/api')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);
    }
}
