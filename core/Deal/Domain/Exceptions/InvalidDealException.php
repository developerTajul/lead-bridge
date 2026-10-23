<?php

declare(strict_types=1);

namespace Core\Deal\Domain\Exceptions;

use Core\Deal\Domain\DealRules;
use RuntimeException;

/**
 * Thrown when a deal would be left in an invalid state.
 */
final class InvalidDealException extends RuntimeException
{
    /**
     * Reports a missing or blank title.
     *
     * @return self
     */
    public static function emptyTitle(): self
    {
        return new self('A deal must carry a title.');
    }

    /**
     * Reports a title longer than the title column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function titleTooLong(int $length): self
    {
        return new self(sprintf(
            'A deal title cannot exceed %d characters, %d given.',
            DealRules::MAX_TITLE_LENGTH,
            $length,
        ));
    }

    /**
     * Reports an amount that does not fit the amount column.
     *
     * @param string $amount
     *
     * @return self
     */
    public static function invalidAmount(string $amount): self
    {
        return new self(sprintf(
            'A deal amount must be between %s and %s, [%s] given.',
            DealRules::MIN_AMOUNT,
            DealRules::MAX_AMOUNT,
            $amount,
        ));
    }

    /**
     * Reports a currency code that is not the three letters the column holds.
     *
     * @param string $currency
     *
     * @return self
     */
    public static function invalidCurrency(string $currency): self
    {
        return new self(sprintf(
            'A deal currency must be a %d-letter code, [%s] given.',
            DealRules::CURRENCY_LENGTH,
            $currency,
        ));
    }

    /**
     * Reports a company ID that cannot identify a company.
     *
     * @param int $companyId
     *
     * @return self
     */
    public static function invalidCompanyId(int $companyId): self
    {
        return new self(sprintf(
            'A linked company ID must be a positive integer, %d given.',
            $companyId,
        ));
    }

    /**
     * Reports a contact ID that cannot identify a contact.
     *
     * @param int $contactId
     *
     * @return self
     */
    public static function invalidContactId(int $contactId): self
    {
        return new self(sprintf(
            'A linked contact ID must be a positive integer, %d given.',
            $contactId,
        ));
    }

    /**
     * Reports a pipeline ID that cannot identify a pipeline.
     *
     * @param int $pipelineId
     *
     * @return self
     */
    public static function invalidPipelineId(int $pipelineId): self
    {
        return new self(sprintf(
            'A pipeline ID must be a positive integer, %d given.',
            $pipelineId,
        ));
    }

    /**
     * Reports a stage ID that cannot identify a stage.
     *
     * @param int $dealStageId
     *
     * @return self
     */
    public static function invalidStageId(int $dealStageId): self
    {
        return new self(sprintf(
            'A deal stage ID must be a positive integer, %d given.',
            $dealStageId,
        ));
    }

    /**
     * Reports a page size outside the range the deal list accepts.
     *
     * @param int $perPage
     *
     * @return self
     */
    public static function invalidPerPage(int $perPage): self
    {
        return new self(sprintf(
            'A deal page size must be between %d and %d, %d given.',
            DealRules::MIN_PER_PAGE,
            DealRules::MAX_PER_PAGE,
            $perPage,
        ));
    }

    /**
     * Reports a close date that is not a date.
     *
     * @param string $closeDate
     *
     * @return self
     */
    public static function invalidCloseDate(string $closeDate): self
    {
        return new self(sprintf(
            'A deal close date must be a date in Y-m-d form, [%s] given.',
            $closeDate,
        ));
    }

    /**
     * Reports a stage that does not belong to the deal's pipeline.
     *
     * @param int $dealStageId
     * @param int $pipelineId
     *
     * @return self
     */
    public static function stageNotInPipeline(int $dealStageId, int $pipelineId): self
    {
        return new self(sprintf(
            'The stage %d does not belong to pipeline %d, so the deal cannot sit in it.',
            $dealStageId,
            $pipelineId,
        ));
    }
}
