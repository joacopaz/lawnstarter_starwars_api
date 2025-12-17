<?php

namespace Tests\Feature;

use App\Jobs\ComputeQueryStatistics;
use App\Models\Query;
use App\Models\QueryStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComputeQueryStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_correctly_aggregates_star_wars_queries()
    {
        Http::fake();

        $testTime = \Illuminate\Support\Carbon::create(2025, 12, 16, 9, 30, 0, 'UTC');
        $this->travelTo($testTime);

        Query::create([
            'query_string' => 'lu',
            'resource_type' => 'People',
            'duration_ms' => 250,
            'served_from_cache' => false,
            'created_at' => now()->subMinutes(2),
        ]);

        Query::create([
            'query_string' => 'lu',
            'resource_type' => 'People',
            'duration_ms' => 50,
            'served_from_cache' => true,
            'created_at' => now()->subMinutes(1),
        ]);

        Query::create([
            'query_string' => 'ta',
            'resource_type' => 'Movies',
            'duration_ms' => 300,
            'served_from_cache' => false,
            'created_at' => now()->subSeconds(30),
        ]);

        (new ComputeQueryStatistics)->handle(new Query, new QueryStatistic);

        $this->assertDatabaseHas('query_statistics', [
            'total_queries' => 3,
            'total_cached_queries' => 1,
            'average_duration_ms' => '200.00',
            'most_popular_hour' => 9,
        ]);

        $stats = QueryStatistic::orderBy('calculated_at', 'desc')->first();

        $this->assertEquals('lu', $stats->top_five_queries[0]['query']);
        $this->assertEquals(2, $stats->top_five_queries[0]['count']);
        $this->assertEquals('ta', $stats->top_five_queries[1]['query']);
    }

    public function test_it_handles_empty_queries_gracefully()
    {
        (new ComputeQueryStatistics)->handle(new Query, new QueryStatistic);

        $this->assertDatabaseHas('query_statistics', [
            'total_queries' => 0,
            'total_cached_queries' => 0,
            'average_duration_ms' => '0.00',
        ]);

        $stats = QueryStatistic::orderBy('calculated_at', 'desc')->first();

        $this->assertIsArray($stats->top_five_queries);
        $this->assertEmpty($stats->top_five_queries);
    }

    public function test_it_limits_top_queries_to_exactly_five()
    {
        foreach (range('a', 'f') as $index => $char) {
            $count = 6 - $index;

            for ($i = 0; $i < $count; $i++) {
                Query::create([
                    'query_string' => $char, // using chars for unique strings
                    'resource_type' => 'People',
                    'duration_ms' => 100,
                    'served_from_cache' => false,
                    'created_at' => now()->subSeconds(30),
                ]);
            }
        }

        (new ComputeQueryStatistics)->handle(new Query, new QueryStatistic);

        $stats = QueryStatistic::orderBy('calculated_at', 'desc')->first();

        $this->assertCount(5, $stats->top_five_queries);
        $this->assertEquals('a', $stats->top_five_queries[0]['query']);
        // Verify 'f' (the 6th one) is NOT in the array
        $queriesInStats = collect($stats->top_five_queries)->pluck('query');
        $this->assertNotContains('f', $queriesInStats);
    }

    public function test_it_calculates_average_duration_with_correct_precision()
    {
        Query::create([
            'query_string' => 'a',
            'resource_type' => 'x',
            'duration_ms' => 100,
            'served_from_cache' => false,
            'created_at' => now(),
        ]);

        Query::create([
            'query_string' => 'b',
            'resource_type' => 'x',
            'duration_ms' => 100,
            'served_from_cache' => false,
            'created_at' => now(),
        ]);

        Query::create([
            'query_string' => 'c',
            'resource_type' => 'x',
            'duration_ms' => 101,
            'served_from_cache' => false,
            'created_at' => now(),
        ]);

        (new ComputeQueryStatistics)->handle(new Query, new QueryStatistic);

        $this->assertDatabaseHas('query_statistics', [
            'average_duration_ms' => '100.33', // (100+100+101) / 3
        ]);
    }

    public function test_it_correctly_calculates_the_cache_hit_ratio()
    {
        // 10 total queries, 7 are from cache
        for ($i = 0; $i < 7; $i++) {
            Query::create(['query_string' => 'a', 'resource_type' => 'x', 'duration_ms' => 10, 'served_from_cache' => true, 'created_at' => now()]);
        }
        for ($i = 0; $i < 3; $i++) {
            Query::create(['query_string' => 'b', 'resource_type' => 'x', 'duration_ms' => 100, 'served_from_cache' => false, 'created_at' => now()]);
        }

        (new ComputeQueryStatistics)->handle(new Query, new QueryStatistic);

        $this->assertDatabaseHas('query_statistics', [
            'total_queries' => 10,
            'total_cached_queries' => 7,
        ]);
    }
}
