<?php

use App\Http\Controllers\HotelManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard/predictions', [HotelManagementController::class, 'getAiPredictionsAndPricing']);
Route::get('/dashboard/audit-date', [HotelManagementController::class, 'auditDate']);
Route::get('/live-availability', [HotelManagementController::class, 'getLiveAvailability']);
Route::get('/available-rooms', [HotelManagementController::class, 'getAvailableRoomsForDates']);
Route::post('/bookings', [HotelManagementController::class, 'saveBooking']);
Route::post('/bookings/update-status', [HotelManagementController::class, 'updateBookingStatus']);
Route::get('/reservations/all', [HotelManagementController::class, 'getAllReservations']);
Route::get('/active-reservations', [HotelManagementController::class, 'getActiveReservations']);

Route::get('/users', [HotelManagementController::class, 'getUsers']);
Route::post('/users', [HotelManagementController::class, 'createUser']);
Route::post('/users/update', [HotelManagementController::class, 'updateUser']);
Route::delete('/users/{id}', [HotelManagementController::class, 'deleteUser']);
Route::post('/login', [HotelManagementController::class, 'login']);
