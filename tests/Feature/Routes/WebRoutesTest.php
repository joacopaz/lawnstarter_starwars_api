<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    public function test_it_renders_the_landing_component_at_the_root()
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('landing')
            );
    }

    public function test_it_renders_the_item_details_component_for_valid_resources()
    {
        $result = ['result' => ['properties' => ['name' => 'Luke Skywalker', 'films' => []]]];
        Http::fake([
            'https://swapi.tech/api/*' => Http::response($result),
        ]);
        $response = $this->get('/people/1');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resource')
            ->has('metadata', fn (Assert $prop) => $prop
                ->where('properties', $result['result']['properties'])
            )
        );
    }

    public function test_it_renders_the_error_component_on_fallback()
    {
        $response = $this->get('/not-a-valid-resource/999');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->has('message')
            ->has('status')
        );
    }
}
