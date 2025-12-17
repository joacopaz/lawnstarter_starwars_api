<?php

namespace Tests\Feature\Queries;

use App\Enums\ResourceType;
use App\Exceptions\SwapiLookupFailedException;
use App\Models\Query;
use App\Services\SwApiService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SwApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $swapiBaseUrl = 'https://swapi.tech/api';

    private SwApiService $swapiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->swapiService = app(SwApiService::class);
    }

    private function runDeferredTasks()
    {
        app(DeferredCallbackCollection::class)->invoke();
    }

    public function test_it_successfully_fetches_and_logs_a_star_wars_query()
    {
        $fakeData = [
            'name' => 'Luke Skywalker',
            'height' => '172',
            'mass' => '77',
        ];

        Http::fake([
            "{$this->swapiBaseUrl}/*" => Http::response([
                'result' => $fakeData,
            ], 200),
        ]);

        Cache::spy();
        $cacheKey = 'swapi:search:People:lu';
        $data = $this->swapiService->search('People', 'lu');
        $this->runDeferredTasks();

        Cache::shouldHaveReceived('add')->once();
        $this->assertIsArray($data);
        $this->assertEquals($data, $fakeData);

        $this->assertDatabaseHas('queries', [
            'query_string' => 'lu',
            'resource_type' => 'People',
            'served_from_cache' => false,
        ]);

        $this->assertTrue(
            Query::where('query_string', 'lu')
                ->whereNotNull('created_at')
                ->whereNotNull('duration_ms')
                ->exists()
        );

        // it should serve it from cache the second time
        $data = $this->swapiService->search('People', 'lu');
        $this->runDeferredTasks();
        Cache::shouldHaveReceived('get')->once();

        $this->assertDatabaseHas('queries', [
            'query_string' => 'lu',
            'resource_type' => 'People',
            'served_from_cache' => true,
        ]);

        $this->assertTrue(
            Query::where('served_from_cache', true)
                ->whereNotNull('created_at')
                ->whereNotNull('duration_ms')
                ->exists()
        );
    }

    public function test_it_handles_swapi_errors_gracefully()
    {
        Http::fake([
            "{$this->swapiBaseUrl}/*" => Http::response(['error' => 'Not Found'], 404),
        ]);
        $this->expectException(SwapiLookupFailedException::class);
        $this->swapiService->search('People', 'lu');
    }

    public function test_it_handles_general_errors_gracefully()
    {
        Http::fake([
            "{$this->swapiBaseUrl}/*" => fn () => throw new \Exception('Connection Failed'),        ]);
        $this->expectException(Exception::class);
        $this->swapiService->search('People', 'lu');
    }

    public function test_it_gets_resource_metadata()
    {
        $filmsResource = ResourceType::Films->value;
        $personResource = ResourceType::People->value;

        Http::fake([
            "{$this->swapiBaseUrl}/{$filmsResource}/1" => Http::response(['result' => ['properties' => ['title' => 'A New Hope'], 'uid' => '1']], 200),
            "{$this->swapiBaseUrl}/{$filmsResource}/2" => Http::response(['result' => ['properties' => ['title' => 'The Empire Strikes Back'], 'uid' => '2']], 200),
            "{$this->swapiBaseUrl}/{$filmsResource}/3" => Http::response(['result' => ['properties' => ['title' => 'Return of the Jedi'], 'uid' => '3']], 200),

            "{$this->swapiBaseUrl}/*" => Http::response([
                'result' => [
                    'properties' => [
                        $filmsResource => [
                            "{$this->swapiBaseUrl}/{$filmsResource}/1",
                            "{$this->swapiBaseUrl}/{$filmsResource}/2",
                            "{$this->swapiBaseUrl}/{$filmsResource}/3",
                        ],
                        'name' => 'Luke Skywalker',
                    ],
                ],
            ], 200),
        ]);

        Cache::spy();
        $data = $this->swapiService->getResourceMetadata($personResource, '1');
        $films = $data['properties']['films'];
        $this->assertEquals('A New Hope', $films[0]['name']);
        $this->assertEquals('/movies/1', $films[0]['url']);
        $this->assertEquals('The Empire Strikes Back', $films[1]['name']);
        $this->assertEquals('/movies/2', $films[1]['url']);
        $this->assertEquals('Return of the Jedi', $films[2]['name']);
        $this->assertEquals('/movies/3', $films[2]['url']);
        Http::assertSentCount(4);
        Cache::shouldHaveReceived('rememberForever')->times(1);
        Cache::shouldHaveReceived('add')->times(3); // 3 unpacked films
        // uses cache next call
        $this->swapiService->getResourceMetadata($personResource, '1');
        Http::assertSentCount(4); // no refetchs
    }
}
