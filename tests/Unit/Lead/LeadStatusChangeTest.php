<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\Mappers\LeadResponseMapper;
use Core\Lead\Application\Services\LeadService;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Shared\Application\Contracts\QueueManagerContract;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the lead status change use case.
 */
final class LeadStatusChangeTest extends TestCase
{
    private function makeLead(LeadStatus $status): LeadEntity
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

    /**
     * @return LeadRepositoryContract&MockObject
     */
    private function makeRepository(?LeadEntity $lead): LeadRepositoryContract
    {
        $repository = $this->createMock(LeadRepositoryContract::class);
        $repository->method('findEntityById')->willReturn($lead);

        return $repository;
    }

    private function makeService(LeadRepositoryContract $repository): LeadService
    {
        return new LeadService(
            $repository,
            $this->createMock(LeadQueryContract::class),
            new LeadResponseMapper(),
            $this->createMock(QueueManagerContract::class),
        );
    }

    public function test_it_moves_the_lead_and_saves_the_new_status(): void
    {
        $repository = $this->makeRepository($this->makeLead(LeadStatus::NEW));
        $repository->expects($this->once())
            ->method('saveStatus')
            ->with($this->callback(fn(LeadEntity $lead) => $lead->getStatus() === LeadStatus::CONTACTED));

        $result = $this->makeService($repository)->changeStatus(7, LeadStatus::CONTACTED);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Lead status updated successfully.', $result->message);
    }

    public function test_it_returns_the_lead_on_success(): void
    {
        $repository = $this->makeRepository($this->makeLead(LeadStatus::NEW));

        $result = $this->makeService($repository)->changeStatus(7, LeadStatus::CONTACTED);

        $this->assertInstanceOf(LeadEntity::class, $result->getData());
        $this->assertSame(LeadStatus::CONTACTED, $result->getData()->getStatus());
    }

    public function test_it_returns_a_not_found_failure_when_the_lead_is_missing(): void
    {
        $repository = $this->makeRepository(null);
        $repository->expects($this->never())->method('saveStatus');

        $result = $this->makeService($repository)->changeStatus(404, LeadStatus::CONTACTED);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_NOT_FOUND', $result->errorCode);
    }

    /**
     * A rejected transition is a rule failure, not a crash, so it must come
     * back as a failure Result.
     */
    public function test_it_fails_when_the_domain_rejects_the_transition(): void
    {
        $repository = $this->makeRepository($this->makeLead(LeadStatus::CONTACTED));
        $repository->expects($this->never())->method('saveStatus');

        $result = $this->makeService($repository)->changeStatus(7, LeadStatus::CONVERTED);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_INVALID', $result->errorCode);
    }

    /**
     * A rejected transition must leave the stored status untouched.
     */
    public function test_it_does_not_persist_a_rejected_transition(): void
    {
        $lead = $this->makeLead(LeadStatus::CONTACTED);
        $repository = $this->makeRepository($lead);

        $this->makeService($repository)->changeStatus(7, LeadStatus::CONVERTED);

        $this->assertSame(LeadStatus::CONTACTED, $lead->getStatus());
    }
}
