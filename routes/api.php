<?php

use App\Http\Controllers\Api\BookingApiController;
use App\Http\Controllers\Api\TrialClassApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Trial Classes Endpoints
Route::get('/trial-classes', [TrialClassApiController::class, 'index'])->name('api.trial-classes.index');
Route::get('/trial-classes/{trialClass}', [TrialClassApiController::class, 'show'])->name('api.trial-classes.show');
Route::get('/trial-classes/{trialClass}/roster', [TrialClassApiController::class, 'roster'])->name('api.trial-classes.roster');

// Booking Endpoints
Route::post('/bookings/checkout', [BookingApiController::class, 'checkout'])->name('api.bookings.checkout');
