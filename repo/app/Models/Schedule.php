<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'date',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'capacity_per_slot',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slot_duration_minutes' => 'integer',
            'capacity_per_slot' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Generate available time slots for this schedule.
     */
    public function getSlots(): array
    {
        $slots = [];
        $start = strtotime($this->date->format('Y-m-d') . ' ' . $this->start_time);
        $end = strtotime($this->date->format('Y-m-d') . ' ' . $this->end_time);
        $duration = $this->slot_duration_minutes * 60;

        while ($start + $duration <= $end) {
            $slots[] = [
                'start' => date('Y-m-d H:i:s', $start),
                'end' => date('Y-m-d H:i:s', $start + $duration),
                'capacity' => $this->capacity_per_slot,
            ];
            $start += $duration;
        }

        return $slots;
    }
}
