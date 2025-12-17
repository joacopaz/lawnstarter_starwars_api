<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Exceptions\IncorrectSwapiResourceException;
use App\Exceptions\SwapiLookupFailedException;
use App\Models\Query;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SwApiService
{
    private string $baseUrl = 'https://swapi.tech/api';

    public function __construct(protected readonly Query $queryModel) {}

    /**
     * Fetches data from star wars api for people or movies and logs the queries in the DB
     *
     * @param  string  $resourceType  The resource to search (e.g., 'People' | 'Movies').
     * @param  string  $query  The search term (e.g., 'Luke').
     * @return array The fetched data.
     *
     * @throws RuntimeException If the external API call fails or IncorrectSwapiResourceException if resource type is invalid.
     */
    public function search(string $resourceType, string $query): array
    {
        $cacheKey = "swapi:search:{$resourceType}:{$query}";
        $servedFromCache = false;
        $startDurationMicroSeconds = microtime(true);

        if (Cache::has($cacheKey)) {
            $servedFromCache = true;
            $swApiData = Cache::get($cacheKey);
        } else {
            $parsedResourceType = $this->parseResourceType($resourceType)->value;
            $url = "{$this->baseUrl}/{$parsedResourceType}";
            $queryKey = $parsedResourceType === 'people' ? 'name' : 'title';
            Log::debug("Reaching out to external api {$url}?{$queryKey}={$query}");

            try {
                /** @var \Illuminate\Http\Client\Response $apiResponse */
                $apiResponse = Http::timeout(5)->get($url, [
                    $queryKey => $query,
                ]);
                if ($apiResponse->failed()) {
                    throw new SwapiLookupFailedException;
                }

                $swApiData = $apiResponse->json('result', []);
                Cache::add($cacheKey, $swApiData);
            } catch (Exception $e) {
                throw $e;
            }
        }

        $durationMs = round((microtime(true) - $startDurationMicroSeconds) * 1_000);

        Concurrency::defer([
            fn () => $this->queryModel->create([
                'query_string' => $query,
                'resource_type' => $resourceType,
                'served_from_cache' => $servedFromCache,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]),

        ]);

        return $swApiData;
    }

    /**
     * Fetches data from star wars api for a specific resource UID
     *
     * @param  string  $resourceType  The resource to search (e.g., 'People' | 'Movies').
     * @param  string  $uid  The uid.
     * @return array The fetched data.
     *
     * @throws RuntimeException If the external API call fails or IncorrectSwapiResourceException if resource type is invalid.
     */
    public function getResourceMetadata(string $resourceType, string $uid): array
    {
        $parsedResourceType = $this->parseResourceType($resourceType)->value;
        $cacheKey = "swapi:meta:{$parsedResourceType}:{$uid}";

        return Cache::rememberForever($cacheKey, function () use ($uid, $parsedResourceType) {
            $url = "{$this->baseUrl}/{$parsedResourceType}/{$uid}";

            try {
                Log::debug("Reaching out to external api {$url}");

                /** @var \Illuminate\Http\Client\Response $apiResponse */
                $apiResponse = Http::timeout(5)->get($url);

                if ($apiResponse->failed()) {
                    throw new SwapiLookupFailedException;
                }

                $result = $apiResponse->json('result', []);
                // characters appear in an array of films, films star an array of characters
                $keyToUnpack = $parsedResourceType === 'people' ? 'films' : 'characters';
                $result['properties'][$keyToUnpack] = $this->mapResourceUrlsToNames($parsedResourceType, $result['properties'][$keyToUnpack]);

                return $result;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new RuntimeException("Could not connect to the api service: {$e->getMessage()}");
            }
        });
    }

    private function mapResourceUrlsToNames(string $resourceType, array $resourceUrls): array
    {
        if (! $resourceUrls) {

            return [];
        }
        $result = [];
        $urlsToFetch = [];

        foreach ($resourceUrls as $resourceUrl) {
            $uid = basename(parse_url($resourceUrl, PHP_URL_PATH));
            $cacheKey = "swapi:mapping:{$resourceType}:{$uid}";

            if (Cache::has($cacheKey)) {
                $result[] = Cache::get($cacheKey);
            } else {
                $urlsToFetch[] = $resourceUrl;
            }
        }

        if (! $urlsToFetch) {
            return $this->getNameAndUrlFromResource($result, $resourceType);
        }

        $responses = Http::pool(function ($pool) use ($urlsToFetch) {
            foreach ($urlsToFetch as $urlToFetch) {
                $pool->get($urlToFetch);
            }
        });

        foreach ($responses as $response) {
            if ($response->successful()) {
                $data = $response->json('result', []);
                $cacheKey = "swapi:mapping:{$resourceType}:{$data['uid']}";
                Cache::add($cacheKey, $data);
                $result[] = $data;
            } else {
                Log::error('Failure', $response);
            }
        }

        return $this->getNameAndUrlFromResource($result, $resourceType);
    }

    /**
     * Validates a string resource type and casts it to a valid SWAPI value
     *
     * @param  string  $resourceType  The resource to search (e.g., 'People' | 'Movies').
     *
     * @throws IncorrectSwapiResourceException if the resource type is invalid
     */
    private function parseResourceType(string $resourceType): ResourceType
    {
        switch ($resourceType) {
            case 'People':
            case 'people':
                return ResourceType::People;
                break;
            case 'Movies':
            case 'movies':
                return ResourceType::Films;
                break;
            default:
                throw new IncorrectSwapiResourceException;
        }
    }

    private function getNameAndUrlFromResource(array $resources, string $resourceType): array
    {
        $nameKey = $resourceType === 'people' ? 'title' : 'name';
        $redirectUrl = $resourceType === 'people' ? 'movies' : 'people';

        return array_map(function ($item) use ($nameKey, $redirectUrl) {
            return [
                'name' => $item['properties'][$nameKey] ?? null,
                'url' => "/{$redirectUrl}/{$item['uid']}",
            ];
        }, $resources);

    }
}
