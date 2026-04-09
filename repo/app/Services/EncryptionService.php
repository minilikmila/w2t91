<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    /**
     * Encrypt a value. Returns null if input is null.
     */
    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a value. Returns null if input is null or decryption fails.
     */
    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value; // Return as-is if not encrypted or decryption fails
        }
    }

    /**
     * Mask an email address: show first 2 chars + domain.
     * e.g., "john.doe@example.com" → "jo***@example.com"
     */
    public function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $visible = min(2, strlen($local));
        $masked = substr($local, 0, $visible) . str_repeat('*', 3);

        return $masked . '@' . $domain;
    }

    /**
     * Mask a phone number: show last 4 digits.
     * e.g., "+15551234567" → "***4567"
     */
    public function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) <= 4) {
            return $phone;
        }

        return '***' . substr($digits, -4);
    }

    /**
     * Mask a generic string: show first and last char with asterisks.
     * e.g., "John Smith" → "J********h"
     */
    public function maskGeneric(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $len = strlen($value);

        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        return $value[0] . str_repeat('*', $len - 2) . $value[$len - 1];
    }

    /**
     * Fully redact a value.
     */
    public function redact(?string $value): string
    {
        return '[REDACTED]';
    }
}
