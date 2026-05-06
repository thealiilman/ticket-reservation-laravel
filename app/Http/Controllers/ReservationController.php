<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use DB;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function confirm(string $id)
    {
        return DB::transaction(function () use ($id) {
            $reservation = Reservation::lockForUpdate()->find($id);

            if (!$reservation) {
                return response()->json(
                    ['message' => 'Reservation does not exist'],
                    404,
                );
            }

            $reservation->update([
                'status'=> ReservationStatus::Confirmed
            ]);

            return response()->noContent();
        });
    }

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
