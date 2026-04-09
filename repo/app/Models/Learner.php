<?php

namespace App\Models;

use App\Models\Traits\AuditsTimestamps;
use App\Models\Traits\EncryptsPii;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Learner extends Model
{
    use HasFactory, SoftDeletes, EncryptsPii, AuditsTimestamps;

    protected array $encryptedFields = [
        'email',
        'phone',
        'guardian_contact',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'search_email',
        'search_phone',
        'gender',
        'nationality',
        'language',
        'address',
        'guardian_name',
        'guardian_contact',
        'status',
        'fingerprint',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::saving(function (Learner $learner) {
            // Populate normalized searchable columns before encryption runs
            if ($learner->isDirty('email') && $learner->email) {
                $learner->search_email = strtolower(trim($learner->email));
            }
            if ($learner->isDirty('phone') && $learner->phone) {
                $learner->search_phone = preg_replace('/\D/', '', $learner->phone);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'metadata' => 'array',
        ];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(LearnerIdentifier::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function exerciseAttempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function isMinor(): bool
    {
        if (!$this->date_of_birth) {
            return false;
        }

        return $this->date_of_birth->age < 18;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
