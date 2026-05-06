<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DELETE /api/reservations/{id}', function() {
    it('responds with HTTP 204 No Content when reservation is found and still on-hold', function() {
        $event = Event::factory()->create(['title' => 'The Music of Oasis', 'total_tickets' => 0]);
        $reservation = Reservation::factory()->create(['number_of_tickets' => 0, 'event_id' => $event->id]);

        $this
            ->delete("/api/reservations/{$reservation->id}")
            ->assertNoContent();
        
        $this->assertDatabaseEmpty(Reservation::class);
    });

    it('responds with HTTP 404 Not Found when reservation does not exist', function() {
        $this
            ->delete('/api/reservations/1')
            ->assertNotFound()
            ->assertSimilarJson(['message' => 'Reservation does not exist']);
    });

    it('responds with HTTP 405 Method Not Allowed when reservation is found and already confirmed', function() {
        $event = Event::factory()->create(['title' => 'The Music of Oasis', 'total_tickets' => 0]);
        $reservation = Reservation::factory()->create([
            'number_of_tickets' => 0,
            'event_id' => $event->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this
            ->delete("/api/reservations/{$reservation->id}")
            ->assertMethodNotAllowed()
            ->assertSimilarJson(['message' => 'Reservation has already been confirmed']);
    });
});
