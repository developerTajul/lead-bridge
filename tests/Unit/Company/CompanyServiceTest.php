<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use Core\Company\Application\DTOs\Requests\StoreCompanyDTO;
use Core\Company\Application\Mappers\CompanyResponseMapper;
use Core\Company\Application\Services\CompanyService;
use Core\Company\Domain\Contracts\CompanyRepositoryContract;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the company creation use case with every port mocked.
 */
final class CompanyServiceTest extends TestCase
{
    private function makeStoreCompanyDTO(string $name = 'Acme Ltd'): StoreCompanyDTO
    {
        return new StoreCompanyDTO(
            name: $name,
            website: 'https://acme.test',
            industry: 'Software',
            phone: '+1 555 0100',
            address: '1 Road, Town',
        );
    }

    private function makeService(
        CompanyRepositoryContract $repository,
        ?TransactionManagerContract $transactionManager = null,
    ): CompanyService {
        return new CompanyService(
            companyRepository: $repository,
            responseMapper: new CompanyResponseMapper(),
            transactionManager: $transactionManager ?? $this->createMock(TransactionManagerContract::class),
            logger: $this->createMock(LoggerInterface::class),
        );
    }

    public function test_it_creates_a_company_and_returns_the_response_dto(): void
    {
        $repository = $this->createMock(CompanyRepositoryContract::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn(CompanyEntity $company) => $company->id === null
                    && $company->name === 'Acme Ltd'
                    && $company->website === 'https://acme.test'
            ))
            ->willReturn(CompanyEntity::reconstitute(
                id: 12,
                name: 'Acme Ltd',
                website: 'https://acme.test',
                industry: 'Software',
                phone: '+1 555 0100',
                address: '1 Road, Town',
                createdAt: '2026-10-19 08:15:00',
                updatedAt: '2026-10-19 08:15:00',
            ));

        $result = $this->makeService($repository)->createCompany($this->makeStoreCompanyDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertSame(12, $result->getData()->id);
        $this->assertSame('Acme Ltd', $result->getData()->name);
        $this->assertSame('Software', $result->getData()->industry);
        $this->assertSame('Company created successfully.', $result->getMessage());
    }

    public function test_it_commits_the_transaction_on_success(): void
    {
        $repository = $this->createMock(CompanyRepositoryContract::class);
        $repository->method('save')->willReturn(CompanyEntity::reconstitute(id: 12, name: 'Acme Ltd'));

        $transaction = $this->createMock(TransactionManagerContract::class);
        $transaction->expects($this->once())->method('beginTransaction');
        $transaction->expects($this->once())->method('commit');
        $transaction->expects($this->never())->method('rollback');

        $result = $this->makeService($repository, $transaction)->createCompany($this->makeStoreCompanyDTO());

        $this->assertTrue($result->isSuccess());
    }

    public function test_it_reports_a_broken_invariant_as_an_invalid_failure(): void
    {
        $repository = $this->createMock(CompanyRepositoryContract::class);
        // The entity rejects the input, so nothing may reach persistence.
        $repository->expects($this->never())->method('save');

        $transaction = $this->createMock(TransactionManagerContract::class);
        $transaction->expects($this->once())->method('beginTransaction');
        $transaction->expects($this->once())->method('rollback');
        $transaction->expects($this->never())->method('commit');

        $result = $this->makeService($repository, $transaction)
            ->createCompany($this->makeStoreCompanyDTO(name: '   '));

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_INVALID', $result->errorCode);
        $this->assertSame('A company must carry a name.', $result->getMessage());
    }

    public function test_it_rolls_back_and_fails_when_persistence_throws(): void
    {
        $repository = $this->createMock(CompanyRepositoryContract::class);
        $repository->method('save')->willThrowException(new RuntimeException('insert blew up'));

        $transaction = $this->createMock(TransactionManagerContract::class);
        $transaction->expects($this->once())->method('beginTransaction');
        $transaction->expects($this->once())->method('rollback');
        $transaction->expects($this->never())->method('commit');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new CompanyService(
            companyRepository: $repository,
            responseMapper: new CompanyResponseMapper(),
            transactionManager: $transaction,
            logger: $logger,
        );

        $result = $service->createCompany($this->makeStoreCompanyDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('COMPANY_CREATE_FAILED', $result->errorCode);
    }

    public function test_it_never_returns_an_entity(): void
    {
        $repository = $this->createMock(CompanyRepositoryContract::class);
        $repository->method('save')->willReturn(CompanyEntity::reconstitute(id: 12, name: 'Acme Ltd'));

        $result = $this->makeService($repository)->createCompany($this->makeStoreCompanyDTO());

        $this->assertNotInstanceOf(CompanyEntity::class, $result->getData());
        $this->assertSame(12, $result->getData()->id);
    }
}
