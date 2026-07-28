<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishPhp\StatamicStandardSite\Http\Controllers\PublicationController;
use PublishPhp\StatamicStandardSite\Http\Controllers\StatusController;

Route::prefix('standard-site')->name('standard-site.')->group(function () {
    Route::post('publication/check', [PublicationController::class, 'check'])->name('publication.check');
    Route::post('publication/create', [PublicationController::class, 'create'])->name('publication.create');
    Route::get('status/errors', [StatusController::class, 'errors'])->name('status.errors');
    Route::get('status/documents', [StatusController::class, 'documents'])->name('status.documents');
});
