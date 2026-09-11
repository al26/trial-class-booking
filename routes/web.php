<?php

use App\Http\Controllers\BookingDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookingDemoController::class, 'index'])->name('booking.index');
Route::post('/booking/checkout', [BookingDemoController::class, 'store'])->name('booking.store');
