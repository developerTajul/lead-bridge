<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\Mappers\ActivityMapper;
use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the list filter's strict conversion and its page-size bound.
 */
final class ActivityFilterValidationTest extends TestCase
{
    private ActivityMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ActivityMapper();
    }

    public function test_the_mapper_converts_every_query_value_to_its_enum(): void
    {
        $dto = $this->mapper->mapToFilterDTO([
            'status' => 'pending',
            'type' => 'call',
            'user_id' => '7',
            'filter' => 'today',
            'per_page' => '25',
        ]);

        $this->assertSame(ActivityStatus::PENDING, $dto->status);
        $this->assertSame(ActivityType::CALL, $dto->type);
        $this->assertSame(7, $dto->userId);
        $this->assertSame(ActivityPeriod::TODAY, $dto->filter);
        $this->assertSame(25, $dto->perPage);
    }

    public function test_the_mapper_treats_blank_values_as_absent(): void
    {
        $dto = $this->mapper->mapToFilterDTO([
            'status' => '',
            'type' => '',
            'user_id' => '',
            'filter' => '',
        ]);

        $this->assertNull($dto->status);
        $this->assertNull($dto->type);
        $this->assertNull($dto->userId);
        $this->assertNull($dto->filter);
    }

    public function test_the_mapper_applies_the_default_page_size_when_none_is_given(): void
    {
        $dto = $this->mapper->mapToFilterDTO([]);

        $this->assertSame(15, $dto->perPage);
    }

    /**
     * A rejected filter is bad request input, not a broken domain invariant,
     * so it must not surface as a domain exception.
     */
    #[DataProvider('unrecognisedValues')]
    public function test_the_mapper_rejects_an_unrecognised_value(
        string $key,
        mixed $value,
        string $wireField,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('for filter [%s]', $wireField));

        $this->mapper->mapToFilterDTO([$key => $value]);
    }

    /**
     * Each case pairs the query key with the wire field name the exception reports.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function unrecognisedValues(): array
    {
        return [
            'unknown status' => ['status', 'archived', 'status'],
            'unknown type' => ['type', 'sms', 'type'],
            'unknown period' => ['filter', 'tomorrow', 'filter'],
            'non-numeric user' => ['user_id', 'someone', 'user_id'],
            'zero user' => ['user_id', '0', 'user_id'],
            'array status' => ['status', ['pending'], 'status'],
        ];
    }

    public function test_an_invalid_per_page_is_rejected_rather_than_coerced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('for filter [per_page]');

        $this->mapper->mapToFilterDTO(['per_page' => 'lots']);
    }

    public function test_the_lenient_path_keeps_the_values_it_can_convert(): void
    {
        [$dto, $ignored] = $this->mapper->mapToFilterDTOLenient([
            'status' => 'pending',
            'type' => 'call',
            'user_id' => '7',
            'filter' => 'today',
            'per_page' => '25',
        ]);

        $this->assertSame(ActivityStatus::PENDING, $dto->status);
        $this->assertSame(ActivityType::CALL, $dto->type);
        $this->assertSame(7, $dto->userId);
        $this->assertSame(ActivityPeriod::TODAY, $dto->filter);
        $this->assertSame(25, $dto->perPage);
        $this->assertSame([], $ignored);
    }

    public function test_the_lenient_path_drops_an_unrecognised_value_and_reports_it(): void
    {
        [$dto, $ignored] = $this->mapper->mapToFilterDTOLenient(['status' => 'archived']);

        $this->assertNull($dto->status);
        $this->assertSame(['status'], $ignored);
    }

    public function test_the_lenient_path_reports_every_field_it_dropped(): void
    {
        [$dto, $ignored] = $this->mapper->mapToFilterDTOLenient([
            'status' => 'archived',
            'type' => 'sms',
            'user_id' => 'someone',
            'filter' => 'tomorrow',
            'per_page' => 'lots',
        ]);

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
        [$dto, $ignored] = $this->mapper->mapToFilterDTOLenient([]);

        $this->assertNull($dto->status);
        $this->assertSame(15, $dto->perPage);
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
