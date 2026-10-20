<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KitchenController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [KitchenController::class, 'dashboard'])->name('dashboard');
    Route::get('/inventory', [KitchenController::class, 'inventory'])->name('inventory');
    Route::post('/inventory', [KitchenController::class, 'saveInventory'])->name('inventory.store');
    Route::put('/inventory/{id}', [KitchenController::class, 'saveInventory'])->whereNumber('id')->name('inventory.update');
    Route::post('/locations', [KitchenController::class, 'addLocation'])->name('locations.store');
    Route::post('/locations/{id}/verify', [KitchenController::class, 'verifyLocation'])->whereNumber('id')->name('locations.verify');
    Route::put('/locations/{id}', [KitchenController::class, 'updateLocation'])->whereNumber('id')->name('locations.update');
    Route::delete('/locations/{id}', [KitchenController::class, 'deleteLocation'])->whereNumber('id')->name('locations.destroy');
    Route::get('/planner', [KitchenController::class, 'planner'])->name('planner');
    Route::post('/planner/generate', [KitchenController::class, 'generate'])->name('planner.generate');
    Route::post('/meals', [KitchenController::class, 'saveMeal'])->name('meals.store');
    Route::delete('/meals/{id}', [KitchenController::class, 'deleteMeal'])->whereNumber('id')->name('meals.destroy');
    Route::post('/meals/{id}/cook', [KitchenController::class, 'cook'])->whereNumber('id')->name('meals.cook');
    Route::get('/recipes', [KitchenController::class, 'recipes'])->name('recipes');
    Route::post('/recipes', [KitchenController::class, 'saveRecipe'])->name('recipes.store');
    Route::put('/recipes/{id}', [KitchenController::class, 'saveRecipe'])->whereNumber('id')->name('recipes.update');
    Route::post('/kits', [KitchenController::class, 'saveKit'])->name('kits.store');
    Route::get('/shopping', [KitchenController::class, 'shopping'])->name('shopping');
    Route::get('/family', [KitchenController::class, 'family'])->name('family');
    Route::post('/profiles', [KitchenController::class, 'saveProfile'])->name('profiles.store');
    Route::put('/profiles/{id}', [KitchenController::class, 'saveProfile'])->whereNumber('id')->name('profiles.update');
    Route::post('/invite', [KitchenController::class, 'invite'])->middleware('throttle:5,1')->name('invite');
});
