<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'version_number',
        'waypoints',
        'prior_values',
        'created_by',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'waypoints' => 'array',
            'prior_values' => 'array',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
