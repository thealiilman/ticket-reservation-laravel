<?php

namespace App\Jobs;

use App\Models\Reservation;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireReservation implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reservation_id)
    {
        
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $reservation = Reservation::lockForUpdate()->find($this->reservation_id);
            if ($reservation) {
                $reservation->delete();
            }
        });
    }
}
