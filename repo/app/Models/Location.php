<?php

namespace App\Models;

use App\Models\Traits\AuditsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes, AuditsTimestamps;

    protected $fillable = [
        'name',
        'type',
        'description',
        'address',
        'display_address',
        'latitude',
        'longitude',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
