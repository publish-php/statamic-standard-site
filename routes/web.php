<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Serves the publication verification endpoint.
 *
 * GET /.well-known/site.standard.publication
 * Returns the AT-URI of the publication record as plaintext.
 *
 * @see https://standard.site/docs/verification/
 */
Route::get('/.well-known/site.standard.publication', function () {
    $uri = config('statamic.standard-site.publication_uri');

    if (! $uri) {
        return response('No publication record configured.', 404)
            ->header('Content-Type', 'text/plain');
    }

    return response($uri, 200)
        ->header('Content-Type', 'text/plain');
});
