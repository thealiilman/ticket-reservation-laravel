<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function destroy(string $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json(
                ['message' => 'Reservation does not exist'],
                404,
            );
        }

        if ($reservation->status == ReservationStatus::Confirmed) {
            return response()->json(
                ['message' => 'Reservation has already been confirmed'],
                405,
            );
        }

        $reservation->delete();

        return response()->json([], 204);
    }
}
