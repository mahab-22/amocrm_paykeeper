<?php

use App\Http\Controllers\CallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InstallController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingsController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/install', [InstallController::class,'install']);
Route::get('/deactivate',[InstallController::class,'deactivate']);
Route::get('/pay',PaymentController::class);
Route::post('/settings',[SettingsController::class,'install']);
Route::post('/settings/get',[SettingsController::class,'settings_get']);
Route::post('/mail',[SettingsController::class,'mail']);
Route::post('/callback',CallbackController::class);
Route::get('/callback',function (){
    abort(404);
});
