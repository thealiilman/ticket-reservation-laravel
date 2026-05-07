<?php

use App\Enums\ReservationStatus;
use App\Jobs\ExpireReservation;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

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

    /**
     * @author Ali Ilman
     * @description
     * Test was written with the help of Gemini.
     * 
     * I reckon I spent at least 2 hours in total tinkering with
     * various approaches – Concurrency::run, Http::pool; all to no avail.
     * I even had the server running with testing environment whilst the test
     * executes network requests to the server – this didn't feel maintainable
     * and wouldn't be ideal for running in CI/CD pipelines.
     *
     * I resorted to Gemini for help because I felt having a test for a
     * significant part of the reservation process is crucial even though as I
     * have experienced now how DIFFICULT! testing concurrency is!
     *
     * For my own curiosity, I asked Gemini how it could look like if we were
     * to have a test with 3 concurrent requests – it didn't look pretty.
     */
    it('respects inventory when there are concurrent requests', function () {
        Queue::fake();
        $total_tickets = 10;
        // 1. Setup: Create an event with 10 tickets
        $event = Event::factory()->create([
            'title' => 'The Music of Oasis',
            'total_tickets' => $total_tickets,
        ]);
        $endpoint = "/api/events/{$event->id}/reserve";

        // 2. Fork the process to simulate two concurrent requests
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->fail('Could not fork process');
        } elseif ($pid) {
            // --- Parent Process (Request A) ---
            // Tries to reserve all 10 tickets
            $responseA = $this->post($endpoint, [
                'number_of_tickets' => $total_tickets
            ]);

            // Wait for the child process to finish
            pcntl_wait($status);
            $childExitCode = pcntl_wexitstatus($status);

            // Assertions for Request A
            $responseA->assertStatus(201);
            Queue::assertPushedTimes(ExpireReservation::class, 1);
            
            // Assertions for Request B (via child exit code)
            expect($childExitCode)->toBe(
                0,
                "Child process failed to receive 410 status"
            );

            // Verify final state: only 10 tickets reserved
            $reserved_number_of_tickets = (int)$event->reservations()->sum('number_of_tickets');
            expect($reserved_number_of_tickets)->toBe($total_tickets);
        } else {
            // --- Child Process (Request B) ---
            // Important: Reconnect to DB to get a fresh connection for this process
            DB::reconnect();
            
            // Tiny sleep to ensure Parent Process (Request A) hits the lock first
            usleep(50000); 

            // Tries to reserve 1 ticket (should find it sold out/locked)
            $responseB = $this->post($endpoint, [
                'number_of_tickets' => 1
            ]);

            Queue::assertPushedTimes(ExpireReservation::class, 0);

            // Exit with 0 if we got the expected 410 Gone, else 1
            exit($responseB->status() === 410 ? 0 : 1);
        }
    });
});
