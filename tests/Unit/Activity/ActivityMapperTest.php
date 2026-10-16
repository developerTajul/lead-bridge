<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\Mappers\ActivityMapper;
use Core\Activity\Domain\Enums\ActivityType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the date parsing done at the request boundary.
 */
final class ActivityMapperTest extends TestCase
{
    private ActivityMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ActivityMapper();
    }

    public function test_create_dto_parses_dates_into_immutable_objects(): void
    {
        $dto = $this->mapper->mapToCreateDTO($this->createPayload());

        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->dueDate);
        $this->assertSame('2026-10-05 09:30:00', $dto->dueDate->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->completedAt);
        $this->assertSame('2026-10-04 08:15:00', $dto->completedAt->format('Y-m-d H:i:s'));
    }

    public function test_create_dto_leaves_missing_dates_null(): void
    {
        $payload = $this->createPayload();
        unset($payload['due_date'], $payload['completed_at']);

        $dto = $this->mapper->mapToCreateDTO($payload);

        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->completedAt);
    }

    public function test_create_dto_treats_an_empty_date_string_as_null(): void
    {
        $dto = $this->mapper->mapToCreateDTO($this->createPayload([
            'due_date' => '',
            'completed_at' => '',
        ]));

        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->completedAt);
    }

    public function test_create_dto_rejects_an_unparseable_due_date(): void
    {
        // A bad date is request input, so it fails as a handled bad request
        // rather than escaping as a raw date-parsing error.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('for field [due_date]');

        $this->mapper->mapToCreateDTO($this->createPayload(['due_date' => 'not-a-date']));
    }

    public function test_create_dto_rejects_an_unknown_type_rather_than_raising_a_value_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('for field [type]');

        $this->mapper->mapToCreateDTO($this->createPayload(['type' => 'sms']));
    }

    public function test_update_dto_parses_dates_into_immutable_objects(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, [
            'due_date' => '2026-12-01 10:00:00',
            'completed_at' => '2026-10-04 08:15:00',
        ]);

        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->dueDate);
        $this->assertSame('2026-12-01 10:00:00', $dto->dueDate->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->completedAt);
    }

    public function test_update_dto_leaves_missing_dates_null(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, ['subject' => 'Only subject']);

        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->completedAt);
        $this->assertFalse($dto->reopen);
    }

    public function test_update_dto_rejects_an_unparseable_completion_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('for field [completed_at]');

        $this->mapper->mapToUpdateDTO(1, ['completed_at' => 'not-a-date']);
    }

    public function test_update_dto_rejects_an_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('for field [type]');

        $this->mapper->mapToUpdateDTO(1, ['type' => 'sms']);
    }

    public function test_update_dto_maps_the_reopen_flag(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, ['reopen' => true]);

        $this->assertTrue($dto->reopen);
    }

    /**
     * Regression: a plain (bool) cast reads the string "false" as true, which
     * silently reopened activities.
     */
    #[DataProvider('reopenWireValues')]
    public function test_update_dto_reads_the_reopen_flag_as_a_real_boolean(
        mixed $wireValue,
        bool $expected,
    ): void {
        $dto = $this->mapper->mapToUpdateDTO(1, ['reopen' => $wireValue]);

        $this->assertSame($expected, $dto->reopen);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function reopenWireValues(): array
    {
        return [
            'string false' => ['false', false],
            'string true' => ['true', true],
            'string zero' => ['0', false],
            'string one' => ['1', true],
            'boolean false' => [false, false],
            'boolean true' => [true, true],
            'integer zero' => [0, false],
            'integer one' => [1, true],
        ];
    }

    public function test_update_dto_maps_the_type_enum(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, ['type' => 'meeting']);

        $this->assertSame(ActivityType::MEETING, $dto->type);
    }

    /**
     * A null field normally means "leave unchanged", so an explicit clear has
     * to be carried as its own intent.
     */
    #[DataProvider('blankWireValues')]
    public function test_update_dto_reads_a_supplied_blank_value_as_a_clear(mixed $wireValue): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, [
            'due_date' => $wireValue,
            'description' => $wireValue,
        ]);

        $this->assertTrue($dto->clearDueDate);
        $this->assertTrue($dto->clearDescription);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->description);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankWireValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    public function test_update_dto_reads_an_absent_key_as_leave_unchanged(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, ['subject' => 'Only subject']);

        $this->assertFalse($dto->clearDueDate);
        $this->assertFalse($dto->clearDescription);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->description);
    }

    public function test_update_dto_does_not_clear_a_field_that_carries_a_value(): void
    {
        $dto = $this->mapper->mapToUpdateDTO(1, [
            'due_date' => '2026-12-01 10:00:00',
            'description' => 'Still here',
        ]);

        $this->assertFalse($dto->clearDueDate);
        $this->assertFalse($dto->clearDescription);
        $this->assertSame('2026-12-01 10:00:00', $dto->dueDate?->format('Y-m-d H:i:s'));
        $this->assertSame('Still here', $dto->description);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'trackable_type' => 'App\\Models\\Lead',
            'trackable_id' => 42,
            'type' => 'call',
            'subject' => 'Discovery call',
            'user_id' => 7,
            'description' => 'Intro call with the prospect',
            'due_date' => '2026-10-05 09:30:00',
            'completed_at' => '2026-10-04 08:15:00',
        ], $overrides);
    }
}
