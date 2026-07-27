<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishPhp\StatamicStandardSite\Http\Controllers\PublicationController;

Route::prefix('standard-site')->group(function () {
    Route::post('publication/check', [PublicationController::class, 'check']);
    Route::post('publication/create', [PublicationController::class, 'create']);
});
