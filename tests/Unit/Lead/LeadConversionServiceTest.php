<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Application\Services\LeadConversionService;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Events\LeadConverted;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the lead conversion use case with every port mocked.
 */
final class LeadConversionServiceTest extends TestCase
{
    private function makeLead(LeadStatus $status = LeadStatus::QUALIFIED): LeadEntity
    {
        return new LeadEntity(
            id: 7,
            name: 'Acme Prospect',
            email: 'prospect@acme.test',
            phone: '555-0100',
            companyName: 'Acme Ltd',
            status: $status,
            source: 'referral',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }

    private function makeService(
        LeadRepositoryContract $repository,
        ?EventDispatcherContract $dispatcher = null,
    ): LeadConversionService {
        $transaction = $this->createMock(TransactionManagerContract::class);
        $transaction->method('run')->willReturnCallback(fn(callable $cb) => $cb());

        return new LeadConversionService(
            leadRepository: $repository,
            transactionManager: $transaction,
            eventDispatcher: $dispatcher ?? $this->createMock(EventDispatcherContract::class),
            logger: $this->createMock(LoggerInterface::class),
        );
    }

    public function test_it_converts_a_qualified_lead_and_saves_it(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn($this->makeLead());

        $repository->expects($this->once())
            ->method('saveStatus')
            ->with($this->callback(
                fn(LeadEntity $lead) => $lead->getStatus() === LeadStatus::CONVERTED
            ));

        $result = $this->makeService($repository)->convert(7);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('converted', $result->getData()->status);
        $this->assertSame('Converted', $result->getData()->status_label);
        $this->assertSame(7, $result->getData()->id);
    }

    public function test_it_dispatches_the_converted_event_after_commit(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn($this->makeLead());

        $dispatcher = $this->createMock(EventDispatcherContract::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $event) {
                $this->assertInstanceOf(LeadConverted::class, $event);
                $this->assertSame(7, $event->leadId);
                $this->assertSame('Acme Prospect', $event->name);
                $this->assertSame('Acme Ltd', $event->companyName);
                $this->assertSame('referral', $event->source);

                return true;
            }));

        $result = $this->makeService($repository, $dispatcher)->convert(7);

        $this->assertTrue($result->isSuccess());
    }

    public function test_it_fails_when_the_lead_does_not_exist(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn(null);

        // Nothing may be written or announced for a lead that is not there.
        $repository->expects($this->never())->method('saveStatus');

        $dispatcher = $this->createMock(EventDispatcherContract::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $result = $this->makeService($repository, $dispatcher)->convert(7);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_NOT_FOUND', $result->errorCode);
    }

    public function test_it_refuses_a_lead_that_is_not_qualified_and_rolls_back(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn($this->makeLead(LeadStatus::NEW));

        $repository->expects($this->never())->method('saveStatus');

        $transaction = $this->createMock(TransactionManagerContract::class);
        $transaction->expects($this->once())->method('beginTransaction');
        $transaction->expects($this->once())->method('rollback');
        $transaction->expects($this->never())->method('commit');

        $dispatcher = $this->createMock(EventDispatcherContract::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $service = new LeadConversionService(
            leadRepository: $repository,
            transactionManager: $transaction,
            eventDispatcher: $dispatcher,
            logger: $this->createMock(LoggerInterface::class),
        );

        $result = $service->convert(7);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_INVALID', $result->errorCode);
    }

    public function test_it_reports_the_moment_of_conversion_not_the_stale_row_timestamp(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        // The entity carries the timestamp the row had before the write; the
        // response must not echo that back as the conversion time.
        $repository->method('findEntityById')->willReturn($this->makeLead());

        $before = new \DateTimeImmutable();

        $result = $this->makeService($repository)->convert(7);

        $reported = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $result->getData()->converted_at);

        $this->assertNotSame('2026-10-05 10:13:16', $result->getData()->converted_at);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $reported->getTimestamp());
    }

    public function test_a_failing_listener_does_not_fail_the_conversion(): void
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn($this->makeLead());

        $dispatcher = $this->createMock(EventDispatcherContract::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('listener blew up'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new LeadConversionService(
            leadRepository: $repository,
            transactionManager: $this->createMock(TransactionManagerContract::class),
            eventDispatcher: $dispatcher,
            logger: $logger,
        );

        $result = $service->convert(7);

        // The write committed, so a broken listener must not report a failure.
        $this->assertTrue($result->isSuccess());
        $this->assertSame('converted', $result->getData()->status);
    }
}
