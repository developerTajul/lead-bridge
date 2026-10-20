<?php

declare(strict_types=1);

namespace Core\Contact\Application\Services;

use Core\Contact\Application\DTOs\Requests\StoreContactDTO;
use Core\Contact\Application\DTOs\Requests\UpdateContactDTO;
use Core\Contact\Application\Mappers\ContactResponseMapper;
use Core\Contact\Domain\Contracts\ContactRepositoryContract;
use Core\Contact\Domain\Entities\ContactEntity;
use Core\Contact\Domain\Exceptions\InvalidContactException;
use Core\Contact\Domain\ValueObjects\CompanyLink;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles contact write use cases.
 */
final class ContactService
{
    private const ERROR_INVALID = 'CONTACT_INVALID';

    private const ERROR_CREATE_FAILED = 'CONTACT_CREATE_FAILED';

    private const ERROR_NOT_FOUND = 'CONTACT_NOT_FOUND';

    private const ERROR_UPDATE_FAILED = 'CONTACT_UPDATE_FAILED';

    private const ERROR_DELETE_FAILED = 'CONTACT_DELETE_FAILED';

    /**
     * @param ContactRepositoryContract  $contactRepository
     * @param ContactResponseMapper      $responseMapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     */
    public function __construct(
        private readonly ContactRepositoryContract $contactRepository,
        private readonly ContactResponseMapper $responseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Creates a contact, attaching it to a company when the payload names one.
     *
     * The entity guards the contact's own invariants and the CompanyLink value
     * object guards the pivot values; the DTO only carries input.
     *
     * @param StoreContactDTO $storeContactDTO
     *
     * @return Result Contains the ContactResponseDTO on success.
     */
    public function createContact(StoreContactDTO $storeContactDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $contactEntity = ContactEntity::create(
                name: $storeContactDTO->name,
                email: $storeContactDTO->email,
                phone: $storeContactDTO->phone,
            );

            $companyLink = $this->toCompanyLink($storeContactDTO);

            $savedContact = $this->contactRepository->save($contactEntity, $companyLink);

            $contactResponseDTO = $this->responseMapper->toResponseDTO($savedContact);

            $this->transactionManager->commit();
        } catch (InvalidContactException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Contact creation failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the contact. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }

        return Result::success(
            data: $contactResponseDTO,
            message: 'Contact created successfully.',
        );
    }

    /**
     * Updates a contact inside a transaction, holding a row lock from the read
     * through to the save.
     *
     * @param UpdateContactDTO $updateContactDTO
     *
     * @return Result Contains the ContactResponseDTO on success.
     */
    public function updateContact(UpdateContactDTO $updateContactDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $contactEntity = $this->contactRepository->findByIdWithLock($updateContactDTO->id);

            if ($contactEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Contact not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            // Tell the entity to update itself; it guards its own invariants and
            // hands back the updated instance.
            $updatedContact = $contactEntity->applyChanges(
                name: $updateContactDTO->name,
                email: $updateContactDTO->email,
                phone: $updateContactDTO->phone,
                clearPhone: $updateContactDTO->clearPhone,
            );

            // The company link is not part of the entity's own state, so it
            // runs through its own behaviour method. It still runs before the
            // write, so a rejected payload is refused before anything lands.
            $updatedContact = $updatedContact->applyCompanyChange(
                companyLink: $updateContactDTO->companyLink,
                clearCompany: $updateContactDTO->clearCompany,
            );

            $savedContact = $this->contactRepository->update(
                contact: $updatedContact,
                link: $updateContactDTO->companyLink,
                clearCompany: $updateContactDTO->clearCompany,
            );

            $contactResponseDTO = $this->responseMapper->toResponseDTO($savedContact);

            $this->transactionManager->commit();
        } catch (InvalidContactException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Contact update failed.', [
                'id' => $updateContactDTO->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the contact. Please try again.',
                errorCode: self::ERROR_UPDATE_FAILED,
            );
        }

        return Result::success(
            data: $contactResponseDTO,
            message: 'Contact updated successfully.',
        );
    }

    /**
     * Deletes a contact inside a transaction, holding a row lock from the read
     * through to the delete.
     *
     * @param int $id
     *
     * @return Result
     */
    public function deleteContact(int $id): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $contactEntity = $this->contactRepository->findByIdWithLock($id);

            if ($contactEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Contact not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            $this->contactRepository->delete($contactEntity);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Contact deletion failed.', [
                'id' => $id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to delete the contact. Please try again.',
                errorCode: self::ERROR_DELETE_FAILED,
            );
        }

        return Result::success(
            data: null,
            message: 'Contact deleted successfully.',
        );
    }

    /**
     * Builds the company link the payload asks for, or null when it names no
     * company. The job title and the primary flag are meaningless without one,
     * so they are dropped here rather than carried as loose values.
     *
     * @param StoreContactDTO $storeContactDTO
     *
     * @return CompanyLink|null
     *
     * @throws InvalidContactException When the link values would break an invariant.
     */
    private function toCompanyLink(StoreContactDTO $storeContactDTO): ?CompanyLink
    {
        if ($storeContactDTO->companyId === null) {
            return null;
        }

        return CompanyLink::create(
            companyId: $storeContactDTO->companyId,
            jobTitle: $storeContactDTO->jobTitle,
            isPrimary: $storeContactDTO->isPrimary,
        );
    }
}
