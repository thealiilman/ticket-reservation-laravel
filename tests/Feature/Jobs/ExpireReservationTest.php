<?php

use App\Enums\ReservationStatus;
use App\Jobs\ExpireReservation;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('destroys a reservation when given reservation ID is found in database', function() {
    Queue::fake();
    $event = Event::factory()->create([
        'title' => 'The Music of Oasis',
        'total_tickets' => 0,
    ]);
    $reservation = Reservation::factory()->create([
        'number_of_tickets' => 0,
        'event_id' => $event->id,
    ]);
    Reservation::factory()->create([
        'number_of_tickets' => 0,
        'event_id' => $event->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    (new ExpireReservation($reservation->id))->handle();

    $on_hold_reservations = $event->reservations()->on_hold()->count();
    expect($on_hold_reservations)->toBe(0);
    $confirmed_reservations = $event->reservations()->confirmed()->count();
    expect($confirmed_reservations)->toBe(1);
});

it('does not do anything when given reservation ID is not found in database', function() {
    Queue::fake();
    $event = Event::factory()->create([
        'title' => 'The Music of Oasis',
        'total_tickets' => 0,
    ]);
    Reservation::factory()->create([
        'number_of_tickets' => 0,
        'event_id' => $event->id,
    ]);

    (new ExpireReservation(666420))->handle();
    expect($event->reservations->count())->toBe(1);
});
