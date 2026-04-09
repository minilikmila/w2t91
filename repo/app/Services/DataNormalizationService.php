<?php

namespace App\Services;

use Carbon\Carbon;

class DataNormalizationService
{
    /**
     * Normalize a date string to Y-m-d format.
     * Supports: Y-m-d, m/d/Y, d-m-Y, d/m/Y, Y/m/d, M d Y, d M Y, etc.
     */
    public function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $formats = [
            'Y-m-d',
            'm/d/Y',
            'd-m-Y',
            'd/m/Y',
            'Y/m/d',
            'M d, Y',
            'M d Y',
            'd M Y',
            'd-M-Y',
            'F d, Y',
            'F d Y',
            'd F Y',
            'Ymd',
            'm-d-Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception) {
                continue;
            }
        }

        // Fallback: let Carbon try to parse it
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Normalize a phone number by stripping non-digit characters except leading +.
     * Returns E.164-like format.
     */
    public function normalizePhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $hasPlus = str_starts_with($value, '+');

        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return ($hasPlus ? '+' : '') . $digits;
    }

    /**
     * Normalize a currency amount to a decimal string with 2 decimal places.
     * Strips currency symbols and thousand separators.
     */
    public function normalizeCurrency(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Remove currency symbols and whitespace
        $value = preg_replace('/[^\d.,\-]/', '', $value);

        // Handle European format (1.234,56 → 1234.56)
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        // Handle comma as thousands separator (1,234.56 → 1234.56)
        elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $value)) {
            $value = str_replace(',', '', $value);
        }
        // Handle comma as decimal separator (123,45 → 123.45)
        elseif (preg_match('/^\d+(,\d{1,2})$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Normalize latitude/longitude coordinates.
     * Returns float rounded to 7 decimal places, or null if invalid.
     */
    public function normalizeCoordinate(?string $value, string $type = 'latitude'): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Remove degree symbols and directional letters
        $value = preg_replace('/[°\'\"NSEW\s]/', '', $value);

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if ($type === 'latitude' && ($float < -90 || $float > 90)) {
            return null;
        }

        if ($type === 'longitude' && ($float < -180 || $float > 180)) {
            return null;
        }

        return round($float, 7);
    }

    /**
     * Normalize a rating value to a float within the given range.
     */
    public function normalizeRating(?string $value, float $min = 0, float $max = 5): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Handle "X/Y" format (e.g., "4/5", "8/10")
        if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $value, $matches)) {
            $numerator = (float) $matches[1];
            $denominator = (float) $matches[2];

            if ($denominator == 0) {
                return null;
            }

            // Scale to target range
            $value = ($numerator / $denominator) * $max;
            return round(min(max($value, $min), $max), 2);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return round(min(max($float, $min), $max), 2);
    }

    /**
     * Normalize an email address: trim, lowercase.
     */
    public function normalizeEmail(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
    }

    /**
     * Normalize a name: trim, title case.
     */
    public function normalizeName(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normalize a gender value to a standard set.
     */
    public function normalizeGender(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtolower(trim($value));

        $map = [
            'm' => 'male',
            'f' => 'female',
            'male' => 'male',
            'female' => 'female',
            'man' => 'male',
            'woman' => 'female',
            'other' => 'other',
            'non-binary' => 'other',
            'nonbinary' => 'other',
            'prefer not to say' => 'prefer_not_to_say',
            'prefer_not_to_say' => 'prefer_not_to_say',
            'n/a' => 'prefer_not_to_say',
        ];

        return $map[$value] ?? 'other';
    }

    /**
     * Apply all relevant normalizations to a learner data row.
     */
    public function normalizeLearnerRow(array $row): array
    {
        if (isset($row['first_name'])) {
            $row['first_name'] = $this->normalizeName($row['first_name']);
        }

        if (isset($row['last_name'])) {
            $row['last_name'] = $this->normalizeName($row['last_name']);
        }

        if (isset($row['email'])) {
            $row['email'] = $this->normalizeEmail($row['email']);
        }

        if (isset($row['phone'])) {
            $row['phone'] = $this->normalizePhone($row['phone']);
        }

        if (isset($row['date_of_birth'])) {
            $row['date_of_birth'] = $this->normalizeDate($row['date_of_birth']);
        }

        if (isset($row['gender'])) {
            $row['gender'] = $this->normalizeGender($row['gender']);
        }

        if (isset($row['guardian_name'])) {
            $row['guardian_name'] = $this->normalizeName($row['guardian_name']);
        }

        if (isset($row['guardian_contact'])) {
            $row['guardian_contact'] = $this->normalizePhone($row['guardian_contact']);
        }

        return $row;
    }
}
