<?php

declare(strict_types=1);

namespace Core\Contact\Application\Services;

use Core\Contact\Application\DTOs\Requests\ContactFilterDTO;
use Core\Contact\Application\Mappers\ContactResponseMapper;
use Core\Contact\Domain\Contracts\ContactQueryContract;
use Core\Contact\Domain\DataObjects\StoredContactData;
use Core\Contact\Domain\Exceptions\InvalidContactException;
use Core\Contact\Domain\ValueObjects\ContactFilter;
use Core\Shared\Application\DTOs\Result;
use Psr\Log\LoggerInterface;

/**
 * Handles contact read use cases.
 */
final class ContactQueryService
{
    private const ERROR_INVALID = 'CONTACT_INVALID';

    private const ERROR_INDEX_FAILED = 'CONTACT_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'CONTACT_NOT_FOUND';

    private const ERROR_DETAIL_FAILED = 'CONTACT_DETAIL_FAILED';

    /**
     * @param ContactQueryContract   $contactQuery
     * @param ContactResponseMapper  $responseMapper
     * @param LoggerInterface        $logger
     */
    public function __construct(
        private readonly ContactQueryContract $contactQuery,
        private readonly ContactResponseMapper $responseMapper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the paginated contact list matching the given criteria.
     *
     * @param ContactFilterDTO $contactFilterDTO
     *
     * @return Result Contains the PaginatedResult of ContactResponseDTO on success.
     */
    public function getPaginatedContacts(ContactFilterDTO $contactFilterDTO): Result
    {
        try {
            $contactFilter = new ContactFilter(
                search: $contactFilterDTO->search,
                perPage: $contactFilterDTO->perPage,
            );

            $paginatedContacts = $this->contactQuery->getFilteredContacts($contactFilter);

            // Entities become response DTOs here, so nothing downstream of the
            // Service ever sees a domain object.
            $mappedPaginatedResult = $paginatedContacts->map(
                fn(StoredContactData $storedContact) => $this->responseMapper->toResponseDTO($storedContact)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Contacts retrieved successfully.',
            );
        } catch (InvalidContactException $exception) {
            // A rejected filter is bad input, not a system failure.
            $this->logger->warning('Contact list rejected an invalid filter.', [
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve contacts.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve contacts.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns one contact's full profile.
     *
     * @param string $id
     *
     * @return Result Contains the ContactDetailResponseDTO on success.
     */
    public function getContactDetails(string $id): Result
    {
        try {
            $contactDetail = $this->contactQuery->findDetailById((int) $id);

            // A missing contact is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($contactDetail === null) {
                return Result::failure('Contact not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toDetailResponseDTO($contactDetail),
                message: 'Contact retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve contact.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve contact.', self::ERROR_DETAIL_FAILED);
        }
    }
}
