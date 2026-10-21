<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| REST API v1 — authenticated with a client API token (Authorization: Bearer sm_...).
| Every call acts on the client the token belongs to.
*/
Route::prefix('v1')->name('api.')->middleware('throttle:api')->group(function () {
    Route::get('me', V1\MeController::class)->name('me');

    Route::apiResource('lists', V1\ListController::class);
    Route::prefix('lists/{list}')->name('lists.')->group(function () {
        Route::get('subscribers', [V1\SubscriberController::class, 'index'])->name('subscribers.index');
        Route::post('subscribers', [V1\SubscriberController::class, 'store'])->name('subscribers.store');
        Route::post('subscribers/bulk', [V1\SubscriberController::class, 'bulk'])->name('subscribers.bulk');
        Route::get('subscribers/{subscriber}', [V1\SubscriberController::class, 'show'])->name('subscribers.show');
        Route::patch('subscribers/{subscriber}', [V1\SubscriberController::class, 'update'])->name('subscribers.update');
        Route::delete('subscribers/{subscriber}', [V1\SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        Route::post('subscribers/{subscriber}/unsubscribe', [V1\SubscriberController::class, 'unsubscribe'])->name('subscribers.unsubscribe');
        Route::post('subscribers/{subscriber}/tags', [V1\SubscriberController::class, 'tags'])->name('subscribers.tags');
    });

    Route::get('campaigns', [V1\CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('campaigns', [V1\CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [V1\CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('campaigns/{campaign}/launch', [V1\CampaignController::class, 'launchCampaign'])->name('campaigns.launch');
    Route::post('campaigns/{campaign}/pause', [V1\CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/resume', [V1\CampaignController::class, 'resume'])->name('campaigns.resume');

    Route::get('automations', [V1\AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations/{automation}/enroll', [V1\AutomationController::class, 'enroll'])->name('automations.enroll');

    Route::post('send', V1\SendController::class)->name('send');
});
