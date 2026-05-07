<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/events/{id}', function() {
    it('responds with HTTP 200 OK when event is found', function() {
        $total_tickets = 100;
        $reserved_tickets = 10;
        $sold_tickets = 5;
        $available_tickets = $total_tickets - $reserved_tickets - $sold_tickets;
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => $total_tickets,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => $reserved_tickets,
            'event_id' => $event->id,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => $sold_tickets,
            'event_id' => $event->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this
            ->get("/api/events/{$event->id}")
            ->assertOk()
            ->assertSimilarJson([
                'title' => $event->title,
                'description' => $event->description,
                'total_tickets' => $total_tickets,
                'reserved_tickets' => $reserved_tickets,
                'sold_tickets' => $sold_tickets,
                'available_tickets' => $available_tickets,
            ]);
    });

    it('responds with HTTP 404 Not Found when event does not exist', function() {
        $this
            ->get('/api/events/1')
            ->assertNotFound()
            ->assertSimilarJson(['message' => 'Event does not exist']);
    });
});
