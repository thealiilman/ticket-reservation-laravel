# ticket-reservation-laravel
This Laravel application is my submission for CJ's take-home technical assessment.

The objective is to build a ticket reservation service within a 4-6 hour period where the main focus – as I understand it – is to get a functioning application which respects the expected flow whilst considering ideas one can apply to extend the service.
## Database design
<img width="631" height="252" alt="Screenshot 2026-05-03 at 12 15 06" src="https://github.com/user-attachments/assets/fd93ce2a-7881-4cf1-82f8-83fbf463bc02" />

## Flow
<details>
  <summary>Click to expand</summary>

- Retrieve event details
  - API: `GET /events/{id}`
    - When event is found, responds with JSON object containing `title`, `description`, `total_tickets`, `reserved_tickets`, `sold_tickets` and `available_tickets` as the properties, and with status HTTP 200 OK
    - When event isn't found, responds with HTTP 404 Not Found
- Reserve X number of tickets
  - API: `POST /events/{id}/reserve`
    - When tickets are available, responds with JSON object containing `reservation_id` as the sole property and with status HTTP 201 Created
    - When event isn't found, responds with HTTP 404 Not Found
    - When tickets are unavailable, responds with HTTP 410 Gone
- Confirm the reservation
  - API: `POST /reservations/{id}/confirm`
    - When confirmation is successful, responds with HTTP 204 No Content
    - When confirmation is unsuccessful, responds with HTTP 400 Bad Request
  - This is to simulate successful payment and the reserved tickets have been sold
- Cancel the reservation
  - API: `DELETE /reservations/{id}`
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
To be filled.
