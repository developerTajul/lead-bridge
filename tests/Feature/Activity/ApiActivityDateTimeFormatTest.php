<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature cover for the wire format of the activity datetimes. The API used to
 * answer due_date and completed_at as "Y-m-d H:i:s" while created_at and
 * updated_at came back as ISO-8601, which forced a client to branch per field.
 * These tests pin all four to one parseable format.
 */
final class ApiActivityDateTimeFormatTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindReadSide();
    }

    /**
     * The datetime fields the API exposes, paired with the value the stub row
     * carries, so an assertion can name the field it is talking about.
     *
     * @return array<string, array{string}>
     */
    public static function datetimeFields(): array
    {
        return [
            'due_date' => ['due_date'],
            'completed_at' => ['completed_at'],
            'created_at' => ['created_at'],
            'updated_at' => ['updated_at'],
        ];
    }

    #[DataProvider('datetimeFields')]
    public function test_every_datetime_field_is_iso_8601(string $field): void
    {
        $response = $this->getJson('/api/v1/activities/1');

        $response->assertOk();

        $value = (string) $response->json("data.{$field}");

        $this->assertNotSame('', $value, "data.{$field} came back empty.");

        // Constrained ISO-8601: a date, a "T" separator, a wall clock, and a
        // mandatory offset. The old "Y-m-d H:i:s" carried a space and no offset,
        // so it fails this pattern on both counts.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',
            $value,
            "data.{$field} is not ISO-8601; got [{$value}].",
        );

        // Parseable at all is the property a client actually depends on.
        $this->assertNotFalse(
            strtotime($value),
            "data.{$field} could not be parsed back as a date: [{$value}].",
        );
    }

    /**
     * The four fields must agree with each other, not merely each be parseable
     * on its own: a client picks one parse strategy for the whole payload.
     */
    public function test_all_datetime_fields_share_one_format(): void
    {
        $response = $this->getJson('/api/v1/activities/1');

        $response->assertOk();

        $formats = [];

        foreach (array_keys(self::datetimeFields()) as $field) {
            $formats[$field] = $this->shapeOf((string) $response->json("data.{$field}"));
        }

        $this->assertCount(
            1,
            array_unique($formats),
            'The response mixes datetime formats: ' . json_encode($formats),
        );
    }

    /**
     * A nullable datetime must stay absent-by-null rather than being formatted
     * into an epoch or a placeholder string.
     */
    public function test_a_null_completion_time_stays_null(): void
    {
        $this->bindReadSide(withCompletion: false);

        $response = $this->getJson('/api/v1/activities/1');

        $response->assertOk();
        $this->assertNull($response->json('data.completed_at'));
    }

    /**
     * The instant must survive the format change: ATOM carries an offset, so a
     * client sees the same moment the row holds, not a shifted one.
     */
    public function test_the_datetime_offset_keeps_the_stored_instant(): void
    {
        $response = $this->getJson('/api/v1/activities/1');

        $response->assertOk();

        $dueDate = new DateTimeImmutable((string) $response->json('data.due_date'));

        $this->assertSame(
            '2026-10-05 09:30:00',
            $dueDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Reduces a datetime string to its shape, so two fields can be compared for
     * format without requiring them to hold the same instant.
     *
     * @param string $value
     *
     * @return string
     */
    private function shapeOf(string $value): string
    {
        return preg_replace('/\d/', '9', $value) ?? $value;
    }

    private function bindReadSide(bool $withCompletion = true): void
    {
        $entity = ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new DateTimeImmutable('2026-10-05 09:30:00', new DateTimeZone('UTC')),
            completedAt: $withCompletion
                ? new DateTimeImmutable('2026-10-04 08:15:00', new DateTimeZone('UTC'))
                : null,
        );

        $this->app->instance(ActivityQueryContract::class, new class($entity) implements ActivityQueryContract {
            public function __construct(private readonly ActivityEntity $entity) {}

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                return new ActivityProjectionDTO(
                    entity: $this->entity,
                    createdAt: new DateTimeImmutable('2026-10-01 08:00:00', new DateTimeZone('UTC')),
                    updatedAt: new DateTimeImmutable('2026-10-02 09:15:00', new DateTimeZone('UTC')),
                );
            }
        });

        $this->app->instance(ActivityRepositoryContract::class, new class($entity) implements ActivityRepositoryContract {
            public function __construct(private readonly ActivityEntity $entity) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                return $activity;
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                return $this->entity;
            }

            public function delete(ActivityEntity $activity): void {}

            public function clearLockedRecord(int $id): void {}
        });

        // Only the read side is exercised here, so the transaction manager is
        // stubbed rather than opening a real connection.
        $this->app->instance(
            TransactionManagerContract::class,
            $this->createMock(TransactionManagerContract::class),
        );
    }
}
