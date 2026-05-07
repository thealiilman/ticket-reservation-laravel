<?php

use App\Enums\ReservationStatus;
use App\Jobs\ExpireReservation;
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

describe('POST /api/events/{id}/reserve', function() {
    it('responds with HTTP 201 Created when event is found and reservation is made', function() {
        Queue::fake();
        $total_tickets = 100;
        $tickets_for_reservation = 5;
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => $total_tickets,
        ]);

        $this
            ->post(
                "/api/events/{$event->id}/reserve",
                ['number_of_tickets' => $tickets_for_reservation]
            )
            ->assertCreated()
            ->assertSimilarJson([
                'reservation_id' => $event->reservations()->first()->id,
            ]);

        $event_reservations = $event->reservations()->get();
        expect($event_reservations)->toHaveCount(1);
        expect($event_reservations->first()->number_of_tickets)->toEqual($tickets_for_reservation);
        Queue::assertPushed(ExpireReservation::class, function ($job) {
            /**
             * @author Ali Ilman
             * @description
             * There doesn't seem to be a robust way to check
             * if the job's delay is 5 minutes.
             * 
             * Will stick to validating there is delay.
             */
            return $job->delay !== null;
        });
    });

    it('responds with HTTP 404 Not Found when event does not exist', function() {
        Queue::fake();
        $this
            ->post('/api/events/1/reserve')
            ->assertNotFound()
            ->assertSimilarJson(['message' => 'Event does not exist']);

        Queue::assertNothingPushed();
    });

    it('responds with HTTP 410 Gone when event is found but tickets no longer available', function() {
        Queue::fake();
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => 0,
        ]);

        $this
            ->post(
                "/api/events/{$event->id}/reserve",
                ['number_of_tickets' => 5]
            )
            ->assertGone()
            ->assertSimilarJson(['message' => 'Sorry folks, no more tickets are available']);

        $event_reservations = $event->reservations()->get();
        expect($event_reservations)->toHaveCount(0);
        Queue::assertNothingPushed();
    });
});
