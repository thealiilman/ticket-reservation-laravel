<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ReservationStatus;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'status',
        'number_of_tickets',
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    #[Scope]
    public function on_hold(Builder $query) {
        return $query->where('status', ReservationStatus::OnHold);
    }

    #[Scope]
    public function confirmed(Builder $query) {
        return $query->where('status', ReservationStatus::Confirmed);
    }
}
