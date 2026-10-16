<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers a write that commits but cannot be read back. The row is already
 * durable by then, so the caller must still see success, the events must still
 * be dispatched, and the transaction must not be rolled back.
 */
final class ActivityReadBackResilienceTest extends TestCase
{
    /** @var array<int, object> */
    private array $dispatched = [];

    /** @var array<int, string> */
    private array $log = [];

    private bool $rolledBack = false;

    private bool $committed = false;

    private ?ActivityEntity $entityAtCommit = null;

    public function test_create_falls_back_to_the_saved_entity_when_the_read_back_returns_nothing(): void
    {
        $result = $this->makeService(readBack: 'empty')->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Activity created successfully.', $result->getMessage());
    }

    public function test_create_falls_back_to_the_saved_entity_when_the_read_back_throws(): void
    {
        $result = $this->makeService(readBack: 'throws')->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
    }

    public function test_update_falls_back_to_the_saved_entity_when_the_read_back_returns_nothing(): void
    {
        $result = $this->makeService(readBack: 'empty')
            ->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Activity updated successfully.', $result->getMessage());
    }

    public function test_update_falls_back_to_the_saved_entity_when_the_read_back_throws(): void
    {
        $result = $this->makeService(readBack: 'throws')
            ->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));

        $this->assertTrue($result->isSuccess());
    }

    /**
     * The response has to be usable, not just successful: a retry triggered by
     * a failure result would duplicate the row that is already committed.
     */
    public function test_the_fallback_response_carries_the_saved_entity_state(): void
    {
        $result = $this->makeService(readBack: 'empty')->create($this->makeCreateDTO());

        /** @var ActivityResponseDTO $response */
        $response = $result->getData();

        $this->assertInstanceOf(ActivityResponseDTO::class, $response);
        $this->assertSame(7, $response->id);
        $this->assertSame(7, $response->user_id);
        $this->assertSame('lead', $response->trackable_type);
        $this->assertSame(42, $response->trackable_id);
        $this->assertSame('call', $response->type);
        $this->assertSame('Discovery call', $response->subject);
        $this->assertSame('Intro call with the prospect', $response->description);
        $this->assertSame('2026-10-05 09:30:00', $response->due_date);
        $this->assertNull($response->completed_at);
        // The entity carries neither, so they stay null rather than being invented.
        $this->assertNull($response->created_at);
        $this->assertNull($response->trackable);
    }

    /**
     * A read-back failure happens after the commit, so a listener that never
     * hears about the row is the failure the fallback exists to prevent.
     */
    public function test_events_are_dispatched_even_when_the_read_back_fails(): void
    {
        $this->makeService(readBack: 'throws')->create($this->makeCreateDTO());

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ActivityCreatedEvent::class, $this->dispatched[0]);
    }

    public function test_update_dispatches_its_events_even_when_the_read_back_fails(): void
    {
        $this->makeService(readBack: 'empty', recordOnLock: true)
            ->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));

        $this->assertCount(1, $this->dispatched);
    }

    public function test_a_failed_read_back_does_not_roll_back_a_committed_create(): void
    {
        $this->makeService(readBack: 'throws')->create($this->makeCreateDTO());

        $this->assertTrue($this->committed);
        $this->assertFalse($this->rolledBack, 'rollback() must not follow a successful commit().');
    }

    public function test_a_failed_read_back_does_not_roll_back_a_committed_update(): void
    {
        $this->makeService(readBack: 'throws')
            ->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));

        $this->assertTrue($this->committed);
        $this->assertFalse($this->rolledBack, 'rollback() must not follow a successful commit().');
    }

    public function test_a_failed_read_back_is_logged(): void
    {
        $this->makeService(readBack: 'empty')->create($this->makeCreateDTO());

        $this->assertContains('error', $this->log, 'A silent fallback would hide a broken read side.');
    }

    /**
     * The events are owned by the service once drained, so the entity the
     * repository returned cannot still be holding them.
     */
    public function test_the_saved_entity_is_drained_before_the_commit(): void
    {
        $this->makeService(readBack: 'empty')->create($this->makeCreateDTO());

        $this->assertNotNull($this->entityAtCommit, 'The save never ran.');
        $this->assertSame(
            [],
            $this->entityAtCommit->releaseEvents(),
            'The entity must be drained so a committed event cannot be stranded.',
        );
    }

    /**
     * @param string $readBack 'ok' returns a projection, 'empty' returns null,
     *                         'throws' raises from the read side.
     * @param bool   $recordOnSave Record a created event on the entity handed to save().
     * @param bool   $recordOnLock Record an event on the entity the lock read returns.
     */
    private function makeService(
        string $readBack = 'ok',
        bool $recordOnSave = false,
        bool $recordOnLock = false,
    ): ActivityService {
        $dispatched = &$this->dispatched;
        $log = &$this->log;
        $rolledBack = &$this->rolledBack;
        $committed = &$this->committed;
        $entityAtCommit = &$this->entityAtCommit;

        $repository = new class(
            $readBack,
            $recordOnSave,
            $recordOnLock,
            $dispatched,
            $log,
            $rolledBack,
            $committed,
            $entityAtCommit,
        ) implements ActivityRepositoryContract, ActivityQueryContract {
            /**
             * @param array<int, object> $dispatched
             * @param array<int, string> $log
             * @param ?ActivityEntity    $entityAtCommit
             */
            public function __construct(
                private readonly string $readBack,
                private readonly bool $recordOnSave,
                private readonly bool $recordOnLock,
                private array &$dispatched,
                private array &$log,
                private bool &$rolledBack,
                private bool &$committed,
                private ?ActivityEntity &$entityAtCommit,
            ) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                if ($this->recordOnSave) {
                    $activity->recordCreated();
                }

                $saved = $this->hydrated();

                $this->entityAtCommit = $saved;

                return $saved;
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                $entity = $this->hydrated(id: $id);

                if ($this->recordOnLock) {
                    $entity->recordDeleted();
                }

                return $entity;
            }

            public function delete(ActivityEntity $activity): void {}

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                if ($this->readBack === 'throws') {
                    throw new RuntimeException('The read side is unavailable.');
                }

                if ($this->readBack === 'empty') {
                    return null;
                }

                return new ActivityProjectionDTO(
                    entity: $this->hydrated(id: $id),
                    createdAt: new \DateTimeImmutable('2026-10-01 08:00:00'),
                    updatedAt: new \DateTimeImmutable('2026-10-01 08:00:00'),
                );
            }

            private function hydrated(int $id = 7): ActivityEntity
            {
                return ActivityEntity::reconstitute(
                    id: $id,
                    trackableType: 'lead',
                    trackableId: 42,
                    type: ActivityType::CALL,
                    subject: 'Discovery call',
                    userId: 7,
                    description: 'Intro call with the prospect',
                    dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
                );
            }
        };

        $transactionManager = new class($committed, $rolledBack) implements TransactionManagerContract {
            public function __construct(
                private bool &$committed,
                private bool &$rolledBack,
            ) {}

            public function beginTransaction(): void {}

            public function commit(): void
            {
                $this->committed = true;
            }

            public function rollback(): void
            {
                $this->rolledBack = true;
            }

            public function run(callable $callback): mixed
            {
                return $callback();
            }
        };

        $logger = new class($log) implements LoggerInterface {
            /** @param array<int, string> $log */
            public function __construct(private array &$log) {}

            public function emergency(string|\Stringable $message, array $context = []): void { $this->log[] = 'emergency'; }
            public function alert(string|\Stringable $message, array $context = []): void { $this->log[] = 'alert'; }
            public function critical(string|\Stringable $message, array $context = []): void { $this->log[] = 'critical'; }
            public function error(string|\Stringable $message, array $context = []): void { $this->log[] = 'error'; }
            public function warning(string|\Stringable $message, array $context = []): void { $this->log[] = 'warning'; }
            public function notice(string|\Stringable $message, array $context = []): void { $this->log[] = 'notice'; }
            public function info(string|\Stringable $message, array $context = []): void { $this->log[] = 'info'; }
            public function debug(string|\Stringable $message, array $context = []): void { $this->log[] = 'debug'; }
            public function log($level, string|\Stringable $message, array $context = []): void { $this->log[] = (string) $level; }
        };

        $eventDispatcher = new class($dispatched) implements EventDispatcherContract {
            /** @param array<int, object> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $event): void
            {
                $this->dispatched[] = $event;
            }
        };

        return new ActivityService(
            activityRepository: $repository,
            activityQuery: $repository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $transactionManager,
            logger: $logger,
            eventDispatcher: $eventDispatcher,
        );
    }

    private function makeCreateDTO(): ActivityCreateDTO
    {
        return new ActivityCreateDTO(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
        );
    }
}
