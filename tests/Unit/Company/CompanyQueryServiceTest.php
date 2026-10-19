<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Application\DTOs\Requests\CompanyFilterDTO;
use Core\Company\Application\Mappers\CompanyResponseMapper;
use Core\Company\Application\Services\CompanyQueryService;
use Core\Company\Domain\DataObjects\CompanyDetailData;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the company list use case with every port mocked.
 */
final class CompanyQueryServiceTest extends TestCase
{
    public function test_it_returns_a_paginated_result_of_response_dtos(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredCompanies')
            ->willReturn(new PaginatedResult(
                items: [$this->makeCompany(id: 12, name: 'Acme Ltd')],
                total: 1,
                currentPage: 1,
                perPage: 15,
                lastPage: 1,
            ));

        $result = $this->makeService($query)->getPaginatedCompanies(new CompanyFilterDTO());

        $this->assertTrue($result->isSuccess());

        $paginatedCompanies = $result->getData();

        $this->assertInstanceOf(PaginatedResult::class, $paginatedCompanies);
        $this->assertSame(1, $paginatedCompanies->total);
        // The Service must hand the response DTOs, never domain entities.
        $this->assertSame(
            'Core\Company\Application\DTOs\Responses\CompanyResponseDTO',
            $paginatedCompanies->items[0]::class,
        );
        $this->assertSame('Acme Ltd', $paginatedCompanies->items[0]->name);
        $this->assertSame(12, $paginatedCompanies->items[0]->id);
    }

    public function test_it_maps_the_filter_dto_onto_the_domain_filter(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredCompanies')
            ->with($this->callback(fn(CompanyFilter $filter) => $filter->search === 'acme'
                && $filter->perPage === 25))
            ->willReturn($this->emptyPage());

        $result = $this->makeService($query)->getPaginatedCompanies(new CompanyFilterDTO(
            search: 'acme',
            perPage: 25,
        ));

        $this->assertTrue($result->isSuccess());
    }

    public function test_it_returns_a_failure_result_when_the_page_size_is_rejected(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->never())->method('getFilteredCompanies');

        // The mapper would normally catch this first; the Service must still
        // turn a rejection from the domain filter into a failure Result.
        $result = $this->makeService($query)->getPaginatedCompanies(new CompanyFilterDTO(perPage: 0));

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_INVALID', $result->errorCode);
    }

    public function test_it_returns_a_failure_result_when_the_read_throws(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())
            ->method('getFilteredCompanies')
            ->willThrowException(new RuntimeException('connection lost'));

        $result = $this->makeService($query)->getPaginatedCompanies(new CompanyFilterDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_INDEX_FAILED', $result->errorCode);
        // No exception may escape the Service.
        $this->assertSame('Failed to retrieve companies.', $result->getMessage());
    }

    public function test_it_returns_an_empty_page_without_erroring(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())->method('getFilteredCompanies')->willReturn($this->emptyPage());

        $result = $this->makeService($query)->getPaginatedCompanies(new CompanyFilterDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->getData()->items);
        $this->assertSame(0, $result->getData()->total);
    }

    /**
     * A read opens no transaction: the read-side service has no transaction
     * manager to reach for in the first place.
     */
    public function test_it_never_touches_a_transaction(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->method('getFilteredCompanies')->willReturn($this->emptyPage());

        $service = $this->makeService($query);

        $this->assertInstanceOf(CompanyQueryService::class, $service);
        $this->assertTrue($service->getPaginatedCompanies(new CompanyFilterDTO())->isSuccess());
    }

    private function makeService(CompanyQueryContract $query): CompanyQueryService
    {
        return new CompanyQueryService(
            companyQuery: $query,
            responseMapper: new CompanyResponseMapper(),
            logger: $this->createMock(LoggerInterface::class),
        );
    }

    public function test_it_returns_the_company_detail_as_a_response_dto(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())
            ->method('findDetailById')
            ->with('7')
            ->willReturn($this->makeCompanyDetail());

        $result = $this->makeService($query)->getCompanyDetails('7');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Company retrieved successfully.', $result->getMessage());

        $detail = $result->getData();

        // The Service hands the response DTO, never the read model or entity.
        $this->assertSame(
            'Core\Company\Application\DTOs\Responses\CompanyDetailResponseDTO',
            $detail::class,
        );
        $this->assertSame(7, $detail->id);
        $this->assertSame('Acme Ltd', $detail->name);
    }

    /**
     * The read model arrives flattened; the mapper is the one place that
     * nests it, so the DTO's relations must come back as DTOs themselves.
     */
    public function test_it_nests_the_related_records_into_dtos(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->method('findDetailById')->willReturn($this->makeCompanyDetail());

        $detail = $this->makeService($query)->getCompanyDetails('7')->getData();

        $this->assertCount(1, $detail->contacts);
        $this->assertSame(
            'Core\Company\Application\DTOs\Responses\CompanyContactResponseDTO',
            $detail->contacts[0]::class,
        );
        $this->assertSame('Jane Doe', $detail->contacts[0]->name);
        $this->assertSame('Head of Ops', $detail->contacts[0]->job_title);
        $this->assertTrue($detail->contacts[0]->is_primary);

        $this->assertCount(1, $detail->deals);

        $deal = $detail->deals[0];

        $this->assertSame(
            'Core\Company\Application\DTOs\Responses\CompanyDealResponseDTO',
            $deal::class,
        );
        $this->assertSame('Renewal', $deal->title);
        $this->assertSame('2500.00', $deal->amount);
        $this->assertSame('2026-12-01', $deal->expected_close_date);

        $this->assertNotNull($deal->stage);
        $this->assertSame('Negotiation', $deal->stage->name);
        $this->assertSame(70, $deal->stage->probability);

        $this->assertCount(1, $deal->activities);
        $this->assertSame(
            'Core\Company\Application\DTOs\Responses\CompanyActivityResponseDTO',
            $deal->activities[0]::class,
        );
        $this->assertSame('Follow up sent', $deal->activities[0]->subject);
        $this->assertSame('Aisha Rahman', $deal->activities[0]->user_name);
    }

    public function test_it_returns_a_failure_result_when_the_company_is_missing(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())->method('findDetailById')->willReturn(null);

        $result = $this->makeService($query)->getCompanyDetails('404');

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_NOT_FOUND', $result->errorCode);
        $this->assertSame('Company not found.', $result->getMessage());
    }

    public function test_it_returns_a_failure_result_when_the_detail_read_throws(): void
    {
        $query = $this->createMock(CompanyQueryContract::class);
        $query->expects($this->once())
            ->method('findDetailById')
            ->willThrowException(new RuntimeException('connection lost'));

        $result = $this->makeService($query)->getCompanyDetails('7');

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_DETAIL_FAILED', $result->errorCode);
        // No exception may escape the Service.
        $this->assertSame('Failed to retrieve company.', $result->getMessage());
    }

    private function makeCompanyDetail(): CompanyDetailData
    {
        return new CompanyDetailData(
            company: $this->makeCompany(id: 7, name: 'Acme Ltd'),
            contacts: [[
                'id' => 3,
                'name' => 'Jane Doe',
                'email' => 'jane@acme.test',
                'phone' => '+1 555 0111',
                'job_title' => 'Head of Ops',
                'is_primary' => true,
            ]],
            deals: [[
                'id' => 11,
                'title' => 'Renewal',
                'amount' => '2500.00',
                'currency' => 'BDT',
                'expected_close_date' => '2026-12-01',
                'actual_close_date' => null,
                'stage' => [
                    'id' => 2,
                    'name' => 'Negotiation',
                    'order_position' => 3,
                    'probability' => 70,
                ],
                'activities' => [[
                    'id' => 21,
                    'type' => 'call',
                    'subject' => 'Follow up sent',
                    'description' => 'Left a voicemail.',
                    'due_date' => '2026-10-05 10:13:16',
                    'completed_at' => null,
                    'user_name' => 'Aisha Rahman',
                ]],
            ]],
            activities: [],
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

    private function makeCompany(int $id, string $name): CompanyEntity
    {
        return CompanyEntity::reconstitute(
            id: $id,
            name: $name,
            website: 'https://acme.test',
            industry: 'Software',
            phone: '+1 555 0100',
            address: '1 Road, Town',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }
}
