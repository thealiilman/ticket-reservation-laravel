<?php

namespace App\Http\Controllers;

use App\Jobs\ExpireReservation;
use App\Models\Event;
use DB;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function show(string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(
                ['message' => 'Event does not exist'],
                404,
            );
        }

        $reserved_tickets = (int)$event->reservations()->on_hold()->sum(('number_of_tickets'));
        $sold_tickets = (int)$event->reservations()->confirmed()->sum(('number_of_tickets'));
        $available_tickets = $event->total_tickets - $event->reservations()->sum(('number_of_tickets'));

        return response()->json(
            [
                'title' => $event->title,
                'description' => $event->description,
                'total_tickets' => $event->total_tickets,
                'reserved_tickets' => $reserved_tickets,
                'sold_tickets' => $sold_tickets,
                'available_tickets' => $available_tickets,
            ]
        );
    }

    public function reserve(Request $request, string $id) {
        return DB::transaction(function() use ($request, $id) {
            $event = Event::find($id);

            if (!$event) {
                return response()->json(
                    ['message' => 'Event does not exist'],
                    404,
                );
            }

            $available_tickets = $event->total_tickets - $event->reservations()->sum(('number_of_tickets'));
            $tickets_for_reservation = $request->input('number_of_tickets');

            if (($available_tickets - $tickets_for_reservation) < 0) {
                return response()->json(
                    ['message' => 'Sorry folks, no more tickets are available'],
                    410,
                );
            }

            $reservation = $event->reservations()->create(['number_of_tickets' => $tickets_for_reservation]);
            ExpireReservation::dispatch($reservation->id)->delay(now()->addMinutes(5));

            return response()->json(
                ['reservation_id' => $reservation->id],
                201,
            );
        });
    }
}
