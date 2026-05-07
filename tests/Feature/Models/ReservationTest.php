<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Model::on_hold()', function() {
    it('returns a collection containing only on_hold records', function() {
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => 0,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => 0,
            'event_id' => $event->id,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => 0,
            'event_id' => $event->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $on_hold_reservations = Reservation::query()->on_hold()->count();
        expect($on_hold_reservations)->toBe(1);
    });
});

describe('Model::confirmed()', function() {
    it('returns a collection containing only confirmed records', function() {
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => 0,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => 0,
            'event_id' => $event->id,
        ]);
        Reservation::factory()->create([
            'number_of_tickets' => 0,
            'event_id' => $event->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $confirmed_reservations = Reservation::query()->confirmed()->count();
        expect($confirmed_reservations)->toBe(1);
    });
});
