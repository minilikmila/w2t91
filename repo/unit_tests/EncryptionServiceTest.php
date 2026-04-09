<?php

namespace Tests\Unit;

use App\Services\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EncryptionService();
    }

    // --- Email Masking ---

    public function test_mask_email_standard(): void
    {
        $this->assertEquals('jo***@example.com', $this->service->maskEmail('john.doe@example.com'));
    }

    public function test_mask_email_short_local(): void
    {
        $this->assertEquals('a***@example.com', $this->service->maskEmail('a@example.com'));
    }

    public function test_mask_email_null_returns_null(): void
    {
        $this->assertNull($this->service->maskEmail(null));
    }

    public function test_mask_email_empty_returns_null(): void
    {
        $this->assertNull($this->service->maskEmail(''));
    }

    public function test_mask_email_no_at_sign(): void
    {
        $this->assertEquals('***', $this->service->maskEmail('invalid'));
    }

    // --- Phone Masking ---

    public function test_mask_phone_standard(): void
    {
        $this->assertEquals('***4567', $this->service->maskPhone('+15551234567'));
    }

    public function test_mask_phone_short(): void
    {
        $this->assertEquals('1234', $this->service->maskPhone('1234'));
    }

    public function test_mask_phone_null_returns_null(): void
    {
        $this->assertNull($this->service->maskPhone(null));
    }

    public function test_mask_phone_empty_returns_null(): void
    {
        $this->assertNull($this->service->maskPhone(''));
    }

    // --- Generic Masking ---

    public function test_mask_generic_standard(): void
    {
        $this->assertEquals('J********h', $this->service->maskGeneric('John Smith'));
    }

    public function test_mask_generic_short(): void
    {
        $this->assertEquals('**', $this->service->maskGeneric('AB'));
    }

    public function test_mask_generic_null_returns_null(): void
    {
        $this->assertNull($this->service->maskGeneric(null));
    }

    // --- Redaction ---

    public function test_redact_returns_redacted(): void
    {
        $this->assertEquals('[REDACTED]', $this->service->redact('sensitive data'));
    }

    public function test_redact_null_returns_redacted(): void
    {
        $this->assertEquals('[REDACTED]', $this->service->redact(null));
    }
}
