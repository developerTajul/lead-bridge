<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\TrackableType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the TrackableType value object.
 */
final class TrackableTypeTest extends TestCase
{
    public function test_from_string_keeps_the_morph_string(): void
    {
        $trackableType = TrackableType::fromString('App\\Models\\Lead');

        $this->assertSame('App\\Models\\Lead', $trackableType->toString());
    }

    #[DataProvider('blankValues')]
    public function test_from_string_rejects_a_blank_value(string $value): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity must reference a trackable type.');

        TrackableType::fromString($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankValues(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
        ];
    }

    public function test_from_string_accepts_every_allowed_record_type(): void
    {
        foreach (TrackableType::allowedTypes() as $allowedType) {
            $this->assertSame($allowedType, TrackableType::fromString($allowedType)->toString());
        }
    }

    /**
     * Without an allowlist any class name from request input would be accepted
     * as a morph target.
     */
    #[DataProvider('disallowedValues')]
    public function test_from_string_rejects_a_type_outside_the_allowlist(string $value): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('Unsupported trackable type');

        TrackableType::fromString($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disallowedValues(): array
    {
        return [
            'arbitrary class' => ['App\\Models\\Invoice'],
            'framework class' => ['Illuminate\\Support\\Facades\\DB'],
            'plain string' => ['lead'],
            'leading space' => [' App\\Models\\Lead'],
        ];
    }

    public function test_equals_compares_the_underlying_value(): void
    {
        $lead = TrackableType::fromString('App\\Models\\Lead');

        $this->assertTrue($lead->equals(TrackableType::fromString('App\\Models\\Lead')));
        $this->assertFalse($lead->equals(TrackableType::fromString('App\\Models\\Deal')));
    }
}
