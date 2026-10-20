<?php

declare(strict_types=1);

namespace Core\Contact\Domain\Contracts;

use Core\Contact\Domain\DataObjects\StoredContactData;
use Core\Contact\Domain\Entities\ContactEntity;
use Core\Contact\Domain\ValueObjects\CompanyLink;

/**
 * PORT: persistence for contacts.
 */
interface ContactRepositoryContract
{
    /**
     * Persists the contact and, when a company link is given, attaches it to
     * that company on the pivot table.
     *
     * Both writes belong to one use case, so the adapter runs them inside a
     * transaction and a failure leaves neither behind.
     *
     * @param ContactEntity    $contact
     * @param CompanyLink|null $link Null when the contact is created without a company.
     *
     * @return StoredContactData The stored contact with its company links.
     */
    public function save(ContactEntity $contact, ?CompanyLink $link = null): StoredContactData;

    /**
     * Reads a contact while holding a row lock, so a concurrent write cannot
     * slip between the read and the save.
     *
     * The lock is a storage concern: callers ask for a contact they mean to
     * change, and the adapter decides how that is made safe.
     *
     * @param int $id
     *
     * @return ContactEntity|null Null when no contact carries the id.
     */
    public function findByIdWithLock(int $id): ?ContactEntity;

    /**
     * Persists changes to an existing contact and, when asked, changes the
     * company link on the pivot table in the same write.
     *
     * The contact row and its link belong to one use case, so the adapter runs
     * them inside one transaction and a failure leaves neither behind.
     *
     * @param ContactEntity    $contact
     * @param CompanyLink|null $link  The link to attach, or null when none is attached.
     * @param bool             $clearCompany Detach the contact from its company.
     *
     * @return StoredContactData The stored contact with its company links.
     */
    public function update(
        ContactEntity $contact,
        ?CompanyLink $link = null,
        bool $clearCompany = false,
    ): StoredContactData;

    /**
     * Deletes a contact.
     *
     * @param ContactEntity $contact
     *
     * @return void
     */
    public function delete(ContactEntity $contact): void;
}
