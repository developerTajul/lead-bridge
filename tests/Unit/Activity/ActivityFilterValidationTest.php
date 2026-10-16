<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the list filter's strict conversion and its page-size bound.
 */
final class ActivityFilterValidationTest extends TestCase
{
    public function test_from_request_converts_every_value_to_its_enum(): void
    {
        $dto = ActivityFilterDTO::fromRequest(
            status: 'pending',
            type: 'call',
            userId: '7',
            filter: 'today',
            perPage: '25',
        );

        $this->assertSame(ActivityStatus::PENDING, $dto->status);
        $this->assertSame(ActivityType::CALL, $dto->type);
        $this->assertSame(7, $dto->userId);
        $this->assertSame(ActivityPeriod::TODAY, $dto->filter);
        $this->assertSame(25, $dto->perPage);
    }

    public function test_from_request_treats_blank_values_as_absent(): void
    {
        $dto = ActivityFilterDTO::fromRequest(
            status: '',
            type: '',
            userId: '',
            filter: '',
        );

        $this->assertNull($dto->status);
        $this->assertNull($dto->type);
        $this->assertNull($dto->userId);
        $this->assertNull($dto->filter);
    }

    #[DataProvider('unrecognisedValues')]
    public function test_from_request_rejects_an_unrecognised_value(
        string $argument,
        mixed $value,
        string $wireField,
    ): void {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage(sprintf('for filter [%s]', $wireField));

        ActivityFilterDTO::fromRequest(...[$argument => $value]);
    }

    /**
     * Each case pairs the constructor argument with the wire field name the
     * exception reports.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function unrecognisedValues(): array
    {
        return [
            'unknown status' => ['status', 'archived', 'status'],
            'unknown type' => ['type', 'sms', 'type'],
            'unknown period' => ['filter', 'tomorrow', 'filter'],
            'non-numeric user' => ['userId', 'someone', 'user_id'],
            'zero user' => ['userId', '0', 'user_id'],
        ];
    }

    public function test_an_invalid_per_page_is_rejected_rather_than_coerced(): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('for filter [per_page]');

        ActivityFilterDTO::fromRequest(perPage: 'lots');
    }

    public function test_the_lenient_path_keeps_the_values_it_can_convert(): void
    {
        [$dto, $ignored] = ActivityFilterDTO::fromRequestLenient(
            status: 'pending',
            type: 'call',
            userId: '7',
            filter: 'today',
            perPage: '25',
        );

        $this->assertSame(ActivityStatus::PENDING, $dto->status);
        $this->assertSame(ActivityType::CALL, $dto->type);
        $this->assertSame(7, $dto->userId);
        $this->assertSame(ActivityPeriod::TODAY, $dto->filter);
        $this->assertSame(25, $dto->perPage);
        $this->assertSame([], $ignored);
    }

    public function test_the_lenient_path_drops_an_unrecognised_value_and_reports_it(): void
    {
        [$dto, $ignored] = ActivityFilterDTO::fromRequestLenient(status: 'archived');

        $this->assertNull($dto->status);
        $this->assertSame(['status'], $ignored);
    }

    public function test_the_lenient_path_reports_every_field_it_dropped(): void
    {
        [$dto, $ignored] = ActivityFilterDTO::fromRequestLenient(
            status: 'archived',
            type: 'sms',
            userId: 'someone',
            filter: 'tomorrow',
            perPage: 'lots',
        );

        // A dropped page size falls back to the default rather than going null.
        $this->assertSame(15, $dto->perPage);
        $this->assertNull($dto->status);
        $this->assertNull($dto->type);
        $this->assertNull($dto->userId);
        $this->assertNull($dto->filter);
        $this->assertSame(['status', 'type', 'user_id', 'filter', 'per_page'], $ignored);
    }

    public function test_the_lenient_path_reports_nothing_for_absent_values(): void
    {
        [$dto, $ignored] = ActivityFilterDTO::fromRequestLenient();

        $this->assertNull($dto->status);
        $this->assertSame([], $ignored);
    }

    public function test_the_domain_filter_holds_the_enums(): void
    {
        $filter = new ActivityFilter(
            status: ActivityStatus::COMPLETED,
            type: ActivityType::MEETING,
            userId: 3,
            period: ActivityPeriod::OVERDUE,
            perPage: 50,
        );

        $this->assertSame(ActivityStatus::COMPLETED, $filter->status);
        $this->assertSame(ActivityType::MEETING, $filter->type);
        $this->assertSame(ActivityPeriod::OVERDUE, $filter->period);
    }

    public function test_the_domain_filter_accepts_the_upper_page_size_bound(): void
    {
        $filter = new ActivityFilter(perPage: ActivityFilter::MAX_PER_PAGE);

        $this->assertSame(100, $filter->perPage);
    }

    #[DataProvider('outOfRangePageSizes')]
    public function test_the_domain_filter_rejects_a_page_size_outside_its_bounds(int $perPage): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('Page size must be between 1 and 100');

        new ActivityFilter(perPage: $perPage);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfRangePageSizes(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'above the bound' => [101],
            'far above the bound' => [5000],
        ];
    }
}
