<?php

namespace Tests\Unit;

use App\Services\DataNormalizationService;
use PHPUnit\Framework\TestCase;

class DataNormalizationTest extends TestCase
{
    private DataNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataNormalizationService();
    }

    // --- Date Normalization ---

    public function test_normalize_date_iso_format(): void
    {
        $this->assertEquals('2024-03-15', $this->service->normalizeDate('2024-03-15'));
    }

    public function test_normalize_date_us_format(): void
    {
        $this->assertEquals('2024-03-15', $this->service->normalizeDate('03/15/2024'));
    }

    public function test_normalize_date_european_format(): void
    {
        $this->assertEquals('2024-03-15', $this->service->normalizeDate('15-03-2024'));
    }

    public function test_normalize_date_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeDate(null));
    }

    public function test_normalize_date_empty_returns_null(): void
    {
        $this->assertNull($this->service->normalizeDate(''));
    }

    public function test_normalize_date_compact_format(): void
    {
        $this->assertEquals('2024-03-15', $this->service->normalizeDate('20240315'));
    }

    // --- Phone Normalization ---

    public function test_normalize_phone_with_country_code(): void
    {
        $this->assertEquals('+15551234567', $this->service->normalizePhone('+1 (555) 123-4567'));
    }

    public function test_normalize_phone_dots(): void
    {
        $this->assertEquals('5551234567', $this->service->normalizePhone('555.123.4567'));
    }

    public function test_normalize_phone_spaces(): void
    {
        $this->assertEquals('+445551234567', $this->service->normalizePhone('+44 555 123 4567'));
    }

    public function test_normalize_phone_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizePhone(null));
    }

    public function test_normalize_phone_empty_returns_null(): void
    {
        $this->assertNull($this->service->normalizePhone(''));
    }

    // --- Currency Normalization ---

    public function test_normalize_currency_with_symbol(): void
    {
        $this->assertEquals('1234.56', $this->service->normalizeCurrency('$1,234.56'));
    }

    public function test_normalize_currency_european(): void
    {
        $this->assertEquals('1234.56', $this->service->normalizeCurrency('1.234,56'));
    }

    public function test_normalize_currency_comma_decimal(): void
    {
        $this->assertEquals('123.45', $this->service->normalizeCurrency('123,45'));
    }

    public function test_normalize_currency_plain_number(): void
    {
        $this->assertEquals('99.00', $this->service->normalizeCurrency('99'));
    }

    public function test_normalize_currency_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeCurrency(null));
    }

    // --- Coordinate Normalization ---

    public function test_normalize_latitude_valid(): void
    {
        $this->assertEquals(40.7128, $this->service->normalizeCoordinate('40.7128', 'latitude'));
    }

    public function test_normalize_latitude_with_symbols(): void
    {
        $result = $this->service->normalizeCoordinate('40.7128° N', 'latitude');
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(40.7128, $result, 0.001);
    }

    public function test_normalize_latitude_out_of_range(): void
    {
        $this->assertNull($this->service->normalizeCoordinate('95.0', 'latitude'));
    }

    public function test_normalize_longitude_valid(): void
    {
        $this->assertEquals(-74.006, $this->service->normalizeCoordinate('-74.0060', 'longitude'));
    }

    public function test_normalize_longitude_out_of_range(): void
    {
        $this->assertNull($this->service->normalizeCoordinate('200.0', 'longitude'));
    }

    // --- Rating Normalization ---

    public function test_normalize_rating_fraction(): void
    {
        $this->assertEquals(4.0, $this->service->normalizeRating('4/5'));
    }

    public function test_normalize_rating_fraction_scaled(): void
    {
        $this->assertEquals(4.0, $this->service->normalizeRating('8/10'));
    }

    public function test_normalize_rating_plain(): void
    {
        $this->assertEquals(3.5, $this->service->normalizeRating('3.5'));
    }

    public function test_normalize_rating_clamped_to_max(): void
    {
        $this->assertEquals(5.0, $this->service->normalizeRating('7', 0, 5));
    }

    public function test_normalize_rating_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeRating(null));
    }

    // --- Email Normalization ---

    public function test_normalize_email_trims_and_lowercases(): void
    {
        $this->assertEquals('user@example.com', $this->service->normalizeEmail('  User@EXAMPLE.COM  '));
    }

    public function test_normalize_email_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeEmail(null));
    }

    // --- Name Normalization ---

    public function test_normalize_name_title_case(): void
    {
        $this->assertEquals('John Doe', $this->service->normalizeName('john DOE'));
    }

    public function test_normalize_name_collapses_spaces(): void
    {
        $this->assertEquals('John Doe', $this->service->normalizeName('  john   DOE  '));
    }

    public function test_normalize_name_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeName(null));
    }

    // --- Gender Normalization ---

    public function test_normalize_gender_m(): void
    {
        $this->assertEquals('male', $this->service->normalizeGender('M'));
    }

    public function test_normalize_gender_female(): void
    {
        $this->assertEquals('female', $this->service->normalizeGender('Female'));
    }

    public function test_normalize_gender_nonbinary(): void
    {
        $this->assertEquals('other', $this->service->normalizeGender('non-binary'));
    }

    public function test_normalize_gender_null_returns_null(): void
    {
        $this->assertNull($this->service->normalizeGender(null));
    }

    // --- Learner Row Normalization ---

    public function test_normalize_learner_row(): void
    {
        $row = [
            'first_name' => 'john',
            'last_name' => 'DOE',
            'email' => '  John@Example.COM ',
            'phone' => '+1 (555) 123-4567',
            'date_of_birth' => '03/15/1990',
            'gender' => 'M',
        ];

        $normalized = $this->service->normalizeLearnerRow($row);

        $this->assertEquals('John', $normalized['first_name']);
        $this->assertEquals('Doe', $normalized['last_name']);
        $this->assertEquals('john@example.com', $normalized['email']);
        $this->assertEquals('+15551234567', $normalized['phone']);
        $this->assertEquals('1990-03-15', $normalized['date_of_birth']);
        $this->assertEquals('male', $normalized['gender']);
    }
}
