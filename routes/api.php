<?php

use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::apiResource('reservations', ReservationController::class);
