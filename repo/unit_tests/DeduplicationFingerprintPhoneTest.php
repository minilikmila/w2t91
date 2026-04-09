<?php

namespace Tests\Unit;

use App\Services\DataNormalizationService;
use App\Services\DeduplicationService;
use PHPUnit\Framework\TestCase;

class DeduplicationFingerprintPhoneTest extends TestCase
{
    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeduplicationService(new DataNormalizationService());
    }

    public function test_fingerprint_includes_phone_last4(): void
    {
        $withPhone = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '+15551234567',
        ];

        $withoutPhone = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
        ];

        $fp1 = $this->service->generateFingerprint($withPhone);
        $fp2 = $this->service->generateFingerprint($withoutPhone);

        $this->assertNotEquals($fp1, $fp2, 'Fingerprint should differ when phone is present vs absent.');
    }

    public function test_same_last4_produces_same_fingerprint(): void
    {
        $data1 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '+15551234567',
        ];

        $data2 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '+44 999 123 4567',
        ];

        $fp1 = $this->service->generateFingerprint($data1);
        $fp2 = $this->service->generateFingerprint($data2);

        $this->assertEquals($fp1, $fp2, 'Same last-4 digits should produce same fingerprint.');
    }

    public function test_different_last4_produces_different_fingerprint(): void
    {
        $data1 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '+15551234567',
        ];

        $data2 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '+15551239999',
        ];

        $fp1 = $this->service->generateFingerprint($data1);
        $fp2 = $this->service->generateFingerprint($data2);

        $this->assertNotEquals($fp1, $fp2, 'Different last-4 digits should produce different fingerprints.');
    }

    public function test_phone_normalization_in_fingerprint(): void
    {
        $data1 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '(555) 123-4567',
        ];

        $data2 = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'phone' => '555.123.4567',
        ];

        $fp1 = $this->service->generateFingerprint($data1);
        $fp2 = $this->service->generateFingerprint($data2);

        $this->assertEquals($fp1, $fp2, 'Different phone formats with same digits should produce same fingerprint.');
    }
}
