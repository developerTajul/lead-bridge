<?php

declare(strict_types=1);

namespace Core\Pipeline\Domain\Exceptions;

use Core\Pipeline\Domain\PipelineRules;
use RuntimeException;

/**
 * Thrown when a pipeline would be left in an invalid state.
 */
final class InvalidPipelineException extends RuntimeException
{
    /**
     * Reports a missing or blank name.
     *
     * @return self
     */
    public static function emptyName(): self
    {
        return new self('A pipeline must carry a name.');
    }

    /**
     * Reports a name longer than the name column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function nameTooLong(int $length): self
    {
        return new self(sprintf(
            'A pipeline name cannot exceed %d characters, %d given.',
            PipelineRules::MAX_NAME_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a pipeline built without any stage.
     *
     * @return self
     */
    public static function noStages(): self
    {
        return new self('A pipeline must carry at least one stage.');
    }

    /**
     * Reports a stage with a missing or blank name.
     *
     * @return self
     */
    public static function emptyStageName(): self
    {
        return new self('A stage must carry a name.');
    }

    /**
     * Reports a stage name longer than the name column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function stageNameTooLong(int $length): self
    {
        return new self(sprintf(
            'A stage name cannot exceed %d characters, %d given.',
            PipelineRules::MAX_STAGE_NAME_LENGTH,
            $length,
        ));
    }

    /**
     * Reports an order position below the first slot.
     *
     * @param int $orderPosition
     *
     * @return self
     */
    public static function invalidOrderPosition(int $orderPosition): self
    {
        return new self(sprintf(
            'A stage order position must be at least %d, %d given.',
            PipelineRules::MIN_ORDER_POSITION,
            $orderPosition,
        ));
    }

    /**
     * Reports a probability outside the percentage range.
     *
     * @param int $probability
     *
     * @return self
     */
    public static function invalidProbability(int $probability): self
    {
        return new self(sprintf(
            'A stage probability must be between %d and %d, %d given.',
            PipelineRules::MIN_PROBABILITY,
            PipelineRules::MAX_PROBABILITY,
            $probability,
        ));
    }

    /**
     * Reports two stages sharing a name. The stages table is unique on
     * (pipeline_id, name), so the pair cannot both be written.
     *
     * @param string $stageName
     *
     * @return self
     */
    public static function duplicateStageName(string $stageName): self
    {
        return new self(sprintf(
            'A pipeline cannot hold two stages named [%s].',
            $stageName,
        ));
    }

    /**
     * Reports two stages sharing an order position, which would leave their
     * order undefined.
     *
     * @param int $orderPosition
     *
     * @return self
     */
    public static function duplicateOrderPosition(int $orderPosition): self
    {
        return new self(sprintf(
            'A pipeline cannot hold two stages at order position %d.',
            $orderPosition,
        ));
    }

    /**
     * Reports a stored row that does not carry its identifier.
     *
     * @return self
     */
    public static function missingId(): self
    {
        return new self('A stored pipeline must carry an identifier.');
    }
}
