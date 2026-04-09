<?php

namespace Tests\Unit;

use App\Http\Middleware\LogRequestResponse;
use Tests\TestCase;

class LogRedactionTest extends TestCase
{
    public function test_sensitive_fields_are_redacted(): void
    {
        $middleware = new LogRequestResponse();

        $params = [
            'username' => 'testuser',
            'password' => 'secret123',
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'ssn' => '123-45-6789',
            'national_id' => 'ABC123',
            'address' => '123 Main St',
            'date_of_birth' => '1990-01-01',
            'guardian_contact' => '+9876543210',
            'guardian_name' => 'Jane Doe',
            'api_token' => 'some-token-object',
            'authorization' => 'Bearer token',
            'status' => 'active',
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('sanitizeParams');
        $method->setAccessible(true);

        $sanitized = $method->invoke($middleware, $params);

        // Sensitive fields should be redacted
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['email']);
        $this->assertEquals('[REDACTED]', $sanitized['first_name']);
        $this->assertEquals('[REDACTED]', $sanitized['last_name']);
        $this->assertEquals('[REDACTED]', $sanitized['phone']);
        $this->assertEquals('[REDACTED]', $sanitized['ssn']);
        $this->assertEquals('[REDACTED]', $sanitized['national_id']);
        $this->assertEquals('[REDACTED]', $sanitized['address']);
        $this->assertEquals('[REDACTED]', $sanitized['date_of_birth']);
        $this->assertEquals('[REDACTED]', $sanitized['guardian_contact']);
        $this->assertEquals('[REDACTED]', $sanitized['guardian_name']);
        $this->assertEquals('[REDACTED]', $sanitized['api_token']);
        $this->assertEquals('[REDACTED]', $sanitized['authorization']);

        // Non-sensitive fields should be preserved
        $this->assertEquals('testuser', $sanitized['username']);
        $this->assertEquals('active', $sanitized['status']);
    }

    public function test_nested_sensitive_fields_are_redacted(): void
    {
        $middleware = new LogRequestResponse();

        $params = [
            'learner' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'status' => 'active',
            ],
        ];

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('sanitizeParams');
        $method->setAccessible(true);

        $sanitized = $method->invoke($middleware, $params);

        $this->assertEquals('[REDACTED]', $sanitized['learner']['first_name']);
        $this->assertEquals('[REDACTED]', $sanitized['learner']['last_name']);
        $this->assertEquals('[REDACTED]', $sanitized['learner']['email']);
        $this->assertEquals('active', $sanitized['learner']['status']);
    }

    public function test_api_token_not_in_request_input(): void
    {
        // Verify that AuthenticateToken uses attributes, not merge
        $middleware = new \App\Http\Middleware\AuthenticateToken();
        $reflection = new \ReflectionClass($middleware);
        $source = file_get_contents($reflection->getFileName());

        // The middleware should use $request->attributes->set, not $request->merge
        $this->assertStringContainsString('$request->attributes->set(\'api_token\'', $source);
        $this->assertStringNotContainsString('$request->merge([\'api_token\'', $source);
    }

    public function test_object_values_are_redacted(): void
    {
        $middleware = new LogRequestResponse();

        // Simulate an object (like an Eloquent model) in params
        $mockObject = new \stdClass();
        $mockObject->token = 'secret-hash';
        $mockObject->user_id = 1;

        $params = [
            'resource_id' => 5,
            'some_object' => $mockObject,
            'status' => 'active',
        ];

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('sanitizeParams');
        $method->setAccessible(true);

        $sanitized = $method->invoke($middleware, $params);

        // Object values should be redacted, not serialized
        $this->assertEquals('[OBJECT REDACTED]', $sanitized['some_object']);
        // Scalar values should pass through
        $this->assertEquals(5, $sanitized['resource_id']);
        $this->assertEquals('active', $sanitized['status']);
    }

    public function test_null_values_pass_through(): void
    {
        $middleware = new LogRequestResponse();

        $params = [
            'notes' => null,
            'resource_id' => 5,
        ];

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('sanitizeParams');
        $method->setAccessible(true);

        $sanitized = $method->invoke($middleware, $params);

        $this->assertNull($sanitized['notes']);
        $this->assertEquals(5, $sanitized['resource_id']);
    }
}
