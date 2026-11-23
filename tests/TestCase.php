<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function asAdmin(): static
    {
        return $this->withBasicAuth('admin', 'admin01');
    }
}
