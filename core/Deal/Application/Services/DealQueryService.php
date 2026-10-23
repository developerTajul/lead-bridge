<?php

declare(strict_types=1);

namespace Core\Deal\Application\Services;

use Core\Deal\Application\DTOs\Requests\DealFilterDTO;
use Core\Deal\Application\Mappers\DealResponseMapper;
use Core\Deal\Domain\Contracts\DealFormOptionsContract;
use Core\Deal\Domain\Contracts\DealQueryContract;
use Core\Deal\Domain\DataObjects\StoredDealData;
use Core\Deal\Domain\Exceptions\InvalidDealException;
use Core\Deal\Domain\ValueObjects\DealFilter;
use Core\Shared\Application\DTOs\Result;
use Psr\Log\LoggerInterface;

/**
 * Handles deal read use cases.
 */
final class DealQueryService
{
    private const ERROR_INVALID = 'DEAL_INVALID';

    private const ERROR_INDEX_FAILED = 'DEAL_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'DEAL_NOT_FOUND';

    private const ERROR_DETAIL_FAILED = 'DEAL_DETAIL_FAILED';

    private const ERROR_FORM_OPTIONS_FAILED = 'DEAL_FORM_OPTIONS_FAILED';

    /**
     * @param DealQueryContract       $dealQuery
     * @param DealFormOptionsContract $formOptions
     * @param DealResponseMapper      $responseMapper
     * @param LoggerInterface         $logger
     */
    public function __construct(
        private readonly DealQueryContract $dealQuery,
        private readonly DealFormOptionsContract $formOptions,
        private readonly DealResponseMapper $responseMapper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the paginated deal list matching the given criteria.
     *
     * @param DealFilterDTO $dealFilterDTO
     *
     * @return Result Contains the PaginatedResult of DealResponseDTO on success.
     */
    public function getPaginatedDeals(DealFilterDTO $dealFilterDTO): Result
    {
        try {
            $dealFilter = new DealFilter(
                pipelineId: $dealFilterDTO->pipelineId,
                dealStageId: $dealFilterDTO->dealStageId,
                perPage: $dealFilterDTO->perPage,
            );

            $paginatedDeals = $this->dealQuery->getFilteredDeals($dealFilter);

            // Entities become response DTOs here, so nothing downstream of the
            // Service ever sees a domain object.
            $mappedPaginatedResult = $paginatedDeals->map(
                fn(StoredDealData $storedDeal) => $this->responseMapper->toResponseDTO($storedDeal->deal)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Deals retrieved successfully.',
            );
        } catch (InvalidDealException $exception) {
            // A rejected filter is bad input, not a system failure.
            $this->logger->warning('Deal list rejected an invalid filter.', [
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve deals.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve deals.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns one deal with everything attached to it.
     *
     * @param string $id
     *
     * @return Result Contains the DealDetailResponseDTO on success.
     */
    public function getDealDetails(string $id): Result
    {
        try {
            $storedDeal = $this->dealQuery->findDetailById((int) $id);

            // A missing deal is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($storedDeal === null) {
                return Result::failure('Deal not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toDetailResponseDTO($storedDeal),
                message: 'Deal retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve deal.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve deal.', self::ERROR_DETAIL_FAILED);
        }
    }

    /**
     * Returns the rows the deal form offers for each of its pickers.
     *
     * @return Result Contains the DealFormOptionsResponseDTO on success.
     */
    public function getFormOptions(): Result
    {
        try {
            $formOptions = $this->formOptions->getFormOptions();

            return Result::success(
                data: $this->responseMapper->toFormOptionsResponseDTO($formOptions),
                message: 'Deal form options retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve the deal form options.', ['exception' => $exception]);

            return Result::failure(
                'Failed to retrieve the deal form options.',
                self::ERROR_FORM_OPTIONS_FAILED,
            );
        }
    }
}
