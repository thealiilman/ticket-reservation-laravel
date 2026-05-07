# ticket-reservation-laravel
This Laravel application is my submission for CJ's take-home technical assessment.

The objective is to build a ticket reservation service within a 4-6 hour period where the main focus – as I understand it – is to get a functioning application which respects the expected flow whilst considering ideas one can apply to extend the service.

## Running locally and running tests
### Installing dependencies
<details>
  <summary>Click to expand</summary>

The project requires PHP v8.3 to v8.5 which is the recommended version range for Laravel 13 so far. Ensure that the appropriate PHP version is installed.

Once the appropriate PHP version has been installed, run `composer install` to install the project's dependencies.
</details>

### Environment variables
<details>
  <summary>Click to expand</summary>

Copy-paste the following content into a `.env` file:
```
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:JsRKpqU304s0KRyhKLdGvqrF+JSTzEIu2ANC8yAzvwA=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ticket_reservation
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
```

</details>

### Setting up database
<details>
  <summary>Click to expand</summary>

I recommend installing [DBngin](https://dbngin.com/) by TablePlus to manage database servers

Run `php artisan migrate` which will prepare the database and create tables.
- `ticket_reservation` is the name for local environment's database
  - Refer to `DB_DATABASE` in `.env`
- `ticket_reservation_local` is the name for testing environment's database
  - Refer to `DB_DATABASE` in `phpunit.xml`
</details>

### Running locally
<details>
  <summary>Click to expand</summary>

- Run `php artisan serve` to start the application server
- Run `php artisan queue:work` to start the worker process – queued jobs will be processed by this process
</details>

### Running tests
<details>
  <summary>Click to expand</summary

As the project uses Pest for the test suite, run `./vendor/bin/pest` to execute tests.
</details>

## Database design
<img width="631" height="252" alt="Screenshot 2026-05-03 at 12 15 06" src="https://github.com/user-attachments/assets/fd93ce2a-7881-4cf1-82f8-83fbf463bc02" />

## Key decisions / trade-offs
### Not storing number of reserved tickets, sold tickets, and available tickets in `events` table
<details>
  <summary>Click to expand</summary>

My implementation with obtaining inventory status relies on the event querying its reservations.

1. On-hold associated reservations
2. Confirmed associated reservations
3. All associated reservations

For each of these queries, we execute a `SUM` on the `number_of_tickets` column. From here, we'll get:

1. Number of on-hold tickets
2. Number of confirmed tickets
3. Number of unavailable tickets

No counter-caching in `events` table. I'm not concerned about performance for the following reasons:
1. We can add an index on `event_id` in `reservations` table to avoid full table scans, scanning only for rows with the given `event_id`
2. Most events have capacity in 4-digits, few going to 5-digits whilst 6-digits and beyond are rare – database may well manage to look for up to 6-digits worth of rows with index on `event_id` just fine

</details>

### Database lock instead of cache lock
<details>
  <summary>Click to expand</summary>

I intentionally went with database lock to prevent race conditions because the circumstances are strictly to do with accessing the database. If there were requests to third-party services then a cache lock is fitting – with cache lock, we can prevent race conditions for possibly any code block.
</details>

## Note on AI Assistance
<details>
  <summary>Click to expand</summary>

I'm going to shed some light on the concurrency test in `EventControllerTest.php`.

I reckon I spent at least 2 hours in total tinkering with various approaches – `Concurrency::run`, `Http::pool`; all to no avail.
I even had the server running with testing environment whilst the test executes network requests to the server – this didn't feel maintainable and wouldn't be ideal for running in CI/CD pipelines.

I resorted to Gemini for help because I felt having a test for a significant part of the reservation process is crucial even though as I
have experienced now how DIFFICULT testing concurrency is!

For my own curiosity, I asked Gemini how it could look like if we were to have a test with 3 concurrent requests – it didn't look pretty.

Prompt used:
```md
Define AI: an esteemed software engineer with tremendous expertise in PHP, Laravel, Pest and concurrency.

My question to you: in @app/Http/Controllers/EventController.php, I have reserve method for creating event reservations
The content of the method is wrapped by a DB transaction and then retrieval of Event contains lockForUpdate.
How can I write a test in @tests/Feature/Controllers/EventControllerTest.php to validate that concurrency is respected e.g. two requests happening, first one has 10 tickets and 10 tickets are reserved and event considered sold out, second request sees HTTP 410
```

As Gemini was trying to come up with suggestions, I can see it even experimented with `Concurrency::run` which was unsuccessful – it ran into the same error as I did which has to do with serialisation.

Gemini's justification for why the test works:
* DatabaseMigrations: Unlike RefreshDatabase, this trait doesn't wrap the test in a DB::beginTransaction(). The $event is
  physically inserted into your MySQL database, making it visible to the child process.
* pcntl_fork(): Creates a true parallel process. The Parent and Child hit the reserve method at roughly the same time.
* lockForUpdate(): When the first process enters the transaction in EventController.php, it locks the event row. The second
  process will hit the same line and block (wait) until the first process finishes and commits.
* Validation: Once the first process commits (10 tickets taken), the second process wakes up, re-calculates $available_tickets,
  sees 0, and correctly returns the 410 Gone status.
</details>

## Flow
<details>
  <summary>Click to expand</summary>

- Retrieve event details
  - API: `GET /api/events/{id}`
    - When event is found, responds with JSON object containing `title`, `description`, `total_tickets`, `reserved_tickets`, `sold_tickets` and `available_tickets` as the properties, and with status HTTP 200 OK
    - When event isn't found, responds with HTTP 404 Not Found
- Reserve X number of tickets
  - API: `POST /api/events/{id}/reserve`
    - When tickets are available, responds with JSON object containing `reservation_id` as the sole property and with status HTTP 201 Created
    - When event isn't found, responds with HTTP 404 Not Found
    - When tickets are unavailable, responds with HTTP 410 Gone
- Confirm the reservation
  - API: `POST /api/reservations/{id}/confirm`
    - When confirmation is successful, responds with HTTP 204 No Content
    - When confirmation is unsuccessful, responds with HTTP 400 Bad Request
  - This is to simulate successful payment and the reserved tickets have been sold
- Cancel the reservation
  - API: `DELETE /api/reservations/{id}`
    - When reservation is found and deleted, responds with HTTP 204 No Content
    - When reservation is found but already confirmed, responds with HTTP 405 Method Not Allowed
    - When reservation isn't found, responds with HTTP 404 Not Found
  - It isn't specified in the technical assessment PDF whether cancelling the reservation is possible for both _on-hold_ and _confirmed_ statuses
  - From recollecting whatever I've booked online, hotel and restaurant reservations are usually cancellable whilst event tickets e.g. MPO aren't and they're usually non-refundable
  - For my submission, I'll make it so that only _on-hold_ reservations can be cancelled
  - When the reservation is cancelled, the tickets are made available again
- Automatically expire the reservation when left _on-hold_ for more than 5 minutes
  - A background job should be scheduled for execution 5 minutes after the reservation record was created
  - The background job would trigger cancellation of the reservation, making the tickets available again

</details>

## Ideas for extending the service
### Pick-me-up emails for events with available tickets
<details>
    <summary>Click to expand</summary>

Adding `expired` and `cancelled` statuses to Reservation opens the door to pick-me-up emails we could send to the user should the event still have available tickets.

> _1 week left until The Music of a-ha concert_
> 
> _sees the user has a cancelled reservation for The Music of a-ha with 2 tickets_
> 
> JOHN DOE! Do you wish to see the orchestra perform the music of a-ha? We can see you were intrigued at one point but you didn't proceed with your reservation. 😢 Here, have a 10% discount.

With these statuses in mind, we would have to replace the deletion of the reservation record with:
1. Setting status to `expired` in the background job
2. Setting status to `cancelled` when user wants to cancel – in the process, converts delete API to cancel API

</details>

### Venue
<details>
    <summary>Click to expand</summary>

This opens up LOTS of possibilities:
- Browse events by venue e.g. events at DFP, events at Hin Bus Depot, events at The Campus
- Seating
    - The venue would have its default seating layout
    - It's significant that we give the event the ability to "minimise" the seating – perhaps the venue has 10 rows of seats but for this occasion, the 2 rows closest to the stage will be taken up by performers thus the event's organiser doesn't want to show those rows
    - Users can select their seats. We can also tease them at the end of the year if W42 is the seat they selected the most
- Analysis
    - Which performing arts centres had the most events in the first full year after the pandemic lockdowns?
    - Which parks held events during the pandemic?
    - Which users are the most frequent visitors of the DFP?

</details>

### Categorisation
<details>
    <summary>Click to expand</summary>

Is this an orchestra? Is this sports? Is this a workshop?

Categorisation would provide the following:
- More specific searchers for users
    - I want orchestra!
    - I want pottery workshops!
- Analysis
    - Which sport had the most tickets sold over the past year?
    - How did each type of performing arts perform (unpunintended) in the last few weeks?
</details>

### Grouping events
<details>
    <summary>Click to expand</summary>

This would be something which requires radical changes.

As it is, events are standalone.

What if the MPO want to perform all Pink Floyd singles split across 8 concerts throughout the month?

How could they generate more visibility on each concert under the same campaign? Folks go on the platform and think – _wow, the MPO will be performing the popular tunes of Pink Floyd? There will be eight of them!_

Each concert with the same marketing material. Instant visibility of different _occasions_ for the same event.

There are arguments that an occasion is a kind of event whereas an event is anything that is happening thus an event having many occasions sounds fitting.
</details>

### Ticket types
<details>
  <summary>Click to expand</summary>

There could be event organisers wanting to add touches of grandeur to certain seats.

An event could have various ticket types where each type has its own availability (and pricing).
</details>
