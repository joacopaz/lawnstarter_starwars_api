<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\IncorrectSwapiResourceException;
use App\Exceptions\SwapiLookupFailedException;
use App\Services\SwApiService;
use Exception;
use Inertia\Inertia;

class ResourceController
{
    public function __construct(private readonly SwApiService $swApiService) {}

    public function show(string $resourceType, string $uid)
    {
        try {
            $metadata = $this->swApiService->getResourceMetadata($resourceType, $uid);

            return Inertia::render('resource', ['metadata' => $metadata]);

        } catch (SwapiLookupFailedException|IncorrectSwapiResourceException) {
            return Inertia::render('error', [
                'status' => 404,
                'message' => 'The Star Wars resource you requested could not be found.',
            ])->toResponse(request())->setStatusCode(404);

        } catch (Exception) {

            return Inertia::render('error', ['status' => 500])
                ->toResponse(request())->setStatusCode(500);
        }
    }
}
