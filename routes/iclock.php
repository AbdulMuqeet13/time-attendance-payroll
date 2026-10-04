<?php

use App\Http\Controllers\Adms\IclockController;
use Illuminate\Support\Facades\Route;

/*
| ZKTeco ADMS push protocol. Devices with no URL field call /iclock; others may be set to /api/iclock.
| No session, cookies or CSRF: these are raw device requests.
*/

foreach (['iclock', 'api/iclock'] as $prefix) {
    Route::prefix($prefix)->middleware('throttle:iclock')->group(function () use ($prefix) {
        $name = $prefix === 'iclock' ? 'iclock.' : 'api.iclock.';

        Route::get('cdata', [IclockController::class, 'handshake'])->name($name.'handshake');
        Route::post('cdata', [IclockController::class, 'upload'])->name($name.'upload');
        Route::post('registry', [IclockController::class, 'registry'])->name($name.'registry');
        Route::get('getrequest', [IclockController::class, 'poll'])->name($name.'poll');
        Route::post('devicecmd', [IclockController::class, 'commandResult'])->name($name.'command-result');
        Route::post('querydata', [IclockController::class, 'queryData'])->name($name.'query-data');
        Route::any('{path?}', [IclockController::class, 'fallback'])->where('path', '.*')->name($name.'fallback');
    });
}
