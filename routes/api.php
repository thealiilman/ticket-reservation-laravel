<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post("/reservations/{id}/confirm", [ReservationController::class, 'confirm']);
Route::apiResource('reservations', ReservationController::class);
Route::post("/events/{id}/reserve", [EventController::class, 'reserve']);
Route::apiResource('events', EventController::class);
