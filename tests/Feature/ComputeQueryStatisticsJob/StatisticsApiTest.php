<?php

namespace Tests\Feature\Statistics;

use App\Models\QueryStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $apiVersion = env('API_VERSION', 'v1');
        $this->baseUrl = "/api/{$apiVersion}/stats";
    }

    public function test_it_returns_the_latest_statistics_successfully()
    {
        QueryStatistic::create([
            'total_queries' => 5,
            'total_cached_queries' => 1,
            'average_duration_ms' => 150.00,
            'most_popular_hour' => 14,
            'top_five_queries' => [['query' => 'luke', 'count' => 5]],
            'calculated_at' => now()->subDay(),
        ]);

        QueryStatistic::create([
            'total_queries' => 20,
            'total_cached_queries' => 10,
            'average_duration_ms' => 120.50,
            'most_popular_hour' => 10,
            'top_five_queries' => [['query' => 'vader', 'count' => 20]],
            'calculated_at' => now(),
        ]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_queries' => 20,
                    'most_popular_hour' => 10,
                ],
            ])
            ->assertJsonPath('data.top_five_queries.0.query', 'vader');
    }

    public function test_it_returns_a_graceful_response_when_no_statistics_exist()
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Statistics not yet available',
                'calculated_at' => null,
            ]);
    }

    public function test_the_api_returns_the_correct_json_data_types()
    {
        QueryStatistic::create([
            'total_queries' => 5,
            'total_cached_queries' => 1,
            'average_duration_ms' => 150.35,
            'most_popular_hour' => 14,
            'top_five_queries' => [['query' => 'luke', 'count' => 5]],
            'calculated_at' => now()->subDay(),
        ]);

        $response = $this->getJson($this->baseUrl);

        $this->assertIsInt($response->json('data.total_queries'));
        $this->assertIsInt($response->json('data.total_cached_queries'));
        $this->assertIsFloat($response->json('data.average_duration_ms'));
        $this->assertIsInt($response->json('data.most_popular_hour'));
        $this->assertIsArray($response->json('data.top_five_queries'));
        $response->assertJsonStructure(['data' => ['calculated_at']])
            ->assertJsonPath('data.calculated_at', fn ($value) => (bool) strtotime($value)
            );
    }
}
