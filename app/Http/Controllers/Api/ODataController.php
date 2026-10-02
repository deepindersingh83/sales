<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ODataFeed;
use App\Services\Reporting\ODataQueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * OData v4 endpoint for Power BI / Excel / Tableau ("Get Data → OData feed").
 * Authenticated like the rest of the API (Bearer token, or Basic auth with the
 * workspace API token as password — what BI tools can send).
 */
class ODataController extends Controller
{
    public function __construct(protected ODataFeed $feed) {}

    /** Service document: the list of entity sets. */
    public function service(): JsonResponse
    {
        return $this->json([
            '@odata.context' => $this->root().'/$metadata',
            'value' => collect(array_keys($this->feed->entitySets()))
                ->map(fn ($name) => ['name' => $name, 'kind' => 'EntitySet', 'url' => $name])
                ->all(),
        ]);
    }

    public function metadata(): Response
    {
        return response($this->feed->metadata(), 200, [
            'Content-Type' => 'application/xml',
            'OData-Version' => '4.0',
        ]);
    }

    public function entitySet(Request $request, string $entitySet): JsonResponse
    {
        $options = collect($request->query())
            ->filter(fn ($v, $k) => str_starts_with($k, '$'))
            ->all();

        try {
            $result = $this->feed->query($entitySet, $options);
        } catch (ODataQueryException $e) {
            return $this->json(['error' => ['code' => 'BadRequest', 'message' => $e->getMessage()]], 400);
        }

        $body = ['@odata.context' => $this->root()."/\$metadata#{$entitySet}"];
        if ($result['count'] !== null) {
            $body['@odata.count'] = $result['count'];
        }
        $body['value'] = $result['value'];

        if ($result['next_skip'] !== null) {
            $next = array_merge($options, ['$skip' => $result['next_skip']]);
            if ($result['next_top'] !== null) {
                $next['$top'] = $result['next_top'];
            }
            unset($next['$count']);
            $body['@odata.nextLink'] = $this->root()."/{$entitySet}?".http_build_query($next, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->json($body);
    }

    protected function root(): string
    {
        return url('/api/v1/odata');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, ['OData-Version' => '4.0'], JSON_UNESCAPED_SLASHES);
    }
}
