<?php

declare(strict_types=1);

namespace Core\Company\Application\Services;

use Core\Company\Application\DTOs\Requests\StoreCompanyDTO;
use Core\Company\Application\DTOs\Requests\UpdateCompanyDTO;
use Core\Company\Application\Mappers\CompanyResponseMapper;
use Core\Company\Domain\Contracts\CompanyRepositoryContract;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\Exceptions\InvalidCompanyException;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles company write use cases.
 */
final class CompanyService
{
    private const ERROR_INVALID = 'COMPANY_INVALID';

    private const ERROR_CREATE_FAILED = 'COMPANY_CREATE_FAILED';

    private const ERROR_NOT_FOUND = 'COMPANY_NOT_FOUND';

    private const ERROR_UPDATE_FAILED = 'COMPANY_UPDATE_FAILED';

    private const ERROR_DELETE_FAILED = 'COMPANY_DELETE_FAILED';

    /**
     * @param CompanyRepositoryContract  $companyRepository
     * @param CompanyResponseMapper      $responseMapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     */
    public function __construct(
        private readonly CompanyRepositoryContract $companyRepository,
        private readonly CompanyResponseMapper $responseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Creates a company inside a transaction.
     *
     * @param StoreCompanyDTO $storeCompanyDTO
     *
     * @return Result Contains the CompanyResponseDTO on success.
     */
    public function createCompany(StoreCompanyDTO $storeCompanyDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            // The entity guards its own invariants; the DTO only carries input.
            $companyEntity = CompanyEntity::create(
                name: $storeCompanyDTO->name,
                website: $storeCompanyDTO->website,
                industry: $storeCompanyDTO->industry,
                phone: $storeCompanyDTO->phone,
                address: $storeCompanyDTO->address,
            );

            $savedCompany = $this->companyRepository->save($companyEntity);

            $companyResponseDTO = $this->responseMapper->toResponseDTO($savedCompany);

            $this->transactionManager->commit();
        } catch (InvalidCompanyException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Company creation failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the company. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }

        return Result::success(
            data: $companyResponseDTO,
            message: 'Company created successfully.',
        );
    }

    /**
     * Updates a company inside a transaction, holding a row lock from the read
     * through to the save.
     *
     * @param UpdateCompanyDTO $updateCompanyDTO
     *
     * @return Result Contains the CompanyResponseDTO on success.
     */
    public function updateCompany(UpdateCompanyDTO $updateCompanyDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $companyEntity = $this->companyRepository->findByIdWithLock($updateCompanyDTO->id);

            if ($companyEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Company not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            // Tell the entity to update itself; it guards its own invariants and
            // hands back the updated instance.
            $updatedCompany = $companyEntity->applyChanges(
                name: $updateCompanyDTO->name,
                website: $updateCompanyDTO->website,
                industry: $updateCompanyDTO->industry,
                phone: $updateCompanyDTO->phone,
                address: $updateCompanyDTO->address,
                clearWebsite: $updateCompanyDTO->clearWebsite,
                clearIndustry: $updateCompanyDTO->clearIndustry,
                clearPhone: $updateCompanyDTO->clearPhone,
                clearAddress: $updateCompanyDTO->clearAddress,
            );

            $savedCompany = $this->companyRepository->save($updatedCompany);

            $companyResponseDTO = $this->responseMapper->toResponseDTO($savedCompany);

            $this->transactionManager->commit();
        } catch (InvalidCompanyException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Company update failed.', [
                'id' => $updateCompanyDTO->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the company. Please try again.',
                errorCode: self::ERROR_UPDATE_FAILED,
            );
        }

        return Result::success(
            data: $companyResponseDTO,
            message: 'Company updated successfully.',
        );
    }

    /**
     * Deletes a company inside a transaction, holding a row lock from the read
     * through to the delete.
     *
     * @param string $id
     *
     * @return Result
     */
    public function deleteCompany(string $id): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $companyEntity = $this->companyRepository->findByIdWithLock((int) $id);

            if ($companyEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Company not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            $this->companyRepository->delete($companyEntity);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Company deletion failed.', [
                'id' => $id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to delete the company. Please try again.',
                errorCode: self::ERROR_DELETE_FAILED,
            );
        }

        return Result::success(
            data: null,
            message: 'Company deleted successfully.',
        );
    }
}
