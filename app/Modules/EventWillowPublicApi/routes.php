<?php
use Illuminate\Support\Facades\Route;
use App\Modules\EventWillowPublicApi\Controller;

Route::prefix('api/eventwillow/v1')->middleware(['api', 'throttle:eventwillow-public', \App\Modules\EventWillowPublicApi\AuthenticateSite::class])->group(function () {
    Route::get('events', [Controller::class, 'events']);
    Route::get('events/{id}', [Controller::class, 'event']);
    Route::get('channels', [Controller::class, 'channels']);
    Route::get('channels/{identifier}', [Controller::class, 'channel']);
    Route::get('channels/{identifier}/events', [Controller::class, 'channelEvents']);
    Route::get('facets', [Controller::class, 'facets']);
});
