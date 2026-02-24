<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_basic_test()
    {
        // follow redirects (app may redirect to login) and assert final response OK
        $response = $this->followingRedirects()->get('/');

        $response->assertStatus(200);
    }
}
