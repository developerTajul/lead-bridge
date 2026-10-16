<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Infrastructure\Mappers\ActivityEntityMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers datetime persistence and rehydration. Datetimes are written as UTC
 * wall-clock strings, so reading them back must produce the same instant no
 * matter what the application timezone is set to.
 */
final class ActivityDateTimeRoundTripTest extends TestCase
{
    private string $originalTimezone = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    /**
     * The stored strings are what ActivityRepository::toPersistence() writes.
     */
    #[DataProvider('applicationTimezones')]
    public function test_a_stored_utc_datetime_reads_back_as_the_same_instant(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $entity = (new ActivityEntityMapper())->toEntity($this->modelData());

        $this->assertSame(
            '2026-10-05 09:30:00',
            $entity->getDueDate()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
        $this->assertSame('UTC', $entity->getDueDate()?->getTimezone()->getName());
        $this->assertSame(
            '2026-10-02 11:00:00',
            $entity->getCompletedAt()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }

    /**
     * The regression this guards: parsing the stored string in the application
     * timezone makes toPersistence() re-anchor the instant, so each update
     * shifts the stored value by the UTC offset.
     */
    #[DataProvider('applicationTimezones')]
    public function test_rehydrating_and_re_persisting_does_not_shift_the_instant(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $entity = (new ActivityEntityMapper())->toEntity($this->modelData());

        // Mirrors ActivityRepository::toPersistence().
        $rewritten = $entity->getDueDate()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $this->assertSame('2026-10-05 09:30:00', $rewritten);
    }

    #[DataProvider('applicationTimezones')]
    public function test_the_instant_survives_a_repeated_round_trip(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $mapper = new ActivityEntityMapper();
        $data = $this->modelData();
        $instant = null;

        // Three cycles: a shifting parse would drift the value every time.
        for ($cycle = 0; $cycle < 3; $cycle++) {
            $entity = $mapper->toEntity($data);

            $data['due_date'] = $entity->getDueDate()
                ?->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');

            $instant = $entity->getDueDate()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        $this->assertSame('2026-10-05 09:30:00', $instant);
    }

    /**
     * A nil column stays null rather than becoming the epoch or "now".
     */
    #[DataProvider('applicationTimezones')]
    public function test_a_missing_datetime_stays_null(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $data = $this->modelData();
        $data['due_date'] = null;
        $data['completed_at'] = null;

        $entity = (new ActivityEntityMapper())->toEntity($data);

        $this->assertNull($entity->getDueDate());
        $this->assertNull($entity->getCompletedAt());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function applicationTimezones(): array
    {
        return [
            'utc' => ['UTC'],
            'dhaka' => ['Asia/Dhaka'],
            'new york' => ['America/New_York'],
            'kathmandu' => ['Asia/Kathmandu'],
        ];
    }

    /**
     * A row as the model hands it over, with the datetimes Eloquent cast.
     *
     * @return array<string, mixed>
     */
    private function modelData(): array
    {
        return [
            'id' => 1,
            'user_id' => 7,
            'trackable_type' => 'lead',
            'trackable_id' => 42,
            'type' => ActivityType::CALL->value,
            'subject' => 'Discovery call',
            'description' => 'Intro call with the prospect',
            'due_date' => '2026-10-05 09:30:00',
            'completed_at' => '2026-10-02 11:00:00',
            'created_at' => '2026-10-01 08:00:00',
            'updated_at' => '2026-10-01 08:00:00',
        ];
    }
}
