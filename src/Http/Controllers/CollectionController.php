<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statamic\Facades\Collection;

/**
 * Manages per-collection opt-in for Standard Site syncing.
 *
 * Lists all collections with their current sync status and allows
 * toggling the `standard_site_enabled` flag in the collection's
 * inject (cascade) config.
 */
class CollectionController extends Controller
{
    /**
     * List all collections with their Standard Site sync status.
     */
    public function index(): JsonResponse
    {
        $collections = Collection::all()->map(function ($collection) {
            return [
                'handle' => $collection->handle(),
                'title' => $collection->title(),
                'enabled' => (bool) $collection->cascade()->get('standard_site_enabled', false),
                'entries_count' => $collection->queryEntries()->count(),
            ];
        })->sortBy('title')->values();

        return response()->json([
            'collections' => $collections,
        ]);
    }

    /**
     * Toggle sync status for a collection.
     */
    public function toggle(Request $request, string $handle): JsonResponse
    {
        $collection = Collection::findByHandle($handle);
        if (! $collection) {
            return response()->json([
                'success' => false,
                'error' => "Collection '{$handle}' not found.",
            ], 404);
        }

        $enabled = (bool) $request->input('enabled', false);
        $cascade = $collection->cascade();

        if ($enabled) {
            $cascade->put('standard_site_enabled', true);
        } else {
            $cascade->forget('standard_site_enabled');
        }

        $collection->cascade($cascade->all());
        $collection->save();

        return response()->json([
            'success' => true,
            'handle' => $handle,
            'enabled' => $enabled,
        ]);
    }
}
