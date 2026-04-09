<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/';

    public function test_11_char_password_rejected(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PASSWORD_PATTERN, 'Abcdefgh1!x');
    }

    public function test_12_char_password_accepted(): void
    {
        $this->assertMatchesRegularExpression(self::PASSWORD_PATTERN, 'Abcdefgh1!xy');
    }

    public function test_12_char_no_uppercase_rejected(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PASSWORD_PATTERN, 'abcdefgh1!xy');
    }

    public function test_12_char_no_lowercase_rejected(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PASSWORD_PATTERN, 'ABCDEFGH1!XY');
    }

    public function test_12_char_no_digit_rejected(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PASSWORD_PATTERN, 'Abcdefgh!!xy');
    }

    public function test_12_char_no_special_rejected(): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PASSWORD_PATTERN, 'Abcdefgh12xy');
    }

    public function test_long_valid_password_accepted(): void
    {
        $this->assertMatchesRegularExpression(self::PASSWORD_PATTERN, 'SecureP@ssw0rd123!');
    }
}
