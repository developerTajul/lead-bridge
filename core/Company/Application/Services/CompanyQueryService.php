<?php

declare(strict_types=1);

namespace Core\Company\Application\Services;

use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Application\DTOs\Requests\CompanyFilterDTO;
use Core\Company\Application\Mappers\CompanyResponseMapper;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\Exceptions\InvalidCompanyException;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use Core\Shared\Application\DTOs\Result;
use Psr\Log\LoggerInterface;

/**
 * Handles company read use cases.
 */
final class CompanyQueryService
{
    private const ERROR_INVALID = 'COMPANY_INVALID';

    private const ERROR_INDEX_FAILED = 'COMPANY_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'COMPANY_NOT_FOUND';

    private const ERROR_DETAIL_FAILED = 'COMPANY_DETAIL_FAILED';

    /**
     * @param CompanyQueryContract  $companyQuery
     * @param CompanyResponseMapper $responseMapper
     * @param LoggerInterface       $logger
     */
    public function __construct(
        private readonly CompanyQueryContract $companyQuery,
        private readonly CompanyResponseMapper $responseMapper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the paginated company list matching the given criteria.
     *
     * @param CompanyFilterDTO $companyFilterDTO
     *
     * @return Result Contains the PaginatedResult of CompanyResponseDTO on success.
     */
    public function getPaginatedCompanies(CompanyFilterDTO $companyFilterDTO): Result
    {
        try {
            $companyFilter = new CompanyFilter(
                search: $companyFilterDTO->search,
                perPage: $companyFilterDTO->perPage,
            );

            $paginatedCompanies = $this->companyQuery->getFilteredCompanies($companyFilter);

            // Entities become response DTOs here, so nothing downstream of the
            // Service ever sees a domain object.
            $mappedPaginatedResult = $paginatedCompanies->map(
                fn(CompanyEntity $company) => $this->responseMapper->toResponseDTO($company)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Companies retrieved successfully.',
            );
        } catch (InvalidCompanyException $exception) {
            // A rejected filter is bad input, not a system failure.
            $this->logger->warning('Company list rejected an invalid filter.', [
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve companies.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve companies.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns one company's stored values for an edit form to prefill.
     *
     * @param string $id
     *
     * @return Result Contains the CompanyEditResponseDTO on success.
     */
    public function getCompanyForEdit(string $id): Result
    {
        try {
            $companyEntity = $this->companyQuery->findById((int) $id);

            // A missing company is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($companyEntity === null) {
                return Result::failure('Company not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toEditResponseDTO($companyEntity),
                message: 'Company retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve company for edit.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve company.', self::ERROR_DETAIL_FAILED);
        }
    }

    /**
     * Returns one company's 360-degree profile.
     *
     * @param string $id
     *
     * @return Result Contains the CompanyDetailResponseDTO on success.
     */
    public function getCompanyDetails(string $id): Result
    {
        try {
            $companyDetail = $this->companyQuery->findDetailById($id);

            // A missing company is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($companyDetail === null) {
                return Result::failure('Company not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toDetailResponseDTO($companyDetail),
                message: 'Company retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve company.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve company.', self::ERROR_DETAIL_FAILED);
        }
    }
}
