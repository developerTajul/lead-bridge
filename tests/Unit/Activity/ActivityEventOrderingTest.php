<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Verifies that domain events travel from the entity to the dispatcher only
 * after the transaction has committed.
 */
final class ActivityEventOrderingTest extends TestCase
{
    /** @var array<int, string> */
    private array $log = [];

    public function test_events_are_dispatched_after_the_commit(): void
    {
        $service = $this->makeService();

        $result = $service->create(new ActivityCreateDTO(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            ['begin', 'repository.save', 'commit', 'dispatch:ActivityCreatedEvent'],
            $this->log,
        );
    }

    public function test_a_throwing_listener_does_not_roll_back_a_committed_create(): void
    {
        $service = $this->makeService(listenerThrows: true);

        $result = $service->create(new ActivityCreateDTO(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
        ));

        // The write already committed, so the caller must still see success.
        $this->assertTrue($result->isSuccess());
        $this->assertNotContains('rollback', $this->log);
        $this->assertContains('commit', $this->log);
    }

    private function makeService(bool $listenerThrows = false): ActivityService
    {
        $repository = new class($this->log) implements ActivityRepositoryContract, ActivityQueryContract {
            /** @param array<int, string> $log */
            public function __construct(private array &$log) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                $this->log[] = 'repository.save';

                return $this->makeEntity();
            }

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                // The write paths read the saved row back to build the response.
                return new ActivityProjectionDTO(entity: $this->makeEntity());
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                return null;
            }

            public function delete(ActivityEntity $activity): void {}

            private function makeEntity(): ActivityEntity
            {
                return ActivityEntity::reconstitute(
                    id: 5,
                    trackableType: 'lead',
                    trackableId: 42,
                    type: ActivityType::CALL,
                    subject: 'Discovery call',
                    userId: 7,
                );
            }
        };

        $transactionManager = new class($this->log) implements TransactionManagerContract {
            /** @param array<int, string> $log */
            public function __construct(private array &$log) {}

            public function beginTransaction(): void
            {
                $this->log[] = 'begin';
            }

            public function commit(): void
            {
                $this->log[] = 'commit';
            }

            public function rollback(): void
            {
                $this->log[] = 'rollback';
            }

            public function run(callable $callback): mixed
            {
                return $callback();
            }
        };

        $eventDispatcher = new class($this->log, $listenerThrows) implements EventDispatcherContract {
            /** @param array<int, string> $log */
            public function __construct(
                private array &$log,
                private bool $throws,
            ) {}

            public function dispatch(object $event): void
            {
                $this->log[] = 'dispatch:' . class_basename($event);

                if ($this->throws) {
                    throw new RuntimeException('Listener blew up.');
                }
            }
        };

        return new ActivityService(
            activityRepository: $repository,
            activityQuery: $repository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $transactionManager,
            logger: $this->createMock(LoggerInterface::class),
            eventDispatcher: $eventDispatcher,
        );
    }
}
