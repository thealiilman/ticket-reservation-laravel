<?php

use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post("/reservations/{id}/confirm", [ReservationController::class, 'confirm']);
Route::apiResource('reservations', ReservationController::class);
