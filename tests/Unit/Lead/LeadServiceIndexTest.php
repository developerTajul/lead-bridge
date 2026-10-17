<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\DTOs\Requests\LeadFilterDTO;
use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Application\Mappers\LeadResponseMapper;
use Core\Lead\Application\Services\LeadService;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Shared\Application\Contracts\QueueManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers the lead list use case with every port mocked.
 */
final class LeadServiceIndexTest extends TestCase
{
    public function test_it_returns_a_paginated_result_of_response_dtos(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredLeads')
            ->willReturn(new PaginatedResult(
                items: [$this->makeProjection(1, 'Acme Lead')],
                total: 1,
                currentPage: 1,
                perPage: 15,
                lastPage: 1,
            ));

        $result = $this->makeService($query)->getLeads(new LeadFilterDTO());

        $this->assertTrue($result->isSuccess());

        $paginatedLeads = $result->getData();

        $this->assertInstanceOf(PaginatedResult::class, $paginatedLeads);
        $this->assertSame(1, $paginatedLeads->total);
        // The Service must hand the view response DTOs, never domain entities.
        $this->assertSame(
            'Core\Lead\Application\DTOs\Responses\LeadResponseDTO',
            $paginatedLeads->items[0]::class,
        );
        $this->assertSame('Acme Lead', $paginatedLeads->items[0]->name);
        $this->assertSame('New', $paginatedLeads->items[0]->status_label);
    }

    public function test_it_maps_the_filter_dto_onto_the_domain_filter(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredLeads')
            ->with($this->callback(fn(LeadFilter $filter) => $filter->status === LeadStatus::QUALIFIED
                && $filter->source === 'referral'
                && $filter->search === 'acme'
                && $filter->perPage === 25))
            ->willReturn($this->emptyPage());

        $result = $this->makeService($query)->getLeads(new LeadFilterDTO(
            status: LeadStatus::QUALIFIED,
            source: 'referral',
            search: 'acme',
            perPage: 25,
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function test_it_returns_a_failure_result_when_the_page_size_is_rejected(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->never())->method('getFilteredLeads');

        // toPerPage would normally catch this first; the Service must still
        // turn a rejection from the domain filter into a failure Result.
        $result = $this->makeService($query)->getLeads(new LeadFilterDTO(perPage: 0));

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_INVALID', $result->errorCode);
    }

    public function test_it_returns_a_failure_result_when_the_read_throws(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredLeads')
            ->willThrowException(new RuntimeException('connection lost'));

        $result = $this->makeService($query)->getLeads(new LeadFilterDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_INDEX_FAILED', $result->errorCode);
        // No exception may escape the Service.
        $this->assertSame('Failed to retrieve leads.', $result->getMessage());
    }

    public function test_it_returns_an_empty_page_without_erroring(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())->method('getFilteredLeads')->willReturn($this->emptyPage());

        $result = $this->makeService($query)->getLeads(new LeadFilterDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->getData()->items);
        $this->assertSame(0, $result->getData()->total);
    }

    public function test_it_returns_a_single_lead_as_a_response_dto(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->makeProjection(7, 'Acme Prospect'));

        $result = $this->makeService($query)->getLeadById(7);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            'Core\Lead\Application\DTOs\Responses\LeadResponseDTO',
            $result->getData()::class,
        );
        $this->assertSame(7, $result->getData()->id);
    }

    public function test_it_returns_a_not_found_failure_when_the_lead_is_missing(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())->method('findById')->willReturn(null);

        $result = $this->makeService($query)->getLeadById(404);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_NOT_FOUND', $result->errorCode);
    }

    public function test_it_returns_a_fetch_failure_when_the_read_throws(): void
    {
        $query = $this->createMock(LeadQueryContract::class);
        $query->expects($this->once())
            ->method('findById')
            ->willThrowException(new RuntimeException('connection lost'));

        $result = $this->makeService($query)->getLeadById(7);

        $this->assertTrue($result->isFailure());
        $this->assertSame('LEAD_FETCH_FAILED', $result->errorCode);
    }

    private function makeService(LeadQueryContract $query): LeadService
    {
        return new LeadService(
            $this->createMock(LeadRepositoryContract::class),
            $query,
            new LeadResponseMapper(),
            $this->createMock(QueueManagerContract::class),
        );
    }

    private function emptyPage(): PaginatedResult
    {
        return new PaginatedResult(
            items: [],
            total: 0,
            currentPage: 1,
            perPage: 15,
            lastPage: 1,
        );
    }

    private function makeProjection(int $id, string $name): LeadProjectionDTO
    {
        return new LeadProjectionDTO(
            entity: new LeadEntity(
                id: $id,
                name: $name,
                email: 'lead@example.com',
                phone: '123456',
                companyName: 'Acme',
                status: LeadStatus::NEW,
                source: 'website',
                createdAt: '2026-10-05 10:13:16',
                updatedAt: '2026-10-05 10:13:16',
            ),
            createdAt: new DateTimeImmutable('2026-10-05 10:13:16'),
            updatedAt: new DateTimeImmutable('2026-10-05 10:13:16'),
        );
    }
}
