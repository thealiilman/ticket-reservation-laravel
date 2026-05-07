<?php

namespace App\Http\Controllers;

use App\Models\Event;
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

        $reserved_tickets = $event->reservations()->on_hold()->sum(('number_of_tickets'));
        $sold_tickets = $event->reservations()->confirmed()->sum(('number_of_tickets'));
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
}
