<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TransactionController;

Route::get('/', function () {
    return response()->json("Hello from your API!");
});


Route::name('api.transactions.')->prefix('/transactions/')->group(function () {
    Route::post('deposit', [TransactionController::class, 'deposit'])->name('deposit');
    Route::post('transfer', [TransactionController::class, 'transfer'])->name('transfer');
});
