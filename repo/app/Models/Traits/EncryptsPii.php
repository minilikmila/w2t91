<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptsPii
{
    /**
     * Get the list of fields that should be encrypted at rest.
     * Override in model to specify fields.
     */
    protected function getEncryptedFields(): array
    {
        return $this->encryptedFields ?? [];
    }

    public static function bootEncryptsPii(): void
    {
        static::saving(function ($model) {
            foreach ($model->getEncryptedFields() as $field) {
                if ($model->isDirty($field) && $model->{$field} !== null) {
                    $value = $model->{$field};
                    // Only encrypt if not already encrypted
                    if (!str_starts_with($value, 'eyJ')) {
                        $model->{$field} = Crypt::encryptString($value);
                    }
                }
            }
        });
    }

    /**
     * Get a decrypted attribute value.
     */
    public function decryptField(string $field): ?string
    {
        $value = $this->attributes[$field] ?? null;

        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }

    /**
     * Get all PII fields decrypted.
     */
    public function getDecryptedPii(): array
    {
        $data = [];
        foreach ($this->getEncryptedFields() as $field) {
            $data[$field] = $this->decryptField($field);
        }
        return $data;
    }
}
