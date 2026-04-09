<?php

namespace Tests\Unit;

use App\Services\DataNormalizationService;
use App\Services\DeduplicationService;
use PHPUnit\Framework\TestCase;

class DeduplicationTest extends TestCase
{
    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $normalizer = new DataNormalizationService();
        $this->service = new DeduplicationService($normalizer);
    }

    public function test_generate_fingerprint_deterministic(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
        ];

        $fp1 = $this->service->generateFingerprint($data);
        $fp2 = $this->service->generateFingerprint($data);

        $this->assertNotEmpty($fp1);
        $this->assertEquals($fp1, $fp2);
    }

    public function test_generate_fingerprint_case_insensitive(): void
    {
        $data1 = ['first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '1990-01-15'];
        $data2 = ['first_name' => 'JOHN', 'last_name' => 'DOE', 'date_of_birth' => '1990-01-15'];

        $this->assertEquals(
            $this->service->generateFingerprint($data1),
            $this->service->generateFingerprint($data2)
        );
    }

    public function test_generate_fingerprint_different_data(): void
    {
        $data1 = ['first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '1990-01-15'];
        $data2 = ['first_name' => 'Jane', 'last_name' => 'Doe', 'date_of_birth' => '1990-01-15'];

        $this->assertNotEquals(
            $this->service->generateFingerprint($data1),
            $this->service->generateFingerprint($data2)
        );
    }

    public function test_generate_identifier_fingerprint_email(): void
    {
        $fp1 = $this->service->generateIdentifierFingerprint('email', 'John@Example.COM');
        $fp2 = $this->service->generateIdentifierFingerprint('email', 'john@example.com');

        $this->assertEquals($fp1, $fp2);
    }

    public function test_generate_identifier_fingerprint_phone(): void
    {
        $fp1 = $this->service->generateIdentifierFingerprint('phone', '+1 (555) 123-4567');
        $fp2 = $this->service->generateIdentifierFingerprint('phone', '+15551234567');

        $this->assertEquals($fp1, $fp2);
    }

    public function test_generate_identifier_fingerprint_different_types(): void
    {
        $fp1 = $this->service->generateIdentifierFingerprint('email', 'test@example.com');
        $fp2 = $this->service->generateIdentifierFingerprint('phone', 'test@example.com');

        $this->assertNotEquals($fp1, $fp2);
    }

    public function test_fingerprint_is_sha256(): void
    {
        $fp = $this->service->generateFingerprint([
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->assertEquals(64, strlen($fp));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fp);
    }

    public function test_resolve_duplicate_invalid_resolution_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->resolveDuplicate(999, 'invalid_status');
    }
}
