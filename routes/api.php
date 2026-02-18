<?php

use App\Http\Controllers\Portal\CompanyProcessingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Bot API: Tenant-Kontext via Domain, kein Session/Theme Overhead
Route::middleware([
    'universal',
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
    'throttle:60,1',
])->prefix('bot')->group(function () {
    Route::get('/next', [CompanyProcessingController::class, 'getNext']);
    Route::get('/batch', [CompanyProcessingController::class, 'getBatch']);
    Route::post('/{id}/complete', [CompanyProcessingController::class, 'markComplete'])->whereNumber('id');
    Route::post('/{id}/failed', [CompanyProcessingController::class, 'markFailed'])->whereNumber('id');
    Route::post('/batch-complete', [CompanyProcessingController::class, 'markBatchComplete']);
    Route::get('/stats', [CompanyProcessingController::class, 'getStats']);
});

Route::post('/payments-providers/stripe/webhook', [
    App\Http\Controllers\PaymentProviders\StripeController::class,
    'handleWebhook',
])->name('payments-providers.stripe.webhook');

Route::post('/payments-providers/paddle/webhook', [
    App\Http\Controllers\PaymentProviders\PaddleController::class,
    'handleWebhook',
])->name('payments-providers.paddle.webhook');

Route::post('/payments-providers/lemon-squeezy/webhook', [
    App\Http\Controllers\PaymentProviders\LemonSqueezyController::class,
    'handleWebhook',
])->name('payments-providers.lemon-squeezy.webhook');
