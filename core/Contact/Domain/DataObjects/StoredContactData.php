<?php

declare(strict_types=1);

namespace Core\Contact\Domain\DataObjects;

use Core\Contact\Domain\Entities\ContactEntity;

/**
 * A stored contact together with the companies it is attached to. The links
 * are flattened rows, so nothing above Infrastructure has to know how the
 * pivot is read.
 */
final readonly class StoredContactData
{
    /**
     * @param ContactEntity              $contact
     * @param array<int, array<string, mixed>> $companies
     */
    public function __construct(
        public ContactEntity $contact,
        public array $companies = [],
    ) {}
}
