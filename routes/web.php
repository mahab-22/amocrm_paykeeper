<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ChangeSettingsController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/change_settings/{token_}',[ChangeSettingsController::class,'show_form']);
Route::post('/change_settings/{token_}',[ChangeSettingsController::class,'save'])->name('change_settings');
Route::get('/success',function (){ return view('success_payment');});
Route::get('/fail',function (){ return view('fail_payment');});
//Route::get('/', [InstallController::class,'install']);
